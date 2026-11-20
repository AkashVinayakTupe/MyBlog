<?php
// post.php - one article page. Opened as /post-slug

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site.php';

// Check the slug looks valid
$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-z0-9-]{1,255}$/', $slug)) {
    require __DIR__ . '/404.php';
    exit;
}

// Find the published post
$db   = get_db();
$stmt = $db->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    require __DIR__ . '/404.php';
    exit;
}

// ---------- Related posts: same category first, then latest ----------
$related = [];
$exclude = [(int)$post['id']];

if (!empty($post['category'])) {
    $related = fetch_posts($db, ' AND category = ? AND id != ?', [$post['category'], $post['id']], 3);
    foreach ($related as $r) {
        $exclude[] = (int)$r['id'];
    }
}
if (count($related) < 3) {
    // ids are cast to int, so this is safe to put in the SQL text
    $not_in  = ' AND id NOT IN (' . implode(',', $exclude) . ')';
    $related = array_merge($related, fetch_posts($db, $not_in, [], 3 - count($related)));
}

// ---------- Values for the page ----------
$published    = $post['published_at'] ?: $post['created_at'];
$updated      = $post['updated_at'] ?? '';
$show_updated = $updated && date('Y-m-d', strtotime($updated)) > date('Y-m-d', strtotime($published));
$post_link    = post_url($post);

$page_title       = $post['seo_title'] ?: $post['title'];
$meta_description = $post['meta_description'] ?: strip_tags((string)$post['excerpt']);
$canonical_url    = $post_link;
$og_type          = 'article';
$og_image         = !empty($post['cover_image']) ? base_url('uploads/' . $post['cover_image']) : '';

// Share links (plain links, no tracking scripts)
$share_url   = rawurlencode($post_link);
$share_text  = rawurlencode($post['title']);
$share_links = [
    'WhatsApp' => 'https://wa.me/?text=' . $share_text . '%20' . $share_url,
    'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url,
    'X'        => 'https://twitter.com/intent/tweet?text=' . $share_text . '&url=' . $share_url,
    'Telegram' => 'https://t.me/share/url?url=' . $share_url . '&text=' . $share_text,
];

// Tags are saved like "cricket, india, news"
$tags = array_filter(array_map('trim', explode(',', (string)$post['tags'])));

require __DIR__ . '/includes/header.php';
?>

<!-- Reading progress bar -->
<div id="read-bar" class="fixed top-0 left-0 h-1.5 w-0 bg-sun z-[60]"></div>

<article>
    <div class="max-w-3xl mx-auto">
        <!-- Breadcrumbs -->
        <nav aria-label="Breadcrumb" class="text-sm text-ink/60 mb-5">
            <a href="<?= e(base_url()) ?>" class="hover:text-ink">Home</a>
            <?php if (!empty($post['category'])): ?>
                <span class="mx-1">/</span><a href="<?= e(category_url($post['category'])) ?>" class="hover:text-ink"><?= e($post['category']) ?></a>
            <?php endif; ?>
        </nav>

        <?= category_chip($post['category']) ?>

        <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-[1.1] tracking-tight"><?= e($post['title']) ?></h1>

        <?php if (!empty($post['excerpt'])): ?>
            <p class="mt-5 font-serif text-xl sm:text-2xl text-ink/70 leading-snug"><?= e($post['excerpt']) ?></p>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-ink/60">
            <span class="font-semibold text-ink">By <?= e(APP_NAME) ?></span>
            <time datetime="<?= e(date('c', strtotime($published))) ?>"><?= e(format_date($published)) ?></time>
            <?php if ($show_updated): ?>
                <span>Updated <time datetime="<?= e(date('c', strtotime($updated))) ?>"><?= e(format_date($updated)) ?></time></span>
            <?php endif; ?>
            <span><?= reading_time((string)$post['content']) ?> min read</span>
        </div>
    </div>

    <!-- Cover: wide, not lazy-loaded (first thing people see) -->
    <figure class="max-w-4xl mx-auto mt-8">
        <div class="relative aspect-[16/9] overflow-hidden rounded-3xl group">
            <?= render_cover($post, true) ?>
        </div>
        <?php if (!empty($post['cover_image']) && !empty($post['image_credit'])): ?>
            <figcaption class="text-xs text-ink/60 mt-2 px-1"><?= e($post['image_credit']) ?></figcaption>
        <?php endif; ?>
    </figure>

    <div class="max-w-[720px] mx-auto mt-10">
        <!-- Article body. This HTML was cleaned by sanitize_html() when it was SAVED.
             Every save path (AI pipeline, post editor) must call sanitize_html(). -->
        <div class="article-body prose prose-lg max-w-none">
            <?= $post['content'] ?>
        </div>

        <?php if ($tags): ?>
            <div class="flex flex-wrap gap-2 mt-10">
                <?php foreach ($tags as $tag): ?>
                    <a href="<?= e(tag_url($tag)) ?>"
                       class="text-sm border-2 border-ink/10 hover:border-ink rounded-full px-3 py-1">#<?= e($tag) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Share buttons -->
        <div class="mt-10 border-t-2 border-ink/10 pt-6">
            <p class="font-extrabold mb-3">Share this story</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($share_links as $name => $href): ?>
                    <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"
                       class="text-sm font-semibold bg-ink text-white rounded-full px-4 py-2 hover:bg-ink/85"><?= e($name) ?></a>
                <?php endforeach; ?>
                <button type="button" id="copy-link" data-url="<?= e($post_link) ?>"
                        class="text-sm font-semibold border-2 border-ink rounded-full px-4 py-2 hover:bg-sun">Copy link</button>
            </div>
        </div>
    </div>
</article>

<?php if ($related): ?>
    <section class="mt-16 pt-10 border-t-2 border-ink/10">
        <h2 class="flex items-center gap-3 text-2xl font-extrabold mb-6">
            <span class="inline-block w-8 h-2 bg-sun rounded-full"></span>Keep reading
        </h2>
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($related as $r): ?>
                <?= render_post_card($r) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<script>
    // Progress bar grows as you scroll
    addEventListener('scroll', function () {
        var h = document.documentElement;
        var max = h.scrollHeight - h.clientHeight;
        document.getElementById('read-bar').style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
    }, { passive: true });

    // Copy link button
    document.getElementById('copy-link').addEventListener('click', function () {
        var btn = this;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(btn.dataset.url).then(function () { btn.textContent = 'Link copied'; });
        }
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>