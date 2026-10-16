
<?php

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$database = getenv('DB_NAME');
$port = (int) (getenv('DB_PORT') ?: 4000);

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
