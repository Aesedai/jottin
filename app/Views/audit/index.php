<?php $this->setVar('title', 'Audit log'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Audit log</h1>
    <p class="muted">Who changed what, newest first. Entries can't be edited or deleted, and they don't contain note text.</p>
    <?php if ($entries === []): ?>
        <p class="muted">Nothing recorded yet.</p>
    <?php else: ?>
        <ul class="rows">
            <?php foreach ($entries as $entry): ?>
                <li>
                    <div>
                        <strong><?= esc($entry['action']) ?></strong>
                        <span class="muted"><?= esc($entry['actor_label']) ?> · <?= esc($entry['entity_type']) ?> <code><?= esc($entry['entity_ref']) ?></code></span>
                    </div>
                    <span class="muted"><?= view('partials/time', ['at' => $entry['created_at']]) ?></span>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
