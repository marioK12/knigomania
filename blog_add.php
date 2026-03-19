<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) {
  header("Location: login.php");
  exit;
}

$cu   = current_user();
$meId = (int)$cu["id"];

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// upload dirs for blog images
$IMG_DIR_FS  = rtrim(__DIR__, "/\\") . "/blogIMG/";
$IMG_DIR_URL = $base . "blogIMG/";
if (!is_dir($IMG_DIR_FS)) { @mkdir($IMG_DIR_FS, 0775, true); }

// limits
$MAX_FILES = 6;
$MAX_SIZE  = 5 * 1024 * 1024; // 5MB per image
$ALLOW_EXT = ["jpg","jpeg","png","webp"];

// helpers
function safe_ext(string $name): string {
  $name = strtolower($name);
  $ext = pathinfo($name, PATHINFO_EXTENSION);
  return preg_replace('/[^a-z0-9]+/','', $ext);
}
function is_image_mime(string $tmp): bool {
  $fi = @finfo_open(FILEINFO_MIME_TYPE);
  if (!$fi) return false;
  $mime = (string)@finfo_file($fi, $tmp);
  @finfo_close($fi);
  return in_array($mime, ["image/jpeg","image/png","image/webp"], true);
}
function new_img_name(string $ext): string {
  $rnd = bin2hex(random_bytes(16));
  return "b_" . date("Ymd_His") . "_" . $rnd . "." . $ext;
}

// load edit
$editId = (int)($_GET["edit"] ?? 0);

$title = "";
$body  = "";
$err   = "";

// existing images (for edit)
$existingImgs = [];

if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
  $st->execute([$editId]);
  $post = $st->fetch(PDO::FETCH_ASSOC);

  if (!$post || (int)$post["user_id"] !== $meId) {
    header("Location: blog.php?msg=nope");
    exit;
  }

  $title = (string)$post["title"];
  $body  = (string)$post["body"];

  // load images
  $st = $pdo->prepare("SELECT id, filename FROM blog_post_images WHERE post_id = ? ORDER BY id ASC");
  $st->execute([$editId]);
  $existingImgs = $st->fetchAll(PDO::FETCH_ASSOC);
}

// submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim((string)($_POST["title"] ?? ""));
  $body  = trim((string)($_POST["body"] ?? ""));

  if ($title === "" || mb_strlen($title) < 3) {
    $err = "Заглавието трябва да е поне 3 символа.";
  } elseif ($body === "" || mb_strlen($body) < 10) {
    $err = "Текстът трябва да е поне 10 символа.";
  } else {
    // start transaction (post + images + deletes)
    $pdo->beginTransaction();
    try {
      if ($editId > 0) {
        $st = $pdo->prepare("
          UPDATE blog_posts
          SET title = ?, body = ?, updated_at = NOW()
          WHERE id = ? AND user_id = ?
        ");
        $st->execute([$title, $body, $editId, $meId]);
        $postId = $editId;
      } else {
        $st = $pdo->prepare("
          INSERT INTO blog_posts (user_id, title, body)
          VALUES (?, ?, ?)
        ");
        $st->execute([$meId, $title, $body]);
        $postId = (int)$pdo->lastInsertId();
      }

      /* -----------------------------------------
         Delete selected images (only in edit)
      ----------------------------------------- */
      if ($editId > 0) {
        $del = $_POST["del_imgs"] ?? [];
        if (is_array($del) && !empty($del)) {
          $ids = array_values(array_filter(array_map('intval', $del), fn($x)=>$x>0));
          if (!empty($ids)) {
            // fetch filenames
            $in = implode(",", array_fill(0, count($ids), "?"));
            $st = $pdo->prepare("SELECT id, filename FROM blog_post_images WHERE post_id = ? AND id IN ($in)");
            $st->execute(array_merge([$postId], $ids));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // delete db
            $st = $pdo->prepare("DELETE FROM blog_post_images WHERE post_id = ? AND id IN ($in)");
            $st->execute(array_merge([$postId], $ids));

            // delete files
            foreach ($rows as $r) {
              $fn = (string)$r["filename"];
              if ($fn !== "") {
                $path = $IMG_DIR_FS . $fn;
                if (is_file($path)) @unlink($path);
              }
            }
          }
        }
      }

      /* -----------------------------------------
         Upload new images (add + edit)
      ----------------------------------------- */
      if (!empty($_FILES["images"]) && is_array($_FILES["images"]["name"])) {
        $names = $_FILES["images"]["name"];
        $tmps  = $_FILES["images"]["tmp_name"];
        $errs  = $_FILES["images"]["error"];
        $sizes = $_FILES["images"]["size"];

        // count existing (edit)
        $st = $pdo->prepare("SELECT COUNT(*) FROM blog_post_images WHERE post_id = ?");
        $st->execute([$postId]);
        $existingCount = (int)$st->fetchColumn();

        $uploaded = 0;

        for ($i=0; $i<count($names); $i++) {
          if ($uploaded >= $MAX_FILES) break;

          $name = (string)$names[$i];
          $tmp  = (string)$tmps[$i];
          $er   = (int)$errs[$i];
          $sz   = (int)$sizes[$i];

          if ($name === "") continue;
          if ($er === UPLOAD_ERR_NO_FILE) continue;

          if ($er !== UPLOAD_ERR_OK) {
            throw new Exception("Грешка при качване на снимка.");
          }
          if ($sz <= 0 || $sz > $MAX_SIZE) {
            throw new Exception("Снимките трябва да са до 5MB.");
          }

          $ext = safe_ext($name);
          if (!in_array($ext, $ALLOW_EXT, true)) {
            throw new Exception("Позволени формати: JPG, PNG, WEBP.");
          }
          if (!is_uploaded_file($tmp) || !is_image_mime($tmp)) {
            throw new Exception("Невалиден файл за снимка.");
          }

          // total limit including existing
          if (($existingCount + $uploaded) >= $MAX_FILES) break;

          $newName = new_img_name($ext);
          $dest = $IMG_DIR_FS . $newName;

          if (!@move_uploaded_file($tmp, $dest)) {
            throw new Exception("Не успях да запиша снимката на сървъра.");
          }

          $st = $pdo->prepare("INSERT INTO blog_post_images (post_id, filename) VALUES (?, ?)");
          $st->execute([$postId, $newName]);

          $uploaded++;
        }
      }

      $pdo->commit();

      // ✅ Redirect към листинга
      if ($editId > 0) {
        header("Location: blog.php?msg=updated");
      } else {
        header("Location: blog.php?msg=saved");
      }
      exit;

    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $ex->getMessage() ?: "Възникна грешка. Опитай пак.";
    }
  }
}

$page_title = ($editId > 0) ? "Редакция на пост" : "Нов пост";
?>
<!doctype html>
<html lang="bg">
<head>
  <title><?= e($page_title) ?></title>

  <?php require_once "header.php"; ?>

  <link rel="stylesheet" href="blog_add.css">
</head>

<body class="km-layout">

<?php require_once "nav.php"; ?>

<main>
  <div class="container py-4">

    <div class="ba-topbar mb-3">
      <a class="btn btn-soft" href="blog.php">← Блог</a>

      <div class="ms-auto text-muted small">
        Влязъл като:
        <strong><?= e($cu["name"] ?? $cu["email"] ?? "Потребител") ?></strong>
      </div>
    </div>

    <!-- ✅ added: ba-boot for subtle animation -->
    <div class="ba-card ba-boot p-4">
      <h2 class="fw-bold mb-3"><?= e($page_title) ?></h2>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">Заглавие</label>
          <input class="form-control"
                 name="title"
                 value="<?= e($title) ?>"
                 maxlength="180"
                 required>
        </div>

        <div class="mb-3">
          <label class="form-label">Текст</label>
          <textarea class="form-control"
                    name="body"
                    rows="10"
                    required><?= e($body) ?></textarea>
          <div class="form-text">
            Пиши нормално — можеш да форматираш с нови редове.
          </div>
        </div>

        <!-- Existing images (edit) -->
        <?php if ($editId > 0 && !empty($existingImgs)): ?>
          <div class="mb-3">
            <label class="form-label">Текущи снимки</label>

            <div class="ba-img-grid">
              <?php foreach ($existingImgs as $im): ?>
                <?php
                  $imgId = (int)$im["id"];
                  $fn = (string)$im["filename"];
                  $url = $IMG_DIR_URL . rawurlencode($fn);
                ?>
                <label class="ba-img-tile">
                  <img src="<?= e($url) ?>" alt="">
                  <span class="ba-img-check">
                    <input type="checkbox" name="del_imgs[]" value="<?= $imgId ?>">
                    <span>Изтрий</span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="form-text">
              Отбележи „Изтрий“ на снимки, които не искаш.
            </div>
          </div>
        <?php endif; ?>

        <!-- Upload new images -->
        <div class="mb-3">
          <label class="form-label">Снимки (до <?= (int)$MAX_FILES ?>)</label>
          <input class="form-control" type="file" name="images[]" accept="image/*" multiple>
          <div class="form-text">
            Позволени: JPG / PNG / WEBP. До 5MB на снимка.
          </div>
        </div>

        <div class="d-flex gap-2 ba-actions">
          <button class="btn btn-eco" type="submit">
            <?= ($editId > 0 ? "Запази" : "Публикувай") ?>
          </button>
          <a class="btn btn-soft" href="blog.php">Отказ</a>
        </div>
      </form>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>

<!-- ✅ if header.php doesn't include it, this guarantees Bootstrap behavior -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- ✅ subtle entrance animation trigger -->
<script>
  window.addEventListener("load", () => {
    document.querySelectorAll(".ba-boot").forEach(el => el.classList.add("is-ready"));
  }, { once:true });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
