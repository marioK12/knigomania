<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

/* base url (subfolder safe) */
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Кампания";

$cu = is_logged_in() ? current_user() : null;
$meId = (int)($cu["id"] ?? 0);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { http_response_code(404); echo "Not found"; exit; }

/* campaign + owner */
$st = $pdo->prepare("
  SELECT c.*, u.name AS user_name, u.id AS owner_id
  FROM campaigns c
  LEFT JOIN users u ON u.id = c.user_id
  WHERE c.id = ?
  LIMIT 1
");
$st->execute([$id]);
$camp = $st->fetch(PDO::FETCH_ASSOC);
if (!$camp) { http_response_code(404); echo "Not found"; exit; }

$page_title = "Кампания: " . ($camp["title"] ?? "Кампания");

/* images */
$imgSt = $pdo->prepare("SELECT image FROM campaign_images WHERE campaign_id=? ORDER BY id ASC");
$imgSt->execute([$id]);
$images = $imgSt->fetchAll(PDO::FETCH_COLUMN);

$IMG_URL = $base . "campaignIMG/";

$ownerId = (int)($camp["owner_id"] ?? 0);
$isOwner = ($meId > 0 && $ownerId === $meId);

$types = [
  "chitalishte"=>"Читалище",
  "orphanage"=>"Дом/Център",
  "school"=>"Училище",
  "library"=>"Библиотека",
  "other"=>"Друго",
];

$orgTypeKey = (string)($camp["org_type"] ?? "other");
$orgTypeLabel = $types[$orgTypeKey] ?? "Друго";

/* simple date formatting */
function km_date($s){
  $s = (string)$s;
  if ($s === "") return "";
  return $s; // keep as-is (YYYY-MM-DD) from DB
}

/* =========================================================
   ✅ SEND MESSAGE (Campaign → messages.campaign_id)
========================================================= */
$okMsg  = "";
$errMsg = "";

// keep textarea value on error
$draftBody = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST["action"] ?? "") === "send_campaign_msg") {

  if (!$cu) {
    $errMsg = "Трябва да си влязъл, за да изпратиш съобщение.";
  } elseif ($ownerId <= 0) {
    $errMsg = "Липсва получател за тази кампания.";
  } elseif ($ownerId === $meId) {
    $errMsg = "Не можеш да пишеш на себе си.";
  } else {
    $body = trim((string)($_POST["body"] ?? ""));
    $draftBody = $body;

    if ($body === "") {
      $errMsg = "Напиши съобщение.";
    } elseif (mb_strlen($body) > 2000) {
      $errMsg = "Съобщението е твърде дълго (макс 2000 символа).";
    } else {
      // NOTE: messages has: from_user_id, to_user_id, ref_type, usedbook_id, campaign_id, body, created_at, read_at
      $ins = $pdo->prepare("
        INSERT INTO messages (from_user_id, to_user_id, ref_type, usedbook_id, campaign_id, body, created_at, read_at)
        VALUES (?, ?, 'campaign', NULL, ?, ?, NOW(), NULL)
      ");
      $ins->execute([$meId, $ownerId, (int)$camp["id"], $body]);

      // prevent double submit
      header("Location: ".$base."campaign_view.php?id=".(int)$camp["id"]."&sent=1");
      exit;
    }
  }
}

if (($_GET["sent"] ?? "") === "1") {
  $okMsg = "Съобщението е изпратено ✅";
}

$shouldOpenModal = ($errMsg !== "");
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<link rel="stylesheet" href="<?= e($base) ?>km-startup.css?v=1">

<body class="km-layout km-campaign-view">
<?php require_once "nav.php"; ?>

<style>
/* =========================================================
   campaign_view.css — SCOPED ONLY TO MAIN
   ✅ Does NOT affect navbar/footer
========================================================= */

body.km-layout.km-campaign-view main{
  --ink:#0f172a;
  --muted:rgba(15,23,42,.66);
  --card:rgba(255,255,255,.92);
  --stroke:rgba(15,23,42,.10);
  --shadow:0 18px 55px rgba(2,6,23,.10);
  --shadow2:0 26px 90px rgba(2,6,23,.14);
  --r:22px;
  --p1:#0f766e;
  --p2:#16a34a;
  --p3:#0ea5e9;
  --focus: rgba(14,165,233,.18);

  color: var(--ink);
  padding-bottom: 56px;

  background:
    radial-gradient(1100px 540px at 16% -12%, rgba(14,165,233,.12), transparent 60%),
    radial-gradient(980px 520px at 86% -10%, rgba(34,197,94,.14), transparent 60%),
    linear-gradient(180deg, #f8fafc, #f3f6fb 40%, #f8fafc);
}

/* HERO */
body.km-layout.km-campaign-view main .cv-hero{
  position:relative;
  overflow:hidden;
  padding: 54px 0 32px;
  color:#fff;
  border-bottom: 1px solid rgba(255,255,255,.18);
  background:
    radial-gradient(900px 420px at 16% 12%, rgba(255,255,255,.14), transparent 60%),
    radial-gradient(900px 420px at 86% 10%, rgba(255,255,255,.10), transparent 60%),
    linear-gradient(120deg, var(--p1), var(--p2) 55%, var(--p3));
  isolation:isolate;
}
body.km-layout.km-campaign-view main .cv-hero::after{
  content:"";
  position:absolute; inset:0;
  background:
    radial-gradient(840px 340px at 50% 0%, rgba(124,58,237,.18), transparent 60%),
    linear-gradient(to bottom, rgba(2,6,23,.04), rgba(2,6,23,.22));
  pointer-events:none;
}
body.km-layout.km-campaign-view main .cv-hero .container{ position:relative; z-index:2; }

/* text */
body.km-layout.km-campaign-view main .cv-kicker{
  display:inline-flex; align-items:center; gap:10px;
  padding:10px 14px; border-radius:999px;
  background: rgba(255,255,255,.16);
  border: 1px solid rgba(255,255,255,.24);
  backdrop-filter: blur(10px);
  box-shadow: 0 12px 30px rgba(0,0,0,.12);
  font-weight: 900;
}
body.km-layout.km-campaign-view main .cv-title{
  margin: 12px 0 8px;
  font-weight: 950;
  letter-spacing: -.45px;
  line-height: 1.05;
  text-shadow: 0 18px 55px rgba(0,0,0,.26);
  font-size: clamp(1.75rem, 3.2vw, 2.85rem);
}
body.km-layout.km-campaign-view main .cv-sub{
  margin:0; max-width: 75ch;
  opacity:.95; line-height:1.55;
  text-shadow: 0 12px 30px rgba(0,0,0,.16);
}

/* hero buttons */
body.km-layout.km-campaign-view main .cv-hero-actions{
  margin-top: 16px;
  display:flex;
  gap:10px;
  flex-wrap: wrap;
}
body.km-layout.km-campaign-view main .btnx{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  border-radius: 14px;
  padding: 11px 14px;
  font-weight: 950;
  text-decoration:none;

  border: 1px solid rgba(255,255,255,.22);
  color:#fff;
  background: rgba(255,255,255,.16);
  backdrop-filter: blur(10px);
  box-shadow: 0 14px 34px rgba(0,0,0,.16);
  transition: transform .18s ease, background .18s ease, box-shadow .18s ease;
}
body.km-layout.km-campaign-view main .btnx:hover{
  background: rgba(255,255,255,.22);
  transform: translateY(-1px);
  box-shadow: 0 18px 46px rgba(0,0,0,.18);
}

/* layout */
body.km-layout.km-campaign-view main .cv-wrap{ padding: 18px 0 0; }
body.km-layout.km-campaign-view main .cv-grid{
  display:grid;
  grid-template-columns: 1.2fr .8fr;
  gap: 14px;
  align-items: start;
}

/* cards */
body.km-layout.km-campaign-view main .card{
  background: var(--card);
  border: 1px solid var(--stroke);
  border-radius: var(--r);
  box-shadow: var(--shadow);
}
body.km-layout.km-campaign-view main .card-pad{ padding: 18px; }

/* gallery */
body.km-layout.km-campaign-view main .cv-gallery{ padding: 14px; }
body.km-layout.km-campaign-view main .cv-mainimg{
  width:100%;
  aspect-ratio: 16/10;
  border-radius: 18px;
  background: rgba(15,23,42,.06);
  border: 1px solid rgba(15,23,42,.10);
  overflow:hidden;
  position: relative;
}
body.km-layout.km-campaign-view main .cv-mainimg img{ width:100%; height:100%; object-fit: cover; display:block; }
body.km-layout.km-campaign-view main .cv-mainimg button{
  position:absolute; inset:0; border:0; background:transparent; cursor: zoom-in;
}
body.km-layout.km-campaign-view main .cv-thumbs{
  margin-top: 12px;
  display:flex;
  gap:10px;
  flex-wrap: wrap;
}
body.km-layout.km-campaign-view main .cv-thumb{
  width: 86px;
  height: 64px;
  border-radius: 14px;
  overflow:hidden;
  border: 2px solid transparent;
  background: rgba(15,23,42,.06);
  cursor:pointer;
}
body.km-layout.km-campaign-view main .cv-thumb img{ width:100%; height:100%; object-fit: cover; display:block; }
body.km-layout.km-campaign-view main .cv-thumb.is-active{ border-color: rgba(14,165,233,.55); }

/* right info */
body.km-layout.km-campaign-view main .badge{
  display:inline-flex; gap:8px; align-items:center;
  padding: 7px 11px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.78);
  font-weight: 950;
  color: rgba(15,23,42,.82);
  font-size: .86rem;
}
body.km-layout.km-campaign-view main .cv-side .line{
  border-top: 1px solid rgba(15,23,42,.08);
  margin: 12px 0;
}
body.km-layout.km-campaign-view main .kv{ display:grid; gap:8px; }
body.km-layout.km-campaign-view main .kv .rowx{
  display:flex; justify-content:space-between; gap:10px; flex-wrap: wrap;
  font-weight: 850;
}
body.km-layout.km-campaign-view main .kv .key{ color: rgba(15,23,42,.62); font-weight: 850; }
body.km-layout.km-campaign-view main .kv .val{ color: rgba(15,23,42,.92); }

/* description */
body.km-layout.km-campaign-view main .cv-desc h3{
  margin:0 0 10px;
  font-weight: 950;
}
body.km-layout.km-campaign-view main .cv-desc .txt{
  color: rgba(15,23,42,.78);
  line-height: 1.65;
  font-weight: 650;
  white-space: pre-wrap;
}

/* donate */
body.km-layout.km-campaign-view main .cv-donate{ margin-top: 14px; }
body.km-layout.km-campaign-view main .cv-donate h3{ margin:0 0 10px; font-weight: 950; }
body.km-layout.km-campaign-view main .cv-steps{ display:grid; gap:10px; }
body.km-layout.km-campaign-view main .cv-step{
  padding: 12px 14px;
  border-radius: 18px;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.86);
  box-shadow: 0 10px 24px rgba(2,6,23,.06);
}

