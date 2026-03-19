// favorites_client.js
window.FavLocal = (function(){
  const KEY = "km_favs_v1"; // { book:[ids], textbook:[ids] }

  function load(){
    try { return JSON.parse(localStorage.getItem(KEY) || "{}") || {}; }
    catch(e){ return {}; }
  }
  function save(data){ localStorage.setItem(KEY, JSON.stringify(data)); }

  function list(type){
    const d = load();
    const arr = Array.isArray(d[type]) ? d[type] : [];
    return arr.map(Number).filter(n => Number.isFinite(n));
  }
  function has(type, id){
    id = Number(id);
    if (!Number.isFinite(id)) return false;
    return list(type).includes(id);
  }
  function toggle(type, id){
    id = Number(id);
    if (!Number.isFinite(id)) return false;

    const d = load();
    const arr = Array.isArray(d[type]) ? d[type].map(Number) : [];
    const i = arr.indexOf(id);
    if (i >= 0) arr.splice(i, 1); else arr.push(id);
    d[type] = arr;
    save(d);
    return i < 0; // liked?
  }
  function count(){
    const d = load();
    const a = (Array.isArray(d.book) ? d.book.length : 0) + (Array.isArray(d.textbook) ? d.textbook.length : 0);
    return a;
  }

  return { list, has, toggle, count };
})();
