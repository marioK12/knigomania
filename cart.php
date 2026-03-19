<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$me = current_user();
$meId = (int)($me["id"] ?? 0);

$st = $pdo->prepare("
  SELECT id, item_type, item_id, title, unit_price, qty, updated_at
  FROM cart_items
  WHERE user_id=?
  ORDER BY id DESC
");
$st->execute([$meId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
$sub = 0.0;
foreach($items as $it){
  $q = max(1, (int)$it["qty"]);
  $count += $q;
  $sub += (float)$it["unit_price"] * $q;
}
$delivery = ($sub >= 30) ? 0.0 : 0.0;
$total = $sub + $delivery;

$page_title = "Моята количка";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>cart.css?v=2">
<link rel="stylesheet" href="<?= e($base) ?>cart1.css?v=1">

<main class="cart-wrap">
  <div class="container" style="max-width:1100px;">

    <div class="cart-head">
      <div class="cart-ico">🛒</div>
      <h1 class="cart-title">Моята количка</h1>
    </div>

    <?php if (!$items): ?>
      <div class="cart-card" style="padding:18px;">
        <div style="font-weight:950; font-size:1.05rem;">Количката е празна.</div>
        <div style="color:rgba(17,24,39,.65); margin-top:6px;">Добави книги/учебници и после натисни „Купи“ 🙂</div>
        <div style="margin-top:12px;">
          <a class="pay-btn" style="max-width:260px; text-decoration:none;" href="<?= e($base) ?>index.php">
            Продължи пазаруването →
          </a>
        </div>
      </div>
    <?php else: ?>

      <div class="cart-grid">

        <section class="cart-card cart-items">
          <?php foreach($items as $it): ?>
            <?php
              $rowId  = (int)$it["id"];
              $type   = (string)$it["item_type"];   // book | textbook
              $itemId = (int)$it["item_id"];
              $title  = (string)$it["title"];
              $unit   = (float)$it["unit_price"];
              $qty    = max(1, (int)$it["qty"]);
              $line   = $unit * $qty;
            ?>
            <article class="cart-item"
                     data-row="<?= $rowId ?>"
                     data-unit="<?= e(number_format($unit, 2, ".", "")) ?>">
              <div class="cart-thumb">
                <img
                  src="<?= e($base) ?>textbooks/no-cover.png"
                  alt=""
                  class="cart-img"
                  data-type="<?= e($type) ?>"
                  data-id="<?= $itemId ?>">
              </div>

              <div class="cart-info">
                <h3 class="cart-name"><?= e($title) ?></h3>
                <div class="cart-sub"><?= $type === "textbook" ? "Учебник" : "Книга" ?> • ID: <?= $itemId ?></div>
                <div class="cart-price"><?= number_format($unit, 2, ".", "") ?> €</div>
                <div class="cart-sub" style="margin-top:6px;">
                  Общо: <strong class="js-line" style="color:#8a3d14;"><?= number_format($line, 2, ".", "") ?> лв</strong>
                </div>
              </div>

              <div class="cart-actions">
                <button class="cart-trash js-del" type="button" title="Премахни" data-row="<?= $rowId ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M6 6l1 16h10l1-16"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                </button>

                <div class="cart-qty">
                  <button class="qty-btn js-minus" type="button" data-row="<?= $rowId ?>">–</button>
                  <div class="qty-val" id="qty<?= $rowId ?>"><?= $qty ?></div>
                  <button class="qty-btn js-plus" type="button" data-row="<?= $rowId ?>">+</button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <aside class="cart-card cart-summary">
          <div class="sum-title">Обобщение</div>

          <div class="sum-row"><span>Брой артикули:</span> <strong id="sumCount"><?= (int)$count ?></strong></div>
          <div class="sum-row"><span>Междинна сума:</span> <strong id="sumSub"><?= number_format($sub, 2, ".", "") ?> лв</strong></div>
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
            <div class="val" id="sumTotal"><?= number_format($total, 2, ".", "") ?> лв</div>
          </div>

          <a class="btn btn-primary w-100" href="<?= e($base) ?>checkout.php">Към плащане →</a>

          <div class="sum-foot">Безплатна доставка за поръчки над 30 лв</div>
        </aside>

      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script src="<?= e($base) ?>bookslist.js?v=1"></script>
<script src="<?= e($base) ?>textbooks-data.js?v=1"></script>

<script>
/* 1) Връзваме кориците от JS масивите (bookslist.js / textbooks-data.js) */
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
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith("/")) return src;
    src = src.replace(/^(\.\/|\/)+/, "");
    return BASE + src;
  }

  imgs.forEach(img => {
    const type = (img.dataset.type || "").trim();
    const id = Number(img.dataset.id || 0);
    if (!id || !type) return;

    if (type === "book"){
      const b = pick(BOOKS, id);
      const src = normalizeSrc(b?.img || b?.cover || b?.image || "");
      if (src) img.src = src;
      return;
    }

    if (type === "textbook"){
      const tb = pick(TEXTBOOKS, id);
      const src = normalizeSrc(tb?.img || tb?.cover || tb?.image || "");
      if (src) img.src = src;
      return;
    }
  });
})();

/* 2) + / - / delete -> cart_api.php (NO RELOAD + no jumping) */
(function(){
  const sumCountEl = document.getElementById("sumCount");
  const sumSubEl   = document.getElementById("sumSub");
  const sumTotalEl = document.getElementById("sumTotal");

  function money(n){
    return (Number(n) || 0).toFixed(2) + " €";
  }

  function recalcTotals(){
    let count = 0;
    let sub = 0;

    document.querySelectorAll(".cart-item").forEach(row => {
      const unit = Number(row.dataset.unit || 0);
      const qEl = row.querySelector(".qty-val");
      const qty = Math.max(1, Number(qEl?.textContent || 1));
      count += qty;
      sub += unit * qty;

      const lineEl = row.querySelector(".js-line");
      if (lineEl) lineEl.textContent = money(unit * qty);
    });

    if (sumCountEl) sumCountEl.textContent = String(count);
    if (sumSubEl)   sumSubEl.textContent   = money(sub);
    if (sumTotalEl) sumTotalEl.textContent = money(sub); // при теб delivery е 0
  }

  async function post(action, data){
    const fd = new FormData();
    for (const k in data) fd.append(k, data[k]);
    const res = await fetch("<?= e($base) ?>cart_api.php?action=" + encodeURIComponent(action), {
      method: "POST",
      body: fd,
      credentials: "same-origin"
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok || !j.ok) throw new Error(j.err || "Грешка");
    return j;
  }

  document.addEventListener("click", async (e) => {
    const plus  = e.target.closest(".js-plus");
    const minus = e.target.closest(".js-minus");
    const del   = e.target.closest(".js-del");
    if (!plus && !minus && !del) return;

    try{
      if (del){
        const rowId = del.dataset.row;
        await post("remove", { row_id: rowId });

        const row = document.querySelector('.cart-item[data-row="'+rowId+'"]');
        if (row) row.remove();
        recalcTotals();
        return;
      }

      const rowId = (plus || minus).dataset.row;
      const qEl = document.getElementById("qty"+rowId);
      const cur = Number(qEl?.textContent || 1);
      const next = plus ? (cur + 1) : (cur - 1);

      const j = await post("setQty", { row_id: rowId, qty: String(next) });
      if (qEl) qEl.textContent = String(j.qty);

      recalcTotals();
    }catch(err){
      alert(err.message || "Грешка");
    }
  });

  recalcTotals();
})();
</script>


</body>
</html>