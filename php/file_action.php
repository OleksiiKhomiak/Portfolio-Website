<?php
declare(strict_types=1);
session_start();

/*
  Security check:
  Only admin users are allowed to perform file actions.
*/
if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

/*
  Only POST requests are allowed.
  This prevents users from triggering actions via URL.
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

/*
  Read action parameters from the form.
*/
$action = $_POST['action'] ?? '';
$fileId = (int)($_POST['file_id'] ?? 0);

/*
  Basic validation:
  - file_id must be valid
  - action must be "delete"
*/
if ($fileId <= 0 || $action !== 'delete') {
  http_response_code(400);
  exit('Bad request');
}

try {

  /*
    Connect to database using PDO.
    We enable exceptions to catch SQL errors.
  */
  $pdo = new PDO(
    "mysql:host=mysql;dbname=Portfolio;charset=utf8mb4",
    "root",
    "qwerty",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  /*
    Step 1:
    Get the stored filename from the database.
    We need this to remove the physical file from the server.
  */
  $stmt = $pdo->prepare("
    SELECT stored_name
    FROM Files
    WHERE file_id = :id
    LIMIT 1
  ");

  $stmt->execute([':id' => $fileId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  /*
    If file does not exist in database,
    simply return to portfolio page.
  */
  if (!$row) {
    header("Location: portfolio.php");
    exit;
  }

  $stored = (string)$row['stored_name'];

  /*
    Step 2:
    Remove all visibility restrictions for this file.
    (cleanup rows from Not_Available_Users table)
  */
  $pdo->prepare("
    DELETE FROM Not_Available_Users
    WHERE file_id = :id
  ")->execute([':id' => $fileId]);

  /*
    Step 3:
    Remove file record from Files table.
  */
  $pdo->prepare("
    DELETE FROM Files
    WHERE file_id = :id
  ")->execute([':id' => $fileId]);

  /*
    Step 4:
    Delete the physical file from the uploads folder.
    IMPORTANT: adjust path if your uploads directory is different.
  */
  $uploadDir = __DIR__ . "/../uploads/";
  $path = $uploadDir . $stored;

  if ($stored !== '' && file_exists($path)) {
    @unlink($path); // delete file from disk
  }

  /*
    After deletion redirect back to portfolio page.
  */
  header("Location: portfolio.php");
  exit;

} catch (Throwable $e) {

  /*
    If something goes wrong (DB error, filesystem error),
    return HTTP 500 and show safe error message.
  */
  http_response_code(500);
  exit("Error: " . htmlspecialchars($e->getMessage()));
}