<div class="field">
    <label for="name"><?= esc($label) ?></label>
    <input id="name" name="name" type="text" maxlength="100" required value="<?= esc($value, 'attr') ?>"
        <?php if (! empty($nameError)): ?>aria-invalid="true" aria-describedby="name-error"<?php endif ?>>
    <?php if (! empty($nameError)): ?><p class="field-error" id="name-error"><?= esc($nameError) ?></p><?php endif ?>
</div>
