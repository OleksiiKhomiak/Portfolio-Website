<?php
declare(strict_types=1);

function db(): PDO {
    $host = 'mysql';      
    $db   = 'Portfolio';
    $user = 'root';
    $pass = 'qwerty';           

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}