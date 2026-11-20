<?php
// includes/header.php
// Top part of every public page.
// A page can set these BEFORE including this file:
//   $page_title, $meta_description, $canonical_url, $robots,
//   $og_type, $og_image, $search_query, $extra_head

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site.php';

$page_title       = $page_title ?? APP_NAME;
$meta_description = $meta_description ?? (defined('SITE_TAGLINE') ? SITE_TAGLINE : '');
$canonical_url    = $canonical_url ?? '';
$robots           = $robots ?? 'index, follow';
$og_type          = $og_type ?? 'website';
$og_image         = $og_image ?? '';
$search_query     = $search_query ?? '';
$extra_head       = $extra_head ?? '';   // raw HTML written by us in code (JSON-LD later)

$header_db      = get_db();
$nav_categories = get_nav_categories($header_db, 8);
$ticker_posts   = fetch_posts($header_db, '', [], 6);   // latest titles for the ticker
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($meta_description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <?php if ($canonical_url !== ''): ?>
        <link rel="canonical" href="<?= e($canonical_url) ?>">
        <meta property="og:url" content="<?= e($canonical_url) ?>">
    <?php endif; ?>

    <!-- Open Graph and Twitter Card (used when someone shares a link) -->
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:title" content="<?= e($page_title) ?>">
    <meta property="og:description" content="<?= e($meta_description) ?>">
    <meta property="og:type" content="<?= e($og_type) ?>">
    <?php if ($og_image !== ''): ?>
        <meta property="og:image" content="<?= e($og_image) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $og_image !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($page_title) ?>">
    <meta name="twitter:description" content="<?= e($meta_description) ?>">
    <?php if ($og_image !== ''): ?>
        <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php endif; ?>

    <!-- Fonts: Bricolage Grotesque (headings, UI) and Newsreader (reading text) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;1,6..72,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        // Our colours and fonts for Tailwind
        tailwind.config = {
            theme: { extend: {
                colors: { ink: '#151A3C', sun: '#FFD60A' },
                fontFamily: {
                    sans:  ['"Bricolage Grotesque"', 'system-ui', 'sans-serif'],
                    serif: ['Newsreader', 'Georgia', 'serif']
                }
            } }
        };
    </script>
    <style>
        /* Yellow marker that grows under a title on hover */
        .hl { background: linear-gradient(#FFD60A, #FFD60A) 0 92% / 0 38% no-repeat; transition: background-size .25s; }
        .hl:hover, .group:hover .hl { background-size: 100% 38%; }

        /* Scrolling ticker */
        .ticker { display: flex; width: max-content; animation: tick 45s linear infinite; }
        .ticker:hover { animation-play-state: paused; }
        @keyframes tick { to { transform: translateX(-50%); } }

        /* Article text */
        .article-body { font-family: Newsreader, Georgia, serif; font-size: 1.2rem; line-height: 1.75; color: #151A3C; }
        .article-body h2, .article-body h3 { font-family: "Bricolage Grotesque", sans-serif; color: #151A3C; }
        .article-body a { text-decoration: underline; text-decoration-color: #FFD60A; text-decoration-thickness: 3px; text-underline-offset: 3px; }
        .article-body blockquote { border-left: 4px solid #FFD60A; font-style: italic; }

        a:focus-visible, button:focus-visible, input:focus-visible { outline: 3px solid #7C3AED; outline-offset: 2px; }

        @media (prefers-reduced-motion: reduce) {
            .ticker { animation: none; }
            * { transition: none !important; }
        }
    </style>
    <?= $extra_head ?>
</head>
<body class="bg-white text-ink antialiased">

<?php if ($ticker_posts): ?>
<!-- Trending ticker -->
<div class="bg-ink text-white text-sm overflow-hidden">
    <div class="max-w-6xl mx-auto flex items-center">
        <span class="shrink-0 bg-sun text-ink font-extrabold px-4 py-2">Trending</span>
        <div class="overflow-hidden flex-1">
            <div class="ticker py-2">
                <?php for ($copy = 0; $copy < 2; $copy++): ?>
                    <div class="flex shrink-0" <?= $copy === 1 ? 'aria-hidden="true"' : '' ?>>
                        <?php foreach ($ticker_posts as $tp): ?>
                            <a href="<?= e(post_url($tp)) ?>" tabindex="<?= $copy === 1 ? '-1' : '0' ?>"
                               class="px-6 whitespace-nowrap hover:text-sun"><?= e($tp['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-ink/10">
    <div class="max-w-6xl mx-auto px-4 pt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <!-- Logo -->
        <a href="<?= e(base_url()) ?>" class="flex items-center gap-2 text-2xl font-extrabold tracking-tight">
            <span class="inline-block w-3.5 h-3.5 bg-sun rounded-sm rotate-12"></span><?= e(APP_NAME) ?>
        </a>

        <!-- Search button (phones only) -->
        <button type="button" id="search-toggle" aria-label="Open search"
                class="sm:hidden p-2 rounded-full border-2 border-ink/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        </button>

        <!-- Search form (always visible on bigger screens) -->
        <form action="<?= e(base_url('search')) ?>" method="get" id="search-form"
              class="hidden sm:flex order-last sm:order-none basis-full sm:basis-auto">
            <input type="search" name="q" maxlength="100" placeholder="Search stories"
                   value="<?= e($search_query) ?>" aria-label="Search"
                   class="flex-1 sm:w-64 border-2 border-r-0 border-ink/10 rounded-l-full px-4 py-2 text-sm">
            <button class="bg-ink text-white px-5 py-2 text-sm font-semibold rounded-r-full hover:bg-ink/90">Search</button>
        </form>
    </div>

    <!-- Topic menu: coloured dot per category, scrolls sideways on phones -->
    <nav class="max-w-6xl mx-auto px-4 py-3 flex gap-2 overflow-x-auto whitespace-nowrap text-sm font-semibold" aria-label="Topics">
        <a href="<?= e(base_url()) ?>" class="px-4 py-1.5 rounded-full bg-ink text-white">Latest</a>
        <?php foreach ($nav_categories as $cat): $cc = category_color($cat); ?>
            <a href="<?= e(category_url($cat)) ?>"
               class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border-2 border-ink/10 hover:border-ink">
                <span class="w-2.5 h-2.5 rounded-full" style="background:<?= e($cc['bg']) ?>"></span><?= e($cat) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>

<script>
    // Phones: the search button shows or hides the search box
    document.getElementById('search-toggle').addEventListener('click', function () {
        var form = document.getElementById('search-form');
        form.classList.toggle('hidden');
        form.classList.toggle('flex');
        if (!form.classList.contains('hidden')) { form.querySelector('input').focus(); }
    });
</script>

<main class="max-w-6xl mx-auto px-4 py-8">