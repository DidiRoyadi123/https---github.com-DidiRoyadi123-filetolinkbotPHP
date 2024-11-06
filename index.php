<?php
// Menampilkan semua error untuk membantu debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Memuat file .env
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Menghubungkan ke database
$mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Variabel untuk pesan
$message = '';

// Fungsi untuk mengambil data dari Telegram
function getTelegramData($chat_id = null) {
    $url = 'https://api.telegram.org/bot' . $_ENV['TELEGRAM_BOT_TOKEN'] . '/getUpdates';
    
    // Inisialisasi cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout dalam detik

    // Tentukan DNS server khusus (Google DNS)
    curl_setopt($ch, CURLOPT_DNS_SERVERS, "8.8.8.8,8.8.4.4");
    
    $response = curl_exec($ch);
    
    // Cek jika ada kesalahan saat request
    if ($response === false) {
        die('Error cURL: ' . curl_error($ch));
    }

    curl_close($ch);
    
    // Mengonversi data JSON menjadi array
    $response = json_decode($response, true);
    
    $messages = [];
    
    if (isset($response['result'])) {
        foreach ($response['result'] as $update) {
            if (isset($update['message']['document'])) {
                $document = $update['message']['document'];
                if (!$chat_id || $update['message']['chat']['id'] == $chat_id) {
                    $messages[] = [
                        'file_id' => $document['file_id'],
                        'file_name' => $document['file_name'],
                        'file_size' => $document['file_size'],
                        'chat_id' => $update['message']['chat']['id']
                    ];
                }
            }
        }
    }
    
    return $messages;
}

$messages = getTelegramData();

if (!empty($messages)) {
    print_r($messages);
} else {
    echo "Tidak ada pesan atau file yang diterima.";
}
// Fungsi untuk menyimpan data ke database
function insertDataToDatabase($fileData) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("INSERT INTO files (file_id, file_name, file_size, unique_link) VALUES (?, ?, ?, ?)");
    
    foreach ($fileData as $file) {
        $uniqueLink = md5($file['file_id'] . time());
        $stmt->bind_param("ssis", $file['file_id'], $file['file_name'], $file['file_size'], $uniqueLink);
        $stmt->execute();
    }
    
    $stmt->close();
}

// Fungsi untuk mengirim file kembali ke pengguna
function sendFileToUser($chat_id, $file_id) {
    $url = 'https://api.telegram.org/bot' . $_ENV['TELEGRAM_BOT_TOKEN'] . '/sendDocument';
    $data = [
        'chat_id' => $chat_id,
        'document' => $file_id
    ];
    file_get_contents($url . '?' . http_build_query($data));
}

// Memeriksa jika tombol diklik dan memproses permintaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['get_channel_data'])) {
        $channelData = getTelegramData($_ENV['CHANNEL_ID']);
        if (!empty($channelData)) {
            insertDataToDatabase($channelData);
            $message = "Data dari channel berhasil dimasukkan ke database.";
        } else {
            $message = "Tidak ada data untuk channel saat ini.";
        }
    }

    if (isset($_POST['get_group_data'])) {
        $groupData = getTelegramData($_ENV['GROUP_ID']);
        if (!empty($groupData)) {
            insertDataToDatabase($groupData);
            $message = "Data dari grup berhasil dimasukkan ke database.";
        } else {
            $message = "Tidak ada data untuk grup saat ini.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot Data Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">
    <h1>Telegram Bot Data Management</h1>

    <?php if ($message): ?>
        <div class="alert alert-info" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" name="get_channel_data" class="btn btn-primary btn-lg btn-block mb-3">
            Dapatkan Data dari Channel
        </button>
        <button type="submit" name="get_group_data" class="btn btn-primary btn-lg btn-block">
            Dapatkan Data dari Grup
        </button>
    </form>

</div>

</body>
</html>
