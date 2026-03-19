<?php
require_once "db.php";
require_once "auth.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// base url (subfolder safe)
$base = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$base = ($base === "" || $base === ".") ? "/" : ($base . "/");

$IMG_URL = $base . "usedIMG/";
$TB_CATEGORY = "Учебници";

// user (само за бутона)
$me = is_logged_in() ? current_user() : null;
$logged = (bool)$me;

// -----------------------------
// Subjects by grade group (DB slugs)
// -----------------------------
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

// За “all” показваме обединение (само за dropdown когато клас=all)
$ALL_SUBJECTS = [];
foreach ($SUBJECTS_BY_GRADE as $grp => $arr) {
  foreach ($arr as $slug => $label) $ALL_SUBJECTS[$slug] = $label;
}
ksort($ALL_SUBJECTS);

// -----------------------------
// Filters (GET)
// -----------------------------
$q       = trim((string)($_GET["q"] ?? ""));
$grade   = trim((string)($_GET["grade"] ?? "all"));   // all | 1-4 | 5-7 | 8-12
$subject = trim((string)($_GET["subject"] ?? "all")); // all | <slug>
$sort    = trim((string)($_GET["sort"] ?? "new"));

// ако е избран клас, но предмет не е валиден за него -> reset
if ($grade !== "all" && $subject !== "all") {
  $allowed = $SUBJECTS_BY_GRADE[$grade] ?? [];
  if (!isset($allowed[$subject])) $subject = "all";
}

// sort
$orderSql = "b.created_at DESC";
if ($sort === "old")        $orderSql = "b.created_at ASC";
if ($sort === "price_asc")  $orderSql = "b.price ASC, b.created_at DESC";
if ($sort === "price_desc") $orderSql = "b.price DESC, b.created_at DESC";

// -----------------------------
// WHERE: реално към БД (grade_band + subject)
// + ЗАДЪЛЖИТЕЛНИ: city и description да не са празни
// -----------------------------
$where  = [
  "b.status = 'active'",
  "b.category = :cat",

  // ✅ “задължителни” полета (ако са празни – не показваме обявата)
  "COALESCE(NULLIF(TRIM(b.city), ''), '') <> ''",
  "COALESCE(NULLIF(TRIM(b.description), ''), '') <> ''",
];
$params = [":cat" => $TB_CATEGORY];

// search
if ($q !== "") {
  $where[] = "(b.title LIKE :q OR b.author LIKE :q OR b.description LIKE :q)";
  $params[":q"] = "%".$q."%";
}

// grade_band
if (in_array($grade, ["1-4","5-7","8-12"], true)) {
  $where[] = "b.grade_band = :gb";
  $params[":gb"] = $grade;
}

// subject
if ($subject !== "all") {
  $known = isset($ALL_SUBJECTS[$subject]);
  if ($known) {
    $where[] = "b.subject = :sub";
    $params[":sub"] = $subject;
  }
}

$whereSql = "WHERE " . implode(" AND ", $where);

// -----------------------------
// Fetch items + first image
// -----------------------------
$items = [];
$total = 0;

try{
  $sql = "
    SELECT
      b.*,
      u.name AS user_name,
      (
        SELECT i.file_name
        FROM used_book_images i
        WHERE i.used_book_id = b.id
        ORDER BY i.sort_order ASC, i.id ASC
        LIMIT 1
      ) AS first_image
    FROM used_books b
    LEFT JOIN users u ON u.id = b.user_id
    $whereSql
    ORDER BY $orderSql
    LIMIT 200
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  $stC = $pdo->prepare("SELECT COUNT(*) FROM used_books b $whereSql");
  $stC->execute($params);
  $total = (int)$stC->fetchColumn();
}catch(Throwable $ex){
  $items = [];
  $total = 0;
}

$page_title = "Учебници втора ръка";
?>

<!doctype html>
<html lang="bg">
<?php require_once "header.php"; ?>
<link rel="stylesheet" href="<?= e($base) ?>textbooks_secondhand.css">

<body class="km-layout">
<?php require_once "nav.php"; ?>

<header class="tbs-hero">
  <div class="container tbs-hero-inner text-center">
    <div class="tbs-hero-icons" aria-hidden="true">🎓 📘</div>
    <h1 class="tbs-hero-title">Учебници втора ръка</h1>
    <p class="tbs-hero-sub">Ползвани учебници от потребители — отлични цени и устойчив избор 📚</p>
  </div>
  <div class="tbs-hero-wave" aria-hidden="true"></div>
</header>

