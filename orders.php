<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$me = current_user();
$meId = (int)($me["id"] ?? 0);

// orders
$st = $pdo->prepare("
  SELECT
    MIN(id) AS id,
    item_type,
    item_id,
    MAX(item_title) AS item_title,
    SUM(qty) AS qty,
    MAX(unit_price) AS unit_price,
    SUM(unit_price * qty) AS total_price,
    status,
    MAX(created_at) AS created_at
  FROM orders
  WHERE buyer_id = ?
  GROUP BY item_type, item_id, status
  ORDER BY created_at DESC
  LIMIT 200
");
$st->execute([$meId]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);


// summary
$itemCount = 0;
$subTotal  = 0.0;
foreach ($orders as $o){
  $q = max(1, (int)($o["qty"] ?? 1));
  $itemCount += $q;
  $subTotal  += (float)($o["unit_price"] ?? 0) * $q;
}

// delivery rule (както в примера)
$delivery = ($subTotal >= 30) ? 0.0 : 0.0; // ако искаш доставка, кажи
$total = $subTotal + $delivery;

$page_title = "Моята количка";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>cart.css?v=1">

<main class="cart-wrap">
  <div class="container" style="max-width:1100px;">

    <div class="cart-head">
      <div class="cart-ico">🛒</div>
      <h1 class="cart-title">Моята количка</h1>
    </div>

    <?php if (!$orders): ?>
      <div class="cart-card" style="padding:18px;">
        <div style="font-weight:950; font-size:1.05rem;">Нямаш добавени поръчки.</div>
        <div style="color:rgba(17,24,39,.65); margin-top:6px;">
          Върни се към книгите/учебниците и добави нещо 🙂
        </div>
        <div style="margin-top:12px;">
          <a class="pay-btn" style="max-width:260px; text-decoration:none;"
             href="<?= e($base) ?>index.php">Към началото →</a>
        </div>
      </div>

    <?php else: ?>

      <div class="cart-grid">

        <!-- LEFT: items -->
        <section class="cart-card cart-items">

          <?php foreach ($orders as $o): ?>
            <?php
              $oid   = (int)($o["id"] ?? 0);
              $type  = (string)($o["item_type"] ?? "");
              $title = (string)($o["item_title"] ?? "");
              $qty   = max(1, (int)($o["qty"] ?? 1));
              $unit  = (float)($o["unit_price"] ?? 0);
              $tot   = (float)($o["total_price"] ?? ($unit*$qty));
              $status= (string)($o["status"] ?? "pending");
              $dt    = (string)($o["created_at"] ?? "");

              $typeLabel = ($type === "textbook") ? "Учебник" : (($type === "book") ? "Книга" : $type);

              $statusLabel = $status;
              if ($status === "pending")  $statusLabel = "⏳ В изчакване";
              if ($status === "paid")     $statusLabel = "✅ Платена";
              if ($status === "sent")     $statusLabel = "📦 Изпратена";
              if ($status === "done")     $statusLabel = "🏁 Завършена";
              if ($status === "canceled") $statusLabel = "❌ Отказана";

              // placeholder image (може после да го вържем към реални снимки)
              $img = $base . "textbooks/no-cover.png";
            ?>

            <article class="cart-item">
              <div class="cart-thumb">
                 <img
    src="<?= e($base) ?>textbooks/no-cover.png"
    alt=""
    class="cart-img"
    data-type="<?= e($type) ?>"  
    data-id="<?= (int)$o['item_id'] ?>">
              </div>

              <div class="cart-info">
                <h3 class="cart-name"><?= e($title) ?></h3>
                <div class="cart-sub"><?= e($typeLabel) ?> • №<?= $oid ?> • <?= e($statusLabel) ?></div>
                <div class="cart-price"><?= number_format($unit, 2, ".", "") ?> лв</div>
                <div class="cart-sub" style="margin-top:6px;">
                  Общо: <strong style="color:#8a3d14;"><?= number_format($tot, 2, ".", "") ?> лв</strong>
                  <span style="margin-left:10px;">•</span>
                  <span style="margin-left:10px;">Дата: <?= e($dt) ?></span>
                </div>
              </div>

              <div class="cart-actions">
                <!-- В момента нямаш delete/update API. Този бутон е само UI.
                     Ако искаш да изтриваме поръчка, ще направим orders_api.php -->
                <button class="cart-trash" type="button" title="Премахни (не е вързано още)" disabled
                        style="opacity:.45; cursor:not-allowed;">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M6 6l1 16h10l1-16"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                </button>

                <div class="cart-qty" title="Количество (не е вързано още)">
                  <button class="qty-btn" type="button" disabled style="opacity:.45; cursor:not-allowed;">–</button>
                  <div class="qty-val"><?= (int)$qty ?></div>
                  <button class="qty-btn" type="button" disabled style="opacity:.45; cursor:not-allowed;">+</button>
                </div>
              </div>
            </article>

          <?php endforeach; ?>

        </section>

        <!-- RIGHT: summary -->
        <aside class="cart-card cart-summary">
          <div class="sum-title">Обобщение</div>

          <div class="sum-row"><span>Брой артикули:</span> <strong><?= (int)$itemCount ?></strong></div>
          <div class="sum-row"><span>Междинна сума:</span> <strong><?= number_format($subTotal, 2, ".", "") ?> лв</strong></div>
          <div class="sum-row">
            <span>Доставка:</span>
            <?php if ($delivery <= 0): ?>
              <strong style="color:#16a34a;">Безплатна</strong>
            <?php else: ?>
              <strong><?= number_format($delivery, 2, ".", "") ?> лв</strong>
            <?php endif; ?>
          </div>

          <div class="sum-total">
            <div class="lbl">Общо:</div>
            <div class="val"><?= number_format($total, 2, ".", "") ?> лв</div>
          </div>

          <!-- Тук няма реален checkout, защото ти вече създаваш поръчка от бутона.
               Ако искаш общ checkout, ще сменим логиката към истинска количка. -->
          <a class="pay-btn" href="<?= e($base) ?>index.php" style="text-decoration:none;">
            Продължи пазаруването <span aria-hidden="true">→</span>
          </a>

          <div class="sum-foot">Безплатна доставка за поръчки над 30 лв</div>
        </aside>

      </div>

    <?php endif; ?>

  </div>
</main>

<?php require_once "footer.php"; ?>


<script>
/* =========================================
   Bind cart images from JS catalogs
   Supports: books + textbooks
   - book     -> window.BOOKS
   - textbook -> window.TEXTBOOKS
   Also supports relative filenames
========================================= */
(function(){
  const imgs = document.querySelectorAll(".cart-img");
  if (!imgs.length) return;

  const BASE = (window.KM_BASE || "<?= e($base) ?>").toString();

  const BOOKS = Array.isArray(window.BOOKS) ? window.BOOKS : [];
  const TEXTBOOKS = Array.isArray(window.TEXTBOOKS) ? window.TEXTBOOKS : [];

  function pick(arr, id){
    return arr.find(x => Number(x?.id) === Number(id)) || null;
  }

  function normalizeSrc(src){
    src = String(src || "").trim();
    if (!src) return "";

    // absolute url
    if (/^https?:\/\//i.test(src)) return src;

    // already absolute path
    if (src.startsWith("/")) return src;

    // common local folders (adjust if your paths differ)
    // if it's just a filename, we try assets folders first
    if (!src.includes("/") && !src.includes("\\")) {
      // books often: booksIMG/filename or assets/...
      return BASE + "booksIMG/" + encodeURIComponent(src);
    }

    // relative path
    src = src.replace(/^(\.\/|\/)+/, "");
    return BASE + src;
  }

  imgs.forEach(img => {
    const type = (img.dataset.type || "").trim(); // "book" | "textbook"
    const id   = Number(img.dataset.id);

    if (!id || !type) return;

    if (type === "book"){
      const b = pick(BOOKS, id);
      const src = normalizeSrc(b?.img || b?.image || b?.cover || "");
      if (src) img.src = src;
      return;
    }

    if (type === "textbook"){
      const tb = pick(TEXTBOOKS, id);
      const src = normalizeSrc(tb?.img || tb?.image || tb?.cover || "");
      if (src) img.src = src;
      return;
    }
  });
})();
</script>

</body>
</html>
