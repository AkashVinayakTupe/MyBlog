<?php
// category.php - posts in one category (/category/slug) or one tag (/tag/slug)

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

$db       = get_db();
$cat_slug = $_GET['slug'] ?? '';
$tag_slug = $_GET['tag'] ?? '';

$type = '';      // 'category' or 'tag'
$label = '';     // real name, for example "Technology"
$where = '';
$params = [];
$base = '';

if ($cat_slug !== '' && preg_match('/^[a-z0-9-]{1,100}$/', $cat_slug)) {
    // Find the real category name that matches the slug
    $names = $db->query("SELECT DISTINCT category FROM posts
                         WHERE status = 'published' AND category IS NOT NULL AND category != ''")
                ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($names as $name) {
        if (slugify($name) === $cat_slug) {
            $type = 'category';
            $label = $name;
            $where = ' AND category = ?';
            $params = [$name];
            $base = category_url($name);
            break;
        }
    }
} elseif ($tag_slug !== '' && preg_match('/^[a-z0-9-]{1,100}$/', $tag_slug)) {
    // Find the real tag that matches the slug
    $lists = $db->query("SELECT tags FROM posts
                         WHERE status = 'published' AND tags IS NOT NULL AND tags != ''")
                ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($lists as $list) {
        foreach (explode(',', $list) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && slugify($tag) === $tag_slug) {
                $type = 'tag';
                $label = $tag;
                $where = " AND FIND_IN_SET(?, REPLACE(tags, ', ', ','))";
                $params = [$tag];
                $base = tag_url($tag);
                break 2;   // stop both loops
            }
        }
    }
}

// Nothing matched: show the 404 page
if ($type === '') {
    require __DIR__ . '/404.php';
    exit;
}

$total = count_posts($db, $where, $params);
$pages = max(1, (int)ceil($total / POSTS_PER_PAGE));
$page  = min(current_page_number(), $pages);
$posts = fetch_posts($db, $where, $params, POSTS_PER_PAGE, ($page - 1) * POSTS_PER_PAGE);

$heading = ($type === 'category' ? 'Category: ' : 'Tag: ') . $label;
$page_title       = $heading . ($page > 1 ? ' (page ' . $page . ')' : '') . ' | ' . APP_NAME;
$meta_description = 'Read all posts in "' . $label . '" on ' . APP_NAME . '.';
$canonical_url    = $page > 1 ? $base . '?page=' . $page : $base;

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-extrabold mb-6"><?= e($heading) ?></h1>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($posts as $post): ?>
        <?= render_post_card($post) ?>
    <?php endforeach; ?>
</div>
<?= render_pagination($page, $pages, $base) ?>

<?php require __DIR__ . '/includes/footer.php'; ?>