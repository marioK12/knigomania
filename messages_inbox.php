<?php
// messages_inbox.php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }
$me = current_user();
$meId = (int)$me["id"];

/*
  Threads are grouped by:
  - other user
  - ref_type
  - usedbook_id OR campaign_id (reference id)
*/
$st = $pdo->prepare("
  SELECT
    CASE WHEN m.from_user_id = :me THEN m.to_user_id ELSE m.from_user_id END AS other_id,

    m.usedbook_id,
    m.campaign_id,
    COALESCE(NULLIF(m.ref_type,''), 'direct') AS ref_type,

    MAX(m.created_at) AS last_at,
    SUM(CASE WHEN m.to_user_id = :me AND m.read_at IS NULL THEN 1 ELSE 0 END) AS unread_count
  FROM messages m
  WHERE m.from_user_id = :me OR m.to_user_id = :me
  GROUP BY other_id, m.usedbook_id, m.campaign_id, ref_type
  ORDER BY last_at DESC
  LIMIT 200
");
$st->execute([":me"=>$meId]);
$threads = $st->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Съобщения";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="message.css?v=2">

<main class="km-main py-0">
  <div class="container msg-inbox">

    <div class="msg-topbar">
      <div class="msg-hero-inner">
        <div class="msg-hero-icons" aria-hidden="true">
          <span>💬</span>
          <span>📨</span>
        </div>

        <div class="msg-title">Съобщения</div>
        <div class="msg-subtitle">Всички разговори на едно място</div>
      </div>
    </div>

    <div class="py-4">
      <?php if (!$threads): ?>
        <div class="msg-empty">Нямаш разговори.</div>
      <?php else: ?>
        <?php foreach ($threads as $t): ?>
          <?php
            $otherId   = (int)$t["other_id"];
            $usedId    = (int)($t["usedbook_id"] ?? 0);
            $campId    = (int)($t["campaign_id"] ?? 0);
            $unread    = (int)($t["unread_count"] ?? 0);

            $refType = strtolower(trim((string)($t["ref_type"] ?? "direct")));
            if ($refType === "") $refType = "direct";

            // other user label
            $u = $pdo->prepare("SELECT name,email FROM users WHERE id=?");
            $u->execute([$otherId]);
            $ou = $u->fetch(PDO::FETCH_ASSOC);
            $label = $ou ? ($ou["name"] ?: $ou["email"]) : ("User #" . $otherId);

            // ad title by type
            $refTitle = "";
            if ($campId > 0 && $refType === "campaign") {
              $b = $pdo->prepare("SELECT title FROM campaigns WHERE id=?");
              $b->execute([$campId]);
              $refTitle = (string)($b->fetchColumn() ?: "");
            } elseif ($usedId > 0) {
              if ($refType === "giftbook") {
                $b = $pdo->prepare("SELECT title FROM gift_books WHERE id=?");
                $b->execute([$usedId]);
                $refTitle = (string)($b->fetchColumn() ?: "");
              } else {
                // usedbook / usedtextbook -> used_books
                $b = $pdo->prepare("SELECT title FROM used_books WHERE id=?");
                $b->execute([$usedId]);
                $refTitle = (string)($b->fetchColumn() ?: "");
              }
            }

            // ✅ build correct chat link
            $href = "chat.php?u=" . $otherId;

            if ($campId > 0 && $refType === "campaign") {
              $href .= "&b=" . $campId . "&t=campaign";
              $metaLeft = "Кампания: " . ($refTitle !== "" ? $refTitle : "#".$campId);

            } elseif ($usedId > 0) {
              $href .= "&b=" . $usedId . "&t=" . rawurlencode($refType);
              $metaLeft = "Обява: " . ($refTitle !== "" ? $refTitle : "#".$usedId);

            } else {
              // direct chat (IMPORTANT: don't send t=... to avoid breaking chat.php)
              $metaLeft = "Личен чат";
            }

            $metaRight = (string)($t["last_at"] ?? "");
          ?>

          <a class="msg-thread" href="<?= e($href) ?>">
            <div class="msg-thread-left">
              <div class="msg-thread-title"><?= e($label) ?></div>
              <div class="msg-thread-meta">
                <?= e($metaLeft) ?> • <?= e($metaRight) ?>
              </div>
            </div>

            <?php if ($unread > 0): ?>
              <span class="msg-unread"><?= (int)$unread ?></span>
            <?php else: ?>
              <span class="msg-pill">Отвори</span>
            <?php endif; ?>
          </a>

        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
  function kmReplay(){
    document.body.classList.remove("km-anim");
    void document.body.offsetHeight;
    document.body.classList.add("km-anim");
  }
  addEventListener("DOMContentLoaded", kmReplay);
  addEventListener("pageshow", e => { if (e.persisted) kmReplay(); });
</script>

</body>
</html>
