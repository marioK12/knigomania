<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function stars_text($n){
  $n = max(0, min(5, (int)$n));
  return str_repeat("★", $n) . str_repeat("☆", 5-$n);
}

/* ✅ base url (subfolder safe) */
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

/* =========================
   Avatar helpers
========================= */
function get_initials(string $name): string {
  $name = trim($name);
  if ($name === "") return "U";
  $parts = preg_split('/\s+/', $name);
  $initials = '';
  $count = 0;
  foreach ($parts as $part) {
    if ($count >= 2) break;
    if (mb_strlen($part) > 0) {
      $initials .= mb_substr($part, 0, 1, 'UTF-8');
      $count++;
    }
  }
  return mb_strtoupper($initials, 'UTF-8');
}

function pick_avatar_from_fields(?string $avatar, ?string $name, ?string $email): string {
  global $base;

  $avatar = trim((string)($avatar ?? ""));
  $userName = trim((string)($name ?? ""));
  $userEmail = trim((string)($email ?? ""));

  $fallbackName = $userName !== "" ? $userName : ($userEmail !== "" ? $userEmail : "U");
  $ini = get_initials($fallbackName);

  $fallbackUrl = "https://ui-avatars.com/api/?name=" . urlencode($ini) . "&background=6b7280&color=fff&bold=true&size=100&length=2";

  if ($avatar === "" || $avatar === "NULL" || strtolower($avatar) === "null") return $fallbackUrl;
  if (preg_match('~^https?://~i', $avatar)) return $avatar;

  $avatar = preg_replace('~^(\./|/)+~', '', $avatar);

  if (strpos($avatar, '/') === false && strpos($avatar, '\\') === false) {
    $fs = rtrim(__DIR__, "/\\") . "/usersIMG/" . $avatar;
    if (file_exists($fs)) return $base . "usersIMG/" . rawurlencode($avatar);
    return $fallbackUrl;
  }

  return $avatar;
}

/* =========================
   0) ID only
========================= */
$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { header("Location: textbooks.php"); exit; }

/* =========================
   1) User
========================= */
$me   = is_logged_in() ? current_user() : null;
$meId = $me ? (int)($me["id"] ?? 0) : 0;

/* =========================
   JSON helper
========================= */
function json_out($arr, int $code = 200): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================================================
   AJAX API
   - GET  ?ajax=reviews  => JSON list + stats
   - POST ?ajax=reviews  => add/edit/delete/toggle_like
========================================================= */
$ajax = (string)($_GET["ajax"] ?? "");

