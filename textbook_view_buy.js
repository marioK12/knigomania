// =============================
// textbook_view_buy.js
// ADD TO CART for textbooks (NO email, NO checkout)
// Target: cart_api.php?action=add
// ✅ NO redirect + refresh cart badge
// =============================

(function(){
  const btn = document.getElementById("addToCartBtn");
  if (!btn) return;

  const BASE = (window.KM_BASE || "").toString(); // "/" or "/subfolder/"

  function goLogin(){
    const next = encodeURIComponent(location.pathname + location.search);
    location.href = BASE + "login.php?next=" + next;
  }

  function safeNumPrice(x){
    const n = Number(x);
    return Number.isFinite(n) ? n : 0;
  }

  function pickFromArray(arr, id){
    if (!Array.isArray(arr)) return null;
    return arr.find(x => Number(x?.id) === Number(id)) || null;
  }

  function getTextbook(){
    const id = Number(new URLSearchParams(location.search).get("id"));
    if (!Number.isFinite(id)) return null;

    // 1) direct from window.TEXTBOOKS
    let tb = pickFromArray(window.TEXTBOOKS, id);

    // 2) fallback from sessionStorage
    if (!tb){
      try{
        const raw = sessionStorage.getItem("TEXTBOOKS_DATA_v1");
        if (raw){
          const arr = JSON.parse(raw);
          tb = pickFromArray(arr, id);
        }
      }catch(e){}
    }
    return tb;
  }

  btn.addEventListener("click", async () => {
    if (!window.KM_AUTH?.loggedIn) return goLogin();

    const tb = getTextbook();
    if (!tb?.id){
      alert("Не успях да заредя данните за учебника.");
      return;
    }

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = "Добавям...";

    try{
      const fd = new FormData();
      fd.append("type", "textbook");
      fd.append("id", String(tb.id));
      fd.append("qty", "1");
      fd.append("title", String(tb.title || "Учебник"));
      fd.append("unit_price", String(safeNumPrice(tb.price).toFixed(2)));

      const res = await fetch(BASE + "cart_api.php?action=add", {
        method: "POST",
        body: fd,
        credentials: "same-origin"
      });

      const j = await res.json().catch(() => ({}));

      if (!res.ok || !j.ok){
        alert(j.err || "Грешка при добавяне в количката.");
        return;
      }

      // ако има лимит до 10 (ако си го направил в API-то)
      if (j.capped) {
        // не е фатално, просто info
        // можеш да махнеш това ако не искаш alert
        alert("⚠️ Максимум 10 броя за този артикул.");
      }

      // ✅ feedback (NO redirect)
      btn.textContent = "✅ Добавено";
      btn.classList.add("is-added");
      setTimeout(() => {
        btn.textContent = oldText;
        btn.classList.remove("is-added");
      }, 1200);

      // ✅ refresh cart badge if present
      if (window.KMCart && typeof window.KMCart.refreshBadge === "function") {
        window.KMCart.refreshBadge();
      }

      return;

    }catch(e){
      alert("Грешка при връзката със сървъра.");
    }finally{
      btn.disabled = false;
      // ако сме в catch и не сме сменили текста
      if (btn.textContent === "Добавям...") btn.textContent = oldText;
    }
  });
})();
