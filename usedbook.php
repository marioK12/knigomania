<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Книги втора ръка";

$me = is_logged_in() ? current_user() : null;
$me_id = (int)($me["id"] ?? 0);
$me_role = (string)($me["role"] ?? "user");
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<body class="km-layout">
<?php require_once "nav.php"; ?>

<!-- layout стиловете за картата (като учебници) -->
<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand.css">
<!-- green + hero 1:1 като usedbooks.php -->
<link rel="stylesheet" href="<?= e($base) ?>usedbook.css">

<!-- ✅ HERO 1:1 като usedbooks.php -->
<header class="ub-hero">
  <div class="container ub-hero-inner">

    <div class="ub-hero-icons" aria-hidden="true">
      <span>📚</span>
      <span>🌿</span>
      <span>🤍</span>
    </div>

    <h1 class="ub-hero-title">Книги втора ръка</h1>

    <p class="ub-hero-subtitle">Детайли за обявата</p>
  </div>

  <!-- ✅ SVG wave 1:1 (същият path като usedbooks.php) -->
  <svg class="ub-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,64 C240,96 480,32 720,48 C960,64 1200,128 1440,80 L1440,120 L0,120 Z"></path>
  </svg>
</header>


<main class="tbs-main">
  <div class="container" style="max-width:1100px;">

    <!-- TOP BAR: Back + Edit (same row) -->
    <div class="tbs-topbar">
      <a class="btn tbs-filter2-clear" href="<?= e($base) ?>usedbooks.php">← Назад</a>
      <div id="ownerTopBtn"></div>
    </div>

    <div id="msg" class="d-none"></div>

    <div id="wrap">Зареждане...</div>
  </div>
</main>

<?php require_once "footer.php"; ?>

<!-- Message Modal -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Пиши на продавача</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Затвори"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2" id="msgModalTo">До: —</div>
        <textarea id="msgModalBody" class="form-control" rows="4" maxlength="2000"
                  placeholder="Напиши съобщение..."></textarea>
        <div class="small text-muted mt-2" id="msgModalStatus"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отказ</button>
        <button type="button" class="btn btn-primary" id="msgModalSend">Изпрати</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ favorites.js -->
<script src="<?= e($base) ?>favorites.js"></script>


<script>
const API = "usedbooks_api.php";

const CURRENT_UID  = <?php echo (int)$me_id; ?>;
const CURRENT_ROLE = <?php echo json_encode($me_role, JSON_UNESCAPED_UNICODE); ?>;

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

function hideMsg(){
  const box = document.getElementById("msg");
  box.classList.add("d-none");
  box.innerHTML = "";
}

function getId(){
  const n = Number(new URLSearchParams(location.search).get("id"));
  return Number.isFinite(n) ? n : 0;
}

function catLabel(cat){
  const map = {
    hudojestvena:"Художествена", nauchna:"Научна", detski:"Детски",
    fantastika:"Фантастика", fentezi:"Фентъзи", trilari:"Трилъри",
    krimi:"Криминални", romantika:"Романтика", istoriya:"История",
    biografii:"Биографии", psihologiya:"Психология", samorazvitie:"Саморазвитие",
    biznes:"Бизнес", poeziya:"Поезия"
  };
  return map[cat] || cat || "";
}

function condLabel(c){
  const map = { new:"Нова", like_new:"Като нова", good:"Добра", fair:"Средна", poor:"Лоша" };
  return map[c] || c || "";
}

function canEdit(itemUserId){
  if (!CURRENT_UID) return false;
  return (CURRENT_UID === Number(itemUserId)) || (CURRENT_ROLE === "admin");
}

function ensureBootstrap(){
  if (typeof bootstrap === "undefined" || !bootstrap?.Modal) {
    showMsg("err", "Bootstrap JS (bundle) не е зареден.");
    return false;
  }
  return true;
}

