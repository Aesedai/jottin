<?php $this->setVar('title', 'Collections'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Collections</h1>
    <?php if ($collections === []): ?>
        <p class="muted">No collections yet. Use a hashtag in a jott, or create one below.</p>
    <?php else: ?>
        <ul class="rows">
            <?php foreach ($collections as $item): ?>
                <li>
                    <div>
                        <a href="/collections/<?= esc($item['public_id'], 'attr') ?>"><strong><?= esc($item['name']) ?></strong></a>
                        <span class="muted"><?= (int) $item['jott_count'] ?> <?= (int) $item['jott_count'] === 1 ? 'jott' : 'jotts' ?><?php if ($item['tag_key'] !== null): ?> · #<?= esc($item['tag_key']) ?><?php else: ?> · Manual<?php endif ?></span>
                    </div>
                    <a href="/collections/<?= esc($item['public_id'], 'attr') ?>/settings">Settings<span class="visually-hidden"> for <?= esc($item['name']) ?></span></a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<section class="card narrow">
    <h2>New collection</h2>
    <form action="/collections" method="post">
        <?= csrf_field() ?>
        <?= view('partials/name_field', ['label' => 'Name', 'value' => '', 'nameError' => $nameError ?? null]) ?>
        <button type="submit" class="primary">Create collection</button>
    </form>
</section>
<?= $this->endSection() ?>
