<?php
declare(strict_types=1);
session_start();

/*
  ------------------------------------------------------------
  Access control
  ------------------------------------------------------------
  1) User must be logged in
  2) Only admin can access this page
*/
if (empty($_SESSION['userName'])) {
  header("Location: login.php");
  exit;
}

if (($_SESSION['role'] ?? 'user') !== 'admin') {
  http_response_code(403);
  exit("403 Forbidden: Admin only");
}

/*
  ------------------------------------------------------------
  Database connection helper (single source of truth)
  ------------------------------------------------------------
  NOTE: Keeping it as a function makes it easy to reuse anywhere.
*/
function db(): PDO {
  $host = 'mysql';
  $db   = 'Portfolio';
  $user = 'root';
  $pass = 'qwerty';

  $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

/*
  ------------------------------------------------------------
  Configuration
  ------------------------------------------------------------
  - max upload size
  - allowed MIME types
  - upload directory
*/
$MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

$ALLOWED_MIME = [
  'application/pdf' => 'pdf',
  'image/jpeg'      => 'jpg',
  'image/png'       => 'png',
  'application/zip' => 'zip',
];

$UPLOAD_DIR = __DIR__ . '/../uploads';
if (!is_dir($UPLOAD_DIR)) {
  @mkdir($UPLOAD_DIR, 0755, true);
}

/*
  ------------------------------------------------------------
  Helper functions
  ------------------------------------------------------------
*/
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean_text(string $s, int $maxLen): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s) ?? '';
  if (mb_strlen($s) > $maxLen) {
    $s = mb_substr($s, 0, $maxLen);
  }
  return $s;
}

/*
  Username validation:
  - 3–32 chars
  - letters, numbers, dot, underscore, dash
*/
function is_valid_username(string $u): bool {
  return (bool)preg_match('/^[A-Za-z0-9._-]{3,32}$/', $u);
}

/*
  ------------------------------------------------------------
  CSRF protection
  ------------------------------------------------------------
  We store a random token in session and verify it on POST.
*/
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors  = [];
$success = false;

/*
  Old values are kept so the form does not reset after validation errors.
*/
$old = [
  'title'        => '',
  'description'  => '',
  'blocked_user' => '',
  'year'         => '1',
  'period'       => '1',
  'category'     => 'General',
];

