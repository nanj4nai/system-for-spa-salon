<?php
// Expected variable: $p (appointment row)
?>

<!-- LEFT INFO -->
<div>
    <h3 class="font-semibold text-lg">
        Appointment #<?= (int)$p['appointment_id'] ?>
    </h3>

    <p class="text-sm opacity-70 mt-1">
        Client: <?= htmlspecialchars($p['client_name']) ?>
    </p>

    <p class="text-sm mt-2">
        Total: <strong>₱<?= number_format($p['total_amount'], 2) ?></strong>
    </p>

    <p class="text-sm">
        Paid:
        <strong class="text-indigo-600">
            ₱<?= number_format($p['amount_paid'], 2) ?>
        </strong>
    </p>

    <?php if (!empty($p['balance_due']) && $p['balance_due'] > 0): ?>
        <p class="text-sm text-yellow-600 dark:text-yellow-400">
            Remaining Balance: ₱<?= number_format($p['balance_due'], 2) ?>
        </p>
    <?php else: ?>
        <p class="text-sm text-green-600 dark:text-green-400">
            Paid in Full
        </p>
    <?php endif; ?>

    <?php if (!empty($p['payment_reference'])): ?>
        <p class="text-xs opacity-60 mt-2">
            Reference #: <?= htmlspecialchars($p['payment_reference']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($p['created_at'])): ?>
        <p class="text-xs opacity-60">
            Submitted: <?= htmlspecialchars($p['created_at']) ?>
        </p>
    <?php endif; ?>
</div>