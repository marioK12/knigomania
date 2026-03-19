<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }
$me = current_user();
$meId = (int)($me["id"] ?? 0);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// uploads dir
$IMG_DIR_FS = rtrim(__DIR__, "/\\") . "/giftIMG/";
if (!is_dir($IMG_DIR_FS)) { @mkdir($IMG_DIR_FS, 0775, true); }

// constraints
$MAX_IMAGES  = 6;
$MAX_SIZE    = 6 * 1024 * 1024; // 6MB
$ALLOWED_EXT = ["jpg","jpeg","png","webp"];

function clean_filename(string $name): string {
  $name = preg_replace('~[^\w\-.]+~u', '_', $name);
  $name = trim($name, "._- ");
  return $name !== "" ? $name : "img";
}

$err = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {

  $title  = trim((string)($_POST["title"] ?? ""));
  $author = trim((string)($_POST["author"] ?? "")); // ✅ NEW (required)
  $cond   = trim((string)($_POST["condition"] ?? "good"));
  $city   = trim((string)($_POST["city"] ?? ""));
  $phone  = trim((string)($_POST["phone"] ?? ""));
  $desc   = trim((string)($_POST["description"] ?? ""));

  if (!in_array($cond, ["new","like_new","good","fair","poor"], true)) $cond = "good";

  // required fields
  if ($title === "") $err = "Попълни заглавие.";
  if ($err === "" && $author === "") $err = "Попълни автор.";
  if ($err === "" && $city === "")  $err = "Попълни град.";
  if ($err === "" && $phone === "") $err = "Попълни телефон.";
  if ($err === "" && $desc === "")  $err = "Попълни описание.";

  // images required: at least 1 selected
  if ($err === "") {
    if (empty($_FILES["images"]) || !is_array($_FILES["images"]["name"])) {
      $err = "Качи поне 1 снимка.";
    } else {
      $names = $_FILES["images"]["name"];
      $errs  = $_FILES["images"]["error"] ?? [];
      $selected = 0;

      for ($i=0; $i<count($names); $i++){
        $eUp = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
        if ($eUp !== UPLOAD_ERR_NO_FILE) $selected++;
      }

      if ($selected <= 0) $err = "Качи поне 1 снимка.";
      if ($err === "" && $selected > $MAX_IMAGES) $err = "Можеш да качиш най-много {$MAX_IMAGES} снимки.";
    }
  }

  if ($err === "") {
    // create listing
    $st = $pdo->prepare("
      INSERT INTO gift_books (user_id, title, author, description, `condition`, city, phone, status, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $st->execute([$meId, $title, $author, $desc, $cond, $city, $phone]);
    $id = (int)$pdo->lastInsertId();

    // upload images (must save at least 1)
    $uploadedOk = 0;

    $names = $_FILES["images"]["name"];
    $tmp   = $_FILES["images"]["tmp_name"];
    $sizes = $_FILES["images"]["size"] ?? [];
    $errs  = $_FILES["images"]["error"] ?? [];

    $sort = 0;

    for ($i=0; $i<count($names); $i++) {
      $eUp = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
      if ($eUp === UPLOAD_ERR_NO_FILE) continue;
      if ($eUp !== UPLOAD_ERR_OK) continue;

      $orig = (string)$names[$i];
      $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if (!in_array($ext, $ALLOWED_EXT, true)) continue;

      $size = (int)($sizes[$i] ?? 0);
      if ($size <= 0 || $size > $MAX_SIZE) continue;

      $baseName = clean_filename(pathinfo($orig, PATHINFO_FILENAME));
      $fn = "g_" . $id . "_" . time() . "_" . $i . "_" . $baseName . "." . $ext;
      $dest = $IMG_DIR_FS . $fn;

      if (@move_uploaded_file($tmp[$i], $dest)) {
        $pdo->prepare("INSERT INTO gift_book_images (gift_book_id, file_name, sort_order) VALUES (?, ?, ?)")
            ->execute([$id, $fn, $sort++]);
        $uploadedOk++;
      }
    }

    if ($uploadedOk <= 0) {
      // rollback listing if no image saved
      $pdo->prepare("DELETE FROM gift_books WHERE id=? AND user_id=? LIMIT 1")->execute([$id, $meId]);
      $err = "Не успях да кача снимка. Провери формата (JPG/PNG/WEBP) и размера (до 6MB).";
    } else {
      header("Location: giftbook_view.php?id=" . $id);
      exit;
    }
  }
}

$page_title = "Добави • Подари книга";
include "header.php";
?>
<body class="km-layout">
<?php include "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>giftbook_form.css?v=5">

<main class="km-main py-4 py-md-5 gb-form-page">
  <div class="container" style="max-width: 900px;">

    <div class="gb-form-card">
      <div class="gb-form-head">
        <div class="gb-badge">🎁 Подари книга</div>
        <h1 class="gb-form-title">Добави обява</h1>
        <div class="gb-form-sub">Полетата с * са задължителни. Снимките са максимум <?= (int)$MAX_IMAGES ?>.</div>
      </div>

      <?php if ($err): ?>
        <div class="alert alert-danger gb-alert"><?= e($err) ?></div>
      <?php endif; ?>

      <form id="gbAddForm" method="post" enctype="multipart/form-data" novalidate>
        <div class="row g-3">

          <div class="col-12">
            <label class="form-label">Заглавие *</label>
            <input class="form-control" name="title" required maxlength="200"
                   value="<?= e($_POST["title"] ?? "") ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Автор *</label>
            <input class="form-control" name="author" required maxlength="160"
                   value="<?= e($_POST["author"] ?? "") ?>">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Състояние</label>
            <?php $c = (string)($_POST["condition"] ?? "good"); ?>
            <select class="form-select" name="condition">
              <option value="new"      <?= $c==="new" ? "selected" : "" ?>>нова</option>
              <option value="like_new" <?= $c==="like_new" ? "selected" : "" ?>>като нова</option>
              <option value="good"     <?= $c==="good" ? "selected" : "" ?>>добро</option>
              <option value="fair"     <?= $c==="fair" ? "selected" : "" ?>>средно</option>
              <option value="poor"     <?= $c==="poor" ? "selected" : "" ?>>лошо</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Град *</label>
            <input class="form-control" name="city" required maxlength="80"
                   value="<?= e($_POST["city"] ?? "") ?>">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Телефон *</label>
            <input class="form-control" name="phone" required maxlength="32"
                   value="<?= e($_POST["phone"] ?? "") ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Описание *</label>
            <textarea class="form-control" name="description" rows="4" maxlength="5000" required><?= e($_POST["description"] ?? "") ?></textarea>
          </div>

          <div class="col-12">
            <label class="form-label">Снимки (JPG/PNG/WEBP) * (1–<?= (int)$MAX_IMAGES ?>)</label>
            <input id="gbImages" class="form-control" type="file" name="images[]" multiple required
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <div class="form-text">Поне 1 снимка е задължителна • до 6MB всяка • максимум <?= (int)$MAX_IMAGES ?>.</div>

            <div id="gbFileHint" class="gb-filehint"></div>
            <div id="gbFileErr" class="gb-fileerr" style="display:none;"></div>
          </div>

          <div class="col-12 gb-actions">
            <a class="gb-btn-cancel" href="<?= e($base) ?>giftbooks.php">Отказ</a>

            <button id="gbSubmit" class="gb-btn-primary" type="submit">
              Публикувай
            </button>
          </div>

        </div>
      </form>
    </div>

  </div>
</main>

<?php include "footer.php"; ?>

<script>
(function(){
  const MAX = <?= (int)$MAX_IMAGES ?>;

  const form = document.getElementById("gbAddForm");
  const btn  = document.getElementById("gbSubmit");
  const inp  = document.getElementById("gbImages");
  const hint = document.getElementById("gbFileHint");
  const err  = document.getElementById("gbFileErr");

  function setErr(msg){
    if (!err) return;
    if (!msg){
      err.style.display = "none";
      err.textContent = "";
      return;
    }
    err.style.display = "block";
    err.textContent = msg;
  }

  function updateHint(){
    if (!inp || !hint) return;

    const n = (inp.files && inp.files.length) ? inp.files.length : 0;
    hint.textContent = n ? ("Избрани снимки: " + n + " / " + MAX) : "";
    setErr("");

    if (n > MAX){
      setErr("Максимумът е " + MAX + " снимки. Намали избора.");
    }
  }

  if (inp) inp.addEventListener("change", updateHint);
  updateHint();

  if (form){
    form.addEventListener("submit", (ev) => {
      const n = (inp && inp.files) ? inp.files.length : 0;

      if (n <= 0){
        ev.preventDefault();
        setErr("Качи поне 1 снимка.");
        inp && inp.focus();
        return;
      }
      if (n > MAX){
        ev.preventDefault();
        setErr("Максимумът е " + MAX + " снимки. Намали избора.");
        inp && inp.focus();
        return;
      }

      if (btn){
        btn.disabled = true;
        btn.classList.add("is-loading");
        btn.textContent = "Публикувам…";
      }
    });
  }
})();
</script>

</body>
</html>
