<?php
require_once "db.php";
require_once "auth.php";

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"];

// base url (subfolder safe)
$base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\") . "/";
$AVATAR_DIR_URL = $base . "usersIMG/";

// =====================
// GET: reviews + avatar + likes
// =====================
if ($method === "GET") {
    $book_id = (int)($_GET["book_id"] ?? 0);
    if ($book_id <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Липсва валидно ID на книга"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $me = is_logged_in() ? (int)current_user()["id"] : 0;

    $st = $pdo->prepare("
        SELECT
            r.id,
            r.rating,
            r.message,
            r.created_at,
            r.user_id,
            u.email,
            u.name,
            u.avatar AS avatar_file,

            (SELECT COUNT(*) FROM review_likes rl WHERE rl.review_id = r.id) AS likes_count,
            (SELECT COUNT(*) FROM review_likes rl2 WHERE rl2.review_id = r.id AND rl2.user_id = ?) AS liked_by_me

        FROM reviews r
        JOIN users u ON u.id = r.user_id
        WHERE r.book_id = ?
        ORDER BY r.created_at DESC
    ");
    $st->execute([$me, $book_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $file = $row["avatar_file"] ?? "";
        $row["avatar"] = $file ? ($AVATAR_DIR_URL . $file) : "";
        unset($row["avatar_file"]);

        $row["likes_count"] = (int)($row["likes_count"] ?? 0);
        $row["liked_by_me"] = ((int)($row["liked_by_me"] ?? 0)) > 0;
    }
    unset($row);

    echo json_encode(["reviews" => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================
// POST: toggle_like / edit_review / add_review
// =====================
if ($method === "POST") {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(["error" => "Трябва да сте логнати"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) $input = $_POST;

    $action = trim((string)($input["action"] ?? ""));

    // ---- ❤️ LIKE / UNLIKE ----
    if ($action === "toggle_like") {
        $review_id = (int)($input["review_id"] ?? 0);
        if ($review_id <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Невалидно ревю"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $uid = (int)current_user()["id"];

        $st = $pdo->prepare("SELECT id FROM review_likes WHERE review_id=? AND user_id=? LIMIT 1");
        $st->execute([$review_id, $uid]);
        $likeId = (int)($st->fetchColumn() ?: 0);

        if ($likeId > 0) {
            $pdo->prepare("DELETE FROM review_likes WHERE id=?")->execute([$likeId]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO review_likes (review_id, user_id) VALUES (?, ?)")
                ->execute([$review_id, $uid]);
            $liked = true;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM review_likes WHERE review_id=?");
        $st->execute([$review_id]);
        $cnt = (int)$st->fetchColumn();

        echo json_encode(["success" => true, "liked" => $liked, "likes_count" => $cnt], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- ⭐ EDIT REVIEW (text + stars) + ✅ update time like textbook_view.php ----
    if ($action === "edit_review") {
        $review_id = (int)($input["review_id"] ?? 0);
        $rating  = (int)($input["rating"] ?? 0);
        $message = trim((string)($input["message"] ?? ""));

        if ($review_id <= 0 || $rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(["error" => "Невалидни данни"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (mb_strlen($message) < 2) {
            http_response_code(400);
            echo json_encode(["error" => "Напиши поне 2 символа 🙂"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $user = current_user();
        $uid  = (int)($user["id"] ?? 0);
        $role = (string)($user["role"] ?? "user");

        $st = $pdo->prepare("
            UPDATE reviews
            SET rating = ?, message = ?, created_at = NOW()
            WHERE id = ? AND (user_id = ? OR ? = 'admin')
        ");
        $st->execute([$rating, $message, $review_id, $uid, $role]);

        if ($st->rowCount() > 0) {
            echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(403);
            echo json_encode(["error" => "Нямате права"], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ---- ➕ ADD REVIEW (1 review/book) ----
    $book_id = (int)($input["book_id"] ?? 0);
    $rating  = (int)($input["rating"] ?? 0);
    $message = trim((string)($input["message"] ?? ""));

    if ($book_id <= 0 || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(["error" => "Невалидни данни"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (mb_strlen($message) < 2) {
        http_response_code(400);
        echo json_encode(["error" => "Напиши поне 2 символа 🙂"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $st = $pdo->prepare("INSERT INTO reviews (book_id, user_id, rating, message) VALUES (?, ?, ?, ?)");
        $st->execute([$book_id, (int)current_user()["id"], $rating, $message]);

        echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) == 1062) {
            http_response_code(409);
            echo json_encode(["error" => "Вече имаш ревю за тази книга. Използвай ✏️ Редактирай."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(500);
        echo json_encode(["error" => "Грешка при запис. Опитай пак."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// =====================
// DELETE: delete review
// =====================
if ($method === "DELETE") {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(["error" => "Не сте оторизирани"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $review_id = (int)($_GET["id"] ?? 0);
    if ($review_id <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Липсва валидно ID на ревю"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = current_user();
    $role = (string)($user["role"] ?? "user");

    $st = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $st->execute([$review_id, (int)$user["id"], $role]);

    if ($st->rowCount() > 0) {
        echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(403);
        echo json_encode(["error" => "Нямате права"], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Методът не е разрешен"], JSON_UNESCAPED_UNICODE);
