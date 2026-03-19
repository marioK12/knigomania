<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$me   = current_user();
$meId = (int)$me["id"];

/*
  chat.php params:
  u = other user id
  b = ref id (usedbook_id / giftbook_id / campaign_id)
  t = type: usedbook / usedtextbook / giftbook / campaign
*/
$otherId = (int)($_GET["u"] ?? 0);
$refId   = (int)($_GET["b"] ?? 0);
$type    = strtolower(trim((string)($_GET["t"] ?? "usedbook")));

if ($otherId <= 0) { die("Missing user."); }
if ($type === "") $type = "usedbook";

// ✅ allow only known types (avoid junk)
$allowed = ["usedbook","usedtextbook","giftbook","campaign"];
if (!in_array($type, $allowed, true)) $type = "usedbook";

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// Ако имаме "обява/референция" – валидирай + вземи данни (за правилен линк)
$book  = null;   // title holder (for subtitle)
$adUrl = "";

// We'll map to proper columns:
$usedbookId  = 0; // used_books id OR gift_books id (kept in usedbook_id historically)
$campaignId  = 0; // campaigns id (new)

if ($refId > 0) {

  if ($type === "giftbook") {
    // ✅ GIFT BOOK
    $st = $pdo->prepare("SELECT id, title FROM gift_books WHERE id=? LIMIT 1");
    $st->execute([$refId]);
    $book = $st->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
      $refId = 0;
      $type = "usedbook";
    } else {
      $usedbookId = (int)$refId; // historically stored in usedbook_id
      $adUrl = $base . "giftbook_view.php?id=" . (int)$refId;
    }

  } elseif ($type === "campaign") {
    // ✅ CAMPAIGN
    $st = $pdo->prepare("SELECT id, title FROM campaigns WHERE id=? LIMIT 1");
    $st->execute([$refId]);
    $book = $st->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
      $refId = 0;
      $type = "usedbook";
    } else {
      $campaignId = (int)$refId;
      $adUrl = $base . "campaign_view.php?id=" . (int)$refId;
    }

  } else {
    // ✅ USED BOOKS (и учебници втора ръка, които при теб са пак в used_books)
    $st = $pdo->prepare("SELECT id, title, category FROM used_books WHERE id=? LIMIT 1");
    $st->execute([$refId]);
    $book = $st->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
      $refId = 0;
    } else {
      $usedbookId = (int)$refId;

      // ✅ ако е изрично usedtextbook → винаги textbook view
      if ($type === "usedtextbook") {
        $adUrl = $base . "textbook_secondhand_view.php?id=" . (int)$refId;
      } else {
        // старото ти поведение: избери според category
        $cat = trim((string)($book["category"] ?? ""));
        if ($cat === "Учебници") {
          $adUrl = $base . "textbook_secondhand_view.php?id=" . (int)$refId;
        } else {
          $adUrl = $base . "usedbook.php?id=" . (int)$refId;
        }
      }
    }
  }
}

