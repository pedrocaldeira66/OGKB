<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$logDir  = '/var/www/html/_ogkb_logs';
$logFile = $logDir . '/ping.log';

$ts     = date('Y-m-d H:i:s');
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? '';

$line = "[$ts] $method from $remote\n";

$ok = false;
$err = '';

if (!is_dir($logDir)) {
    $err = "logDir_missing";
} else {
    $bytes = @file_put_contents($logFile, $line, FILE_APPEND);
    if ($bytes === false) {
        $e = error_get_last();
        $err = $e['message'] ?? 'file_put_contents_failed';
    } else {
        $ok = true;
    }
}

echo "pong\n";
echo "log_ok=" . ($ok ? "1" : "0") . "\n";
if (!$ok) {
    echo "log_error=" . $err . "\n";
}
