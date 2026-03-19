// =============================
// product.js (FINAL - uses SAME tv-* layout as textbook_view.css)
// - TV layout: tv-grid + tv-card + tv-image-card/info-card
// - TV thumbs: tv-thumbs + tv-thumb
// - Reviews UI: tv-* (same as textbook view)
// - ✅ Dynamic avg rating from reviews (like textbooks)
// =============================

const catName = {
  hudojestvena: "Художествена", nauchna: "Научна", detski: "Детски книги",
  fantastika: "Фантастика", fentezi: "Фентъзи", trilari: "Трилъри",
  krimi: "Криминални", romantika: "Романтика", istoriya: "История",
  biografii: "Биографии", psihologiya: "Психология", samorazvitie: "Саморазвитие",
  biznes: "Бизнес", poeziya: "Поезия"
};

const BASE = (window.KM_BASE || "").toString();
const detailsEl = document.getElementById("bookDetails");

const REVIEWS_API = "reviews_api.php";

// Helpers
function getIdFromUrl() {
  const n = Number(new URLSearchParams(location.search).get("id"));
  return Number.isFinite(n) ? n : null;
}
function stars(n) {
  n = Math.max(0, Math.min(5, Number(n) || 0));
  return "★".repeat(n) + "☆".repeat(5 - n);
}
function safeNumPrice(x) { const n = Number(x); return Number.isFinite(n) ? n : 0; }
function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
function toBoolLiked(v){
  if (v === null || v === undefined) return false;
  if (typeof v === "boolean") return v;
  if (typeof v === "number") return v === 1;
  if (typeof v === "string"){
    const s = v.trim().toLowerCase();
    if (s === "1" || s === "true" || s === "yes" || s === "liked") return true;
    if (s === "0" || s === "false" || s === "no"  || s === "unliked") return false;
  }
  return !!v;
}