/* lightbox */
body.km-layout.km-campaign-view main .lb{
  position:fixed; inset:0;
  background: rgba(2,6,23,.78);
  display:none;
  align-items:center;
  justify-content:center;
  z-index: 9999;
  padding: 18px;
}
body.km-layout.km-campaign-view main .lb.is-open{ display:flex; }
body.km-layout.km-campaign-view main .lb-inner{
  width:min(1100px, 96vw);
  max-height: 86vh;
  position: relative;
}
body.km-layout.km-campaign-view main .lb-img{
  width:100%;
  max-height: 86vh;
  object-fit: contain;
  display:block;
  border-radius: 18px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.12);
}
body.km-layout.km-campaign-view main .lb-btn{
  position:absolute;
  top: 12px;
  border:0;
  background: rgba(255,255,255,.14);
  color:#fff;
  padding: 10px 12px;
  border-radius: 14px;
  cursor:pointer;
  font-weight: 900;
  backdrop-filter: blur(10px);
}
body.km-layout.km-campaign-view main .lb-close{ right: 12px; }
body.km-layout.km-campaign-view main .lb-prev{ left: 12px; top: 50%; transform: translateY(-50%); }
body.km-layout.km-campaign-view main .lb-next{ right: 12px; top: 50%; transform: translateY(-50%); }

