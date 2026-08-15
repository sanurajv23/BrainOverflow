<?php

$host = 'localhost';
$database = 'brainoverflow';
$username = 'brainadmin';
$password = 'BrainOverflow@2026';

$dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
