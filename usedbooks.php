<!doctype html>
<html lang="bg" class="ub-anim">
<?php 
$page_title = "Книги втора ръка";
require_once("header.php"); 
?>
<body class="km-layout ub-page">


<?php require_once "auth.php"; ?>
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="usedbooks.css">

<!-- (по желание) може да махнеш този <style>, ако вече имаш .tb-like в usedbooks.css -->
<style>
.ub-card-media{ position:relative; }
.tb-like{
  position:absolute;
  top:12px;
  right:12px;
  width:38px;
  height:38px;
  border-radius:999px;
  background:rgba(255,255,255,.92);
  box-shadow:0 8px 20px rgba(0,0,0,.15);
  display:grid;
  place-items:center;
  cursor:pointer;
  z-index:10;

  border:0;
  padding:0;
  outline:0;

  transition:transform .2s ease, box-shadow .2s ease;
}
.tb-like:hover{
  transform:scale(1.08);
  box-shadow:0 12px 26px rgba(0,0,0,.18);
}
.tb-like-ico{
  width:20px;
  height:20px;
  fill:transparent;
  stroke:#cbd5e1;
  stroke-width:2;
  transition:fill .2s ease, stroke .2s ease, transform .2s ease;
}
.tb-like.is-active .tb-like-ico{
  fill:#ef4444;
  stroke:#ef4444;
  transform:scale(1.05);
}
</style>

<!-- ================= HERO ================= -->
<section class="ub-hero">
  <div class="container ub-hero-inner">

    <div class="ub-hero-icons" aria-hidden="true">
      <span>♻️</span>
      <span>🌿</span>
      <span>🤍</span>
    </div>

    <h1 class="ub-hero-title">Книги втора ръка</h1>

    <p class="ub-hero-subtitle">
      Качествени употребявани книги на отлични цени.
      Устойчиво четене за всеки! 🌱
    </p>

    <div class="ub-hero-cta mt-3">
      <?php if (is_logged_in()): ?>
        <a class="btn btn-light ub-pill" href="usedbook_add.php">➕ Добави обява</a>
      <?php else: ?>
        <a class="btn ub-pill ub-login-like-add" href="login.php?next=usedbook_add.php">
          <span class="ub-plus">➕</span> Влез, за да добавиш
        </a>
      <?php endif; ?>
    </div>
  </div>

  <svg class="ub-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,64 C240,96 480,32 720,48 C960,64 1200,128 1440,80 L1440,120 L0,120 Z"></path>
  </svg>
</section>

<!-- ================= MAIN ================= -->
<main class="ub-main">

  <!-- FILTER -->
  <div class="ub-filter-wrap">
    <div class="ub-filter-card">

      <div class="ub-filter-head">
        <div class="ub-filter-ico">🔍</div>
        <div class="ub-filter-title">Филтри</div>

        <div class="ub-filter-cta" id="ubCtaSlot"></div>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="ub-label">Търсене</label>
          <input id="q" class="form-control ub-input" placeholder="Заглавие или автор...">
        </div>

        <div class="col-md-3">
          <label class="ub-label">Категория</label>
          <select id="category" class="form-select ub-input">
            <option value="all">Всички категории</option>
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

        <div class="col-md-4">
          <label class="ub-label">Подреди по</label>
          <select id="sort" class="form-select ub-input">
            <option value="newest">Най-нови</option>
            <option value="price_asc">Цена (възх.)</option>
            <option value="price_desc">Цена (низх.)</option>
          </select>
        </div>
      </div>

    </div>
  </div>

  <!-- META -->
  <div class="container">
    <div id="meta" class="ub-meta"></div>
    <div class="row g-4" id="grid"></div>
  </div>

</main>

<?php require_once "footer.php"; ?>

<script>
const API = "usedbooks_api.php";
const grid = document.getElementById("grid");
const meta = document.getElementById("meta");

