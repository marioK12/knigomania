<?php
require_once "db.php";
require_once "auth.php";

$page_title = "Регистрация";

if (is_logged_in()) {
  header("Location: index.php");
  exit;
}

$err = "";
$email = "";
$name = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name  = trim($_POST["name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $pass1 = $_POST["password"] ?? "";
  $pass2 = $_POST["password2"] ?? "";

  if ($name === "" || $email === "" || $pass1 === "" || $pass2 === "") {
    $err = "Моля, попълнете всички полета.";
  } elseif (mb_strlen($name) < 2) {
    $err = "Името трябва да е поне 2 символа.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Невалиден имейл адрес.";
  } elseif (strlen($pass1) < 6) {
    $err = "Паролата трябва да е поне 6 символа.";
  } elseif ($pass1 !== $pass2) {
    $err = "Паролите не съвпадат.";
  } else {
    // проверка дали имейлът съществува
    $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $st->execute([$email]);

    if ($st->fetch()) {
      $err = "Вече има профил с този имейл.";
    } else {
      $hash = password_hash($pass1, PASSWORD_DEFAULT);

      // ✅ записваме name + email + pass_hash
      $ins = $pdo->prepare(
        "INSERT INTO users (name, email, pass_hash, created_at) VALUES (?, ?, ?, NOW())"
      );
      $ins->execute([$name, $email, $hash]);

      // ✅ auto-login със name
      $_SESSION["user"] = [
        "id" => (int)$pdo->lastInsertId(),
        "name" => $name,
        "email" => $email
      ];

      header("Location: index.php");
      exit;
    }
  }
}
?>

<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">

<?php require_once "nav.php"; ?>

<main class="auth-wrap">
  <div class="auth-card">
    <h1 class="auth-title">Регистрация</h1>
    <p class="auth-sub">Създай своя профил 📚</p>

    <?php if ($err): ?>
      <div class="auth-alert error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form" autocomplete="on">

      <div class="auth-field">
        <label for="name">Име</label>
        <input id="name" name="name" type="text" required
               value="<?= htmlspecialchars($name) ?>"
               placeholder="Вашето име">
      </div>

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

      <div class="auth-field">
        <label for="password2">Повтори паролата</label>
        <input id="password2" name="password2" type="password" required
               placeholder="••••••••">
      </div>

      <button class="auth-btn primary" type="submit">
        Създай профил
      </button>
    </form>

    <p class="auth-foot">
      Вече имаш профил?
      <a href="login.php">Вход</a>
    </p>
  </div>
</main>

<?php require_once "footer.php"; ?>

</body>
</html>