@media (max-width: 992px){
  body.km-layout.km-campaign-view main .cv-grid{ grid-template-columns: 1fr; }
}
</style>



<main class="km-main">

  <section class="cv-hero">
    <div class="container">
      <div class="cv-kicker">🤝 Кампания за дарения</div>
      <h1 class="cv-title"><?= e($camp["title"]) ?></h1>
      <p class="cv-sub">
        <?= e($camp["org_name"]) ?>
        <?php if (!empty($camp["city"])): ?> • <?= e($camp["city"]) ?><?php endif; ?>
      </p>

      <div class="cv-hero-actions">
        <a class="btnx ghost" href="<?= e($base . "campaigns.php") ?>">← Назад</a>

        <?php if ($isOwner): ?>
          <a class="btnx" href="<?= e($base . "campaign_edit.php?id=" . (int)$camp["id"]) ?>">✏️ Редактирай</a>
        <?php endif; ?>

        <?php if ($cu && !$isOwner && $ownerId > 0): ?>
          <!-- ✅ Modal trigger -->
          <button type="button" class="btnx primary" data-bs-toggle="modal" data-bs-target="#msgModal">
            <strong>✉️ Пиши</strong>
          </button>
        <?php elseif (!$cu): ?>
          <a class="btnx primary" href="<?= e($base . "login.php") ?>"><strong>🔐 Вход за чат</strong></a>
        <?php endif; ?>

        <a class="btnx" href="#donate">🎁 Как да даря</a>
      </div>
    </div>
  </section>

  <section class="cv-wrap">
    <div class="container">

      <?php if ($okMsg !== ""): ?>
        <div class="alert alert-success" role="alert" style="margin-top:14px;">
          <?= e($okMsg) ?>
        </div>
      <?php endif; ?>

      <?php if ($errMsg !== ""): ?>
        <div class="alert alert-danger" role="alert" style="margin-top:14px;">
          <?= e($errMsg) ?>
        </div>
      <?php endif; ?>

      <div class="cv-grid">

        <!-- LEFT -->
        <div class="card cv-gallery">
          <?php if ($images): ?>
            <div class="cv-mainimg">
              <img id="cvMainImg" src="<?= e($IMG_URL . $images[0]) ?>" alt="Снимка на кампания">
              <button type="button" id="cvZoomBtn" aria-label="Увеличи"></button>
            </div>

            <div class="cv-thumbs" id="cvThumbs">
              <?php foreach($images as $i => $img): ?>
                <div class="cv-thumb <?= $i===0?'is-active':'' ?>" data-src="<?= e($IMG_URL . $img) ?>">
                  <img src="<?= e($IMG_URL . $img) ?>" alt="Миниатюра">
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="cv-noimg">Няма качени снимки към тази кампания.</div>
          <?php endif; ?>
        </div>

        <!-- RIGHT -->
        <aside class="card cv-side card-pad">
          <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
            <span class="badge">
              <?= ($camp["status"] === "active") ? "🟢 Активна" : "⚪ Приключила" ?>
            </span>
            <?php if (!empty($camp["deadline"])): ?>
              <span class="badge">⏳ до <?= e(km_date($camp["deadline"])) ?></span>
            <?php endif; ?>
          </div>

          <div class="line"></div>

          <div class="kv">
            <div class="rowx">
              <span class="key">Организация</span>
              <span class="val"><?= e($camp["org_name"]) ?></span>
            </div>
            <div class="rowx">
              <span class="key">Тип</span>
              <span class="val"><?= e($orgTypeLabel) ?></span>
            </div>
            <div class="rowx">
              <span class="key">Град</span>
              <span class="val"><?= e($camp["city"] ?: "—") ?></span>
            </div>
            <div class="rowx">
              <span class="key">Публикувана от</span>
              <span class="val"><?= e($camp["user_name"] ?: "Потребител") ?></span>
            </div>
          </div>

          <?php if (!empty($camp["contact_hint"])): ?>
            <div class="line"></div>
            <div style="color:rgba(15,23,42,.74); font-weight:750; line-height:1.55;">
              <strong style="font-weight:950;">Контакт:</strong> <?= e($camp["contact_hint"]) ?>
            </div>
          <?php endif; ?>
        </aside>

      </div>

      <!-- DESCRIPTION -->
      <section class="card card-pad cv-desc" style="margin-top:14px;">
        <h3>Описание</h3>
        <div class="txt"><?= e($camp["description"]) ?></div>
      </section>

      <!-- DONATE -->
      <section class="card card-pad cv-donate" id="donate">
        <h3>Как да даря</h3>

        <div class="cv-steps">
          <div class="cv-step">
            <strong>1) Прегледай описанието</strong>
            <p>Виж какви книги се търсят (възраст, тематика, състояние, количество).</p>
          </div>
          <div class="cv-step">
            <strong>2) Свържи се в чата</strong>
            <p>Уточнете удобен начин за предаване (лично, куриер, пункт и т.н.).</p>
          </div>
          <div class="cv-step">
            <strong>3) Подготви дарението</strong>
            <p>По възможност подбери книги в добро състояние и ги опаковай.</p>
          </div>
          <div class="cv-step">
            <strong>4) Предай и потвърди</strong>
            <p>След предаването, може да пишеш за потвърждение/обратна връзка.</p>
          </div>
        </div>
      </section>

    </div>
  </section>

  <!-- LIGHTBOX -->
  <div class="lb" id="lb">
    <div class="lb-inner">
      <img class="lb-img" id="lbImg" src="" alt="Снимка">
      <button class="lb-btn lb-close" id="lbClose" type="button">✕</button>
      <button class="lb-btn lb-prev" id="lbPrev" type="button">‹</button>
      <button class="lb-btn lb-next" id="lbNext" type="button">›</button>
    </div>
  </div>

  <!-- ✅ MESSAGE MODAL (like giftbook_view) -->
  <div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">Пиши на дарителя</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Затвори"></button>
        </div>

        <form method="post" action="<?= e($base . "campaign_view.php?id=" . (int)$camp["id"]) ?>">
          <div class="modal-body">
            <div class="mb-2 text-muted" style="font-size:.95rem;">
              До: <strong><?= e($camp["user_name"] ?: "Потребител") ?></strong>
            </div>

            <input type="hidden" name="action" value="send_campaign_msg">

            <textarea class="form-control" name="body" rows="5"
                      placeholder="Напиши съобщение..." required><?= e($draftBody) ?></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отказ</button>
            <button type="submit" class="btn btn-primary">Изпрати</button>
          </div>
        </form>

      </div>
    </div>
  </div>

