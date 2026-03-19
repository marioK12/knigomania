<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

/* ✅ translate condition to BG (if DB stores EN like "good") */
function gift_condition_bg(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = [
    'new' => 'Нова',
    'like new' => 'Като нова',
    'likenew' => 'Като нова',
    'very good' => 'Много добра',
    'verygood' => 'Много добра',
    'good' => 'Добра',
    'fair' => 'Задоволителна',
    'poor' => 'Лоша',
  ];
  return $map[$s] ?? $s; // ако вече е на български, оставя го
}

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$page_title = "Подари книга";

// current user (for UI only)
$cu = is_logged_in() ? current_user() : null;
$meId = (int)($cu["id"] ?? 0);

// --- filters ---
$q    = trim((string)($_GET["q"] ?? ""));
$city = trim((string)($_GET["city"] ?? ""));

$where  = "g.status='active'";
$params = [];

if ($q !== "") {
  $where .= " AND (g.title LIKE ? OR g.author LIKE ? OR g.description LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($city !== "") {
  $where .= " AND g.city = ?";
  $params[] = $city;
}

$sql = "
SELECT
  g.*,
  u.name  AS seller_name,
  u.email AS seller_email,
  (
    SELECT file_name
    FROM gift_book_images gi
    WHERE gi.gift_book_id = g.id
    ORDER BY gi.sort_order ASC, gi.id ASC
    LIMIT 1
  ) AS img
FROM gift_books g
JOIN users u ON u.id = g.user_id
WHERE $where
ORDER BY g.created_at DESC, g.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

// cities dropdown
$st2 = $pdo->query("SELECT DISTINCT city FROM gift_books WHERE city IS NOT NULL AND city<>'' ORDER BY city ASC");
$cities = $st2 ? $st2->fetchAll(PDO::FETCH_COLUMN) : [];
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout km-anim">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>giftbooks.css?v=10">

