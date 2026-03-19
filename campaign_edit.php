<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

/* base url (subfolder safe) */
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Редактирай кампания";

$me  = current_user();
$meId = (int)($me["id"] ?? 0);

$types = [
  "chitalishte"=>"Читалище",
  "orphanage"=>"Дом/Център",
  "school"=>"Училище",
  "library"=>"Библиотека",
  "other"=>"Друго",
];

/* upload settings */
$MAX_IMAGES = 5;                 // максимум 5 (и трябва да остане поне 1)
$MAX_FILE_MB = 5;
$MAX_FILE_BYTES = $MAX_FILE_MB * 1024 * 1024;
$ALLOWED_EXT = ["jpg","jpeg","png","webp"];

/* upload folder */
$uploadDir = __DIR__ . "/campaignIMG/";
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

/* campaign id */
$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { http_response_code(404); echo "Not found"; exit; }

/* load campaign (owner check) */
$st = $pdo->prepare("SELECT * FROM campaigns WHERE id=? LIMIT 1");
$st->execute([$id]);
$camp = $st->fetch(PDO::FETCH_ASSOC);
if (!$camp) { http_response_code(404); echo "Not found"; exit; }

if ((int)$camp["user_id"] !== $meId) { http_response_code(403); echo "Forbidden"; exit; }

/* load images */
$imgSt = $pdo->prepare("SELECT id, image FROM campaign_images WHERE campaign_id=? ORDER BY id ASC");
$imgSt->execute([$id]);
$imgs = $imgSt->fetchAll(PDO::FETCH_ASSOC);

$IMG_URL = $base . "campaignIMG/";

$err = "";

