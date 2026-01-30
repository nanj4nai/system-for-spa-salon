<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$appointment_service_id = intval($data['appointment_service_id'] ?? 0);
$service_id  = intval($data['service_id'] ?? 0);
$employee_id = intval($data['staff_id'] ?? 0);
$variant_id  = intval($data['variant_id'] ?? 0);
$quantity = 1;

if (!$appointment_service_id || !$service_id || !$employee_id) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       LOAD OLD SERVICE
    ===================== */
    $stmt = $conn->prepare("
        SELECT 
            a.source,
            aps.appointment_id,
            aps.service_id AS old_service_id,
            aps.variant_id AS old_variant_id
        FROM appointment_services aps
        JOIN appointments a ON a.id = aps.appointment_id
        WHERE aps.id = ?
    ");
    $stmt->bind_param("i", $appointment_service_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        throw new Exception("Service record not found");
    }

    $appointment_id = (int)$row['appointment_id'];

    /* =====================
        DUPLICATE CHECK (EDIT)
    ===================== */
    if ($row['source'] !== 'online') {
        $stmt = $conn->prepare("
        SELECT id
        FROM appointment_services
        WHERE appointment_id = ?
        AND service_id = ?
        AND (
            (variant_id IS NULL AND ? IS NULL)
            OR variant_id = ?
        )
        AND id != ?
    ");
        $stmt->bind_param(
            "iiiii",
            $appointment_id,
            $service_id,
            $variant_id,
            $variant_id,
            $appointment_service_id
        );
        $stmt->execute();

        if ($stmt->get_result()->fetch_assoc()) {
            throw new Exception(
                $variant_id
                    ? "This service variant already exists"
                    : "This service already exists"
            );
        }
    }

    if ($row['source'] === 'online') {

        if ((int)$service_id !== (int)$row['old_service_id']) {
            echo json_encode([
                "success" => false,
                "error" => "Online booking services cannot change service"
            ]);
            exit;
        }

        if ((int)$variant_id !== (int)$row['old_variant_id']) {
            echo json_encode([
                "success" => false,
                "error" => "Online booking services cannot change variant"
            ]);
            exit;
        }

        // staff change allowed ✅
    }

    /* =====================
       UPDATE APPOINTMENT SERVICE
    ===================== */
    $stmt = $conn->prepare("
        UPDATE appointment_services
        SET service_id = ?, employee_id = ?, variant_id = ?, quantity = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "iiiii",
        $service_id,
        $employee_id,
        $variant_id,
        $quantity,
        $appointment_service_id
    );
    $stmt->execute();

    /* =====================
   UPDATE TRANSACTION SERVICE (SAFE)
===================== */
    $stmt = $conn->prepare("
    SELECT id
    FROM spa_transactions
    WHERE appointment_id = ?
    LIMIT 1
");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if ($txn) {
        // price
        $stmt = $conn->prepare("
        SELECT COALESCE(sv.price, s.base_price) AS price
        FROM services s
        LEFT JOIN service_variants sv ON sv.id = ?
        WHERE s.id = ?
    ");
        $stmt->bind_param("ii", $variant_id, $service_id);
        $stmt->execute();
        $price = floatval($stmt->get_result()->fetch_assoc()['price']);
        $total = $price * $quantity;

        // update exact row
        $stmt = $conn->prepare("
        UPDATE spa_transaction_services
        SET
            service_id = ?,
            employee_id = ?,
            quantity = ?,
            unit_price = ?,
            total_price = ?
        WHERE appointment_service_id = ?
        LIMIT 1
    ");
        $stmt->bind_param(
            "iiiddi",
            $service_id,
            $employee_id,
            $quantity,
            $price,
            $total,
            $appointment_service_id
        );
        $stmt->execute();
    }

    // 🔥 recalc totals
    if ($txn) {
        recalcTransaction($conn, $txn['id']);
    }
    $conn->commit();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
