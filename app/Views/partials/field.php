<?php
$fieldError = service('validation')->getError($name);
$errorId    = $name . '-error';
?>
<div class="field">
    <label for="<?= esc($name, 'attr') ?>"><?= esc($label) ?></label>
    <input
        id="<?= esc($name, 'attr') ?>"
        name="<?= esc($name, 'attr') ?>"
        type="<?= esc($type ?? 'text', 'attr') ?>"
        value="<?= esc($value ?? '', 'attr') ?>"
        <?php if (! empty($autocomplete)): ?>autocomplete="<?= esc($autocomplete, 'attr') ?>"<?php endif ?>
        <?php if ($fieldError !== ''): ?>aria-invalid="true" aria-describedby="<?= esc($errorId, 'attr') ?>"<?php endif ?>
        required
    >
    <?php if (! empty($hint)): ?><p class="hint"><?= esc($hint) ?></p><?php endif ?>
    <?php if ($fieldError !== ''): ?><p class="field-error" id="<?= esc($errorId, 'attr') ?>"><?= esc($fieldError) ?></p><?php endif ?>
</div>
