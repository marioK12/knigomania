<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// uploads dir
$IMG_DIR_FS = rtrim(__DIR__, "/\\") . "/giftIMG/";
if (!is_dir($IMG_DIR_FS)) { @mkdir($IMG_DIR_FS, 0775, true); }

// MAX images (total per listing)
$MAX_IMAGES = 6;

// current user
$me = current_user();
$meId = (int)($me["id"] ?? 0);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { http_response_code(404); echo "Not found"; exit; }

// load listing (owner only)
$st = $pdo->prepare("
  SELECT g.*, u.name AS user_name, u.email AS user_email
  FROM gift_books g
  LEFT JOIN users u ON u.id = g.user_id
  WHERE g.id = ?
  LIMIT 1
");
$st->execute([$id]);
$it = $st->fetch(PDO::FETCH_ASSOC);
if (!$it) { http_response_code(404); echo "Not found"; exit; }

$userId = (int)($it["user_id"] ?? 0);
if ($userId !== $meId) { http_response_code(403); echo "Forbidden"; exit; }

// helpers
function clean_filename(string $name): string {
  $name = preg_replace('~[^\w\-.]+~u', '_', $name);
  $name = trim($name, "._- ");
  if ($name === "") $name = "img";
  return $name;
}
function ext_ok(string $ext): bool {
  $ext = strtolower($ext);
  return in_array($ext, ["jpg","jpeg","png","webp"], true);
}

$errors = [];
$flash_ok = "";

/* =========================================================
   ACTIONS
========================================================= */

// delete single image
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST["delete_img_id"])) {
  $imgId = (int)$_POST["delete_img_id"];

  $stImg = $pdo->prepare("SELECT id, file_name FROM gift_book_images WHERE id=? AND gift_book_id=? LIMIT 1");
  $stImg->execute([$imgId, $id]);
  $row = $stImg->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $pdo->prepare("DELETE FROM gift_book_images WHERE id=?")->execute([$imgId]);
    $fn = (string)$row["file_name"];
    $path = rtrim(__DIR__, "/\\") . "/giftIMG/" . $fn;
    if (is_file($path)) { @unlink($path); }
    header("Location: giftbook_edit.php?id=".$id."&ok=imgdel");
    exit;
  } else {
    header("Location: giftbook_edit.php?id=".$id."&err=img");
    exit;
  }
}

// mark closed
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST["mark_closed"])) {
  $pdo->prepare("UPDATE gift_books SET status='closed' WHERE id=? AND user_id=? LIMIT 1")
      ->execute([$id, $meId]);

  header("Location: giftbook_edit.php?id=".$id."&ok=closed");
  exit;
}

// delete listing (and all images/files)
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST["delete_listing"])) {

  // files
  $stF = $pdo->prepare("SELECT file_name FROM gift_book_images WHERE gift_book_id=?");
  $stF->execute([$id]);
  $files = $stF->fetchAll(PDO::FETCH_COLUMN);

  // delete db rows
  $pdo->prepare("DELETE FROM gift_book_images WHERE gift_book_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM gift_books WHERE id=? AND user_id=? LIMIT 1")->execute([$id, $meId]);

  // delete files
  foreach ($files as $fn) {
    $path = rtrim(__DIR__, "/\\") . "/giftIMG/" . (string)$fn;
    if (is_file($path)) @unlink($path);
  }

  header("Location: giftbooks.php");
  exit;
}