<main class="km-main">
  <div class="container py-4">

    <!-- HERO -->
    <header class="gift-hero mb-4">
      <div class="gift-hero-inner">
        <div>
          <h1 class="gift-hero-title">Подари книга</h1>
          <p class="gift-hero-sub">Безплатни книги от потребители — вземи или подари.</p>
        </div>
      </div>
    </header>

    <!-- FILTER PANEL like usedbooks -->
    <form class="gift-filter card mb-4" method="get" action="">
      <div class="gift-filter-head">
        <div class="gift-filter-title">
          <span class="gift-filter-ico" aria-hidden="true">🔎</span>
          <span>Филтри</span>
        </div>

        <?php if (is_logged_in()): ?>
          <a class="gift-add-btn" href="<?= e($base) ?>giftbook_add.php">+ Добави обява</a>
        <?php else: ?>
          <a class="gift-add-btn" href="<?= e($base) ?>login.php">+ Добави обява</a>
        <?php endif; ?>
      </div>

      <div class="gift-filter-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-6">
            <label class="form-label mb-1">Търсене</label>
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Заглавие, автор, описание...">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Град</label>
            <select class="form-select" name="city">
              <option value="">Всички</option>
              <?php foreach ($cities as $c): ?>
                <option value="<?= e($c) ?>" <?= ($city === $c ? "selected" : "") ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-2 d-grid">
            <button class="btn gift-filter-btn" type="submit">Търси</button>
          </div>
        </div>
      </div>
    </form>

    <!-- FOUND COUNT -->
    <div class="gift-found mb-3">
      Намерени <strong><?= (int)count($items) ?></strong> книги
    </div>

    <?php if (!$items): ?>
      <div class="alert alert-info">Няма активни обяви засега.</div>
    <?php else: ?>

      <div class="row g-3 gift-grid">
        <?php foreach ($items as $it): ?>
          <?php
            $id    = (int)$it["id"];
            $img   = trim((string)($it["img"] ?? ""));
            $imgUrl = ($img !== "")
              ? ($base . "giftIMG/" . rawurlencode($img))
              : ($base . "assets/img/no-image.png");

            $sellerName  = trim((string)($it["seller_name"] ?? ""));
            $sellerEmail = trim((string)($it["seller_email"] ?? ""));
            $sellerLabel = $sellerName !== "" ? $sellerName : $sellerEmail;

            $title  = (string)($it["title"] ?? "");
            $author = (string)($it["author"] ?? "");
            $cityTxt = trim((string)($it["city"] ?? ""));
            $viewUrl = $base . "giftbook_view.php?id=" . (int)$id;

            // ✅ condition (translated)
            $condRaw = trim((string)($it["condition"] ?? $it["book_condition"] ?? $it["state"] ?? $it["status_text"] ?? ""));
            $cond = ($condRaw !== "") ? gift_condition_bg($condRaw) : "";
          ?>

          <div class="col-12 col-sm-6 col-lg-4">
            <article class="gift-card">

              <a class="gift-card-img" href="<?= e($viewUrl) ?>" aria-label="Виж обявата">
                <img src="<?= e($imgUrl) ?>" alt="<?= e($title ?: "Книга") ?>">
                <span class="gift-chip">ПОДАРЯВАМ</span>
                <span class="gift-img-hint">👁 Виж</span>
              </a>

              <button class="gift-fav km-fav"
                      type="button"
                      data-type="giftbook"
                      data-id="<?= (int)$id ?>"
                      aria-label="Харесай"
                      title="Харесай">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="gift-fav-ico">
                  <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
                </svg>
              </button>

              <div class="gift-card-body">

                <!-- title left, author right -->
                <div class="gift-topline">
                  <h3 class="gift-title m-0">
                    <a href="<?= e($viewUrl) ?>"><?= e($title ?: "Без заглавие") ?></a>
                  </h3>

                  <?php if ($author !== ""): ?>
                    <div class="gift-author-mini"><?= e($author) ?></div>
                  <?php endif; ?>
                </div>

                <!-- condition + free -->
                <div class="gift-badges">
                  <?php if ($cond !== ""): ?>
                    <span class="gift-badge gift-badge-cond"><?= e($cond) ?></span>
                  <?php endif; ?>
                  <span class="gift-badge gift-badge-free">Безплатно</span>
                </div>

                <!-- city + donor (NO duplicates) -->
                <div class="gift-lines">
                  <div class="gift-line">
                    <span class="gift-ico">📍</span>
                    <span><?= e($cityTxt !== "" ? $cityTxt : "—") ?></span>
                  </div>

                  <div class="gift-line gift-line-muted">
                    <span class="gift-ico">👤</span>
                    <span><?= e($sellerLabel ?: "—") ?></span>
                  </div>
                </div>

                <!-- bottom: only button right -->
                <div class="gift-bottom">
                  <a class="gift-view-btn" href="<?= e($viewUrl) ?>">Виж</a>
                </div>

              </div>

            </article>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>
</main>

<!-- ✅ Only init hearts; click handled globally in footer.php -->
<script>
(function(){
  function toBool(v){
    if (v === true) return true;
    if (v === false) return false;
    if (v === 1 || v === "1") return true;
    if (v === 0 || v === "0") return false;
    if (typeof v === "string"){
      const s = v.trim().toLowerCase();
      if (s === "true" || s === "yes" || s === "liked" || s === "on") return true;
      if (s === "false" || s === "no" || s === "unliked" || s === "off" || s === "") return false;
    }
    return !!v;
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!window.KMFav || typeof KMFav.check !== "function") return;

    const btns = document.querySelectorAll(".km-fav[data-type='giftbook'][data-id]");
    for (const btn of btns) {
      const id = parseInt(btn.dataset.id || "0", 10);
      if (!id) continue;

      try{
        const likedRaw = await KMFav.check("giftbook", id);
        const liked = toBool(likedRaw);
        btn.classList.toggle("is-active", liked);
        btn.classList.toggle("liked", liked);
      }catch(e){}
    }
  });
})();
</script>

<?php require_once "footer.php"; ?>
</body>
</html>
