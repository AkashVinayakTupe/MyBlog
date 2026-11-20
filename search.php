<?php
// search.php - simple search in title, excerpt, tags and content.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

$db = get_db();

// Clean the search text
$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 100);

$too_short = ($q !== '' && mb_strlen($q) < 2);
$posts = [];
$total = 0;
$pages = 1;
$page  = 1;

if ($q !== '' && !$too_short) {
    // Escape the special LIKE characters % and _ so they are searched as normal text
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $where  = ' AND (title LIKE ? OR excerpt LIKE ? OR tags LIKE ? OR content LIKE ?)';
    $params = [$like, $like, $like, $like];

    $total = count_posts($db, $where, $params);
    $pages = max(1, (int)ceil($total / POSTS_PER_PAGE));
    $page  = min(current_page_number(), $pages);
    $posts = fetch_posts($db, $where, $params, POSTS_PER_PAGE, ($page - 1) * POSTS_PER_PAGE);
}

$page_title   = ($q !== '' ? 'Search: ' . $q : 'Search') . ' | ' . APP_NAME;
$robots       = 'noindex, follow';   // search result pages should not be in Google
$search_query = $q;

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-extrabold mb-6">Search</h1>

<?php if ($q === ''): ?>
    <p class="text-gray-600">Type a word in the search box above.</p>

<?php elseif ($too_short): ?>
    <p class="text-gray-600">Please type at least 2 characters.</p>

<?php elseif (!$posts): ?>
    <p class="text-gray-600">No posts found for "<strong><?= e($q) ?></strong>". Try a different word.</p>

<?php else: ?>
    <p class="text-gray-600 mb-6"><?= (int)$total ?> result(s) for "<strong><?= e($q) ?></strong>"</p>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($posts as $post): ?>
            <?= render_post_card($post) ?>
        <?php endforeach; ?>
    </div>
    <?= render_pagination($page, $pages, base_url('search'), ['q' => $q]) ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>