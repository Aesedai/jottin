<?php $this->setVar('title', 'Sign up'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Create your workspace</h1>
    <form action="/signup" method="post">
        <?= csrf_field() ?>
        <?= view('partials/field', ['name' => 'workspace', 'label' => 'Workspace name', 'value' => set_value('workspace'), 'autocomplete' => 'organization']) ?>
        <?= view('partials/field', ['name' => 'name', 'label' => 'Your name', 'value' => set_value('name'), 'autocomplete' => 'name']) ?>
        <?= view('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => set_value('email'), 'autocomplete' => 'username']) ?>
        <?= view('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least 12 characters.']) ?>
        <button type="submit" class="primary">Create workspace</button>
    </form>
    <p class="muted">Already have an account? <a href="/login">Sign in</a></p>
</section>
<?= $this->endSection() ?>
