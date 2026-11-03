<div class="field">
    <label for="text"><?= esc($label) ?></label>
    <textarea id="text" name="text" rows="<?= (int) ($rows ?? 3) ?>" maxlength="10000" required
        placeholder="Follow up with Alex about #interview"
        <?php if (! empty($textError)): ?>aria-invalid="true" aria-describedby="text-error"<?php endif ?>><?= esc($value ?? '') ?></textarea>
    <?php if (! empty($textError)): ?><p class="field-error" id="text-error"><?= esc($textError) ?></p><?php endif ?>
</div>
