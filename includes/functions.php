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

// =====================================================================
// NEW in Step 2
// =====================================================================

// ---------- Redirect ----------
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- Flash messages (one-time messages shown after a redirect) ----------
// type: success, error or info
function set_flash(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Returns the HTML for all waiting messages, then clears them
function render_flash(): string
{
    start_secure_session();
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $flash) {
        $color = match ($flash['type']) {
            'success' => 'bg-green-100 border-green-300 text-green-800',
            'error'   => 'bg-red-100 border-red-300 text-red-800',
            default   => 'bg-blue-100 border-blue-300 text-blue-800',
        };
        $html .= '<div class="border p-3 rounded mb-4 ' . $color . '">' . e($flash['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Slugs ----------
// "Hello World! 2026" becomes "hello-world-2026"
// Note: titles in Hindi or other scripts become "post". We will handle that later if needed.
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    $text = substr($text, 0, 80);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

// Makes a slug that is not used yet. Adds -2, -3 ... if needed.
// $table must be 'posts' or 'pages'. $exclude_id lets you edit an item without clashing with itself.
function unique_slug(PDO $db, string $slug, string $table = 'posts', int $exclude_id = 0): string
{
    if (!in_array($table, ['posts', 'pages'], true)) {
        throw new InvalidArgumentException('Bad table name for unique_slug.');
    }

    $candidate = $slug;
    $number = 2;
    $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$candidate, $exclude_id]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $slug . '-' . $number;
        $number++;
    }
}

// ---------- Reading time ----------
// Returns minutes (at least 1). Uses 200 words per minute.
function reading_time(string $html): int
{
    $text  = trim(strip_tags($html));
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $count = $words ? count($words) : 0;
    return max(1, (int)ceil($count / 200));
}

// ---------- HTML sanitizer ----------
// Keeps only safe tags. Used on every article written by AI before saving.
// Allowed: p, h2, h3, ul, ol, li, strong, em, a, blockquote, img
function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $allowed_tags = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote', 'img'];
    // These tags are removed together with everything inside them
    $drop_tags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
                  'textarea', 'select', 'svg', 'math', 'noscript', 'head', 'title', 'meta', 'link', 'base'];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);   // do not print HTML warnings
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) {
        return '';
    }

    sanitize_node($root, $allowed_tags, $drop_tags);

    $clean = '';
    foreach ($root->childNodes as $child) {
        $clean .= $dom->saveHTML($child);
    }
    return trim($clean);
}

// Helper for sanitize_html(): cleans one node and everything inside it
function sanitize_node(DOMNode $node, array $allowed_tags, array $drop_tags): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {

        // Remove HTML comments
        if ($child instanceof DOMComment) {
            $node->removeChild($child);
            continue;
        }
        // Plain text is fine
        if (!($child instanceof DOMElement)) {
            continue;
        }

        $tag = strtolower($child->nodeName);

        // Dangerous tags: remove with content
        if (in_array($tag, $drop_tags, true)) {
            $node->removeChild($child);
            continue;
        }

        // Clean the inside first
        sanitize_node($child, $allowed_tags, $drop_tags);

        // Tag not allowed: keep its content, drop the tag itself
        if (!in_array($tag, $allowed_tags, true)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        // Allowed tag: keep only safe attributes
        $keep = ['a' => ['href'], 'img' => ['src', 'alt']][$tag] ?? [];
        foreach (iterator_to_array($child->attributes) as $attr) {
            if (!in_array($attr->name, $keep, true)) {
                $child->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            $href = trim($child->getAttribute('href'));
            // Only http, https, mailto or a path starting with /
            if (!preg_match('#^(https?://|mailto:|/)#i', $href)) {
                $child->removeAttribute('href');
            } else {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            $src = trim($child->getAttribute('src'));
            if (!preg_match('#^(https?://|/)#i', $src)) {
                $node->removeChild($child);   // image without a safe src is removed
                continue;
            }
            $child->setAttribute('loading', 'lazy');
        }
    }
}