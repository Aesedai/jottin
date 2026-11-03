<?php
$labels = [
    'ready'     => ['○', 'Ready to send'],
    'queued'    => ['…', 'Queued'],
    'sending'   => ['…', 'Sending'],
    'succeeded' => ['✓', 'Delivered'],
    'failed'    => ['✕', 'Failed'],
    'rearmed'   => ['↻', 'Re-armed, ready to send'],
];
[$icon, $label] = $labels[$state] ?? $labels['ready'];
?>
<span class="delivery-state state-<?= esc($state, 'attr') ?>"><span aria-hidden="true"><?= $icon ?></span> <?= esc($label) ?></span>
