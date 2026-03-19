<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$IMG_URL = $base . "usedIMG/";
$TB_CATEGORY = "Учебници";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { http_response_code(404); echo "Not found"; exit; }

// ✅ взимаме само учебници
$st = $pdo->prepare("
  SELECT
    b.*,
    u.name AS user_name,
    u.email AS user_email
  FROM used_books b
  LEFT JOIN users u ON u.id = b.user_id
  WHERE b.id = ? AND b.category = ?
  LIMIT 1
");
$st->execute([$id, $TB_CATEGORY]);
$it = $st->fetch(PDO::FETCH_ASSOC);
if (!$it) { http_response_code(404); echo "Not found"; exit; }

// снимки
$st2 = $pdo->prepare("
  SELECT file_name
  FROM used_book_images
  WHERE used_book_id = ?
  ORDER BY sort_order ASC, id ASC
");
$st2->execute([$id]);
$imgs = $st2->fetchAll(PDO::FETCH_COLUMN);

$title   = (string)($it["title"] ?? "");
$author  = (string)($it["author"] ?? "");
$desc    = trim((string)($it["description"] ?? ""));
$price   = (float)($it["price"] ?? 0);
$city    = trim((string)($it["city"] ?? ""));
$cond    = (string)($it["condition"] ?? "good");
$userId  = (int)($it["user_id"] ?? 0);
$seller  = trim((string)($it["user_name"] ?? "")) ?: "Потребител";

$condTxt = [
  "new"      => "Нова",
  "like_new" => "Като нова",
  "good"     => "Добра",
  "fair"     => "Задоволителна",
  "poor"     => "Лоша",
][$cond] ?? "Добра";

// owner?
$me = is_logged_in() ? current_user() : null;
$meId = (int)($me["id"] ?? 0);
$isOwner = $me && $meId === $userId;

$page_title = $title ? ($title . " — Учебници втора ръка") : "Учебници втора ръка";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand.css">
<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand_view.css">

<body class="km-layout">
<?php require_once "nav.php"; ?>

<header class="tbs-hero">
  <div class="container tbs-hero-inner text-center">
    <div class="tbs-hero-icons" aria-hidden="true">🎓 📘</div>
    <h1 class="tbs-hero-title">Учебници втора ръка</h1>
    <p class="tbs-hero-sub">Детайли за обявата</p>
  </div>
  <div class="tbs-hero-wave" aria-hidden="true"></div>
</header>

<main class="tbs-main">
  <div class="container" style="max-width: 1100px;">

    <!-- ✅ TOP BAR: Назад + Редактирай (хоризонтално) -->
    <div class="tbs-topbar">
      <a class="btn tbs-filter2-clear" href="<?= e($base) ?>textbooks_secondhand.php">← Назад</a>

      <?php if ($isOwner): ?>
        <a class="btn-tbs-edit" href="<?= e($base) ?>textbook_secondhand_edit.php?id=<?= (int)$id ?>">✏️ Редактирай</a>
      <?php endif; ?>
    </div>

    <div class="tbs-filter2" style="margin-top:0;">
      <div class="row g-4 align-items-stretch">

        <!-- LEFT: IMAGE -->
        <div class="col-12 col-lg-6">
          <div class="tbs-card" style="height:100%; overflow:hidden;">
            <div class="tbs-card-img" style="height: 430px;">
              <?php if (!empty($imgs[0])): ?>
                <img
                  id="mainImg"
                  src="<?= e($IMG_URL . $imgs[0]) ?>"
                  alt="<?= e($title ?: "Учебник") ?>"
                  style="width:100%;height:100%;object-fit:contain;padding:12px;background:#f1f5f9;"
                >
              <?php else: ?>
                <img
                  src="<?= e($base) ?>assets/placeholder-book.png"
                  alt="Няма снимка"
                  style="width:100%;height:100%;object-fit:contain;padding:12px;background:#f1f5f9;"
                >
              <?php endif; ?>
              <span class="tbs-chip">Учебници</span>
            </div>

            <?php if (count($imgs) > 1): ?>
              <div class="tbs-thumbs">
                <?php foreach ($imgs as $k => $fn): ?>
                  <button type="button" class="tbs-thumb" data-src="<?= e($IMG_URL . $fn) ?>" aria-label="Снимка <?= (int)($k+1) ?>">
                    <img src="<?= e($IMG_URL . $fn) ?>" alt="Снимка <?= (int)($k+1) ?>">
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT: DETAILS -->
        <div class="col-12 col-lg-6">

          <div class="d-flex align-items-start justify-content-between gap-3">
            <div style="min-width:0;">
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="tbs-cond tbs-cond-vgood" style="font-size:.78rem;">Учебници</span>
                <span class="tbs-cond tbs-cond-good" style="font-size:.78rem;"><?= e($condTxt) ?></span>
              </div>

              <h2 style="margin:10px 0 0; font-weight:1000; color:var(--ink);">
                <?= e($title ?: "Учебник") ?>
              </h2>

              <div style="margin-top:4px; font-weight:800; color:rgba(15,23,42,.62);">
                от <?= e($author ?: "—") ?>
              </div>
            </div>

            <!-- ✅ PRICE only (edit removed from here) -->
            <div class="tbs-view-pricecol">
              <div class="tbs-price">
                <?= number_format($price, 2) ?> €
              </div>
            </div>
          </div>

          <hr style="opacity:.12; margin:18px 0;">

          <div class="row g-3">
            <div class="col-6">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Състояние</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);"><?= e($condTxt) ?></div>
            </div>

            <div class="col-6">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Град</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);"><?= e($city !== "" ? $city : "—") ?></div>
            </div>

            <div class="col-12">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Описание</div>
              <div style="
                margin-top:6px;
                background: rgba(2,6,23,.03);
                border: 1px solid rgba(15,23,42,.10);
                border-radius: 14px;
                padding: 10px 12px;
                color: rgba(15,23,42,.80);
              ">
                <?= $desc !== "" ? nl2br(e($desc)) : "Няма описание." ?>
              </div>
            </div>

            <div class="col-12">
              <hr style="opacity:.12; margin:10px 0 14px;">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Продавач</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);"><?= e($seller) ?></div>
            </div>
          </div>

          <!-- ✅ BUTTONS -->
          <div class="d-flex gap-2 flex-wrap mt-3">

            <?php if (!$isOwner): ?>
              <?php if (!$me): ?>
                <a class="btn tbs-filter2-go" href="<?= e($base) ?>login.php">🔒 Влез, за да пишеш</a>
              <?php else: ?>
                <button type="button"
                        class="btn tbs-filter2-go"
                        data-bs-toggle="modal"
                        data-bs-target="#msgModal">
                  ✉️ Пиши
                </button>
              <?php endif; ?>
            <?php endif; ?>

            <a class="btn tbs-filter2-clear" href="<?= e($base) ?>textbooks_secondhand.php">📚 Още учебници</a>

            <!-- ❤️ Favorite (REAL: DB) -->
            <button class="tbs-view-fav km-fav"
                    type="button"
                    data-id="<?= (int)$id ?>"
                    data-type="usedtextbook"
                    aria-label="Харесай"
                    title="Харесай">
              <svg viewBox="0 0 24 24" aria-hidden="true" class="tbs-view-fav-ico">
                <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
              </svg>
            </button>

          </div>

        </div>
      </div>
    </div>

  </div>
