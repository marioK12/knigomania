<?php
require_once "db.php";
require_once "auth.php";

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

/* ---------------------------------------------------------
   Paths / URLs
--------------------------------------------------------- */

// base URL (ако си в подпапка) -> винаги завършва с /
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$IMG_URL = $base . "usedIMG/";

// FS folder
$IMG_DIR_FS = rtrim(__DIR__, "/\\") . "/usedIMG/";
if (!is_dir($IMG_DIR_FS)) {
  @mkdir($IMG_DIR_FS, 0775, true);
}

/* ---------------------------------------------------------
   Helpers
--------------------------------------------------------- */

function jexit($arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

function postv(string $k): string {
  return trim((string)($_POST[$k] ?? ""));
}

function read_json_body(): array {
  $raw = file_get_contents("php://input");
  if (!is_string($raw) || trim($raw) === "") return [];
  // махаме BOM ако има
  $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function safe_unlink(string $path): void {
  if ($path !== "" && is_file($path)) @unlink($path);
}

function save_image_upload(array $file, string $destDir, int $uid): ?string {
  if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  $maxBytes = 3 * 1024 * 1024; // 3MB per image
  if (($file["size"] ?? 0) > $maxBytes) return null;

  $tmp = $file["tmp_name"] ?? "";
  if ($tmp === "" || !is_uploaded_file($tmp)) return null;

  $info = @getimagesize($tmp);
  if (!$info) return null;

  $mime = $info["mime"] ?? "";
  $allowed = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp",
  ];
  if (!isset($allowed[$mime])) return null;

  $ext = $allowed[$mime];
  $newName = "ub{$uid}_" . bin2hex(random_bytes(8)) . "." . $ext;
  $dest = rtrim($destDir, "/\\") . "/" . $newName;

  if (!@move_uploaded_file($tmp, $dest)) return null;

  return $newName;
}

function is_owner_or_admin(PDO $pdo, int $bookId, array $user): bool {
  $uid  = (int)($user["id"] ?? 0);
  $role = (string)($user["role"] ?? "user");

  $st = $pdo->prepare("SELECT user_id FROM used_books WHERE id=? LIMIT 1");
  $st->execute([$bookId]);
  $ownerId = (int)($st->fetchColumn() ?: 0);

  return ($ownerId > 0 && ($ownerId === $uid || $role === "admin"));
}

function normalize_condition(string $c): string {
  $allowed = ["new","like_new","good","fair","poor"];
  return in_array($c, $allowed, true) ? $c : "good";
}

function normalize_category(string $cat): string {
  // whitelist – синхронизирай с твоите опции
  $allowed = [
    "hudojestvena","nauchna","detski","fantastika","fentezi","trilari",
    "krimi","romantika","istoriya","biografii","psihologiya",
    "samorazvitie","biznes","poeziya"
  ];
  return in_array($cat, $allowed, true) ? $cat : "";
}

/* ---------------------------------------------------------
   GET: list / single
--------------------------------------------------------- */

if ($method === "GET") {

  // SINGLE ITEM
  $id = (int)($_GET["id"] ?? 0);
  if ($id > 0) {
    $st = $pdo->prepare("
      SELECT
        b.id, b.user_id, b.title, b.author, b.category, b.description,
        b.price, b.`condition`, b.city, b.phone, b.status,
        b.created_at,
        u.name AS seller_name,
        u.email AS seller_email
      FROM used_books b
      JOIN users u ON u.id = b.user_id
      WHERE b.id = ?
      LIMIT 1
    ");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) jexit(["error" => "Обявата не е намерена."], 404);

    $st2 = $pdo->prepare("
      SELECT id, file_name, sort_order
      FROM used_book_images
      WHERE used_book_id = ?
      ORDER BY sort_order ASC, id ASC
    ");
    $st2->execute([$id]);

    $imgs = [];
    while ($im = $st2->fetch(PDO::FETCH_ASSOC)) {
      $imgs[] = [
        "id"   => (int)$im["id"],
        "url"  => $GLOBALS["IMG_URL"] . $im["file_name"],
        "sort" => (int)($im["sort_order"] ?? 0),
        "file" => (string)$im["file_name"],
      ];
    }

    $row["images"] = $imgs;
    $row["price"]  = (float)$row["price"];

    jexit(["item" => $row]);
  }

  // LIST
  $q        = trim((string)($_GET["q"] ?? ""));
  $category = trim((string)($_GET["category"] ?? ""));
  $sort     = trim((string)($_GET["sort"] ?? "newest"));
  $status   = trim((string)($_GET["status"] ?? "active"));

  // ако не си логнат admin -> не позволявай да филтрира sold/друго през URL
  $user = is_logged_in() ? current_user() : null;
  $role = (string)($user["role"] ?? "user");
  if ($role !== "admin") $status = "active";

  $where = [];
  $params = [];

  if ($status === "") $status = "active";
  $where[] = "b.status = ?";
  $params[] = $status;

  if ($q !== "") {
    $where[] = "(b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%" . $q . "%";
    $params[] = "%" . $q . "%";
  }

 $TB_CATEGORY = "Учебници";

// ако category е празно/всички → НЕ показвай учебници в usedbooks.php
if ($category === "" || $category === "all") {
  $where[] = "b.category <> ?";
  $params[] = $TB_CATEGORY;
} else {
  // нормалните категории за книги
  $category = normalize_category($category);

  // ако някой подаде невалидна категория -> пак скриваме учебниците
  if ($category === "") {
    $where[] = "b.category <> ?";
    $params[] = $TB_CATEGORY;
  } else {
    $where[] = "b.category = ?";
    $params[] = $category;
  }
}


  $orderBy = "b.created_at DESC";
  if ($sort === "price_asc")  $orderBy = "b.price ASC, b.created_at DESC";
  if ($sort === "price_desc") $orderBy = "b.price DESC, b.created_at DESC";

  $sql = "
    SELECT
      b.id, b.user_id, b.title, b.author, b.category, b.description,
      b.price, b.`condition`, b.city, b.phone, b.status,
      b.created_at,
      u.name AS seller_name,
      u.email AS seller_email
    FROM used_books b
    JOIN users u ON u.id = b.user_id
    " . (count($where) ? ("WHERE " . implode(" AND ", $where)) : "") . "
    ORDER BY $orderBy
    LIMIT 200
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // снимки (за листинга: връщаме всички URLs, първата ти е достатъчна)
  $ids = array_map(fn($r) => (int)$r["id"], $rows);
  $imgMap = [];
  if ($ids) {
    $in = implode(",", array_fill(0, count($ids), "?"));
    $st2 = $pdo->prepare("
      SELECT used_book_id, file_name, sort_order
      FROM used_book_images
      WHERE used_book_id IN ($in)
      ORDER BY sort_order ASC, id ASC
    ");
    $st2->execute($ids);
    while ($im = $st2->fetch(PDO::FETCH_ASSOC)) {
      $bid = (int)$im["used_book_id"];
      $imgMap[$bid] ??= [];
      $imgMap[$bid][] = $IMG_URL . $im["file_name"];
    }
  }

  foreach ($rows as &$r) {
    $bid = (int)$r["id"];
    $r["images"] = $imgMap[$bid] ?? [];
    $r["price"] = (float)$r["price"];
  }
  unset($r);

  jexit(["items" => $rows]);
}

/* ---------------------------------------------------------
   POST: create / actions
--------------------------------------------------------- */

if ($method === "POST") {
  require_login();
  $user = current_user();
  $uid = (int)($user["id"] ?? 0);

  // ---- JSON actions ----
  $json = read_json_body();
  if (!empty($json["action"])) {
    $action = (string)$json["action"];

    // EDIT
    if ($action === "edit") {
      $bookId = (int)($json["id"] ?? 0);
      if ($bookId <= 0) jexit(["error" => "Невалидна обява."], 400);
      if (!is_owner_or_admin($pdo, $bookId, $user)) jexit(["error" => "Нямате права."], 403);

      $title = trim((string)($json["title"] ?? ""));
      $author = trim((string)($json["author"] ?? ""));
      $category = normalize_category(trim((string)($json["category"] ?? "")));
      $description = trim((string)($json["description"] ?? ""));
      $price = (float)str_replace(",", ".", (string)($json["price"] ?? "0"));
      $condition = normalize_condition(trim((string)($json["condition"] ?? "good")));
      $city = trim((string)($json["city"] ?? ""));
      $phone = trim((string)($json["phone"] ?? ""));

      if (mb_strlen($title) < 2) jexit(["error" => "Заглавието е твърде кратко."], 400);
      if (mb_strlen($author) < 2) jexit(["error" => "Авторът е твърде кратък."], 400);
      if ($category === "") jexit(["error" => "Избери валидна категория."], 400);

      // ✅ ОПИСАНИЕТО Е ЗАДЪЛЖИТЕЛНО (min 10)
      if (mb_strlen($description) < 10) jexit(["error" => "Описанието трябва да е поне 10 символа."], 400);

      if ($price < 0) $price = 0;

      $st = $pdo->prepare("
        UPDATE used_books
        SET title=?, author=?, category=?, description=?, price=?, `condition`=?, city=?, phone=?
        WHERE id=?
      ");
      $st->execute([$title, $author, $category, $description, $price, $condition, $city, $phone, $bookId]);

      jexit(["success" => true]);
    }

    // DELETE IMAGE (must leave >=1)
    if ($action === "delete_image") {
      $imageId = (int)($json["image_id"] ?? 0);
      if ($imageId <= 0) jexit(["error" => "Невалидна снимка."], 400);

      $st = $pdo->prepare("SELECT id, used_book_id, file_name FROM used_book_images WHERE id=? LIMIT 1");
      $st->execute([$imageId]);
      $im = $st->fetch(PDO::FETCH_ASSOC);
      if (!$im) jexit(["error" => "Снимката не е намерена."], 404);

      $bookId = (int)$im["used_book_id"];
      if (!is_owner_or_admin($pdo, $bookId, $user)) jexit(["error" => "Нямате права."], 403);

      $st = $pdo->prepare("SELECT COUNT(*) FROM used_book_images WHERE used_book_id=?");
      $st->execute([$bookId]);
      $cnt = (int)$st->fetchColumn();

      if ($cnt <= 1) jexit(["error" => "Трябва да остане поне 1 снимка."], 400);

      $pdo->beginTransaction();
      try {
        $pdo->prepare("DELETE FROM used_book_images WHERE id=?")->execute([$imageId]);
        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(["error" => "Грешка при изтриване."], 500);
      }

      safe_unlink($IMG_DIR_FS . ($im["file_name"] ?? ""));
      jexit(["success" => true]);
    }

    // MARK SOLD
    if ($action === "mark_sold") {
      $bookId = (int)($json["id"] ?? 0);
      if ($bookId <= 0) jexit(["error" => "Невалидна обява."], 400);
      if (!is_owner_or_admin($pdo, $bookId, $user)) jexit(["error" => "Нямате права."], 403);

      $pdo->prepare("UPDATE used_books SET status='sold' WHERE id=?")->execute([$bookId]);
      jexit(["success" => true]);
    }

    // DELETE LISTING
    if ($action === "delete_listing") {
      $bookId = (int)($json["id"] ?? 0);
      if ($bookId <= 0) jexit(["error" => "Невалидна обява."], 400);
      if (!is_owner_or_admin($pdo, $bookId, $user)) jexit(["error" => "Нямате права."], 403);

      $st = $pdo->prepare("SELECT file_name FROM used_book_images WHERE used_book_id=?");
      $st->execute([$bookId]);
      $files = $st->fetchAll(PDO::FETCH_COLUMN);

      $pdo->beginTransaction();
      try {
        $pdo->prepare("DELETE FROM used_book_images WHERE used_book_id=?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM used_books WHERE id=?")->execute([$bookId]);
        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(["error" => "Грешка при изтриване."], 500);
      }

      foreach ($files as $f) safe_unlink($IMG_DIR_FS . (string)$f);
      jexit(["success" => true]);
    }

    jexit(["error" => "Невалидно действие."], 400);
  }

  // ---- multipart/form-data actions ----
  $actionForm = postv("action");

  // ADD IMAGES: action=add_images + id + images[]
  if ($actionForm === "add_images") {
    $bookId = (int)($_POST["id"] ?? 0);
    if ($bookId <= 0) jexit(["error" => "Невалидна обява."], 400);
    if (!is_owner_or_admin($pdo, $bookId, $user)) jexit(["error" => "Нямате права."], 403);

    // current count
    $st = $pdo->prepare("SELECT COUNT(*) FROM used_book_images WHERE used_book_id=?");
    $st->execute([$bookId]);
    $current = (int)$st->fetchColumn();

    if ($current >= 5) jexit(["error" => "Вече имаш 5 снимки."], 400);

    if (empty($_FILES["images"]) || !is_array($_FILES["images"]["name"] ?? null)) {
      jexit(["error" => "Няма избрани снимки."], 400);
    }

    $left  = 5 - $current;
    $count = min($left, count($_FILES["images"]["name"]));

    $saved = [];

    $pdo->beginTransaction();
    try {
      for ($i = 0; $i < $count; $i++) {
        $one = [
          "name" => $_FILES["images"]["name"][$i] ?? "",
          "type" => $_FILES["images"]["type"][$i] ?? "",
          "tmp_name" => $_FILES["images"]["tmp_name"][$i] ?? "",
          "error" => $_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE,
          "size" => $_FILES["images"]["size"][$i] ?? 0,
        ];

        $fn = save_image_upload($one, $IMG_DIR_FS, $uid);
        if ($fn) {
          $saved[] = $fn;
          $sortOrder = $current + count($saved) - 1;
          $pdo->prepare("
            INSERT INTO used_book_images (used_book_id, file_name, sort_order)
            VALUES (?, ?, ?)
          ")->execute([$bookId, $fn, $sortOrder]);
        }
      }

      if (!count($saved)) {
        throw new Exception("Снимките не се качиха (JPG/PNG/WEBP до 3MB).");
      }

      $pdo->commit();
      jexit(["success" => true, "added" => count($saved)]);

    } catch (Throwable $e) {
      $pdo->rollBack();
      jexit(["error" => $e->getMessage() ?: "Грешка при качване."], 400);
    }
  }

  // ---- CREATE listing (multipart, без action) ----
  $title = postv("title");
  $author = postv("author");
  $category = normalize_category(postv("category"));
  $description = trim(postv("description")); // ✅ trim
  $price = (float)str_replace(",", ".", postv("price"));
  $condition = normalize_condition(postv("condition"));
  $city = postv("city");
  $phone = postv("phone");

  if (mb_strlen($title) < 2) jexit(["error" => "Заглавието е твърде кратко."], 400);
  if (mb_strlen($author) < 2) jexit(["error" => "Авторът е твърде кратък."], 400);
  if ($category === "") jexit(["error" => "Избери валидна категория."], 400);

  // ✅ ОПИСАНИЕТО Е ЗАДЪЛЖИТЕЛНО (min 10)
  if (mb_strlen($description) < 10) jexit(["error" => "Описанието трябва да е поне 10 символа."], 400);

  if ($price < 0) $price = 0;

  // поне 1 снимка
  $hasAny = !empty($_FILES["images"])
    && is_array($_FILES["images"]["name"] ?? null)
    && count(array_filter($_FILES["images"]["name"])) > 0;

  if (!$hasAny) jexit(["error" => "Моля, качи поне 1 снимка."], 400);

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("
      INSERT INTO used_books (user_id, title, author, category, description, price, `condition`, city, phone, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $st->execute([$uid, $title, $author, $category, $description, $price, $condition, $city, $phone]);
    $bookId = (int)$pdo->lastInsertId();

    // images (up to 5)
    $savedFiles = [];
    $count = min(5, count($_FILES["images"]["name"]));
    for ($i = 0; $i < $count; $i++) {
      $one = [
        "name" => $_FILES["images"]["name"][$i] ?? "",
        "type" => $_FILES["images"]["type"][$i] ?? "",
        "tmp_name" => $_FILES["images"]["tmp_name"][$i] ?? "",
        "error" => $_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE,
        "size" => $_FILES["images"]["size"][$i] ?? 0,
      ];

      $fn = save_image_upload($one, $IMG_DIR_FS, $uid);
      if ($fn) {
        $savedFiles[] = $fn;
        $pdo->prepare("
          INSERT INTO used_book_images (used_book_id, file_name, sort_order)
          VALUES (?, ?, ?)
        ")->execute([$bookId, $fn, $i]);
      }
    }

    if (count($savedFiles) < 1) {
      throw new Exception("Снимките не се качиха. Позволени: JPG/PNG/WEBP до 3MB.");
    }

    $pdo->commit();
    jexit(["success" => true, "id" => $bookId]);

  } catch (Throwable $e) {
    $pdo->rollBack();
    jexit(["error" => $e->getMessage() ?: "Грешка при запис. Опитай пак."], 400);
  }
}

jexit(["error" => "Методът не е разрешен"], 405);
