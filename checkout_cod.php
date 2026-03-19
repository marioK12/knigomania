<?php
require_once "db.php";
require_once "auth.php";

header("Content-Type: application/json; charset=utf-8");

function jexit($arr, int $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function postv($k){ return trim((string)($_POST[$k] ?? "")); }

/* =========================
   PHPMailer include (leave ONLY ONE)
========================= */
// ✅ B) Manual
require_once __DIR__ . "/PHPMailer/Exception.php";
require_once __DIR__ . "/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   SMTP CONFIG (ПОПЪЛНИ ТВОЕТО)
========================= */
$SMTP_HOST = "smtp.gmail.com";
$SMTP_USER = "mariokanturski187@gmail.com";
$SMTP_PASS = "pcuj sxxl zzmm qtrm";
$SMTP_PORT = 587;
$SMTP_SEC  = "tls";

$FROM_EMAIL  = $SMTP_USER;
$FROM_NAME   = "KnigoMania";
$ADMIN_EMAIL = "mariokanturski187@gmail.com";

function send_mail_smtp($to, $toName, $subject, $body, $replyTo="", $replyToName=""){
  global $SMTP_HOST,$SMTP_USER,$SMTP_PASS,$SMTP_PORT,$SMTP_SEC,$FROM_EMAIL,$FROM_NAME;

  try{
    $mail = new PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->isSMTP();
    $mail->Host = $SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = $SMTP_USER;
    $mail->Password = $SMTP_PASS;
    $mail->SMTPSecure = $SMTP_SEC;
    $mail->Port = $SMTP_PORT;

    $mail->setFrom($FROM_EMAIL, $FROM_NAME);
    $mail->addAddress($to, $toName ?: $to);

    if ($replyTo !== "") $mail->addReplyTo($replyTo, $replyToName ?: $replyTo);

    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();
    return ["ok"=>true];
  }catch(Exception $e){
    return ["ok"=>false, "err"=>$e->getMessage()];
  }
}

/* =========================
   MAIN
========================= */
if (!is_logged_in()) jexit(["ok"=>false, "err"=>"Трябва да си влязъл."], 401);

$me = current_user();
$meId = (int)($me["id"] ?? 0);

$shipName  = postv("ship_name");
$shipPhone = postv("ship_phone");
$shipAddr  = postv("ship_addr");
$shipEmail = postv("ship_email");
if ($shipEmail === "" || !filter_var($shipEmail, FILTER_VALIDATE_EMAIL)) {
  jexit(["ok"=>false,"err"=>"Невалиден имейл."], 400);
}

$note      = postv("note");

if ($shipName === "" || $shipPhone === "" || $shipAddr === "") {
  jexit(["ok"=>false,"err"=>"Попълни име, телефон и адрес."], 400);
}

// Load cart
$st = $pdo->prepare("
  SELECT id, item_type, item_id, title, unit_price, qty
  FROM cart_items
  WHERE user_id=?
  ORDER BY updated_at DESC, id DESC
");
$st->execute([$meId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$items) jexit(["ok"=>false,"err"=>"Количката е празна."], 400);

$itemsCount = 0;
$subtotal = 0.0;

foreach($items as $it){
  $q = max(1, (int)$it["qty"]);
  $p = (float)$it["unit_price"];
  $itemsCount += $q;
  $subtotal += $p * $q;
}
$shipping = 0.0;
$grand = $subtotal + $shipping;

try{
  $pdo->beginTransaction();

  // 1 order header (ако искаш отделна таблица order_items – казваш и го правим)
  $ins = $pdo->prepare("
    INSERT INTO orders (buyer_id, seller_id, item_type, item_id, item_title, qty, unit_price, total_price, status,
                        created_at, updated_at)
    VALUES (:b, 0, 'cart', 0, 'Cart Checkout', :q, 0, :tot, 'pending', NOW(), NOW())
  ");
  $ins->execute([":b"=>$meId, ":q"=>$itemsCount, ":tot"=>$grand]);
  $orderId = (int)$pdo->lastInsertId();

  // clear cart
  $pdo->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$meId]);

  $pdo->commit();

}catch(Exception $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(["ok"=>false, "err"=>"DB грешка: ".$e->getMessage()], 500);
}

// Build email body
$buyerEmail = trim((string)($me["email"] ?? ""));
$buyerName  = trim((string)($me["name"] ?? ""));

$lines = [];
$lines[] = "Нова поръчка от количка (Наложен платеж)";
$lines[] = "";
$lines[] = "Поръчка: #{$orderId}";
$displayName = $buyerName !== "" ? $buyerName : $shipName;
$lines[] = "Купувач: {$displayName} ({$shipEmail})";
$lines[] = "Профил ID: {$meId}";
$lines[] = "Име за доставка: {$shipName}";
$lines[] = "Телефон: {$shipPhone}";
$lines[] = "Адрес: {$shipAddr}";
if ($note !== "") $lines[] = "Бележка: {$note}";
$lines[] = "";
$lines[] = "Артикули:";
foreach($items as $it){
  $typeLabel = ($it["item_type"] === "textbook") ? "Учебник" : "Книга";
  $q = max(1, (int)$it["qty"]);
  $u = (float)$it["unit_price"];
  $t = (string)$it["title"];
  $lines[] = "- {$typeLabel}: {$t} (ID {$it["item_id"]}) | {$q} × ".number_format($u,2,'.','')." = ".number_format($u*$q,2,'.','')." лв";
}
$lines[] = "";
$lines[] = "Междинна сума: ".number_format($subtotal,2,'.','')." лв";
$lines[] = "Доставка: ".number_format($shipping,2,'.','')." лв";
$lines[] = "Общо: ".number_format($grand,2,'.','')." лв";
$lines[] = "";
$lines[] = "Дата: ".date("Y-m-d H:i:s");

$subject = "Нова поръчка #{$orderId} (Checkout)";
$body = implode("\n", $lines);

// Send
$send = send_mail_smtp($ADMIN_EMAIL, "Orders", $subject, $body, $shipEmail, $buyerName ?: $shipName);


if (!$send["ok"]) {
  jexit(["ok"=>true, "order_id"=>$orderId, "redirect"=>$base."index.php"]);

}

jexit(["ok"=>true, "order_id"=>$orderId]);
