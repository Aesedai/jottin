<?php $this->setVar('title', 'Members'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Members</h1>
    <?php if (! empty($created)): ?>
        <div class="alert alert-ok" role="status">
            <p>Added <strong><?= esc($created['email']) ?></strong>. Share this password securely. It won't be shown again, and they should change it after signing in.</p>
            <p><code><?= esc($created['password']) ?></code></p>
        </div>
    <?php endif ?>
    <ul class="rows">
        <?php foreach ($members as $member): ?>
            <li>
                <div>
                    <strong><?= esc($member['name']) ?></strong>
                    <span class="muted"><?= esc($member['email']) ?></span>
                </div>
                <div class="row-actions">
                    <span class="badge"><?= esc(ucfirst($member['role'])) ?></span>
                    <?php if ($member['role'] !== 'owner'): ?>
                        <form action="/members/<?= (int) $member['id'] ?>/remove" method="post" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="danger" aria-label="Remove <?= esc($member['name'], 'attr') ?>">Remove</button>
                        </form>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
</section>
<section class="card narrow">
    <h2>Add a member</h2>
    <form action="/members" method="post">
        <?= csrf_field() ?>
        <?= view('partials/field', ['name' => 'name', 'label' => 'Name', 'value' => empty($created) ? set_value('name') : '']) ?>
        <?= view('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => empty($created) ? set_value('email') : '']) ?>
        <button type="submit" class="primary">Add member</button>
    </form>
</section>
<?= $this->endSection() ?>
