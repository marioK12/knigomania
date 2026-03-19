<?php
require_once "auth.php";
require_once "db.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Любими";

// current user
$me = current_user();
$meId = (int)($me["id"] ?? 0);

// ✅ JOIN за usedbook / usedtextbook + giftbook + първа снимка + публикувалия потребител
$st = $pdo->prepare("
  SELECT
    f.item_type,
    f.item_id,
    f.created_at AS fav_created_at,

    /* used books / used textbooks */
    ub.title       AS ub_title,
    ub.author      AS ub_author,
    ub.price       AS ub_price,
    u.name         AS ub_user_name,
    u.email        AS ub_user_email,
    (
      SELECT ubi.file_name
      FROM used_book_images ubi
      WHERE ubi.used_book_id = ub.id
      ORDER BY ubi.sort_order ASC, ubi.id ASC
      LIMIT 1
    ) AS ub_image,

    /* gift books */
    gb.title       AS gb_title,
    gb.city        AS gb_city,
    gu.name        AS gb_user_name,
    gu.email       AS gb_user_email,
    (
      SELECT gbi.file_name
      FROM gift_book_images gbi
      WHERE gbi.gift_book_id = gb.id
      ORDER BY gbi.sort_order ASC, gbi.id ASC
      LIMIT 1
    ) AS gb_image

  FROM favorites f

  LEFT JOIN used_books ub
    ON ub.id = f.item_id
   AND f.item_type IN ('usedbook','usedtextbook')

  LEFT JOIN users u
    ON u.id = ub.user_id

  LEFT JOIN gift_books gb
    ON gb.id = f.item_id
   AND f.item_type = 'giftbook'

  LEFT JOIN users gu
    ON gu.id = gb.user_id

  WHERE f.user_id = :uid
  ORDER BY f.created_at DESC
  LIMIT 500
");
$st->execute([":uid" => $meId]);
$favRows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">

<link rel="stylesheet" href="<?= e($base) ?>favourites.css?v=10">

<?php require_once "nav.php"; ?>

<main class="fav-wrap">
  <div class="container fav-container">

    <section class="fav-hero">
      <div class="fav-hero-left">
        <h1 class="fav-title">Любими</h1>
        <p class="fav-sub">Тук са всички неща, които си запазил като любими.</p>
      </div>

      <div class="fav-hero-right">
        <div class="fav-pill">❤️ <span>Общо:</span> <b id="favTotal">0</b></div>
        <a class="fav-pill fav-pill-ghost" href="<?= e($base) ?>index.php">← Назад</a>
      </div>
    </section>

    <div id="favEmpty" class="fav-empty d-none">
      <div class="fav-empty-ico">🤍</div>
      <div class="fav-empty-title">Нямаш добавени любими.</div>
      <div class="fav-empty-sub">Отиди до книги/учебници и натисни сърцето.</div>
      <a class="fav-btn fav-btn-primary" href="<?= e($base) ?>books.php">Разгледай книги</a>
    </div>

    <div id="favGrid" class="fav-grid"></div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
  window.FAV_ROWS = <?= json_encode($favRows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  window.BASE_URL = <?= json_encode($base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>

<script src="<?= e($base) ?>favorites.js"></script>

<script>
/* =========================
   Utilities
========================= */
const BASE = window.BASE_URL || "/";

function esc(s){
  return String(s ?? "")
    .replaceAll("&","&amp;").replaceAll("<","&lt;")
    .replaceAll(">","&gt;").replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}
function money(x){
  const n = Number(x);
  return Number.isFinite(n) ? n.toFixed(2) : "";
}
function svgPlaceholder(label){
  const txt = esc(label || "Няма снимка");
  const svg = `
  <svg xmlns="http://www.w3.org/2000/svg" width="800" height="520" viewBox="0 0 800 520">
    <defs>
      <linearGradient id="g" x1="0" x2="1">
        <stop offset="0" stop-color="#fff7ed"/>
        <stop offset="1" stop-color="#ffe8cc"/>
      </linearGradient>
    </defs>
    <rect width="800" height="520" rx="26" fill="url(#g)"/>
    <rect x="36" y="36" width="728" height="448" rx="22" fill="#ffffff" opacity="0.55"/>
    <g opacity="0.65">
      <path d="M280 300c40-70 120-70 160 0" fill="none" stroke="#fb923c" stroke-width="14" stroke-linecap="round"/>
      <circle cx="340" cy="235" r="18" fill="#fb923c"/>
      <circle cx="460" cy="235" r="18" fill="#fb923c"/>
    </g>
    <text x="50%" y="76%" text-anchor="middle" font-family="system-ui,Segoe UI,Arial" font-size="26" fill="#9a3412" opacity="0.85">${txt}</text>
  </svg>`;
  return "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(svg);
}

const TYPE_BADGE = {
  book: "📘 Книга",
  textbook: "📚 Учебник",
  usedbook: "♻️ Книга (2-ра ръка)",
  usedtextbook: "♻️ Учебник (2-ра ръка)",
  giftbook: "🎁 Подари книга",
};

const ROUTES = {
  book:         (id) => BASE + "product.php?id=" + encodeURIComponent(id),
  textbook:     (id) => BASE + "textbook_view.php?id=" + encodeURIComponent(id),
  usedbook:     (id) => BASE + "usedbook.php?id=" + encodeURIComponent(id),
  usedtextbook: (id) => BASE + "textbook_secondhand_view.php?id=" + encodeURIComponent(id),
  giftbook:     (id) => BASE + "giftbook_view.php?id=" + encodeURIComponent(id),
};

/* ✅ image resolver */
function resolveImg(src, type){
  const s = String(src || "").trim();
  const t = String(type || "");
  const ph = svgPlaceholder(TYPE_BADGE[t] || t || "Няма снимка");

  if (!s) return ph;

  if (/^https?:\/\//i.test(s) || s.startsWith("data:")) return s;
  if (s.startsWith("/")) return s;

  if (s.includes("/") || s.includes("\\")) return BASE + s.replace(/^(\.\/)+/, "");

  if (t === "usedbook" || t === "usedtextbook") return BASE + "usedIMG/" + s;
  if (t === "giftbook") return BASE + "giftIMG/" + s;

  return BASE + s;
}

/* =========================
   Load scripts safely
========================= */
function loadScriptOnce(src, testFn){
  return new Promise((resolve) => {
    try{
      if (testFn && testFn()) return resolve(true);
      const s = document.createElement("script");
      s.src = src;
      s.onload = () => resolve(true);
      s.onerror = () => resolve(false);
      document.head.appendChild(s);
    }catch(e){ resolve(false); }
  });
}

async function ensureDataFiles(){
  await loadScriptOnce(BASE + "bookslist.js", () => Array.isArray(window.BOOKS));
  await loadScriptOnce(BASE + "textbooks-data.js", () => Array.isArray(window.TEXTBOOKS));
}

function findBook(id){
  if (!Array.isArray(window.BOOKS)) return null;
  return window.BOOKS.find(b => Number(b?.id) === Number(id)) || null;
}
function findTextbook(id){
  if (!Array.isArray(window.TEXTBOOKS)) return null;
  return window.TEXTBOOKS.find(t => Number(t?.id) === Number(id)) || null;
}

/* =========================
   Render card
========================= */
function renderCard(row){
  const type = String(row.item_type || "");
  const id   = Number(row.item_id || 0);

  const badge = TYPE_BADGE[type] || type;
  const href  = ROUTES[type] ? ROUTES[type](id) : "#";

  let title = `${badge} #${id}`;
  let meta  = "";
  let price = "";
  let img   = svgPlaceholder(badge);

  if (type === "usedbook" || type === "usedtextbook"){
    if (row.ub_title) title = row.ub_title;

    const uname = String(row.ub_user_name || "").trim();
    const uemail = String(row.ub_user_email || "").trim();
    const who = uname || uemail || "Потребител";
    meta = "Публикувано от: " + who;

    if (row.ub_price != null) price = money(row.ub_price);
    img = resolveImg(row.ub_image || "", type);
  }
  else if (type === "giftbook"){
  if (row.gb_title) title = row.gb_title;

  const uname = String(row.gb_user_name || "").trim();
  const uemail = String(row.gb_user_email || "").trim();
  const who = uname || uemail || "Потребител";

  // ✅ само дарител, без град
  meta = "Дарител: " + who;

  price = "";
  img = resolveImg(row.gb_image || "", type);
}

  else if (type === "book"){
    const b = findBook(id);
    if (b){
      title = b.title || title;
      meta  = b.author ? ("Автор: " + b.author) : "";
      price = money(b.price);
      img   = resolveImg(b.img || "", type);
    }
  }
  else if (type === "textbook"){
    const tb = findTextbook(id);
    if (tb){
      title = tb.title || title;
      meta  = tb.publisher ? ("Издателство: " + tb.publisher) : "";
      price = money(tb.price);
      img   = resolveImg(tb.img || "", type);
    }
  }

  const priceHtml = price ? (esc(price) + " €") : "&nbsp;";

  return `
    <article class="fav-card" data-type="${esc(type)}" data-id="${id}">
      <div class="fav-img">
        <img src="${esc(img)}" alt="${esc(title)}"
             onerror="this.onerror=null;this.src='${esc(svgPlaceholder(badge))}'">
        <span class="fav-badge">${esc(badge)}</span>
      </div>

      <div class="fav-body">
        <h3 class="fav-card-title">${esc(title)}</h3>
        ${meta ? `<div class="fav-meta">${esc(meta)}</div>` : ``}

        <div class="fav-foot">
          <div class="fav-price">${priceHtml}</div>

          <div class="fav-actions">
            ${href !== "#" ? `<a class="fav-btn fav-btn-ghost" href="${esc(href)}">👁 Виж</a>` : ``}
            <button class="fav-btn fav-btn-danger js-remove"
                    type="button"
                    data-type="${esc(type)}"
                    data-id="${id}">
              🗑 Премахни
            </button>
          </div>
        </div>
      </div>
    </article>
  `;
}

/* =========================
   Render
========================= */
async function renderFavs(){
  const grid  = document.getElementById("favGrid");
  const empty = document.getElementById("favEmpty");
  const total = document.getElementById("favTotal");

  const rows = Array.isArray(window.FAV_ROWS) ? window.FAV_ROWS : [];

  total.textContent = String(rows.length);

  if (!rows.length){
    empty.classList.remove("d-none");
    grid.innerHTML = "";
    return;
  }
  empty.classList.add("d-none");

  grid.innerHTML = rows.map(renderCard).join("");
}

(async function(){
  await ensureDataFiles();
  await renderFavs();
})();

document.addEventListener("click", async (e) => {
  const b = e.target.closest(".js-remove");
  if (!b) return;

  e.preventDefault();
  e.stopPropagation();

  const type = String(b.dataset.type || "");
  const id   = Number(b.dataset.id || 0);
  if (!type || !id) return;

  b.disabled = true;
  b.textContent = "Махам...";

  try { await window.KMFav.remove(type, id); } catch(e){}

  window.FAV_ROWS = (window.FAV_ROWS || []).filter(r =>
    !(String(r.item_type) === type && Number(r.item_id) === id)
  );

  await renderFavs();
});

window.addEventListener("pageshow", () => {
  renderFavs();
});
</script>

</body>
</html>
