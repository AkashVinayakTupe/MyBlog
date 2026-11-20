<?php
// index.php - home page: hero stories, topic pills, then the latest list with pagination.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

$db = get_db();

// Page 1 shows 3 hero stories first. The list below continues after them.
$hero_count = 3;
$total = count_posts($db);
$rest  = max(0, $total - $hero_count);
$pages = max(1, (int)ceil($rest / POSTS_PER_PAGE));
$page  = min(current_page_number(), $pages);

$hero  = ($page === 1) ? fetch_posts($db, '', [], $hero_count, 0) : [];
$posts = fetch_posts($db, '', [], POSTS_PER_PAGE, $hero_count + ($page - 1) * POSTS_PER_PAGE);
$topics = get_category_counts($db, 8);

// Page title and canonical address
if ($page === 1) {
    $page_title    = APP_NAME . (defined('SITE_TAGLINE') ? ' - ' . SITE_TAGLINE : '');
    $canonical_url = base_url();
} else {
    $page_title    = 'Latest stories, page ' . $page . ' | ' . APP_NAME;
    $canonical_url = base_url('?page=' . $page);
}

require __DIR__ . '/includes/header.php';
?>

<?php if (!$hero && !$posts): ?>
    <div class="text-center py-24">
        <h1 class="text-3xl font-extrabold">No stories yet</h1>
        <p class="mt-2 font-serif text-lg text-ink/70">Published articles will appear here.</p>
    </div>
<?php else: ?>

    <?php if ($hero): ?>
        <!-- Hero: 1 big story and up to 2 smaller ones -->
        <h1 class="sr-only"><?= e(APP_NAME) ?> - latest stories</h1>
        <section class="grid gap-4 lg:grid-cols-3 lg:grid-rows-2 mb-10">
            <?php foreach ($hero as $i => $story): ?>
                <?= render_story($story, $i === 0) ?>
            <?php endforeach; ?>
        </section>

        <?php if ($topics): ?>
            <!-- Topic pills -->
            <section class="mb-12" aria-label="Browse topics">
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($topics as $name => $count): $cc = category_color($name); ?>
                        <a href="<?= e(category_url($name)) ?>"
                           class="inline-flex items-center gap-2 border-2 border-ink/10 hover:border-ink rounded-full px-4 py-2 text-sm font-semibold">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:<?= e($cc['bg']) ?>"></span>
                            <?= e($name) ?>
                            <span class="text-ink/50"><?= (int)$count ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <h1 class="text-3xl font-extrabold mb-8">Latest stories, page <?= (int)$page ?></h1>
    <?php endif; ?>

    <?php if ($posts): ?>
        <?php if ($page === 1): ?>
            <h2 class="flex items-center gap-3 text-2xl font-extrabold mb-6">
                <span class="inline-block w-8 h-2 bg-sun rounded-full"></span>Latest stories
            </h2>
        <?php endif; ?>
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($posts as $post): ?>
                <?= render_post_card($post) ?>
            <?php endforeach; ?>
        </div>
        <?= render_pagination($page, $pages, base_url()) ?>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>