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

if (!is_logged_in()) jexit(["ok"=>false, "err"=>"Трябва да си влязъл."], 401);
$me = current_user();
$meId = (int)($me["id"] ?? 0);

$action = $_GET["action"] ?? postv("action");
$action = $action ?: "list";

$MAX_QTY = 10;

if ($action === "count"){
  $st = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE user_id=?");
  $st->execute([$meId]);
  $n = (int)$st->fetchColumn();
  jexit(["ok"=>true, "count"=>$n]);
}

if ($action === "list"){
  $st = $pdo->prepare("
    SELECT id, item_type, item_id, title, unit_price, qty, updated_at
    FROM cart_items
    WHERE user_id=?
    ORDER BY updated_at DESC, id DESC
  ");
  $st->execute([$meId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $sub = 0.0;
  $count = 0;
  foreach($rows as $r){
    $q = max(1, (int)$r["qty"]);
    $count += $q;
    $sub += (float)$r["unit_price"] * $q;
  }
  jexit(["ok"=>true, "items"=>$rows, "count"=>$count, "subtotal"=>$sub]);
}

if ($action === "add"){
  $type = postv("type");          // book | textbook
  $itemId = posti("id");
  $addQty = max(1, posti("qty", 1));

  $title = postv("title");
  $unit  = postf("unit_price");

  if (!in_array($type, ["book","textbook"], true)) jexit(["ok"=>false,"err"=>"Bad type"], 400);
  if ($itemId <= 0) jexit(["ok"=>false,"err"=>"Bad id"], 400);
  if ($title === "") jexit(["ok"=>false,"err"=>"Missing title"], 400);
  if ($unit <= 0) jexit(["ok"=>false,"err"=>"Bad price"], 400);

  // UPSERT: увеличава qty до 10
  $st = $pdo->prepare("SELECT qty FROM cart_items WHERE user_id=? AND item_type=? AND item_id=? LIMIT 1");
  $st->execute([$meId, $type, $itemId]);
  $oldQty = $st->fetchColumn();

  if ($oldQty !== false){
    $newQty = (int)$oldQty + $addQty;
    $capped = false;
    if ($newQty > $MAX_QTY){ $newQty = $MAX_QTY; $capped = true; }

    $up = $pdo->prepare("
      UPDATE cart_items
      SET qty=?, title=?, unit_price=?
      WHERE user_id=? AND item_type=? AND item_id=?
    ");
    $up->execute([$newQty, $title, $unit, $meId, $type, $itemId]);

    jexit(["ok"=>true, "qty"=>$newQty, "capped"=>$capped]);
  }

  $qty = min($MAX_QTY, $addQty);
  $ins = $pdo->prepare("
    INSERT INTO cart_items (user_id, item_type, item_id, title, unit_price, qty)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$meId, $type, $itemId, $title, $unit, $qty]);

  jexit(["ok"=>true, "qty"=>$qty, "capped"=>($addQty > $MAX_QTY)]);
}

if ($action === "setQty"){
  $id = posti("row_id");
  $qty = posti("qty", 1);
  if ($id <= 0) jexit(["ok"=>false,"err"=>"Bad row_id"], 400);

  if ($qty < 1) $qty = 1;
  if ($qty > $MAX_QTY) $qty = $MAX_QTY;

  $up = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND user_id=?");
  $up->execute([$qty, $id, $meId]);
  jexit(["ok"=>true, "qty"=>$qty]);
}

if ($action === "remove"){
  $id = posti("row_id");
  if ($id <= 0) jexit(["ok"=>false,"err"=>"Bad row_id"], 400);

  $del = $pdo->prepare("DELETE FROM cart_items WHERE id=? AND user_id=?");
  $del->execute([$id, $meId]);
  jexit(["ok"=>true]);
}

if ($action === "clear"){
  $pdo->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$meId]);
  jexit(["ok"=>true]);
}

jexit(["ok"=>false, "err"=>"Unknown action"], 400);