// =============================
// BOOKS.js (filters + render)
// ✅ Favorites via DB (favorites.js + footer handler) — NO localStorage for likes
// ✅ Filters persist via localStorage (only filters)
// ✅ FIX: books hearts use ONLY .liked (avoid stuck color after product/back)
// =============================

// Категории (за показване)
const catName = {
  hudojestvena: "Художествена",
  nauchna: "Научна",
  detski: "Детски",
  fantastika: "Фантастика",
  fentezi: "Фентъзи",
  trilari: "Трилъри",
  krimi: "Криминални",
  romantika: "Романтика",
  istoriya: "История",
  biografii: "Биографии",
  psihologiya: "Психология",
  samorazvitie: "Саморазвитие",
  biznes: "Бизнес",
  poeziya: "Поезия"
};

// -----------------------------
// DOM
// -----------------------------
const grid = document.getElementById("grid");
const countEl = document.getElementById("count");
const qEl = document.getElementById("q");
const catEl = document.getElementById("cat");
const sortEl = document.getElementById("sort");
const emptyState = document.getElementById("emptyState");
const clearFiltersBtn = document.getElementById("clearFiltersBtn");

function mustExist(el, id) {
  if (!el) console.warn(`[books.js] Missing element #${id} in HTML`);
  return !!el;
}
mustExist(grid, "grid");
mustExist(countEl, "count");
mustExist(qEl, "q");
mustExist(catEl, "cat");
mustExist(sortEl, "sort");
mustExist(emptyState, "emptyState");
mustExist(clearFiltersBtn, "clearFiltersBtn");

// -----------------------------
// Helpers
// -----------------------------
function stars(n) {
  n = Math.max(0, Math.min(5, Number(n) || 0));
  return "★".repeat(n) + "☆".repeat(5 - n);
}

