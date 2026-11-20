<?php
// includes/site.php
// Helper functions for the PUBLIC pages (links, queries, cards, pagination).

require_once __DIR__ . '/functions.php';

const POSTS_PER_PAGE = 9;

// ---------- Links ----------
function base_url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

function post_url(array $post): string
{
    return base_url($post['slug']);
}

function category_url(string $category): string
{
    return base_url('category/' . slugify($category));
}

function tag_url(string $tag): string
{
    return base_url('tag/' . slugify($tag));
}

// ---------- Dates ----------
function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $time = strtotime($datetime);
    return $time ? date('j M Y', $time) : '';
}

// ---------- Paging ----------
function current_page_number(): int
{
    $page = (int)($_GET['page'] ?? 1);
    return max(1, min($page, 10000));
}

// ---------- Queries ----------
// Gets PUBLISHED posts only. $where is SQL written by us. User values go in $params.
function fetch_posts(PDO $db, string $where, array $params, int $limit, int $offset = 0): array
{
    $sql = "SELECT id, title, slug, excerpt, content, cover_image, cover_alt,
                   category, tags, published_at, created_at
            FROM posts
            WHERE status = 'published' $where
            ORDER BY published_at DESC, id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_posts(PDO $db, string $where = '', array $params = []): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

// Category names used by published posts (for the menu)
function get_nav_categories(PDO $db, int $limit = 6): array
{
    $stmt = $db->query(
        "SELECT category, COUNT(*) AS total FROM posts
         WHERE status = 'published' AND category IS NOT NULL AND category != ''
         GROUP BY category ORDER BY total DESC LIMIT " . (int)$limit
    );
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// NEW: category name => number of posts (for the topic pills)
function get_category_counts(PDO $db, int $limit = 8): array
{
    $stmt = $db->query(
        "SELECT category, COUNT(*) AS total FROM posts
         WHERE status = 'published' AND category IS NOT NULL AND category != ''
         GROUP BY category ORDER BY total DESC LIMIT " . (int)$limit
    );
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// ---------- Category colours ----------
// The same category name always gets the same colour. Returns bg (background) and fg (text).
function category_color(?string $name): array
{
    static $palette = [
        ['#E11D48', '#FFFFFF'], ['#FBBF24', '#151A3C'], ['#059669', '#FFFFFF'], ['#0369A1', '#FFFFFF'],
        ['#7C3AED', '#FFFFFF'], ['#FB923C', '#151A3C'], ['#0F766E', '#FFFFFF'], ['#DB2777', '#FFFFFF'],
    ];
    $i = abs(crc32(strtolower((string)$name))) % count($palette);
    return ['bg' => $palette[$i][0], 'fg' => $palette[$i][1]];
}

// Small coloured category label. $link = false when it sits inside another link.
function category_chip(?string $category, bool $link = true): string
{
    if (!$category) {
        return '';
    }
    $c     = category_color($category);
    $style = 'background:' . $c['bg'] . ';color:' . $c['fg'];
    $class = 'inline-block text-xs font-bold px-2.5 py-1 rounded-full';
    if ($link) {
        return '<a href="' . e(category_url($category)) . '" class="' . $class . '" style="' . e($style) . '">'
             . e($category) . '</a>';
    }
    return '<span class="' . $class . '" style="' . e($style) . '">' . e($category) . '</span>';
}

// ---------- Cover image ----------
// Fills its parent (the parent needs "relative overflow-hidden" and a height).
// No image yet? Shows a coloured box with a big first letter.
function render_cover(array $post, bool $eager = false): string
{
    if (!empty($post['cover_image'])) {
        $alt = $post['cover_alt'] ?: $post['title'];
        return '<img src="' . e(base_url('uploads/' . $post['cover_image'])) . '" alt="' . e($alt) . '" '
             . 'width="1200" height="630" ' . ($eager ? 'fetchpriority="high"' : 'loading="lazy"')
             . ' class="absolute inset-0 w-full h-full object-cover transition duration-500 group-hover:scale-105">';
    }
    $c      = category_color($post['category'] ?? '');
    $letter = e(mb_strtoupper(mb_substr($post['title'], 0, 1)));
    return '<div class="absolute inset-0 transition duration-500 group-hover:scale-105" '
         . 'style="background:' . $c['bg'] . ';color:' . $c['fg'] . '">'
         . '<span class="absolute left-5 top-5 w-14 h-14 rounded-full border-4 border-current opacity-25"></span>'
         . '<span class="absolute -right-3 -bottom-10 text-[11rem] leading-none font-extrabold opacity-25 select-none" '
         . 'aria-hidden="true">' . $letter . '</span></div>';
}

// ---------- Standard post card (home list, category, search, related) ----------
function render_post_card(array $post): string
{
    $url  = e(post_url($post));
    $date = e(format_date($post['published_at'] ?: $post['created_at']));
    $mins = reading_time((string)$post['content']);

    return '<article class="group">'
        . '<a href="' . $url . '" class="relative block aspect-[16/10] overflow-hidden rounded-2xl" tabindex="-1" aria-hidden="true">'
        . render_cover($post) . '</a>'
        . '<div class="mt-4 flex items-center gap-3">' . category_chip($post['category'])
        . '<span class="text-xs text-ink/60">' . $date . '</span></div>'
        . '<h2 class="mt-2 text-xl font-extrabold leading-snug"><a href="' . $url . '" class="hl">'
        . e($post['title']) . '</a></h2>'
        . '<p class="mt-2 font-serif text-ink/70 line-clamp-2">' . e($post['excerpt']) . '</p>'
        . '<p class="mt-2 text-xs text-ink/60">' . $mins . ' min read</p>'
        . '</article>';
}

// ---------- Hero story (big image with the title on top) ----------
function render_story(array $post, bool $big = false): string
{
    $title_size = $big ? 'text-2xl sm:text-4xl' : 'text-lg sm:text-xl';
    $height     = $big ? 'min-h-[22rem] lg:min-h-[32rem] lg:col-span-2 lg:row-span-2' : 'min-h-[14rem]';
    $date       = e(format_date($post['published_at'] ?: $post['created_at']));
    $mins       = reading_time((string)$post['content']);

    return '<a href="' . e(post_url($post)) . '" class="group relative block overflow-hidden rounded-3xl ' . $height . '">'
        . render_cover($post, $big)
        . '<div class="absolute inset-0 bg-gradient-to-t from-ink/90 via-ink/30 to-transparent"></div>'
        . '<div class="absolute inset-x-0 bottom-0 p-5 sm:p-7 text-white">'
        . category_chip($post['category'], false)
        . '<h2 class="mt-3 font-extrabold leading-tight ' . $title_size . '">' . e($post['title']) . '</h2>'
        . ($big ? '<p class="mt-2 hidden sm:block font-serif text-white/80 line-clamp-2">' . e($post['excerpt']) . '</p>' : '')
        . '<p class="mt-3 text-xs text-white/70">' . $date . ' &nbsp; ' . $mins . ' min read</p>'
        . '</div></a>';
}

// ---------- Pagination links ----------
function render_pagination(int $page, int $total_pages, string $base, array $query = []): string
{
    if ($total_pages <= 1) {
        return '';
    }

    $link = function (int $n) use ($base, $query): string {
        $q = $query;
        if ($n > 1) {
            $q['page'] = $n;
        }
        return $base . ($q ? '?' . http_build_query($q) : '');
    };

    $item = 'px-4 py-2 rounded-full border-2 text-sm font-semibold ';
    $html = '<nav class="flex flex-wrap justify-center gap-2 mt-12" aria-label="Pagination">';

    if ($page > 1) {
        $html .= '<a href="' . e($link($page - 1)) . '" class="' . $item . 'border-ink/10 hover:border-ink">Previous</a>';
    }
    for ($n = max(1, $page - 2); $n <= min($total_pages, $page + 2); $n++) {
        if ($n === $page) {
            $html .= '<span class="' . $item . 'bg-ink text-white border-ink">' . $n . '</span>';
        } else {
            $html .= '<a href="' . e($link($n)) . '" class="' . $item . 'border-ink/10 hover:border-ink">' . $n . '</a>';
        }
    }
    if ($page < $total_pages) {
        $html .= '<a href="' . e($link($page + 1)) . '" class="' . $item . 'border-ink/10 hover:border-ink">Next</a>';
    }

    return $html . '</nav>';
}