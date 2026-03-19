<?php
require_once "db.php";
require_once "auth.php";

/* =========================
   SAFE HELPERS (no redeclare)
========================= */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
}
if (!function_exists('km_initials')) {
  function km_initials(?string $name, ?string $email): string {
    $s = trim((string)($name ?: ""));
    if ($s === "") $s = trim((string)$email);
    if ($s === "") return "U";
    $parts = preg_split('/\s+/', $s);
    $a = mb_substr($parts[0], 0, 1, 'UTF-8');
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1, 'UTF-8') : "";
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out !== "" ? $out : "U";
  }
}

/* =========================
   Base URL (subfolder-safe)
========================= */
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

/* =========================
   User / Avatar / Unread
========================= */
$AVATAR_DIR_URL = $base . "usersIMG/";
$cu = is_logged_in() ? current_user() : null;

$avatarFile = $cu["avatar"] ?? "";
$avatarUrl  = $avatarFile ? ($AVATAR_DIR_URL . $avatarFile) : "";
$ini = $cu ? km_initials($cu["name"] ?? null, $cu["email"] ?? null) : "U";

$unread_msgs = 0;
if ($cu) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND read_at IS NULL");
  $st->execute([(int)$cu["id"]]);
  $unread_msgs = (int)$st->fetchColumn();
}
?>

<!-- ✅ AUTH/BASE globals (трябват преди fetch-овете) -->
<script>
  window.KM_AUTH = window.KM_AUTH || { loggedIn: <?= is_logged_in() ? "true" : "false" ?> };
  window.KM_BASE = "<?= e($base) ?>";
</script>

