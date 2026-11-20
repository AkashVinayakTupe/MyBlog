<?php
// includes/footer.php
// Bottom part of every public page.
$footer_cats = get_nav_categories(get_db(), 6);
?>
</main>

<footer class="bg-ink text-white mt-16">
    <div class="max-w-6xl mx-auto px-4 py-12 grid gap-10 md:grid-cols-3">
        <div>
            <p class="flex items-center gap-2 text-2xl font-extrabold">
                <span class="inline-block w-3.5 h-3.5 bg-sun rounded-sm rotate-12"></span><?= e(APP_NAME) ?>
            </p>
            <?php if (defined('SITE_TAGLINE')): ?>
                <p class="mt-3 font-serif text-white/70"><?= e(SITE_TAGLINE) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <p class="font-bold text-sun mb-3">Topics</p>
            <ul class="space-y-2 text-sm text-white/80">
                <?php foreach ($footer_cats as $cat): ?>
                    <li><a href="<?= e(category_url($cat)) ?>" class="hover:text-sun"><?= e($cat) ?></a></li>
                <?php endforeach; ?>
                <?php if (!$footer_cats): ?><li>Topics appear here after the first post.</li><?php endif; ?>
            </ul>
        </div>

        <div>
            <p class="font-bold text-sun mb-3">About</p>
            <ul class="space-y-2 text-sm text-white/80">
                <li><a href="<?= e(base_url('page/about')) ?>" class="hover:text-sun">About</a></li>
                <li><a href="<?= e(base_url('page/author')) ?>" class="hover:text-sun">Author</a></li>
                <li><a href="<?= e(base_url('page/contact')) ?>" class="hover:text-sun">Contact</a></li>
                <li><a href="<?= e(base_url('page/privacy-policy')) ?>" class="hover:text-sun">Privacy Policy</a></li>
                <li><a href="<?= e(base_url('page/disclaimer')) ?>" class="hover:text-sun">Disclaimer</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 text-center text-xs text-white/60 py-5">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
    </div>
</footer>

</body>
</html>