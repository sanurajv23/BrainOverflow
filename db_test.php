<?php

try {
    require __DIR__ . '/config/database.php';

    echo 'Database connection successful';
} catch (Throwable $error) {
    echo 'Database connection failed: ' . $error->getMessage();
}