</main>

<!-- ✅ MODAL (само ако е логнат и НЕ е собственик) -->
<?php if ($me && !$isOwner): ?>
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Пиши на продавача</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Затвори"></button>
      </div>

      <div class="modal-body">
        <div class="small text-muted mb-2">До: <?= e($seller) ?></div>

        <textarea id="msgBody"
                  class="form-control"
                  rows="4"
                  maxlength="2000"
                  placeholder="Напиши съобщение..."></textarea>

        <div class="small text-muted mt-2" id="msgStatus"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отказ</button>
        <button type="button" class="btn btn-primary" id="msgSendBtn">Изпрати</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ✅ favorites.js (преди footer, за да е наличен KMFav) -->
<script src="<?= e($base) ?>favorites.js"></script>

<?php require_once "footer.php"; ?>

<script>
// thumbnails -> сменя главната снимка
(() => {
  const main = document.getElementById('mainImg');
  if (!main) return;
  document.querySelectorAll('.tbs-thumb').forEach(btn => {
    btn.addEventListener('click', () => {
      const src = btn.getAttribute('data-src');
      if (src) main.src = src;
    });
  });
})();
</script>

<?php if ($me && !$isOwner): ?>
<script>
// MODAL send -> POST към send_message.php -> redirect към chat.php
(() => {
  const sendBtn = document.getElementById('msgSendBtn');
  const bodyEl  = document.getElementById('msgBody');
  const status  = document.getElementById('msgStatus');

  if (!sendBtn || !bodyEl) return;

  sendBtn.addEventListener('click', async () => {
    const body = (bodyEl.value || '').trim();
    if (!body) { status.textContent = "Напиши съобщение."; return; }

    sendBtn.disabled = true;
    status.textContent = "Изпращане...";

    const fd = new FormData();
    fd.append('to_user_id', '<?= (int)$userId ?>');
    fd.append('usedbook_id', '<?= (int)$id ?>');
    fd.append('body', body);

    try{
      const r = await fetch('<?= e($base) ?>send_message.php', { method:'POST', body: fd });
      const j = await r.json().catch(() => ({}));

      if (!r.ok || !j.ok){
        status.textContent = j.error || "Грешка при изпращане.";
      } else {
        status.textContent = "Изпратено ✅";
        window.location.href = '<?= e($base) ?>chat.php?u=<?= (int)$userId ?>&b=<?= (int)$id ?>';
      }
    } catch(e){
      status.textContent = "Мрежова грешка.";
    } finally {
      sendBtn.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>

<script>
/* ❤️ Initial state from DB (fixed) */
document.addEventListener("DOMContentLoaded", async () => {
  const btn = document.querySelector(".km-fav.tbs-view-fav[data-type='usedtextbook'][data-id]");
  if (!btn) return;

  if (!window.KMFav || typeof KMFav.check !== "function") return;

  const id = parseInt(btn.dataset.id || "0", 10);
  if (!Number.isFinite(id) || id <= 0) return;

  try{
    const liked = await KMFav.check("usedtextbook", id);
    const likedB =
      liked === true ||
      liked === 1 ||
      liked === "1" ||
      (typeof liked === "string" && liked.trim().toLowerCase() === "true");
    btn.classList.toggle("is-active", likedB);
  }catch(e){}
});
</script>

</body>
</html>
