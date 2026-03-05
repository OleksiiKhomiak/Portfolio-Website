<?php
declare(strict_types=1);
session_start();

/*
  Connect to database using PDO.
  - ERRMODE_EXCEPTION: throw errors instead of silent failures
  - FETCH_ASSOC: results returned as associative arrays
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
  http_response_code(500);
  exit("DB error");
}

/*
  Read file ID from GET request.
  File ID must be a valid integer.
*/
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
  http_response_code(400);
  exit("Bad request");
}

/*
  Current logged user (if any).
  Used for access restriction checks.
*/
$userName = $_SESSION['userName'] ?? null;

/*
  Load file information from database
  and verify that the user has access to it.
*/
if ($userName) {

  /*
    If user is logged in:
    check that the file is NOT blocked for this user
    in Not_Available_Users table.
  */
  $stmt = $pdo->prepare("
    SELECT
      f.file_id,
      f.stored_name,
      f.original_name,
      f.mime_type,
      f.file_size
    FROM Files f
    WHERE f.file_id = :id
      AND NOT EXISTS (
        SELECT 1
        FROM Not_Available_Users nau
        WHERE nau.file_id = f.file_id
          AND nau.user_name = :uname
      )
    LIMIT 1
  ");

  $stmt->execute([
    ':id' => $id,
    ':uname' => $userName
  ]);

} else {

  /*
    If user is NOT logged in:
    allow download of any file.
    (You can change this logic later if needed.)
  */
  $stmt = $pdo->prepare("
    SELECT
      file_id,
      stored_name,
      original_name,
      mime_type,
      file_size
    FROM Files
    WHERE file_id = :id
    LIMIT 1
  ");

  $stmt->execute([':id' => $id]);
}

/*
  Fetch file row from database.
*/
$file = $stmt->fetch();

if (!$file) {
  http_response_code(404);
  exit("Not found");
}

/*
  Extract file information.
*/
$stored   = (string)($file['stored_name'] ?? '');
$original = (string)($file['original_name'] ?? '');
$mime     = (string)($file['mime_type'] ?? 'application/octet-stream');

if ($stored === '') {
  http_response_code(500);
  exit("Missing stored file name");
}

/*
  Build full path to file in uploads directory.
  IMPORTANT: make sure folder name matches your project.
*/
$UPLOAD_DIR = __DIR__ . "/../uploads";
$path = $UPLOAD_DIR . "/" . $stored;

/*
  Check if physical file exists on disk.
*/
if (!is_file($path)) {
  http_response_code(404);
  exit("File not found on disk");
}

/*
  Determine filename used for download.
  Prefer original filename, fallback to stored filename.
*/
$downloadName = $original !== '' ? $original : $stored;

/*
  Sanitize filename:
  remove strange characters or line breaks
  to avoid header injection.
*/
$downloadName = preg_replace('/[^\pL\pN\.\-\_\s\(\)\[\]]/u', '_', $downloadName) ?? 'download';

/*
  Send HTTP headers for file download.
*/
header("Content-Type: {$mime}");
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");

/*
  Output file content to browser.
*/
readfile($path);
exit;