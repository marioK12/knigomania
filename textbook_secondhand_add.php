<?php
require_once "db.php";
require_once "auth.php";

if (!is_logged_in()) { header("Location: login.php"); exit; }
$me = current_user();
$meId = (int)$me["id"];

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

// upload dirs (ползваме същото като usedbooks)
$IMG_DIR_FS = rtrim(__DIR__, "/\\") . "/usedIMG/";
if (!is_dir($IMG_DIR_FS)) { @mkdir($IMG_DIR_FS, 0775, true); }

// constants
$CATEGORY = "Учебници";
$MAX_FILES = 6;

// subjects by grade group (slugs)
$SUBJECTS_BY_GRADE = [
  "1-4" => [
    "bg"   => "Български език",
    "math" => "Математика",
    "os"   => "Околен свят",
    "music"=> "Музика",
    "art"  => "Изобразително изкуство",
    "tech" => "Технологии",
    "eng"  => "Английски език",
    "civ"  => "Човекът и обществото",
    "nat"  => "Човекът и природата",
  ],
  "5-7" => [
    "bg"   => "Български език",
    "lit"  => "Литература",
    "math" => "Математика",
    "eng"  => "Английски език",
    "hist" => "История",
    "geo"  => "География",
    "bio"  => "Биология",
    "phys" => "Физика",
    "chem" => "Химия",
    "it"   => "Информационни технологии",
    "music"=> "Музика",
    "art"  => "Изобразително изкуство",
    "tech" => "Технологии и предприемачество",
  ],
  "8-12" => [
    "bg"   => "Български език",
    "lit"  => "Литература",
    "math" => "Математика",
    "eng"  => "Английски език",
    "hist" => "История",
    "geo"  => "География",
    "bio"  => "Биология",
    "phys" => "Физика",
    "chem" => "Химия",
    "it"   => "Информационни технологии",
    "phil" => "Философия",
    "civ"  => "Гражданско образование",
  ],
];

// helpers
function postv($k){ return trim((string)($_POST[$k] ?? "")); }
function is_img_ext($ext){
  $ext = strtolower($ext);
  return in_array($ext, ["jpg","jpeg","png","webp"], true);
}
function safe_price($v){
  $v = str_replace(",", ".", trim((string)$v));
  if ($v === "") return 0.0;
  return (float)$v;
}

// ✅ изискваме "текст" = да има поне 1 буква (кирилица или латиница)
function has_letter($s): bool {
  return (bool)preg_match('/[A-Za-zА-Яа-яЁёІіЇїЄє]/u', (string)$s);
}

// form values (default)
$title = $author = $desc = $city = $phone = "";
$price = "";
$condition = "good";
$grade_band = "1-4";
$subject = "bg";

