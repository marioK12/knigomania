<?php
require_once "db.php";
require_once "auth.php";
require_once "mailer.php";

$page_title = "Забравена парола";

$msg = "";
$err = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim((string)($_POST["email"] ?? ""));

  // Никога не казваме дали имейлът съществува (anti-enumeration)
  $genericMsg = "Ако има акаунт с този имейл, изпратихме код за възстановяване.";

  if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = $genericMsg;
  } else {
    // намираме user
    $st = $pdo->prepare("SELECT id, name, email FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {
      $userId = (int)$u["id"];

      // rate limit: максимум 1 код на 60 секунди
      $st = $pdo->prepare("
        SELECT last_sent_at
        FROM password_resets
        WHERE user_id=? AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
      ");
      $st->execute([$userId]);
      $last = $st->fetchColumn();

      if ($last && (time() - strtotime($last) < 60)) {
        // пак показваме generic msg
        $msg = $genericMsg;
      } else {
        // чистим стари/изтекли
        $pdo->prepare("DELETE FROM password_resets WHERE user_id=? AND (used_at IS NOT NULL OR expires_at < NOW())")
            ->execute([$userId]);

        $code = km_random_code6();
        $codeHash = km_hash_code($code);
        $exp = date("Y-m-d H:i:s", time() + 10 * 60); // 10 мин

        $ip = $_SERVER["REMOTE_ADDR"] ?? null;
        $ua = substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);

        $pdo->prepare("
          INSERT INTO password_resets(user_id, code_hash, expires_at, last_sent_at, ip, user_agent)
          VALUES(?, ?, ?, NOW(), ?, ?)
        ")->execute([$userId, $codeHash, $exp, $ip, $ua]);

        // изпращаме имейл
        $safeName = (string)($u["name"] ?? "");
        $subject = "Код за възстановяване на парола (КнигоМания)";
        $html = "
          <div style='font-family:Arial,sans-serif'>
            <h2>Възстановяване на парола</h2>
            <p>Твоят код е:</p>
            <div style='font-size:28px;font-weight:800;letter-spacing:4px;margin:12px 0'>{$code}</div>
            <p>Кодът е валиден 10 минути.</p>
            <p style='color:#666'>Ако не си искал това — игнорирай този имейл.</p>
          </div>
        ";

        //send_mail($u["email"], $safeName, $subject, $html);
        km_send_mail($u["email"], $u["name"] ?? "", $subject, $html);

        $msg = $genericMsg;
      }
    } else {
      $msg = $genericMsg;
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
    <h1 class="auth-title">Забравена парола</h1>
    <p class="auth-sub">Въведи имейла си и ще получиш код.</p>

    <?php if ($msg): ?>
      <div class="auth-alert success"><?= htmlspecialchars($msg) ?></div>
      <div style="margin-top:12px">
        <a class="auth-btn primary" href="reset_password.php" style="display:inline-block;text-decoration:none;">Имам код</a>
      </div>
    <?php endif; ?>

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

      <button class="auth-btn primary" type="submit">Изпрати код</button>
    </form>

    <p class="auth-foot">
      Сети ли се?
      <a href="login.php">Вход</a>
    </p>
  </div>
</main>

<?php require_once "footer.php"; ?>
</body>
</html>