/* defaults for form */
$val = [
  "org_name"     => (string)($camp["org_name"] ?? ""),
  "org_type"     => (string)($camp["org_type"] ?? "other"),
  "title"        => (string)($camp["title"] ?? ""),
  "description"  => (string)($camp["description"] ?? ""),
  "city"         => (string)($camp["city"] ?? ""),
  "deadline"     => (string)($camp["deadline"] ?? ""),
  "contact_hint" => (string)($camp["contact_hint"] ?? ""),
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $val["org_name"]     = trim((string)($_POST["org_name"] ?? ""));
  $val["org_type"]     = trim((string)($_POST["org_type"] ?? ""));
  $val["title"]        = trim((string)($_POST["title"] ?? ""));
  $val["description"]  = trim((string)($_POST["description"] ?? ""));
  $val["city"]         = trim((string)($_POST["city"] ?? ""));
  $val["deadline"]     = trim((string)($_POST["deadline"] ?? ""));
  $val["contact_hint"] = trim((string)($_POST["contact_hint"] ?? ""));

  /* required fields */
  if ($val["org_name"]==="" || $val["org_type"]==="" || $val["title"]==="" || $val["description"]==="" || $val["city"]==="" || $val["deadline"]==="") {
    $err = "Попълнете всички задължителни полета: Тип, Организация, Заглавие, Описание, Град и Краен срок.";
  } elseif (!isset($types[$val["org_type"]])) {
    $err = "Невалиден тип организация.";
  }

  /* figure deletes */
  $delIds = $_POST["delete_images"] ?? [];
  if (!is_array($delIds)) $delIds = [];
  $delIds = array_values(array_filter(array_map('intval', $delIds), fn($x)=>$x>0));

  $currentCount = count($imgs);
  $deleteCount = 0;
  if ($delIds) {
    $presentIds = array_map(fn($x)=> (int)$x["id"], $imgs);
    foreach($delIds as $did){
      if (in_array($did, $presentIds, true)) $deleteCount++;
    }
  }

  /* new uploads count */
  $newCount = 0;
  $hasNew = !empty($_FILES["images"]) && is_array($_FILES["images"]["name"]) && (($_FILES["images"]["name"][0] ?? "") !== "");
  if ($hasNew) $newCount = count($_FILES["images"]["name"]);

  /* enforce final image count: 1..5 */
  if ($err === "") {
    $finalCount = $currentCount - $deleteCount + $newCount;

    if ($finalCount < 1) {
      $err = "Трябва да остане поне 1 снимка към кампанията.";
    } elseif ($finalCount > $MAX_IMAGES) {
      $err = "След редакция може да има максимум {$MAX_IMAGES} снимки. (Сега ще станат {$finalCount})";
    }
  }

  /* validate new images */
  if ($err === "" && $hasNew) {
    if ($newCount < 1) {
      $err = "Невалидно качване на снимки.";
    } else {
      for ($i=0; $i<$newCount; $i++) {
        $name  = (string)($_FILES["images"]["name"][$i] ?? "");
        $tmp   = (string)($_FILES["images"]["tmp_name"][$i] ?? "");
        $size  = (int)($_FILES["images"]["size"][$i] ?? 0);
        $upErr = (int)($_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($upErr !== UPLOAD_ERR_OK) { $err = "Проблем при качването на снимка."; break; }
        if (!is_uploaded_file($tmp)) { $err = "Невалиден файл при качване."; break; }
        if ($size <= 0 || $size > $MAX_FILE_BYTES) { $err = "Всяка снимка трябва да е до {$MAX_FILE_MB}MB."; break; }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) { $err = "Позволени формати: JPG, PNG, WEBP."; break; }

        $info = @getimagesize($tmp);
        if ($info === false) { $err = "Една от снимките не е валидно изображение."; break; }
      }
    }
  }

  if ($err === "") {

    /* 1) update campaign */
    $up = $pdo->prepare("
      UPDATE campaigns
      SET org_name=?, org_type=?, title=?, description=?, city=?, deadline=?, contact_hint=?
      WHERE id=? AND user_id=?
      LIMIT 1
    ");
    $up->execute([
      $val["org_name"],
      $val["org_type"],
      $val["title"],
      $val["description"],
      $val["city"],
      $val["deadline"],
      ($val["contact_hint"] !== "" ? $val["contact_hint"] : null),
      $id,
      $meId
    ]);

    /* 2) delete selected images (db + file) */
    if ($delIds) {
      $in = implode(",", array_fill(0, count($delIds), "?"));
      $sel = $pdo->prepare("SELECT id, image FROM campaign_images WHERE campaign_id=? AND id IN ($in)");
      $sel->execute(array_merge([$id], $delIds));
      $toDel = $sel->fetchAll(PDO::FETCH_ASSOC);

      foreach($toDel as $row){
        $file = (string)$row["image"];
        $path = $uploadDir . $file;
        if ($file !== "" && is_file($path)) { @unlink($path); }
      }

      $del = $pdo->prepare("DELETE FROM campaign_images WHERE campaign_id=? AND id IN ($in)");
      $del->execute(array_merge([$id], $delIds));
    }

    /* 3) upload new images */
    if ($hasNew) {
      for ($i=0; $i<$newCount; $i++) {
        $orig = (string)($_FILES["images"]["name"][$i] ?? "");
        $tmp  = (string)($_FILES["images"]["tmp_name"][$i] ?? "");
        if (!is_uploaded_file($tmp)) continue;

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) continue;

        $fileName = "c_" . $id . "_" . uniqid("", true) . "." . $ext;
        $dest = $uploadDir . $fileName;

        if (@move_uploaded_file($tmp, $dest)) {
          $pdo->prepare("INSERT INTO campaign_images (campaign_id, image) VALUES (?, ?)")
              ->execute([$id, $fileName]);
        }
      }
    }

    header("Location: " . $base . "campaign_view.php?id=" . $id);
    exit;
  }
}

/* reload images for display (after errors/deletes not executed) */
$imgSt->execute([$id]);
$imgs = $imgSt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<link rel="stylesheet" href="<?= e($base) ?>km-startup.css?v=1">