// save details + optional upload
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST["save_giftbook"])) {

  $title  = trim((string)($_POST["title"] ?? ""));
  $author = trim((string)($_POST["author"] ?? ""));
  $city   = trim((string)($_POST["city"] ?? ""));
  $cond   = trim((string)($_POST["condition"] ?? "good"));
  $status = trim((string)($_POST["status"] ?? "active"));
  $desc   = trim((string)($_POST["description"] ?? ""));

  if ($title === "") $errors[] = "Заглавието е задължително.";
  if (!in_array($cond, ["new","like_new","good","fair","poor"], true)) $cond = "good";
  if (!in_array($status, ["active","closed"], true)) $status = "active";

  // update details
  if (!$errors) {
    $pdo->prepare("
      UPDATE gift_books
      SET title=?, author=?, city=?, `condition`=?, `status`=?, description=?
      WHERE id=? AND user_id=?
      LIMIT 1
    ")->execute([$title, $author, $city, $cond, $status, $desc, $id, $meId]);
  }

  // upload images (optional) + MAX 6 TOTAL
  if (!$errors && !empty($_FILES["images"]) && is_array($_FILES["images"]["name"])) {

    // existing count
    $stCnt = $pdo->prepare("SELECT COUNT(*) FROM gift_book_images WHERE gift_book_id=?");
    $stCnt->execute([$id]);
    $existing = (int)$stCnt->fetchColumn();

    $slots = $MAX_IMAGES - $existing;
    if ($slots <= 0) {
      $errors[] = "Достигнат е максимумът от {$MAX_IMAGES} снимки.";
    } else {
      // selected (non-empty)
      $selected = 0;
      $countAll = count($_FILES["images"]["name"]);
      for ($i=0; $i<$countAll; $i++){
        $err = $_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_NO_FILE) $selected++;
      }
      if ($selected > $slots) {
        $errors[] = "Можеш да качиш още най-много {$slots} снимки (общо максимум {$MAX_IMAGES}).";
      }
    }

    if (!$errors) {
      // next sort_order
      $stMax = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM gift_book_images WHERE gift_book_id=?");
      $stMax->execute([$id]);
      $sort = (int)$stMax->fetchColumn();

      $count = count($_FILES["images"]["name"]);
      $uploadedNow = 0;

      for ($i=0; $i<$count; $i++) {

        if ($uploadedNow >= $slots) break;

        $err = $_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "Грешка при качване на файл."; continue; }

        $tmp  = $_FILES["images"]["tmp_name"][$i] ?? "";
        $orig = (string)($_FILES["images"]["name"][$i] ?? "img");
        $size = (int)($_FILES["images"]["size"][$i] ?? 0);

        if ($size > 6 * 1024 * 1024) { $errors[] = "Файлът е твърде голям (макс 6MB)."; continue; }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!ext_ok($ext)) { $errors[] = "Невалиден формат (само JPG/PNG/WEBP)."; continue; }

        $baseName = clean_filename(pathinfo($orig, PATHINFO_FILENAME));
        $newName  = $baseName . "_" . $id . "_" . time() . "_" . $i . "." . $ext;
        $dest     = $IMG_DIR_FS . $newName;

        if (!move_uploaded_file($tmp, $dest)) {
          $errors[] = "Не успях да запиша файла.";
          continue;
        }

        $sort++;
        $pdo->prepare("INSERT INTO gift_book_images (gift_book_id, file_name, sort_order) VALUES (?,?,?)")
            ->execute([$id, $newName, $sort]);

        $uploadedNow++;
      }
    }
  }

  if ($errors) {
    // reload for rendering
    $st->execute([$id]);
    $it = $st->fetch(PDO::FETCH_ASSOC);
  } else {
    header("Location: giftbook_edit.php?id=".$id."&ok=1");
    exit;
  }
}

