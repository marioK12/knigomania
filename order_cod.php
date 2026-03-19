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
function posti($k, $def=0){ return (int)($_POST[$k] ?? $def); }
function postf($k, $def=0.0){
  $v = str_replace(",", ".", trim((string)($_POST[$k] ?? "")));
  $n = (float)$v;
  return is_finite($n) ? $n : (float)$def;
}

/* =========================
   PHPMailer include (leave ONLY ONE)
========================= */

// ✅ A) Composer
// require_once __DIR__ . "/vendor/autoload.php";

// ✅ B) Manual
require_once __DIR__ . "/PHPMailer/Exception.php";
require_once __DIR__ . "/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   SMTP CONFIG
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

$type = postv("type");          // book | textbook
$itemId   = posti("id");
$addQty   = max(1, posti("qty", 1));   // колко добавяме (обикновено 1)

$title = postv("title");        // from JS
$unit  = postf("unit_price");   // from JS

$shipName  = postv("ship_name");
$shipPhone = postv("ship_phone");
$shipAddr  = postv("ship_addr");
$note      = postv("note");

if (!in_array($type, ["book","textbook"], true)) jexit(["ok"=>false,"err"=>"Bad type."], 400);
if ($itemId <= 0) jexit(["ok"=>false,"err"=>"Bad id."], 400);

if ($title === "") jexit(["ok"=>false,"err"=>"Missing title."], 400);
if ($unit <= 0) jexit(["ok"=>false,"err"=>"Bad price."], 400);

if ($shipName === "" || $shipPhone === "" || $shipAddr === "") {
  jexit(["ok"=>false,"err"=>"Попълни име, телефон и адрес."], 400);
}

// max qty rule
$MAX_QTY = 10;

try{
  $pdo->beginTransaction();

  // 1) Ако има вече pending за същия артикул -> увеличаваме qty (до 10)
  $st = $pdo->prepare("
    SELECT id, qty
    FROM orders
    WHERE buyer_id = ?
      AND item_type = ?
      AND item_id = ?
      AND status = 'pending'
    ORDER BY id DESC
    LIMIT 1
    FOR UPDATE
  ");
  $st->execute([$meId, $type, $itemId]);
  $existing = $st->fetch(PDO::FETCH_ASSOC);

  $orderId = 0;
  $finalQty = 0;
  $capped = false;

  if ($existing){
    $orderId = (int)$existing["id"];
    $oldQty  = (int)$existing["qty"];

    $newQty = $oldQty + $addQty;
    if ($newQty > $MAX_QTY){
      $newQty = $MAX_QTY;
      $capped = true;
    }

    $finalQty = $newQty;
    $total = $unit * $finalQty;

    $up = $pdo->prepare("
      UPDATE orders
      SET item_title = :title,
          qty = :q,
          unit_price = :u,
          total_price = :t
      WHERE id = :id AND buyer_id = :b
    ");
    $up->execute([
      ":title"=>$title,
      ":q"=>$finalQty,
      ":u"=>$unit,
      ":t"=>$total,
      ":id"=>$orderId,
      ":b"=>$meId
    ]);

  } else {
    // 2) Няма -> правим нов ред (qty до 10)
    $finalQty = min($MAX_QTY, $addQty);
    $capped = ($addQty > $MAX_QTY);

    $total = $unit * $finalQty;

    // seller_id неизвестен
    $sellerId = 0;

    $ins = $pdo->prepare("
      INSERT INTO orders (buyer_id, seller_id, item_type, item_id, item_title, qty, unit_price, total_price, status)
      VALUES (:b,:s,:t,:i,:title,:q,:u,:tot,'pending')
    ");
    $ins->execute([
      ":b"=>$meId,
      ":s"=>$sellerId,
      ":t"=>$type,
      ":i"=>$itemId,
      ":title"=>$title,
      ":q"=>$finalQty,
      ":u"=>$unit,
      ":tot"=>$total
    ]);
    $orderId = (int)$pdo->lastInsertId();
  }

  $pdo->commit();

} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(["ok"=>false, "err"=>"DB error: ".$e->getMessage()], 500);
}

// Email (по желание можеш да го пращаш само при first insert,
// но оставям както беше – ще получаваш при всяко добавяне)
$subject = "Нова поръчка (Наложен платеж) #{$orderId}";
$body =
"Нова поръчка (Наложен платеж)\n\n".
"Поръчка: #{$orderId}\n".
"Тип: {$type}\n".
"Артикул: {$title} (ID {$itemId})\n".
"Количество: {$finalQty}\n".
"Ед. цена: ".number_format($unit, 2, ".", "")." лв.\n".
"Общо: ".number_format($unit*$finalQty, 2, ".", "")." лв.\n\n".
"Купувач user_id: {$meId}\n".
"Доставка:\n".
" - Име: {$shipName}\n".
" - Телефон: {$shipPhone}\n".
" - Адрес/офис: {$shipAddr}\n".
" - Бележка: {$note}\n\n".
"Дата: ".date("Y-m-d H:i:s")."\n";

$buyerEmail = trim((string)($me["email"] ?? ""));
$buyerName  = trim((string)($me["name"] ?? ""));

$send = send_mail_smtp($ADMIN_EMAIL, "Orders", $subject, $body, $buyerEmail, $buyerName);

$out = ["ok"=>true, "order_id"=>$orderId, "status"=>"pending", "qty"=>$finalQty];
if ($capped) $out["warn"] = "Достигнат е максимумът от 10 броя за този артикул.";
if (!$send["ok"]) $out["mail_warn"] = $send["err"];

jexit($out);
