<?php
require_once "db.php";
require_once "auth.php";
require_login();

$page_title = "Моят профил";

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$user   = current_user();
$userId = (int)($user["id"] ?? 0);

function csrf_token(): string {
  if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
  }
  return $_SESSION["csrf"];
}

function csrf_verify(string $token): bool {
  return isset($_SESSION["csrf"])
    && is_string($token)
    && hash_equals($_SESSION["csrf"], $token);
}

function e($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function postv(string $k): string {
  return trim((string)($_POST[$k] ?? ""));
}

function safe_unlink(string $path): void {
  if ($path !== "" && is_file($path)) {
    @unlink($path);
  }
}

function initials(?string $name, ?string $email): string {
  $s = trim((string)($name ?: ""));
  if ($s === "") $s = trim((string)$email);
  if ($s === "") return "U";

  $parts = preg_split('/\s+/', $s);
  $a = mb_substr($parts[0], 0, 1, "UTF-8");
  $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1, "UTF-8") : "";

  $out = mb_strtoupper($a . $b, "UTF-8");
  return $out !== "" ? $out : "U";
}

/* =========================
   Flash messages (PRG)
========================= */
$flash_success = $_SESSION["flash_success"] ?? "";
$flash_error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

/* =========================
   Avatar paths
========================= */
$AVATAR_DIR_FS  = rtrim(__DIR__, "/\\") . DIRECTORY_SEPARATOR . "usersIMG" . DIRECTORY_SEPARATOR;
$base           = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base           = ($base === "" || $base === ".") ? "/" : ($base . "/");
$AVATAR_DIR_URL = $base . "usersIMG/";

/* Ensure avatar folder exists */
if (!is_dir($AVATAR_DIR_FS)) {
  @mkdir($AVATAR_DIR_FS, 0775, true);
}

/* =========================
   Fetch user
========================= */
$st = $pdo->prepare("SELECT name, email, avatar, pass_hash FROM users WHERE id = ?");
$st->execute([$userId]);
$dbUser = $st->fetch(PDO::FETCH_ASSOC);

if (!$dbUser) {
  header("Location: logout.php");
  exit;
}

/* =========================
   Sessions / device manager
========================= */
$sessions = [];
$currentSelector = "";

if (!empty($_COOKIE["KM_SESS"]) && strpos((string)$_COOKIE["KM_SESS"], ":") !== false) {
  [$sel] = explode(":", (string)$_COOKIE["KM_SESS"], 2);
  $currentSelector = trim((string)$sel);
}

if (function_exists("list_my_sessions")) {
  $sessions = list_my_sessions($pdo, $userId);
}

/* =========================
   Mail adapter
========================= */
require_once "mailer.php";

function km_send(string $to, string $name, string $subject, string $html): bool {
  if (function_exists("km_send_mail")) {
    return (bool)km_send_mail($to, $name, $subject, $html);
  }
  if (function_exists("send_mail")) {
    return (bool)send_mail($to, $name, $subject, $html);
  }
  if (function_exists("sendMail")) {
    return (bool)sendMail($to, $name, $subject, $html);
  }
  return false;
}

/* =========================
   Code helpers
========================= */
function km_make_code6(): string {
  if (function_exists("km_random_code6")) return (string)km_random_code6();
  return (string)random_int(100000, 999999);
}

function km_make_code_hash(string $code): string {
  if (function_exists("km_hash_code")) return (string)km_hash_code($code);
  return hash("sha256", $code);
}

function km_verify_code(string $code, string $storedHash): bool {
  if (function_exists("km_hash_code")) {
    if (preg_match('/^\$2y\$|\$argon2id\$|\$argon2i\$|\$2a\$|\$2b\$/', $storedHash)) {
      return password_verify($code, $storedHash);
    }
    return hash_equals($storedHash, hash("sha256", $code));
  }
  return hash_equals($storedHash, hash("sha256", $code));
}

/* =========================
   Avatar GD helpers
========================= */
function load_image_gd(string $tmp, string $mime) {
  if (!function_exists("imagecreatetruecolor")) {
    return false;
  }

  return match ($mime) {
    "image/jpeg" => function_exists("imagecreatefromjpeg") ? @imagecreatefromjpeg($tmp) : false,
    "image/png"  => function_exists("imagecreatefrompng")  ? @imagecreatefrompng($tmp)  : false,
    "image/webp" => function_exists("imagecreatefromwebp") ? @imagecreatefromwebp($tmp) : false,
    default      => false
  };
}

