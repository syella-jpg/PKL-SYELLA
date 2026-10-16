<?php

/**
 * Open a TLS connection to the MySQL-compatible TiDB Cloud database.
 * TiDB integrations may expose either TIDB_* or DB_* environment names.
 */
function app_database_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $readEnv = static function (string $primary, string $fallback = ''): string {
        $value = getenv($primary);
        if ($value === false || $value === '') {
            $value = $fallback !== '' ? getenv($fallback) : false;
        }
        return $value === false ? '' : (string)$value;
    };

    $host = trim($readEnv('TIDB_HOST', 'DB_HOST'));
    $user = $readEnv('TIDB_USER', 'DB_USER');
    $password = $readEnv('TIDB_PASSWORD', 'DB_PASSWORD');
    $database = trim($readEnv('TIDB_DATABASE', 'DB_NAME'));
    $port = (int)($readEnv('TIDB_PORT', 'DB_PORT') ?: 4000);

    if ($host === '' || $user === '' || $database === '' || $port < 1 || $port > 65535) {
        throw new RuntimeException('Konfigurasi TiDB belum lengkap. Periksa variabel TIDB_* atau DB_* di Vercel.');
    }

    $connection = mysqli_init();
    if (!$connection) {
        throw new RuntimeException('Tidak dapat memulai koneksi database MySQL.');
    }

    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        $connection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    }

    $caPath = trim($readEnv('TIDB_CA', 'DB_CA'));
    if ($caPath !== '') {
        $connection->ssl_set(null, null, $caPath, null, null);
    }

    $connection->real_connect(
        $host,
        $user,
        $password,
        $database,
        $port,
        null,
        MYSQLI_CLIENT_SSL
    );
    $connection->set_charset('utf8mb4');

    return $connection;
}
