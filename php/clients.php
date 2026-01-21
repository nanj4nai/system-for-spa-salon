<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    /* =======================
   LIST CLIENTS
======================= */
    if ($action === 'list') {
        $res = $conn->query("
        SELECT 
            id,
            full_name,
            contact_number,
            email,
            address,
            notes,
            created_at,
            DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS created_at_formatted
        FROM clients
        ORDER BY created_at DESC
    ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    /* =======================
   CLIENT VISIT HISTORY
======================= */
    if ($action === 'visits') {
        $clientId = intval($_GET['id'] ?? 0);

        if ($clientId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $conn->prepare("
        SELECT 
            a.id AS appointment_id,
            t.id AS transaction_id,
            DATE_FORMAT(a.appointment_date, '%M %d, %Y') AS appointment_date,
            a.status,
            COALESCE(SUM(
                CASE 
                    WHEN sv.price IS NOT NULL THEN sv.price
                    ELSE s.base_price
                END
            ), 0) AS total_amount,
            COALESCE(
                GROUP_CONCAT(DISTINCT s.name SEPARATOR ', '),
                '—'
            ) AS services
        FROM appointments a
        LEFT JOIN spa_transactions t 
            ON t.appointment_id = a.id   -- 👈 THIS IS KEY
        LEFT JOIN appointment_services aps 
            ON aps.appointment_id = a.id
        LEFT JOIN services s 
            ON s.id = aps.service_id
        LEFT JOIN service_variants sv 
            ON sv.id = aps.variant_id
        WHERE a.client_id = ?
        AND a.status IN ('checked_in', 'completed')
        GROUP BY a.id
        ORDER BY a.appointment_date DESC
    ");

        $stmt->bind_param("i", $clientId);
        $stmt->execute();

        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    /* =======================
   CLIENT TRANSACTIONS
======================= */
    if ($action === 'transactions') {
        $clientId = intval($_GET['id'] ?? 0);

        if ($clientId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $conn->prepare("
        SELECT
            t.id,
            t.transaction_number,
            t.created_at,
            t.total_amount,
            COALESCE(SUM(p.amount), 0) AS total_paid,
            (t.total_amount - COALESCE(SUM(p.amount), 0)) AS balance_due,
            t.payment_status,
            t.has_receivable
        FROM spa_transactions t
        LEFT JOIN payments p ON p.transaction_id = t.id
        WHERE t.client_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();

        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    /* =======================
   CLIENT A/R SUMMARY
======================= */
    if ($action === 'ar_summary') {
        $clientId = intval($_GET['id'] ?? 0);

        $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS open_count,
            COALESCE(SUM(balance), 0) AS total_balance
        FROM accounts_receivable
        WHERE client_id = ?
          AND status = 'open'
    ");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();

        echo json_encode($stmt->get_result()->fetch_assoc());
        exit;
    }
    if ($action === 'transaction_details') {

        $txId = intval($_GET['id'] ?? 0);

        $stmt = $conn->prepare("
        SELECT
            id,
            transaction_number,
            total_amount,
            payment_status,
            balance_due
        FROM spa_transactions
        WHERE id = ?
    ");
        $stmt->bind_param("i", $txId);
        $stmt->execute();
        $tx = $stmt->get_result()->fetch_assoc();

        if (!$tx) {
            echo json_encode(["success" => false]);
            exit;
        }

        // SERVICES
        $services = $conn->query("
        SELECT s.name, ts.quantity, ts.total_price
        FROM spa_transaction_services ts
        JOIN services s ON s.id = ts.service_id
        WHERE ts.transaction_id = $txId
    ")->fetch_all(MYSQLI_ASSOC);

        // PRODUCTS
        $products = $conn->query("
        SELECT p.name, ps.quantity, ps.total_price
        FROM product_sales ps
        JOIN products p ON p.id = ps.product_id
        WHERE ps.transaction_id = $txId
    ")->fetch_all(MYSQLI_ASSOC);

        // PAYMENTS
        $payments = $conn->query("
        SELECT payment_method, amount, payment_date
        FROM payments
        WHERE transaction_id = $txId
    ")->fetch_all(MYSQLI_ASSOC);

        // RECEIVABLE
        $ar = $conn->query("
        SELECT * FROM accounts_receivable
        WHERE transaction_id = $txId
        LIMIT 1
    ")->fetch_assoc();

        $arPayments = [];
        if ($ar) {
            $arPayments = $conn->query("
            SELECT amount, payment_date, remarks
            FROM ar_payments
            WHERE receivable_id = {$ar['id']}
        ")->fetch_all(MYSQLI_ASSOC);
        }

        echo json_encode([
            "transaction" => $tx,
            "services" => $services,
            "products" => $products,
            "payments" => $payments,
            "receivable" => $ar,
            "ar_payments" => $arPayments
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === '') {

    $id      = intval($_POST['id'] ?? 0);
    $name    = trim($_POST['full_name'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    if ($name === '') {
        echo json_encode(['success' => false, 'error' => 'Client name is required']);
        exit;
    }

    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE clients
            SET full_name = ?, contact_number = ?, email = ?, address = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $name, $contact, $email, $address, $notes, $id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO clients (full_name, contact_number, email, address, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $name, $contact, $email, $address, $notes);
    }

    echo json_encode(['success' => $stmt->execute()]);
    exit;
}
