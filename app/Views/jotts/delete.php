<?php $this->setVar('title', 'Delete jott'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Delete this jott?</h1>
    <p class="jott-text"><?= esc($jott['text']) ?></p>
    <p class="muted">This can't be undone. The audit log keeps a record that it was deleted, but not its text.</p>
    <form action="/jotts/<?= esc($jott['public_id'], 'attr') ?>/delete" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="danger">Delete jott</button>
        <a class="button" href="/jotts/<?= esc($jott['public_id'], 'attr') ?>">Cancel</a>
    </form>
</section>
<?= $this->endSection() ?>
