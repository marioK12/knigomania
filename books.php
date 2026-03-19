<!doctype html>
<html lang="bg">
<?php 
$page_title = "Всички книги";
require_once("header.php"); 
?>

<body class="km-layout">

  <link rel="stylesheet" href="books.css">

  <?php require_once("nav.php"); ?>

  <main class="km-main py-5">
    <div class="container">

      <h1 class="mb-1 reveal" style="animation-delay:.05s">Всички книги</h1>
      <p class="text-muted mb-4 reveal" style="animation-delay:.12s">Открийте вашата следваща любима книга</p>

      <!-- Филтри -->
      <section class="p-4 km-filters reveal" style="animation-delay:.18s">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span>🧪</span>
          <h6 class="m-0">Филтри</h6>
        </div>

        <div class="row g-3 align-items-end">
          <div class="col-12 col-lg-4">
            <label class="form-label small mb-1">Търсене</label>
            <input id="q" type="text" class="form-control" placeholder="Заглавие или автор...">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label small mb-1">Категория</label>
            <select id="cat" class="form-select">
              <option value="">Всички категории</option>
              <option value="hudojestvena">Художествена</option>
              <option value="nauchna">Научна</option>
              <option value="detski">Детски</option>
              <option value="fantastika">Фантастика</option>
              <option value="fentezi">Фентъзи</option>
              <option value="trilari">Трилъри</option>
              <option value="krimi">Криминални</option>
              <option value="romantika">Романтика</option>
              <option value="istoriya">История</option>
              <option value="biografii">Биографии</option>
              <option value="psihologiya">Психология</option>
              <option value="samorazvitie">Саморазвитие</option>
              <option value="biznes">Бизнес</option>
              <option value="poeziya">Поезия</option>
            </select>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label small mb-1">Подреди по</label>
            <select id="sort" class="form-select">
              <option value="new">Най-нови</option>
              <option value="priceAsc">Цена (възх.)</option>
              <option value="priceDesc">Цена (низх.)</option>
              <option value="titleAsc">Име (A–Z)</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Брой резултати -->
      <div class="mb-5 text-muted reveal" style="animation-delay:.25s">
        Намерени <strong id="count">0</strong> книги
      </div>

      <!-- Empty state -->
      <div id="emptyState" class="text-center p-4 km-empty d-none mb-5 reveal" style="animation-delay:.30s">
        <h5 class="mb-2">Няма намерени книги 😕</h5>
        <p class="text-muted mb-3">Опитай с друга дума или изчисти филтрите.</p>
        <button id="clearFiltersBtn" type="button" class="btn btn-warning">Покажи всички книги</button>
      </div>

      <!-- Grid -->
      <div class="row g-4" id="grid"></div>

    </div>
  </main>

  <?php require_once("footer.php"); ?>

  <script src="bookslist.js"></script>
  <script src="books.js"></script>

</body>
</html>