$err = "";
$ok = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = postv("title");
  $author = postv("author");
  $desc = postv("description");
  $city = postv("city");
  $phone = postv("phone");
  $price = postv("price");
  $condition = postv("condition") ?: "good";
  $grade_band = postv("grade_band") ?: "1-4";
  $subject = postv("subject") ?: "all";

  // validate grade
  if (!isset($SUBJECTS_BY_GRADE[$grade_band])) $grade_band = "1-4";

  // validate subject for grade
  if (!isset($SUBJECTS_BY_GRADE[$grade_band][$subject])) {
    $subject = array_key_first($SUBJECTS_BY_GRADE[$grade_band]) ?: "bg";
  }

  $p = safe_price($price);

  if ($title === "") $err = "Моля, въведи заглавие.";
  else if ($author === "") $err = "Моля, въведи автор/издателство.";
  else if ($p < 0) $err = "Невалидна цена.";
  else if (!in_array($condition, ["new","like_new","good","fair","poor"], true)) $err = "Невалидно състояние.";

  // ✅ ЗАДЪЛЖИТЕЛНИ: град + описание (описание да е текст)
  else if ($city === "") $err = "Моля, въведи град.";
  else if (!has_letter($city)) $err = "Градът трябва да съдържа текст (букви).";
  else if ($desc === "") $err = "Моля, въведи описание.";
  else if (!has_letter($desc)) $err = "Описанието трябва да съдържа текст (букви), не само цифри/символи.";

  else {
    // validate images (at least 1)
    $files = $_FILES["images"] ?? null;
    $hasAny = $files && isset($files["name"]) && is_array($files["name"]) && count(array_filter($files["name"])) > 0;

    if (!$hasAny) {
      $err = "Качи поне 1 снимка.";
    } else {
      // count
      $names = $files["name"];
      $tmp   = $files["tmp_name"];
      $errs  = $files["error"];
      $sizes = $files["size"];

      $picked = [];
      for ($i=0; $i<count($names); $i++){
        if ($names[$i] === "") continue;
        $picked[] = $i;
      }
      if (count($picked) > $MAX_FILES) {
        $err = "Може да качиш най-много {$MAX_FILES} снимки.";
      } else {
        // insert book
        try{
          $pdo->beginTransaction();

          $st = $pdo->prepare("
            INSERT INTO used_books
              (user_id, title, author, category, grade_band, subject, description, price, `condition`, city, phone, status, created_at)
            VALUES
              (:uid, :title, :author, :cat, :gb, :sub, :descr, :price, :cond, :city, :phone, 'active', NOW())
          ");
          $st->execute([
            ":uid" => $meId,
            ":title" => $title,
            ":author" => $author,
            ":cat" => $CATEGORY,
            ":gb" => $grade_band,
            ":sub" => $subject,

            // ✅ вече НЕ позволяваме празни -> директно записваме текста
            ":descr" => $desc,
            ":city" => $city,

            ":price" => $p,
            ":cond" => $condition,
            ":phone" => ($phone === "" ? null : $phone),
          ]);

          $bookId = (int)$pdo->lastInsertId();

          // upload images
          $insImg = $pdo->prepare("
            INSERT INTO used_book_images (used_book_id, file_name, sort_order, created_at)
            VALUES (:bid, :fn, :so, NOW())
          ");

          $sortOrder = 0;

          foreach ($picked as $i){
            if ($errs[$i] !== UPLOAD_ERR_OK) {
              throw new RuntimeException("Грешка при качване на снимка.");
            }
            if ($sizes[$i] > 8 * 1024 * 1024) {
              throw new RuntimeException("Снимка е твърде голяма (макс 8MB).");
            }

            $orig = (string)$names[$i];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!is_img_ext($ext)) {
              throw new RuntimeException("Позволени формати: jpg, jpeg, png, webp.");
            }

            $fn = "ub2_" . $bookId . "_" . bin2hex(random_bytes(6)) . "." . $ext;
            $dest = $IMG_DIR_FS . $fn;

            if (!move_uploaded_file($tmp[$i], $dest)) {
              throw new RuntimeException("Неуспешно записване на снимка.");
            }

            $insImg->execute([
              ":bid" => $bookId,
              ":fn" => $fn,
              ":so" => $sortOrder++,
            ]);
          }

          $pdo->commit();

          // ✅ пращаме към VIEW-а за учебници
          header("Location: ".$base."textbook_secondhand_view.php?id=".$bookId);
          exit;

        } catch (Throwable $ex) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $err = "Грешка: " . $ex->getMessage();
        }
      }
    }
  }
}

$page_title = "Добави учебник втора ръка";
?>
<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand.css">

<body class="km-layout">
<?php require_once "nav.php"; ?>

<header class="tbs-hero" style="padding:40px 0 64px;">
  <div class="container tbs-hero-inner text-center">
    <div class="tbs-hero-icons" aria-hidden="true">➕ 📘</div>
    <h1 class="tbs-hero-title" style="font-size:clamp(28px,3.6vw,44px);">Добави учебник втора ръка</h1>
    <p class="tbs-hero-sub">Попълни данните и качи снимки.</p>
  </div>
  <div class="tbs-hero-wave" aria-hidden="true"></div>
</header>

