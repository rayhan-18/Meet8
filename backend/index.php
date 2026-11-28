<?php
define('SERVICE_ACCOUNT_FILE', file_exists(__DIR__ . '/service-account.json')
    ? __DIR__ . '/service-account.json'
    : dirname(__DIR__) . '/service-account.json');
const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
const CHANNEL_ID = 'high_importance_channel';

function base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function httpPost(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException('cURL error: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $response];
}

function getAccessToken(array $sa): string
{
    $now = time();
    $header = base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64url(json_encode([
        'iss' => $sa['client_email'],
        'scope' => FCM_SCOPE,
        'aud' => $sa['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $unsigned = "$header.$claims";

    if (!openssl_sign($unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Gagal menandatangani JWT. Cek private_key di service-account.json');
    }
    $jwt = $unsigned . '.' . base64url($signature);

    [$status, $body] = httpPost(
        $sa['token_uri'],
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    $json = json_decode($body, true);
    if ($status !== 200 || empty($json['access_token'])) {
        throw new RuntimeException("Gagal mendapatkan access token ($status): $body");
    }
    return $json['access_token'];
}

function parseData(string $text): array
{
    $data = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $data[trim($k)] = trim($v);
    }
    return $data;
}

function sendNotification(array $form): array
{
    if (!file_exists(SERVICE_ACCOUNT_FILE)) {
        throw new RuntimeException('File service-account.json tidak ditemukan di folder backend/.');
    }
    $sa = json_decode(file_get_contents(SERVICE_ACCOUNT_FILE), true);

    $message = [
        $form['target_type'] => trim($form['target']),
        'notification' => [
            'title' => $form['title'],
            'body' => $form['body'],
        ],
        'android' => [
            'priority' => 'high',
            'notification' => ['channel_id' => CHANNEL_ID],
        ],
    ];
    if (trim($form['image']) !== '') {
        $message['notification']['image'] = trim($form['image']);
    }
    $data = parseData($form['data']);
    if ($data) {
        $message['data'] = $data;
    }
    $payload = json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    $url = "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send";
    [$status, $body] = httpPost($url, $payload, [
        'Authorization: Bearer ' . getAccessToken($sa),
        'Content-Type: application/json; charset=utf-8',
    ]);
    return ['status' => $status, 'payload' => $payload, 'response' => $body];
}

$form = [
    'target_type' => $_POST['target_type'] ?? 'token',
    'target' => $_POST['target'] ?? '',
    'title' => $_POST['title'] ?? 'Halo Mahasiswa',
    'body' => $_POST['body'] ?? 'Notifikasi dari web PHP',
    'image' => $_POST['image'] ?? '',
    'data' => $_POST['data'] ?? "kategori=pengumuman\nid_pesan=001",
];
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($form['target_type'], ['token', 'topic'], true)) {
        $form['target_type'] = 'token';
    }
    try {
        $result = sendNotification($form);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FCM Sender - PHP</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #eef2f7; margin: 0; padding: 24px 16px; }
        .card { max-width: 640px; margin: auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        h1 { margin-top: 0; font-size: 22px; color: #1f4e79; }
        label { display: block; margin: 14px 0 6px; font-weight: bold; font-size: 14px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ccd; border-radius: 8px; font-size: 14px; }
        textarea { min-height: 80px; font-family: Consolas, monospace; }
        button { margin-top: 18px; width: 100%; padding: 12px; background: #1f4e79; color: #fff; border: 0; border-radius: 8px; font-size: 16px; cursor: pointer; }
        button:hover { background: #2e75b6; }
        .ok { background: #e6f4ea; border-left: 4px solid #34a853; }
        .err { background: #fce8e6; border-left: 4px solid #ea4335; }
        .box { margin-top: 18px; padding: 12px; border-radius: 8px; }
        pre { white-space: pre-wrap; word-break: break-all; font-size: 12px; background: #f6f8fa; padding: 10px; border-radius: 6px; }
        small { color: #666; }
    </style>
</head>
<body>
<div class="card">
    <h1>FCM Sender (PHP)</h1>
    <form method="post">
        <label>Kirim ke</label>
        <select name="target_type">
            <option value="token" <?= $form['target_type'] === 'token' ? 'selected' : '' ?>>Satu device (FCM Token)</option>
            <option value="topic" <?= $form['target_type'] === 'topic' ? 'selected' : '' ?>>Topic (semua device yang subscribe)</option>
        </select>

        <label>FCM Token / Nama Topic</label>
        <textarea name="target" required placeholder="Tempel FCM token, atau tulis: kelas"><?= e($form['target']) ?></textarea>

        <label>Judul</label>
        <input name="title" required value="<?= e($form['title']) ?>">

        <label>Isi Pesan</label>
        <input name="body" required value="<?= e($form['body']) ?>">

        <label>URL Gambar <small>(opsional)</small></label>
        <input name="image" value="<?= e($form['image']) ?>" placeholder="https://picsum.photos/600/300">

        <label>Data Tambahan <small>(format key=value, satu per baris)</small></label>
        <textarea name="data"><?= e($form['data']) ?></textarea>

        <button type="submit">Kirim Notifikasi</button>
    </form>

    <?php if ($error): ?>
        <div class="box err"><b>Error:</b> <?= e($error) ?></div>
    <?php elseif ($result): ?>
        <div class="box <?= $result['status'] === 200 ? 'ok' : 'err' ?>">
            <b>Status: <?= $result['status'] ?></b>
            <p>Response dari FCM:</p>
            <pre><?= e($result['response']) ?></pre>
            <p>Payload yang dikirim:</p>
            <pre><?= e($result['payload']) ?></pre>
        </div>
    <?php endif; ?>
</div>
</body>
</html>