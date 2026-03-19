<!doctype html>
<html lang="bg">
<?php require_once("header.php"); ?>
<body>

<?php require_once("nav.php"); ?>

<?php
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// ROUTES
$urlCategories = $base . "categories.php";
$urlUsedBooks  = $base . "usedbooks.php";
$urlTextbooks  = $base . "textbooks.php";
$urlRegister   = $base . "register.php";
$urlGiftBook   = $base . "gift_books.php";
$urlCampaigns  = $base . "campaigns.php";

// HERO background
$heroBg = $base . "hero-bg.jpg";
?>

<link rel="stylesheet" href="<?= e($base) ?>books.css?v=1">
<link rel="stylesheet" href="<?= e($base) ?>home.css?v=1">

<style>
  :root{
    --home-ink:#0f172a;
    --home-muted: rgba(15,23,42,.68);

    --home-a1:#ff7a18;
    --home-a2:#f59e0b;
    --home-a3:#ea580c;
    --home-a4:#7c3aed;

    --home-card: rgba(255,255,255,.92);
    --home-stroke: rgba(15,23,42,.10);

    --home-shadow: 0 18px 55px rgba(2,6,23,.12);
    --home-shadow2: 0 10px 24px rgba(2,6,23,.10);

    --home-r: 22px;
  }

  /* HERO */
  .km-hero{
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid rgba(255,255,255,.18);
    isolation: isolate;
  }
  .km-hero::before{
    content:"";
    position:absolute; inset:-2px;
    background:
      radial-gradient(900px 420px at 14% 18%, rgba(255,255,255,.16), transparent 60%),
      radial-gradient(900px 420px at 86% 14%, rgba(255,255,255,.10), transparent 60%),
      linear-gradient(120deg, rgba(234,88,12,.88), rgba(245,158,11,.72));
    mix-blend-mode: overlay;
    pointer-events:none;
  }
  .km-hero::after{
    content:"";
    position:absolute; inset:0;
    background:
      radial-gradient(800px 300px at 50% 0%, rgba(124,58,237,.18), transparent 60%),
      linear-gradient(to bottom, rgba(2,6,23,.10), rgba(2,6,23,.26));
    pointer-events:none;
  }

  /* glow */
  .km-hero .hero-glow{
    position:absolute; inset:0;
    z-index:1;
    pointer-events:none;
  }
  .km-hero .hero-glow::before,
  .km-hero .hero-glow::after{
    content:"";
    position:absolute;
    width: 520px;
    height: 520px;
    border-radius: 50%;
    filter: blur(18px);
    opacity:.55;
    transform: translate3d(0,0,0);
  }
  .km-hero .hero-glow::before{
    left:-180px; top:-220px;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.22), rgba(245,158,11,.16), transparent 62%);
  }
  .km-hero .hero-glow::after{
    right:-220px; top:-260px;
    background: radial-gradient(circle at 40% 40%, rgba(124,58,237,.18), rgba(29,155,240,.12), transparent 64%);
  }

  .km-hero-inner{
    position: relative;
    z-index: 2;
    max-width: 820px;
  }

  .km-hero-kicker{
    display:inline-flex;
    gap:10px;
    align-items:center;
    padding:10px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.22);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 12px 30px rgba(0,0,0,.12);
    font-weight: 800;
    letter-spacing: .2px;
  }

  .km-hero-title{
    text-shadow: 0 18px 55px rgba(0,0,0,.28);
    letter-spacing: -.2px;
    margin-top: 12px;
    margin-bottom: 10px;
  }

  .km-hero-sub{
    max-width: 58ch;
    opacity: .95;
    text-shadow: 0 12px 30px rgba(0,0,0,.18);
  }

  /* =========================
     ✅ FIXED HERO BUTTONS
  ========================= */
  .km-hero-actions{
    display:flex;
    flex-direction:column;
    gap:14px;
    margin-top: 20px;
  }

  .km-hero-actions .km-hero-row{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    justify-content:center;
    align-items:center;
  }

  /* ✅ FIX: second row (gift + campaigns) to stay together, centered */
.km-hero-actions{
  align-items:center; /* centers the inline row */
}