<body class="km-layout km-campaign-edit km-anim">

<?php require_once "nav.php"; ?>


<style>
/* ✅ SCOPED ONLY TO MAIN */
body.km-layout.km-campaign-edit main{
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

  color: var(--ink);
  background:
    radial-gradient(900px 520px at 10% -10%, rgba(34,197,94,.18), transparent 60%),
    radial-gradient(900px 520px at 90% -12%, rgba(14,165,233,.14), transparent 60%),
    linear-gradient(180deg, #f7fbf9, #ffffff 55%);
  padding-bottom: 56px;
}

body.km-layout.km-campaign-edit main .wrap{ padding: 26px 0 46px; }

body.km-layout.km-campaign-edit main .head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
body.km-layout.km-campaign-edit main .h1{
  font-weight:950;
  margin:0;
  letter-spacing:-.2px;
}
body.km-layout.km-campaign-edit main .sub{
  color:var(--muted);
  font-weight:700;
  margin-top: 4px;
}

body.km-layout.km-campaign-edit main .card{
  background: var(--card);
  border: 1px solid var(--stroke);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  padding: 18px;
}

body.km-layout.km-campaign-edit main label{ font-weight: 900; margin-bottom:6px; display:block; }

body.km-layout.km-campaign-edit main input,
body.km-layout.km-campaign-edit main select,
body.km-layout.km-campaign-edit main textarea{
  width:100%;
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.14);
  padding: 10px 12px;
  background: rgba(255,255,255,.96);
  outline:none;
}

body.km-layout.km-campaign-edit main textarea{ min-height: 150px; resize: vertical; }

body.km-layout.km-campaign-edit main input:focus,
body.km-layout.km-campaign-edit main select:focus,
body.km-layout.km-campaign-edit main textarea:focus{
  box-shadow: 0 0 0 4px var(--focus);
  border-color: rgba(34,197,94,.35);
}

body.km-layout.km-campaign-edit main .req{
  font-size: .86rem;
  color: rgba(15,23,42,.55);
  font-weight: 900;
  margin-left: 6px;
}

