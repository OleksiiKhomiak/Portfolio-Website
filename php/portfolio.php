<?php
declare(strict_types=1);
session_start();

/*
  Connect to DB (same settings as in other pages).
  - ERRMODE_EXCEPTION: throw errors instead of silent fails
  - FETCH_ASSOC: return arrays with column names
*/
try {
  $pdo = new PDO(
    "mysql:host=mysql;dbname=Portfolio;charset=utf8mb4",
    "root",
    "qwerty",
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  die("DB error: " . htmlspecialchars($e->getMessage()));
}

/*
  Simple HTML escaping helper:
  - prevents XSS when printing user/content data into HTML
*/
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
  Session info:
  - userName is set after login
  - role is used to enable admin-only actions (upload/delete/hide)
*/
$userName = $_SESSION['userName'] ?? null;
$isAdmin  = (($_SESSION['role'] ?? '') === 'admin');

/*
  Load files:
  If user is logged in -> hide files that are blocked for this username.
  If not logged in -> show all files (you can change this logic later if needed).
*/
if ($userName) {
  $stmt = $pdo->prepare("
    SELECT f.*
    FROM Files f
    WHERE NOT EXISTS (
      SELECT 1
      FROM Not_Available_Users nau
      WHERE nau.file_id = f.file_id
        AND nau.user_name = :uname
    )
    ORDER BY f.file_year ASC, f.file_period ASC, f.categories ASC, f.created_at DESC
  ");
  $stmt->execute([':uname' => $userName]);
  $files = $stmt->fetchAll();
} else {
  // Not logged in: show all files (optional behavior)
  $files = $pdo->query("
    SELECT *
    FROM Files
    ORDER BY file_year ASC, file_period ASC, categories ASC, created_at DESC
  ")->fetchAll();
}

/*
  Build nested structure for UI:
  $tree[year][period][category] = [files...]
  This makes it easy to render Years -> Periods -> Categories.
*/
$tree = [];

foreach ($files as $f) {
  $year   = (int)$f['file_year'];
  $period = (int)$f['file_period'];
  $cat    = trim((string)$f['categories']);

  if ($cat === '') {
    $cat = 'Uncategorized';
  }

  $tree[$year][$period][$cat][] = $f;
}

/*
  Admin-only: preload blocked users list per file, so menu can show:
  - currently blocked usernames
  - quick "Unhide" buttons
*/
$blockedMap = []; // $blockedMap[file_id] = ['user1','user2',...]
if ($isAdmin) {
  $rows = $pdo->query("SELECT file_id, user_name FROM Not_Available_Users")->fetchAll();
  foreach ($rows as $r) {
    $fid = (int)$r['file_id'];
    $uname = (string)$r['user_name'];
    $blockedMap[$fid][] = $uname;
  }
}

/*
  Even if DB is empty we still show the structure (Year 1-4, Period 1-4).
  That way page doesn't look empty.
*/
$yearsToShow   = [1,2,3,4];
$periodsToShow = [1,2,3,4];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Portfolio</title>
  <link rel="stylesheet" href="../css/home.css" />
  <link rel="stylesheet" href="../css/addFile.css" />
  <link rel="stylesheet" href="../css/portfolio.css">
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

      <?php if ($isAdmin): ?>
        <!-- Admin-only link -->
        <a class="navlink" href="addFile.php">Upload File</a>
      <?php endif; ?>

      <?php if (!empty($_SESSION['userName'])): ?>
        <!-- Logged user menu -->
        <div class="user-menu">
          <button class="user-btn" id="userBtn"><?= e((string)$_SESSION['userName']) ?></button>
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

<main class="container">

  <section class="pagehead">
    <div class="pagehead__left">
      <h1 class="pagehead__title">My Portfolio</h1>
      <p class="pagehead__subtitle">
        My work is organised by year → period → category. Each uploaded file appears exactly where I selected during upload.
      </p>
    </div>

    <?php if ($isAdmin): ?>
      <!-- Admin-only button -->
      <div class="pagehead__right">
        <a class="btn btn--secondary" href="addFile.php">+ Upload new</a>
      </div>
    <?php endif; ?>
  </section>

  <?php foreach ($yearsToShow as $y): ?>
    <?php
      // Check if this year has any files
      $yearPeriods  = $tree[$y] ?? [];
      $yearHasFiles = !empty($yearPeriods);
    ?>

    <details class="acc acc--year" <?= $yearHasFiles ? 'open' : '' ?>>
      <summary class="acc__summary">
        <div class="acc__left">
          <span class="acc__title">Year <?= (int)$y ?></span>
          <span class="acc__meta"><?= $yearHasFiles ? 'Periods: 1–4' : 'No files yet' ?></span>
        </div>
        <span class="acc__chev" aria-hidden="true">⌄</span>
      </summary>

      <div class="acc__content">
        <?php foreach ($periodsToShow as $p): ?>
          <?php
            // Data for this period (category => files)
            $periodData = $tree[$y][$p] ?? [];
            $periodHas  = !empty($periodData);
            $catCount   = $periodHas ? count($periodData) : 0;
          ?>

          <details class="acc acc--period">
            <summary class="acc__summary acc__summary--period">
              <div class="acc__left">
                <span class="acc__title">Period <?= (int)$p ?></span>
                <span class="acc__meta"><?= $periodHas ? ($catCount . ' categories') : 'Empty' ?></span>
              </div>
              <span class="acc__chev" aria-hidden="true">⌄</span>
            </summary>

            <div class="acc__content">
              <?php if (!$periodHas): ?>
                <!-- Empty state -->
                <div class="empty">No files uploaded for this period yet.</div>
              <?php else: ?>

                <div class="cats">
                  <?php foreach ($periodData as $catName => $catFiles): ?>
                    <div class="card">
                      <div class="card__head">
                        <h4 class="card__title"><?= e((string)$catName) ?></h4>
                        <span class="badge badge--info"><?= count($catFiles) ?> files</span>
                      </div>

                      <div class="filelist">
                        <?php foreach ($catFiles as $f): ?>
                          <?php
                            // File fields
                            $fid = (int)$f['file_id'];

                            $title = trim((string)($f['file_title'] ?? ''));
                            if ($title === '') $title = 'File #' . $fid;

                            $desc = trim((string)($f['file_description'] ?? ''));
                            $when = $f['created_at'] ?? $f['uploaded_at'] ?? '';

                            // Download link (download.php should validate access again!)
                            $href = "download.php?id=" . $fid;

                            // Admin-only: list of blocked usernames for this file
                            $blocked = $blockedMap[$fid] ?? [];
                          ?>

                          <div class="filerow">
                            <a class="file" href="<?= e($href) ?>" title="Download">
                              <div class="file__name"><?= e($title) ?></div>
                              <div class="file__meta">
                                <?= $desc !== '' ? e($desc) . " • " : "" ?>
                                <?= $when !== '' ? "Uploaded: " . e((string)$when) : "" ?>
                              </div>
                            </a>

                            <?php if ($isAdmin): ?>
                              <!-- Admin actions menu (⋯) -->
                              <div class="dots">
                                <button class="dots__btn" type="button" aria-label="File actions">⋯</button>

                                <div class="dots__menu">
                                  <!-- Delete file -->
                                  <form action="file_action.php" method="POST" onsubmit="return confirm('Delete this file?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file_id" value="<?= $fid ?>">
                                    <button type="submit" class="dots__item dots__item--danger">Delete</button>
                                  </form>

                                  <div class="dots__divider"></div>

                                  <!-- Hide file for specific user -->
                                  <form action="toggle_visibility.php" method="POST">
                                    <input type="hidden" name="action" value="hide">
                                    <input type="hidden" name="file_id" value="<?= $fid ?>">
                                    <input type="text" name="user_name" class="dots__input" placeholder="Username to block" required>
                                    <button type="submit" class="dots__item">Hide for user</button>
                                  </form>

                                  <?php if (!empty($blocked)): ?>
                                    <div class="dots__divider"></div>
                                    <div class="dots__label">Blocked:</div>

                                    <!-- List blocked users + unhide button -->
                                    <?php foreach ($blocked as $u): ?>
                                      <div class="dots__row">
                                        <span class="dots__user"><?= e($u) ?></span>

                                        <form action="toggle_visibility.php" method="POST">
                                          <input type="hidden" name="action" value="unhide">
                                          <input type="hidden" name="file_id" value="<?= $fid ?>">
                                          <input type="hidden" name="user_name" value="<?= e($u) ?>">
                                          <button type="submit" class="dots__small">Unhide</button>
                                        </form>
                                      </div>
                                    <?php endforeach; ?>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endif; ?>

                          </div>

                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

              <?php endif; ?>
            </div>
          </details>

        <?php endforeach; ?>
      </div>
    </details>

  <?php endforeach; ?>

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
  /*
    Username dropdown (Logout):
    - toggle on click
    - close when clicking outside
  */
  const btn = document.getElementById("userBtn");
  const menu = document.getElementById("userDropdown");

  if(btn && menu){
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.style.display = (menu.style.display === "block") ? "none" : "block";
    });

    document.addEventListener("click", (e) => {
      if(!btn.contains(e.target) && !menu.contains(e.target)){
        menu.style.display = "none";
      }
    });
  }

  /*
    Dots menu (⋯) for admin:
    - open/close per file
    - close all when clicking outside
    - IMPORTANT: stopPropagation inside menu so it does not close when typing/clicking inputs
  */
  document.querySelectorAll('.dots__btn').forEach((b) => {
    b.addEventListener('click', (e) => {
      e.stopPropagation();

      const wrapper = b.closest('.dots');
      const m = wrapper.querySelector('.dots__menu');

      const isOpen = m.style.display === 'block';

      // close all other menus
      document.querySelectorAll('.dots__menu').forEach(x => x.style.display = 'none');

      // toggle current
      m.style.display = isOpen ? 'none' : 'block';
    });
  });

  // prevent menu from closing when clicking inside (inputs/buttons/forms)
  document.querySelectorAll('.dots__menu').forEach((m) => {
    m.addEventListener('click', (e) => e.stopPropagation());
  });

  // click outside closes all dots menus
  document.addEventListener('click', () => {
    document.querySelectorAll('.dots__menu').forEach(m => m.style.display = 'none');
  });
</script>

</body>
</html>