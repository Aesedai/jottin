<?php $this->setVar('title', 'Change password'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Change password</h1>
    <form action="/account/password" method="post">
        <?= csrf_field() ?>
        <?= view('partials/field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
        <?= view('partials/field', ['name' => 'new_password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least 12 characters.']) ?>
        <button type="submit" class="primary">Change password</button>
    </form>
</section>
<?= $this->endSection() ?>
