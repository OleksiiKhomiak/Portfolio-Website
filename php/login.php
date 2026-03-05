<?php
session_start();

/*
  Flash error message:
  - read error from session
  - remove it immediately so it appears only once
*/
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

/*
  Helper function to print a formatted error and stop script execution.
*/
function printError(string $err){
    echo "<h1>The following error occurred</h1>
          <p>" . htmlspecialchars($err) . "</p>";
    exit;
}

/*
  Create database connection using PDO.
  - ERRMODE_EXCEPTION: throw exceptions on SQL errors
  - FETCH_ASSOC: return associative arrays
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
  Handle login form submission.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
      Read user input from form.
      - sanitize email
      - password is read directly
    */
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    $email = trim((string)$email);
    $password = trim((string)$password);

    /*
      Basic validation:
      both fields must be filled
    */
    if ($email === '' || $password === '') {
        $_SESSION['error'] = "All fields must be filled!";
        header("Location: login.php");
        exit;
    }

    /*
      Query database for user by email.
      We also load the role so we can know if the user is admin.
    */
    $stmt = $dbHandler->prepare("
        SELECT user_id, user_name, user_password, role
        FROM Users
        WHERE user_email = :em
        LIMIT 1
    ");

    $stmt->bindValue(':em', $email, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch();

    /*
      Verify password using password_verify().
      Passwords in DB are stored as hashes.
    */
    if ($user && password_verify($password, $user['user_password'])) {

        /*
          Login successful:
          store user information in session
        */
        $_SESSION['userId']   = (int)$user['user_id'];
        $_SESSION['userName'] = $user['user_name'];

        /*
          Save user role:
          - admin
          - user
          If role column is missing, fallback to "user".
        */
        $_SESSION['role'] = $user['role'] ?? 'user';

        /*
          Redirect to home page after login.
        */
        header("Location: home.php");
        exit;

    } else {

        /*
          Login failed:
          wrong email or password.
        */
        $_SESSION['error'] = "Email or password incorrect";
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/register.css">
    <title>Login</title>
</head>
<body class="auth-page">
    <!-- Login form -->
    <form action="login.php" method="POST" autocomplete="on">

        <input type="email" placeholder="Enter your email" name="email" required>
        <input type="password" placeholder="Enter your password" name="password" required>

        <!-- Display flash error message -->
        <?php if (!empty($error)) : ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Link to registration page -->
        <a href="register.php">Create account</a>

        <button type="submit" name="btn">Login</button>

    </form>

</body>
</html>