</main>

<?php require_once "footer.php"; ?>

<script>
(function(){
  const mainImg = document.getElementById("cvMainImg");
  const thumbsWrap = document.getElementById("cvThumbs");
  const zoomBtn = document.getElementById("cvZoomBtn");

  // Lightbox
  const lb = document.getElementById("lb");
  const lbImg = document.getElementById("lbImg");
  const lbClose = document.getElementById("lbClose");
  const lbPrev = document.getElementById("lbPrev");
  const lbNext = document.getElementById("lbNext");

  if (mainImg && thumbsWrap){
    const thumbs = Array.from(thumbsWrap.querySelectorAll(".cv-thumb"));
    const sources = thumbs.map(t => t.getAttribute("data-src")).filter(Boolean);
    let idx = 0;

    function setActive(i){
      idx = Math.max(0, Math.min(sources.length - 1, i));
      mainImg.src = sources[idx];
      thumbs.forEach((t,k)=>t.classList.toggle("is-active", k===idx));
    }

    thumbs.forEach((t, i) => t.addEventListener("click", () => setActive(i)));

    function openLb(){
      if (!sources.length) return;
      lbImg.src = sources[idx];
      lb.classList.add("is-open");
      document.body.style.overflow = "hidden";
    }

    function closeLb(){
      lb.classList.remove("is-open");
      document.body.style.overflow = "";
    }

    function prev(){
      setActive((idx - 1 + sources.length) % sources.length);
      lbImg.src = sources[idx];
    }

    function next(){
      setActive((idx + 1) % sources.length);
      lbImg.src = sources[idx];
    }

    if (zoomBtn) zoomBtn.addEventListener("click", openLb);
    mainImg.addEventListener("click", openLb);

    lbClose.addEventListener("click", closeLb);
    lb.addEventListener("click", (e) => { if (e.target === lb) closeLb(); });

    lbPrev.addEventListener("click", (e)=>{ e.stopPropagation(); prev(); });
    lbNext.addEventListener("click", (e)=>{ e.stopPropagation(); next(); });

    document.addEventListener("keydown", (e) => {
      if (!lb.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLb();
      if (e.key === "ArrowLeft") prev();
      if (e.key === "ArrowRight") next();
    });
  }

  // ✅ Auto-open modal if server validation failed
  const shouldOpen = <?= $shouldOpenModal ? "true" : "false" ?>;
  if (shouldOpen && window.bootstrap) {
    const el = document.getElementById('msgModal');
    if (el) {
      const m = new bootstrap.Modal(el);
      m.show();
    }
  }
})();
</script>


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
