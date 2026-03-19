<?php
require_once "db.php";
require_once "auth.php";
require_once "mailer.php";

$page_title = "Смяна на парола";

$msg = "";
$err = "";

$email = trim((string)($_GET["email"] ?? ($_POST["email"] ?? "")));
$code  = trim((string)($_POST["code"] ?? ""));
$pass1 = (string)($_POST["pass1"] ?? "");
$pass2 = (string)($_POST["pass2"] ?? "");

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Невалиден имейл.";
  } elseif ($code === "" || !preg_match('/^\d{6}$/', $code)) {
    $err = "Кодът трябва да е 6 цифри.";
  } elseif (mb_strlen($pass1) < 6) {
    $err = "Паролата трябва да е поне 6 символа.";
  } elseif ($pass1 !== $pass2) {
    $err = "Паролите не съвпадат.";
  } else {

    // намираме user по email
    $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $userId = (int)$st->fetchColumn();

    if (!$userId) {
      $err = "Невалиден код или изтекъл код.";
    } else {
      $codeHash = km_hash_code($code);

      // последен активен reset
      $st = $pdo->prepare("
        SELECT id
        FROM password_resets
        WHERE user_id=?
          AND used_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
      ");
      $st->execute([$userId]);
      $resetId = (int)$st->fetchColumn();

      if (!$resetId) {
        $err = "Невалиден код или изтекъл код.";
      } else {
        $st = $pdo->prepare("SELECT code_hash FROM password_resets WHERE id=? LIMIT 1");
        $st->execute([$resetId]);
        $dbHash = (string)$st->fetchColumn();

        if (!$dbHash || !hash_equals($dbHash, $codeHash)) {
          $err = "Невалиден код или изтекъл код.";
        } else {
          // записваме нова парола
          $newHash = password_hash($pass1, PASSWORD_DEFAULT);

          // ✅ при теб колоната е pass_hash
          $pdo->prepare("UPDATE users SET pass_hash=? WHERE id=?")
              ->execute([$newHash, $userId]);

          // маркираме reset-а като използван
          $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")
              ->execute([$resetId]);

          // ✅ успех + авто-redirect към вход след 2 секунди
          $msg = "Паролата е сменена успешно. Пренасочване към вход…";
          header("Refresh: 2; url=".$base."login.php");
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">
<?php require_once "nav.php"; ?>

<style>
/* Малко по-красив success/box само за тази страница */
.auth-card{ position:relative; }
.reset-success{
  margin-top:12px;
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 14px;
  border-radius:14px;
  border:1px solid rgba(22,163,74,.25);
  background: rgba(22,163,74,.10);
  color: rgba(15,23,42,.86);
  font-weight:800;
}
.reset-success .ico{
  width:34px;height:34px;border-radius:12px;
  display:grid;place-items:center;
  background: rgba(22,163,74,.18);
  box-shadow: inset 0 0 0 1px rgba(22,163,74,.18);
}
.reset-hint{
  margin-top:10px;
  color: rgba(15,23,42,.60);
  font-weight:700;
  font-size:.95rem;
}
</style>

<main class="auth-wrap">
  <div class="auth-card">
    <h1 class="auth-title">Смяна на парола</h1>
    <p class="auth-sub">Въведи имейла, кода от имейла и новата парола.</p>

    <?php if ($msg): ?>
      <div class="reset-success">
        <div class="ico">✅</div>
        <div><?= e($msg) ?></div>
      </div>

      <div class="reset-hint">Ако не стане автоматично, натисни бутона:</div>

      <div style="margin-top:12px;">
        <a class="auth-btn primary"
           href="<?= e($base) ?>login.php"
           style="display:inline-block;text-decoration:none;">Вход →</a>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="auth-alert error"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if (!$msg): ?>
    <form method="post" class="auth-form" autocomplete="on">
      <div class="auth-field">
        <label for="email">Имейл</label>
        <input id="email" name="email" type="email" required
               value="<?= e($email) ?>"
               placeholder="name@example.com">
      </div>

      <div class="auth-field">
        <label for="code">Код (6 цифри)</label>
        <input id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required
               value="<?= e($code) ?>"
               placeholder="123456">
      </div>

      <div class="auth-field">
        <label for="pass1">Нова парола</label>
        <input id="pass1" name="pass1" type="password" minlength="6" required placeholder="******">
      </div>

      <div class="auth-field">
        <label for="pass2">Повтори парола</label>
        <input id="pass2" name="pass2" type="password" minlength="6" required placeholder="******">
      </div>

      <button class="auth-btn primary" type="submit">Смени парола</button>

      <p class="auth-foot" style="margin-top:12px;">
        Нямаш код? <a href="<?= e($base) ?>forgot_password.php">Изпрати нов</a>
      </p>
    </form>
    <?php endif; ?>
  </div>
</main>

<?php require_once "footer.php"; ?>
</body>
</html>
