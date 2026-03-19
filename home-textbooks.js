// home-textbooks.js
(function () {
  const grid = document.getElementById("homeTextbooksGrid");
  if (!grid) return;

  const TEXTBOOKS = window.TEXTBOOKS || [];
  if (!TEXTBOOKS.length) {
    grid.innerHTML = `<div class="col-12 text-muted">Няма налични учебници.</div>`;
    return;
  }

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  const bySubject = {};
  for (const t of TEXTBOOKS) {
    const key = t.subject || "Други";
    if (!bySubject[key]) bySubject[key] = [];
    bySubject[key].push(t);
  }

  const picked = Object.values(bySubject).map(arr => arr[0]);

  grid.innerHTML = picked.map(t => {
    const id = Number(t.id) || 0;
    const title = esc(t.title || "Учебник");
    const subject = esc(t.subject || "");
    const gradeBand = esc(t.gradeBand || "");
    const img = t.img || (t.images && t.images[0]) || "placeholder-book.jpg";
    const price = (t.price != null && t.price !== "")
      ? `${Number(t.price).toFixed(2)} €`
      : "";

    return `
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card km-book-card h-100">

          <a href="textbook_view.php?id=${id}" class="text-decoration-none">
            <div class="km-book-cover">
              <img src="${esc(img)}" alt="${title}" class="km-book-img">
            </div>
          </a>

          <div class="card-body km-book-body">
            <div class="km-book-title">${title}</div>

            <div class="km-book-author">
              ${subject}${gradeBand ? ` • ${gradeBand} клас` : ""}
            </div>

            <div class="km-book-bottom">
              <div class="km-book-price">${esc(price)}</div>
              <a href="textbook_view.php?id=${id}" class="btn btn-success km-book-btn">Виж</a>
            </div>
          </div>

        </div>
      </div>
    `;
  }).join("");
})();