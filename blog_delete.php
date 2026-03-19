<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

$cu = current_user();
$meId = (int)($cu["id"] ?? 0);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { header("Location: blog.php"); exit; }

// взимаме поста и проверяваме собственост
$st = $pdo->prepare("SELECT id, user_id FROM blog_posts WHERE id = ? LIMIT 1");
$st->execute([$id]);
$p = $st->fetch(PDO::FETCH_ASSOC);

if (!$p) { header("Location: blog.php"); exit; }

// ако не е негов — не трие
if ((int)$p["user_id"] !== $meId) {
  header("Location: blog.php?id=".$id);
  exit;
}

// за защита: трие само при POST (не при директен линк)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: blog.php?id=".$id);
  exit;
}

// трием
$del = $pdo->prepare("DELETE FROM blog_posts WHERE id = ? AND user_id = ? LIMIT 1");
$del->execute([$id, $meId]);

// ✅ вместо alert: връщаме с msg
header("Location: blog.php?msg=deleted");
exit;
