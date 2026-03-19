<?php
require_once "db.php";
require_once "auth.php";
?>

<?php 
$page_title = "Нови учебници";
require_once("header.php"); 
?>

<!doctype html>
<html lang="bg" class="ub-anim">
<?php require_once "header.php"; ?>
<body class="km-layout">

<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="textbooks.css?v=3">

<section class="ub-hero">
  <div class="container ub-hero-inner">
    <div class="ub-hero-icons" aria-hidden="true">
      <span>🎓</span><span>📘</span>
    </div>
    <h1 class="ub-hero-title">Нови учебници</h1>
    <p class="ub-hero-subtitle">Всичко необходимо за успешна учебна година 📚</p>
  </div>

  <svg class="ub-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,64 C240,96 480,32 720,48 C960,64 1200,128 1440,80 L1440,120 L0,120 Z"></path>
  </svg>
</section>

<main class="ub-main">

  <div class="ub-filter-wrap">
    <div class="ub-filter-card">
      <div class="ub-filter-head">
        <div class="ub-filter-ico">🔎</div>
        <div class="ub-filter-title">Търсене на учебници</div>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="ub-label" for="tbQ">Търсене</label>
          <input id="tbQ" class="form-control ub-input" placeholder="Заглавие, предмет, издателство...">
        </div>

        <div class="col-md-4">
          <label class="ub-label" for="tbGrade">Клас</label>
          <select id="tbGrade" class="form-select ub-input">
            <option value="all">Всички класове</option>
            <option value="1-4">1 – 4 клас</option>
            <option value="5-7">5 – 7 клас</option>
            <option value="8-12">8 – 12 клас</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="ub-label" for="tbSubject">Предмет</label>
          <select id="tbSubject" class="form-select ub-input">
            <option value="all">Всички предмети</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="tb-year">
    <div class="tb-year-title">Учебна година 2026/2027 🎓</div>
    <div class="tb-year-sub">Всички одобрени от МОН учебници на едно място</div>

    <div class="tb-chips">
      <span class="tb-chip">Безплатна доставка над 50 €</span>
      <span class="tb-chip">Гаранция за качество</span>
      <span class="tb-chip">Бързи срокове</span>
    </div>
  </div>

  <div class="container mt-4" style="max-width:1100px;">
    <div id="tbMeta" class="tb-meta"></div>

    <noscript>
      <div class="tb-empty">
        <div class="tb-empty-title">JavaScript е изключен</div>
        <div class="tb-empty-sub">За да видиш учебниците, включи JavaScript.</div>
      </div>
    </noscript>

    <div id="tbTableWrap">
      <div class="tb-empty" id="tbLoadingFallback" style="display:none;">
        <div class="tb-empty-title">Не успях да заредя учебниците</div>
        <div class="tb-empty-sub">
          Провери дали <b>textbooks-data.js</b> се зарежда (F12 → Console / Network).
        </div>
      </div>
    </div>
  </div>

</main>

<?php require_once "footer.php"; ?>

<!-- DATA -->
<script src="textbooks-data.js?v=1" defer></script>

<!-- LOGIC (тук е handler-a за сърцата + филтри) -->
<script src="textbooks.js?v=1002" defer></script>

<!-- DATA FALLBACK -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => {
    const ok = Array.isArray(window.TEXTBOOKS) && window.TEXTBOOKS.length > 0;
    const fb = document.getElementById("tbLoadingFallback");
    if (!ok && fb) fb.style.display = "block";
  }, 400);
});
</script>

<!-- ✅ FIX: animation on BACK / FORWARD -->
<script>
(function(){
  const html = document.documentElement;
  const body = document.body;

  function restartAnim(el){
    if (!el) return;
    el.classList.remove("ub-anim");
    el.style.animation = "none";
    void el.offsetHeight;
    requestAnimationFrame(() => {
      el.style.animation = "";
      el.classList.add("ub-anim");
    });
  }

  function replay(){
    restartAnim(html);
    restartAnim(body);
  }

  window.addEventListener("pageshow", replay);
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") replay();
  });
  window.addEventListener("load", replay);
})();
</script>

</body>
</html>