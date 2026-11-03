<?php $mode = $webhook['mode'] ?? null; ?>
<div class="delivery-banner">
    <?php if ($webhook === null): ?>
        <p class="muted">No webhook is configured, so nothing is delivered from this collection.</p>
    <?php elseif ($mode === 'disabled'): ?>
        <p class="muted"><strong>Delivery is disabled.</strong> No deliveries occur from this collection.</p>
    <?php elseif ($mode === 'automatic'): ?>
        <p><strong>Automatic delivery to <?= esc($webhook['endpoint_host']) ?>.</strong> New jotts entering this collection are delivered automatically.</p>
    <?php else: ?>
        <p><strong>Manual delivery to <?= esc($webhook['endpoint_host']) ?>.</strong> Select jotts below, then send them.</p>
        <form id="send-form" action="/collections/<?= esc($collection['public_id'], 'attr') ?>/send" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="send-automation"><span class="send-dot" aria-hidden="true"></span>Send selected</button>
        </form>
    <?php endif ?>
</div>