function esc(s){
  return String(s ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

function getParam(name) {
  return new URLSearchParams(location.search).get(name) || "";
}

function syncUrl() {
  if (!qEl || !catEl) return;

  const url = new URL(location.href);
  const q = qEl.value.trim();
  const cat = catEl.value;

  if (q) url.searchParams.set("q", q);
  else url.searchParams.delete("q");

  if (cat) url.searchParams.set("category", cat);
  else url.searchParams.delete("category");

  history.replaceState({}, "", url);
}

// -----------------------------
// Filters persist (only filters)
// -----------------------------
const FILTERS_KEY = "books_filters_v1";

function saveFilters(){
  try{
    localStorage.setItem(FILTERS_KEY, JSON.stringify({
      q: qEl?.value || "",
      cat: catEl?.value || "",
      sort: sortEl?.value || "new"
    }));
  }catch(e){}
}

function loadFilters(){
  // 1) URL has priority
  const q = getParam("q");
  const cat = getParam("category");

  if (qEl) qEl.value = q;
  if (catEl) catEl.value = cat;

  // 2) If no URL filters, fallback to localStorage
  if (!q && !cat){
    try{
      const raw = JSON.parse(localStorage.getItem(FILTERS_KEY) || "null");
      if (raw && typeof raw === "object"){
        if (qEl) qEl.value = raw.q || "";
        if (catEl) catEl.value = raw.cat || "";
        if (sortEl) sortEl.value = raw.sort || "new";
      }
    }catch(e){}
  }
}

loadFilters();

// -----------------------------
// Favorites via DB (KMFav)
// -----------------------------
let favBookSet = new Set(); // ids liked in DB

async function syncFavsFromDB(){
  // ако няма favorites.js -> нищо
  if (!window.KMFav || typeof window.KMFav.list !== "function") return;

  try{
    const items = await window.KMFav.list(); // [{item_type,item_id,...}]

    favBookSet = new Set(
      (items || [])
        .filter(x => String(x.item_type) === "book")
        .map(x => Number(x.item_id))
        .filter(n => Number.isFinite(n) && n > 0)
    );

    // ✅ FIX: books.php трябва да работи само с .liked
    document.querySelectorAll('.km-fav[data-type="book"][data-id]').forEach(btn => {
      const id = Number(btn.dataset.id || 0);
      const on = favBookSet.has(id);

      btn.classList.toggle("liked", on);

      // чистим другите, за да не "залепва" червено
      btn.classList.remove("active");
      btn.classList.remove("is-active");
    });

  }catch(e){
    // silent
  }
}

// -----------------------------
// Filters + sort
// -----------------------------
function applyFilters() {
  const q = (qEl?.value || "").trim().toLowerCase();
  const cat = catEl?.value || "";
  let list = Array.isArray(window.BOOKS) ? [...window.BOOKS] : [];

  if (cat) list = list.filter(b => (b.category || "") === cat);

  if (q) {
    list = list.filter(b => {
      const t = (b.title || "").toLowerCase();
      const a = (b.author || "").toLowerCase();
      return t.includes(q) || a.includes(q);
    });
  }

  const sortValue = sortEl?.value || "new";
  switch (sortValue) {
    case "new":
      list.sort((a, b) => (b.year || 0) - (a.year || 0) || (b.id || 0) - (a.id || 0));
      break;

    case "priceAsc":
      list.sort((a, b) => (a.price || 0) - (b.price || 0));
      break;

    case "priceDesc":
      list.sort((a, b) => (b.price || 0) - (a.price || 0));
      break;

    case "titleAsc":
      list.sort((a, b) => (a.title || "").localeCompare((b.title || ""), "bg"));
      break;
  }

  return list;
}

// -----------------------------
// Render
// -----------------------------
async function render() {
  if (!grid || !countEl || !emptyState) return;

  const list = applyFilters();
  countEl.textContent = String(list.length);

  if (list.length === 0) {
    emptyState.classList.remove("d-none");
    grid.innerHTML = "";
    return;
  } else {
    emptyState.classList.add("d-none");
  }

  grid.innerHTML = list.map((b, i) => {
    const id = Number(b.id || 0);
    const delay = (i * 0.06).toFixed(2);
    const categoryLabel = catName[b.category] || b.category || "—";
    const priceText = Number.isFinite(Number(b.price)) ? Number(b.price).toFixed(2) : "0.00";
    const imgSrc = b.img || "";
    const title = b.title || "";
    const author = b.author || "";
    const desc = b.desc || "";

    return `
      <div class="col-12 col-sm-6 col-lg-3 reveal" style="animation-delay:${delay}s">
        <div class="km-book-card">
          <div class="km-badges">
            ${b.featured ? `<span class="km-badge km-badge-accent">⭐ Препоръчана</span>` : ``}
            <span class="km-badge km-badge-green">${esc(categoryLabel)}</span>
          </div>

          <!-- ❤️ favorite via DB (footer handler handles click) -->
          <button class="km-fav"
                  type="button"
                  aria-label="Харесай"
                  title="Харесай"
                  data-type="book"
                  data-id="${id}">
            <svg class="km-fav-ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 21s-6.716-4.438-9.428-7.151C.858 12.134.5 10.905.5 9.5.5 6.462 2.962 4 6 4c1.74 0 3.41.81 4.5 2.09C11.59 4.81 13.26 4 15 4c3.038 0 5.5 2.462 5.5 5.5 0 1.405-.358 2.634-2.072 4.349C18.716 16.562 12 21 12 21z"/>
            </svg>
          </button>

          <a class="km-book-img-link" href="product.php?id=${encodeURIComponent(id)}" aria-label="Виж ${esc(title)}">
            <img class="km-book-img" src="${esc(imgSrc)}" alt="${esc(title)}">
          </a>


          <div class="km-book-body">
            <span class="km-category-pill">${esc(categoryLabel)}</span>
            <h6 class="km-book-title">${esc(title)}</h6>
            <div class="km-book-author">${esc(author)}</div>
            <p class="km-book-desc">${esc(desc)}</p>

           



            <div class="km-book-footer">
              <div class="km-price">${priceText} €.</div>
              <a class="km-btn btn btn-sm" href="product.php?id=${encodeURIComponent(id)}">Виж</a>
            </div>
          </div>
        </div>
      </div>
    `;
  }).join("");

  // ✅ After render: sync hearts from DB
  await syncFavsFromDB();
}

// -----------------------------
// Events
// -----------------------------
if (qEl) qEl.addEventListener("input", () => { syncUrl(); saveFilters(); render(); });
if (catEl) catEl.addEventListener("change", () => { syncUrl(); saveFilters(); render(); });
if (sortEl) sortEl.addEventListener("change", () => { saveFilters(); render(); });

if (clearFiltersBtn) {
  clearFiltersBtn.addEventListener("click", () => {
    if (qEl) qEl.value = "";
    if (catEl) catEl.value = "";
    if (sortEl) sortEl.value = "new";

    const url = new URL(location.href);
    url.searchParams.delete("q");
    url.searchParams.delete("category");
    history.replaceState({}, "", url);

    saveFilters();
    render();
  });
}

// navbar active link
const path = (location.pathname.split("/").pop() || "index.php").split("?")[0];
document.querySelectorAll(".km-link").forEach(a => {
  const href = (a.getAttribute("href") || "").split("?")[0];
  if (href === path) a.classList.add("active");
});

// ✅ bfcache/back-forward: resync hearts
window.addEventListener("pageshow", () => {
  syncFavsFromDB();
});

// Go!
render();
