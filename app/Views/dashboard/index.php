<?php $this->setVar('title', $collection === null ? 'Jotts' : $collection['name']); ?>
<?php $this->setVar('mainClass', 'app-grid'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= view('partials/collections_nav', ['collections' => $collections, 'collection' => $collection]) ?>
<div class="app-main">
    <section class="card composer">
        <h1 class="visually-hidden">Jotts</h1>
        <form action="/jotts" method="post">
            <?= csrf_field() ?>
            <?= view('partials/jott_text_field', ['label' => 'New jott', 'value' => $draft ?? '', 'textError' => $textError ?? null]) ?>
            <button type="submit" class="primary">Save jott</button>
        </form>
    </section>
    <section class="feed" aria-label="<?= $collection === null ? 'Your jotts' : 'Jotts in ' . esc($collection['name'], 'attr') ?>">
        <?php if ($collection !== null): ?>
            <header class="feed-header">
                <h2><?= esc($collection['name']) ?></h2>
                <a href="/collections/<?= esc($collection['public_id'], 'attr') ?>/settings">Settings &amp; history</a>
            </header>
            <?= view('partials/collection_delivery', ['collection' => $collection, 'webhook' => $webhook]) ?>
        <?php endif ?>
        <?php if ($jotts === []): ?>
            <p class="card muted">
                <?= $collection === null
                    ? 'No jotts yet. Add a hashtag and it will be filed into a collection automatically.'
                    : 'No jotts in this collection yet.' ?>
            </p>
        <?php endif ?>
        <?php foreach ($jotts as $jott): ?>
            <?php
            $state      = $states[(int) $jott['id']] ?? null;
            $manualMode = ($webhook['mode'] ?? null) === 'manual';
            $selectable = $manualMode && in_array($state ?? 'ready', ['ready', 'rearmed'], true);
            ?>
            <article class="card jott">
                <p class="jott-text"><?= esc($jott['text']) ?></p>
                <?php if ($collection !== null && ($manualMode || $state !== null)): ?>
                    <div class="delivery-row">
                        <?php if ($selectable): ?>
                            <span class="select-jott">
                                <input type="checkbox" id="select-<?= esc($jott['public_id'], 'attr') ?>" name="jotts[]" value="<?= esc($jott['public_id'], 'attr') ?>" form="send-form">
                                <label for="select-<?= esc($jott['public_id'], 'attr') ?>">Select<span class="visually-hidden"> this jott to send</span></label>
                            </span>
                        <?php endif ?>
                        <?= view('partials/delivery_state', ['state' => $state ?? 'ready']) ?>
                        <?php if ($manualMode && in_array($state, ['succeeded', 'failed'], true)): ?>
                            <form action="/collections/<?= esc($collection['public_id'], 'attr') ?>/jotts/<?= esc($jott['public_id'], 'attr') ?>/rearm" method="post" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit">Re-arm<span class="visually-hidden"> this jott</span></button>
                            </form>
                        <?php endif ?>
                    </div>
                <?php endif ?>
                <?php if (! empty($memberships[(int) $jott['id']])): ?>
                    <ul class="chips" aria-label="Collections">
                        <?php foreach ($memberships[(int) $jott['id']] as $membership): ?>
                            <li class="chip"><a href="/collections/<?= esc($membership['public_id'], 'attr') ?>"><?= esc($membership['name']) ?></a></li>
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
</div>
<?= $this->endSection() ?>