/*
  ------------------------------------------------------------
  Handle POST (upload)
  ------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Save submitted values for re-rendering the form
  $old['title']        = $_POST['title'] ?? '';
  $old['description']  = $_POST['description'] ?? '';
  $old['blocked_user'] = $_POST['blocked_user'] ?? '';
  $old['year']         = $_POST['year'] ?? '1';
  $old['period']       = $_POST['period'] ?? '1';
  $old['category']     = $_POST['category'] ?? 'General';

  /*
    CSRF validation
  */
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $errors[] = 'Security error (CSRF). Please reload the page and try again.';
  }

  /*
    Sanitize and validate input fields
  */
  $title        = clean_text($_POST['title'] ?? '', 120);
  $description  = clean_text($_POST['description'] ?? '', 255);
  $blocked_user = clean_text($_POST['blocked_user'] ?? '', 32);

  $year     = (int)($_POST['year'] ?? 0);
  $period   = (int)($_POST['period'] ?? 0);
  $category = clean_text($_POST['category'] ?? '', 255);

  if ($title === '' || mb_strlen($title) < 3) {
    $errors[] = 'Title is required (min 3 characters).';
  }

  if ($year < 1 || $year > 4) {
    $errors[] = 'Year must be between 1 and 4.';
  }

  if ($period < 1 || $period > 3) {
    $errors[] = 'Period must be between 1 and 3.';
  }

  if ($category === '' || mb_strlen($category) < 2) {
    $errors[] = 'Category is required.';
  }

  if ($blocked_user !== '' && !is_valid_username($blocked_user)) {
    $errors[] = 'Blocked username is invalid (letters/numbers . _ - ; 3–32 chars).';
  }

  /*
    Validate uploaded file:
    - upload error
    - size
    - MIME type using finfo (more reliable than $_FILES['type'])
  */
  if (!isset($_FILES['file'])) {
    $errors[] = 'File is required.';
  } else {

    $f = $_FILES['file'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Upload failed. Error code: ' . $f['error'];
    } else {

      if ($f['size'] <= 0 || $f['size'] > $MAX_FILE_SIZE) {
        $errors[] = 'File size must be between 1 byte and 10 MB.';
      }

      $tmp = $f['tmp_name'];
      $fi  = new finfo(FILEINFO_MIME_TYPE);
      $mime = $fi->file($tmp) ?: '';

      if (!array_key_exists($mime, $ALLOWED_MIME)) {
        $errors[] = 'File type not allowed. Allowed: PDF, JPG, PNG, ZIP.';
      }
    }
  }

  /*
    If blocked_user is provided, make sure that user exists in Users table.
  */
  if (!$errors && $blocked_user !== '') {
    try {
      $pdo = db();
      $st = $pdo->prepare("SELECT user_id FROM Users WHERE user_name = :u LIMIT 1");
      $st->execute([':u' => $blocked_user]);

      if (!$st->fetch()) {
        $errors[] = "User '{$blocked_user}' not found in database.";
      }
    } catch (Throwable $ex) {
      $errors[] = "Database error while checking blocked user.";
    }
  }

  /*
    If everything is valid:
    1) Move uploaded file to /uploads
    2) Insert file row into Files table
    3) Optionally insert restriction into Not_Available_Users
  */
  if (!$errors) {

    $f   = $_FILES['file'];
    $tmp = $f['tmp_name'];

    $fi   = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($tmp) ?: '';
    $ext  = $ALLOWED_MIME[$mime];

    // Generate safe server filename
    $random   = bin2hex(random_bytes(16));
    $safeName = $random . '.' . $ext;

    $destPath = $UPLOAD_DIR . '/' . $safeName;

    if (!move_uploaded_file($tmp, $destPath)) {
      $errors[] = 'Could not save the uploaded file.';
    } else {

      try {
        $pdo = db();
        $pdo->beginTransaction();

        // Insert file metadata into Files table
        $stmt = $pdo->prepare("
          INSERT INTO Files
            (file_title, file_year, file_period, file_description, stored_name, original_name, mime_type, file_size, categories)
          VALUES
            (:title, :year, :period, :descr, :stored, :orig, :mime, :size, :cat)
        ");

        $stmt->execute([
          ':title'  => $title,
          ':year'   => $year,
          ':period' => $period,
          ':descr'  => ($description !== '' ? $description : '—'),
          ':stored' => $safeName,
          ':orig'   => $f['name'],
          ':mime'   => $mime,
          ':size'   => (int)$f['size'],
          ':cat'    => $category,
        ]);

        $fileId = (int)$pdo->lastInsertId();

        // Optional: add restriction for one user
        if ($blocked_user !== '') {
          $blk = $pdo->prepare("
            INSERT INTO Not_Available_Users (user_name, file_id)
            VALUES (:u, :fid)
          ");
          $blk->execute([
            ':u'   => $blocked_user,
            ':fid' => $fileId
          ]);
        }

        $pdo->commit();

        // Mark success and refresh CSRF token (good practice)
        $success = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Clear form values after successful upload
        $old = [
          'title'        => '',
          'description'  => '',
          'blocked_user' => '',
          'year'         => '1',
          'period'       => '1',
          'category'     => 'General',
        ];

      } catch (Throwable $ex) {

        // Roll back DB changes and remove uploaded file to keep system consistent
        if (isset($pdo)) {
          $pdo->rollBack();
        }
        @unlink($destPath);

        $errors[] = 'Database error: ' . $ex->getMessage();
      }
    }
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Upload File • Portfolio</title>
  <link rel="stylesheet" href="../css/addFile.css" />
  <link rel="stylesheet" href="../css/home.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar__inner">
    <a class="brand" href="home.php">
      <span class="brand__mark" aria-hidden="true"></span>
      <span class="brand__text">Portfolio</span>
    </a>

    <nav class="topbar__nav">
      <a class="navlink" href="home.php">Home</a>
      <a class="navlink" href="portfolio.php">Portfolio</a>
      <a class="navlink" href="addFile.php">Upload File</a>

      <?php if (!empty($_SESSION['userName'])): ?>
        <div class="user-menu">
          <button class="user-btn" id="userBtn"><?= e((string)$_SESSION['userName']); ?></button>
          <div class="user-dropdown" id="userDropdown">
            <a href="logout.php">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a class="btn btn--primary" href="login.php">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container container--wide">

  <section class="pagehead">
    <div class="pagehead__left">
      <h1 class="pagehead__title">Upload File</h1>
      <p class="pagehead__subtitle">
        Add a file to a category. Optionally block access for a specific username.
      </p>
    </div>
    <div class="pagehead__right">
      <a class="btn btn--ghost" href="home.php">← Back</a>
    </div>
  </section>

  <?php if (!empty($errors)): ?>
    <div class="note">
      <strong>Fix these errors:</strong>
      <ul style="margin:8px 0 0; padding-left:18px;">
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php elseif ($success): ?>
    <div class="note" style="border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.10);">
      <strong>Success:</strong> File uploaded and saved to database.
    </div>
  <?php endif; ?>

  <section class="grid grid--upload">

    <article class="card card--form">
      <header class="card__head">
        <h2 class="card__title">File Details</h2>
        <span class="badge badge--info">Admin</span>
      </header>

      <form class="form" action="addFile.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">

        <div class="field">
          <label class="label" for="title">Title</label>
          <input class="input" id="title" name="title" type="text"
                 value="<?= e($old['title']); ?>"
                 placeholder="e.g. Week 3 Reflection" required />
        </div>

        <div class="field">
          <label class="label" for="desc">Description</label>
          <textarea class="textarea" id="desc" name="description" rows="4"
            placeholder="Briefly describe what this file is..."><?= e($old['description']); ?></textarea>
        </div>

        <div class="grid grid--mini">
          <div class="field">
            <label class="label" for="year">Year</label>
            <select class="select" id="year" name="year" required>
              <?php for ($y=1; $y<=4; $y++): ?>
                <option value="<?= $y ?>" <?= ((string)$y === (string)$old['year']) ? 'selected' : '' ?>>
                  Year <?= $y ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="field">
            <label class="label" for="period">Period</label>
            <select class="select" id="period" name="period" required>
              <?php for ($p=1; $p<=3; $p++): ?>
                <option value="<?= $p ?>" <?= ((string)$p === (string)$old['period']) ? 'selected' : '' ?>>
                  Period <?= $p ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label class="label" for="category">Category</label>
          <select class="select" id="category" name="category" required>
            <?php
              $cats = ['WebDev', 'Professional Skills', 'Database', 'BattleBot', 'OOP', 'General', 'Reflections'];
              foreach ($cats as $c):
            ?>
              <option value="<?= e($c) ?>" <?= ($c === $old['category']) ? 'selected' : '' ?>>
                <?= e($c) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="label" for="file">Choose File</label>

          <label class="drop" for="file">
            <div class="drop__icon" aria-hidden="true">⬆</div>
            <div class="drop__text"><strong>Click to upload</strong> or drag & drop</div>
            <div class="drop__hint">Allowed: PDF, JPG, PNG, ZIP (max 10MB)</div>
          </label>

          <input class="fileinput" id="file" name="file" type="file" required />
        </div>

        <div class="divider"></div>

        <div class="field">
          <div class="field__head">
            <label class="label" for="blocked_user">Block access for username (optional)</label>
            <span class="muted small">This user will NOT be able to download/view this file.</span>
          </div>

          <input class="input" id="blocked_user" name="blocked_user" type="text"
                 value="<?= e($old['blocked_user']); ?>"
                 placeholder="Enter username to block"
                 autocomplete="off" />
        </div>

        <div class="form__actions">
          <button class="btn btn--primary" type="submit">Upload</button>
          <a class="btn btn--secondary" href="portfolio.php">Cancel</a>
        </div>

      </form>
    </article>

    <aside class="card card--rules">
      <header class="rules__head">
        <h2 class="card__title">Upload Rules</h2>
      </header>

      <p class="card__desc">
        Keep uploads consistent and easy to review.
      </p>

      <div class="rules__list">
        <div class="rule">
          <div class="rule__k">1) Meaningful titles</div>
          <div class="rule__v">Use clear names to help reviewers understand the content.</div>
        </div>

        <div class="rule">
          <div class="rule__k">2) Keep it academic</div>
          <div class="rule__v">Upload coursework, reports, evidence, reflections, and project files.</div>
        </div>

        <div class="rule">
          <div class="rule__k">3) Correct placement</div>
          <div class="rule__v">Choose Year / Period / Category carefully so the file appears in the correct place.</div>
        </div>

        <div class="rule">
          <div class="rule__k">4) Access control</div>
          <div class="rule__v">Optionally restrict visibility for selected usernames.</div>
        </div>
      </div>
    </aside>

  </section>

