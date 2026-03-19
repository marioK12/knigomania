<!doctype html>
<html lang="bg">
<?php 
$page_title = "За нас";
require_once("header.php"); 
?>

<body class="km-layout" style="background-color: #f8f3ea !important;">
<link rel ="stylesheet" href="about.css">
<!-- Навигацията-->
<?php
    require_once("nav.php");
?>

<!-- Края на навигацията-->

<!-- Начало на мейн-->
<main class="km-main">
<section class="hero-orange text-white py-5">
  <div class="container text-center story-anim">
    <h1 class="display-4 fw-bold">За нас</h1>
    <p class="lead mt-3">
      Вашата дестинация за добра литература и незабравими четива
    </p>
  </div>
</section>
<!-- Разделение-->
<section class="py-5" style="background:#f8f3ea;">
<div class="container">
  <div class="story-anim">
    <div class="story-box p-4 p-md-5 bg-white rounded-4">
      <h2 class="fw-bold mb-3">Нашата история</h2>

      <p class="mb-3">
        КнигоМания започна като малък семеен магазин през 2010 година с мечта - да направи качествената литература достъпна за всички. От скромно начало с няколкостотин книги, днес предлагаме хиляди заглавия във всички жанрове.
      </p>

      <p class="mb-3">
        Вярваме, че всяка книга има своя читател, и всеки читател заслужава да намери своята книга. Затова внимателно подбираме нашата колекция и се грижим всеки клиент да получи най-доброто обслужване.
      </p>

      <p class="mb-0">
        Специално внимание отделяме и на устойчивостта - нашата програма за употребявани книги помага на хиляди заглавия да намерят нов дом, вместо да завършат в боклука.
      </p>
    </div>
  </div>
</div>
</section>
<!-- Разделение-->
<section class="py-5" style="background:#f8f3ea;">
  <div class="container">

    <h2 class="text-center fw-bold mb-4">Нашите ценности</h2>

    <div class="row g-4">

      <!-- 1-ва Карта -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-anim">
          <div class="value-card card h-100 rounded-4 bg-white">
            <div class="card-body text-center p-4">
              <div class="value-icon icon-orange mb-3 contact-icon-animation">📚</div>
              <h5 class="fw-bold mb-2">Качество</h5>
              <p class="text-muted mb-0">
                Предлагаме само най-качествени заглавия от водещи издателства.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- 2-та Карта -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-anim">
          <div class="value-card card h-100 rounded-4 bg-white">
            <div class="card-body text-center p-4">
              <div class="value-icon icon-blue mb-3 contact-icon-animation">❤️</div>
              <h5 class="fw-bold mb-2">Страст</h5>
              <p class="text-muted mb-0">
                Обичаме книгите и искаме да споделим тази любов с вас.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- 3-та Карта -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-anim">
          <div class="value-card card h-100 rounded-4 bg-white">
            <div class="card-body text-center p-4">
              <div class="value-icon icon-green mb-3 contact-icon-animation">👥</div>
              <h5 class="fw-bold mb-2">Общност</h5>
              <p class="text-muted mb-0">
                Изграждаме общност от любители на четенето.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- 4-та Карта -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-anim">
          <div class="value-card card h-100 rounded-4 bg-white">
            <div class="card-body text-center p-4">
              <div class="value-icon icon-purple mb-3 contact-icon-animation">🏅</div>
              <h5 class="fw-bold mb-2">Доверие</h5>
              <p class="text-muted mb-0">
                Вашето доверие е нашата най-голяма награда.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- Разделение-->
<section class="py-5" style="background:#f8f3ea;">
  <div class="container">

    <div class="stats-anim">
      <div class="stats-box bg-white rounded-4 p-4 p-md-5">
        <div class="row text-center g-4">

          <div class="col-6 col-lg-3">
            <div class="stat-number">15+</div>
            <div class="stat-label">Години опит</div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="stat-number">48 ч.</div>
            <div class="stat-label">Бърза доставка</div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="stat-number">10K+</div>
            <div class="stat-label">Доволни клиенти</div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="stat-number">100%</div>
            <div class="stat-label">Гаранция за качество</div>
          </div>

        </div>
      </div>
    </div>

  </div>
</section>

</main>
<!-- Край на мейн-->

<!--Начало на футър-->

  <?php
    require_once("footer.php");
  ?>
<!-- Край на Футър-->

<script>
  const path = location.pathname.split("/").pop() || "index.php";
  document.querySelectorAll(".km-link").forEach(a => {
    const href = (a.getAttribute("href") || "").split("?")[0];
    if (href === path) a.classList.add("active");
  });
</script>

</body>
</html>