// load images
$stImgs = $pdo->prepare("
  SELECT id, file_name, sort_order
  FROM gift_book_images
  WHERE gift_book_id=?
  ORDER BY sort_order ASC, id ASC
");
$stImgs->execute([$id]);
$imgs = $stImgs->fetchAll(PDO::FETCH_ASSOC);

// values
$title  = (string)($it["title"] ?? "");
$author = (string)($it["author"] ?? "");
$city   = (string)($it["city"] ?? "");
$cond   = (string)($it["condition"] ?? "good");
$status = (string)($it["status"] ?? "active");
$desc   = (string)($it["description"] ?? "");

$page_title = "Редакция — Подари книга";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout gb-edit">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>giftbook_edit.css?v=2">

<main class="km-main py-4 py-md-5">
  <div class="container" style="max-width: 1000px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h1 class="h4 mb-1">✏️ Редакция на обява</h1>
        <div class="text-muted small">Промени данните и снимките (общо до <?= (int)$MAX_IMAGES ?>).</div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= e($base) ?>giftbooks.php">← Назад</a>
    </div>

    <?php if (!empty($_GET["ok"])): ?>
      <div id="msg" class="mt-3 p-3 rounded-3 bg-success-subtle border border-success">
        <b>✅</b>
        <?php
          $ok = (string)$_GET["ok"];
          if ($ok === "imgdel") echo "Снимката е изтрита.";
          elseif ($ok === "closed") echo "Обявата е маркирана като затворена.";
          else echo "Промените са запазени.";
        ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET["err"]) || $errors): ?>
      <div id="msg" class="mt-3 p-3 rounded-3 bg-danger-subtle border border-danger">
        <b>❌</b>
        <?php if (!empty($_GET["err"]) && (string)$_GET["err"] === "img"): ?>
          Снимката не е намерена.
        <?php else: ?>
          <div style="font-weight:900; margin-bottom:6px;">Има проблем:</div>
          <ul style="margin:0;">
            <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- MAIN FORM (details + upload) -->
    <form id="editForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="save_giftbook" value="1">

      <!-- DETAILS -->
      <div class="card p-3 p-md-4">
        <h2 class="h6 mb-2">Детайли</h2>
        <hr class="my-3">

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Заглавие *</label>
            <input class="form-control" name="title" value="<?= e($title) ?>" maxlength="200" required>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Автор на книгата</label>
            <input class="form-control" name="author" value="<?= e($author) ?>" maxlength="200">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Град</label>
            <input class="form-control" name="city" value="<?= e($city) ?>" maxlength="80">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Състояние</label>
            <select class="form-select" name="condition">
              <option value="new"      <?= $cond==="new" ? "selected" : "" ?>>Нова</option>
              <option value="like_new" <?= $cond==="like_new" ? "selected" : "" ?>>Като нова</option>
              <option value="good"     <?= $cond==="good" ? "selected" : "" ?>>Добра</option>
              <option value="fair"     <?= $cond==="fair" ? "selected" : "" ?>>Задоволителна</option>
              <option value="poor"     <?= $cond==="poor" ? "selected" : "" ?>>Лоша</option>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Статус</label>
            <select class="form-select" name="status">
              <option value="active" <?= $status==="active" ? "selected" : "" ?>>Активна</option>
              <option value="closed" <?= $status==="closed" ? "selected" : "" ?>>Затворена</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Описание</label>
            <textarea class="form-control" name="description" rows="6" maxlength="5000"><?= e($desc) ?></textarea>
            <div class="form-text">Можеш да добавиш кратко описание.</div>
          </div>

          <div class="col-12 d-grid d-md-flex gap-2 mt-2">
            <button class="btn btn-warning btn-lg" type="submit" id="btnSave">Запази</button>
            <a class="btn btn-outline-secondary btn-lg" href="<?= e($base) ?>giftbook_view.php?id=<?= (int)$id ?>">Виж обявата</a>
          </div>
        </div>
      </div>

      <!-- IMAGES (LAST) -->
      <div class="card p-3 p-md-4 mt-4">
        <h2 class="h6 mb-2">Снимки</h2>
        <div class="text-muted small mb-3">Макс <?= (int)$MAX_IMAGES ?> снимки общо • JPG/PNG/WEBP до 6MB • Изтрий със ✕</div>

        <div class="d-flex flex-wrap gap-2" id="imgs">
          <?php if (!$imgs): ?>
            <div class="text-muted" style="font-weight:800;">Нямаш качени снимки.</div>
          <?php else: ?>
            <?php foreach ($imgs as $im): ?>
              <?php
                $fn  = (string)$im["file_name"];
                $url = $base . "giftIMG/" . rawurlencode($fn);
              ?>
              <div class="position-relative">
                 <img src="<?= e($url) ?>" class="img-thumbnail" alt="Снимка">
                  <button
                  type="submit"
                  name="delete_img_id"
                  value="<?= (int)$im["id"] ?>"
                  class="btn btn-sm btn-danger"
                  title="Изтрий"
                  formnovalidate
                  onclick="return confirm('Да изтрием ли тази снимка?');"
                  style="position:absolute; top:0; right:0; margin:6px;"
                >✕</button>
              </div>

            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <hr class="my-3">

        <div id="addImgsForm" class="d-grid gap-2">
          <input id="newImages" class="form-control" type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp">
          <div class="form-text">Избери снимки и натисни „Запази“ (качват се заедно с промените).</div>
          <button class="btn btn-primary" type="submit" id="btnAddImgs">Качи снимки</button>
        </div>
      </div>
    </form>

    <!-- STATUS -->
    <div class="card p-3 p-md-4 mt-4">
      <h2 class="h6 mb-2">Статус</h2>
      <div class="d-grid d-md-flex gap-2">
        <form method="post" onsubmit="return confirm('Да маркираме ли обявата като затворена?');">
          <button class="btn btn-outline-success" type="submit" name="mark_closed" value="1">✅ Маркирай като затворена</button>
        </form>

        <form method="post" onsubmit="return confirm('Сигурен ли си, че искаш да изтриеш обявата?');">
          <button class="btn btn-outline-danger" type="submit" name="delete_listing" value="1">🗑️ Изтрий обявата</button>
        </form>
      </div>
      <div class="text-muted small mt-2">Тези действия са само за собственика.</div>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  requestAnimationFrame(() => document.body.classList.add("gb-animate"));
});
</script>

</body>
</html>
