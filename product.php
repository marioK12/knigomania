<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// current user
$cu = is_logged_in() ? current_user() : null;

// avatar url (subfolder safe)
$avatarUrl = null;
if ($cu && !empty($cu["avatar"])) {
  $avatarUrl = $base . "usersIMG/" . $cu["avatar"];
}

$page_title = "Книга";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>textbook_view.css?v=999">


<body class="km-layout tv-anim">
<?php require_once "nav.php"; ?>

<main class="km-main">
  <section class="tv-wrap">
    <div class="container tv-container">
      <section class="km-product-wrap">
        <div id="bookDetails"></div>
      </section>
    </div>
  </section>
</main>

<script>
  window.KM_AUTH = <?= json_encode([
    "loggedIn" => is_logged_in(),
    "email"    => $cu["email"] ?? null,
    "name"     => $cu["name"] ?? null,
    "avatar"   => $avatarUrl,
    "base"     => $base
  ], JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- ✅ favorites.js трябва да е ПРЕДИ footer.php -->
<script src="<?= e($base) ?>favorites.js?v=1"></script>

<script src="<?= e($base) ?>bookslist.js?v=1"></script>
<script src="<?= e($base) ?>product.js?v=1"></script>

<?php require_once "footer.php"; ?>
</body>
</html>
