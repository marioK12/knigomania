<?php
require_once "db.php";
require_once "auth.php";

header("Content-Type: application/json; charset=utf-8");

function jexit($arr, int $code = 200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_logged_in()) {
  jexit(["ok"=>false, "err"=>"NOT_LOGGED_IN"], 401);
}

$me = current_user();
$meId = (int)($me["id"] ?? 0);
if ($meId <= 0) jexit(["ok"=>false, "err"=>"BAD_USER"], 500);

$action = $_GET["action"] ?? ($_POST["action"] ?? "");

function get_str($k, $def=""){
  return trim((string)($_POST[$k] ?? $_GET[$k] ?? $def));
}
function get_int($k, $def=0){
  return (int)($_POST[$k] ?? $_GET[$k] ?? $def);
}

$type   = get_str("type");   // book / textbook / usedbook / usedtextbook
$itemId = get_int("id", 0);

/**
 * ✅ ВАЖНО:
 * - usedbook = книги втора ръка
 * - usedtextbook = учебници втора ръка
 * Поддържаме и двата, за да не се чупи нищо.
 */
$allowed = ["book","textbook","usedbook","usedtextbook","giftbook"];
if ($type !== "" && !in_array($type, $allowed, true)) {
  jexit(["ok"=>false, "err"=>"BAD_TYPE"], 400);
}

try {

  /* =========================
     COUNT
     - без type: общ брой + по тип
     - с type: брой само за този тип
  ========================= */
  if ($action === "count") {
    if ($type !== "") {
      $st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND item_type=?");
      $st->execute([$meId, $type]);
      $cnt = (int)$st->fetchColumn();
      jexit(["ok"=>true, "count"=>$cnt, "type"=>$type]);
    } else {
      $st = $pdo->prepare("
        SELECT item_type, COUNT(*) AS cnt
        FROM favorites
        WHERE user_id=?
        GROUP BY item_type
      ");
      $st->execute([$meId]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);

      // ✅ включваме и usedtextbook
     $by = ["book"=>0, "textbook"=>0, "usedbook"=>0, "usedtextbook"=>0];

      $total = 0;

      foreach ($rows as $r){
        $t = (string)($r["item_type"] ?? "");
        $c = (int)($r["cnt"] ?? 0);
        if (isset($by[$t])) $by[$t] = $c;
        $total += $c;
      }

      jexit(["ok"=>true, "count"=>$total, "by_type"=>$by]);
    }
  }

  /* =========================
     TOGGLE
  ========================= */
  if ($action === "toggle") {
    if ($type === "" || $itemId <= 0) jexit(["ok"=>false,"err"=>"BAD_PARAMS"], 400);

    $st = $pdo->prepare("SELECT id FROM favorites WHERE user_id=? AND item_type=? AND item_id=? LIMIT 1");
    $st->execute([$meId, $type, $itemId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $del = $pdo->prepare("DELETE FROM favorites WHERE user_id=? AND item_type=? AND item_id=?");
      $del->execute([$meId, $type, $itemId]);
      jexit(["ok"=>true, "liked"=>false]);
    } else {
      $ins = $pdo->prepare("INSERT INTO favorites (user_id, item_type, item_id) VALUES (?, ?, ?)");
      $ins->execute([$meId, $type, $itemId]);
      jexit(["ok"=>true, "liked"=>true]);
    }
  }

  /* =========================
     CHECK
  ========================= */
  if ($action === "check") {
    if ($type === "" || $itemId <= 0) jexit(["ok"=>false,"err"=>"BAD_PARAMS"], 400);

    $st = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=? AND item_type=? AND item_id=? LIMIT 1");
    $st->execute([$meId, $type, $itemId]);
    $liked = (bool)$st->fetchColumn();
    jexit(["ok"=>true, "liked"=>$liked]);
  }

  /* =========================
     REMOVE
  ========================= */
  if ($action === "remove") {
    if ($type === "" || $itemId <= 0) jexit(["ok"=>false,"err"=>"BAD_PARAMS"], 400);

    $del = $pdo->prepare("DELETE FROM favorites WHERE user_id=? AND item_type=? AND item_id=?");
    $del->execute([$meId, $type, $itemId]);
    jexit(["ok"=>true]);
  }

  /* =========================
     LIST
  ========================= */
  if ($action === "list") {
    $st = $pdo->prepare("SELECT item_type, item_id, created_at FROM favorites WHERE user_id=? ORDER BY created_at DESC");
    $st->execute([$meId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    jexit(["ok"=>true, "items"=>$items]);
  }

  jexit(["ok"=>false, "err"=>"BAD_ACTION"], 400);

} catch (Throwable $e) {
  jexit(["ok"=>false, "err"=>"SERVER_ERROR", "detail"=>$e->getMessage()], 500);
}
