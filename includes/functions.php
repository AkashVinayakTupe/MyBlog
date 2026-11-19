<?php
// includes/functions.php
// Shared helper functions used by every page.

require_once __DIR__ . '/../config.php';

// ---------- Error settings ----------
// Never show raw PHP errors on screen. Write them to the log instead.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

// ---------- Logging ----------
// Adds one line to logs/app.log
function app_log(string $level, string $message): void
{
    $folder = dirname(LOG_FILE);
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . PHP_EOL;
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ---------- Friendly error page ----------
function show_friendly_error(string $detail = ''): void
{
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;max-width:600px;margin:60px auto;padding:20px;'
       . 'border:1px solid #ddd;border-radius:8px">';
    echo '<h2>Something went wrong</h2>';
    echo '<p>Sorry, the page could not load. The problem was saved in logs/app.log.</p>';
    if (APP_DEBUG && $detail !== '') {
        echo '<p style="color:#b00"><strong>Debug:</strong> ' . e($detail) . '</p>';
    }
    echo '</div>';
}

// Catches any error that nothing else caught
set_exception_handler(function (Throwable $ex) {
    app_log('ERROR', get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    show_friendly_error($ex->getMessage());
});

// Catches fatal errors (these would otherwise give a white screen)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('FATAL', $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        show_friendly_error($err['message']);
    }
});

// ---------- Output escaping ----------
// Use e() on EVERY value you print into HTML.
function e(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// ---------- Session ----------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ---------- CSRF protection ----------
// Put csrf_field() inside every POST form.
// Call csrf_check() at the start of every POST handler.
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    start_secure_session();
    $sent = $_POST['csrf_token'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';
    if ($sent === '' || $real === '' || !hash_equals($real, $sent)) {
        app_log('WARN', 'CSRF check failed (bad or expired token).');
        return false;
    }
    return true;
}

// ---------- cURL helper ----------
// One place for all outside calls (Gemini, Trends, search, images).
// Options: method (GET/POST), headers (array), body (string), timeout (seconds)
// Returns: ok, http_code, body, errno, error
function http_request(string $url, array $opts = []): array
{
    $out = ['ok' => false, 'http_code' => 0, 'body' => '', 'errno' => 0, 'error' => ''];

    if (!function_exists('curl_init')) {
        $out['errno'] = -1;
        $out['error'] = 'The PHP cURL extension is not enabled.';
        return $out;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $opts['timeout'] ?? 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,   // keep ON for safety
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => $opts['headers'] ?? [],
        CURLOPT_USERAGENT      => 'MyBlogBot/1.0',
    ]);

    if (($opts['method'] ?? 'GET') === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body'] ?? '');
    }

    $body = curl_exec($ch);
    if ($body === false) {
        $out['errno'] = curl_errno($ch);
        $out['error'] = curl_error($ch);
    } else {
        $out['ok']        = true;
        $out['http_code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $out['body']      = $body;
    }
    return $out;
}

// Turns a cURL error number into a plain-English message
function curl_friendly_message(int $errno, string $error): string
{
    switch ($errno) {
        case -1:
            return 'cURL is not enabled in PHP. Enable extension=curl in php.ini and restart Apache.';
        case 6:
            return 'Could not find the server (DNS problem). Check your internet connection.';
        case 7:
            return 'Could not connect to the server. Check your internet or firewall.';
        case 28:
            return 'The request timed out. Try again. If it keeps happening, raise the timeout.';
        case 60:
        case 77:
            return 'SSL certificate problem. On XAMPP, set curl.cainfo in php.ini (see the fix below the test page).';
        default:
            return 'Network error (' . $errno . '): ' . $error;
    }
}