function save_avatar_256(string $tmp, string $destFs, string $mime): bool {
  $src = load_image_gd($tmp, $mime);
  if (!$src) return false;

  $w = imagesx($src);
  $h = imagesy($src);

  if ($w <= 0 || $h <= 0) {
    imagedestroy($src);
    return false;
  }

  $side = min($w, $h);
  $sx = (int)(($w - $side) / 2);
  $sy = (int)(($h - $side) / 2);

  $dstSize = 256;
  $dst = imagecreatetruecolor($dstSize, $dstSize);

  if ($mime === "image/png" || $mime === "image/webp") {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstSize, $dstSize, $transparent);
  }

  $ok = imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dstSize, $dstSize, $side, $side);
  imagedestroy($src);

  if (!$ok) {
    imagedestroy($dst);
    return false;
  }

  $saved = false;

  if ($mime === "image/jpeg") {
    $saved = function_exists("imagejpeg") ? imagejpeg($dst, $destFs, 88) : false;
  } elseif ($mime === "image/png") {
    $saved = function_exists("imagepng") ? imagepng($dst, $destFs, 6) : false;
  } elseif ($mime === "image/webp") {
    $saved = function_exists("imagewebp") ? imagewebp($dst, $destFs, 85) : false;
  }

  imagedestroy($dst);
  return (bool)$saved;
}

