<?php
declare(strict_types=1);
session_start();

/*
  Only admin users are allowed to change file visibility.
  If someone else tries to access this script, return 403.
*/
if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

/*
  This script only accepts POST requests.
  If someone tries GET or other methods – reject it.
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

/*
  Read form data.
  action  -> hide / unhide
  file_id -> which file we are modifying
  user_name -> user affected by visibility rule
*/
$action = $_POST['action'] ?? '';
$fileId = (int)($_POST['file_id'] ?? 0);
$userName = trim((string)($_POST['user_name'] ?? ''));

/*
  Basic validation:
  - action must be hide or unhide
  - file id must be valid
  - username cannot be empty
*/
if (!in_array($action, ['hide', 'unhide'], true) || $fileId <= 0 || $userName === '') {
  http_response_code(400);
  exit('Bad request');
}

try {

  /*
    Create database connection.
    Using PDO with exception mode enabled.
  */
  $pdo = new PDO(
    "mysql:host=mysql;dbname=Portfolio;charset=utf8mb4",
    "root",
    "qwerty",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  /*
    Check if the user actually exists.
    We store visibility restrictions by user_name,
    so we must confirm the username is valid.
  */
  $u = $pdo->prepare("SELECT user_id FROM Users WHERE user_name = :n LIMIT 1");
  $u->execute([':n' => $userName]);

  if (!$u->fetch()) {
    // If user does not exist – just return to portfolio
    header("Location: portfolio.php");
    exit;
  }

  /*
    If action = hide:
    Add record into Not_Available_Users table
    BUT only if the rule does not already exist.
  */
  if ($action === 'hide') {

    $ins = $pdo->prepare("
      INSERT INTO Not_Available_Users (file_id, user_name)
      SELECT :fid, :uname
      WHERE NOT EXISTS (
        SELECT 1 FROM Not_Available_Users
        WHERE file_id = :fid AND user_name = :uname
      )
    ");

    $ins->execute([
      ':fid' => $fileId,
      ':uname' => $userName
    ]);

  } else {

    /*
      If action = unhide:
      Remove the visibility restriction for this user.
    */
    $del = $pdo->prepare("
      DELETE FROM Not_Available_Users
      WHERE file_id = :fid AND user_name = :uname
      LIMIT 1
    ");

    $del->execute([
      ':fid' => $fileId,
      ':uname' => $userName
    ]);

  }

  /*
    After action is complete, redirect back to portfolio.
    This also prevents form resubmission.
  */
  header("Location: portfolio.php");
  exit;

} catch (Throwable $e) {

  /*
    If something fails (DB error, etc.)
    return HTTP 500 and show safe error message.
  */
  http_response_code(500);
  exit("Error: " . htmlspecialchars($e->getMessage()));
}