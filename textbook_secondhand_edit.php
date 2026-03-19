<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }
$me = current_user();
$meId = (int)$me["id"];

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$IMG_URL = $base . "usedIMG/";
$IMG_DIR_FS = rtrim(__DIR__, "/\\") . "/usedIMG/";
if (!is_dir($IMG_DIR_FS)) { @mkdir($IMG_DIR_FS, 0775, true); }

// constants
$CATEGORY = "Учебници";
$MAX_FILES = 6;

// subjects by grade group (slugs)
$SUBJECTS_BY_GRADE = [
  "1-4" => [
    "bg"   => "Български език",
    "math" => "Математика",
    "os"   => "Околен свят",
    "music"=> "Музика",
    "art"  => "Изобразително изкуство",
    "tech" => "Технологии",
    "eng"  => "Английски език",
    "civ"  => "Човекът и обществото",
    "nat"  => "Човекът и природата",
  ],
  "5-7" => [
    "bg"   => "Български език",
    "lit"  => "Литература",
    "math" => "Математика",
    "eng"  => "Английски език",
    "hist" => "История",
    "geo"  => "География",
    "bio"  => "Биология",
    "phys" => "Физика",
    "chem" => "Химия",
    "it"   => "Информационни технологии",
    "music"=> "Музика",
    "art"  => "Изобразително изкуство",
    "tech" => "Технологии и предприемачество",
  ],
  "8-12" => [
    "bg"   => "Български език",
    "lit"  => "Литература",
    "math" => "Математика",
    "eng"  => "Английски език",
    "hist" => "История",
    "geo"  => "География",
    "bio"  => "Биология",
    "phys" => "Физика",
    "chem" => "Химия",
    "it"   => "Информационни технологии",
    "phil" => "Философия",
    "civ"  => "Гражданско образование",
  ],
];

// helpers
function postv($k){ return trim((string)($_POST[$k] ?? "")); }
function safe_price($v){
  $v = str_replace(",", ".", trim((string)$v));
  if ($v === "") return 0.0;
  return (float)$v;
}
function is_img_ext($ext){
  $ext = strtolower($ext);
  return in_array($ext, ["jpg","jpeg","png","webp"], true);
}
function has_letter($s): bool {
  return (bool)preg_match('/[A-Za-zА-Яа-яЁёІіЇїЄє]/u', (string)$s);
}

// id
$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { header("Location: ".$base."textbooks_secondhand.php"); exit; }