/* =========================
   Handle POST (PRG)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $success = "";
  $error   = "";
  $action  = postv("action");

  try {
    if (!csrf_verify($_POST["csrf"] ?? "")) {
      throw new RuntimeException("CSRF");
    }

    /* ---------- PROFILE ---------- */
    if ($action === "profile") {
      $name    = postv("name");
      $confirm = postv("confirm_password");

      if (mb_strlen($name, "UTF-8") < 2) {
        $error = "Името трябва да е поне 2 символа.";
      } elseif ($confirm === "") {
        $error = "Въведи парола за потвърждение.";
      } else {
        $st = $pdo->prepare("SELECT pass_hash FROM users WHERE id = ?");
        $st->execute([$userId]);
        $hash = $st->fetchColumn();

        if (!$hash || !password_verify($confirm, (string)$hash)) {
          $error = "Грешна парола.";
        } else {
          $st = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
          $st->execute([$name, $userId]);

          $_SESSION["user"]["name"] = $name;
          $success = "Промените са запазени успешно.";
        }
      }
    }

    /* ---------- PASSWORD: SEND CODE ---------- */
    elseif ($action === "pw_send_code") {
      $st = $pdo->prepare("
        SELECT id
        FROM password_resets
        WHERE user_id = ?
          AND used_at IS NULL
          AND expires_at > NOW()
          AND last_sent_at > (NOW() - INTERVAL 60 SECOND)
        ORDER BY id DESC
        LIMIT 1
      ");
      $st->execute([$userId]);

      if ($st->fetchColumn()) {
        $error = "Изчакай малко преди да поискаш нов код.";
      } else {
        $code     = km_make_code6();
        $codeHash = km_make_code_hash($code);

        $st = $pdo->prepare("
          INSERT INTO password_resets
            (user_id, code_hash, expires_at, created_at, attempts, last_sent_at, ip, user_agent)
          VALUES
            (?, ?, (NOW() + INTERVAL 10 MINUTE), NOW(), 0, NOW(), ?, ?)
        ");
        $st->execute([
          $userId,
          $codeHash,
          $_SERVER["REMOTE_ADDR"] ?? null,
          substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255),
        ]);

        $to   = (string)$dbUser["email"];
        $name = (string)($dbUser["name"] ?? "");

        $subject = "КнигоМания — код за смяна на парола";
        $html = "
          <div style='font-family:Arial,sans-serif;line-height:1.5'>
            <h2 style='margin:0 0 10px'>Код за смяна на парола</h2>
            <p>Твоят код е:</p>
            <p style='font-size:22px;font-weight:700;letter-spacing:2px;margin:8px 0'>{$code}</p>
            <p>Кодът е валиден 10 минути.</p>
            <p style='color:#666;font-size:12px'>Ако не си го поискал, игнорирай този имейл.</p>
          </div>
        ";

        if (!km_send($to, $name, $subject, $html)) {
          $error = "Не успях да изпратя имейл. Провери mailer.php.";
        } else {
          $success = "Изпратихме код на имейла ти.";
        }
      }
    }

    /* ---------- PASSWORD: CHANGE ---------- */
    elseif ($action === "pw_change") {
      $code = postv("code");
      $new1 = postv("new_password");
      $new2 = postv("new_password2");

      if (!preg_match('/^\d{6}$/', $code)) {
        $error = "Въведи валиден 6-цифрен код.";
      } elseif ($new1 === "" || $new2 === "") {
        $error = "Попълни всички полета.";
      } elseif ($new1 !== $new2) {
        $error = "Новите пароли не съвпадат.";
      } elseif (strlen($new1) < 6) {
        $error = "Паролата трябва да е поне 6 символа.";
      } else {
        $st = $pdo->prepare("
          SELECT id, code_hash, attempts
          FROM password_resets
          WHERE user_id = ?
            AND used_at IS NULL
            AND expires_at > NOW()
          ORDER BY id DESC
          LIMIT 1
        ");
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
          $error = "Няма активен код или кодът е изтекъл.";
        } else {
          $attempts = (int)($row["attempts"] ?? 0);

          if ($attempts >= 5) {
            $error = "Твърде много грешни опити. Поискай нов код.";
          } else {
            $ok = km_verify_code($code, (string)$row["code_hash"]);

            if (!$ok) {
              $st = $pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?");
              $st->execute([(int)$row["id"]]);
              $error = "Грешен код или кодът е изтекъл.";
            } else {
              $pdo->beginTransaction();

              try {
                $newHash = password_hash($new1, PASSWORD_DEFAULT);

                $st = $pdo->prepare("UPDATE users SET pass_hash = ? WHERE id = ?");
                $st->execute([$newHash, $userId]);

                $st = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                $st->execute([(int)$row["id"]]);

                if (function_exists("logout_all_devices")) {
                  logout_all_devices($pdo, $userId);
                }

                session_regenerate_id(true);
                $pdo->commit();
                $success = "Паролата е сменена успешно.";
              } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                  $pdo->rollBack();
                }
                $error = "Възникна грешка при смяна на парола.";
              }
            }
          }
        }
      }
    }

    /* ---------- AVATAR UPLOAD ---------- */
    elseif ($action === "avatar") {
      if (empty($_FILES["avatar"]) || !is_array($_FILES["avatar"])) {
        $error = "Няма избран файл.";
      } else {
        $f = $_FILES["avatar"];

        if (($f["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
          $error = "Грешка при качване. Код: " . (int)($f["error"] ?? -1);
        } else {
          $maxBytes = 2 * 1024 * 1024; // 2MB

          if (($f["size"] ?? 0) > $maxBytes) {
            $error = "Файлът е твърде голям. Максимум 2MB.";
          } else {
            $tmp = (string)$f["tmp_name"];

            if (!is_uploaded_file($tmp)) {
              $error = "Невалиден качен файл.";
            } else {
              $info = @getimagesize($tmp);

              if (!$info) {
                $error = "Файлът не е валидно изображение.";
              } else {
                $mime = (string)($info["mime"] ?? "");
                $allowed = [
                  "image/jpeg" => "jpg",
                  "image/png"  => "png",
                  "image/webp" => "webp",
                ];

                if (!isset($allowed[$mime])) {
                  $error = "Позволени са само JPG, PNG или WEBP.";
                } else {
                  $ext    = $allowed[$mime];
                  $newName = "u{$userId}_" . bin2hex(random_bytes(8)) . "." . $ext;
                  $destFs = $AVATAR_DIR_FS . $newName;

                  if (!is_dir($AVATAR_DIR_FS)) {
                    $error = "Папката usersIMG липсва.";
                  } elseif (!is_writable($AVATAR_DIR_FS)) {
                    $error = "Папката usersIMG няма права за запис.";
                  } elseif (!function_exists("imagecreatetruecolor")) {
                    $error = "GD не е включен в PHP.";
                  } elseif (!save_avatar_256($tmp, $destFs, $mime)) {
                    $error = "Не успях да обработя аватара. Провери GD/WEBP поддръжката.";
                  } else {
                    if (!empty($dbUser["avatar"])) {
                      safe_unlink($AVATAR_DIR_FS . (string)$dbUser["avatar"]);
                    }

                    $st = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $st->execute([$newName, $userId]);

                    $_SESSION["user"]["avatar"] = $newName;
                    $success = "Аватарът е обновен успешно.";
                  }
                }
              }
            }
          }
        }
      }
    }

    /* ---------- AVATAR REMOVE ---------- */
    elseif ($action === "avatar_remove") {
      if (!empty($dbUser["avatar"])) {
        safe_unlink($AVATAR_DIR_FS . (string)$dbUser["avatar"]);
      }

      $st = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
      $st->execute([$userId]);

      $_SESSION["user"]["avatar"] = null;
      $success = "Аватарът е премахнат.";
    }

    /* ---------- SESSION REVOKE ---------- */
    elseif ($action === "session_revoke") {
      $sid = (int)postv("session_id");

      if ($sid <= 0) {
        $error = "Невалидна сесия.";
      } else {
        $ok = function_exists("revoke_session") ? revoke_session($pdo, $userId, $sid) : false;
        $success = $ok ? "Сесията е премахната." : "Сесията вече не съществува.";
      }
    }

    /* ---------- LOGOUT ALL ---------- */
    elseif ($action === "session_logout_all") {
      if (function_exists("logout_all_devices")) {
        logout_all_devices($pdo, $userId);
      }
      if (function_exists("logout_user")) {
        logout_user($pdo);
      }

      $_SESSION["flash_success"] = "Излезе от всички устройства.";
      header("Location: login.php");
      exit;
    }

    else {
      $error = "Невалидно действие.";
    }

  } catch (Throwable $e) {
    if (($e instanceof RuntimeException) && $e->getMessage() === "CSRF") {
      $error = "Невалидна заявка (CSRF). Презареди страницата и опитай пак.";
    } else {
      $error = "Грешка: " . $e->getMessage();
    }
  }

  $_SESSION["flash_success"] = $success;
  $_SESSION["flash_error"]   = $error;

  header("Location: settings.php");
  exit;
}

