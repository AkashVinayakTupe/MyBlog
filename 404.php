<?php
// 404.php - "page not found". Also used by the other pages when something is missing.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

http_response_code(404);   // tells Google this page does not exist

$page_title = 'Page not found | ' . APP_NAME;
$robots     = 'noindex, follow';

require __DIR__ . '/includes/header.php';
?>

<div class="text-center py-20">
    <p class="text-6xl font-extrabold text-indigo-600">404</p>
    <h1 class="text-2xl font-bold mt-4">Page not found</h1>
    <p class="text-gray-600 mt-2">Sorry, this page does not exist or was moved.</p>
    <a href="<?= e(base_url()) ?>"
       class="inline-block mt-6 bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700">Go to home page</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>