<main class="tbs-main">
  <div class="container">

    <section class="tbs-filter2">
      <div class="tbs-filter2-head">
        <div class="tbs-filter2-badge">🔎</div>
        <div class="tbs-filter2-title">
          <div class="tbs-filter2-title-main">Търсене на учебници</div>
          <div class="tbs-filter2-title-sub">Втора ръка (от потребители)</div>
        </div>

        <div class="tbs-filter2-right">
          <?php if ($logged): ?>
            <a class="btn tbs-filter2-add" href="<?= e($base) ?>textbook_secondhand_add.php">➕ Добави обява</a>
          <?php else: ?>
            <a class="btn tbs-filter2-add" href="<?= e($base) ?>login.php">🔒 Влез, за да добавиш</a>
          <?php endif; ?>
        </div>
      </div>

      <form id="tbsForm" class="row g-3 align-items-end mt-2" method="get" action="">
        <div class="col-12 col-lg-5">
          <label class="form-label tbs-label">Търсене</label>
          <input id="qInput" class="form-control tbs-input" name="q" value="<?= e($q) ?>" placeholder="Заглавие, автор, описание...">
        </div>

        <div class="col-12 col-lg-3">
          <label class="form-label tbs-label">Клас</label>
          <select class="form-select tbs-input" name="grade" id="gradeSel">
            <option value="all" <?= $grade==="all" ? "selected" : "" ?>>Всички класове</option>
            <option value="1-4" <?= $grade==="1-4" ? "selected" : "" ?>>1–4 клас</option>
            <option value="5-7" <?= $grade==="5-7" ? "selected" : "" ?>>5–7 клас</option>
            <option value="8-12" <?= $grade==="8-12" ? "selected" : "" ?>>8–12 клас</option>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label tbs-label">Предмет</label>
          <select class="form-select tbs-input" name="subject" id="subjectSel"></select>
        </div>

        <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
          <div class="d-flex gap-2">
            <label class="form-label tbs-label m-0 align-self-center">Сорт</label>
            <select class="form-select tbs-input" name="sort" id="sortSel" style="max-width:220px">
              <option value="new" <?= $sort==="new" ? "selected" : "" ?>>Най-нови</option>
              <option value="old" <?= $sort==="old" ? "selected" : "" ?>>Най-стари</option>
              <option value="price_asc" <?= $sort==="price_asc" ? "selected" : "" ?>>Цена ↑</option>
              <option value="price_desc" <?= $sort==="price_desc" ? "selected" : "" ?>>Цена ↓</option>
            </select>
          </div>

          <a class="btn tbs-filter2-clear ms-0" href="<?= e($base) ?>textbooks_secondhand.php">Изчисти</a>
          <div class="ms-auto tbs-found">Намерени <b><?= (int)$total ?></b> учебници</div>
        </div>

        <input type="hidden" id="curSubject" value="<?= e($subject) ?>">
      </form>
    </section>

    <section class="tbs-grid">
      <?php if (!$items): ?>
        <div class="tbs-empty">
          <div class="tbs-empty-ico">📭</div>
          <div class="tbs-empty-title">Няма резултати</div>
          <div class="tbs-empty-sub">Пробвай с други филтри или търсене.</div>
        </div>
      <?php else: ?>
        <?php foreach ($items as $it): ?>
          <?php
            $img = trim((string)($it["first_image"] ?? ""));
            $imgSrc = $img ? ($IMG_URL . $img) : ($base . "assets/placeholder-book.png");

            $title  = (string)($it["title"] ?? "");
            $author = (string)($it["author"] ?? "");
            $price  = (float)($it["price"] ?? 0);

            $city = trim((string)($it["city"] ?? ""));

            $cond = (string)($it["condition"] ?? "good");
            $condTxt = [
              "new" => "Нова",
              "like_new" => "Като нова",
              "good" => "Добра",
              "fair" => "Задоволителна",
              "poor" => "Лоша",
            ][$cond] ?? "Добра";

            $condClass = "tbs-cond-good";
            if ($cond === "new") $condClass = "tbs-cond-new";
            else if ($cond === "like_new") $condClass = "tbs-cond-vgood";
            else if ($cond === "fair") $condClass = "tbs-cond-ok";
            else if ($cond === "poor") $condClass = "tbs-cond-bad";

            $gb = (string)($it["grade_band"] ?? "");
            $subSlug = (string)($it["subject"] ?? "");
            $subLabel = "";
            if ($gb && isset($SUBJECTS_BY_GRADE[$gb][$subSlug])) $subLabel = $SUBJECTS_BY_GRADE[$gb][$subSlug];
            else if (isset($ALL_SUBJECTS[$subSlug])) $subLabel = $ALL_SUBJECTS[$subSlug];
          ?>
          <article class="tbs-card">
           <div class="tbs-card-img">

  <a class="tbs-card-link" href="<?= e($base) ?>textbook_secondhand_view.php?id=<?= (int)$it["id"] ?>">
    <img src="<?= e($imgSrc) ?>" alt="<?= e($title ?: "Учебник") ?>">
  </a>

  <!-- ❤️ Favorite -->
  <button
    class="tbs-like km-fav"
    type="button"
    data-id="<?= (int)$it["id"] ?>"
    data-type="usedtextbook"
    aria-label="Харесай"
    title="Харесай">
    <svg viewBox="0 0 24 24" aria-hidden="true" class="tbs-like-ico">
      <path d="M12 21s-7.2-4.6-9.6-8.6C.7 9.4 2 6.5 4.8 5.5c2-.7 4.2.1 5.6 1.8 1.4-1.7 3.6-2.5 5.6-1.8 2.8 1 4.1 3.9 2.4 6.9C19.2 16.4 12 21 12 21z"/>
    </svg>
  </button>

  <span class="tbs-chip"><?= e($subLabel ?: "Учебник") ?></span>
