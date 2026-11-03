<?php $this->setVar('title', 'Not allowed'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Not allowed</h1>
    <p class="muted">Only a workspace owner can do that.</p>
    <p><a href="/dashboard">Back to dashboard</a></p>
</section>
<?= $this->endSection() ?>
