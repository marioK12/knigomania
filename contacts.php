<!doctype html>
<html lang="bg">

<?php 
$page_title = "Контакти";
require_once("header.php"); 
?>

<body class="km-layout" style="background-color: #f8f3ea !important;">
<link rel ="stylesheet" href="contacts.css">
<!-- Навигацията-->
<?php
    require_once("nav.php");
?>
<!-- Края на навигацията-->


<!-- Начало на мейн-->
<main class="km-main">

  <!-- HERO -->
  <section class="hero-orange text-white py-5">
    <div class="container text-center anim-fade anim-delay-1">
      <h1 class="display-4 fw-bold">Контакти</h1>
      <p class="lead mt-3">
        Имате въпрос? Пишете ни и ще ви отговорим възможно най-скоро!
      </p>
    </div>
  </section>

  <!-- CONTENT -->
  <section class="py-5" style="background:#f8f3ea;">
    <div class="container">
      <div class="row g-4">

        <!-- ЛЯВО: ФОРМА -->
        <div class="col-12 col-lg-6">
          <div class="anim-fade anim-delay-2">
            <div class="box-ui p-4 p-md-5">
              <h4 class="fw-bold mb-4">Изпратете съобщение</h4>
              <?php if (isset($_GET["status"]) && $_GET["status"] === "success"): ?>
              <div class="alert alert-success">
              Съобщението беше изпратено успешно!
              </div>
              <?php elseif (isset($_GET["status"]) && $_GET["status"] === "error"): ?>
              <div class="alert alert-danger">
              Възникна грешка. Моля, опитайте отново.
              </div>
              <?php endif; ?>

              <form action="send-mail.php" method="POST">
  <div class="mb-3">
    <label class="form-label fw-semibold">
      Име <span class="text-danger">*</span>
    </label>
    <input 
      type="text" 
      name="name" 
      class="form-control" 
      placeholder="Вашето име" 
      required
    >
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold">
      Имейл <span class="text-danger">*</span>
    </label>
    <input 
      type="email" 
      name="email" 
      class="form-control" 
      placeholder="your@email.com" 
      required
    >
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold">Тема</label>
    <input 
      type="text" 
      name="subject" 
      class="form-control" 
      placeholder="Относно..."
    >
  </div>

  <div class="mb-4">
    <label class="form-label fw-semibold">
      Съобщение <span class="text-danger">*</span>
    </label>
    <textarea 
      name="message" 
      class="form-control" 
      rows="6" 
      placeholder="Вашето съобщение..." 
      required
    ></textarea>
  </div>

  <button type="submit" class="btn btn-dark px-4 py-2">
    Изпрати
  </button>
</form>
            </div>
          </div>
        </div>

        <!-- ДЯСНО: КАРТИ -->
        <div class="col-12 col-lg-6">
          <div class="d-flex flex-column gap-4">

            <div class="anim-fade anim-delay-3">
              <div class="box-ui p-4 d-flex align-items-start gap-3">
                <div class="info-icon icon-orange contact-icon-anim">✉️</div>
                <div>
                  <h5 class="fw-bold mb-1">Имейл</h5>
                  <div class="text-muted">info@knigomania.bg</div>
                  <div class="text-muted">support@knigomania.bg</div>
                </div>
              </div>
            </div>

            <div class="anim-fade anim-delay-4">
              <div class="box-ui p-4 d-flex align-items-start gap-3">
                <div class="info-icon icon-blue contact-icon-anim">📞</div>
                <div>
                  <h5 class="fw-bold mb-1">Телефон</h5>
                  <div class="text-muted">+359 888 123 456</div>
                </div>
              </div>
            </div>

            <div class="anim-fade anim-delay-5">
              <div class="box-ui p-4 d-flex align-items-start gap-3">
                <div class="info-icon icon-green contact-icon-anim">📍</div>
                <div>
                  <h5 class="fw-bold mb-1">Адрес</h5>
                  <div class="text-muted">ул. Свобода 12</div>
                  <div class="text-muted">Благоевград, България</div>
                </div>
              </div>
            </div>

            <div class="anim-fade anim-delay-5">
              <div class="box-ui p-4 d-flex align-items-start gap-3">
                <div class="info-icon icon-green contact-icon-anim">⏰</div>
                <div>
                  <h5 class="fw-bold mb-1">Работно време</h5>
                  <div class="text-muted">Понеделник - Петък:  9:00 - 19:00</div>
                  <div class="text-muted">Събота:  10:00 - 16:00</div>
                  <div class="text-muted">Неделя:  Почивен ден</div>
                </div>
              </div>
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
  
<script>
  const path = location.pathname.split("/").pop() || "index.php";
  document.querySelectorAll(".km-link").forEach(a => {
    const href = (a.getAttribute("href") || "").split("?")[0];
    if (href === path) a.classList.add("active");
  });
</script>

</body>
</html>