function wireMessaging(it){
  const btnWrite = document.getElementById("btnWrite");
  if (!btnWrite) return;

  btnWrite.addEventListener("click", () => {
    if (!ensureBootstrap()) return;

    const modalEl = document.getElementById("msgModal");
    const toEl = document.getElementById("msgModalTo");
    const bodyEl = document.getElementById("msgModalBody");
    const statusEl = document.getElementById("msgModalStatus");
    const sendBtn = document.getElementById("msgModalSend");

    const toName = it.seller_name || it.seller_email || "Продавач";

    toEl.textContent = "До: " + toName;
    bodyEl.value = "";
    statusEl.textContent = "";

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    sendBtn.onclick = async () => {
      const body = bodyEl.value.trim();
      if (!body) { statusEl.textContent = "Напиши съобщение."; return; }

      sendBtn.disabled = true;
      statusEl.textContent = "Изпращане...";

      const fd = new FormData();
      fd.append("to_user_id", String(it.user_id));
      fd.append("usedbook_id", String(it.id));
      fd.append("body", body);

      try{
        const r = await fetch("<?= e($base) ?>send_message.php", { method:"POST", body: fd });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok){
          statusEl.textContent = j.error || "Грешка при изпращане.";
          return;
        }
        statusEl.textContent = "Изпратено ✅";
        window.location.href = "<?= e($base) ?>chat.php?u=" + encodeURIComponent(it.user_id) + "&b=" + encodeURIComponent(it.id);
      } catch(e){
        statusEl.textContent = "Мрежова грешка.";
      } finally {
        sendBtn.disabled = false;
      }
    };
  });
}

/* ❤️ Favorites */
async function applyFavOnLoad(itemId){
  const btn = document.getElementById("ubFavBtn");
  if (!btn) return;
  if (!window.KMFav || typeof KMFav.check !== "function") return;

  try{
    const liked = await KMFav.check("usedbook", itemId);
    const likedB =
      liked === true || liked === 1 || liked === "1" ||
      (typeof liked === "string" && liked.trim().toLowerCase() === "true");
    btn.classList.toggle("is-active", likedB);
  }catch(e){}
}

async function wireFavToggle(){
  const btn = document.getElementById("ubFavBtn");
  if (!btn) return;
  if (!window.KMFav || typeof KMFav.toggle !== "function") return;

  btn.addEventListener("click", async () => {
    const id = Number(btn.dataset.id || 0);
    if (!id) return;

    const liked = await KMFav.toggle("usedbook", id);
    if (liked === null) return;

    btn.classList.toggle("is-active", !!liked);
  });
}