.km-hero-actions .km-hero-row:last-child{
  display:inline-flex;      /* key: doesn't stretch full width */
  width:auto;               /* key: shrink to content */
  margin: 0 auto;           /* center the group */
  justify-content:center;
  gap:14px;
}


  .km-hero-actions .btn{
    border-radius: 14px !important;
    padding: 11px 14px !important;
    font-weight: 800 !important;
    letter-spacing: .15px;
    border: 1px solid rgba(255,255,255,.20) !important;
    background: rgba(255,255,255,.14) !important;
    color: #fff !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 14px 34px rgba(0,0,0,.14);
    transform: translateY(0);
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease;

    width:auto !important;
    min-width: 180px;
    max-width: 240px;
    justify-content:center;
    text-align:center;
  }
  .km-hero-actions .btn:hover{
    transform: translateY(-2px);
    box-shadow: 0 18px 44px rgba(0,0,0,.18);
    background: rgba(255,255,255,.20) !important;
  }

  /* first row slightly smaller */
  .km-hero-actions .km-hero-row:first-child .btn{
    padding: 10px 14px !important;
    min-width: 170px;
    max-width: 230px;
    opacity: .92;
  }

  /* second row CTA bigger */
  .km-hero-actions .km-hero-row:last-child{
    gap:14px;
    margin-top: 2px;
  }
  .km-hero-actions .km-hero-row:last-child .btn{
    min-width: 220px;
    max-width: 260px;
    padding: 12px 16px !important;
    font-size: 1rem;
    opacity: 1;
  }

  /* special CTA buttons */
  .km-hero-actions .km-btn-gift{
    background: linear-gradient(135deg, rgba(124,58,237,.45), rgba(29,155,240,.28)) !important;
    border: 1px solid rgba(255,255,255,.26) !important;
  }
  .km-hero-actions .km-btn-campaigns{
    background: linear-gradient(135deg, rgba(34,197,94,.36), rgba(14,165,233,.22)) !important;
    border: 1px solid rgba(255,255,255,.26) !important;
  }

  /* HERO fade */
  .km-hero-fade{
    background: linear-gradient(to bottom, rgba(2,6,23,0), rgba(2,6,23,.22));
  }

  /* HERO-TO wave */
  .hero-to{
    position:absolute;
    left:0; right:0;
    bottom:-1px;
    height: 86px;
    z-index: 3;
    pointer-events:none;
  }
  .hero-to svg{ width:100%; height:100%; display:block; }
  .hero-to .hero-to-fill{
    fill: rgba(255,255,255,.92);
    filter: drop-shadow(0 -10px 26px rgba(2,6,23,.12));
  }
  .hero-to .hero-to-line{
    fill:none;
    stroke: rgba(255,255,255,.65);
    stroke-width: 1.25;
    opacity:.75;
  }

  /* FEATURES */
  .km-features{
    position: relative;
    padding: 22px 0 10px;
    margin-top: -28px;
    z-index: 3;
  }
  .km-features .container{ position: relative; }

  .km-feature{
    position: relative;
    border-radius: var(--home-r);
    background: var(--home-card);
    border: 1px solid var(--home-stroke);
    box-shadow: var(--home-shadow);
    padding: 16px 16px 44px;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
    color: var(--home-ink);
  }
  .km-feature:hover{
    transform: translateY(-3px);
    box-shadow: 0 22px 70px rgba(2,6,23,.16);
  }
  .km-feature::before{
    content:"";
    position:absolute;
    top:-40px; left:-40px;
    width: 140px; height: 140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245,158,11,.20), transparent 60%);
    filter: blur(2px);
  }
  .km-feature-icon{
    width: 50px;
    height: 50px;
    border-radius: 16px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size: 22px;
    margin-bottom: 10px;
    box-shadow: inset 0 0 0 1px rgba(15,23,42,.08), 0 14px 30px rgba(2,6,23,.10);
    background: rgba(245,158,11,.14);
  }
  .km-feature-icon.orange{ background: rgba(245,158,11,.18); }
  .km-feature-icon.blue{ background: rgba(29,155,240,.14); }
  .km-feature-icon.green{ background: rgba(34,197,94,.14); }
  .km-feature-icon.pink{ background: rgba(236,72,153,.12); }

  .km-feature-title{
    font-weight: 900;
    font-size: 1.02rem;
    margin-bottom: 2px;
  }
  .km-feature-sub{
    color: var(--home-muted);
    font-weight: 600;
    font-size: .95rem;
  }
  .km-feature-more{
    display:none;
    margin-top: 10px;
    color: rgba(15,23,42,.72);
    line-height: 1.5;
    font-size: .93rem;
  }
  .km-feature.open .km-feature-more{ display:block; }

  .km-feature-toggle{
    position:absolute;
    bottom: 10px;
    right: 12px;
    width: 34px;
    height: 34px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.72);
    box-shadow: 0 10px 22px rgba(2,6,23,.10);
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size: 16px;
    line-height: 1;
    opacity: .85;
    transition: transform .18s ease, opacity .18s ease, background .18s ease;
  }
  .km-feature-toggle:hover{
    opacity: 1;
    background: rgba(255,255,255,.92);
  }
  .km-feature.open .km-feature-toggle{
    transform: rotate(180deg);
    opacity: 1;
  }

  @media (max-width: 576px){
    .km-features{ margin-top: -18px; }
    .km-feature{ padding: 14px 14px 44px; }
    .hero-to{ height: 64px; }

    .km-hero-actions .km-hero-row{
      flex-direction:column;
      gap:10px;
    }
    .km-hero-actions .btn{
      width:100% !important;
      min-width: 0;
      max-width: 520px;
    }
  }