/* =========================
   View vars
========================= */
$avatarFilename = $dbUser["avatar"] ?? null;
$avatarUrl = (!empty($avatarFilename)) ? ($AVATAR_DIR_URL . $avatarFilename) : "";
$ini = initials($dbUser["name"] ?? null, $dbUser["email"] ?? null);
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">

<link rel="stylesheet" href="settings.css">

<?php require_once "nav.php"; ?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100">

  <?php if ($flash_success): ?>
    <div class="toast align-items-center text-bg-success border-0"
         role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-delay="3500">
      <div class="d-flex">
        <div class="toast-body">✅ <?= e($flash_success) ?></div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"
                aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($flash_error): ?>
    <div class="toast align-items-center text-bg-danger border-0"
         role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-delay="5000">
      <div class="d-flex">
        <div class="toast-body">❌ <?= e($flash_error) ?></div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"
                aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

</div>

<main class="km-main py-4 py-md-5">
  <div class="container km-wrap">

    <section class="km-hero mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div class="text-white">
          <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="km-chip">⚙️ Settings</span>
            <span class="km-chip">🛡️ Сигурност</span>
            <span class="km-chip">🖼️ Аватар</span>
            <span class="km-chip">📱 Сесии</span>
          </div>
          <h1 class="display-6 mb-1 km-title">Настройки</h1>
          <div class="km-hero-sub">
            Логнат си като: <b><?= e($dbUser["email"]) ?></b>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <?php if ($avatarUrl): ?>
            <img class="km-avatar" src="<?= e($avatarUrl) ?>" alt="Avatar">
          <?php else: ?>
            <div class="km-avatar-fallback"><?= e($ini) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="km-card km-subtle">
          <div class="p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
              <?php if ($avatarUrl): ?>
                <img class="km-avatar" src="<?= e($avatarUrl) ?>" alt="Avatar">
              <?php else: ?>
                <div class="km-avatar-fallback"><?= e($ini) ?></div>
              <?php endif; ?>

              <div>
                <div class="fw-semibold km-name">
                  <?= e($dbUser["name"] ?: "Потребител") ?>
                </div>
                <div class="text-muted small"><?= e($dbUser["email"]) ?></div>
              </div>
            </div>

            <div class="km-divider my-3"></div>

            <div class="fw-semibold mb-2">Смяна на аватар</div>

            <form method="post" enctype="multipart/form-data" class="d-grid gap-2">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="avatar">

              <div class="km-drop">
                <input class="form-control"
                       type="file"
                       name="avatar"
                       accept="image/png,image/jpeg,image/webp"
                       required>
                <div class="form-text">JPG / PNG / WEBP до 2MB.</div>
              </div>

              <button class="btn btn-primary km-btn" type="submit">Качи</button>
            </form>

            <?php if (!empty($dbUser["avatar"])): ?>
              <form method="post" class="mt-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="avatar_remove">
                <button class="btn btn-outline-secondary km-btn w-100" type="submit">Премахни аватар</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="km-card km-subtle mb-4">
          <div class="p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h2 class="h5 mb-1">Профил</h2>
                <div class="text-muted small">Обнови основните си данни.</div>
              </div>
              <span class="badge text-bg-light">✨ Hover & focus</span>
            </div>

            <form method="post" class="row g-3">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="profile">

              <div class="col-md-6">
                <label class="form-label">Име</label>
                <input class="form-control" name="name" value="<?= e($dbUser["name"]) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Имейл</label>
                <input class="form-control" type="email" value="<?= e($dbUser["email"]) ?>" readonly>
                <div class="form-text">Имейлът не може да бъде променян.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Парола (за потвърждение)</label>
                <input class="form-control" type="password" name="confirm_password" required>
              </div>

              <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                <button class="btn btn-primary km-btn px-4" type="submit">Запази</button>
                <a class="btn btn-outline-secondary km-btn" href="settings.php">Откажи</a>
              </div>
            </form>
          </div>
        </div>

        <div class="km-card km-subtle mb-4">
          <div class="p-4 p-md-5">
            <h2 class="h5 mb-1">Смяна на парола</h2>
            <div class="text-muted small mb-3">Натисни „Изпрати код“, после въведи кода и новата парола.</div>

            <form method="post" class="mb-3">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="pw_send_code">
              <button class="btn btn-outline-primary km-btn" type="submit">Изпрати код на имейла</button>
            </form>

            <form method="post" class="row g-3">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="pw_change">

              <div class="col-md-4">
                <label class="form-label">Код</label>
                <input class="form-control" name="code" inputmode="numeric" pattern="\d{6}" placeholder="6 цифри" required>
              </div>

              <div class="col-md-4">
                <label class="form-label">Нова</label>
                <input class="form-control" type="password" name="new_password" required minlength="6">
                <div class="form-text">Минимум 6 символа.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Повтори</label>
                <input class="form-control" type="password" name="new_password2" required minlength="6">
              </div>

              <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                <button class="btn btn-warning km-btn px-4" type="submit">Смени парола</button>
                <a class="btn btn-outline-secondary km-btn" href="settings.php">Откажи</a>
              </div>
            </form>
          </div>
        </div>

        <div class="km-card km-subtle">
          <div class="p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h2 class="h5 mb-1">Устройства и сесии</h2>
                <div class="text-muted small">Управлявай устройствата, на които си логнат.</div>
              </div>

              <form method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="session_logout_all">
                <button class="btn btn-outline-danger km-btn" type="submit">Изход от всички устройства</button>
              </form>
            </div>

            <?php if (empty($sessions)): ?>
              <div class="text-muted">Няма активни сесии.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Устройство</th>
                      <th>IP</th>
                      <th>Последно</th>
                      <th>Изтича</th>
                      <th class="text-end">Действие</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sessions as $s): ?>
                      <?php
                        $sel = (string)($s["selector"] ?? "");
                        $isCurrent = ($currentSelector !== "" && $sel === $currentSelector);
                        $label = (string)($s["device_label"] ?? "");
                        $label = $label !== "" ? $label : "Устройство";
                        $last  = $s["last_used_at"] ?: $s["created_at"];
                      ?>
                      <tr>
                        <td class="fw-semibold">
                          <?= e($label) ?>
                          <?php if ($isCurrent): ?>
                            <span class="badge text-bg-success ms-2">Текущо</span>
                          <?php endif; ?>
                        </td>
                        <td><?= e((string)($s["ip_address"] ?? "-")) ?></td>
                        <td><?= e((string)$last) ?></td>
                        <td><?= e((string)($s["expires_at"] ?? "-")) ?></td>
                        <td class="text-end">
                          <?php if (!$isCurrent): ?>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="session_revoke">
                              <input type="hidden" name="session_id" value="<?= (int)($s["id"] ?? 0) ?>">
                              <button class="btn btn-sm btn-outline-danger km-btn" type="submit">Премахни</button>
                            </form>
                          <?php else: ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.toast').forEach(function (toastEl) {
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  });
});
</script>

</body>
</html>