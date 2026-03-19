// =============================
// checkout.js (FINAL)
// - loads cart via cart_api.php?action=list
// - renders summary with real images from window.BOOKS / window.TEXTBOOKS
// - ✅ confirms order via checkout_cod.php
// - ✅ on success redirects to index.php (home)
// =============================

(function(){
  const BASE = (window.KM_BASE || "").toString();

  const elEmpty = document.getElementById("checkoutEmpty");
  const elWrap  = document.getElementById("checkoutWrap");

  const elItems = document.getElementById("checkoutItems");
  const elSub   = document.getElementById("sumSub");
  const elTotal = document.getElementById("sumTotal");

  const btnConfirm = document.getElementById("confirmOrderBtn");
  const msgBox = document.getElementById("checkoutMsg");

  function escapeHtml(s){
    return String(s ?? "")
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  function showMsg(type, text){
    if (!msgBox) return;
    msgBox.classList.remove("d-none");
    msgBox.className = "mt-3 alert " + (type === "ok" ? "alert-success" : "alert-danger");
    msgBox.textContent = text;
  }

  function normalizeSrc(src){
    src = String(src || "").trim();
    if (!src) return "";
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith("/")) return src;
    return BASE + src.replace(/^(\.\/|\/)+/, "");
  }

  function pick(arr, id){
    return arr.find(x => Number(x?.id) === Number(id)) || null;
  }

  function fillImages(){
    const imgs = document.querySelectorAll(".js-checkout-img");
    if (!imgs.length) return;

    const BOOKS = Array.isArray(window.BOOKS) ? window.BOOKS : [];
    const TEXTBOOKS = Array.isArray(window.TEXTBOOKS) ? window.TEXTBOOKS : [];

    imgs.forEach(img => {
      const type = (img.dataset.type || "").trim(); // book|textbook
      const id = Number(img.dataset.id || 0);
      if (!type || !id) return;

      if (type === "book"){
        const b = pick(BOOKS, id);
        const src = normalizeSrc(b?.img || b?.cover || b?.image || "");
        if (src) img.src = src;
        return;
      }

      if (type === "textbook"){
        const tb = pick(TEXTBOOKS, id);
        const src = normalizeSrc(tb?.img || tb?.cover || tb?.image || "");
        if (src) img.src = src;
        return;
      }
    });
  }

  async function loadCart(){
    const res = await fetch(BASE + "cart_api.php?action=list", {
      credentials: "same-origin",
      headers: { "Accept": "application/json" },
      cache: "no-store"
    });

    const j = await res.json().catch(()=> ({}));
    if (!res.ok || !j.ok) throw new Error(j.err || "Грешка при зареждане на количката.");

    return {
      items: Array.isArray(j.items) ? j.items : [],
      subtotal: Number(j.subtotal || 0),
      total: Number(j.subtotal || 0) // доставката е безплатна при теб
    };
  }

  function render(items, subtotal, total){
    if (!items.length){
      elEmpty?.classList.remove("d-none");
      if (elWrap) elWrap.style.display = "none";
      return;
    }

    elEmpty?.classList.add("d-none");
    if (elWrap) elWrap.style.display = "";

    if (elItems){
      elItems.innerHTML = items.map(it => {
        const type = String(it.item_type || "");
        const id   = Number(it.item_id || 0);
        const qty  = Number(it.qty || 1);
        const unit = Number(it.unit_price || 0);
        const line = (unit * qty).toFixed(2);

        return `
          <div class="d-flex gap-4 align-items-start">
            <img
              class="checkout-img js-checkout-img"
              src="${BASE}textbooks/no-cover.png"
              alt=""
              data-type="${escapeHtml(type)}"
              data-id="${id}"
            />

            <div class="flex-grow-1">
              <div class="fw-semibold">${escapeHtml(it.title || "")}</div>
              <div class="text-muted small">
                ${type === "textbook" ? "Учебник" : "Книга"} • ${qty} бр. × ${unit.toFixed(2)} €
              </div>
            </div>

            <div class="fw-semibold">${line} €</div>
          </div>
        `;
      }).join("");
    }

    if (elSub) elSub.textContent = subtotal.toFixed(2);
    if (elTotal) elTotal.textContent = total.toFixed(2);

    // ✅ след като сме ренднали HTML, попълваме реалните снимки
    fillImages();
  }

  async function finalizeOrder(payload){
    const fd = new FormData();
    fd.append("ship_name", payload.ship_name);
    fd.append("ship_email", payload.ship_email);
    fd.append("ship_phone", payload.ship_phone);
    fd.append("ship_city", payload.ship_city);
    fd.append("ship_addr", payload.ship_addr);
    fd.append("ship_zip", payload.ship_zip);
    fd.append("note", payload.note);

    const res = await fetch(BASE + "checkout_cod.php", {
      method: "POST",
      body: fd,
      credentials: "same-origin"
    });

    const txt = await res.text();
    let j = {};
    try { j = JSON.parse(txt); } catch { j = {}; }

    if (!res.ok || !j.ok) {
      throw new Error(j.err || txt || "Грешка при финализиране на поръчката.");
    }
    return j;
  }

  // ✅ START
  (async function init(){
    try{
      const data = await loadCart();
      render(data.items, data.subtotal, data.total);
    }catch(err){
      showMsg("err", err.message || "Грешка.");
      elEmpty?.classList.remove("d-none");
      if (elWrap) elWrap.style.display = "none";
    }
  })();

  // ✅ Потвърди поръчката -> checkout_cod.php
  btnConfirm?.addEventListener("click", async () => {
    const ship_name  = (document.getElementById("shipName")?.value || "").trim();
    const ship_email = (document.getElementById("shipEmail")?.value || "").trim();
    const ship_phone = (document.getElementById("shipPhone")?.value || "").trim();
    const ship_city  = (document.getElementById("shipCity")?.value || "").trim();
    const ship_addr  = (document.getElementById("shipAddr")?.value || "").trim();
    const ship_zip   = (document.getElementById("shipZip")?.value || "").trim();
    const note       = (document.getElementById("note")?.value || "").trim();

    if (!ship_name || !ship_email || !ship_phone || !ship_city || !ship_addr){
      showMsg("err", "Моля, попълни всички задължителни полета (*).");
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ship_email)){
      showMsg("err", "Моля, въведи валиден имейл.");
      return;
    }

    if (!btnConfirm) return;

    const oldText = btnConfirm.textContent;
    btnConfirm.disabled = true;
    btnConfirm.textContent = "Обработва се...";

    try{
      const j = await finalizeOrder({
        ship_name, ship_email, ship_phone, ship_city, ship_addr, ship_zip, note
      });

      showMsg("ok", `✅ Поръчката е приета! ${j.order_id ? "№" + j.order_id : ""}`);

      // ✅ редирект към Home
      setTimeout(() => {
        location.href = BASE + "index.php";
      }, 900);

    }catch(err){
      showMsg("err", err.message || "Грешка.");
      btnConfirm.disabled = false;
      btnConfirm.textContent = oldText;
      return;
    }
  });

})();