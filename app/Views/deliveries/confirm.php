<?php $this->setVar('title', 'Confirm send'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Send <?= count($jotts) ?> <?= count($jotts) === 1 ? 'jott' : 'jotts' ?>?</h1>
    <p>From <strong><?= esc($collection['name']) ?></strong> to <strong><?= esc($webhook['endpoint_host']) ?></strong>.</p>
    <?php if ($jotts === []): ?>
        <p class="muted">None of the selected jotts are in this collection.</p>
    <?php else: ?>
        <ul class="confirm-list">
            <?php foreach ($jotts as $jott): ?>
                <li><?= esc(mb_strimwidth($jott['text'], 0, 120, '…')) ?></li>
            <?php endforeach ?>
        </ul>
        <form action="/collections/<?= esc($collection['public_id'], 'attr') ?>/send" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <?php foreach ($jotts as $jott): ?>
                <input type="hidden" name="jotts[]" value="<?= esc($jott['public_id'], 'attr') ?>">
            <?php endforeach ?>
            <button type="submit" class="send-automation"><span class="send-dot" aria-hidden="true"></span>Send to Automation</button>
            <a class="button" href="/collections/<?= esc($collection['public_id'], 'attr') ?>">Cancel</a>
        </form>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
