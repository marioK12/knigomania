<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* =========================
   Basic session auth
========================= */
function is_logged_in(): bool {
  return !empty($_SESSION["user"]);
}

function current_user(): ?array {
  return $_SESSION["user"] ?? null;
}

function require_login(): void {
  if (!is_logged_in()) {
    $next = $_SERVER["REQUEST_URI"] ?? "";
    if ($next !== "") header("Location: login.php?next=" . urlencode($next));
    else header("Location: login.php");
    exit;
  }
}

/* =========================
   Helpers (UA/IP/Device)
========================= */
function km_ip(): string {
  return substr((string)($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
}

function km_ua(): string {
  return substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);
}

function km_device_label(): string {
  $ua = km_ua();

  $os = "Unknown OS";
  if (stripos($ua, "Windows") !== false) $os = "Windows";
  elseif (stripos($ua, "Mac OS") !== false || stripos($ua, "Macintosh") !== false) $os = "macOS";
  elseif (stripos($ua, "Android") !== false) $os = "Android";
  elseif (stripos($ua, "iPhone") !== false || stripos($ua, "iPad") !== false) $os = "iOS";
  elseif (stripos($ua, "Linux") !== false) $os = "Linux";

  $br = "Browser";
  if (stripos($ua, "Edg/") !== false) $br = "Edge";
  elseif (stripos($ua, "Chrome/") !== false) $br = "Chrome";
  elseif (stripos($ua, "Firefox/") !== false) $br = "Firefox";
  elseif (stripos($ua, "Safari/") !== false && stripos($ua, "Chrome/") === false) $br = "Safari";

  return "{$br} on {$os}";
}

/* =========================
   Cookie helpers
   Cookie: KM_SESS = selector:token
========================= */
function km_set_session_cookie(string $selector, string $token, int $expTs): void {
  setcookie("KM_SESS", $selector . ":" . $token, [
    "expires"  => $expTs,
    "path"     => "/",
    "secure"   => !empty($_SERVER["HTTPS"]),
    "httponly" => true,
    "samesite" => "Lax",
  ]);
}

function km_clear_session_cookie(): void {
  setcookie("KM_SESS", "", [
    "expires"  => time() - 3600,
    "path"     => "/",
    "secure"   => !empty($_SERVER["HTTPS"]),
    "httponly" => true,
    "samesite" => "Lax",
  ]);
}

function km_get_cookie_parts(): ?array {
  if (empty($_COOKIE["KM_SESS"])) return null;
  $raw = (string)$_COOKIE["KM_SESS"];
  if (strpos($raw, ":") === false) return null;
  [$selector, $token] = explode(":", $raw, 2);
  $selector = trim($selector);
  $token = trim($token);
  if ($selector === "" || $token === "") return null;
  return [$selector, $token];
}

/* =========================
   Security / hygiene
========================= */
function km_cleanup_sessions(PDO $pdo): void {
  $pdo->exec("DELETE FROM user_sessions WHERE expires_at < NOW()");
}

function km_limit_user_sessions(PDO $pdo, int $userId, int $max = 10): void {
  $max = max(1, $max);
  $st = $pdo->prepare("
    SELECT id
    FROM user_sessions
    WHERE user_id=?
    ORDER BY last_used_at DESC, created_at DESC, id DESC
  ");
  $st->execute([$userId]);
  $ids = $st->fetchAll(PDO::FETCH_COLUMN);

  if (count($ids) <= $max) return;

  $toDelete = array_slice($ids, $max);
  $in = implode(",", array_fill(0, count($toDelete), "?"));
  $params = array_map("intval", $toDelete);
  $pdo->prepare("DELETE FROM user_sessions WHERE id IN ($in)")->execute($params);
}

/* =========================
   Remember me (device session) + rotation
========================= */
function remember_me(PDO $pdo, int $userId, int $days = 30): void {
  km_cleanup_sessions($pdo);

  $selector = bin2hex(random_bytes(12));
  $token    = bin2hex(random_bytes(32));
  $hash     = hash("sha256", $token);

  $expTs = time() + $days * 86400;
  $expDb = date("Y-m-d H:i:s", $expTs);

  $st = $pdo->prepare("
    INSERT INTO user_sessions
      (user_id, selector, token_hash, expires_at, device_label, user_agent, ip_address)
    VALUES
      (?, ?, ?, ?, ?, ?, ?)
  ");
  $st->execute([
    $userId,
    $selector,
    $hash,
    $expDb,
    km_device_label(),
    km_ua(),
    km_ip(),
  ]);

  km_limit_user_sessions($pdo, $userId, 10);
  km_set_session_cookie($selector, $token, $expTs);
}

function auto_login(PDO $pdo): void {
  if (is_logged_in()) return;

  $parts = km_get_cookie_parts();
  if (!$parts) { km_clear_session_cookie(); return; }
  [$selector, $token] = $parts;

  km_cleanup_sessions($pdo);

  $st = $pdo->prepare("
    SELECT s.id AS sid, s.user_id, s.token_hash,
           u.id, u.name, u.email, u.avatar
    FROM user_sessions s
    JOIN users u ON u.id = s.user_id
    WHERE s.selector = ?
      AND s.expires_at > NOW()
    LIMIT 1
  ");
  $st->execute([$selector]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) { km_clear_session_cookie(); return; }

  $calc = hash("sha256", $token);
  if (!hash_equals((string)$row["token_hash"], $calc)) {
    $pdo->prepare("DELETE FROM user_sessions WHERE selector=?")->execute([$selector]);
    km_clear_session_cookie();
    return;
  }

  $_SESSION["user"] = [
    "id"     => (int)$row["id"],
    "name"   => $row["name"],
    "email"  => $row["email"],
    "avatar" => $row["avatar"] ?? null
  ];

  // ✅ rotate token + sliding expiration
  $newToken = bin2hex(random_bytes(32));
  $newHash  = hash("sha256", $newToken);

  $days = 30;
  $expTs = time() + $days * 86400;
  $expDb = date("Y-m-d H:i:s", $expTs);

  $pdo->prepare("
    UPDATE user_sessions
    SET token_hash=?, expires_at=?, last_used_at=NOW(),
        ip_address=?, user_agent=?, device_label=?
    WHERE id=?
  ")->execute([
    $newHash,
    $expDb,
    km_ip(),
    km_ua(),
    km_device_label(),
    (int)$row["sid"]
  ]);

  km_set_session_cookie($selector, $newToken, $expTs);
}

/* =========================
   Logout
========================= */
function logout_user(PDO $pdo): void {
  $parts = km_get_cookie_parts();
  if ($parts) {
    [$selector, $token] = $parts;
    $pdo->prepare("DELETE FROM user_sessions WHERE selector=?")->execute([$selector]);
  }

  km_clear_session_cookie();

  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", [
      "expires"  => time() - 3600,
      "path"     => $p["path"],
      "domain"   => $p["domain"],
      "secure"   => $p["secure"],
      "httponly" => $p["httponly"],
      "samesite" => "Lax",
    ]);
  }
  session_destroy();
}

function logout_all_devices(PDO $pdo, int $userId): void {
  $pdo->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([$userId]);
  km_clear_session_cookie();
}

/* =========================
   Session manager helpers
========================= */
function list_my_sessions(PDO $pdo, int $userId): array {
  $st = $pdo->prepare("
    SELECT id, selector, device_label, ip_address, user_agent, created_at, last_used_at, expires_at
    FROM user_sessions
    WHERE user_id=?
    ORDER BY last_used_at DESC, created_at DESC, id DESC
  ");
  $st->execute([$userId]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function revoke_session(PDO $pdo, int $userId, int $sessionId): bool {
  $st = $pdo->prepare("DELETE FROM user_sessions WHERE id=? AND user_id=?");
  $st->execute([$sessionId, $userId]);
  return $st->rowCount() > 0;
}

/* =========================
   Password reset helpers
========================= */
function km_hash_code(string $code): string {
  return hash("sha256", $code);
}

function km_random_code6(): string {
  return str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
}

/* =========================
   Auto-login on include
========================= */
if (isset($pdo) && $pdo instanceof PDO) {
  auto_login($pdo);
}
