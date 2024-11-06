<?php
// Menggunakan .env untuk konfigurasi
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Konfigurasi Bot Telegram
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN']);
define('TELEGRAM_CHANNEL_ID', $_ENV['CHANNEL_ID']);
define('TELEGRAM_GROUP_ID', $_ENV['GROUP_ID']);

// Koneksi ke database
$mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
?>
