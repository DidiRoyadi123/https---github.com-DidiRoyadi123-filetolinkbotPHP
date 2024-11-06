<?php
require 'config.php';

$uniqueLink = $_GET['link'] ?? '';

if (!$uniqueLink) {
    die('Tautan tidak valid.');
}

$stmt = $mysqli->prepare("SELECT file_name FROM files WHERE unique_link = ?");
$stmt->bind_param("s", $uniqueLink);
$stmt->execute();
$stmt->bind_result($fileName);
$stmt->fetch();
$stmt->close();

if ($fileName) {
    $filePath = __DIR__ . '/files/' . $fileName;
    if (file_exists($filePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        readfile($filePath);
    } else {
        die('File tidak ditemukan.');
    }
} else {
    die('Tautan tidak valid.');
}
?>
