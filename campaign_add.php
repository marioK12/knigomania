<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

/* base url (subfolder safe) */
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Нова кампания";

$me  = current_user();
$meId = (int)($me["id"] ?? 0);

$types = [
  "chitalishte"=>"Читалище",
  "orphanage"=>"Дом/Център",
  "school"=>"Училище",
  "library"=>"Библиотека",
  "other"=>"Друго",
];

$err = "";

/* upload settings */
$MAX_IMAGES = 5;                 // ✅ максимум 5 (и задължителни)
$MAX_FILE_MB = 5;                // максимум MB за 1 снимка
$MAX_FILE_BYTES = $MAX_FILE_MB * 1024 * 1024;
$ALLOWED_EXT = ["jpg","jpeg","png","webp"];

/* Ensure upload dir exists */
$uploadDir = __DIR__ . "/campaignIMG/";
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $org_name     = trim((string)($_POST["org_name"] ?? ""));
  $org_type     = trim((string)($_POST["org_type"] ?? ""));
  $title        = trim((string)($_POST["title"] ?? ""));
  $description  = trim((string)($_POST["description"] ?? ""));
  $city         = trim((string)($_POST["city"] ?? ""));
  $contact_hint = trim((string)($_POST["contact_hint"] ?? ""));
  $deadline     = trim((string)($_POST["deadline"] ?? ""));

  /* REQUIRED: Тип, Организация, Заглавие, Описание, Град, Краен срок */
  if ($org_name === "" || $org_type === "" || $title === "" || $description === "" || $city === "" || $deadline === "") {
    $err = "Попълнете всички задължителни полета: Тип, Организация, Заглавие, Описание, Град и Краен срок.";
  } elseif (!isset($types[$org_type])) {
    $err = "Невалиден тип организация.";
  } else {

    /* ✅ REQUIRED images */
    $hasImages = !empty($_FILES["images"]) &&
                 is_array($_FILES["images"]["name"]) &&
                 (($_FILES["images"]["name"][0] ?? "") !== "");

    if (!$hasImages) {
      $err = "Трябва да качите поне 1 снимка (максимум {$MAX_IMAGES}).";
    }
  }

  /* If everything above OK -> validate image files */
  if ($err === "") {

    $count = count($_FILES["images"]["name"]);
    if ($count < 1 || $count > $MAX_IMAGES) {
      $err = "Може да качите от 1 до {$MAX_IMAGES} снимки.";
    } else {

      for ($i=0; $i<$count; $i++) {
        $name  = (string)($_FILES["images"]["name"][$i] ?? "");
        $tmp   = (string)($_FILES["images"]["tmp_name"][$i] ?? "");
        $size  = (int)($_FILES["images"]["size"][$i] ?? 0);
        $upErr = (int)($_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($upErr !== UPLOAD_ERR_OK) { $err = "Проблем при качването на снимка."; break; }
        if (!is_uploaded_file($tmp)) { $err = "Невалиден файл при качване."; break; }
        if ($size <= 0 || $size > $MAX_FILE_BYTES) { $err = "Всяка снимка трябва да е до {$MAX_FILE_MB}MB."; break; }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) { $err = "Позволени формати: JPG, PNG, WEBP."; break; }

        /* basic image check */
        $info = @getimagesize($tmp);
        if ($info === false) { $err = "Една от снимките не е валидно изображение."; break; }
      }
    }
  }

  /* Insert + upload */
  if ($err === "") {

    $st = $pdo->prepare("
      INSERT INTO campaigns
        (user_id, org_name, org_type, title, description, city, contact_hint, deadline, status, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $st->execute([
      $meId,
      $org_name,
      $org_type,
      $title,
      $description,
      $city,
      ($contact_hint !== "" ? $contact_hint : null),
      $deadline
    ]);

    $newId = (int)$pdo->lastInsertId();

    /* upload images + save in DB */
    $count = count($_FILES["images"]["name"]);
    for ($i=0; $i<$count; $i++) {

      $orig = (string)($_FILES["images"]["name"][$i] ?? "");
      $tmp  = (string)($_FILES["images"]["tmp_name"][$i] ?? "");

      if (!is_uploaded_file($tmp)) continue;

      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if (!in_array($ext, $ALLOWED_EXT, true)) continue;

      $fileName = "c_" . $newId . "_" . uniqid("", true) . "." . $ext;
      $dest = $uploadDir . $fileName;

      if (@move_uploaded_file($tmp, $dest)) {
        $pdo->prepare("INSERT INTO campaign_images (campaign_id, image) VALUES (?, ?)")
            ->execute([$newId, $fileName]);
      }
    }

    header("Location: " . $base . "campaign_view.php?id=" . $newId);
    exit;
  }
}
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<link rel="stylesheet" href="<?= e($base) ?>km-startup.css?v=1">

<body class="km-layout km-campaign-add km-anim">

<?php require_once "nav.php"; ?>


<style>
:root{
  --ink:#0f172a;
  --muted:rgba(15,23,42,.65);
  --card:rgba(255,255,255,.92);
  --stroke:rgba(15,23,42,.10);
  --shadow:0 18px 55px rgba(2,6,23,.10);
  --r:22px;
  --p1:#0f766e;
  --p2:#16a34a;
  --p3:#0ea5e9;
  --focus: rgba(34,197,94,.18);
}

body.km-layout.km-campaign-add{
  background:
    radial-gradient(900px 520px at 10% -10%, rgba(34,197,94,.18), transparent 60%),
    radial-gradient(900px 520px at 90% -12%, rgba(14,165,233,.14), transparent 60%),
    linear-gradient(180deg, #f7fbf9, #ffffff 55%);
  color: var(--ink);
}

.wrap{ padding: 26px 0 46px; }

.head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.h1{
  font-weight:950;
  margin:0;
  letter-spacing:-.2px;
}
.sub{
  color:var(--muted);
  font-weight:700;
  margin-top: 4px;
}

.card{
  background: var(--card);
  border: 1px solid var(--stroke);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  padding: 18px;
}

label{ font-weight: 900; margin-bottom:6px; display:block; }

input, select, textarea{
  width:100%;
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.14);
  padding: 10px 12px;
  background: rgba(255,255,255,.96);
  outline:none;
}

textarea{ min-height: 150px; resize: vertical; }

input:focus, select:focus, textarea:focus{
  box-shadow: 0 0 0 4px var(--focus);
  border-color: rgba(34,197,94,.35);
}

.req{
  font-size: .86rem;
  color: rgba(15,23,42,.55);
  font-weight: 900;
  margin-left: 6px;
}

/* ========================
   Upload (nice button/zone)
======================== */
.file-input{
  width:100%;
  border-radius: 18px;
  border: 2px dashed rgba(15,23,42,.22);
  padding: 18px;
  background: rgba(255,255,255,.94);
  font-weight: 850;
  cursor: pointer;
  transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
}

.file-input:hover{
  border-color: rgba(34,197,94,.55);
  background: rgba(240,253,244,.72);
  transform: translateY(-1px);
}

.file-input:focus{
  outline:none;
  box-shadow: 0 0 0 4px rgba(34,197,94,.18);
  border-color: rgba(34,197,94,.55);
}

/* make the native button look good (Chrome/Edge) */
.file-input::file-selector-button{
  border: none;
  border-radius: 14px;
  padding: 10px 14px;
  margin-right: 12px;
  font-weight: 950;
  color:#fff;
  cursor:pointer;
  background: linear-gradient(135deg, rgba(15,118,110,.92), rgba(34,197,94,.82));
  box-shadow: 0 12px 26px rgba(2,6,23,.12);
}
.file-input:hover::file-selector-button{
  filter: brightness(1.05);
}

.file-note{
  color: var(--muted);
  font-weight: 750;
  margin-top: 6px;
  font-size: .92rem;
}

.btnx{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  border-radius: 14px;
  padding: 11px 14px;
  font-weight: 900;
  border: 1px solid rgba(15,23,42,.10);
  text-decoration:none;
}

.btnx.primary{
  color:#fff;
  border-color: rgba(255,255,255,.18);
  background: linear-gradient(135deg, rgba(15,118,110,.92), rgba(34,197,94,.78));
}

.btnx.ghost{
  background: rgba(255,255,255,.86);
  color: rgba(15,23,42,.90);
}

.err{
  border:1px solid rgba(239,68,68,.25);
  background: rgba(239,68,68,.10);
  color: rgba(127,29,29,.95);
  border-radius: 16px;
  padding: 12px 14px;
  font-weight: 800;
  margin-bottom: 12px;
}

.small-help{
  color: rgba(15,23,42,.55);
  font-weight: 750;
  font-size: .92rem;
  margin-top: 6px;
}
</style>

<main class="km-main">
<section class="wrap">
  <div class="container">

    <div class="head">
      <div>
        <h1 class="h1">Нова кампания</h1>
        <div class="sub">Публикувайте кауза и хората ще се свържат с вас в чата.</div>
      </div>
      <a class="btnx ghost" href="<?= e($base . "campaigns.php") ?>">← Назад</a>
    </div>

    <div class="card">
      <?php if ($err): ?>
        <div class="err"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-12 col-md-6">
          <label>Организация <span class="req">*</span></label>
          <input required name="org_name"
                 value="<?= e($_POST["org_name"] ?? "") ?>"
                 placeholder="Напр. Читалище “Христо Ботев”">
        </div>

        <div class="col-12 col-md-6">
          <label>Тип <span class="req">*</span></label>
          <select required name="org_type">
            <option value="" disabled <?= (($_POST["org_type"] ?? "")==="" ? "selected" : "") ?>>Избери тип…</option>
            <?php foreach($types as $k=>$lab): ?>
              <option value="<?= e($k) ?>" <?= (($_POST["org_type"] ?? "")===$k?'selected':'') ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label>Заглавие <span class="req">*</span></label>
          <input required name="title"
                 value="<?= e($_POST["title"] ?? "") ?>"
                 placeholder="Напр. Детски книги за читалище в …">
        </div>

        <div class="col-12">
          <label>Описание / Какво търсим <span class="req">*</span></label>
          <textarea required name="description"
                    placeholder="Опишете какви книги търсите, възраст, състояние, брой, как ще се предават..."><?= e($_POST["description"] ?? "") ?></textarea>
                    
        </div>

        <div class="col-12 col-md-4">
          <label>Град <span class="req">*</span></label>
          <input required name="city"
                 value="<?= e($_POST["city"] ?? "") ?>"
                 placeholder="Напр. София">
        </div>

        <div class="col-12 col-md-4">
          <label>Краен срок <span class="req">*</span></label>
          <input required type="date" name="deadline" value="<?= e($_POST["deadline"] ?? "") ?>">
          <div class="small-help">Изберете дата, до която кампанията е активна.</div>
        </div>

        <div class="col-12 col-md-4">
          <label>Контакт (по желание)</label>
          <input name="contact_hint"
                 value="<?= e($_POST["contact_hint"] ?? "") ?>"
                 placeholder="Напр. ‘Пишете ни в чата’">
        </div>

        <div class="col-12">
          <label>Снимки <span class="req">*</span></label>
          <input class="file-input" type="file" name="images[]" multiple required
                 accept=".jpg,.jpeg,.png,.webp,image/*">
          <div class="file-note">Задължително: 1–<?= (int)$MAX_IMAGES ?> снимки • до <?= (int)$MAX_FILE_MB ?>MB всяка • JPG/PNG/WEBP</div>
        </div>

        <div class="col-12 d-flex gap-2 flex-wrap">
          <button class="btnx primary" type="submit">✅ Публикувай</button>
          <a class="btnx ghost" href="<?= e($base . "campaigns.php") ?>">Отказ</a>
        </div>

      </form>
    </div>

  </div>
</section>
</main>

<?php require_once "footer.php"; ?>


<script>
(function(){
  function kmReplay(){
    document.body.classList.remove("km-anim");
    void document.body.offsetHeight;
    document.body.classList.add("km-anim");
  }
  addEventListener("DOMContentLoaded", kmReplay);
  addEventListener("pageshow", e => { if (e.persisted) kmReplay(); });
})();
</script>

</body>
</html>
