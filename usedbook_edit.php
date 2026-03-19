  <!doctype html>
  <html lang="bg">
  <?php require_once "header.php"; ?>
  <?php require_once "auth.php"; require_login(); ?>
  <body class="km-layout ub-edit">

  <?php require_once "nav.php"; ?>
  <link rel="stylesheet" href="usedbook_edit.css">

  <main class="km-main py-4 py-md-5">
    <div class="container" style="max-width: 1000px;">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h1 class="h4 mb-1">✏️ Редакция на обява</h1>
          <div class="text-muted small">Промени данните и снимките. Трябва да остане поне 1 снимка.</div>
        </div>
        <a class="btn btn-outline-secondary" href="usedbooks.php">← Назад</a>
      </div>

      <div id="msg" class="d-none"></div>

      <div id="wrap">Зареждане...</div>
    </div>
  </main>


  
  <?php require_once "footer.php"; ?>
  

  <script>
  const API = "usedbooks_api.php";

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;")
      .replaceAll('"',"&quot;").replaceAll("'","&#039;");
  }

  function showMsg(type, text){
    const box = document.getElementById("msg");
    box.className = "mt-3 p-3 rounded-3 " + (type==="ok"
      ? "bg-success-subtle border border-success"
      : "bg-danger-subtle border border-danger"
    );
    box.innerHTML = `<b>${type==="ok" ? "✅" : "❌"}</b> ${esc(text)}`;
    box.classList.remove("d-none");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function getId(){
    const n = Number(new URLSearchParams(location.search).get("id"));
    return Number.isFinite(n) ? n : 0;
  }

  const condMap = { new:"Нова", like_new:"Като нова", good:"Добра", fair:"Средна", poor:"Лоша" };

  const categories = [
    ["hudojestvena","Художествена"],
    ["nauchna","Научна"],
    ["detski","Детски"],
    ["fantastika","Фантастика"],
    ["fentezi","Фентъзи"],
    ["trilari","Трилъри"],
    ["krimi","Криминални"],
    ["romantika","Романтика"],
    ["istoriya","История"],
    ["biografii","Биографии"],
    ["psihologiya","Психология"],
    ["samorazvitie","Саморазвитие"],
    ["biznes","Бизнес"],
    ["poeziya","Поезия"],
  ];

  async function load(){
    const id = getId();
    if (!id) return showMsg("err", "Липсва ID.");

    const res = await fetch(API + "?id=" + encodeURIComponent(id), { headers: {"Accept":"application/json"} });
    const d = await res.json().catch(()=>({}));
    if (!res.ok) return showMsg("err", d?.error || "Грешка");

    const it = d.item;
    const images = Array.isArray(it.images) ? it.images : [];

    document.getElementById("wrap").innerHTML = `
      <div class="card p-3 p-md-4">
        <form id="editForm" class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Заглавие *</label>
            <input class="form-control" name="title" required minlength="2" value="${esc(it.title)}">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Автор *</label>
            <input class="form-control" name="author" required minlength="2" value="${esc(it.author)}">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Категория *</label>
            <select class="form-select" name="category" required>
              ${categories.map(([val, label]) => `
                <option value="${val}" ${val===it.category ? "selected":""}>${label}</option>
              `).join("")}
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Цена (€) *</label>
            <input class="form-control" name="price" type="number" step="0.01" min="0" required value="${Number(it.price||0).toFixed(2)}">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">Състояние *</label>
            <select class="form-select" name="condition" required>
              ${["like_new","good","fair","poor","new"].map(v => `
                <option value="${v}" ${v===it.condition ? "selected":""}>${condMap[v] || v}</option>
              `).join("")}
            </select>
          </div>

          <div class="col-12 col-md-6">
           <label class="form-label">Град *</label>
           <input class="form-control" name="city" required minlength="2" value="${esc(it.city||"")}">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Телефон</label>
            <input class="form-control" name="phone" value="${esc(it.phone||"")}">
          </div>

          <div class="col-12">
            <label class="form-label">Описание *</label>
            <textarea class="form-control" name="description" rows="4" required minlength="10">${esc(it.description||"")}</textarea>
            <div class="form-text">Минимум 10 символа.</div>
          </div>

          <div class="col-12 d-grid d-md-flex gap-2 mt-2">
            <button class="btn btn-warning btn-lg" type="submit" id="btnSave">Запази</button>
            <a class="btn btn-outline-secondary btn-lg" href="usedbook.php?id=${Number(it.id)}">Виж обявата</a>
          </div>
        </form>
      </div>

      <div class="card p-3 p-md-4 mt-4">
        <h2 class="h6 mb-2">Снимки</h2>
        <div class="text-muted small mb-3">Трябва да остане поне 1 снимка. Макс 5.</div>

        <div class="d-flex flex-wrap gap-2" id="imgs">
          ${images.map(im => `
            <div class="position-relative" style="width:110px;">
              <img src="${esc(im.url)}" class="img-thumbnail" style="width:110px;height:140px;object-fit:cover;border-radius:12px;">
              <button type="button"
                      class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                      data-action="del-img"
                      data-image-id="${Number(im.id)}"
                      title="Изтрий">
                ✕
              </button>
            </div>
          `).join("")}
        </div>

        <hr class="my-3">

        <form id="addImgsForm" enctype="multipart/form-data" class="d-grid gap-2">
          <input type="hidden" name="action" value="add_images">
          <input type="hidden" name="id" value="${Number(it.id)}">
          <input id="newImages" class="form-control" type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
          <div class="form-text">Добави още снимки (до 5 общо).</div>
          <button class="btn btn-primary" type="submit" id="btnAddImgs">Качи снимки</button>
        </form>
      </div>

      <div class="card p-3 p-md-4 mt-4">
        <h2 class="h6 mb-2">Статус</h2>
        <div class="d-grid d-md-flex gap-2">
          <button class="btn btn-outline-success" type="button" id="btnSold">✅ Маркирай като продадена</button>
          <button class="btn btn-outline-danger" type="button" id="btnDelete">🗑️ Изтрий обявата</button>
        </div>
        <div class="text-muted small mt-2">Тези действия са само за собственика.</div>
      </div>
    `;

    // SAVE
    document.getElementById("editForm").onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById("btnSave");
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "Запазвам...";

      try{
        const fd = new FormData(e.target);

        const desc = String(fd.get("description") || "").trim();
        if (desc.length < 10) {
          showMsg("err", "Описанието трябва да е поне 10 символа.");
          btn.disabled = false;
          btn.textContent = old;
          return;
        }

        const payload = {
          action: "edit",
          id,
          title: fd.get("title"),
          author: fd.get("author"),
          category: fd.get("category"),
          price: fd.get("price"),
          condition: fd.get("condition"),
          city: fd.get("city"),
          phone: fd.get("phone"),
          description: fd.get("description"),
        };

        const res = await fetch(API, {
          method: "POST",
          headers: {
            "Accept":"application/json",
            "Content-Type":"application/json"
          },
          body: JSON.stringify(payload)
        });

        const d2 = await res.json().catch(()=>({}));
        if (!res.ok) throw new Error(d2?.error || "Грешка");

        showMsg("ok", "Промените са запазени.");
        await load();
      } catch(err){
        showMsg("err", err.message || "Грешка");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    };

    // ADD IMAGES
    document.getElementById("addImgsForm").onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById("btnAddImgs");
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "Качвам...";

      try{
        const fd = new FormData(e.target);
        const res = await fetch(API, { method:"POST", headers:{ "Accept":"application/json" }, body: fd });
        const d2 = await res.json().catch(()=>({}));
        if (!res.ok) throw new Error(d2?.error || "Грешка");

        showMsg("ok", "Снимките са качени.");
        await load();
      } catch(err){
        showMsg("err", err.message || "Грешка");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    };

    // SOLD
    document.getElementById("btnSold").onclick = async () => {
      try{
        const res = await fetch(API, {
          method:"POST",
          headers:{ "Accept":"application/json", "Content-Type":"application/json" },
          body: JSON.stringify({ action:"mark_sold", id })
        });
        const d2 = await res.json().catch(()=>({}));
        if (!res.ok) throw new Error(d2?.error || "Грешка");
        showMsg("ok", "Маркирано като продадена.");
        await load();
      } catch(err){
        showMsg("err", err.message || "Грешка");
      }
    };

    // DELETE LISTING
    document.getElementById("btnDelete").onclick = async () => {
      if (!confirm("Сигурен ли си, че искаш да изтриеш обявата?")) return;
      try{
        const res = await fetch(API, {
          method:"POST",
          headers:{ "Accept":"application/json", "Content-Type":"application/json" },
          body: JSON.stringify({ action:"delete_listing", id })
        });
        const d2 = await res.json().catch(()=>({}));
        if (!res.ok) throw new Error(d2?.error || "Грешка");
        location.href = "usedbooks.php";
      } catch(err){
        showMsg("err", err.message || "Грешка");
      }
    };
  }

  /* DELETE IMAGE – глобална делегация (работи винаги) */
  document.addEventListener("click", async (ev) => {
    const b = ev.target.closest?.('[data-action="del-img"]');
    if (!b) return;

    const imageId = Number(b.getAttribute("data-image-id"));
    if (!imageId) return;

    b.disabled = true;
    try{
      const res = await fetch(API, {
        method: "POST",
        headers: { "Accept":"application/json", "Content-Type":"application/json" },
        body: JSON.stringify({ action:"delete_image", image_id: imageId })
      });
      const d2 = await res.json().catch(()=>({}));
      if (!res.ok) throw new Error(d2?.error || "Грешка");

      showMsg("ok", "Снимката е изтрита.");
      await load();
    } catch(err){
      showMsg("err", err.message || "Грешка");
    } finally {
      b.disabled = false;
    }
  });

  load();
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    document.body.classList.add("ubf-ready");
  });
  </script>


  </body>
  </html>
