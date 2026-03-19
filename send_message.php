<?php
require_once "db.php";
require_once "auth.php";

header("Content-Type: application/json; charset=utf-8");

function jexit(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_logged_in()) {
  jexit(["ok" => false, "error" => "Трябва да си влязъл в акаунт, за да пишеш."], 401);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
  jexit(["ok" => false, "error" => "Невалиден метод."], 405);
}

$fromId = (int)($_SESSION["user"]["id"] ?? 0);
$toId   = (int)($_POST["to_user_id"] ?? 0);

$body = trim((string)($_POST["body"] ?? ""));

if ($fromId <= 0) jexit(["ok" => false, "error" => "Липсва потребител."], 401);
if ($toId <= 0)   jexit(["ok" => false, "error" => "Липсва получател."], 400);
if ($fromId === $toId) jexit(["ok" => false, "error" => "Не можеш да пишеш на себе си."], 400);

if ($body === "") jexit(["ok" => false, "error" => "Съобщението е празно."], 400);
if (mb_strlen($body) > 2000) jexit(["ok" => false, "error" => "Съобщението е твърде дълго."], 400);

// validate recipient exists
$st = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$st->execute([$toId]);
if (!$st->fetchColumn()) {
  jexit(["ok" => false, "error" => "Потребителят не съществува."], 404);
}

/* ============================
   ✅ REF CONTEXT (campaign / ad / direct)
============================ */

// incoming
$refTypeIn = strtolower(trim((string)($_POST["ref_type"] ?? "")));

// usedbook_id (ползва се за usedbook/usedtextbook/giftbook)
$bookIdRaw = $_POST["usedbook_id"] ?? null;
$bookId = ($bookIdRaw === null || $bookIdRaw === "" ? null : (int)$bookIdRaw);

// campaign_id (новото)
$campIdRaw = $_POST["campaign_id"] ?? null;
$campaignId = ($campIdRaw === null || $campIdRaw === "" ? null : (int)$campIdRaw);

// normalize negatives to null
if ($bookId !== null && $bookId <= 0) $bookId = null;
if ($campaignId !== null && $campaignId <= 0) $campaignId = null;

/*
  ✅ Source of truth:
  - ако има campaign_id → ref_type='campaign' и usedbook_id=NULL
  - иначе ако има usedbook_id → ref_type in {usedbook, usedtextbook, giftbook}
  - иначе → direct (и двете NULL)
*/
$refType = "direct";

if ($campaignId !== null) {
  $refType = "campaign";
  $bookId = null;

  // validate campaign exists
  $stC = $pdo->prepare("SELECT id FROM campaigns WHERE id=? LIMIT 1");
  $stC->execute([$campaignId]);
  if (!$stC->fetchColumn()) {
    jexit(["ok" => false, "error" => "Кампанията не съществува."], 404);
  }

} elseif ($bookId !== null) {
  $allowedTypes = ["usedbook", "usedtextbook", "giftbook"];
  $refType = in_array($refTypeIn, $allowedTypes, true) ? $refTypeIn : "usedbook";

  // validate ad exists depending on ref_type
  if ($refType === "giftbook") {
    $stB = $pdo->prepare("SELECT id FROM gift_books WHERE id = ? LIMIT 1");
  } else {
    $stB = $pdo->prepare("SELECT id FROM used_books WHERE id = ? LIMIT 1");
  }
  $stB->execute([$bookId]);
  if (!$stB->fetchColumn()) {
    jexit(["ok" => false, "error" => "Обявата не съществува."], 404);
  }
} else {
  // direct chat (no ref)
  $refType = "direct";
}

try {
  $stI = $pdo->prepare("
    INSERT INTO messages (from_user_id, to_user_id, ref_type, usedbook_id, campaign_id, body, created_at, read_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)
  ");
  $stI->execute([
    $fromId,
    $toId,
    $refType,
    $bookId,       // null when not used
    $campaignId,   // null when not used
    $body
  ]);

  jexit(["ok" => true]);
} catch (Throwable $ex) {
  jexit(["ok" => false, "error" => "Грешка при запис: ".$ex->getMessage()], 500);
}