// ---------- Avatar (always IMG; ui-avatars when missing) ----------
function initials2(name, email){
  const s = String(name || "").trim() || String(email || "").trim() || "U";
  const parts = s.split(/\s+/).filter(Boolean);
  const a = (parts[0] || "U").slice(0,1);
  const b = (parts[1] || "").slice(0,1);
  return (a+b).toUpperCase();
}
function uiAvatarUrl(name, email){
  const ini = initials2(name, email);
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(ini)}&background=6b7280&color=fff&bold=true&size=100&length=2`;
}
function tvAvatarImg(avatarUrl, name, email){
  const src = (avatarUrl && String(avatarUrl).trim()) ? String(avatarUrl).trim() : uiAvatarUrl(name, email);
  return `<img class="tv-avatar-img" src="${escapeHtml(src)}" alt="">`;
}

// Back URL
const backUrl = (document.referrer && document.referrer.includes("books")) ? document.referrer : (BASE + "books.php");

// Find book
const id = getIdFromUrl();
const book = Array.isArray(window.BOOKS) ? window.BOOKS.find(b => Number(b.id) === id) : null;

// ===== Edit state =====
let editingId = 0;
let editingRating = 0;
let editingMessage = "";

// ---------- UI Messages (tv-rev-note) ----------
function showMsg(type, text){
  const el = document.getElementById("reviewMsg");
  if(!el) return;

  el.style.display = "";
  el.className = "tv-rev-note " + (type === "ok" ? "is-ok" : type === "warn" ? "is-warn" : "is-warn");
  el.textContent = String(text || "");
}

function goLogin() {
  const next = encodeURIComponent(location.pathname + location.search);
  location.href = BASE + `login.php?next=${next}`;
}

// DELETE
async function deleteReview(rid) {
  if (!rid) return;

  try {
    showMsg("warn", "Изтривам ревюто...");
    const res = await fetch(`${REVIEWS_API}?id=${encodeURIComponent(rid)}`, {
      method: "DELETE",
      headers: { "Accept": "application/json" }
    });

    const txt = await res.text();
    let d = {};
    try { d = JSON.parse(txt); } catch { d = {}; }

    if (!res.ok) throw new Error(d?.error || txt || "Грешка при изтриване");

    editingId = 0;
    editingRating = 0;
    editingMessage = "";

    await loadReviews();
    showMsg("ok", "Ревюто е изтрито.");
  } catch (e) {
    showMsg("err", e.message || "Грешка при изтриване");
  }
}

// LIKE toggle (reviews)
async function toggleLike(rid) {
  if (!rid) return;
  if (!window.KM_AUTH?.loggedIn) return goLogin();

  try {
    const res = await fetch(REVIEWS_API, {
      method: "POST",
      headers: { "Accept": "application/json" },
      body: JSON.stringify({ action: "toggle_like", review_id: rid })
    });

    const txt = await res.text();
    let d = {};
    try { d = JSON.parse(txt); } catch { d = {}; }

    if (!res.ok) throw new Error(d?.error || txt || "Грешка при like");
    await loadReviews();
  } catch (e) {
    showMsg("err", e.message || "Грешка при like");
  }
}

// SAVE edit
async function saveEdit(rid) {
  if (!rid) return;
  if (!window.KM_AUTH?.loggedIn) return goLogin();

  const msg = (document.getElementById("editMessage")?.value || "").trim();
  const rate = Number(editingRating || 0);

  if (!rate || rate < 1 || rate > 5) {
    showMsg("warn", "Моля, избери оценка ⭐ (1–5).");
    return;
  }
  if (msg.length < 2){
    showMsg("warn", "Напиши поне 2 символа 🙂");
    return;
  }

  try {
    showMsg("warn", "Запазвам промените...");
    const res = await fetch(REVIEWS_API, {
      method: "POST",
      headers: { "Accept": "application/json" },
      body: JSON.stringify({
        action: "edit_review",
        review_id: rid,
        rating: rate,
        message: msg
      })
    });

    const txt = await res.text();
    let d = {};
    try { d = JSON.parse(txt); } catch { d = {}; }

    if (!res.ok) throw new Error(d?.error || txt || "Грешка при редакция");

    editingId = 0;
    editingRating = 0;
    editingMessage = "";

    await loadReviews();
    showMsg("ok", "Ревюто е обновено успешно.");
  } catch (e) {
    showMsg("err", e.message || "Грешка при редакция");
  }
}

/* =========================
   WRITE CARD (same UI as textbook_view)
========================= */
function renderWriteCardAlready(myReviewId){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;

  wrap.innerHTML = `
    <div class="tv-rev-note is-warn" style="margin-top:8px;">
      Вече имаш публикувано ревю за тази книга. Използвай „Редактирай“.
    </div>

    <div class="tv-rev-actions" style="margin-top:10px;">
      <button type="button"
              class="tv-mini-btn"
              data-action="start-edit"
              data-rid="${Number(myReviewId)}">
        ✏️ Редактирай моето ревю
      </button>

      <a class="tv-mini-btn" href="#rev${Number(myReviewId)}">👇 Виж моето ревю</a>
    </div>
  `;
}

function renderWriteCardLogin(){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;

  wrap.innerHTML = `
    <div class="tv-rev-note is-warn" style="margin-top:8px;">
      За да публикуваш ревю, трябва да си влязъл в профила.
    </div>
  `;
}

function renderWriteCardForm(oldRating = 0, oldMsg = ""){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;

  wrap.innerHTML = `
    <div id="reviewMsg" class="tv-rev-note" style="display:none;"></div>
    <div class="tv-rev-note" style="margin-top:8px;">Ревюто ще се запази в базата.</div>

    <div class="tv-stars-pick" id="tvStarsPick" aria-label="Оценка">
      ${[1,2,3,4,5].map(v => `<button type="button" class="tv-star" data-v="${v}">★</button>`).join("")}
      <span class="tv-stars-hint" id="tvStarsHint">Изберете</span>
    </div>

    <textarea id="message" class="tv-rev-text" rows="5" placeholder="Споделете мнение..." required>${escapeHtml(oldMsg)}</textarea>

    <button type="button" id="sendReview" class="tv-publish">➤ Публикувай ревю</button>
  `;

  wireStarPicker(Number(oldRating || 0));
  wireSubmitButton();
}

function wireStarPicker(startVal = 0){
  const wrap = document.getElementById("tvStarsPick");
  const hint = document.getElementById("tvStarsHint");
  if(!wrap) return;

  const btns = wrap.querySelectorAll(".tv-star");
  let val = Number(startVal || 0);

  function paint(){
    btns.forEach(b => {
      const v = Number(b.dataset.v||0);
      b.classList.toggle("is-on", v <= val);
    });
    if (hint) hint.textContent = val ? (val + "/5") : "Изберете";
    wireStarPicker._val = val;
  }

  btns.forEach(b => b.addEventListener("click", () => {
    val = Number(b.dataset.v||0);
    paint();
  }));

  paint();
}
wireStarPicker._val = 0;

function wireSubmitButton(){
  const sendBtn = document.getElementById("sendReview");
  if (!sendBtn) return;

  sendBtn.onclick = async () => {
    if (!window.KM_AUTH?.loggedIn) return goLogin();

    const rating = Number(wireStarPicker._val || 0);
    if (!rating) return showMsg("warn", "Моля, избери оценка ⭐ (1–5).");

    const msg = (document.getElementById("message")?.value || "").trim();
    if (msg.length < 2) return showMsg("warn", "Напиши поне 2 символа 🙂");

    sendBtn.disabled = true;
    const old = sendBtn.textContent;
    sendBtn.textContent = "Пращам...";

    try{
      const fd = new FormData();
      fd.append("book_id", String(book.id));
      fd.append("rating", String(rating));
      fd.append("message", msg);

      const res = await fetch(REVIEWS_API, { method:"POST", body: fd, headers:{ "Accept":"application/json" }});
      const txt = await res.text();
      let d = {};
      try{ d = JSON.parse(txt); }catch(e){ d = {}; }

      if (!res.ok){
        showMsg(res.status === 409 ? "warn" : "err", d?.error || txt || "Грешка при публикуване.");
        if (res.status === 409) await loadReviews();
        return;
      }

      showMsg("ok", "Ревюто е добавено успешно!");
      await loadReviews();
    }catch(e){
      showMsg("err", "Грешка при връзка със сървъра.");
    }finally{
      sendBtn.disabled = false;
      sendBtn.textContent = old;
    }
  };
}

/* =========================
   Reviews load + render (tv UI)
   ✅ Also updates avg rating (stars + text) like textbooks
========================= */
async function loadReviews() {
  if (!book?.id) return;

  const listEl = document.getElementById("reviewsList");
  const countEl = document.getElementById("reviewsCount");

  // avg UI
  const avgStarsEl = document.getElementById("tvAvgStars");
  const avgTextEl  = document.getElementById("tvAvgText");

  try {
    const res = await fetch(`${REVIEWS_API}?book_id=${encodeURIComponent(book.id)}`, {
      headers: { "Accept": "application/json" }
    });

    const txt = await res.text();
    let data = {};
    try { data = JSON.parse(txt); } catch { data = {}; }

    const items = Array.isArray(data) ? data : (data.reviews || data.items || []);
    if (countEl) countEl.textContent = String(items.length);

    // ✅ compute avg
    let avg = 0;
    if (items.length){
      let sum = 0, cnt = 0;
      items.forEach(r => {
        const v = Number(r.rating || 0);
        if (v >= 1 && v <= 5){ sum += v; cnt++; }
      });
      avg = cnt ? (sum / cnt) : 0;
    }
    const avgText = items.length ? avg.toFixed(1) : "0.0";
    const avgStars = Math.round(avg);

    if (avgStarsEl) avgStarsEl.textContent = stars(avgStars);
    if (avgTextEl)  avgTextEl.textContent  = `(${avgText}/5)`;

    if (!listEl) return;

    const myEmail = (window.KM_AUTH?.email || "").toLowerCase();
    const myReview = myEmail ? items.find(r => String(r.email || "").toLowerCase() === myEmail) : null;

    // Write card
    if (!window.KM_AUTH?.loggedIn){
      renderWriteCardLogin();
    } else if (myReview && !editingId){
      renderWriteCardAlready(Number(myReview.id));
    } else if (!document.getElementById("tvStarsPick") && !editingId){
      renderWriteCardForm();
    }

    if (!items.length) {
      listEl.innerHTML = `<div class="tv-rev-empty">Все още няма ревюта.</div>`;
      return;
    }

    listEl.innerHTML = items.map(r => {
      const rid = Number(r.id || 0);
      const mine = myEmail && String(r.email || "").toLowerCase() === myEmail;

      const name = r.name || r.email || "Потребител";
      const date = r.created_at || "";
      const msg  = r.message || "";
      const rt   = Number(r.rating || 0);

      const likesCount = Number(r.likes_count || 0);
      const iLike = !!r.liked_by_me;

      // If edit started from "already" button (no data-rating/message),
      // grab from current record:
      if (mine && rid === Number(editingId) && (!editingMessage || !editingRating)){
        if (!editingMessage) editingMessage = String(msg || "");
        if (!editingRating) editingRating = Number(rt || 0);
      }

      if (mine && rid === Number(editingId)) {
        return `
          <div class="tv-one-rev" id="rev${rid}">
            <div class="tv-one-top">
              <div class="tv-one-user">
                ${tvAvatarImg(r.avatar || "", r.name, r.email)}
                <div>
                  <div class="tv-user-name">${escapeHtml(name)}</div>
                  <div class="tv-user-date">${escapeHtml(date)}</div>
                </div>
              </div>
              <div class="tv-one-stars">${escapeHtml(stars(rt))}</div>
            </div>

            <div class="tv-edit-form">
              <div class="tv-edit-row">
                <strong>Оценка:</strong>
                <div class="tv-edit-stars" data-rid="${rid}">
                  ${[1,2,3,4,5].map(v => `
                    <button type="button"
                            class="${v <= editingRating ? "is-on" : ""}"
                            data-action="edit-star"
                            data-v="${v}">★</button>
                  `).join("")}
                  <span id="editHint${rid}" style="margin-left:8px; font-weight:900; color:rgba(15,23,42,.65);">
                    ${editingRating ? (editingRating + "/5") : "0/5"}
                  </span>
                </div>
              </div>

              <textarea id="editMessage" class="tv-rev-text" rows="4" required>${escapeHtml(editingMessage)}</textarea>

              <div class="tv-edit-actions">
                <button class="tv-mini-btn" type="button" data-action="save-edit" data-rid="${rid}">💾 Запази</button>
                <button class="tv-mini-btn" type="button" data-action="cancel-edit" data-rid="${rid}">✖ Откажи</button>
              </div>
            </div>
          </div>
        `;
      }

      return `
        <div class="tv-one-rev" id="rev${rid}">
          <div class="tv-one-top">
            <div class="tv-one-user">
              ${tvAvatarImg(r.avatar || "", r.name, r.email)}
              <div>
                <div class="tv-user-name">${escapeHtml(name)}</div>
                <div class="tv-user-date">${escapeHtml(date)}</div>
              </div>
            </div>
            <div class="tv-one-stars">${escapeHtml(stars(rt))}</div>
          </div>

          <div class="tv-one-text">${escapeHtml(msg).replaceAll("\n","<br>")}</div>

          <div class="tv-rev-actions">
            <button
              class="tv-like ${iLike ? "is-on" : ""}"
              data-action="toggle-like"
              data-rid="${rid}"
              ${!window.KM_AUTH?.loggedIn ? 'disabled title="Влез в профил, за да харесваш."' : ""}>
              ❤️ <span class="tv-like-count">${likesCount}</span>
            </button>

            ${mine ? `
              <button class="tv-mini-btn" type="button"
                      data-action="start-edit"
                      data-rid="${rid}"
                      data-rating="${rt}"
                      data-message="${escapeHtml(msg)}">✏️ Редактирай</button>

              <button class="tv-mini-btn" type="button"
                      data-action="delete-review"
                      data-rid="${rid}">🗑 Изтрий</button>
            ` : ``}
          </div>
        </div>
      `;
    }).join("");

  } catch (e) {
    if (listEl) listEl.innerHTML = "Грешка при зареждане.";
  }
}

/* =========================
   ADD TO CART (books)
========================= */
function wireCODBuyButton() {
  const buyBtn = document.getElementById("addToCartBtn");
  if (!buyBtn) return;

  buyBtn.addEventListener("click", async () => {
    if (!window.KM_AUTH?.loggedIn) return goLogin();
    if (!book?.id) return;

    buyBtn.disabled = true;
    const oldText = buyBtn.textContent;
    buyBtn.textContent = "Добавям...";

    try {
      const fd = new FormData();
      fd.append("type", "book");
      fd.append("id", String(book.id));
      fd.append("qty", "1");
      fd.append("title", String(book.title || "Книга"));
      fd.append("unit_price", String(safeNumPrice(book.price).toFixed(2)));

      const res = await fetch(BASE + "cart_api.php?action=add", {
        method: "POST",
        body: fd,
        credentials: "same-origin"
      });

      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) {
        alert(j.err || "Грешка при добавяне в количката.");
        return;
      }

      buyBtn.textContent = "✅ Добавено";
      buyBtn.classList.add("is-added");
      setTimeout(() => {
        buyBtn.textContent = oldText;
        buyBtn.classList.remove("is-added");
      }, 1200);

      if (window.KMCart && typeof window.KMCart.refreshBadge === "function") {
        window.KMCart.refreshBadge();
      }
    } catch (err) {
      alert("Грешка при връзката със сървъра.");
    } finally {
      buyBtn.disabled = false;
      if (buyBtn.textContent === "Добавям...") buyBtn.textContent = oldText;
    }
  });
}

/* =========================
   Render page (TV layout)
========================= */
if (detailsEl && id && book) {
  document.title = `${book.title} | КнигоМания`;

  const imgsRaw = Array.isArray(book.images) && book.images.length ? book.images : [book.img || ""];
  const imgs = imgsRaw.map(x => String(x || "").trim()).filter(Boolean);
  const mainImg = imgs[0] || "";

  const categoryText = catName[book.category] || book.category || "Категория";
  const authorText = book.author ? `от ${book.author}` : "";

  detailsEl.innerHTML = `
    <div class="tv-grid">
      <div class="tv-card tv-image-card">
        <div class="tv-image-box">
          <img class="tv-main-img" id="tvMainImg" src="${escapeHtml(mainImg)}" alt="${escapeHtml(book.title || "Книга")}">
        </div>

        ${imgs.length > 1 ? `
          <div class="tv-thumbs">
            ${imgs.map(img => `
              <button class="tv-thumb" type="button" data-img="${escapeHtml(img)}" aria-label="Снимка">
                <img src="${escapeHtml(img)}" alt="">
              </button>
            `).join("")}
          </div>
        ` : ``}
      </div>

      <div class="tv-card tv-info-card">
        <h1 class="tv-title">${escapeHtml(book.title || "Книга")}</h1>

        <div class="tv-meta">
          <div class="tv-author">${escapeHtml(authorText)}</div>
          <!-- ✅ dynamic avg -->
          <div class="tv-rating" title="Оценка">
            <span class="tv-stars" id="tvAvgStars">${escapeHtml(stars(0))}</span>
            <span class="tv-rate-text" id="tvAvgText">(0.0/5)</span>
          </div>
        </div>

        <div class="tv-badges">
          <span class="tv-badge">${escapeHtml(categoryText)}</span>
        </div>

        <div class="tv-price">${safeNumPrice(book.price).toFixed(2)} €</div>
        <p class="tv-desc">${escapeHtml(book.desc || "Няма описание за тази книга.").replaceAll("\n","<br>")}</p>

        <div class="tv-actions km-actions">
          <button class="tv-btn tv-btn-cart" id="addToCartBtn" type="button">🛒 Добави в количката</button>

          <button class="km-fav tv-fav"
                  id="favBtn"
                  type="button"
                  aria-label="Харесай"
                  title="Харесай"
                  data-type="book"
                  data-id="${Number(book.id)}">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="tv-fav-ico">
              <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
            </svg>
          </button>

          <a class="tv-btn tv-btn-ghost" href="${escapeHtml(backUrl)}">← Назад</a>
        </div>
      </div>
    </div>

    <div class="tv-reviews">
      <div class="tv-rev-grid">

        <div class="tv-card tv-rev-card">
          <div class="tv-rev-title">Напишете ревю</div>
          <div id="writeArea"></div>
        </div>

        <div class="tv-card tv-rev-card">
          <div class="tv-rev-head">
            <div class="tv-rev-title">Ревюта (<span id="reviewsCount">0</span>)</div>
          </div>
          <div class="tv-rev-list" id="reviewsList">Зареждане...</div>
        </div>

      </div>
    </div>
  `;

  // thumbs click
  const mainEl = document.getElementById("tvMainImg");
  document.querySelectorAll(".tv-thumb").forEach(b => {
    b.addEventListener("click", () => {
      const img = b.getAttribute("data-img") || "";
      if (mainEl && img) mainEl.src = img;
    });
  });

  // cart
  wireCODBuyButton();

  // favorite initial
  const favBtn = document.getElementById("favBtn");
  if (favBtn && window.KMFav && typeof window.KMFav.check === "function") {
    const bid = parseInt(favBtn.dataset.id || "0", 10);
    if (Number.isFinite(bid) && bid > 0) {
      window.KMFav.check("book", bid).then(v => {
        favBtn.classList.toggle("is-active", toBoolLiked(v));
      }).catch(()=>{});
    }
  }

  // init write card
  if (!window.KM_AUTH?.loggedIn) renderWriteCardLogin();
  else renderWriteCardForm();

  // delegated clicks (reviews)
  document.addEventListener("click", (ev) => {
    const del = ev.target.closest?.('[data-action="delete-review"]');
    if (del) {
      const rid = Number(del.getAttribute("data-rid"));
      if (!rid) return;
      deleteReview(rid);
      return;
    }

    const like = ev.target.closest?.('[data-action="toggle-like"]');
    if (like) {
      const rid = Number(like.getAttribute("data-rid"));
      if (!rid) return;
      toggleLike(rid);
      return;
    }

    const start = ev.target.closest?.('[data-action="start-edit"]');
    if (start) {
      if (!window.KM_AUTH?.loggedIn) return goLogin();

      const rid = Number(start.getAttribute("data-rid"));
      if (!rid) return;

      editingId = rid;
      editingRating = Number(start.getAttribute("data-rating")) || 0;
      editingMessage = start.getAttribute("data-message") || "";
      loadReviews();
      return;
    }

    const cancel = ev.target.closest?.('[data-action="cancel-edit"]');
    if (cancel) {
      editingId = 0;
      editingRating = 0;
      editingMessage = "";
      loadReviews();
      return;
    }

    const save = ev.target.closest?.('[data-action="save-edit"]');
    if (save) {
      const rid = Number(save.getAttribute("data-rid"));
      if (!rid) return;
      saveEdit(rid);
      return;
    }

    const es = ev.target.closest?.('[data-action="edit-star"]');
    if (es) {
      const v = Number(es.getAttribute("data-v")) || 0;
      if (v >= 1 && v <= 5) {
        editingRating = v;
        const hint = document.getElementById("editHint" + editingId);
        if (hint) hint.textContent = v + "/5";
        const wrap = es.closest(".tv-edit-stars");
        if (wrap) {
          wrap.querySelectorAll("button[data-v]").forEach(b => {
            const bv = Number(b.dataset.v);
            b.classList.toggle("is-on", bv <= v);
          });
        }
      }
      return;
    }
  });

  // ✅ init avg + list
  loadReviews();
}
