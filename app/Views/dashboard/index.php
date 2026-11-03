<?php $this->setVar('title', 'Jotts'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card composer">
    <h1 class="visually-hidden">Jotts</h1>
    <form action="/jotts" method="post">
        <?= csrf_field() ?>
        <?= view('partials/jott_text_field', ['label' => 'New jott', 'value' => $draft ?? '', 'textError' => $textError ?? null]) ?>
        <button type="submit" class="primary">Save jott</button>
    </form>
</section>
<section aria-label="Your jotts" class="feed">
    <?php if ($jotts === []): ?>
        <p class="card muted">No jotts yet. Add a hashtag and it will be filed into a collection automatically.</p>
    <?php endif ?>
    <?php foreach ($jotts as $jott): ?>
        <article class="card jott">
            <p class="jott-text"><?= esc($jott['text']) ?></p>
            <?php if (! empty($memberships[(int) $jott['id']])): ?>
                <ul class="chips" aria-label="Collections">
                    <?php foreach ($memberships[(int) $jott['id']] as $membership): ?>
                        <li class="chip">#<?= esc($membership['name']) ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <footer class="jott-meta">
                <span class="muted"><?= view('partials/time', ['at' => $jott['created_at']]) ?><?php if ($jott['source'] === 'api'): ?> · via API<?php endif ?></span>
                <a href="/jotts/<?= esc($jott['public_id'], 'attr') ?>">Open<span class="visually-hidden"> jott</span></a>
            </footer>
        </article>
    <?php endforeach ?>
</section>
<?= $this->endSection() ?>
