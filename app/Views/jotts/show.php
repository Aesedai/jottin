<?php $this->setVar('title', 'Edit jott'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<p><a href="/dashboard">&larr; All jotts</a></p>
<section class="card">
    <h1>Edit jott</h1>
    <form action="/jotts/<?= esc($jott['public_id'], 'attr') ?>" method="post">
        <?= csrf_field() ?>
        <?= view('partials/jott_text_field', ['label' => 'Text', 'rows' => 6, 'value' => $draft ?? $jott['text'], 'textError' => $textError ?? null]) ?>
        <button type="submit" class="primary">Save changes</button>
    </form>
    <dl class="facts">
        <div><dt>Created</dt><dd><?= view('partials/time', ['at' => $jott['created_at']]) ?></dd></div>
        <div><dt>Last changed</dt><dd><?= view('partials/time', ['at' => $jott['updated_at']]) ?></dd></div>
        <div><dt>Source</dt><dd><?= $jott['source'] === 'api' ? 'API' : 'Web' ?></dd></div>
    </dl>
</section>
<section class="card">
    <h2>From hashtags</h2>
    <?php $fromTags = array_filter($memberships, static fn (array $m): bool => (int) $m['is_automatic'] === 1); ?>
    <?php if ($fromTags === []): ?>
        <p class="muted">No hashtags in this jott.</p>
    <?php else: ?>
        <ul class="chips">
            <?php foreach ($fromTags as $membership): ?><li class="chip">#<?= esc($membership['name']) ?></li><?php endforeach ?>
        </ul>
        <p class="muted">To remove one of these, edit the hashtag out of the text.</p>
    <?php endif ?>
</section>
<p class="danger-zone"><a class="danger-link" href="/jotts/<?= esc($jott['public_id'], 'attr') ?>/delete">Delete this jott</a></p>
<?= $this->endSection() ?>
