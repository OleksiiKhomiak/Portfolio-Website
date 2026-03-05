<?php
session_start();

/*
  Flash error message:
  - read it from session
  - remove it immediately so it shows only once
*/
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

/*
  Helper function: print a safe error and stop execution.
*/
function printError(string $err): void {
    echo "<h1>The following error occurred</h1><p>" . htmlspecialchars($err) . "</p>";
    exit;
}

/*
  Connect to DB using PDO.
  If connection fails, stop and show the error.
*/
try {
    $dbHandler = new PDO(
        "mysql:host=mysql;dbname=Portfolio;charset=utf8mb4",
        "root",
        "qwerty",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $ex) {
    printError($ex->getMessage());
}

/*
  Handle POST request (registration form submission).
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read and sanitize user input
    $name = trim((string)filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim((string)filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = trim((string)($_POST['password'] ?? ''));

    /*
      Basic validation:
      - no empty fields allowed
    */
    if ($name === '' || $email === '' || $password === '') {
        $_SESSION['error'] = "All fields must be filled!";
        header("Location: register.php"); // redirect prevents form resubmission on refresh
        exit;
    }

    /*
      Check if a user with the same email OR username already exists.
      This is faster and cleaner than selecting all users and looping.
    */
    try {
        $check = $dbHandler->prepare("
            SELECT user_id
            FROM Users
            WHERE user_email = :em OR user_name = :un
            LIMIT 1
        ");
        $check->execute([
            ':em' => $email,
            ':un' => $name
        ]);

        if ($check->fetch()) {
            $_SESSION['error'] = "A user with this email or username already exists.";
            header("Location: register.php");
            exit;
        }

        /*
          Hash the password before storing it.
          Never store plain text passwords!
        */
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        /*
          Insert new user into database.
          Default role can be "user" (we can add it if you have a role column).
        */
        $insert = $dbHandler->prepare("
            INSERT INTO Users (user_name, user_email, user_password)
            VALUES (:us, :em, :ps)
        ");
        $insert->execute([
            ':us' => $name,
            ':em' => $email,
            ':ps' => $hashedPassword
        ]);

        /*
          Redirect to login page after successful registration.
        */
        header("Location: login.php");
        exit;

    } catch (Throwable $ex) {
        printError($ex->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/register.css">
    <title>Register</title>
</head>
<body class="auth-page">
    <form action="register.php" method="POST" autocomplete="on">
        <input type="text" placeholder="Enter your username" name="name" required>
        <input type="email" placeholder="Enter your email" name="email" required>
        <input type="password" placeholder="Enter your password" name="password" required>

        <?php if (!empty($error)) : ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <button type="submit" name="btn">Create account</button>
    </form>
</body>
</html>