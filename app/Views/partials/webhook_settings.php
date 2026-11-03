<?php
$isOwner  = service('currentUser')->hasRole('owner');
$selected = $webhook['mode'] ?? 'disabled';
$modes    = [
    'disabled'  => ['Disabled', 'Nothing is sent from this collection.'],
    'manual'    => ['Manual', 'You select jotts and send them. Each one needs re-arming before it can be sent again.'],
    'automatic' => ['Automatic', 'Each new jott entering this collection is sent automatically.'],
];
?>
<section class="card">
    <h2>Webhook</h2>
    <?php if (! empty($newSecret)): ?>
        <div class="alert alert-ok" role="status">
            <p><strong>Copy this signing secret now.</strong> It won't be shown again. Use it to verify the <code>X-Jottin-Signature</code> header.</p>
            <p><code><?= esc($newSecret) ?></code></p>
        </div>
    <?php endif ?>
    <?php if ($webhook !== null): ?>
        <p>Sends to <strong><?= esc($webhook['endpoint_host']) ?></strong>. The full URL isn't displayed, because it may contain a token.</p>
    <?php endif ?>
    <?php if ($isOwner): ?>
        <form action="/collections/<?= esc($collection['public_id'], 'attr') ?>/webhook" method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label for="url"><?= $webhook === null ? 'Endpoint URL' : 'Replace the endpoint URL' ?></label>
                <input id="url" name="url" type="url" maxlength="2048" autocomplete="off" inputmode="url"
                    <?= $webhook === null ? 'required' : '' ?>
                    <?php if (! empty($webhookError)): ?>aria-invalid="true" aria-describedby="url-error"<?php endif ?>>
                <?php if ($webhook !== null): ?><p class="hint">Leave blank to keep the current one.</p><?php endif ?>
                <?php if (! empty($webhookError)): ?><p class="field-error" id="url-error"><?= esc($webhookError) ?></p><?php endif ?>
            </div>
            <fieldset class="modes">
                <legend>Delivery mode</legend>
                <?php foreach ($modes as $value => [$label, $description]): ?>
                    <div class="mode">
                        <input type="radio" id="mode-<?= $value ?>" name="mode" value="<?= $value ?>" <?= $selected === $value ? 'checked' : '' ?>>
                        <label for="mode-<?= $value ?>">
                            <?= esc($label) ?>
                            <?php if ($value === 'automatic'): ?><span class="jottout" aria-hidden="true"><span class="jott">Jott</span><span class="in">Out</span></span><?php endif ?>
                            <span class="mode-help"><?= esc($description) ?></span>
                        </label>
                    </div>
                <?php endforeach ?>
            </fieldset>
            <button type="submit" class="primary">Save webhook</button>
        </form>
    <?php else: ?>
        <p class="muted">Delivery mode: <strong><?= esc($modes[$selected][0]) ?></strong>. Only the workspace owner can change this.</p>
    <?php endif ?>
</section>
<section class="card">
    <h2>Delivery history</h2>
    <?php if ($history === []): ?>
        <p class="muted">No delivery attempts yet.</p>
    <?php else: ?>
        <ul class="rows">
            <?php foreach ($history as $attempt): ?>
                <li>
                    <div>
                        <strong><?= view('partials/delivery_state', ['state' => $attempt['status']]) ?></strong>
                        <span class="muted"><?= esc(ucfirst($attempt['trigger_type'])) ?> · <?= view('partials/time', ['at' => $attempt['finished_at'] ?? $attempt['created_at']]) ?><?php if ($attempt['http_status'] !== null): ?> · HTTP <?= (int) $attempt['http_status'] ?><?php endif ?><?php if ($attempt['endpoint_host'] !== null): ?> · <?= esc($attempt['endpoint_host']) ?><?php endif ?></span>
                        <span class="muted">Jott <code><?= esc($attempt['jott_ref']) ?></code></span>
                        <?php if ($attempt['error_summary'] !== null): ?><span class="delivery-error"><?= esc($attempt['error_summary']) ?></span><?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
