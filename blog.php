<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// current user
$cu = is_logged_in() ? current_user() : null;

// message (after redirects)
$msg = (string)($_GET["msg"] ?? "");

// single post view?
$postId = (int)($_GET["id"] ?? 0);

$post = null;
$posts = [];
$q = trim((string)($_GET["q"] ?? ""));

// avatars folder (URL)
$AVATAR_DIR_URL = $base . "usersIMG/";

// blog images folder (URL)
$IMG_DIR_URL = $base . "blogIMG/";

/* -------- Helpers -------- */
function pick_avatar_url_or_empty(string $baseAvatarDir, ?string $avatar): string {
  $av = trim((string)$avatar);
  if ($av === "") return "";
  if (preg_match('~^https?://~i', $av)) return $av;
  return $baseAvatarDir . rawurlencode($av);
}

function initials_from(?string $name, ?string $email): string {
  $name = trim((string)$name);

  if ($name !== "") {
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $first = mb_substr($parts[0] ?? "U", 0, 1);
    $second = "";
    if (count($parts) > 1) {
      $second = mb_substr($parts[count($parts)-1], 0, 1);
    }
    $out = mb_strtoupper($first . $second);
    return $out ?: "U";
  }

  $email = trim((string)$email);
  if ($email !== "") return mb_strtoupper(mb_substr($email, 0, 1));

  return "U";
}

/* -------- Data -------- */
$postImages = []; // снимки само за single view

