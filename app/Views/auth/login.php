<?php $this->setVar('title', 'Sign in'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Sign in</h1>
    <form action="/login" method="post">
        <?= csrf_field() ?>
        <?= view('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => set_value('email'), 'autocomplete' => 'username']) ?>
        <?= view('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
        <button type="submit" class="primary">Sign in</button>
    </form>
    <p class="muted">New here? <a href="/signup">Create a workspace</a></p>
</section>
<?= $this->endSection() ?>