async function load(){
  hideMsg();

  const id = getId();
  if (!id){ showMsg("err","Липсва ID."); return; }

  let res, d;
  try{
    res = await fetch(API + "?id=" + encodeURIComponent(id), { headers: { "Accept":"application/json" }});
    d = await res.json().catch(()=>({}));
  }catch(e){
    showMsg("err","Мрежова грешка.");
    return;
  }

  if (!res.ok){
    showMsg("err", d?.error || "Грешка при зареждане.");
    return;
  }

  const it = d.item || {};
  const images = Array.isArray(it.images) ? it.images : [];
  const mainImg = images[0]?.url || "";

  // TOP edit button (same row as Back)
  const ownerTopBtn = document.getElementById("ownerTopBtn");
  if (canEdit(it.user_id)){
    ownerTopBtn.innerHTML = `
      <a class="btn btn-tbs-edit"
         href="<?= e($base) ?>usedbook_edit.php?id=${Number(it.id)}"
         role="button">✏️ Редактирай</a>
    `;
  } else {
    ownerTopBtn.innerHTML = "";
  }

  // thumbs
  const thumbsHtml = images.map((im, k) => `
    <button type="button" class="tbs-thumb" data-src="${esc(im.url)}" aria-label="Снимка ${k+1}">
      <img src="${esc(im.url)}" alt="Снимка ${k+1}">
    </button>
  `).join("");

  const isOwner = CURRENT_UID && Number(CURRENT_UID) === Number(it.user_id);

  const writeBtnHtml = !CURRENT_UID
    ? `<a class="btn tbs-filter2-go" href="<?= e($base) ?>login.php">🔒 Влез, за да пишеш</a>`
    : (isOwner ? `` : `<button type="button" class="btn tbs-filter2-go" id="btnWrite">✉️ Пиши</button>`);

  const placeholder = "<?= e($base) ?>assets/placeholder-book.png";

  document.getElementById("wrap").innerHTML = `
    <div class="tbs-filter2" style="margin-top:0;">
      <div class="row g-4 align-items-stretch">

        <div class="col-12 col-lg-6">
          <div class="tbs-card" style="height:100%; overflow:hidden;">
            <div class="tbs-card-img" style="height: 430px;">
              ${
                mainImg
                  ? `<img id="mainImg" src="${esc(mainImg)}" alt="${esc(it.title||"Книга")}"
                        style="width:100%;height:100%;object-fit:contain;padding:12px;background:#f1f5f9;">`
                  : `<img id="mainImg" src="${esc(placeholder)}" alt="Няма снимка"
                        style="width:100%;height:100%;object-fit:contain;padding:12px;background:#f1f5f9;">`
              }
              <span class="tbs-chip">Книги</span>
            </div>

            ${images.length > 1 ? `
              <div class="tbs-thumbs">
                ${thumbsHtml}
              </div>
            ` : ``}
          </div>
        </div>

        <div class="col-12 col-lg-6">

          <div class="d-flex align-items-start justify-content-between gap-3">
            <div style="min-width:0;">
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="tbs-cond tbs-cond-vgood" style="font-size:.78rem;">${esc(catLabel(it.category))}</span>
                <span class="tbs-cond tbs-cond-good" style="font-size:.78rem;">${esc(condLabel(it.condition))}</span>
              </div>

              <h2 style="margin:10px 0 0; font-weight:1000; color:var(--ink);">
                ${esc(it.title || "Книга")}
              </h2>

              <div style="margin-top:4px; font-weight:800; color:rgba(15,23,42,.62);">
                от ${esc(it.author || "—")}
              </div>
            </div>

            <div class="tbs-view-pricecol">
              <div class="tbs-price">${Number(it.price || 0).toFixed(2)} €</div>
            </div>
          </div>

          <hr style="opacity:.12; margin:18px 0;">

          <div class="row g-3">
            <div class="col-6">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Състояние</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);">${esc(condLabel(it.condition))}</div>
            </div>

            <div class="col-6">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Град</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);">${esc(it.city || "—")}</div>
            </div>

            <div class="col-12">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Описание</div>
              <div style="
                margin-top:6px;
                background: rgba(2,6,23,.03);
                border: 1px solid rgba(15,23,42,.10);
                border-radius: 14px;
                padding: 10px 12px;
                color: rgba(15,23,42,.80);
              ">${esc(it.description || "Няма описание.")}</div>
            </div>

            <div class="col-12">
              <hr style="opacity:.12; margin:10px 0 14px;">
              <div style="font-size:.85rem; font-weight:800; color:rgba(15,23,42,.55);">Продавач</div>
              <div style="font-weight:900; color:rgba(15,23,42,.86);">${esc(it.seller_name || it.seller_email || "—")}</div>
            </div>
          </div>

          <div class="tbs-view-actions mt-3">

            ${writeBtnHtml}

            <a class="btn tbs-filter2-clear" href="<?= e($base) ?>usedbooks.php">📚 Още книги</a>

            <button class="tbs-view-fav km-fav"
                    id="ubFavBtn"
                    type="button"
                    data-id="${Number(it.id)}"
                    data-type="usedbook"
                    aria-label="Харесай"
                    title="Харесай">
              <svg viewBox="0 0 24 24" aria-hidden="true" class="tbs-view-fav-ico">
                <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
              </svg>
            </button>
          </div>

        </div>
      </div>
    </div>
  `;

  document.querySelectorAll(".tbs-thumb").forEach(btn => {
    btn.addEventListener("click", () => {
      const src = btn.getAttribute("data-src");
      if (!src) return;
      const main = document.getElementById("mainImg");
      if (main) main.src = src;
    });
  });

  await applyFavOnLoad(Number(it.id));
  await wireFavToggle();
  wireMessaging(it);

  document.title = (it.title ? it.title + " – " : "") + "Книги втора ръка";
}

load();
</script>

</body>
</html>
