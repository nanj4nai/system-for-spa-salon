<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false]);
    exit;
}

require_once "php/db.php";

$sql = "
SELECT
    a.id AS appointment_id,
    c.full_name AS client_name,
    t.total_amount,
    t.amount_paid,
    t.balance_due,
    t.payment_status,
    a.payment_reference,
    a.payment_proof,
    t.created_at
FROM appointments a
JOIN clients c ON c.id = a.client_id
JOIN spa_transactions t ON t.appointment_id = a.id
WHERE
    a.source = 'online'
    AND a.payment_verified = 0
    AND t.status = 'pending_verification'
    AND t.payment_status IN ('partial','paid')
ORDER BY t.created_at ASC
";

$res = $conn->query($sql);
$items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

ob_start();

if (empty($items)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center opacity-70">
        No pending payments to review.
    </div>
<?php endif; ?>

<?php foreach ($items as $p): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow border">

        <span class="inline-block mb-3 px-3 py-1 rounded-full text-xs font-medium
            <?= $p['payment_status'] === 'partial'
                ? 'bg-yellow-100 text-yellow-800'
                : 'bg-green-100 text-green-800' ?>">
            <?= strtoupper($p['payment_status']) ?> PAYMENT
        </span>

        <div class="flex flex-col lg:flex-row justify-between gap-6 items-start">

            <?php include __DIR__ . "/partials/left-info.php"; ?>

            <div class="flex gap-2 items-start">
                <button
                    data-proof-src="php/view-payment-proof.php?file=<?= urlencode($p['payment_proof']) ?>"
                    class="viewProofBtn px-3 py-2 bg-indigo-600 text-white rounded">
                    View Proof
                </button>

                <button
                    data-appointment-id="<?= $p['appointment_id'] ?>"
                    class="approveBtn px-4 py-2 bg-green-600 text-white rounded">
                    Approve
                </button>

                <button
                    data-appointment-id="<?= $p['appointment_id'] ?>"
                    class="rejectBtn px-4 py-2 bg-red-600 text-white rounded">
                    Reject
                </button>
            </div>
        </div>
    </div>
<?php endforeach;

$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
