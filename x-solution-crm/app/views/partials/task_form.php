<?php $compact ??= false; ?>
<form method="post" action="<?= e(url('/aufgaben')) ?>" class="task-form <?= $compact ? 'compact' : '' ?>">
    <?= csrf_field() ?>
    <?php if (!empty($customerId)): ?><input type="hidden" name="customer_id" value="<?= (int) $customerId ?>"><?php endif; ?>
    <?php if (!empty($contractId)): ?><input type="hidden" name="contract_id" value="<?= (int) $contractId ?>"><?php endif; ?>
    <label>Neue Aufgabe / neuer Termin<input name="title" required maxlength="190" placeholder="z. B. Verlängerung besprechen"></label>
    <label>Fällig am<input name="due_date" data-date placeholder="TT.MM.JJJJ" value="<?= date('d.m.Y', strtotime('+1 day')) ?>"></label>
    <label class="tf-extra">Uhrzeit<input name="due_time" placeholder="09:00" value="09:00"></label>
    <input type="hidden" name="kind" value="aufgabe">
    <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span class="sr-only">Hinzufügen</span></button>
</form>
