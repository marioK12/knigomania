<?php
// db.php

date_default_timezone_set('Europe/Sofia');

$DB_HOST = "localhost";
$DB_NAME = "knigomania";
$DB_USER = "root";
$DB_PASS = "";

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  // ✅ синхронизираме MySQL timezone с PHP
  $pdo->exec("SET time_zone = '+02:00'");

} catch (Exception $e) {
  die("DB connection failed: " . $e->getMessage());
}
