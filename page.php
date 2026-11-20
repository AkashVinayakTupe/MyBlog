<?php
// page.php - static pages (About, Contact, Privacy...). Opened as /page/slug

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-z0-9-]{1,255}$/', $slug)) {
    require __DIR__ . '/404.php';
    exit;
}

$stmt = get_db()->prepare('SELECT title, slug, content FROM pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$static = $stmt->fetch();

if (!$static) {
    require __DIR__ . '/404.php';
    exit;
}

$page_title       = $static['title'] . ' | ' . APP_NAME;
$meta_description = trim(mb_substr(strip_tags((string)$static['content']), 0, 150));
$canonical_url    = base_url('page/' . $static['slug']);

require __DIR__ . '/includes/header.php';
?>

<article class="max-w-[720px] mx-auto">
    <h1 class="text-3xl sm:text-4xl font-extrabold mb-6"><?= e($static['title']) ?></h1>
    <!-- Page text was cleaned by sanitize_html() when it was saved -->
    <div class="prose prose-lg max-w-none prose-a:text-indigo-700">
        <?= $static['content'] ?>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>