<nav class="navbar navbar-expand-xxl km-navbar" data-bs-theme="light">
  <div class="container-fluid">

    <a class="navbar-brand km-brand" href="<?= e($base) ?>index.php">
      <span class="km-brand-icon">📚</span> КнигоМания
    </a>

    <!-- ✅ TOP RIGHT: мобилен режим (иконките стоят до бургер-а) -->
    <div class="km-top-right d-flex align-items-center gap-2 ms-auto">

      <?php if ($cu): ?>
        <div class="km-actions-mobile d-flex align-items-center gap-2">
          <!-- 👤 ПРОФИЛ -->
          <div class="dropdown">
            <button class="btn km-profile-btn dropdown-toggle d-flex align-items-center justify-content-center"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Профил"
                    style="padding:.35rem .55rem;">
              <?php if ($avatarUrl): ?>
                <img src="<?= e($avatarUrl) ?>" alt="Avatar"
                     style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
              <?php else: ?>
                <span style="width:34px;height:34px;border-radius:50%;
                             display:inline-flex;align-items:center;justify-content:center;
                             font-weight:700;font-size:.9rem;
                             background:rgba(0,0,0,.08);">
                  <?= e($ini) ?>
                </span>
              <?php endif; ?>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
              <li class="dropdown-item-text small text-muted">
                <?= e($cu["name"] ?? $cu["email"]) ?>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= e($base) ?>settings.php">⚙️ Настройки</a></li>
              <li>
                <a class="dropdown-item" href="<?= e($base) ?>messages_inbox.php">
                  ✉️ Съобщения<?= $unread_msgs ? " ($unread_msgs)" : "" ?>
                </a>
              </li>
              <li><a class="dropdown-item" href="<?= e($base) ?>logout.php">🚪 Изход</a></li>
            </ul>
          </div>

          <!-- ❤️ ЛЮБИМИ -->
          <a class="btn km-icon-btn d-inline-flex align-items-center justify-content-center position-relative"
             href="<?= e($base) ?>favorites.php"
             title="Любими"
             aria-label="Любими"
             style="width:42px;height:40px;padding:0;">
            <span style="display:inline-flex;width:22px;height:22px;line-height:0;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20.8 6.6c-1.2-2-3.8-2.6-5.7-1.3-.8.5-1.4 1.2-1.9 2-.5-.8-1.1-1.5-1.9-2C7.4 4 4.8 4.6 3.6 6.6c-1.4 2.4-.8 5.3 1.4 7.5C7.4 16.6 12 20 12 20s4.6-3.4 7-5.9c2.2-2.2 2.8-5.1 1.8-7.5z"/>
              </svg>
            </span>
            <span class="km-fav-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                  style="font-size:.65rem;">0</span>
          </a>

          <!-- 🛒 КОШНИЦА -->
          <a class="btn km-icon-btn d-inline-flex align-items-center justify-content-center position-relative"
             href="<?= e($base) ?>cart.php"
             title="Кошница"
             aria-label="Кошница"
             style="width:42px;height:40px;padding:0;">
            <span style="display:inline-flex;width:22px;height:22px;line-height:0;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
              </svg>
            </span>
            <span class="km-cart-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                  style="font-size:.65rem;">0</span>
          </a>
        </div>
      <?php endif; ?>

      <!-- 🍔 бургер -->
      <button class="navbar-toggler km-toggler" type="button" data-bs-toggle="collapse"
              data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
              aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav ms-xxl-3 me-auto mb-2 mb-xxl-0">

        <li class="nav-item"><a class="nav-link km-link" href="<?= e($base) ?>index.php">Начало</a></li>

        <!-- Категории -->
        <li class="nav-item dropdown">
          <a class="nav-link km-link dropdown-toggle" href="<?= e($base) ?>books.php" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">
            Категории
          </a>
          <ul class="dropdown-menu km-dropdown">
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php">📚 Всички нови книги</a></li>
            <li><hr class="dropdown-divider km-divider"></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=hudojestvena">📖 Художествена литература</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=nauchna">🔬 Научна</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=detski">🧸 Детски книги</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=fantastika">🧙 Фантастика</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=fentezi">🐉 Фентъзи</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=trilari">🕵️ Трилъри</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=krimi">🕶️ Криминални</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=romantika">💘 Романтика</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=istoriya">🏛️ История</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=biografii">👤 Биографии</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=psihologiya">🧠 Психология</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=samorazvitie">🌟 Саморазвитие</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=biznes">💼 Бизнес</a></li>
            <li><a class="dropdown-item km-dd-item" href="<?= e($base) ?>books.php?category=poeziya">🪶 Поезия</a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link km-link" href="<?= e($base) ?>usedbooks.php">Книги втора ръка</a></li>

        <!-- Учебници -->
        <li class="nav-item dropdown">
          <a class="nav-link km-link dropdown-toggle"
             href="<?= e($base) ?>textbooks.php"
             role="button"
             data-bs-toggle="dropdown"
             data-bs-auto-close="outside"
             aria-expanded="false">
            Учебници
          </a>

          <ul class="dropdown-menu km-dropdown" style="min-width: 260px;">
            <li class="dropdown-header fw-bold text-primary">Учебници</li>
            <li><hr class="dropdown-divider km-divider"></li>

            <li>
              <a class="dropdown-item km-dd-item d-flex align-items-start gap-2"
                 href="<?= e($base) ?>textbooks.php">
                <span>🆕</span>
                <div>
                  <div class="fw-semibold">Нови учебници</div>
                  <small class="text-muted">Одобрени учебници</small>
                </div>
              </a>
            </li>

            <li>
              <a class="dropdown-item km-dd-item d-flex align-items-start gap-2"
                 href="<?= e($base) ?>textbooks_secondhand.php">
                <span>🔄</span>
                <div>
                  <div class="fw-semibold">Учебници втора ръка</div>
                  <small class="text-muted">Ползвани учебници от потребители</small>
                </div>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link km-link" href="<?= e($base) ?>blog.php">Блог</a></li>
        <li class="nav-item"><a class="nav-link km-link" href="<?= e($base) ?>about.php">За нас</a></li>
        <li class="nav-item"><a class="nav-link km-link" href="<?= e($base) ?>contacts.php">Контакти</a></li>

        <?php if ($cu): ?>
          <li class="nav-item">
            <a class="nav-link km-link" href="<?= e($base) ?>messages_inbox.php">
              Съобщения
              <?php if ($unread_msgs > 0): ?>
                <span class="badge bg-danger ms-1"><?= (int)$unread_msgs ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endif; ?>

      </ul>

      <!-- ✅ ТЪРСАЧКА + desktop иконки до нея -->
      <div class="d-flex flex-column flex-xxl-row gap-2 align-items-stretch align-items-xxl-center ms-xxl-3 mt-3 mt-xxl-0 km-search-wrap">

        <div class="d-flex km-search flex-grow-1">
          <div class="input-group">
            <input class="form-control km-search-input" type="search"
                   placeholder="Търси книга или учебник..." aria-label="Search" id="searchInput">
            <button class="btn km-search-btn" type="button" id="searchButton">Търси</button>
          </div>
        </div>

        <?php if ($cu): ?>
          <!-- ✅ DESKTOP actions: показват се само на XXL+ -->
          <div class="km-actions-desktop d-flex align-items-center gap-2">

            <!-- 👤 ПРОФИЛ -->
            <div class="dropdown">
              <button class="btn km-profile-btn dropdown-toggle d-flex align-items-center justify-content-center"
                      type="button"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                      aria-label="Профил"
                      style="padding:.35rem .55rem;">
                <?php if ($avatarUrl): ?>
                  <img src="<?= e($avatarUrl) ?>" alt="Avatar"
                       style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                  <span style="width:34px;height:34px;border-radius:50%;
                               display:inline-flex;align-items:center;justify-content:center;
                               font-weight:700;font-size:.9rem;
                               background:rgba(0,0,0,.08);">
                    <?= e($ini) ?>
                  </span>
                <?php endif; ?>
              </button>

              <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-item-text small text-muted">
                  <?= e($cu["name"] ?? $cu["email"]) ?>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e($base) ?>settings.php">⚙️ Настройки</a></li>
                <li>
                  <a class="dropdown-item" href="<?= e($base) ?>messages_inbox.php">
                    ✉️ Съобщения<?= $unread_msgs ? " ($unread_msgs)" : "" ?>
                  </a>
                </li>
                <li><a class="dropdown-item" href="<?= e($base) ?>logout.php">🚪 Изход</a></li>
              </ul>
            </div>

            <!-- ❤️ ЛЮБИМИ -->
            <a class="btn km-icon-btn d-inline-flex align-items-center justify-content-center position-relative"
               href="<?= e($base) ?>favorites.php"
               title="Любими"
               aria-label="Любими"
               style="width:42px;height:40px;padding:0;">
              <span style="display:inline-flex;width:22px;height:22px;line-height:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M20.8 6.6c-1.2-2-3.8-2.6-5.7-1.3-.8.5-1.4 1.2-1.9 2-.5-.8-1.1-1.5-1.9-2C7.4 4 4.8 4.6 3.6 6.6c-1.4 2.4-.8 5.3 1.4 7.5C7.4 16.6 12 20 12 20s4.6-3.4 7-5.9c2.2-2.2 2.8-5.1 1.8-7.5z"/>
                </svg>
              </span>
              <span class="km-fav-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                    style="font-size:.65rem;">0</span>
            </a>

            <!-- 🛒 КОШНИЦА -->
            <a class="btn km-icon-btn d-inline-flex align-items-center justify-content-center position-relative"
               href="<?= e($base) ?>cart.php"
               title="Кошница"
               aria-label="Кошница"
               style="width:42px;height:40px;padding:0;">
              <span style="display:inline-flex;width:22px;height:22px;line-height:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="9" cy="21" r="1"></circle>
                  <circle cx="20" cy="21" r="1"></circle>
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
              </span>
              <span class="km-cart-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                    style="font-size:.65rem;">0</span>
            </a>

          </div>
        <?php endif; ?>

        <?php if (!$cu): ?>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="<?= e($base) ?>login.php">Вход</a>
            <a class="btn btn-warning btn-sm" href="<?= e($base) ?>register.php">Регистрация</a>
          </div>
        <?php endif; ?>

      </div>

    </div>
  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', async function() {
  const searchInput  = document.getElementById('searchInput');
  const searchButton = document.getElementById('searchButton');

  const BOOKS_LIST_URL = "<?= e($base) ?>books.php?q=";
  const TB_LIST_URL    = "<?= e($base) ?>textbooks.php?q=";

  function norm(s){ return String(s ?? "").toLowerCase().trim(); }

  function scoreExactStartsContains(text, q){
    const t = norm(text);
    if (!t || !q) return 0;
    if (t === q) return 100;
    if (t.startsWith(q)) return 80;
    if (t.includes(q)) return 60;
    return 0;
  }

  function scoreBook(b, q){
    let s = scoreExactStartsContains(b.title, q);
    if (b.author) s = Math.max(s, scoreExactStartsContains(b.author, q) + 5);
    return s;
  }

  function scoreTextbook(tb, q){
    let s = scoreExactStartsContains(tb.title, q);
    if (tb.subject)   s = Math.max(s, scoreExactStartsContains(tb.subject, q) + 20);
    if (tb.gradeBand) s = Math.max(s, scoreExactStartsContains(tb.gradeBand, q) + 10);
    if (tb.publisher) s = Math.max(s, scoreExactStartsContains(tb.publisher, q) + 5);
    return s;
  }

  function bestMatch(list, q, scorer){
    let best = null, bestScore = 0;
    for (const item of list) {
      const sc = scorer(item, q);
      if (sc > bestScore) { bestScore = sc; best = item; }
    }
    return { best, bestScore };
  }

  function performSearch(){
    const raw = (searchInput?.value || "").trim();
    const q = norm(raw);
    if (!q) { searchInput?.focus(); return; }

    const BOOKS = Array.isArray(window.BOOKS) ? window.BOOKS : [];
    const TEXTBOOKS = Array.isArray(window.TEXTBOOKS) ? window.TEXTBOOKS : [];

    if ((BOOKS.length === 0) && (TEXTBOOKS.length === 0)) {
      window.location.href = BOOKS_LIST_URL + encodeURIComponent(raw);
      return;
    }

    const tbRes = bestMatch(TEXTBOOKS, q, scoreTextbook);
    const bRes  = bestMatch(BOOKS, q, scoreBook);

    if (tbRes.bestScore > bRes.bestScore && tbRes.bestScore >= 60) {
      window.location.href = TB_LIST_URL + encodeURIComponent(raw);
      return;
    }

    window.location.href = BOOKS_LIST_URL + encodeURIComponent(raw);
  }

  if (searchButton) searchButton.addEventListener('click', performSearch);
  if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); performSearch(); }
    });
  }

  /* =========================
     🚀 Lazy-load: data scripts ONLY if search exists
  ========================= */
  async function loadScript(src){
    return new Promise((resolve, reject)=>{
      const s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  async function ensureDataLoaded(){
    if (!searchInput) return;

    // already loaded?
    if (Array.isArray(window.BOOKS) && window.BOOKS.length) return;

    try{
      await loadScript("<?= e($base) ?>bookslist.js");
    }catch(e){}

    try{
      await loadScript("<?= e($base) ?>textbooks-data.js");
    }catch(e){}

    // unify globals
    try{ if (typeof BOOKS !== "undefined" && !window.BOOKS) window.BOOKS = BOOKS; }catch(e){}
    try{ if (typeof TEXTBOOKS !== "undefined" && !window.TEXTBOOKS) window.TEXTBOOKS = TEXTBOOKS; }catch(e){}
  }

  // load datasets after page is interactive (idle)
  if (searchInput) {
    if ("requestIdleCallback" in window) {
      requestIdleCallback(()=>{ ensureDataLoaded(); }, { timeout: 1500 });
    } else {
      setTimeout(()=>{ ensureDataLoaded(); }, 400);
    }
  }

  // ✅ BADGE за Любими (само ако е логнат)
  if (window.KM_AUTH && window.KM_AUTH.loggedIn) {
    (async function(){
      try{
        const r = await fetch("<?= e($base) ?>favorites_api.php?action=count", { cache: "no-store" });
        if (!r.ok) return;
        const j = await r.json();
        const n = Number(j.count || 0);

        document.querySelectorAll(".km-fav-badge").forEach(badge=>{
          badge.textContent = String(n);
          if (n > 0) badge.classList.remove("d-none");
          else badge.classList.add("d-none");
        });
      }catch(e){}
    })();
  }

  // ✅ BADGE за КОШНИЦА (само ако е логнат)
  window.KMCart = window.KMCart || {};
  window.KMCart.refreshBadge = async function(){
    if (!(window.KM_AUTH && window.KM_AUTH.loggedIn)) return;

    try{
      const r = await fetch("<?= e($base) ?>cart_api.php?action=count", { cache: "no-store" });
      if (!r.ok) return;
      const j = await r.json();
      const n = Number(j.count || 0);

      document.querySelectorAll(".km-cart-badge").forEach(badge=>{
        badge.textContent = String(n);
        if (n > 0) badge.classList.remove("d-none");
        else badge.classList.add("d-none");
      });
    }catch(e){}
  };

  window.KMCart.refreshBadge();
});
</script>