const CAT_LABEL = {
  hudojestvena:"Художествена", nauchna:"Научна", detski:"Детски",
  fantastika:"Фантастика", fentezi:"Фентъзи", trilari:"Трилъри",
  krimi:"Криминални", romantika:"Романтика", istoriya:"История",
  biografii:"Биографии", psihologiya:"Психология", samorazvitie:"Саморазвитие",
  biznes:"Бизнес", poeziya:"Поезия"
};

function esc(s){
  return String(s ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

function emptyHtml(){
  return `
    <div class="col-12">
      <div class="ub-empty">
        <div class="ub-empty-title">Няма намерени книги ☹️</div>
        <div class="ub-empty-sub">Опитай с друга дума или изчисти филтрите.</div>
        <button class="btn btn-warning ub-empty-btn" type="button" id="btnReset">
          Покажи всички книги
        </button>
      </div>
    </div>
  `;
}
function animateEmpty(){
  const empty = grid.querySelector(".ub-empty");
  if (!empty) return;
  empty.style.animation = "none";
  empty.offsetHeight;
  empty.style.animation = "";
}

function skelCard(){
  return `
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="ub-card ub-skelcard">
        <div class="ub-skel ub-skel-img"></div>
        <div class="ub-card-body">
          <div class="ub-skel ub-skel-line sm"></div>
          <div class="ub-skel ub-skel-line"></div>
          <div class="ub-skel ub-skel-line sm"></div>
          <div class="ub-card-row">
            <div class="ub-skel ub-skel-pill"></div>
            <div class="ub-skel ub-skel-pill"></div>
          </div>
        </div>
        <div class="ub-card-foot">
          <div class="ub-skel ub-skel-line sm"></div>
          <div class="ub-skel ub-skel-btn"></div>
        </div>
      </div>
    </div>
  `;
}
function renderSkeleton(n=6){
  meta.textContent = "Зареждане...";
  grid.innerHTML = Array.from({length:n}, skelCard).join("");
}

/* ✅ CARD renderer: WHOLE CARD CLICKABLE */
function cardHtml(it){
  const img = (it.images && it.images.length) ? it.images[0] : "";
  const imgHtml = img
    ? `<img src="${esc(img)}" class="ub-card-img" loading="lazy" alt="">`
    : `<div class="ub-card-img ub-noimg">🖼️</div>`;

  const condMap = { new:"Нова", like_new:"Като нова", good:"Добра", fair:"Средна", poor:"Лоша" };
  const condTxt  = condMap[it.condition] || it.condition || "";
  const catLabel = CAT_LABEL[it.category] || it.category || "";
  const city = (it.city || "").toString().trim();

  const id = Number(it.id);
  const href = `usedbook.php?id=${id}`;

  return `
  <div class="col-12 col-sm-6 col-lg-4">
    <div class="ub-card" style="--d:${it.__d}ms">

      <!-- ✅ CLICKABLE ONLY IMAGE AREA -->
      <a class="ub-media-link" href="${href}">
        <div class="ub-card-media">
          ${imgHtml}

          <!-- ❤️ heart (does NOT navigate) -->
          <button class="tb-like km-fav"
                  type="button"
                  aria-label="Харесай"
                  title="Харесай"
                  data-id="${id}"
                  data-type="usedbook">
            <svg class="tb-like-ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 21s-6.7-4.4-9.4-7.1C.9 12.1.5 10.9.5 9.5.5 6.5 3 4 6 4c1.7 0 3.4.8 4.5 2.1C11.6 4.8 13.3 4 15 4c3 0 5.5 2.5 5.5 5.5 0 1.4-.4 2.6-2.1 4.3C18.7 16.6 12 21 12 21z"/>
            </svg>
          </button>

          <!-- ✅ category chip stays on image -->
          <span class="ub-chip">${esc(catLabel)}</span>
        </div>
      </a>

      <!-- ✅ NOT CLICKABLE TEXT AREA -->
      <div class="ub-card-body">
        <div class="ub-card-top">
          <div class="ub-title">${esc(it.title || "")}</div>
          <div class="ub-author">${esc(it.author || "")}</div>
        </div>

        <div class="ub-meta-row">
          <span class="ub-cond">${esc(condTxt)}</span>
          <span class="ub-seller">от ${esc(it.seller_name || it.seller_email || "Потребител")}</span>
        </div>

        ${city ? `<div class="ub-meta-row ub-city">📍 ${esc(city)}</div>` : ``}

        <!-- ✅ CLICKABLE ONLY BUTTON -->
        <div class="ub-card-bottom">
          <div class="ub-price">${Number(it.price||0).toFixed(2)} €</div>
          <a class="ub-btn ub-open" href="${href}">Виж</a>
        </div>
      </div>

    </div>
  </div>`;
}


/* -------------------
   Apply liked state from DB
------------------- */
async function applyFavState(){
  if (!window.KMFav) return;

  const btns = [...grid.querySelectorAll(".km-fav.tb-like[data-type][data-id]")];
  if (!btns.length) return;

  await Promise.all(btns.map(async (btn) => {
    const type = btn.dataset.type;
    const id = Number(btn.dataset.id);
    const liked = await KMFav.check(type, id);
    btn.classList.toggle("is-active", !!liked);
  }));
}

/* -------------------
   Load from API
------------------- */
async function load(){
  const q = document.getElementById("q").value.trim();
  const category = document.getElementById("category").value;
  const sort = document.getElementById("sort").value;

  const params = new URLSearchParams();
  if (q) params.set("q", q);
  if (category && category !== "all") params.set("category", category);
  if (sort) params.set("sort", sort);

  renderSkeleton(6);

  try{
    const res = await fetch(API + "?" + params.toString(), { headers: { "Accept":"application/json" } });

    const txt = await res.text();
    let data = {};
    try { data = JSON.parse(txt); } catch { data = {}; }

    if (!res.ok) throw new Error(data?.error || txt || "Грешка при зареждане.");

    const items = data.items || [];
    meta.textContent = `Намерени ${items.length} книги`;

    if (!items.length){
      grid.innerHTML = emptyHtml();
      animateEmpty();

      const b = document.getElementById("btnReset");
      if (b){
        b.onclick = () => {
          document.getElementById("q").value = "";
          document.getElementById("category").value = "all";
          document.getElementById("sort").value = "newest";
          load();
        };
      }
      return;
    }

    items.forEach((it, i) => it.__d = i * 70);
    grid.innerHTML = items.map(cardHtml).join("");

    await applyFavState();

  } catch(err){
    meta.textContent = "Грешка при зареждане.";
    grid.innerHTML = `
      <div class="col-12">
        <div class="ub-empty">
          <div class="ub-empty-title">❌ Възникна грешка</div>
          <div class="ub-empty-sub">${esc(err.message || "Опитай пак.")}</div>
          <button class="btn btn-warning ub-empty-btn" type="button" onclick="location.reload()">Презареди</button>
        </div>
      </div>
    `;
    animateEmpty();
  }
}

/* -------------------
   Events
------------------- */
document.getElementById("q").addEventListener("input", () => {
  clearTimeout(window.__t);
  window.__t = setTimeout(load, 300);
});
document.getElementById("category").addEventListener("change", load);
document.getElementById("sort").addEventListener("change", load);

/* ✅ Heart click: stop link navigation */
grid.addEventListener("click", async (e) => {
  const btn = e.target.closest?.(".km-fav.tb-like[data-type][data-id]");
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  if (!window.KMFav) return;

  const type = btn.dataset.type;
  const id   = Number(btn.dataset.id);

  const liked = await KMFav.toggle(type, id);
  if (liked === null) return;

  btn.classList.toggle("is-active", !!liked);

  btn.style.transform = "scale(1.15)";
  setTimeout(() => btn.style.transform = "", 120);
});


document.addEventListener("DOMContentLoaded", () => {
  document.documentElement.classList.add("ub-ready");

  const heroBtn = document.querySelector(".ub-hero-cta .btn");
  const slot = document.getElementById("ubCtaSlot");
  if (heroBtn && slot) slot.appendChild(heroBtn);

  load();
});
</script>

</body>
</html>
