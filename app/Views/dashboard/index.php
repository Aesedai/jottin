<?php $this->setVar('title', 'Dashboard'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Welcome, <?= esc(service('currentUser')->name()) ?></h1>
    <p class="muted">Your jotts will appear here.</p>
</section>
<?= $this->endSection() ?>
