// textbooks.js (CARDS) — ❤️ DB favorites (KMFav) + filters
const TB = Array.isArray(window.TEXTBOOKS) ? window.TEXTBOOKS : [];

// DOM
const elQ       = document.getElementById("tbQ");
const elGrade   = document.getElementById("tbGrade");
const elSubject = document.getElementById("tbSubject");
const elMeta    = document.getElementById("tbMeta");
const elWrap    = document.getElementById("tbTableWrap");

const STORE_KEY = "tbFilters_v1";
const SS_KEY    = "TEXTBOOKS_DATA_v1";

function esc(s){
  return String(s ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}
function norm(s){ return String(s ?? "").toLowerCase().trim(); }

// -----------------------------
// persist filters
// -----------------------------
function saveFilters(){
  const data = {
    q: elQ?.value || "",
    grade: elGrade?.value || "all",
    subject: elSubject?.value || "all"
  };
  try { localStorage.setItem(STORE_KEY, JSON.stringify(data)); } catch {}
}

function loadFilters(){
  let data = {};
  try { data = JSON.parse(localStorage.getItem(STORE_KEY) || "{}"); } catch {}

  const out = {
    q: (typeof data.q === "string") ? data.q : "",
    grade: (typeof data.grade === "string") ? data.grade : "all",
    subject: (typeof data.subject === "string") ? data.subject : "all"
  };

  if (elQ) elQ.value = out.q;
  if (elGrade) elGrade.value = out.grade;

  return out;
}


// -----------------------------
// subjects
// -----------------------------
function subjectsForGrade(gradeBand){
  const items = (gradeBand && gradeBand !== "all")
    ? TB.filter(x => String(x?.gradeBand || "") === String(gradeBand))
    : TB;

  const set = new Set(items.map(x => x?.subject).filter(Boolean));
  return Array.from(set).sort((a,b) => String(a).localeCompare(String(b), "bg"));
}

function rebuildSubjectOptions(desiredValue = null){
  if (!elSubject || !elGrade) return;

  const gradeBand = elGrade.value;
  const subs = subjectsForGrade(gradeBand);
  const prev = desiredValue ?? elSubject.value;

  elSubject.innerHTML = [
    `<option value="all">Всички предмети</option>`,
    ...subs.map(s => `<option value="${esc(s)}">${esc(s)}</option>`)
  ].join("");

  if (prev && prev !== "all" && subs.includes(prev)) elSubject.value = prev;
  else elSubject.value = "all";
}

// -----------------------------
// filter
// -----------------------------
function filterItems(){
  const q = norm(elQ?.value || "");
  const gradeBand = elGrade?.value || "all";
  const subject   = elSubject?.value || "all";

  return TB.filter(x => {
    if (!x) return false;

    if (gradeBand !== "all" && String(x.gradeBand || "") !== String(gradeBand)) return false;
    if (subject !== "all" && String(x.subject || "") !== String(subject)) return false;

    if (q){
      const hay = norm(`${x.title || ""} ${x.subject || ""} ${x.publisher || ""} ${x.year || ""} ${x.price || ""} ${x.isbn || ""}`);
      if (!hay.includes(q)) return false;
    }
    return true;
  });
}

// -----------------------------
// cards
// -----------------------------
function cardHtml(x, i){
  const id = Number(x?.id || 0);

  const img = String(x?.image || x?.img || "").trim();
  const media = img
  ? `<a class="tb-img-link" href="textbook_view.php?id=${encodeURIComponent(id)}" aria-label="Виж учебника">
       <img class="tb-img" src="${esc(img)}" alt="${esc(x?.title || "Учебник")}" loading="lazy">
     </a>`
  : `<div class="tb-noimg">📘</div>`;


  const gradeLabel = String(x?.gradeBand || "").replace("-", " - ");
  const subjectLabel = String(x?.subject || "");

  const viewBtn = (id > 0)
    ? `<a class="tb-btn" href="textbook_view.php?id=${encodeURIComponent(id)}">Виж</a>`
    : `<span class="tb-btn" style="opacity:.55; pointer-events:none;">Виж</span>`;

  const heart = (id > 0)
    ? `<button class="tb-like km-fav"
                type="button"
                aria-label="Харесай"
                title="Харесай"
                data-id="${id}"
                data-type="textbook">
         <svg viewBox="0 0 24 24" aria-hidden="true" class="tb-like-ico">
           <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
         </svg>
       </button>`
    : "";

  return `
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="tb-card" style="--d:${i*60}ms">
        <div class="tb-media">
          ${media}
          ${heart}
          <span class="tb-tag">${esc(gradeLabel)} клас • ${esc(subjectLabel)}</span>
        </div>

        <div class="tb-body">
          <div class="tb-title">${esc(x?.title || "")}</div>
          <div class="tb-sub">${esc(x?.publisher || "Издателство")} • ${esc(x?.year || "")}</div>

          <div class="tb-row">
            <div class="tb-price">${Number(x?.price || 0).toFixed(2)} €</div>
            ${viewBtn}
          </div>
        </div>
      </div>
    </div>
  `;
}

// -----------------------------
// ❤️ Apply state from DB
// -----------------------------
let __favToken = 0;

async function applyFavStateFromDB(){
  if (!window.KMFav) return; // ако favorites.js не е зареден
  const token = ++__favToken;

  const btns = [...document.querySelectorAll(".tb-like.km-fav[data-type='textbook'][data-id]")];
  if (!btns.length) return;

  await Promise.all(btns.map(async (btn) => {
    if (token !== __favToken) return;
    const id = Number(btn.dataset.id || 0);
    if (!id) return;
    const liked = await KMFav.check("textbook", id);
    if (token !== __favToken) return;
    btn.classList.toggle("is-active", !!liked);
  }));
}

// -----------------------------
// render
// -----------------------------
function render(){
  if (!elWrap) return;

  const items = filterItems();
  if (elMeta) elMeta.textContent = `Намерени: ${items.length} учебника`;

  if (!items.length){
    elWrap.innerHTML = `
      <div class="tb-empty">
        <div class="tb-empty-title">Няма намерени учебници ☹️</div>
        <div class="tb-empty-sub">Опитай друга дума или смени филтрите.</div>
      </div>
    `;
    return;
  }

  elWrap.innerHTML = `
    <div class="row g-4 tb-grid">
      ${items.map(cardHtml).join("")}
    </div>
  `;

  // след render — взимаме статус от БД
  applyFavStateFromDB();
}

// -----------------------------
// URL q= apply
// -----------------------------
function applyQueryFromUrlAfterInit(){
  const p = new URLSearchParams(location.search);
  const qRaw = (p.get("q") || "").trim();
  if (!qRaw) return false;

  if (elGrade) elGrade.value = "all";
  rebuildSubjectOptions("all");

  const q = norm(qRaw);
  if (elQ) elQ.value = qRaw;

  if (elSubject) {
    const subs = [...elSubject.options].map(o => o.value).filter(v => v && v !== "all");

    let found = subs.find(s => norm(s) === q);
    if (!found) found = subs.find(s => norm(s).startsWith(q));
    if (!found) found = subs.find(s => norm(s).includes(q));

    if (found) elSubject.value = found;
    else elSubject.value = "all";
  }

  return true;
}

// -----------------------------
// events (filters)
// -----------------------------
let t = null;

if (elQ){
  elQ.addEventListener("input", () => {
    clearTimeout(t);
    t = setTimeout(() => { saveFilters(); render(); }, 200);
  });
}

if (elGrade){
  elGrade.addEventListener("change", () => {
    rebuildSubjectOptions("all");
    saveFilters();
    render();
  });
}

if (elSubject){
  elSubject.addEventListener("change", () => {
    saveFilters();
    render();
  });
}

// -----------------------------
// ❤️ click handler (DB toggle) — само тук!
// -----------------------------
/*document.addEventListener("click", async (e) => {
  const btn = e.target.closest(".tb-like.km-fav[data-type='textbook'][data-id]");
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  if (!window.KMFav) {
    // favorites.js не е зареден -> няма как да работи
    return;
  }

  const id = Number(btn.dataset.id || 0);
  if (!id) return;

  const liked = await KMFav.toggle("textbook", id);

  // ако е null => най-често NOT_LOGGED_IN
  if (liked === null){
    const next = encodeURIComponent(location.pathname + location.search);
    location.href = "login.php?next=" + next;
    return;
  }

  btn.classList.toggle("is-active", !!liked);

  btn.style.transform = "scale(1.15)";
  setTimeout(() => btn.style.transform = "", 120);
});*/

document.addEventListener("DOMContentLoaded", () => {
  try { sessionStorage.setItem(SS_KEY, JSON.stringify(TB)); } catch {}

  const saved = loadFilters();
  rebuildSubjectOptions(saved.subject || "all");

  const applied = applyQueryFromUrlAfterInit();
  if (applied) saveFilters();

  render();
});