// =========================================================
// MARK AS READ
// =========================================================
if ($campaignId > 0) {
  $pdo->prepare("
    UPDATE messages
    SET read_at=NOW()
    WHERE to_user_id=? AND from_user_id=? AND campaign_id=? AND ref_type='campaign' AND read_at IS NULL
  ")->execute([$meId, $otherId, $campaignId]);

} elseif ($usedbookId > 0) {
  $pdo->prepare("
    UPDATE messages
    SET read_at=NOW()
    WHERE to_user_id=? AND from_user_id=? AND usedbook_id=? AND ref_type=? AND read_at IS NULL
  ")->execute([$meId, $otherId, $usedbookId, $type]);

} else {
  // direct (no ref)
  $pdo->prepare("
    UPDATE messages
    SET read_at=NOW()
    WHERE to_user_id=? AND from_user_id=? AND usedbook_id IS NULL AND campaign_id IS NULL AND read_at IS NULL
  ")->execute([$meId, $otherId]);
}

// =========================================================
// HISTORY
// =========================================================
if ($campaignId > 0) {
  $st = $pdo->prepare("
    SELECT m.*, u.name AS from_name, u.email AS from_email
    FROM messages m
    JOIN users u ON u.id = m.from_user_id
    WHERE m.campaign_id=? AND m.ref_type='campaign' AND (
      (m.from_user_id=? AND m.to_user_id=?) OR
      (m.from_user_id=? AND m.to_user_id=?)
    )
    ORDER BY m.created_at ASC
    LIMIT 500
  ");
  $st->execute([$campaignId, $meId, $otherId, $otherId, $meId]);

} elseif ($usedbookId > 0) {
  $st = $pdo->prepare("
    SELECT m.*, u.name AS from_name, u.email AS from_email
    FROM messages m
    JOIN users u ON u.id = m.from_user_id
    WHERE m.usedbook_id=? AND m.ref_type=? AND (
      (m.from_user_id=? AND m.to_user_id=?) OR
      (m.from_user_id=? AND m.to_user_id=?)
    )
    ORDER BY m.created_at ASC
    LIMIT 500
  ");
  $st->execute([$usedbookId, $type, $meId, $otherId, $otherId, $meId]);

} else {
  $st = $pdo->prepare("
    SELECT m.*, u.name AS from_name, u.email AS from_email
    FROM messages m
    JOIN users u ON u.id = m.from_user_id
    WHERE m.usedbook_id IS NULL AND m.campaign_id IS NULL AND (
      (m.from_user_id=? AND m.to_user_id=?) OR
      (m.from_user_id=? AND m.to_user_id=?)
    )
    ORDER BY m.created_at ASC
    LIMIT 500
  ");
  $st->execute([$meId, $otherId, $otherId, $meId]);
}

$msgs = $st->fetchAll(PDO::FETCH_ASSOC);

// другият човек
$st2 = $pdo->prepare("SELECT id, name, email FROM users WHERE id=?");
$st2->execute([$otherId]);
$other = $st2->fetch(PDO::FETCH_ASSOC);
if (!$other) { die("User not found."); }

$page_title = "Чат";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<body class="km-layout km-anim">
<?php require_once "nav.php"; ?>

<link rel="stylesheet" href="<?= e($base) ?>chat.css?v=2">

<main class="km-main py-4">
  <div class="container" style="max-width: 900px;">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <a class="btn btn-outline-secondary" href="<?= e($base) ?>messages_inbox.php">← Назад</a>

      <?php if (($campaignId > 0 || $usedbookId > 0) && $book && $adUrl): ?>
        <a class="btn btn-outline-success" href="<?= e($adUrl) ?>">Към обявата</a>
      <?php endif; ?>
    </div>

    <div class="chat-card">

      <div class="chat-header">
        <div class="title">
          Чат с <?= e($other["name"] ?: $other["email"]) ?>
        </div>

        <?php if (($campaignId > 0 || $usedbookId > 0) && $book): ?>
          <div class="subtitle">
            За обява: <?= e($book["title"]) ?>
          </div>
        <?php else: ?>
          <div class="subtitle">Личен чат</div>
        <?php endif; ?>
      </div>

      <div class="chat-body" id="chatBox">
        <?php if (!$msgs): ?>
          <div class="text-muted">Няма съобщения още.</div>
        <?php else: ?>
          <?php foreach ($msgs as $m): ?>
            <?php $mine = ((int)$m["from_user_id"] === $meId); ?>

            <div class="d-flex <?= $mine ? "justify-content-end" : "justify-content-start" ?>">
              <div class="chat-msg <?= $mine ? "me" : "other" ?>">
                <?= e($m["body"]) ?>
                <div class="chat-time"><?= e($m["created_at"]) ?></div>
              </div>
            </div>

          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="chat-footer">
        <div class="input-group">
          <textarea id="chatMsg" class="form-control chat-input" rows="2" maxlength="2000"
                    placeholder="Напиши съобщение..."></textarea>
          <button class="btn btn-primary chat-send" id="chatSend">Изпрати</button>
        </div>
        <div class="small text-muted mt-1" id="chatStatus"></div>
      </div>

    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<script>
(function(){
  const box = document.getElementById("chatBox");
  if (box) box.scrollTop = box.scrollHeight;

  const sendBtn = document.getElementById("chatSend");
  const txt = document.getElementById("chatMsg");
  const st  = document.getElementById("chatStatus");

  txt.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendBtn.click();
    }
  });

  sendBtn.addEventListener("click", async () => {
    const body = (txt.value || "").trim();
    if (!body) { st.textContent = "Напиши съобщение."; return; }

    sendBtn.disabled = true;
    st.textContent = "Изпращане...";

    const fd = new FormData();
    fd.append("to_user_id", "<?= (int)$otherId ?>");
    fd.append("body", body);

    <?php if ($campaignId > 0): ?>
      fd.append("ref_type", "campaign");
      fd.append("campaign_id", "<?= (int)$campaignId ?>");
    <?php elseif ($usedbookId > 0): ?>
      fd.append("ref_type", "<?= e($type) ?>");
      fd.append("usedbook_id", "<?= (int)$usedbookId ?>");
    <?php endif; ?>

    try {
      const r = await fetch("<?= e($base) ?>send_message.php", { method:"POST", body: fd });
      const j = await r.json().catch(()=>({}));

      if (!r.ok || !j.ok) {
        st.textContent = j.error || "Грешка при изпращане.";
      } else {
        txt.value = "";
        st.textContent = "Изпратено ✅";
        location.reload();
      }
    } catch(e) {
      st.textContent = "Мрежова грешка.";
    } finally {
      sendBtn.disabled = false;
    }
  });
})();
</script>

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