</div>





            <div class="tbs-card-body">
              <div class="tbs-card-top">
                <div class="tbs-title"><?= e($title) ?></div>
                <div class="tbs-author"><?= e($author) ?></div>
              </div>

              <div class="tbs-meta">
                <span class="tbs-cond <?= e($condClass) ?>"><?= e($condTxt) ?></span>
                <span class="tbs-seller">от <?= e($it["user_name"] ?: "Потребител") ?></span>
              </div>

              <?php if ($city !== ""): ?>
                <div class="tbs-meta" style="margin-top:6px">
                  <span class="tbs-seller">📍 <?= e($city) ?></span>
                </div>
              <?php endif; ?>

              <div class="tbs-card-bottom">
                <div class="tbs-price"><?= number_format($price, 2, '.', '') ?> €</div>

                <a class="btn btn-tbs-more" href="<?= e($base) ?>textbook_secondhand_view.php?id=<?= (int)$it["id"] ?>">Виж</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </div>
</main>

<?php require_once "footer.php"; ?>

<!-- ✅ Favorites (реален toggle) -->
<script src="favorites.js"></script>

<script>
document.addEventListener("DOMContentLoaded", async () => {
  // всички сърца на страницата
  const btns = Array.from(document.querySelectorAll(".km-fav.tbs-like[data-type='usedtextbook'][data-id]"));
  if (!btns.length) return;

  // ако favorites.js не е наличен – няма как
  if (!window.KMFav) return;

  try{
    // ✅ 1 заявка: взимаме всички любими на потребителя
    const res = await fetch("favorites_api.php?action=list", { cache: "no-store" });
    if (!res.ok) return;

    const data = await res.json();
    if (!data || !data.ok || !Array.isArray(data.items)) return;

    // правим сет от любимите usedtextbook id-та
    const likedIds = new Set(
      data.items
        .filter(x => x.item_type === "usedtextbook")
        .map(x => Number(x.item_id))
        .filter(Boolean)
    );

    // маркираме всички сърца
    btns.forEach(btn => {
      const id = Number(btn.dataset.id || 0);
      btn.classList.toggle("is-active", likedIds.has(id));
    });

  }catch(e){}
});
</script>

<!-- footer.php (преди </body>) -->


<script>
(() => {
  const subjectsByGrade = <?= json_encode($SUBJECTS_BY_GRADE, JSON_UNESCAPED_UNICODE) ?>;
  const allSubjects = <?= json_encode($ALL_SUBJECTS, JSON_UNESCAPED_UNICODE) ?>;

  const form = document.getElementById('tbsForm');
  const gradeSel = document.getElementById('gradeSel');
  const subjectSel = document.getElementById('subjectSel');
  const sortSel = document.getElementById('sortSel');
  const qInput = document.getElementById('qInput');
  const curSubject = document.getElementById('curSubject')?.value || 'all';

  function submitForm(){
    if (!form) return;
    if (form.requestSubmit) form.requestSubmit();
    else form.submit();
  }

  function fillSubjects(grade){
    const map = (grade && grade !== 'all') ? (subjectsByGrade[grade] || {}) : allSubjects;

    const prev = subjectSel.value || curSubject || 'all';

    subjectSel.innerHTML = '';

    const optAll = document.createElement('option');
    optAll.value = 'all';
    optAll.textContent = 'Всички предмети';
    subjectSel.appendChild(optAll);

    Object.keys(map).forEach(slug => {
      const opt = document.createElement('option');
      opt.value = slug;
      opt.textContent = map[slug];
      subjectSel.appendChild(opt);
    });

    const exists = (prev === 'all') || Object.prototype.hasOwnProperty.call(map, prev);
    subjectSel.value = exists ? prev : 'all';
  }

  // initial
  fillSubjects(gradeSel.value);

  // --- AUTO FILTER ---
  let typingTimer = null;
  if (qInput){
    qInput.addEventListener('input', () => {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => submitForm(), 350);
    });
  }

  if (sortSel){
    sortSel.addEventListener('change', () => submitForm());
  }

  if (gradeSel){
    gradeSel.addEventListener('change', () => {
      fillSubjects(gradeSel.value);
      submitForm();
    });
  }

  if (subjectSel){
    subjectSel.addEventListener('change', () => submitForm());
  }
})();
</script>



</body>
</html>
