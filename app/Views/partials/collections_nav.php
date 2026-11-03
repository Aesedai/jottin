<nav class="collections-nav" aria-label="Collections">
    <h2 class="nav-title">Collections</h2>
    <ul>
        <li><a href="/dashboard"<?= $collection === null ? ' aria-current="page"' : '' ?>>All jotts</a></li>
        <?php foreach ($collections as $item): ?>
            <li>
                <a href="/collections/<?= esc($item['public_id'], 'attr') ?>"<?= ($collection['id'] ?? null) === $item['id'] ? ' aria-current="page"' : '' ?>>
                    <span class="nav-name"><?= esc($item['name']) ?></span>
                    <span class="nav-count"><span class="visually-hidden">, jotts: </span><?= (int) $item['jott_count'] ?></span>
                </a>
            </li>
        <?php endforeach ?>
        <li><a href="/collections" class="nav-manage">Manage collections</a></li>
    </ul>
</nav>
