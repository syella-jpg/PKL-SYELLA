<?php

/** Keep the existing local workflow; cloud deployments always require TLS. */
function openReportConnection(): mysqli
{
    try {
        if (getenv('VERCEL') === '1' || getenv('DB_HOST') !== false) {
            return openTidbConnection();
        }
        $connection = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $error) {
        throw new RuntimeException('Koneksi database gagal. Periksa konfigurasi server dan akses TiDB.');
    }
}

/** Open a verified TLS connection using server-side environment variables. */
function openTidbConnection(): mysqli
{
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('Ekstensi mysqli tidak tersedia.');
    }

    $config = [];
    foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'] as $name) {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException('Konfigurasi database belum lengkap.');
        }
        $config[$name] = $value;
    }
    $port = filter_var($config['DB_PORT'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('DB_PORT tidak valid.');
    }

    $customCa = getenv('DB_SSL_CA');
    $candidates = $customCa !== false && $customCa !== ''
        ? [$customCa]
        : [
            ini_get('openssl.cafile'),
            getenv('SSL_CERT_FILE'),
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem',
        ];
    $ca = null;
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
            $ca = $candidate;
            break;
        }
    }
    if ($ca === null) {
        throw new RuntimeException('Sertifikat CA tidak tersedia. Atur DB_SSL_CA ke file CA pada server.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = mysqli_init();
    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $connection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $connection->ssl_set(null, null, $ca, null, null);
    try {
        $connection->real_connect(
            $config['DB_HOST'],
            $config['DB_USER'],
            $config['DB_PASSWORD'],
            $config['DB_NAME'],
            $port,
            null,
            MYSQLI_CLIENT_SSL
        );
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $error) {
        $connection->close();
        throw $error;
    }
}
