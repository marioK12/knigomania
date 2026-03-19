// favorites.js
window.KMFav = (function () {
  const API = "favorites_api.php"; // ако е в подпапка: "/folder/favorites_api.php"

  async function req(params) {
    const url = new URL(API, location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v)));

    const r = await fetch(url.toString(), { credentials: "same-origin" });
    let data = null;
    try { data = await r.json(); } catch(e){}

    if (!r.ok || !data || data.ok === false) {
      // ако не е логнат
      if (data && data.err === "NOT_LOGGED_IN") {
        // по желание: пренасочване към login
        // location.href = "login.php";
        return { ok:false, err:"NOT_LOGGED_IN" };
      }
      return { ok:false, err:(data && data.err) ? data.err : "NETWORK_ERROR" };
    }
    return data;
  }

  async function toggle(type, id) {
    const out = await req({ action:"toggle", type, id });
    if (!out.ok) return null;
    return out.liked; // true/false
  }

  async function check(type, id) {
    const out = await req({ action:"check", type, id });
    if (!out.ok) return false;
    return !!out.liked;
  }

  async function list() {
    const out = await req({ action:"list" });
    if (!out.ok) return [];
    return out.items || [];
  }

  async function remove(type, id) {
    const out = await req({ action:"remove", type, id });
    return !!out.ok;
  }

  return { toggle, check, list, remove };
})();
