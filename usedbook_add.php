<?php
require_once "db.php";
require_once "auth.php";
require_login();

$page_title = "Добави обява - Втора ръка";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout ub-add">

<?php require_once "nav.php"; ?>
<link rel="stylesheet" href="usedbook_add.css">

<main class="km-main py-4 py-md-5">
  <div class="container" style="max-width: 980px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h1 class="h3 mb-1">➕ Добави обява</h1>
        <div class="text-muted">Само логнати потребители могат да публикуват.</div>
      </div>
      <a href="usedbooks.php" class="btn btn-outline-secondary">← Назад</a>
    </div>

    <div id="msg" class="d-none"></div>

    <form id="addForm" class="card p-3 p-md-4" enctype="multipart/form-data">
      <div class="row g-3">

        <div class="col-12 col-md-6">
          <label class="form-label">Заглавие *</label>
          <input class="form-control" name="title" required minlength="2" placeholder="Напр. Хари Потър...">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Автор *</label>
          <input class="form-control" name="author" required minlength="2" placeholder="Напр. Дж. К. Роулинг">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Категория *</label>
          <select class="form-select" name="category" required>
            <option value="">Избери...</option>
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

        <div class="col-12 col-md-4">
          <label class="form-label">Цена (€) *</label>
          <input class="form-control" name="price" type="number" step="0.01" min="0" value="0" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Състояние *</label>
          <select class="form-select" name="condition" required>
            <option value="like_new">Като нова</option>
            <option value="good" selected>Добра</option>
            <option value="fair">Средна</option>
            <option value="poor">Лоша</option>
            <option value="new">Нова</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Град (по желание)</label>
          <input class="form-control" name="city" placeholder="Напр. София">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Телефон (по желание)</label>
          <input class="form-control" name="phone" placeholder="Напр. 0888 123 456">
          <div class="form-text">Препоръчително, ако искаш да се свързват с теб.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Описание *</label>
          <textarea class="form-control"
                    name="description"
                    rows="4"
                    required
                    minlength="10"
                    placeholder="Състояние, забележки, издание..."></textarea>
          <div class="form-text">Минимум 10 символа.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Снимки (до 5) *</label>
          <input id="images" class="form-control" type="file" name="images[]"
                 accept="image/png,image/jpeg,image/webp" multiple required>
          <div class="form-text">JPG/PNG/WEBP до 3MB. Задължително поне 1 снимка.</div>

          <div id="previews" class="d-flex flex-wrap gap-2 mt-3"></div>
        </div>

        <div class="col-12 d-grid d-md-flex gap-2 mt-2">
          <button class="btn btn-primary btn-lg" type="submit" id="btnSave">Публикувай</button>
          <a class="btn btn-outline-secondary btn-lg" href="usedbooks.php">Откажи</a>
        </div>

      </div>
    </form>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
const API = "usedbooks_api.php";
const input = document.getElementById("images");
const previews = document.getElementById("previews");

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

function clearMsg(){
  const box = document.getElementById("msg");
  box.classList.add("d-none");
  box.innerHTML = "";
}

function renderPreviews(files){
  previews.innerHTML = "";
  files.forEach(f => {
    const url = URL.createObjectURL(f);
    const div = document.createElement("div");
    div.innerHTML = `<img src="${url}" style="width:92px;height:110px;object-fit:cover;border-radius:12px;border:1px solid rgba(0,0,0,.1)">`;
    previews.appendChild(div);
  });
}

input.addEventListener("change", () => {
  clearMsg();

  let files = Array.from(input.files || []);
  if (files.length > 5) {
    showMsg("err", "Максимум 5 снимки. Ще се използват първите 5.");
    files = files.slice(0, 5);
  }
  renderPreviews(files);
});

document.getElementById("addForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  clearMsg();

  const btn = document.getElementById("btnSave");
  btn.disabled = true;
  const old = btn.textContent;
  btn.textContent = "Публикувам...";

  try{
    // Form data
    const fd = new FormData(e.target);

    // ✅ задължително описание (мин 10 символа)
    const desc = String(fd.get("description") || "").trim();
    if (desc.length < 10) {
      showMsg("err", "Описанието трябва да е поне 10 символа.");
      btn.disabled = false;
      btn.textContent = old;
      return;
    }

    // Files (max 5)
    let files = Array.from(input.files || []);

    if (files.length < 1) {
      showMsg("err", "Моля, качи поне 1 снимка.");
      btn.disabled = false;
      btn.textContent = old;
      return;
    }

    if (files.length > 5) {
      showMsg("err", "Максимум 5 снимки. Ще се използват първите 5.");
      files = files.slice(0, 5);
    }

    // Replace images[] with trimmed list
    fd.delete("images[]");
    files.forEach(f => fd.append("images[]", f));

    const res = await fetch(API, {
      method: "POST",
      body: fd,
      headers: { "Accept": "application/json" }
    });

    const txt = await res.text();
    let d = {};
    try { d = JSON.parse(txt); } catch { d = {}; }

    if (!res.ok) {
      throw new Error(d?.error || txt || "Грешка при публикуване.");
    }

    showMsg("ok", "Обявата е публикувана успешно!");
    setTimeout(() => location.href = "usedbooks.php", 650);

  } catch(err){
    showMsg("err", err.message || "Грешка.");
    btn.disabled = false;
    btn.textContent = old;
  }
});
</script>

</body>
</html>
