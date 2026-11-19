<?php
// admin/test_gemini.php
// Diagnostic page: checks PHP, cURL, database, Gemini key and model.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/gemini.php';

// TEMPORARY guard: only your own computer may open this page.
// Step 3 replaces this with a proper admin login check.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Not allowed.');
}

start_secure_session();

// ---------- Handle button clicks ----------
$call_result = null;
$list_result = null;
$csrf_failed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $csrf_failed = true;
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'test_call') {
            $call_result = gemini_generate('Reply with exactly one word: OK');
        } elseif ($action === 'list_models') {
            $list_result = gemini_list_models();
        }
    }
}

// ---------- Build the checks table ----------
$checks = [];

$checks[] = ['PHP version', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail', PHP_VERSION];

$has_curl = extension_loaded('curl');
$curl_info = $has_curl ? 'Enabled (version ' . curl_version()['version'] . ')' : 'NOT enabled';
$checks[] = ['cURL', $has_curl ? 'ok' : 'fail', $curl_info];

$checks[] = ['OpenSSL', extension_loaded('openssl') ? 'ok' : 'fail',
             extension_loaded('openssl') ? 'Enabled' : 'NOT enabled'];

$key_set = (GEMINI_API_KEY !== '' && GEMINI_API_KEY !== 'PASTE_YOUR_GEMINI_KEY_HERE');
$checks[] = ['Gemini key set', $key_set ? 'ok' : 'fail',
             $key_set ? 'Yes (hidden for safety)' : 'No. Paste it in config.php'];

$checks[] = ['Model (GEMINI_MODEL)', 'info', GEMINI_MODEL];

try {
    get_db()->query('SELECT 1');
    $checks[] = ['Database', 'ok', 'Connected to "' . DB_NAME . '"'];
} catch (Throwable $ex) {
    $checks[] = ['Database', 'fail', $ex->getMessage()];
}

$log_ok = is_writable(dirname(LOG_FILE));
$checks[] = ['logs/ folder writable', $log_ok ? 'ok' : 'fail', $log_ok ? 'Yes' : 'No. Check folder permissions'];

$checks[] = ['max_execution_time', 'info', ini_get('max_execution_time') . ' seconds (CLI is 0)'];
$checks[] = ['GD library / WebP', 'info',
             function_exists('imagewebp') ? 'WebP supported' : 'WebP NOT supported (we will use JPEG)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Gemini Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Gemini and server test</h1>

    <?php if ($csrf_failed): ?>
        <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4">
            Security token expired. Please reload the page and try again.
        </div>
    <?php endif; ?>

    <!-- Checks table -->
    <table class="w-full bg-white rounded shadow mb-6 text-sm">
        <?php foreach ($checks as [$label, $status, $detail]): ?>
            <tr class="border-b">
                <td class="p-3 font-medium"><?= e($label) ?></td>
                <td class="p-3">
                    <?php if ($status === 'ok'): ?>
                        <span class="text-green-700 font-bold">OK</span>
                    <?php elseif ($status === 'fail'): ?>
                        <span class="text-red-700 font-bold">FAIL</span>
                    <?php else: ?>
                        <span class="text-gray-500 font-bold">INFO</span>
                    <?php endif; ?>
                </td>
                <td class="p-3"><?= e($detail) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_call">
            <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Run live Gemini test
            </button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="list_models">
            <button class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
                List models
            </button>
        </form>
    </div>
    <p class="text-sm text-gray-500 mb-6">
        The live test uses one request from your free limit. It can take up to about 15 seconds if Google asks us to retry.
    </p>

    <!-- Live test result -->
    <?php if ($call_result): ?>
        <div class="bg-white rounded shadow p-4 mb-6">
            <h2 class="font-bold mb-2">Live test result</h2>
            <?php if ($call_result['ok']): ?>
                <p class="text-green-700 font-bold">Success. Your key and model work.</p>
                <p class="text-sm mt-2">Gemini said: <strong><?= e(trim($call_result['text'])) ?></strong></p>
            <?php else: ?>
                <p class="text-red-700 font-bold">Failed.</p>
                <p class="mt-2"><?= e($call_result['message']) ?></p>
                <?php if ($call_result['http_code']): ?>
                    <p class="text-sm mt-1">HTTP code: <?= (int)$call_result['http_code'] ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="text-sm text-gray-500 mt-1">Attempts used: <?= (int)$call_result['attempts'] ?></p>
            <?php if (!$call_result['ok'] && $call_result['raw'] !== ''): ?>
                <p class="text-sm font-medium mt-3">Raw error from Google:</p>
                <pre class="bg-gray-100 p-3 text-xs overflow-x-auto whitespace-pre-wrap"><?= e($call_result['raw']) ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- List models result -->
    <?php if ($list_result): ?>
        <div class="bg-white rounded shadow p-4 mb-6">
            <h2 class="font-bold mb-2">Models your key can see</h2>
            <?php if ($list_result['ok']): ?>
                <p class="text-sm mb-2">
                    Your current setting: <strong><?= e(GEMINI_MODEL) ?></strong>
                    <?= in_array(GEMINI_MODEL, $list_result['models'], true)
                        ? '<span class="text-green-700">(found in the list)</span>'
                        : '<span class="text-red-700">(NOT in the list)</span>' ?>
                </p>
                <p class="text-sm text-gray-500 mb-2">
                    This list shows names only. It does not show which models are free. Check
                    aistudio.google.com/rate-limit for your limits.
                </p>
                <ul class="text-sm list-disc pl-5">
                    <?php foreach ($list_result['models'] as $name): ?>
                        <li><?= e($name) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-red-700 font-bold">Failed.</p>
                <p class="mt-2"><?= e($list_result['message']) ?></p>
                <?php if ($list_result['raw'] !== ''): ?>
                    <pre class="bg-gray-100 p-3 text-xs overflow-x-auto whitespace-pre-wrap mt-2"><?= e($list_result['raw']) ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>