</main>

<footer class="footer">
  <div class="container footer__inner">
    <div class="footer__left">
      <div class="footer__brand">
        <span class="brand__mark"></span>
        <span class="brand__text">Portfolio</span>
      </div>
      <p class="footer__copy">© <?= date("Y"); ?> My Portfolio. All rights reserved.</p>
    </div>

    <div class="footer__socials">
      <a class="social" href="https://github.com/OleksiiKhomiak" target="_blank" rel="noopener noreferrer">GitHub</a>
      <a class="social" href="https://t.me/dntxry" target="_blank" rel="noopener noreferrer">Telegram</a>
      <a class="social" href="mailto:khomiak2007@gmail.com">Email</a>
    </div>
  </div>
</footer>

<script>
  // Show selected filename inside the drop zone label
  const file = document.getElementById('file');
  const dropText = document.querySelector('.drop__text');

  file?.addEventListener('change', () => {
    if (file.files && file.files[0]) {
      dropText.innerHTML = `<strong>${file.files[0].name}</strong>`;
    }
  });

  // User dropdown (topbar)
  const btn = document.getElementById("userBtn");
  const menu = document.getElementById("userDropdown");

  if (btn && menu) {
    btn.addEventListener("click", () => {
      menu.style.display = (menu.style.display === "block") ? "none" : "block";
    });

    document.addEventListener("click", (e) => {
      if (!btn.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = "none";
      }
    });
  }
</script>

</body>
</html>