<main class="tbs-main">
  <div class="container" style="max-width: 980px;">

    <div class="tbs-filter2" style="margin-top:-40px;">
      <?php if ($err): ?>
        <div class="alert alert-danger mb-3"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-12 col-lg-7">
          <label class="form-label tbs-label">Заглавие *</label>
          <input class="form-control tbs-input" name="title" value="<?= e($title) ?>" required>
        </div>

        <div class="col-12 col-lg-5">
          <label class="form-label tbs-label">Автор / Издателство *</label>
          <input class="form-control tbs-input" name="author" value="<?= e($author) ?>" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Клас *</label>
          <select class="form-select tbs-input" name="grade_band" id="gradeSel" required>
            <option value="1-4" <?= $grade_band==="1-4" ? "selected" : "" ?>>1–4 клас</option>
            <option value="5-7" <?= $grade_band==="5-7" ? "selected" : "" ?>>5–7 клас</option>
            <option value="8-12" <?= $grade_band==="8-12" ? "selected" : "" ?>>8–12 клас</option>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Предмет *</label>
          <select class="form-select tbs-input" name="subject" id="subjectSel" required></select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Състояние *</label>
          <select class="form-select tbs-input" name="condition" required>
            <option value="new" <?= $condition==="new" ? "selected" : "" ?>>Нова</option>
            <option value="like_new" <?= $condition==="like_new" ? "selected" : "" ?>>Като нова</option>
            <option value="good" <?= $condition==="good" ? "selected" : "" ?>>Добра</option>
            <option value="fair" <?= $condition==="fair" ? "selected" : "" ?>>Задоволителна</option>
            <option value="poor" <?= $condition==="poor" ? "selected" : "" ?>>Лоша</option>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Цена (€) *</label>
          <input class="form-control tbs-input" name="price" value="<?= e($price) ?>" inputmode="decimal" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Град *</label>
          <input
            class="form-control tbs-input"
            name="city"
            value="<?= e($city) ?>"
            required
            minlength="2"
            maxlength="60"
            pattern=".*[A-Za-zА-Яа-яЁёІіЇїЄє].*"
            title="Въведи град с букви (напр. София, Пловдив...)"
          >
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Телефон</label>
          <input class="form-control tbs-input" name="phone" value="<?= e($phone) ?>">
        </div>

        <div class="col-12">
          <label class="form-label tbs-label">Описание *</label>
          <textarea
            class="form-control tbs-input"
            name="description"
            rows="4"
            required
            minlength="10"
            maxlength="2000"
            placeholder="Напр. издание, забележки, комплект ли е..."
          ><?= e($desc) ?></textarea>
          <div class="form-text">Описанието трябва да съдържа текст (букви). Препоръчително: поне 10 знака.</div>
        </div>

        <div class="col-12">
          <label class="form-label tbs-label">Снимки * (до <?= (int)$MAX_FILES ?>)</label>
          <input class="form-control tbs-input" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
          <div class="form-text">Качи поне 1 снимка. Макс 8MB на снимка.</div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn tbs-filter2-go" type="submit" style="min-width: 180px;">Запази</button>
          <a class="btn tbs-filter2-clear" href="<?= e($base) ?>textbooks_secondhand.php">Отказ</a>
        </div>

        <input type="hidden" id="curSubject" value="<?= e($subject) ?>">
      </form>
    </div>

  </div>
</main>

<?php require_once "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(() => {
  const subjectsByGrade = <?= json_encode($SUBJECTS_BY_GRADE, JSON_UNESCAPED_UNICODE) ?>;

  const gradeSel = document.getElementById('gradeSel');
  const subjectSel = document.getElementById('subjectSel');
  const curSubject = document.getElementById('curSubject')?.value || '';

  function fillSubjects(grade){
    const map = subjectsByGrade[grade] || {};
    const prev = subjectSel.value || curSubject || '';

    subjectSel.innerHTML = '';
    Object.keys(map).forEach(slug => {
      const opt = document.createElement('option');
      opt.value = slug;
      opt.textContent = map[slug];
      subjectSel.appendChild(opt);
    });

    if (prev && Object.prototype.hasOwnProperty.call(map, prev)) subjectSel.value = prev;
    else subjectSel.selectedIndex = 0;
  }

  fillSubjects(gradeSel.value);
  gradeSel.addEventListener('change', () => fillSubjects(gradeSel.value));
})();
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
