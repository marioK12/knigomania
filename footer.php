<?php
// base url (subfolder safe)
if (!isset($base)) {
  $base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
  $base = ($base === "" || $base === ".") ? "/" : ($base . "/");
}
if (!function_exists("e")) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
}
?>

<link rel="stylesheet" href="<?= e($base) ?>footer.css?v=1">

<footer class="footer-main text-white pt-5 pb-4">
  <div class="container">
    <div class="row g-5 justify-content-between text-start">


      <!-- Brand -->
      <div class="col-12 col-lg-4">
        <h5 class="fw-bold mb-3">📚 КнигоМания</h5>

        <p class="footer-text mb-2">
          Вашата дестинация за нови и употребявани книги, учебници и общност от читатели.
        </p>

        <p class="footer-text mb-2">
          📖 Откривайте заглавия от всички жанрове – художествена литература, научни книги, детски издания,
          както и учебници за различни класове и предмети.
        </p>

        <p class="footer-text mb-3">
          💬 Свързваме хората чрез четенето – запазвайте любими, пишете ревюта и общувайте с други читатели.
        </p>

        <div class="footer-text small">
          <div class="mb-1">✨ Откривай • ❤️ Запазвай • 💬 Общувай</div>
          <div>🎁 Подари книга и направи добро – всяка книга заслужава втори живот.</div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="col-12 col-sm-6 col-lg-3">
        <h5 class="fw-bold mb-3">Бързи връзки</h5>

        <ul class="list-unstyled mb-0 footer-links">
          <li><a href="<?= e($base) ?>index.php">Начало</a></li>
          <li><a href="<?= e($base) ?>books.php">Нови книги</a></li>
          <li><a href="<?= e($base) ?>usedbooks.php">Книги втора ръка</a></li>
          <li><a href="<?= e($base) ?>textbooks.php">Нови учебници</a></li>
          <li><a href="<?= e($base) ?>textbooks_secondhand.php">Учебници втора ръка</a></li>
          <li><a href="<?= e($base) ?>giftbooks.php">Подари книга</a></li>
          <li><a href="<?= e($base) ?>campaigns.php">Кампании</a></li>
          <li><a href="<?= e($base) ?>favorites.php">Любими</a></li>
          <li><a href="<?= e($base) ?>cart.php">Количка</a></li>
          <li><a href="<?= e($base) ?>blog.php">Блог</a></li>
          <li><a href="<?= e($base) ?>about.php">За нас</a></li>
          <li><a href="<?= e($base) ?>contacts.php">Контакти</a></li>
        </ul>
      </div>
      
      <!-- Profile -->
      <div class="col-12 col-sm-6 col-lg-2">
        <h5 class="fw-bold mb-3">Профил</h5>

        <ul class="list-unstyled mb-0 footer-links">
          <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
            <li><a href="<?= e($base) ?>settings.php">Настройки</a></li>
            <li><a href="<?= e($base) ?>messages_inbox.php">Съобщения</a></li>
            <li><a href="<?= e($base) ?>logout.php">Изход</a></li>
          <?php else: ?>
            <li><a href="<?= e($base) ?>login.php">Вход</a></li>
            <li><a href="<?= e($base) ?>register.php">Регистрация</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Contacts -->
      <div class="col-12 col-sm-6 col-lg-3">
        <h5 class="fw-bold mb-3">Контакти</h5>
        <ul class="list-unstyled mb-0 footer-text">
        <li class="mb-2">✉️ info@knigomania.bg</li>
        <li class="mb-2">📞 +359 888 123 456</li>
        <li class="mb-2">📍 Благоевград, България</li>
        <li class="mt-2 footer-links">
  <a href="<?= e($base) ?>contacts.php">
    📬 Страница за контакти →
  </a>
</li>

        </ul>
      </div>

    </div>

    <hr class="footer-line my-4">

    <div class="text-center footer-bottom">
      © <?= date('Y') ?> КнигоМания. Всички права запазени.
    </div>
  </div>
</footer>

<!-- Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Favorites -->
<script src="<?= e($base) ?>favorites.js"></script>

<!-- Global km-fav handler -->
<script>
(function(){
  if (window.__kmFavFooterWired) return;
  window.__kmFavFooterWired = true;

  function toLikedBool(v){
    if (v === null || v === undefined) return null;
    if (typeof v === "boolean") return v;
    if (typeof v === "number") return v === 1;
    if (typeof v === "string"){
      const s = v.trim().toLowerCase();
      if (["1","true","yes","liked"].includes(s)) return true;
      if (["0","false","no","unliked"].includes(s)) return false;
    }
    return !!v;
  }

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".km-fav");
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const type = btn.dataset.type;
    const id = parseInt(btn.dataset.id || "0", 10);
    if (!type || !id) return;

    if (!window.KMFav?.toggle) return;

    const raw = await KMFav.toggle(type, id);

    if (raw === null){
      const next = encodeURIComponent(location.pathname + location.search);
      location.href = "<?= e($base) ?>login.php?next=" + next;
      return;
    }

    const liked = toLikedBool(raw);
    btn.classList.toggle("is-active", liked);
    btn.classList.toggle("liked", liked);

    btn.style.transform = "scale(1.12)";
    setTimeout(() => btn.style.transform = "", 120);
  }, true);
})();
</script>