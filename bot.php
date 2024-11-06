<?php
require 'config.php';

define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN']);
define('API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');

// Fungsi untuk mengirim pesan ke pengguna
function sendMessage($chatId, $message) {
    $url = API_URL . 'sendMessage';
    $post_fields = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    
    if ($response === false) {
        file_put_contents('log.txt', 'Error sending message: ' . curl_error($ch) . "\n", FILE_APPEND);
    } else {
        file_put_contents('log.txt', 'Response from Telegram: ' . $response . "\n", FILE_APPEND);
    }

    curl_close($ch);
}

// Fungsi untuk mengirim file dokumen ke pengguna
function sendDocument($chatId, $filePath) {
    $url = API_URL . 'sendDocument';
    $post_fields = [
        'chat_id' => $chatId,
        'document' => new CURLFile(realpath($filePath))
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    
    if ($response === false) {
        file_put_contents('log.txt', 'Error sending document: ' . curl_error($ch) . "\n", FILE_APPEND);
    } else {
        file_put_contents('log.txt', 'Response from Telegram: ' . $response . "\n", FILE_APPEND);
    }

    curl_close($ch);
}

// Fungsi untuk memeriksa apakah pengguna adalah anggota grup
function isUserMember($chatId) {
    $url = API_URL . 'getChatMember';
    $params = [
        'chat_id' => $_ENV['GROUP_ID'],
        'user_id' => $chatId
    ];

    $response = json_decode(file_get_contents($url . '?' . http_build_query($params)), true);

    file_put_contents('log.txt', "getChatMember response: " . print_r($response, true) . "\n", FILE_APPEND);

    if (isset($response['result']['status']) && in_array($response['result']['status'], ['member', 'administrator', 'creator'])) {
        return true;
    }

    return false;
}

// Fungsi untuk mendownload file dari Telegram
function downloadFile($fileId) {
    $fileInfo = json_decode(file_get_contents(API_URL . 'getFile?file_id=' . $fileId), true);
    $filePath = $fileInfo['result']['file_path'];
    $fileUrl = 'https://api.telegram.org/file/bot' . TELEGRAM_BOT_TOKEN . '/' . $filePath;

    $destination = __DIR__ . '/files/' . basename($filePath);
    file_put_contents($destination, file_get_contents($fileUrl));

    return basename($filePath);
}

// Fungsi untuk membuat tautan unik untuk file
function generateUniqueLink($fileId) {
    return md5($fileId . time());
}

// Fungsi untuk menangani file yang dikirimkan ke channel
function handleFile($chatId, $fileId, $fileName, $fileSize) {
    global $mysqli;

    $fileDownloaded = downloadFile($fileId);
    $uniqueLink = generateUniqueLink($fileId);

    // Menyimpan detail file ke database
    $stmt = $mysqli->prepare("INSERT INTO files (file_id, file_name, file_size, unique_link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $fileId, $fileName, $fileSize, $uniqueLink);
    $stmt->execute();

    $downloadLink = $_ENV['APP_URL'] . '/download.php?link=' . $uniqueLink;
    sendMessage($chatId, "File Anda telah diunggah! Berikut tautannya: <a href='$downloadLink'>$downloadLink</a>");
}

// Fungsi untuk menangani perintah /start dan mengirim file yang diminta
function handleStartCommand($chatId, $uniqueLink) {
    global $mysqli;

    // Periksa apakah pengguna sudah menjadi anggota grup
    if (!isUserMember($chatId)) {
        sendMessage($chatId, "Anda harus bergabung dengan grup kami terlebih dahulu untuk mengunduh file. Klik link untuk bergabung: https://t.me/" . ltrim($_ENV['GROUP_ID'], '@'));
        return;
    }

    // Memeriksa apakah pengguna memiliki permintaan file di database
    $stmt = $mysqli->prepare("SELECT file_name FROM files WHERE unique_link = ?");
    $stmt->bind_param("s", $uniqueLink);
    $stmt->execute();
    $stmt->bind_result($fileName);

    if ($stmt->fetch()) {
        $filePath = __DIR__ . '/files/' . $fileName;
        if (file_exists($filePath)) {
            sendDocument($chatId, $filePath);
        } else {
            sendMessage($chatId, "Maaf, file tidak ditemukan.");
        }
    } else {
        sendMessage($chatId, "Tautan unduhan tidak valid.");
    }
    $stmt->close();
}

$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);

if (!$update) {
    exit;
}

$chatId = $update['message']['chat']['id'];
$messageText = $update['message']['text'] ?? '';

file_put_contents('log.txt', "Received message: " . print_r($update, true) . "\n", FILE_APPEND);

if ($messageText === '/start') {
    if (isset($update['message']['text']) && preg_match('/^\/start\s+(\w+)$/', $messageText, $matches)) {
        $uniqueLink = $matches[1];
        handleStartCommand($chatId, $uniqueLink);
    } else {
        sendMessage($chatId, "Selamat datang! Kirimkan tautan unduhan yang valid untuk mengunduh file.");
    }
} elseif (isset($update['message']['document']) && $update['message']['chat']['username'] == ltrim($_ENV['CHANNEL_ID'], '@')) {
    $fileId = $update['message']['document']['file_id'];
    $fileName = $update['message']['document']['file_name'];
    $fileSize = $update['message']['document']['file_size'];
    handleFile($chatId, $fileId, $fileName, $fileSize);
}
?>