</style>

<section class="km-hero"
  style="background-image:
    linear-gradient(120deg, rgba(194,65,12,.88), rgba(245,158,11,.70)),
    url('<?= e($heroBg) ?>');">

  <div class="hero-glow" aria-hidden="true"></div>

  <div class="container">
    <div class="km-hero-inner">
      <div class="km-hero-kicker">✨ Добре дошли в КнигоМания</div>

      <h1 class="km-hero-title">
        Открийте вашата<br>следваща любима книга
      </h1>

      <p class="km-hero-sub">
        Нови книги, книги втора ръка и учебници. Поръчвайте лесно онлайн.
      </p>

      <div class="km-hero-actions">
        <div class="km-hero-row">
          <a class="btn km-btn-white" href="<?= e($base . "books.php") ?>">Виж категории →</a>
          <a class="btn km-btn-white" href="<?= e($urlUsedBooks) ?>">Книги втора ръка →</a>
          <a class="btn km-btn-white" href="<?= e($urlTextbooks) ?>">Учебници →</a>
          <a class="btn km-btn-white" href="<?= e($base . "textbooks_secondhand.php") ?>">Учебници втора ръка →</a>
        </div>

        <div class="km-hero-row">
          <a class="btn km-btn-white km-btn-gift" href="<?= e($base . "giftbooks.php") ?>">🎁 Подари книга →</a>
          <a class="btn km-btn-white km-btn-campaigns" href="<?= e($urlCampaigns) ?>">🤝 Кампании →</a>
        </div>
      </div>

    </div>
  </div>

  <div class="hero-to" aria-hidden="true">
    <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
      <path class="hero-to-fill" d="M0,64 C180,110 360,20 540,48 C720,78 900,140 1080,96 C1260,54 1350,10 1440,34 L1440,120 L0,120 Z"></path>
      <path class="hero-to-line" d="M0,64 C180,110 360,20 540,48 C720,78 900,140 1080,96 C1260,54 1350,10 1440,34"></path>
    </svg>
  </div>

  <div class="km-hero-fade"></div>
</section>

