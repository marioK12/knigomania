// home-recommended.js
(function () {
  const grid = document.getElementById("homeRecommendedGrid");
  if (!grid) return;

  const BOOKS = window.BOOKS || [];
  const featured = BOOKS.filter(b => b && b.featured === true);

  if (!featured.length) {
    grid.innerHTML = `<div class="col-12 text-muted">Няма препоръчани книги.</div>`;
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

  function stars(n){
    n = Math.max(0, Math.min(5, Number(n) || 0));
    return "★".repeat(n) + "☆".repeat(5 - n);
  }

  grid.innerHTML = featured.map(b => {
    const id = Number(b.id) || 0;

    const title  = esc(b.title || "Книга");
    const author = esc(b.author || "");
    const desc   = esc(b.desc || "");

    const img = b.img || (b.images && b.images[0]) || "placeholder-book.jpg";
    const price = (b.price != null && b.price !== "")
      ? `${Number(b.price).toFixed(2)} €`
      : "";

    return `
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card km-book-card h-100">

          <a href="product.php?id=${id}" class="text-decoration-none">
            <div class="km-book-cover">
              <img src="${esc(img)}" alt="${title}" class="km-book-img">
            </div>
          </a>

          <!-- САМО Препоръчана -->
          <div class="km-book-tags">
            <span class="km-tag-featured">⭐ Препоръчана</span>
          </div>

          <div class="card-body km-book-body">
            <a href="product.php?id=${id}" class="text-decoration-none text-dark">
              <div class="km-book-title">${title}</div>
            </a>

            ${author ? `<div class="km-book-author">${author}</div>` : ``}

            ${desc ? `<div class="km-book-desc">${desc}</div>` : ``}

            

            <div class="km-book-bottom">
              <div class="km-book-price">${esc(price)}</div>
              <a href="product.php?id=${id}" class="btn btn-warning km-book-btn">Виж</a>
            </div>
          </div>

        </div>
      </div>
    `;
  }).join("");
})();