// load item
$st = $pdo->prepare("
  SELECT *
  FROM used_books
  WHERE id = ? AND category = ?
  LIMIT 1
");
$st->execute([$id, $CATEGORY]);
$item = $st->fetch(PDO::FETCH_ASSOC);

if (!$item) {
  header("Location: ".$base."textbooks_secondhand.php");
  exit;
}

// permission: owner or admin
$role = (string)($me["role"] ?? "user");
$isOwner = ((int)($item["user_id"] ?? 0) === $meId);
if (!$isOwner && $role !== "admin") {
  header("Location: ".$base."textbook_secondhand_view.php?id=".$id);
  exit;
}

// fetch images (id + file)
$stImgs = $pdo->prepare("
  SELECT id, file_name, sort_order
  FROM used_book_images
  WHERE used_book_id = ?
  ORDER BY sort_order ASC, id ASC
");
$stImgs->execute([$id]);
$imgs = $stImgs->fetchAll(PDO::FETCH_ASSOC);

// form values (prefill)
$title      = (string)($item["title"] ?? "");
$author     = (string)($item["author"] ?? "");
$desc       = (string)($item["description"] ?? "");
$city       = (string)($item["city"] ?? "");
$phone      = (string)($item["phone"] ?? "");
$price      = (string)($item["price"] ?? "");
$condition  = (string)($item["condition"] ?? "good");
$grade_band = (string)($item["grade_band"] ?? "1-4");
$subject    = (string)($item["subject"] ?? "bg");

$err = "";

// normalize grade/subject
if (!isset($SUBJECTS_BY_GRADE[$grade_band])) $grade_band = "1-4";
if (!isset($SUBJECTS_BY_GRADE[$grade_band][$subject])) {
  $subject = array_key_first($SUBJECTS_BY_GRADE[$grade_band]) ?: "bg";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $action = postv("action");
  if ($action === "") $action = "save";

  // ✅ ACTION: SOLD
  if ($action === "sold") {
    try{
      $stS = $pdo->prepare("
        UPDATE used_books
        SET status = 'sold', updated_at = NOW()
        WHERE id = ? AND category = ?
        LIMIT 1
      ");
      $stS->execute([$id, $CATEGORY]);
      header("Location: ".$base."textbook_secondhand_view.php?id=".$id);
      exit;
    } catch (Throwable $ex) {
      $err = "Грешка: " . $ex->getMessage();
    }
  }

  // ✅ ACTION: DELETE (soft delete)
  else if ($action === "delete") {
    try{
      $stD = $pdo->prepare("
        UPDATE used_books
        SET status = 'deleted', updated_at = NOW()
        WHERE id = ? AND category = ?
        LIMIT 1
      ");
      $stD->execute([$id, $CATEGORY]);
      header("Location: ".$base."textbooks_secondhand.php");
      exit;
    } catch (Throwable $ex) {
      $err = "Грешка: " . $ex->getMessage();
    }
  }

  // ✅ ACTION: SAVE (edit + images)
  else {

    // ---- fields ----
    $title      = postv("title");
    $author     = postv("author");
    $desc       = postv("description");
    $city       = postv("city");
    $phone      = postv("phone");
    $price      = postv("price");
    $condition  = postv("condition") ?: "good";
    $grade_band = postv("grade_band") ?: "1-4";
    $subject    = postv("subject") ?: "bg";

    if (!isset($SUBJECTS_BY_GRADE[$grade_band])) $grade_band = "1-4";
    if (!isset($SUBJECTS_BY_GRADE[$grade_band][$subject])) {
      $subject = array_key_first($SUBJECTS_BY_GRADE[$grade_band]) ?: "bg";
    }

    $p = safe_price($price);

    // ---- image ops inputs ----
    $delIds = $_POST["del_img"] ?? [];
    if (!is_array($delIds)) $delIds = [];
    $delIds = array_values(array_unique(array_filter(array_map("intval", $delIds))));

    $files = $_FILES["new_images"] ?? null;

    // count existing
    $existingCount = count($imgs);

    // validate delete belongs to this book
    $existingIds = array_map(fn($r)=> (int)$r["id"], $imgs);
    $delIds = array_values(array_intersect($delIds, $existingIds));
    $afterDeleteCount = $existingCount - count($delIds);

    // new uploads count
    $newCount = 0;
    $pickedIdx = [];
    if ($files && isset($files["name"]) && is_array($files["name"])) {
      for ($i=0; $i<count($files["name"]); $i++){
        if ((string)$files["name"][$i] !== "") $pickedIdx[] = $i;
      }
      $newCount = count($pickedIdx);
    }

    // validate fields
    if ($title === "") $err = "Моля, въведи заглавие.";
    else if ($author === "") $err = "Моля, въведи автор/издателство.";
    else if ($p < 0) $err = "Невалидна цена.";
    else if (!in_array($condition, ["new","like_new","good","fair","poor"], true)) $err = "Невалидно състояние.";
    else if ($city === "") $err = "Моля, въведи град.";
    else if (!has_letter($city)) $err = "Градът трябва да съдържа текст (букви).";
    else if ($desc === "") $err = "Моля, въведи описание.";
    else if (!has_letter($desc)) $err = "Описанието трябва да съдържа текст (букви), не само цифри/символи.";
    else {

      // validate images constraints
      $totalAfter = $afterDeleteCount + $newCount;

      if ($totalAfter <= 0) {
        $err = "Трябва да остане поне 1 снимка.";
      } else if ($totalAfter > $MAX_FILES) {
        $err = "Максимум {$MAX_FILES} снимки общо. Премахни някои или качи по-малко.";
      } else {

        // validate each new file
        if ($newCount > 0) {
          $names = $files["name"];
          $errs  = $files["error"];
          $sizes = $files["size"];

          foreach ($pickedIdx as $i){
            if ($errs[$i] !== UPLOAD_ERR_OK) { $err = "Грешка при качване на снимка."; break; }
            if ($sizes[$i] > 8 * 1024 * 1024) { $err = "Снимка е твърде голяма (макс 8MB)."; break; }
            $ext = strtolower(pathinfo((string)$names[$i], PATHINFO_EXTENSION));
            if (!is_img_ext($ext)) { $err = "Позволени формати: jpg, jpeg, png, webp."; break; }
          }
        }

        if ($err === "") {
          try{
            $pdo->beginTransaction();

            // update book
            $stU = $pdo->prepare("
              UPDATE used_books
              SET
                title = :title,
                author = :author,
                grade_band = :gb,
                subject = :sub,
                description = :descr,
                price = :price,
                `condition` = :cond,
                city = :city,
                phone = :phone,
                updated_at = NOW()
              WHERE id = :id AND category = :cat
              LIMIT 1
            ");
            $stU->execute([
              ":title" => $title,
              ":author" => $author,
              ":gb" => $grade_band,
              ":sub" => $subject,
              ":descr" => $desc,
              ":price" => $p,
              ":cond" => $condition,
              ":city" => $city,
              ":phone" => ($phone === "" ? null : $phone),
              ":id" => $id,
              ":cat" => $CATEGORY,
            ]);

            // delete images (db + fs)
            if (!empty($delIds)) {
              $in = implode(",", array_fill(0, count($delIds), "?"));
              $stFn = $pdo->prepare("SELECT id, file_name FROM used_book_images WHERE used_book_id = ? AND id IN ($in)");
              $stFn->execute(array_merge([$id], $delIds));
              $toDel = $stFn->fetchAll(PDO::FETCH_ASSOC);

              $stDel = $pdo->prepare("DELETE FROM used_book_images WHERE used_book_id = ? AND id = ?");
              foreach ($toDel as $row) {
                $stDel->execute([$id, (int)$row["id"]]);
                $fn = (string)$row["file_name"];
                if ($fn !== "") {
                  $path = $IMG_DIR_FS . $fn;
                  if (is_file($path)) @unlink($path);
                }
              }
            }

            // add new images
            if ($newCount > 0) {
              $stMax = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM used_book_images WHERE used_book_id = ?");
              $stMax->execute([$id]);
              $sortOrder = (int)$stMax->fetchColumn() + 1;

              $insImg = $pdo->prepare("
                INSERT INTO used_book_images (used_book_id, file_name, sort_order, created_at)
                VALUES (:bid, :fn, :so, NOW())
              ");

              $names = $files["name"];
              $tmp   = $files["tmp_name"];

              foreach ($pickedIdx as $i){
                $ext = strtolower(pathinfo((string)$names[$i], PATHINFO_EXTENSION));
                $fn = "ub2_" . $id . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                $dest = $IMG_DIR_FS . $fn;

                if (!move_uploaded_file($tmp[$i], $dest)) {
                  throw new RuntimeException("Неуспешно записване на снимка.");
                }

                $insImg->execute([
                  ":bid" => $id,
                  ":fn" => $fn,
                  ":so" => $sortOrder++,
                ]);
              }
            }

            // normalize sort_order
            $stRe = $pdo->prepare("
              SELECT id
              FROM used_book_images
              WHERE used_book_id = ?
              ORDER BY sort_order ASC, id ASC
            ");
            $stRe->execute([$id]);
            $ids = $stRe->fetchAll(PDO::FETCH_COLUMN);

            $stUpSo = $pdo->prepare("UPDATE used_book_images SET sort_order = ? WHERE id = ? AND used_book_id = ?");
            $so = 0;
            foreach ($ids as $imgId) {
              $stUpSo->execute([$so++, (int)$imgId, $id]);
            }

            $pdo->commit();

            header("Location: ".$base."textbook_secondhand_view.php?id=".$id);
            exit;

          } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "Грешка: " . $ex->getMessage();
          }
        }
      }
    }
  }
}

// refresh images for UI
$stImgs->execute([$id]);
$imgs = $stImgs->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Редакция на учебник (втора ръка)";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand.css">
<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand_edit.css">

<body class="km-layout">
<?php require_once "nav.php"; ?>

<header class="tbs-hero" style="padding:40px 0 64px;">
  <div class="container tbs-hero-inner text-center">
    <div class="tbs-hero-icons" aria-hidden="true">✏️ 📘</div>
    <h1 class="tbs-hero-title" style="font-size:clamp(28px,3.6vw,44px);">Редактирай учебник</h1>
    <p class="tbs-hero-sub">Промени данните, управлявай снимки и запази.</p>
  </div>
  <div class="tbs-hero-wave" aria-hidden="true"></div>
</header>

<main class="tbs-main">
  <div class="container" style="max-width: 980px;">

    <div class="tbs-filter2" style="margin-top:-40px;">
      <?php if ($err): ?>
        <div class="alert alert-danger mb-3"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-12 col-lg-7">
          <label class="form-label tbs-label">Заглавие *</label>
          <input class="form-control tbs-input" name="title" value="<?= e($title) ?>" required>
        </div>

        <div class="col-12 col-lg-5">
          <label class="form-label tbs-label">Автор / Издателство *</label>
          <input class="form-control tbs-input" name="author" value="<?= e($author) ?>" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Клас *</label>
          <select class="form-select tbs-input" name="grade_band" id="gradeSel" required>
            <option value="1-4" <?= $grade_band==="1-4" ? "selected" : "" ?>>1–4 клас</option>
            <option value="5-7" <?= $grade_band==="5-7" ? "selected" : "" ?>>5–7 клас</option>
            <option value="8-12" <?= $grade_band==="8-12" ? "selected" : "" ?>>8–12 клас</option>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Предмет *</label>
          <select class="form-select tbs-input" name="subject" id="subjectSel" required></select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Състояние *</label>
          <select class="form-select tbs-input" name="condition" required>
            <option value="new" <?= $condition==="new" ? "selected" : "" ?>>Нова</option>
            <option value="like_new" <?= $condition==="like_new" ? "selected" : "" ?>>Като нова</option>
            <option value="good" <?= $condition==="good" ? "selected" : "" ?>>Добра</option>
            <option value="fair" <?= $condition==="fair" ? "selected" : "" ?>>Задоволителна</option>
            <option value="poor" <?= $condition==="poor" ? "selected" : "" ?>>Лоша</option>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Цена (€) *</label>
          <input class="form-control tbs-input" name="price" value="<?= e($price) ?>" inputmode="decimal" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Град *</label>
          <input
            class="form-control tbs-input"
            name="city"
            value="<?= e($city) ?>"
            required
            minlength="2"
            maxlength="60"
            pattern=".*[A-Za-zА-Яа-яЁёІіЇїЄє].*"
            title="Въведи град с букви (напр. София, Пловдив...)"
          >
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Телефон</label>
          <input class="form-control tbs-input" name="phone" value="<?= e($phone) ?>">
        </div>

        <div class="col-12">
          <label class="form-label tbs-label">Описание *</label>
          <textarea class="form-control tbs-input" name="description" rows="4" required minlength="10" maxlength="2000"
            placeholder="Напр. издание, забележки, комплект ли е..."><?= e($desc) ?></textarea>
          <div class="form-text">Минимум 10 символа.</div>
        </div>

        <!-- =========================
             IMAGES SECTION
             ========================= -->
        <div class="col-12">
          <div class="tbs-img-card">
            <div class="tbs-img-head">
              <div class="tbs-img-title">Снимки</div>
              <div class="tbs-img-sub">Трябва да остане поне 1 снимка. Макс <?= (int)$MAX_FILES ?>.</div>
            </div>

            <?php if ($imgs): ?>
              <div class="tbs-img-grid">
                <?php foreach ($imgs as $row): ?>
                  <?php $imgId = (int)$row["id"]; $fn = (string)$row["file_name"]; ?>
                  <label class="tbs-img-item" title="Кликни за изтриване">
                    <input class="tbs-img-check" type="checkbox" name="del_img[]" value="<?= (int)$imgId ?>">
                    <img src="<?= e($IMG_URL . $fn) ?>" alt="Снимка">
                    <span class="tbs-img-x" aria-hidden="true">✕</span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="alert alert-warning mb-2">Няма снимки. Качи поне 1.</div>
            <?php endif; ?>

            <div class="tbs-upload-box">
              <input class="form-control tbs-input" type="file" name="new_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
              <div class="tbs-upload-help">Добави още снимки (до <?= (int)$MAX_FILES ?> общо). Макс 8MB на снимка.</div>

              <button class="btn tbs-upload-btn" type="submit" name="action" value="save">
                Качи снимки
              </button>
            </div>
          </div>
        </div>

        <!-- =========================
             STATUS SECTION (под снимките)
             ========================= -->
        <div class="col-12">
          <div class="tbs-status-card">
            <div class="tbs-status-title">Статус</div>
            <div class="tbs-status-actions">
              <button class="btn tbs-btn-sold" type="submit" name="action" value="sold">
                ✅ Маркирай като продадена
              </button>

              <button class="btn tbs-btn-delete" type="submit" name="action" value="delete"
                onclick="return confirm('Сигурен ли си, че искаш да изтриеш обявата?');">
                🗑 Изтрий обявата
              </button>
            </div>
            <div class="tbs-status-note">Тези действия са само за собственика.</div>
          </div>
        </div>

        <div class="col-12 d-flex gap-2 flex-wrap">
          <button class="btn tbs-filter2-go" type="submit" name="action" value="save" style="min-width: 180px;">Запази</button>
          <a class="btn tbs-filter2-clear" href="<?= e($base) ?>textbook_secondhand_view.php?id=<?= (int)$id ?>">Виж обявата</a>
        </div>

        <input type="hidden" id="curSubject" value="<?= e($subject) ?>">
      </form>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(() => {
  const subjectsByGrade = <?= json_encode($SUBJECTS_BY_GRADE, JSON_UNESCAPED_UNICODE) ?>;

  const gradeSel = document.getElementById('gradeSel');
  const subjectSel = document.getElementById('subjectSel');
  const curSubject = document.getElementById('curSubject')?.value || '';

  function fillSubjects(grade){
    const map = subjectsByGrade[grade] || {};
    const prev = subjectSel.value || curSubject || '';

    subjectSel.innerHTML = '';
    Object.keys(map).forEach(slug => {
      const opt = document.createElement('option');
      opt.value = slug;
      opt.textContent = map[slug];
      subjectSel.appendChild(opt);
    });

    if (prev && Object.prototype.hasOwnProperty.call(map, prev)) subjectSel.value = prev;
    else subjectSel.selectedIndex = 0;
  }

  fillSubjects(gradeSel.value);
  gradeSel.addEventListener('change', () => fillSubjects(gradeSel.value));
})();
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