body.km-layout.km-campaign-edit main .btnx{
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
body.km-layout.km-campaign-edit main .btnx.primary{
  color:#fff;
  border-color: rgba(255,255,255,.18);
  background: linear-gradient(135deg, rgba(15,118,110,.92), rgba(34,197,94,.78));
}
body.km-layout.km-campaign-edit main .btnx.ghost{
  background: rgba(255,255,255,.86);
  color: rgba(15,23,42,.90);
}

body.km-layout.km-campaign-edit main .err{
  border:1px solid rgba(239,68,68,.25);
  background: rgba(239,68,68,.10);
  color: rgba(127,29,29,.95);
  border-radius: 16px;
  padding: 12px 14px;
  font-weight: 800;
  margin-bottom: 12px;
}

/* existing images */
body.km-layout.km-campaign-edit main .img-grid{
  display:flex;
  flex-wrap: wrap;
  gap:12px;
}
body.km-layout.km-campaign-edit main .img-card{
  width: 160px;
  border-radius: 18px;
  border: 1px solid rgba(15,23,42,.12);
  background: rgba(255,255,255,.90);
  overflow:hidden;
  box-shadow: 0 10px 24px rgba(2,6,23,.06);
}
body.km-layout.km-campaign-edit main .img-card img{
  width:100%;
  height:110px;
  object-fit: cover;
  display:block;
}
body.km-layout.km-campaign-edit main .img-foot{
  padding: 10px 10px 12px;
  display:flex;
  align-items:center;
  gap:8px;
  justify-content:flex-start;
}
body.km-layout.km-campaign-edit main .chk{
  width:18px; height:18px;
}
body.km-layout.km-campaign-edit main .small{
  color: rgba(15,23,42,.62);
  font-weight: 800;
  font-size: .92rem;
}

/* upload zone */
body.km-layout.km-campaign-edit main .file-input{
  width:100%;
  border-radius: 18px;
  border: 2px dashed rgba(15,23,42,.22);
  padding: 18px;
  background: rgba(255,255,255,.94);
  font-weight: 850;
  cursor: pointer;
  transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
}
body.km-layout.km-campaign-edit main .file-input:hover{
  border-color: rgba(34,197,94,.55);
  background: rgba(240,253,244,.72);
  transform: translateY(-1px);
}
body.km-layout.km-campaign-edit main .file-input::file-selector-button{
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

body.km-layout.km-campaign-edit main .note{
  color: rgba(15,23,42,.60);
  font-weight: 750;
  margin-top: 6px;
  font-size: .92rem;
}
</style>

<main class="km-main">
<section class="wrap">
  <div class="container">

    <div class="head">
      <div>
        <h1 class="h1">Редактирай кампания</h1>
        <div class="sub">Може да редактираш полетата и снимките (макс 5, трябва да остане поне 1).</div>
      </div>
      <a class="btnx ghost" href="<?= e($base . "campaign_view.php?id=" . (int)$id) ?>">← Назад</a>
    </div>

    <div class="card">
      <?php if ($err): ?>
        <div class="err"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-12 col-md-6">
          <label>Организация <span class="req">*</span></label>
          <input required name="org_name" value="<?= e($val["org_name"]) ?>">
        </div>

        <div class="col-12 col-md-6">
          <label>Тип <span class="req">*</span></label>
          <select required name="org_type">
            <?php foreach($types as $k=>$lab): ?>
              <option value="<?= e($k) ?>" <?= ($val["org_type"]===$k?'selected':'') ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label>Заглавие <span class="req">*</span></label>
          <input required name="title" value="<?= e($val["title"]) ?>">
        </div>

        <div class="col-12">
          <label>Описание / Какво търсим <span class="req">*</span></label>
          <textarea required name="description"><?= e($val["description"]) ?></textarea>
        </div>

        <div class="col-12 col-md-4">
          <label>Град <span class="req">*</span></label>
          <input required name="city" value="<?= e($val["city"]) ?>">
        </div>

        <div class="col-12 col-md-4">
          <label>Краен срок <span class="req">*</span></label>
          <input required type="date" name="deadline" value="<?= e($val["deadline"]) ?>">
        </div>

        <div class="col-12 col-md-4">
          <label>Контакт (по желание)</label>
          <input name="contact_hint" value="<?= e($val["contact_hint"]) ?>" placeholder="Напр. ‘Пишете ни в чата’">
        </div>

        <div class="col-12">
          <label>Текущи снимки (маркирай за изтриване)</label>

          <?php if ($imgs): ?>
            <div class="img-grid">
              <?php foreach($imgs as $im): ?>
                <div class="img-card">
                  <img src="<?= e($IMG_URL . $im["image"]) ?>" alt="Снимка">
                  <div class="img-foot">
                    <input class="chk" type="checkbox" name="delete_images[]" value="<?= (int)$im["id"] ?>">
                    <div class="small">Изтрий</div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="note">Няма снимки (не би трябвало, защото са задължителни).</div>
          <?php endif; ?>

          <div class="note">Важно: трябва да остане поне 1 снимка. Максимум общо <?= (int)$MAX_IMAGES ?>.</div>
        </div>

        <div class="col-12">
          <label>Добави нови снимки (ако имаш място до 5)</label>
          <input class="file-input" type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,image/*">
          <div class="note">До <?= (int)$MAX_IMAGES ?> снимки общо • до <?= (int)$MAX_FILE_MB ?>MB всяка • JPG/PNG/WEBP</div>
        </div>

        <div class="col-12 d-flex gap-2 flex-wrap">
          <button class="btnx primary" type="submit">✅ Запази</button>
          <a class="btnx ghost" href="<?= e($base . "campaign_view.php?id=" . (int)$id) ?>">Отказ</a>
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
