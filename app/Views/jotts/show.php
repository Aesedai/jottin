<?php $this->setVar('title', 'Edit jott'); ?>
<?php
$fromTags   = array_filter($memberships, static fn (array $m): bool => (int) $m['is_automatic'] === 1);
$manual     = array_filter($memberships, static fn (array $m): bool => (int) $m['is_manual'] === 1);
$manualIds  = array_column($manual, 'public_id');
$addable    = array_filter($collections, static fn (array $c): bool => ! in_array($c['public_id'], $manualIds, true));
$jottAction = '/jotts/' . esc($jott['public_id'], 'attr');
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<p><a href="/dashboard">&larr; All jotts</a></p>
<section class="card">
    <h1>Edit jott</h1>
    <form action="/jotts/<?= esc($jott['public_id'], 'attr') ?>" method="post">
        <?= csrf_field() ?>
        <?= view('partials/jott_text_field', ['label' => 'Text', 'rows' => 6, 'value' => $draft ?? $jott['text'], 'textError' => $textError ?? null]) ?>
        <button type="submit" class="primary">Save changes</button>
    </form>
    <dl class="facts">
        <div><dt>Created</dt><dd><?= view('partials/time', ['at' => $jott['created_at']]) ?></dd></div>
        <div><dt>Last changed</dt><dd><?= view('partials/time', ['at' => $jott['updated_at']]) ?></dd></div>
        <div><dt>Source</dt><dd><?= $jott['source'] === 'api' ? 'API' : 'Web' ?></dd></div>
    </dl>
</section>
<section class="card">
    <h2>From hashtags</h2>
    <?php if ($fromTags === []): ?>
        <p class="muted">No hashtags in this jott.</p>
    <?php else: ?>
        <ul class="chips">
            <?php foreach ($fromTags as $membership): ?><li class="chip">#<?= esc($membership['tag_key'] ?? $membership['name']) ?></li><?php endforeach ?>
        </ul>
        <p class="muted">To remove one of these, edit the hashtag out of the text.</p>
    <?php endif ?>
</section>
<section class="card">
    <h2>Added manually</h2>
    <?php if ($manual === []): ?>
        <p class="muted">Not added to any collection by hand.</p>
    <?php else: ?>
        <ul class="rows">
            <?php foreach ($manual as $membership): ?>
                <li>
                    <a href="/collections/<?= esc($membership['public_id'], 'attr') ?>"><?= esc($membership['name']) ?></a>
                    <form action="<?= $jottAction ?>/collections/<?= esc($membership['public_id'], 'attr') ?>/remove" method="post" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" aria-label="Remove from <?= esc($membership['name'], 'attr') ?>">Remove</button>
                    </form>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <div class="add-collection">
        <?php if ($addable !== []): ?>
            <form action="<?= $jottAction ?>/collections" method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="collection">Add to a collection</label>
                    <select id="collection" name="collection" required>
                        <?php foreach ($addable as $item): ?>
                            <option value="<?= esc($item['public_id'], 'attr') ?>"><?= esc($item['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <button type="submit">Add</button>
            </form>
        <?php endif ?>
        <form action="<?= $jottAction ?>/collections" method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label for="new_collection">Or create a new collection</label>
                <input id="new_collection" name="new_collection" type="text" maxlength="100" required
                    <?php if (! empty($collectionError)): ?>aria-invalid="true" aria-describedby="new-collection-error"<?php endif ?>>
                <?php if (! empty($collectionError)): ?><p class="field-error" id="new-collection-error"><?= esc($collectionError) ?></p><?php endif ?>
            </div>
            <button type="submit">Create and add</button>
        </form>
    </div>
</section>
<p class="danger-zone"><a class="danger-link" href="/jotts/<?= esc($jott['public_id'], 'attr') ?>/delete">Delete this jott</a></p>
<?= $this->endSection() ?>