if ($postId > 0) {
  $st = $pdo->prepare("
    SELECT
      p.*,
      u.name  AS author_name,
      u.email AS author_email,
      u.avatar AS author_avatar
    FROM blog_posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
    LIMIT 1
  ");
  $st->execute([$postId]);
  $post = $st->fetch(PDO::FETCH_ASSOC);

  // снимките за поста
  if ($post) {
    $st = $pdo->prepare("
      SELECT filename
      FROM blog_post_images
      WHERE post_id = ?
      ORDER BY id ASC
    ");
    $st->execute([$postId]);
    $postImages = $st->fetchAll(PDO::FETCH_COLUMN);
  }

  $page_title = $post ? ($post["title"] . " — Блог") : "Постът не е намерен — Блог";
} else {
  if ($q !== "") {
    $st = $pdo->prepare("
      SELECT
        p.*,
        u.name  AS author_name,
        u.email AS author_email,
        u.avatar AS author_avatar
      FROM blog_posts p
      JOIN users u ON u.id = p.user_id
      WHERE p.title LIKE ? OR p.body LIKE ?
      ORDER BY p.created_at DESC
      LIMIT 50
    ");
    $like = "%".$q."%";
    $st->execute([$like, $like]);
  } else {
    $st = $pdo->query("
      SELECT
        p.*,
        u.name  AS author_name,
        u.email AS author_email,
        u.avatar AS author_avatar
      FROM blog_posts p
      JOIN users u ON u.id = p.user_id
      ORDER BY p.created_at DESC
      LIMIT 50
    ");
  }

  $posts = $st->fetchAll(PDO::FETCH_ASSOC);
  $page_title = "Блог";
}
?>
<!doctype html>
<html lang="bg">
<head>
  <title><?= e($page_title) ?></title>

  <?php require_once "header.php"; ?>
  <link rel="stylesheet" href="blog.css">
</head>

<body class="km-layout">

<?php require_once "nav.php"; ?>

<main>

  <?php if ($postId <= 0): ?>
    <section class="blog-hero2">
      <div class="hero-inner">
        <h1>Блог</h1>
        <p>Новини, рецензии и интересни статии за света на книгите</p>

        <?php if ($cu): ?>
          <a class="btn-hero" href="blog_add.php"><span style="font-weight:900;">+</span> Напиши статия</a>
        <?php else: ?>
          <button class="btn-hero" id="btnNeedAccount" type="button"><span style="font-weight:900;">+</span> Напиши статия</button>
        <?php endif; ?>

        <div class="hero-tools">
          <form class="hero-search" method="get" action="blog.php">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Търси в блога…">
            <button class="btn btn-soft" type="submit">Търси</button>
          </form>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <div class="container py-4">

    <?php if ($msg === "deleted"): ?>
      <div class="alert alert-success">Постът беше изтрит успешно.</div>
    <?php elseif ($msg === "saved"): ?>
      <div class="alert alert-success">Статията беше запазена успешно.</div>
    <?php elseif ($msg === "updated"): ?>
      <div class="alert alert-success">Статията беше обновена успешно.</div>
    <?php elseif ($msg === "nope"): ?>
      <div class="alert alert-danger">Нямаш право да извършиш това действие.</div>
    <?php endif; ?>

    <?php if ($postId > 0): ?>
      <!-- SINGLE POST -->
      <?php if (!$post): ?>
        <div class="alert alert-warning">Постът не е намерен.</div>
        <a class="btn btn-soft" href="blog.php">← Назад към блога</a>
      <?php else: ?>

        <div class="mb-3">
          <a class="btn btn-soft" href="blog.php">← Назад</a>

          <?php if ($cu && (int)$cu["id"] === (int)$post["user_id"]): ?>
            <a class="btn btn-eco ms-2" href="blog_add.php?edit=<?= (int)$post["id"] ?>">Редакция</a>
            <form class="d-inline" method="post" action="blog_delete.php?id=<?= (int)$post["id"] ?>">
              <button class="btn btn-del ms-2" type="submit">Изтрий</button>

            </form>
          <?php endif; ?>
        </div>

        <?php
          $authorName = (string)($post["author_name"] ?: $post["author_email"]);
          $avatarUrl  = pick_avatar_url_or_empty($AVATAR_DIR_URL, $post["author_avatar"] ?? "");
          $ini        = initials_from($post["author_name"] ?? "", $post["author_email"] ?? "");

          // всички снимки
          $imgs = $postImages ?: [];

// махаме празни/NULL имена (ако има)
$imgs = array_values(array_filter($imgs, fn($x) => trim((string)$x) !== ""));

$mainImg = $imgs[0] ?? "";
$hasMedia = ($mainImg !== "");
$hasGallery = count($imgs) > 1;

// ✅ ако има поне 1 снимка -> 2 колони (has-media)
// ✅ ако няма снимки -> 1 колона (no-media)
$layoutClass = $hasMedia ? "has-media" : "no-media";

        ?>

        <div class="blog-card p-4">
          <h2 class="fw-bold mb-2"><?= e($post["title"]) ?></h2>

          <div class="post-meta-row mb-3">
            <span class="author-pill">
              <span class="avatar-sm-wrap <?= $avatarUrl==="" ? "no-img" : "" ?>" data-ini="<?= e($ini) ?>" aria-hidden="true">
                <?php if ($avatarUrl !== ""): ?>
                  <img class="avatar-sm" src="<?= e($avatarUrl) ?>" alt=""
                       onerror="this.remove(); this.parentNode.classList.add('no-img');">
                <?php endif; ?>
              </span>
              <span><?= e($authorName) ?></span>
            </span>

            <span class="chip">🕒 <?= e(date("d.m.Y H:i", strtotime($post["created_at"]))) ?></span>

            <?php if (!empty($post["updated_at"])): ?>
              <span class="chip">✏️ обновено: <?= e(date("d.m.Y H:i", strtotime($post["updated_at"]))) ?></span>
            <?php endif; ?>
          </div>

          <div class="post-layout bookish <?= e($layoutClass) ?>">

            <?php if ($hasMedia): ?>
              <?php $mainUrl = $IMG_DIR_URL . rawurlencode((string)$mainImg); ?>

              <div class="post-media">
                <div class="post-main">
                  <img id="postMainImg" src="<?= e($mainUrl) ?>" alt="">
                </div>

                <?php if ($hasGallery): ?>
                  <div class="post-thumbs" id="postThumbs" aria-label="Галерия снимки">
                    <?php foreach ($imgs as $i => $fn): ?>
                      <?php $u = $IMG_DIR_URL . rawurlencode((string)$fn); ?>
                      <button
                        type="button"
                        class="post-thumb <?= $i===0 ? "is-active" : "" ?>"
                        data-src="<?= e($u) ?>"
                        aria-label="Снимка <?= (int)$i+1 ?>">
                        <img src="<?= e($u) ?>" alt="">
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

              </div>
            <?php endif; ?>

            <div class="post-content">
              <div class="post-body"><?= e($post["body"]) ?></div>
            </div>

          </div>

        </div>

      <?php endif; ?>

    <?php else: ?>
      <!-- LIST VIEW -->

      <?php if (empty($posts)): ?>
        <div class="empty-state">
          <div class="empty-icon" aria-hidden="true">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none">
              <path d="M6 4h10a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6"/>
              <path d="M8 7h8M8 10h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </div>

          <?php if ($q !== ""): ?>
            <h4 class="empty-title">Няма резултати</h4>
            <p class="empty-text">Не намерихме нищо за <b>“<?= e($q) ?>”</b>. Опитай с друга дума.</p>
            <div class="empty-actions">
              <a class="btn btn-soft" href="blog.php">Покажи всички статии</a>
            </div>
          <?php else: ?>
            <h4 class="empty-title">Все още няма статии</h4>
            <p class="empty-text">Бъди първият, който ще публикува нещо интересно за книгите.</p>
            <div class="empty-actions">
              <?php if ($cu): ?>
                <a class="btn btn-eco" href="blog_add.php"><span style="font-weight:900;">+</span> Напиши статия</a>
              <?php else: ?>
                <a class="btn btn-soft" href="<?= e($base) ?>login.php">Вход / Регистрация</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>

        <h3 class="section-title"><?= $q !== "" ? "Резултати" : "Последни статии" ?></h3>

        <div class="row g-3">
          <?php foreach ($posts as $p): ?>
            <?php
              $snippet = mb_substr(trim((string)$p["body"]), 0, 220);
              if (mb_strlen(trim((string)$p["body"])) > 220) $snippet .= "…";

              $authorName = (string)($p["author_name"] ?: $p["author_email"]);
              $avatarUrl  = pick_avatar_url_or_empty($AVATAR_DIR_URL, $p["author_avatar"] ?? "");
              $ini        = initials_from($p["author_name"] ?? "", $p["author_email"] ?? "");
            ?>
            <div class="col-12 col-md-6 col-lg-4">
              <a href="blog.php?id=<?= (int)$p["id"] ?>" class="text-decoration-none text-reset">
                <div class="blog-card p-3 h-100">
                  <div class="blog-meta mb-1">
                    <span class="author-line">
                      <span class="avatar-xs-wrap <?= $avatarUrl==="" ? "no-img" : "" ?>" data-ini="<?= e($ini) ?>" aria-hidden="true">
                        <?php if ($avatarUrl !== ""): ?>
                          <img class="avatar-xs" src="<?= e($avatarUrl) ?>" alt=""
                               onerror="this.remove(); this.parentNode.classList.add('no-img');">
                        <?php endif; ?>
                      </span>
                      <span class="author-name"><?= e($authorName) ?></span>
                    </span>
                    <span class="meta-sep">•</span>
                    <span><?= e(date("d.m.Y", strtotime($p["created_at"]))) ?></span>
                  </div>

                  <h5 class="fw-bold mb-2"><?= e($p["title"]) ?></h5>
                  <div class="text-muted" style="line-height:1.55;"><?= e($snippet) ?></div>

                  <div class="mt-3">
                    <span class="btn btn-soft btn-sm">Прочети повече →</span>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<!-- Need account modal -->
<div class="modal fade" id="needAccModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px;">
      <div class="modal-header">
        <h5 class="modal-title">Трябва ти акаунт</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        За да публикуваш в блога, трябва да си влязъл в профила си.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Затвори</button>
        <a class="btn btn-eco" href="<?= e($base) ?>login.php">Вход / Регистрация</a>
      </div>
    </div>
  </div>
</div>

<?php require_once "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  // Need account modal
  const btn = document.getElementById("btnNeedAccount");
  if (btn) {
    btn.addEventListener("click", function(){
      new bootstrap.Modal(document.getElementById("needAccModal")).show();
    });
  }

  // animations
  const main = document.querySelector("main");
  if (main) main.classList.add("km-boot");

  window.addEventListener("load", () => {
    if (main) main.classList.add("is-ready");
    const cards = document.querySelectorAll(".blog-card");
    cards.forEach((card, i) => {
      card.classList.add("km-card-in");
      setTimeout(() => card.classList.add("is-in"), 120 + i * 80);
    });
  }, { once: true });

  // thumbs -> replace main image (single post)
  const thumbs = document.getElementById("postThumbs");
  const mainImg = document.getElementById("postMainImg");
  if (thumbs && mainImg){
    thumbs.addEventListener("click", (ev) => {
      const b = ev.target.closest(".post-thumb");
      if (!b) return;
      const src = b.getAttribute("data-src");
      if (!src) return;
      mainImg.src = src;

      thumbs.querySelectorAll(".post-thumb").forEach(x => x.classList.remove("is-active"));
      b.classList.add("is-active");
    });
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
