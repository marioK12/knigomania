<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Кампании за дарения";

$cu = is_logged_in() ? current_user() : null;
$meId = (int)(($cu["id"] ?? 0));

// filters
$q = trim((string)($_GET["q"] ?? ""));
$city = trim((string)($_GET["city"] ?? ""));
$type = trim((string)($_GET["type"] ?? ""));
$status = trim((string)($_GET["status"] ?? "active"));

$where = "1=1";
$params = [];

if ($status !== "") { $where .= " AND c.status=?"; $params[] = $status; }
if ($q !== "") {
  $where .= " AND (c.title LIKE ? OR c.org_name LIKE ? OR c.description LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($city !== "") { $where .= " AND c.city=?"; $params[] = $city; }
if ($type !== "") { $where .= " AND c.org_type=?"; $params[] = $type; }

// cities for filter
$citySt = $pdo->query("SELECT DISTINCT city FROM campaigns WHERE city IS NOT NULL AND city<>'' ORDER BY city");
$cities = $citySt->fetchAll(PDO::FETCH_COLUMN);

// list (with cover image)
$st = $pdo->prepare("
  SELECT
    c.*,
    u.name AS user_name,
    (
      SELECT ci.image
      FROM campaign_images ci
      WHERE ci.campaign_id = c.id
      ORDER BY ci.id ASC
      LIMIT 1
    ) AS cover_image
  FROM campaigns c
  LEFT JOIN users u ON u.id=c.user_id
  WHERE {$where}
  ORDER BY c.created_at DESC, c.id DESC
");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// image base
$CAMP_IMG_URL = $base . "campaignIMG/";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>campaigns.css?v=2">
<link rel="stylesheet" href="<?= e($base) ?>km-startup.css?v=1">

<body class="km-layout km-campaigns km-anim">
<?php require_once "nav.php"; ?>

<main class="km-main">

<section class="camp-hero">
  <div class="camp-glow" aria-hidden="true"></div>

  <div class="container">
    <div class="camp-hero-actions">
      <?php if ($cu): ?>
        <a class="btn" href="<?= e($base . "campaign_add.php") ?>">➕ Нова кампания</a>
      <?php else: ?>
        <a class="btn" href="<?= e($base . "login.php") ?>">Вход</a>
      <?php endif; ?>
    </div>

    <div>
      <div class="camp-kicker">🤝 Кампании за дарения</div>
      <h1 class="camp-title">Дарете книги там, където са най-нужни</h1>
      <p class="camp-sub">
        Читалища, библиотеки, училища и социални центрове публикуват кампании. Влизате в чата и уточнявате дарението.
      </p>
    </div>
  </div>
</section>

<section class="camp-filters-wrap">
  <div class="container">
    <div class="camp-filters">
      <form method="get">
        <input type="text" name="q" value="<?= e($q) ?>" style="font-weight:bold;" placeholder="Търси по заглавие/организация...">
        <select name="city">
          <option value="">Всички градове</option>
          <?php foreach($cities as $c): ?>
            <option value="<?= e($c) ?>" <?= $city===$c?'selected':'' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="type">
          <option value="">Всички типове</option>
          <?php
            $types = [
              "chitalishte"=>"Читалище",
              "orphanage"=>"Дом/Център",
              "school"=>"Училище",
              "library"=>"Библиотека",
              "other"=>"Друго",
            ];
            foreach($types as $k=>$label):
          ?>
            <option value="<?= e($k) ?>" <?= $type===$k?'selected':'' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="status">
          <option value="active" <?= $status==='active'?'selected':'' ?>>Активни</option>
          <option value="closed" <?= $status==='closed'?'selected':'' ?>>Приключили</option>
          <option value="" <?= $status===''?'selected':'' ?>>Всички</option>
        </select>

        <button class="camp-btn" type="submit">Търси</button>
      </form>
    </div>
  </div>
</section>

<section class="camp-grid">
  <div class="container">
    <div class="row g-3">

      <?php if (!$rows): ?>
        <div class="col-12">
          <div class="camp-empty">
            <div>
              <div class="camp-empty-title">Няма намерени кампании</div>
              <div class="camp-empty-sub">Пробвай с други филтри или създай нова кампания.</div>
            </div>
            <a class="btn-new" href="<?= e($cu ? ($base."campaign_add.php") : ($base."login.php")) ?>">
              <?= $cu ? "➕ Нова кампания" : "Вход" ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach($rows as $r): ?>
        <?php
          $cover = trim((string)($r["cover_image"] ?? ""));
          $coverUrl = ($cover !== "") ? ($CAMP_IMG_URL . $cover) : "";
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="camp-item">

            <a class="camp-cover" href="<?= e($base . "campaign_view.php?id=" . (int)$r["id"]) ?>">
              <?php if ($coverUrl !== ""): ?>
                <img src="<?= e($coverUrl) ?>" alt="Снимка на кампания">
              <?php else: ?>
                <div class="camp-cover-ph">📚 Няма снимка</div>
              <?php endif; ?>
            </a>

            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
              <span class="badge-soft">
                <?= ($r["status"] === "active") ? "🟢 Активна" : "⚪ Приключила" ?>
              </span>

              <?php if (!empty($r["deadline"])): ?>
                <span class="badge-soft">⏳ до <?= e($r["deadline"]) ?></span>
              <?php endif; ?>
            </div>

            <div class="camp-h"><?= e($r["title"]) ?></div>

            <div class="camp-meta">
              <?= e($r["org_name"]) ?>
              <?php if (!empty($r["city"])): ?> • <?= e($r["city"]) ?><?php endif; ?>
            </div>

            <div class="camp-desc">
              <?= e(mb_strimwidth(strip_tags((string)$r["description"]), 0, 170, "…", "UTF-8")) ?>
            </div>

            <div class="camp-actions">
              <a class="primary" href="<?= e($base . "campaign_view.php?id=" . (int)$r["id"]) ?>">Виж →</a>
            </div>

          </div>
        </div>
      <?php endforeach; ?>

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
