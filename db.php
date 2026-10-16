<?php

mysqli_report(MYSQLI_REPORT_OFF);

// PHP's built-in server does not load Vercel's .env.local automatically.
// Read local-only DB settings from that ignored file when process variables
// have not already been supplied. Production continues to use Vercel env vars.
$localEnvFile = __DIR__ . '/.env.local';
if (is_readable($localEnvFile)) {
    foreach (file($localEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_starts_with($line, 'export ')) $line = substr($line, 7);
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $match)) continue;
        $key = $match[1];
        if (getenv($key) !== false) continue;
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $quote = $value[0];
            $value = substr($value, 1, -1);
            if ($quote === '"') $value = stripcslashes($value);
            else $value = str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$database = getenv('DB_NAME');
$port = (int) (getenv('DB_PORT') ?: 4000);

if ($password === '[SENSITIVE]') {
    http_response_code(500);
    exit('DB_PASSWORD di .env.local masih placeholder. Isi dengan password TiDB agar server lokal bisa tersambung.');
}

$caPath = __DIR__ . '/certs/isrgrootx1.pem';

if (!$host || !$user || !$password || !$database) {
    http_response_code(500);
    exit('Database configuration is missing.');
}

$conn = mysqli_init();

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
}

mysqli_ssl_set($conn, null, null, $caPath, null, null);

if (!$conn->real_connect(
    $host,
    $user,
    $password,
    $database,
    $port,
    null,
    MYSQLI_CLIENT_SSL
)) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset('utf8mb4');
