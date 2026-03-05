<?php
session_start();

/*
  Start session to access login data.
  Session stores:
  - userName
  - role (admin / user)
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Page title -->
  <title>Online Portfolio</title>

  <!-- Main stylesheet -->
  <link rel="stylesheet" href="../css/home.css" />
</head>
<body>

  <!-- ===============================
       TOP NAVIGATION BAR
       =============================== -->
  <header class="topbar">
    <div class="container topbar__inner">

      <!-- Logo / Brand -->
      <a class="brand" href="home.php">
        <span class="brand__mark" aria-hidden="true"></span>
        <span class="brand__text">Portfolio</span>
      </a>

      <!-- Navigation links -->
      <nav class="topbar__nav">

        <!-- Public pages -->
        <a class="navlink" href="home.php">Home</a>
        <a class="navlink" href="portfolio.php">Portfolio</a>

        <!-- Admin-only link -->
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
          <a class="navlink" href="addFile.php">Upload File</a>
        <?php endif; ?>

        <!-- If user is logged in show username -->
        <?php if (!empty($_SESSION['userName'])): ?>

          <div class="user-menu">

            <!-- Username button -->
            <button class="user-btn" id="userBtn">
              <?= htmlspecialchars($_SESSION['userName']); ?>
            </button>

            <!-- Dropdown menu -->
            <div class="user-dropdown" id="userDropdown">
              <a href="logout.php">Logout</a>
            </div>

          </div>

        <?php else: ?>

          <!-- If not logged in show login button -->
          <a class="btn btn--primary" href="login.php">Login</a>

        <?php endif; ?>

      </nav>
    </div>
  </header>

  <!-- ===============================
       MAIN PAGE CONTENT
       =============================== -->
  <main class="container">

    <!-- HERO SECTION (INTRODUCTION) -->
    <section class="hero">

      <!-- Left content -->
      <div class="hero__content">

        <h1 class="hero__title">My IT Portfolio</h1>

        <p class="hero__subtitle">
          This website presents my academic work, learning evidence, and project outcomes.
          The portfolio is organised to demonstrate my progress and competencies across my studies.
        </p>

        <!-- Main action buttons -->
        <div class="hero__actions">

          <?php if (!empty($_SESSION['userName'])): ?>

            <!-- If logged in → go directly to portfolio -->
            <a class="btn btn--primary" href="portfolio.php">Go to Portfolio</a>

          <?php else: ?>

            <!-- If not logged in → show register/login -->
            <a class="btn btn--primary" href="register.php">Register</a>
            <a class="btn btn--secondary" href="login.php">Login</a>

          <?php endif; ?>

        </div>

        <!-- Quick personal facts -->
        <div class="hero__meta">
          <div class="pill">Name: Oleksii Khomiak</div>
          <div class="pill">Age: 18</div>
          <div class="pill">Location: Kyiv, Ukraine</div>
          <div class="pill">University: NHL Stenden</div>
        </div>

      </div>

      <!-- Right side information panel -->
      <aside class="hero__panel" aria-label="Profile overview">

        <div class="panel__header">
          <h2 class="panel__title">Profile overview</h2>
          <span class="badge badge--info">Academic</span>
        </div>

        <!-- Key information blocks -->
        <div class="panel__list">

          <div class="panel__item">
            <div class="panel__k">Purpose</div>
            <div class="panel__v">A curated archive of coursework and projects.</div>
          </div>

          <div class="panel__item">
            <div class="panel__k">Structure</div>
            <div class="panel__v">Year → Period → Category → Files.</div>
          </div>

          <div class="panel__item">
            <div class="panel__k">Contact</div>
            <div class="panel__v">
              GitHub: OleksiiKhomiak<br>
              Phone Number: +380 96 011 7377<br>
              Email: khomiak2007@gmail.com
            </div>
          </div>

        </div>
      </aside>
    </section>

    <!-- ===============================
         ABOUT SECTION
         =============================== -->
    <section class="section">

      <div class="section__head">
        <h2 class="section__title">About Me</h2>
        <p class="section__desc">
          Background information and the academic purpose of this portfolio.
        </p>
      </div>

      <div class="grid">

        <!-- Main about card -->
        <article class="card">

          <div class="card__head">
            <h4 class="card__title">Introduction</h4>
            <span class="badge badge--ok">Student</span>
          </div>

          <p class="card__desc">
            My name is <strong>Oleksii Khomiak</strong>. I am an <strong>18-year-old</strong> student from
            <strong>Kyiv, Ukraine</strong>, currently studying at
            <strong>NHL Stenden University of Applied Sciences</strong>.
          </p>

          <p class="card__desc">
            This website serves as my <strong>academic portfolio</strong>. It documents my progress through projects,
            assignments, reports, and reflections, and provides evidence of my skills development over time.
            Each file is organised by <strong>year, period, and category</strong> to make it easy to navigate and review.
          </p>

          <p class="card__desc" style="margin-bottom:0;">
            The portfolio is updated regularly and is intended for academic assessment, self-reflection,
            and professional presentation of my learning outcomes.
          </p>

        </article>

        <!-- Skills / Work areas card -->
        <aside class="card">

          <div class="card__head">
            <h4 class="card__title">Areas of Work</h4>
            <span class="badge badge--info">IT</span>
          </div>

          <p class="card__desc">
            This portfolio includes various academic and practical projects demonstrating my skills
            in software development, documentation, teamwork, and problem solving.
          </p>

          <!-- Highlight list -->
          <div class="filelist" aria-label="Highlights list">

            <div class="file">
              <div>
                <div class="file__name">Web Development</div>
                <div class="file__meta">Frontend development, UI design, and web applications</div>
              </div>
              <div class="file__meta">Focus</div>
            </div>

            <div class="file">
              <div>
                <div class="file__name">Project Development</div>
                <div class="file__meta">Planning, implementation, and documentation of projects</div>
              </div>
              <div class="file__meta">Field</div>
            </div>

            <div class="file">
              <div>
                <div class="file__name">Analysis & Documentation</div>
                <div class="file__meta">Technical documentation and analytical reports</div>
              </div>
              <div class="file__meta">Area</div>
            </div>

          </div>

          <!-- Quick action buttons -->
          <div class="card__actions">

            <a class="btn btn--secondary btn--sm" href="portfolio.php">Open Portfolio</a>

            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
              <a class="btn btn--ghost btn--sm" href="addFile.php">Upload File</a>
            <?php endif; ?>

          </div>

        </aside>
      </div>
    </section>

  </main>

  <!-- ===============================
       FOOTER
       =============================== -->
  <footer class="footer">

    <div class="container footer__inner">

      <div class="footer__left">
        <div class="footer__brand">
          <span class="brand__mark"></span>
          <span class="brand__text">Portfolio</span>
        </div>

        <!-- Dynamic year -->
        <p class="footer__copy">
          © <?= date("Y"); ?> My Portfolio. All rights reserved.
        </p>
      </div>

      <!-- Social links -->
      <div class="footer__socials">
        <a class="social" href="https://github.com/OleksiiKhomiak" target="_blank" rel="noopener noreferrer">GitHub</a>
        <a class="social" href="https://t.me/dntxry" target="_blank" rel="noopener noreferrer">Telegram</a>
        <a class="social" href="mailto:khomiak2007@gmail.com">Email</a>
      </div>

    </div>
  </footer>

  <script>

    /*
      Username dropdown logic
      - click username → open menu
      - click outside → close menu
    */

    const btn = document.getElementById("userBtn");
    const menu = document.getElementById("userDropdown");

    if(btn && menu){

      btn.addEventListener("click", () => {
        menu.style.display = (menu.style.display === "block") ? "none" : "block";
      });

      document.addEventListener("click", (e) => {
        if(!btn.contains(e.target) && !menu.contains(e.target)){
          menu.style.display = "none";
        }
      });

    }

  </script>

</body>
</html>