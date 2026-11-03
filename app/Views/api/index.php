<?php $this->setVar('title', 'API keys'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>API keys</h1>
    <p class="muted">A key lets another system add jotts to this workspace.</p>
    <?php if (! empty($newToken)): ?>
        <div class="alert alert-ok" role="status">
            <p><strong>Copy this key now.</strong> It won't be shown again, because only a hash of it is stored.</p>
            <p><code><?= esc($newToken) ?></code></p>
        </div>
    <?php endif ?>
    <?php if ($credentials === []): ?>
        <p class="muted">No API keys yet.</p>
    <?php else: ?>
        <ul class="rows">
            <?php foreach ($credentials as $credential): ?>
                <li>
                    <div>
                        <strong><?= esc($credential['label']) ?></strong>
                        <span class="muted">
                            <code><?= esc($credential['token_prefix']) ?>…</code>
                            · Created <?= view('partials/time', ['at' => $credential['created_at']]) ?>
                            · <?php if ($credential['last_used_at'] === null): ?>Never used<?php else: ?>Last used <?= view('partials/time', ['at' => $credential['last_used_at']]) ?><?php endif ?>
                        </span>
                    </div>
                    <div class="row-actions">
                        <?php if ($credential['revoked_at'] !== null): ?>
                            <span class="badge">Revoked</span>
                        <?php else: ?>
                            <span class="badge">Active</span>
                            <form action="/settings/api/<?= esc($credential['public_id'], 'attr') ?>/revoke" method="post" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="danger" aria-label="Revoke <?= esc($credential['label'], 'attr') ?>">Revoke</button>
                            </form>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<section class="card narrow">
    <h2>Create a key</h2>
    <form action="/settings/api" method="post">
        <?= csrf_field() ?>
        <?= view('partials/field', ['name' => 'label', 'label' => 'Label', 'value' => '', 'hint' => 'What will use this key, such as "n8n".']) ?>
        <button type="submit" class="primary">Create key</button>
    </form>
</section>
<section class="card">
    <h2>Send a jott</h2>
    <pre class="code-sample"><code>curl -X POST <?= esc(base_url('v1/jotts')) ?> \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"text": "Follow up with Alex about #interview"}'</code></pre>
</section>
<?= $this->endSection() ?>
