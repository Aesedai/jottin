<?php $this->setVar('title', 'Collection settings'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<p><a href="/collections/<?= esc($collection['public_id'], 'attr') ?>">&larr; Back to <?= esc($collection['name']) ?></a></p>
<section class="card">
    <h1>Settings for <?= esc($collection['name']) ?></h1>
    <form action="/collections/<?= esc($collection['public_id'], 'attr') ?>/settings" method="post">
        <?= csrf_field() ?>
        <?= view('partials/name_field', ['label' => 'Name', 'value' => $collection['name'], 'nameError' => $nameError ?? null]) ?>
        <?php if ($collection['tag_key'] !== null): ?>
            <p class="muted">Hashtag: <code>#<?= esc($collection['tag_key']) ?></code>. Renaming keeps this hashtag, so it still files into this collection.</p>
        <?php else: ?>
            <p class="muted">This is a manual collection, so no hashtag files into it.</p>
        <?php endif ?>
        <button type="submit" class="primary">Save name</button>
    </form>
</section>
<?= $this->endSection() ?>