<section class="km-features">
  <div class="container">
    <div class="row g-3 justify-content-center">

      <div class="col-12 col-md-6 col-lg-3">
        <div class="km-feature">
          <div class="km-feature-icon orange km-icon-anim">🚚</div>
          <div class="km-feature-title">Безплатна доставка</div>
          <div class="km-feature-sub">При поръчки над 50 €</div>

          <div class="km-feature-more" aria-hidden="true">
            Доставяме бързо и сигурно до цялата страна. Опаковаме внимателно, за да пристигнат книгите без забележки.
            При поръчки над 50 € доставката е безплатна. Получавате информация и проследяване на пратката.
          </div>

          <button class="km-feature-toggle" type="button" aria-expanded="false" aria-label="Покажи повече">▾</button>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <div class="km-feature">
          <div class="km-feature-icon blue km-icon-anim">🛡️</div>
          <div class="km-feature-title">Гаранция</div>
          <div class="km-feature-sub">100% проверени книги</div>

          <div class="km-feature-more" aria-hidden="true">
            Всяка книга се проверява преди изпращане. Описанията са точни и отговарят на реалното състояние.
            Ако има проблем, реагираме веднага. Целта ни е да получавате само качествени заглавия.
          </div>

          <button class="km-feature-toggle" type="button" aria-expanded="false" aria-label="Покажи повече">▾</button>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <div class="km-feature">
          <div class="km-feature-icon green km-icon-anim">🎧</div>
          <div class="km-feature-title">Поддръжка</div>
          <div class="km-feature-sub">Помощ 24/7</div>

          <div class="km-feature-more" aria-hidden="true">
            При въпроси за поръчка, доставка или наличности – пишете ни. Помагаме с препоръки и насоки според интересите ви.
            Отговаряме бързо и ясно. Винаги може да разчитате на съдействие.
          </div>

          <button class="km-feature-toggle" type="button" aria-expanded="false" aria-label="Покажи повече">▾</button>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <div class="km-feature">
          <div class="km-feature-icon pink km-icon-anim">💜</div>
          <div class="km-feature-title">С любов</div>
          <div class="km-feature-sub">Страст към книгите</div>

          <div class="km-feature-more" aria-hidden="true">
            КнигоМания е проект, създаден с любов към четенето. Подбираме заглавия с внимание и грижа към детайла.
            Вярваме, че книгите правят хората по-свободни. Искаме всяка поръчка да носи радост.
          </div>

          <button class="km-feature-toggle" type="button" aria-expanded="false" aria-label="Покажи повече">▾</button>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="home-section home-section-books">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
      <div>
        <h2 class="sec-title">Препоръчани книги</h2>
        <div class="sec-sub">От всички категории (featured)</div>
      </div>
      <a class="btn btn-warning" href="<?= e($base . "books.php") ?>">Виж всички →</a>
    </div>

    <div class="row g-4" id="homeRecommendedGrid"></div>
  </div>
</section>

<section class="home-section home-section-textbooks">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
      <div>
        <h2 class="sec-title">Учебници</h2>
        <div class="sec-sub">Подбрани оферти за ученици и родители</div>
      </div>
      <a class="btn btn-success" href="<?= e($base . "textbooks.php") ?>">Виж всички →</a>
    </div>

    <div class="row g-4" id="homeTextbooksGrid"></div>
  </div>
</section>

<section class="home-section">
  <div class="container">
    <div class="join-box">
      <div class="join-inner">
        <h3 class="join-title">Станете част от нашата общност</h3>
        <p class="join-sub">
          Запазете любимите си книги, добавяйте ревюта и получавайте персонализирани препоръки.
        </p>
        <a class="btn btn-light btn-lg join-btn" href="<?= e($base . "register.php") ?>">Регистрирай се сега</a>
      </div>
    </div>
  </div>
</section>

<?php require_once("footer.php"); ?>

<script src="<?= e($base) ?>bookslist.js"></script>

<script>
  if (typeof BOOKS !== "undefined") window.BOOKS = BOOKS;
</script>

<script src="<?= e($base) ?>textbooks-data.js?v=1"></script>

<script src="<?= e($base) ?>home-recommended.js?v=1"></script>
<script src="<?= e($base) ?>home-textbooks.js?v=1"></script>

<script>
  const path = location.pathname.split("/").pop() || "index.php";
  document.querySelectorAll(".km-link").forEach(a => {
    const href = (a.getAttribute("href") || "").split("?")[0];
    if (href === path) a.classList.add("active");
  });
</script>

<script>
  (function(){
    const toggles = document.querySelectorAll(".km-feature-toggle");

    function closeCard(card){
      card.classList.remove("open");
      const btn = card.querySelector(".km-feature-toggle");
      const more = card.querySelector(".km-feature-more");
      if (btn) btn.setAttribute("aria-expanded","false");
      if (more) more.setAttribute("aria-hidden","true");
    }

    function openCard(card){
      card.classList.add("open");
      const btn = card.querySelector(".km-feature-toggle");
      const more = card.querySelector(".km-feature-more");
      if (btn) btn.setAttribute("aria-expanded","true");
      if (more) more.setAttribute("aria-hidden","false");
    }

    toggles.forEach(btn=>{
      btn.addEventListener("click", ()=>{
        const card = btn.closest(".km-feature");
        if (!card) return;

        const isOpen = card.classList.contains("open");

        document.querySelectorAll(".km-feature.open").forEach(opened=>{
          if (opened !== card) closeCard(opened);
        });

        if (isOpen) closeCard(card);
        else openCard(card);
      });
    });
  })();
</script>

</body>
</html>
