<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Завършване на поръчката";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<body class="km-layout">
<?php require_once "nav.php"; ?>

<!-- ✅ FIX: rel="stylesheet" -->
<link rel="stylesheet" href="<?= e($base) ?>checkout.css?v=1">

<!-- ✅ Малко CSS само за снимките в обобщението (можеш да го преместиш в checkout.css) -->
<style>
.checkout-img{
  width:64px;
  height:86px;
  object-fit:cover;
  border-radius:10px;
  background:#f3f4f6;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
  flex:0 0 auto;
}
</style>

<main class="container py-4" style="max-width:1100px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="h3 m-0">✅ Завършване на поръчката</h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($base) ?>cart.php">← Назад към количката</a>
  </div>

  <div id="checkoutEmpty" class="alert alert-info d-none">
    Количката е празна.
  </div>

  <div class="row g-4" id="checkoutWrap" style="display:none;">
    <!-- LEFT -->
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm mb-3" style="border-radius:16px;">
        <div class="card-body">
          <h5 class="mb-3">👤 Лични данни</h5>

          <div class="mb-3">
            <label class="form-label">Име и фамилия *</label>
            <input class="form-control" id="shipName" placeholder="Име и фамилия">
          </div>

          <div class="mb-3">
            <label class="form-label">Имейл *</label>
            <input class="form-control" id="shipEmail" type="email" placeholder="you@example.com">
          </div>

          <div class="mb-0">
            <label class="form-label">Телефон *</label>
            <input class="form-control" id="shipPhone" placeholder="+359...">
          </div>
        </div>
      </div>

      <div class="card shadow-sm" style="border-radius:16px;">
        <div class="card-body">
          <h5 class="mb-3">📍 Адрес за доставка</h5>

          <div class="mb-3">
            <label class="form-label">Град *</label>
            <input class="form-control" id="shipCity" placeholder="София">
          </div>

          <div class="mb-3">
            <label class="form-label">Адрес / офис на Еконт/Спиди *</label>
            <input class="form-control" id="shipAddr" placeholder="ул. ... / офис ...">
          </div>

          <div class="mb-3">
            <label class="form-label">Пощенски код</label>
            <input class="form-control" id="shipZip" placeholder="1000">
          </div>

          <div class="mb-0">
            <label class="form-label">Бележка</label>
            <textarea class="form-control" id="note" rows="4" placeholder="Допълнителни инструкции..."></textarea>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <button id="confirmOrderBtn" class="btn btn-success w-100" style="border-radius:14px; font-weight:800; padding:12px 14px;">
          ✅ Потвърди поръчката
        </button>
        <div id="checkoutMsg" class="mt-3 d-none"></div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm" style="border-radius:16px;">
        <div class="card-body">
          <h5 class="mb-3">🧾 Обобщение</h5>

          <div id="checkoutItems" class="vstack gap-3"></div>

          <hr class="my-3">

          <div class="d-flex justify-content-between">
            <div class="text-muted">Междинна сума:</div>
            <div class="fw-semibold"><span id="sumSub">0.00</span> €</div>
          </div>

          <div class="d-flex justify-content-between mt-2">
            <div class="text-muted">Доставка:</div>
            <div class="fw-semibold text-success">Безплатна</div>
          </div>

          <hr class="my-3">

          <div class="d-flex justify-content-between align-items-center">
            <div class="h6 m-0">Общо:</div>
            <div class="h5 m-0" style="font-weight:900;"><span id="sumTotal">0.00</span> €</div>
          </div>

          <div class="mt-3 p-3" style="border-radius:14px; background:rgba(0,0,0,.03);">
            <div class="fw-semibold">🚚 Доставка</div>
            <div class="text-muted small">Очаквай доставка до 2-3 работни дни.</div>
          </div>

          <div class="mt-2 p-3" style="border-radius:14px; background:rgba(0,0,0,.03);">
            <div class="fw-semibold">💳 Плащане</div>
            <div class="text-muted small">Наложен платеж при доставка.</div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once "footer.php"; ?>


<script src="<?= e($base) ?>bookslist.js?v=1"></script>
<script src="<?= e($base) ?>textbooks-data.js?v=1"></script>

<script>
window.KM_BASE = "<?= e($base) ?>";
window.KM_AUTH = window.KM_AUTH || { loggedIn: true };
</script>

<!-- ✅ нашият JS -->
<script src="<?= e($base) ?>checkout.js?v=3"></script>

</body>
</html>