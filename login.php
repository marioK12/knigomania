<?php
require_once "db.php";
require_once "auth.php";

auto_login($pdo);

$page_title = "Вход";

if (is_logged_in()) {
  header("Location: index.php");
  exit;
}

$err = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $pass  = $_POST["password"] ?? "";

  if ($email === "" || $pass === "") {
    $err = "Моля, попълнете имейл и парола.";
  } else {

    $st = $pdo->prepare("
      SELECT id, name, email, avatar, pass_hash
      FROM users
      WHERE email = ?
      LIMIT 1
    ");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u || !password_verify($pass, $u["pass_hash"])) {
      $err = "Грешен имейл или парола.";
    } else {

      $_SESSION["user"] = [
        "id"     => (int)$u["id"],
        "name"   => $u["name"],
        "email"  => $u["email"],
        "avatar" => $u["avatar"] ?? null
      ];

      if (!empty($_POST["remember"])) {
        remember_me($pdo, (int)$u["id"], 30);
      }

      $next = $_GET["next"] ?? "";
      if ($next && str_starts_with($next, "/") === false && strpos($next, "http") !== 0) {
        header("Location: " . $next);
      } else {
        header("Location: index.php");
      }
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">

<script>
window.IS_LOGGED = <?= is_logged_in() ? 'true' : 'false' ?>;
</script>

<?php require_once "nav.php"; ?>

<main class="auth-wrap">
  <div class="auth-card">
    <h1 class="auth-title">Вход</h1>
    <p class="auth-sub">Добре дошъл отново 👋</p>

    <?php if ($err): ?>
      <div class="auth-alert error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form" autocomplete="on">
      <div class="auth-field">
        <label for="email">Имейл</label>
        <input id="email" name="email" type="email" required
               value="<?= htmlspecialchars($email) ?>"
               placeholder="name@example.com">
      </div>

      <div class="auth-field">
        <label for="password">Парола</label>
        <input id="password" name="password" type="password" required
               placeholder="••••••••">
      </div>

      <label style="display:flex;gap:10px;align-items:center;margin:10px 0 14px;">
        <input type="checkbox" name="remember" value="1">
        Запомни ме
      </label>

      <button class="auth-btn primary" type="submit">Влез</button>

      <p class="auth-foot" style="margin-top:10px">
          Забравена парола?
        <a href="forgot_password.php">Възстанови</a>
      </p>

    </form>

    <p class="auth-foot">
      Нямаш профил?
      <a href="register.php">Регистрация</a>
    </p>
  </div>
</main>

<?php require_once "footer.php"; ?>

</body>
</html>
