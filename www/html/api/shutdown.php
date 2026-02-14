<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

$logDir  = '/var/www/html/_ogkb_logs';
$logFile = $logDir . '/shutdown.log';

function log_line(string $msg): void {
    global $logDir, $logFile;
    $ts = date('Y-m-d H:i:s');
    if (is_dir($logDir)) {
        @file_put_contents($logFile, "[$ts] $msg\n", FILE_APPEND);
    }
}

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data) . "\n";
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
$cookie = $_SERVER['HTTP_COOKIE'] ?? '';
$sid    = session_id();

log_line("REQ method=$method remote=$remote sid=$sid ua=" . substr($ua, 0, 80));

if ($method !== 'POST') {
    log_line("DENY 405 method_not_allowed");
    reply(405, ['ok' => false, 'error' => 'Method not allowed']);
}

if ($cookie === '' || strpos($cookie, session_name() . '=') === false) {
    log_line("DENY 403 missing_session_cookie");
    reply(403, ['ok' => false, 'error' => 'Missing session cookie']);
}

$token = $_POST['token'] ?? '';
if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    log_line("DENY 403 bad_token token_len=" . strlen($token));
    reply(403, ['ok' => false, 'error' => 'Bad token']);
}

// Allow localhost + private ranges (covers your 10.42.0.x AP)
$allowed = false;
if ($remote === '127.0.0.1' || $remote === '::1') $allowed = true;
if (!$allowed && preg_match('/^10\./', $remote)) $allowed = true;
if (!$allowed && preg_match('/^192\.168\./', $remote)) $allowed = true;
if (!$allowed && preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $remote)) $allowed = true;

if (!$allowed) {
    log_line("DENY 403 network_block remote=$remote");
    reply(403, ['ok' => false, 'error' => 'Not allowed from this network', 'remote' => $remote]);
}

// Respond early
log_line("ALLOW ok:true issuing shutdown");
echo json_encode(['ok' => true, 'message' => 'Shutting down now']) . "\n";

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    flush();
}

// Must work under your systemd NoNewPrivileges override
$cmd = '/usr/bin/sudo -n /usr/local/sbin/ogkb-poweroff 2>&1';
$out = [];
$rc  = 0;
exec($cmd, $out, $rc);

log_line("EXEC rc=$rc out=" . substr(implode(' | ', $out), 0, 400));
exit;