/* ✅ 1) POST handler FIRST (важно!) */
if ($ajax === "reviews" && $_SERVER["REQUEST_METHOD"] === "POST") {

  if (!$meId) json_out(["ok"=>false, "error"=>"Трябва да си влязъл в профила."], 401);

  $raw = file_get_contents("php://input");
  $isJson = stripos($_SERVER["CONTENT_TYPE"] ?? "", "application/json") !== false;

  $payload = [];
  if ($isJson && $raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $payload = $tmp;
  }

  $action = trim((string)($payload["action"] ?? ($_POST["action"] ?? "")));

  // --- toggle like ---
  if ($action === "toggle_like") {
    $rid = (int)($payload["review_id"] ?? ($_POST["review_id"] ?? 0));
    if ($rid <= 0) json_out(["ok"=>false, "error"=>"Невалидно ревю."], 400);

    $chk = $pdo->prepare("SELECT 1 FROM reviews WHERE id=? AND item_type='textbook' AND book_id=? AND status='approved' LIMIT 1");
    $chk->execute([$rid, $id]);
    if (!(bool)$chk->fetchColumn()) json_out(["ok"=>false, "error"=>"Невалидно ревю."], 400);

    $st = $pdo->prepare("SELECT 1 FROM review_likes WHERE review_id=? AND user_id=? LIMIT 1");
    $st->execute([$rid, $meId]);
    $already = (bool)$st->fetchColumn();

    if ($already) {
      $pdo->prepare("DELETE FROM review_likes WHERE review_id=? AND user_id=?")->execute([$rid, $meId]);
      $i_like = 0;
    } else {
      $pdo->prepare("INSERT INTO review_likes (review_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$rid, $meId]);
      $i_like = 1;
    }

    $st2 = $pdo->prepare("SELECT COUNT(*) FROM review_likes WHERE review_id=?");
    $st2->execute([$rid]);
    $cnt = (int)$st2->fetchColumn();

    json_out(["ok"=>true, "review_id"=>$rid, "likes"=>$cnt, "i_like"=>$i_like]);
  }

  // --- add review ---
  if ($action === "add_review") {
    $rating  = (int)($payload["rating"] ?? ($_POST["rating"] ?? 0));
    $message = trim((string)($payload["message"] ?? ($_POST["message"] ?? "")));

    $st = $pdo->prepare("SELECT id FROM reviews WHERE item_type='textbook' AND book_id=? AND user_id=? LIMIT 1");
    $st->execute([$id, $meId]);
    $existingId = (int)$st->fetchColumn();
    if ($existingId) {
      json_out(["ok"=>false, "error"=>"Вече имаш публикувано ревю. Използвай „Редактирай“.", "review_id"=>$existingId], 409);
    }

    if ($rating < 1 || $rating > 5) json_out(["ok"=>false, "error"=>"Моля, избери оценка ⭐ (1–5)."], 400);
    if (mb_strlen($message) < 2) json_out(["ok"=>false, "error"=>"Напиши поне 2 символа 🙂"], 400);

    $st = $pdo->prepare("
      INSERT INTO reviews (book_id, item_type, user_id, rating, message, status, created_at)
      VALUES (?, 'textbook', ?, ?, ?, 'approved', NOW())
    ");
    $st->execute([$id, $meId, $rating, $message]);

    json_out(["ok"=>true]);
  }

  // --- edit review ---
  if ($action === "edit_review") {
    $rid     = (int)($payload["review_id"] ?? ($_POST["review_id"] ?? 0));
    $rating  = (int)($payload["rating"] ?? ($_POST["rating"] ?? 0));
    $message = trim((string)($payload["message"] ?? ($_POST["message"] ?? "")));

    if ($rid <= 0) json_out(["ok"=>false, "error"=>"Невалидно ревю."], 400);
    if ($rating < 1 || $rating > 5) json_out(["ok"=>false, "error"=>"Моля, избери оценка ⭐ (1–5)."], 400);
    if (mb_strlen($message) < 2) json_out(["ok"=>false, "error"=>"Напиши поне 2 символа 🙂"], 400);

    $chk = $pdo->prepare("SELECT 1 FROM reviews WHERE id=? AND user_id=? AND item_type='textbook' AND book_id=? LIMIT 1");
    $chk->execute([$rid, $meId, $id]);
    if (!(bool)$chk->fetchColumn()) json_out(["ok"=>false, "error"=>"Нямаш права за това ревю."], 403);

    $st = $pdo->prepare("
      UPDATE reviews
      SET rating=?, message=?, status='approved', created_at=NOW()
      WHERE id=? AND user_id=?
    ");
    $st->execute([$rating, $message, $rid, $meId]);

    json_out(["ok"=>true]);
  }

  // --- delete review ---
  if ($action === "delete_review") {
    $rid = (int)($payload["review_id"] ?? ($_POST["review_id"] ?? 0));
    if ($rid <= 0) json_out(["ok"=>false, "error"=>"Невалидно ревю."], 400);

    $chk = $pdo->prepare("SELECT 1 FROM reviews WHERE id=? AND user_id=? AND item_type='textbook' AND book_id=? LIMIT 1");
    $chk->execute([$rid, $meId, $id]);
    if (!(bool)$chk->fetchColumn()) json_out(["ok"=>false, "error"=>"Нямаш права за това ревю."], 403);

    $pdo->prepare("DELETE FROM reviews WHERE id=? AND user_id=?")->execute([$rid, $meId]);
    $pdo->prepare("DELETE FROM review_likes WHERE review_id=?")->execute([$rid]);

    json_out(["ok"=>true]);
  }

  json_out(["ok"=>false, "error"=>"Невалидно действие."], 400);
}

/* ✅ 2) GET handler */
if ($ajax === "reviews" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $st = $pdo->prepare("
    SELECT COUNT(*) AS c, AVG(rating) AS a
    FROM reviews
    WHERE item_type='textbook' AND book_id=? AND status='approved'
  ");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $revCount  = (int)($row["c"] ?? 0);
  $avgRating = (float)($row["a"] ?? 0);
  $avgText  = $revCount ? number_format($avgRating, 1) : "0.0";
  $avgStars = (int)round($avgRating);

  $st = $pdo->prepare("
    SELECT
      r.id,
      r.user_id,
      r.rating,
      r.message,
      r.created_at,
      u.name,
      u.email,
      u.avatar,
      (SELECT COUNT(*) FROM review_likes rl WHERE rl.review_id = r.id) AS likes_count,
      (SELECT COUNT(*) FROM review_likes rl2 WHERE rl2.review_id = r.id AND rl2.user_id = ?) AS i_like
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.item_type='textbook' AND r.book_id=? AND r.status='approved'
    ORDER BY r.created_at DESC
    LIMIT 200
  ");
  $st->execute([$meId, $id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $myReviewId = 0;
  $out = [];
  foreach ($rows as $r) {
    $rid = (int)($r["id"] ?? 0);
    $uid = (int)($r["user_id"] ?? 0);
    if ($meId && $uid === $meId && !$myReviewId) $myReviewId = $rid;

    $name  = (string)($r["name"] ?? "");
    $email = (string)($r["email"] ?? "");
    $ava   = pick_avatar_from_fields($r["avatar"] ?? "", $name, $email);

    $out[] = [
      "id" => $rid,
      "user_id" => $uid,
      "name" => $name,
      "email" => $email,
      "avatar" => $ava,
      "created_at" => (string)($r["created_at"] ?? ""),
      "rating" => (int)($r["rating"] ?? 0),
      "message" => (string)($r["message"] ?? ""),
      "likes_count" => (int)($r["likes_count"] ?? 0),
      "liked_by_me" => ((int)($r["i_like"] ?? 0)) ? 1 : 0,
      "is_owner" => ($meId && $uid === $meId) ? 1 : 0,
    ];
  }

  json_out([
    "ok" => true,
    "logged_in" => $meId ? 1 : 0,
    "me_id" => $meId,
    "count" => $revCount,
    "avg" => $avgRating,
    "avg_text" => $avgText,
    "avg_stars" => $avgStars,
    "my_review_id" => $myReviewId,
    "reviews" => $out
  ]);
}

/* =========================
   PAGE (normal)
========================= */
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>textbook_view.css?v=3">

<style>
@keyframes tvGlow {
  0%   { box-shadow: 0 0 0 rgba(124,58,237,0); transform: translateY(0); }
  30%  { box-shadow: 0 0 0 6px rgba(124,58,237,.18); transform: translateY(-1px); }
  70%  { box-shadow: 0 0 0 10px rgba(124,58,237,.10); }
  100% { box-shadow: 0 0 0 rgba(124,58,237,0); transform: translateY(0); }
}
.tv-one-rev.is-highlight{
  border-radius: 14px;
  background: rgba(124,58,237,.06);
  animation: tvGlow 1.2s ease-out 1;
}
</style>

<main class="tv-wrap">
  <div class="container tv-container">

    <div class="tv-grid">
      <div class="tv-card tv-image-card">
        <div class="tv-image-box">
          <img class="tv-main-img" id="tvImg" src="<?= e($base) ?>textbooks/no-cover.png" alt="">
        </div>
      </div>

      <div class="tv-card tv-info-card">
        <h1 class="tv-title" id="tvTitle">Учебник</h1>

        <div class="tv-meta">
          <div class="tv-author" id="tvMeta"></div>

          <div class="tv-rating" title="Оценка">
            <span class="tv-stars" id="tvAvgStars"><?= e(stars_text(0)) ?></span>
            <span class="tv-rate-text" id="tvAvgText">(0.0/5)</span>
          </div>
        </div>

        <div class="tv-badges">
          <span class="tv-badge" id="tvSubject">Предмет</span>
          <span class="tv-badge tv-badge-soft" id="tvGrade" style="display:none;"></span>
        </div>

        <div class="tv-price" id="tvPrice">0.00 €</div>
        <p class="tv-desc" id="tvDesc"></p>

        <div class="tv-actions km-actions">
          <button class="tv-btn tv-btn-cart" id="addToCartBtn" type="button">🛒 Добави в количката</button>

          <button class="km-fav tv-fav"
                  type="button"
                  aria-label="Харесай"
                  title="Харесай"
                  data-type="textbook"
                  data-id="<?= (int)$id ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="tv-fav-ico">
              <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
            </svg>
          </button>

          <button class="tv-btn tv-btn-ghost" type="button" onclick="history.back()">← Назад</button>
        </div>

        <div class="tv-rev-note" id="tvDataWarn" style="display:none; margin-top:12px;">
          Не успях да заредя данните за учебника. Отвори го от <a href="<?= e($base) ?>textbooks.php">„Учебници“</a>.
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

  </div>
</main>
<script src="<?= e($base) ?>favorites.js"></script>
<script src="<?= e($base) ?>textbooks-data.js?v=1"></script>

<?php require_once "footer.php"; ?>

<script>
window.KM_BASE = "<?= e($base) ?>";
window.KM_AUTH = window.KM_AUTH || {
  loggedIn: <?= $meId ? "true" : "false" ?>,
  id: <?= (int)$meId ?>,
  email: <?= $me ? json_encode((string)($me["email"] ?? ""), JSON_UNESCAPED_UNICODE) : '""' ?>
};
</script>
<script src="<?= e($base) ?>textbook_view_buy.js?v=1"></script>

<script>
/* ❤️ Initial favorite state */
document.addEventListener("DOMContentLoaded", async () => {
  const btn = document.querySelector(".km-fav.tv-fav[data-type='textbook'][data-id]");
  if (!btn) return;
  if (!window.KMFav || typeof KMFav.check !== "function") return;

  const id = Number(btn.dataset.id || 0);
  if (!id) return;

  try{
    const liked = await KMFav.check("textbook", id);
    btn.classList.toggle("is-active", !!liked);
  }catch(e){}
});
</script>

<script>
(function(){
  const id = <?= (int)$id ?>;
  const BASE = "<?= e($base) ?>";

  const elTitle = document.getElementById("tvTitle");
  const elImg   = document.getElementById("tvImg");
  const elMeta  = document.getElementById("tvMeta");
  const elSub   = document.getElementById("tvSubject");
  const elGrade = document.getElementById("tvGrade");
  const elPrice = document.getElementById("tvPrice");
  const elDesc  = document.getElementById("tvDesc");
  const elWarn  = document.getElementById("tvDataWarn");

  function esc(s){
    return String(s ?? "")
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  function setWarn(msg){
    if (elWarn) {
      elWarn.style.display = "";
      if (msg) elWarn.innerHTML = msg;
    }
  }

  function normalizeImg(path){
    let p = String(path || "").trim();
    if (!p) return BASE + "textbooks/no-cover.png";

    if (/^https?:\/\//i.test(p)) return p;

    p = p.replace(/^\.\/+/, "");
    p = p.replace(/^\/+/, "");

    return BASE + p;
  }

  const arr = Array.isArray(window.TEXTBOOKS) ? window.TEXTBOOKS : [];
  const tb = arr.find(x => Number(x && x.id) === id) || null;

  console.log("TEXTBOOK ID =", id);
  console.log("TEXTBOOKS loaded =", arr.length);
  console.log("FOUND textbook =", tb);

  if (!tb){
    if (elTitle) elTitle.textContent = "Не успях да заредя учебника";
    if (elDesc) {
      elDesc.innerHTML = 'Учебникът с ID <strong>' + id + '</strong> не е намерен.';
    }
    setWarn('Не успях да заредя данните за учебника. Отвори го от <a href="<?= e($base) ?>textbooks.php">„Учебници“</a>.');
    return;
  }

  const title = tb.title || "Учебник";
  const publisher = tb.publisher || "Издателство";
  const year = tb.year || "";
  const subject = tb.subject || "Предмет";
  const gradeBand = tb.gradeBand || "";
  const img = normalizeImg(tb.img || (tb.images && tb.images[0]) || "");
  const price = Number(tb.price || 0).toFixed(2) + " €";
  const desc = tb.description || (window.TEXTBOOK_DESCRIPTIONS && window.TEXTBOOK_DESCRIPTIONS[id]) || "Няма описание за този учебник.";

  if (elTitle) elTitle.textContent = title;
  if (elImg){
    elImg.src = img;
    elImg.alt = title;
  }
  if (elMeta) elMeta.textContent = "от " + publisher + (year ? (" • " + year) : "");
  if (elSub) elSub.textContent = subject;

  if (elGrade){
    if (gradeBand){
      elGrade.style.display = "";
      elGrade.textContent = gradeBand + " клас";
    } else {
      elGrade.style.display = "none";
    }
  }

  if (elPrice) elPrice.textContent = price;
  if (elDesc) elDesc.innerHTML = esc(desc).replaceAll("\n","<br>");
})();
</script>

<script>
/* =========================================================
   Reviews UI (AJAX, no refresh)
========================================================= */
const REV_AJAX = "<?= e($base) ?>textbook_view.php?id=<?= (int)$id ?>&ajax=reviews";

function escapeHtml(s){
  return String(s ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}
function stars(n){
  n = Math.max(0, Math.min(5, Number(n) || 0));
  return "★".repeat(n) + "☆".repeat(5-n);
}
function showMsg(type, text){
  const el = document.getElementById("reviewMsg");
  if(!el) return;
  el.style.display = "";
  el.className = "tv-rev-note " + (type === "ok" ? "is-ok" : "is-warn");
  el.textContent = String(text || "");
}
function goLogin(){
  const BASE = (window.KM_BASE || "").toString();
  const next = encodeURIComponent(location.pathname + location.search);
  location.href = BASE + `login.php?next=${next}`;
}

let editingId = 0;
let editingRating = 0;
let editingMessage = "";

function renderWriteCardLogin(){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;
  wrap.innerHTML = `
    <div class="tv-rev-note is-warn" style="margin-top:8px;">
      За да публикуваш ревю, трябва да си влязъл в профила.
    </div>
  `;
}

function renderWriteCardAlready(myReviewId){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;

  wrap.innerHTML = `
    <div class="tv-rev-note is-warn" style="margin-top:8px;">
      Вече имаш публикувано ревю за този учебник. Използвай „Редактирай“.
    </div>

    <div class="tv-rev-actions" style="margin-top:10px;">
      <button type="button" class="tv-mini-btn" data-action="start-edit" data-rid="${Number(myReviewId)}">
        ✏️ Редактирай моето ревю
      </button>
      <a class="tv-mini-btn" href="#rev${Number(myReviewId)}">👇 Виж моето ревю</a>
    </div>
  `;
}

function renderWriteCardForm(oldRating = 0, oldMsg = ""){
  const wrap = document.getElementById("writeArea");
  if (!wrap) return;

  wrap.innerHTML = `
    <div id="reviewMsg" class="tv-rev-note" style="display:none;"></div>
    

    <div class="tv-stars-pick" id="tvStarsPick" aria-label="Оценка">
      ${[1,2,3,4,5].map(v => `<button type="button" class="tv-star" data-v="${v}">★</button>`).join("")}
      <span class="tv-stars-hint" id="tvStarsHint">Изберете</span>
    </div>

    <textarea id="message" class="tv-rev-text" rows="5" placeholder="Споделете мнение..." required>${escapeHtml(oldMsg)}</textarea>

    <button type="button" id="sendReview" class="tv-publish">➤ Публикувай ревю</button>
  `;

  const wrapStars = document.getElementById("tvStarsPick");
  const hint = document.getElementById("tvStarsHint");
  const btns = wrapStars ? wrapStars.querySelectorAll(".tv-star") : [];
  let val = Number(oldRating || 0);

  function paint(){
    btns.forEach(b => {
      const v = Number(b.dataset.v||0);
      b.classList.toggle("is-on", v <= val);
    });
    if (hint) hint.textContent = val ? (val + "/5") : "Изберете";
    renderWriteCardForm._val = val;
  }
  btns.forEach(b => b.addEventListener("click", () => { val = Number(b.dataset.v||0); paint(); }));
  paint();

  const sendBtn = document.getElementById("sendReview");
  if (sendBtn){
    sendBtn.onclick = async () => {
      if (!window.KM_AUTH?.loggedIn) return goLogin();

      const rating = Number(renderWriteCardForm._val || 0);
      if (!rating) return showMsg("warn", "Моля, избери оценка ⭐ (1–5).");

      const msg = (document.getElementById("message")?.value || "").trim();
      if (msg.length < 2) return showMsg("warn", "Напиши поне 2 символа 🙂");

      sendBtn.disabled = true;
      const old = sendBtn.textContent;
      sendBtn.textContent = "Пращам...";

      try{
        const res = await fetch(REV_AJAX, {
          method: "POST",
          headers: { "Accept":"application/json", "Content-Type":"application/json" },
          credentials: "same-origin",
          cache: "no-store",
          body: JSON.stringify({ action:"add_review", rating, message: msg })
        });

        const txt = await res.text();
        let d = {};
        try{ d = JSON.parse(txt); }catch(e){ d = {}; }

        if (!res.ok){
          showMsg("warn", d?.error || txt || ("HTTP " + res.status));
          await loadReviews();
          return;
        }

        showMsg("ok", "Ревюто е добавено успешно!");
        editingId = 0; editingRating = 0; editingMessage = "";
        await loadReviews();
      }catch(e){
        showMsg("warn", "Грешка при връзка със сървъра.");
      }finally{
        sendBtn.disabled = false;
        sendBtn.textContent = old;
      }
    };
  }
}
renderWriteCardForm._val = 0;

async function loadReviews(){
  const listEl = document.getElementById("reviewsList");
  const countEl = document.getElementById("reviewsCount");
  const avgStarsEl = document.getElementById("tvAvgStars");
  const avgTextEl  = document.getElementById("tvAvgText");

  try{
    const res = await fetch(REV_AJAX, {
      headers: { "Accept":"application/json" },
      credentials: "same-origin",
      cache: "no-store"
    });

    const txt = await res.text();
    let data = {};
    try{ data = JSON.parse(txt); }catch(e){ data = {}; }
    if (!data || !data.ok) throw new Error(data?.error || "bad");

    if (countEl) countEl.textContent = String(data.count ?? 0);
    if (avgStarsEl) avgStarsEl.textContent = stars(Number(data.avg_stars || 0));
    if (avgTextEl)  avgTextEl.textContent = `(${String(data.avg_text || "0.0")}/5)`;

    const items = Array.isArray(data.reviews) ? data.reviews : [];
    const myId = Number(data.my_review_id || 0);

    if (!window.KM_AUTH?.loggedIn){
      renderWriteCardLogin();
    } else if (myId && !editingId){
      renderWriteCardAlready(myId);
    } else if (!document.getElementById("tvStarsPick") && !editingId){
      renderWriteCardForm();
    }

    if (!listEl) return;

    if (!items.length){
      listEl.innerHTML = `<div class="tv-rev-empty">Все още няма ревюта.</div>`;
      return;
    }

    listEl.innerHTML = items.map(r => {
      const rid = Number(r.id || 0);
      const mine = !!r.is_owner;

      const name = r.name || r.email || "Потребител";
      const date = r.created_at || "";
      const msg  = r.message || "";
      const rt   = Number(r.rating || 0);

      const likesCount = Number(r.likes_count || 0);
      const iLike = !!r.liked_by_me;

      if (mine && rid === Number(editingId) && (!editingMessage || !editingRating)){
        if (!editingMessage) editingMessage = String(msg || "");
        if (!editingRating) editingRating = Number(rt || 0);
      }

      if (mine && rid === Number(editingId)){
        return `
          <div class="tv-one-rev" id="rev${rid}">
            <div class="tv-one-top">
              <div class="tv-one-user">
                <img class="tv-avatar-img" src="${escapeHtml(r.avatar||"")}" alt="">
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
                    <button type="button" class="${v <= editingRating ? "is-on" : ""}" data-action="edit-star" data-v="${v}">★</button>
                  `).join("")}
                  <span id="editHint${rid}" style="margin-left:8px; font-weight:900; color:rgba(15,23,42,.65);">
                    ${editingRating ? (editingRating + "/5") : "0/5"}
                  </span>
                </div>
              </div>

              <textarea id="editMessage" class="tv-rev-text" rows="4" required>${escapeHtml(editingMessage)}</textarea>

              <div class="tv-edit-actions">
                <button class="tv-mini-btn" type="button" data-action="save-edit" data-rid="${rid}">💾 Запази</button>
                <button class="tv-mini-btn" type="button" data-action="cancel-edit">✖ Откажи</button>
              </div>
            </div>
          </div>
        `;
      }

      return `
        <div class="tv-one-rev" id="rev${rid}">
          <div class="tv-one-top">
            <div class="tv-one-user">
              <img class="tv-avatar-img" src="${escapeHtml(r.avatar||"")}" alt="">
              <div>
                <div class="tv-user-name">${escapeHtml(name)}</div>
                <div class="tv-user-date">${escapeHtml(date)}</div>
              </div>
            </div>
            <div class="tv-one-stars">${escapeHtml(stars(rt))}</div>
          </div>

          <div class="tv-one-text">${escapeHtml(msg).replaceAll("\n","<br>")}</div>

          <div class="tv-rev-actions">
            <button class="tv-like ${iLike ? "is-on" : ""}" data-action="toggle-like" data-rid="${rid}"
              ${!window.KM_AUTH?.loggedIn ? 'disabled title="Влез в профил, за да харесваш."' : ""}>
              ❤️ <span class="tv-like-count">${likesCount}</span>
            </button>

            ${mine ? `
              <button class="tv-mini-btn" type="button" data-action="start-edit" data-rid="${rid}" data-rating="${rt}" data-message="${escapeHtml(msg)}">✏️ Редактирай</button>
              <button class="tv-mini-btn" type="button" data-action="delete-review" data-rid="${rid}">🗑 Изтрий</button>
            ` : ``}
          </div>
        </div>
      `;
    }).join("");

  }catch(e){
    if (listEl) listEl.innerHTML = `<div class="tv-rev-empty">Грешка при зареждане: ${escapeHtml(e.message || "")}</div>`;
  }
}

async function apiPost(obj){
  const res = await fetch(REV_AJAX, {
    method: "POST",
    headers: { "Accept":"application/json", "Content-Type":"application/json" },
    credentials: "same-origin",
    cache: "no-store",
    body: JSON.stringify(obj)
  });

  const txt = await res.text();
  let d = {};
  try{ d = JSON.parse(txt); }catch(e){ d = {}; }
  if (!res.ok) throw new Error(d?.error || txt || ("HTTP " + res.status));
  return d;
}

document.addEventListener("click", async (ev) => {
  const del = ev.target.closest?.('[data-action="delete-review"]');
  if (del){
    if (!window.KM_AUTH?.loggedIn) return goLogin();
    const rid = Number(del.getAttribute("data-rid"));
    if (!rid) return;
    if (!confirm("Сигурен ли си?")) return;

    try{
      await apiPost({ action:"delete_review", review_id: rid });
      editingId = 0; editingRating = 0; editingMessage = "";
      await loadReviews();
    }catch(e){
      alert(e.message || "Грешка при изтриване");
    }
    return;
  }

  const like = ev.target.closest?.('[data-action="toggle-like"]');
  if (like){
    if (!window.KM_AUTH?.loggedIn) return goLogin();
    const rid = Number(like.getAttribute("data-rid"));
    if (!rid) return;

    try{
      await apiPost({ action:"toggle_like", review_id: rid });
      await loadReviews();
    }catch(e){
      alert(e.message || "Грешка при like");
    }
    return;
  }

  const start = ev.target.closest?.('[data-action="start-edit"]');
  if (start){
    if (!window.KM_AUTH?.loggedIn) return goLogin();
    const rid = Number(start.getAttribute("data-rid"));
    if (!rid) return;

    editingId = rid;
    editingRating = Number(start.getAttribute("data-rating")) || 0;
    editingMessage = start.getAttribute("data-message") || "";
    await loadReviews();
    return;
  }

  const cancel = ev.target.closest?.('[data-action="cancel-edit"]');
  if (cancel){
    editingId = 0; editingRating = 0; editingMessage = "";
    await loadReviews();
    return;
  }

  const save = ev.target.closest?.('[data-action="save-edit"]');
  if (save){
    if (!window.KM_AUTH?.loggedIn) return goLogin();
    const rid = Number(save.getAttribute("data-rid"));
    if (!rid) return;

    const msg = (document.getElementById("editMessage")?.value || "").trim();
    const rate = Number(editingRating || 0);

    if (!rate || rate < 1 || rate > 5) return showMsg("warn", "Моля, избери оценка ⭐ (1–5).");
    if (msg.length < 2) return showMsg("warn", "Напиши поне 2 символа 🙂");

    try{
      await apiPost({ action:"edit_review", review_id: rid, rating: rate, message: msg });
      editingId = 0; editingRating = 0; editingMessage = "";
      await loadReviews();
      showMsg("ok", "Запазено ✅");
    }catch(e){
      showMsg("warn", e.message || "Грешка при редакция");
    }
    return;
  }

  const es = ev.target.closest?.('[data-action="edit-star"]');
  if (es){
    const v = Number(es.getAttribute("data-v")) || 0;
    if (v >= 1 && v <= 5){
      editingRating = v;
      const hint = document.getElementById("editHint" + editingId);
      if (hint) hint.textContent = v + "/5";
      const wrap = es.closest(".tv-edit-stars");
      if (wrap){
        wrap.querySelectorAll("button[data-v]").forEach(b => {
          const bv = Number(b.dataset.v);
          b.classList.toggle("is-on", bv <= v);
        });
      }
    }
    return;
  }
});

document.addEventListener("DOMContentLoaded", () => {
  if (!window.KM_AUTH?.loggedIn) renderWriteCardLogin();
  else renderWriteCardForm();
  loadReviews();
});
</script>

<script>
/* page animation replay */
(function(){
  function replayAnim(){
    document.body.classList.remove("tv-anim");
    void document.body.offsetWidth;
    document.body.classList.add("tv-anim");
  }
  window.addEventListener("pageshow", function(e){
    if (e.persisted) replayAnim();
  });
  document.addEventListener("DOMContentLoaded", replayAnim);
})();
</script>

</body>
</html>
