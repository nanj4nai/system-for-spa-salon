<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

/*
|-----------------------------------------------------------------------
| GET → Fetch appointments with multiple services
|-----------------------------------------------------------------------
*/
if ($method === "GET") {

    $conditions = [];
    $params = [];
    $types = "";

    // --- Filters ---
    if (!empty($_GET["id"])) {
        $conditions[] = "a.id = ?";
        $params[] = $_GET["id"];
        $types .= "i";
    }

    if (!empty($_GET["date"])) {
        $conditions[] = "a.appointment_date = ?";
        $params[] = $_GET["date"];
        $types .= "s";
    }

    if (!empty($_GET["customer"])) {
        $conditions[] = "c.full_name LIKE ?";
        $params[] = "%" . $_GET["customer"] . "%";
        $types .= "s";
    }

    if (!empty($_GET["staff"])) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM appointment_services aps
            WHERE aps.appointment_id = a.id
            AND aps.employee_id = ?
        )";
        $params[] = $_GET["staff"];
        $types .= "i";
    }


    if (!empty($_GET["service"])) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM appointment_services aps
            WHERE aps.appointment_id = a.id
              AND aps.service_id = ?
        )";
        $params[] = $_GET["service"];
        $types .= "i";
    }

    $conditions[] = "
        NOT (
            a.source = 'online'
            AND a.payment_rejected_at IS NOT NULL
        )
    ";

    $where = "WHERE " . implode(" AND ", $conditions);

    $sql = "
        SELECT DISTINCT
            a.id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status AS raw_status,
            a.source,              -- 👈 ADD THIS
            a.checked_in_at,
            a.created_at,
            c.full_name AS client_name,

            t.id AS transaction_id,
            t.payment_status,
            t.has_receivable,
            t.balance_due,
            t.total_amount,
            t.status AS transaction_status

        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        LEFT JOIN spa_transactions t ON t.appointment_id = a.id

        $where
        ORDER BY a.appointment_date DESC, a.start_time DESC
    ";


    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $row["payment_status"] = $row["payment_status"] ?? "unpaid";
        $row["has_receivable"] = (int)($row["has_receivable"] ?? 0);
        $row["transaction_status"] = $row["transaction_status"] ?? "editing";

        // --- Derive real status ---
        if (in_array($row["raw_status"], ["cancelled", "no_show"])) {
            $row["status"] = $row["raw_status"];
        }
        // ===========================
        // ONLINE BOOKING
        // ===========================
        elseif ($row["source"] === "online") {

            if (empty($row["transaction_id"])) {
                // no transaction yet
                $row["status"] = "pending";
            } elseif ($row["transaction_status"] === "pending_verification") {
                // 🔥 payment submitted but not approved
                $row["status"] = "pending";
            } else {
                // 🔥 payment approved by admin
                $row["status"] = "confirmed";
            }
        }
        // ===========================
        // WALK-IN / ADMIN BOOKING
        //  ==========================  
        else {

            if ($row["checked_in_at"]) {
                $row["status"] = "checked_in";
            } elseif ($row["payment_status"] === "paid") {
                $row["status"] = "completed";
            } elseif ($row["has_receivable"]) {
                // cashier explicitly marked AR
                $row["status"] = "checked_in";
            } else {
                $row["status"] = $row["raw_status"];
            }
        }

        $row["payment_label"] = match (true) {
            $row["source"] === "online"
                && $row["transaction_status"] === "pending_verification" =>
            "Pending Verification",

            $row["source"] === "online"
                && $row["payment_status"] === "partial" =>
            "Deposit Paid",

            $row["source"] === "online"
                && $row["payment_status"] === "paid" =>
            "Fully Paid",

            $row["has_receivable"] =>
            "Account Receivable",

            default =>
            null
        };


        if (!empty($_GET["status"]) && $row["status"] !== $_GET["status"]) {
            continue;
        }

        $row["usage_mode"] =
            !empty($row["transaction_id"])
            && $row["transaction_status"] !== "pending_verification"
            ? "actual"
            : "planned";

        $appointments[$row["id"]] = $row;
        $appointments[$row["id"]]["services"] = [];
        $appointments[$row["id"]]["services_map"] = [];
        $appointments[$row["id"]]["extra_products"] = [];
    }


    // --- Load services + staff (SAFE IN QUERY) ---
    if ($appointments) {
        $ids = array_keys($appointments);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql2 = "
        SELECT
            aps.id AS appointment_service_id,
            aps.appointment_id,
            aps.service_id,

            s.name AS service_name,
            s.base_price,

            v.name AS variant_name,
            v.price AS variant_price,

            e.full_name AS staff_name
        FROM appointment_services aps
        JOIN services s ON aps.service_id = s.id
        LEFT JOIN service_variants v ON aps.variant_id = v.id
        LEFT JOIN employees e ON aps.employee_id = e.id
        WHERE aps.appointment_id IN ($placeholders)
        ";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param(str_repeat("i", count($ids)), ...$ids);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        while ($row2 = $res2->fetch_assoc()) {
            $aid = $row2["appointment_id"];

            if (!isset($appointments[$aid]["services_map"])) {
                $appointments[$aid]["services_map"] = [];
            }

            $key = $row2["service_name"] . "|" . ($row2["variant_name"] ?? "");

            if (!isset($appointments[$aid]["services_map"][$key])) {
                $appointments[$aid]["services_map"][$key] = [
                    "appointment_service_id" => $row2["appointment_service_id"],
                    "service_id" => $row2["service_id"],
                    "service_name" => $row2["service_name"],
                    "variant_name" => $row2["variant_name"],
                    "staff_name" => $row2["staff_name"] ?? "Unassigned",
                    "price" => $row2["variant_price"] ?? $row2["base_price"],
                    "products" => []
                ];
            }
        }
        // --- Load DEFAULT products for PLANNED services ---
        $plannedServiceIds = [];

        foreach ($appointments as $a) {
            if ($a["usage_mode"] === "planned") {
                foreach ($a["services_map"] as $svc) {
                    $plannedServiceIds[] = $svc["service_id"];
                }
            }
        }

        $plannedServiceIds = array_unique($plannedServiceIds);

        if ($plannedServiceIds) {
            $ph = implode(',', array_fill(0, count($plannedServiceIds), '?'));

            $sqlPlanned = "
                SELECT
                    sp.service_id,
                    p.name,
                    sp.quantity,
                    p.unit
                FROM service_products sp
                JOIN products p ON p.id = sp.product_id
                WHERE sp.service_id IN ($ph)
            ";

            $stmtPlanned = $conn->prepare($sqlPlanned);
            $stmtPlanned->bind_param(str_repeat("i", count($plannedServiceIds)), ...$plannedServiceIds);
            $stmtPlanned->execute();
            $resPlanned = $stmtPlanned->get_result();

            while ($r = $resPlanned->fetch_assoc()) {
                foreach ($appointments as &$a) {
                    if ($a["usage_mode"] !== "planned") continue;

                    foreach ($a["services_map"] as &$svc) {
                        if ($svc["service_id"] == $r["service_id"]) {
                            $svc["products"][] = [
                                "name" => $r["name"],
                                "qty"  => (float)$r["quantity"],
                                "unit" => $r["unit"],
                                "cost" => null // planned → no cost yet
                            ];
                        }
                    }
                }
            }
        }

        $actualServiceIds = [];

        foreach ($appointments as $a) {
            if ($a["usage_mode"] === "actual") {
                foreach ($a["services_map"] ?? [] as $svc) {
                    $actualServiceIds[] = $svc["appointment_service_id"];
                }
            }
        }

        if ($actualServiceIds) {
            $ph = implode(',', array_fill(0, count($actualServiceIds), '?'));

            $sql3 = "
            SELECT
                asp.appointment_service_id,
                p.name,
                p.price,
                p.unit_per_item,
                asp.quantity_used,
                asp.unit
            FROM appointment_service_products asp
            JOIN products p ON p.id = asp.product_id
            WHERE asp.appointment_service_id IN ($ph)
            ";


            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param(str_repeat("i", count($actualServiceIds)), ...$actualServiceIds);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            while ($r = $res3->fetch_assoc()) {

                $cost = 0;
                if ((float)$r["unit_per_item"] > 0) {
                    $cost = ($r["quantity_used"] / $r["unit_per_item"]) * $r["price"];
                }

                foreach ($appointments as &$a) {
                    if ($a["usage_mode"] !== "actual") continue;

                    foreach ($a["services_map"] as &$svc) {
                        if ($svc["appointment_service_id"] == $r["appointment_service_id"]) {
                            $svc["products"][] = [
                                "name" => $r["name"],
                                "qty"  => (float)$r["quantity_used"],
                                "unit" => $r["unit"],
                                "cost" => round($cost, 2)
                            ];
                        }
                    }
                }
            }
        }
        $sqlExtra = "
    SELECT
        aep.appointment_id,
        p.name,
        aep.quantity,
        aep.unit_price,
        aep.total_price
    FROM appointment_extra_products aep
    JOIN products p ON p.id = aep.product_id
    WHERE aep.appointment_id IN ($placeholders)
";
        $stmtExtra = $conn->prepare($sqlExtra);
        $stmtExtra->bind_param(str_repeat("i", count($ids)), ...$ids);
        $stmtExtra->execute();
        $resExtra = $stmtExtra->get_result();

        while ($r = $resExtra->fetch_assoc()) {
            $appointments[$r["appointment_id"]]["extra_products"][] = [
                "name" => $r["name"],
                "qty"  => (float)$r["quantity"],
                "unit_price" => (float)$r["unit_price"],
                "total_price" => (float)$r["total_price"],
            ];
        }
    }
    foreach ($appointments as &$a) {
        $a["services"] = array_values($a["services_map"]);
        unset($a["services_map"]);
    }
    $vatRate = 0.12;
    $vatRow = $conn->query("SELECT vat_rate FROM settings LIMIT 1")->fetch_assoc();
    if ($vatRow) {
        $vatRate = ((float)$vatRow["vat_rate"]) / 100;
    }

    foreach ($appointments as &$a) {

        if (!$a["transaction_id"]) continue;

        // 🔒 LOCK pricing if cashier total exists
        if ($a["total_amount"] !== null) {
            $a["pricing_breakdown"] = [
                "subtotal"    => round($a["total_amount"] / (1 + $vatRate), 2),
                "vat_rate"    => $vatRate * 100,
                "vat_amount"  => round(
                    $a["total_amount"] - ($a["total_amount"] / (1 + $vatRate)),
                    2
                ),
                "grand_total" => (float)$a["total_amount"]
            ];
            continue;
        }

        // fallback (should rarely happen)
        $subtotal = 0;

        foreach ($a["services"] as $s) {
            $subtotal += (float)$s["price"];
        }

        $vat = $subtotal * $vatRate;

        $a["pricing_breakdown"] = [
            "subtotal" => round($subtotal, 2),
            "vat_rate" => $vatRate * 100,
            "vat_amount" => round($vat, 2),
            "grand_total" => round($subtotal + $vat, 2)
        ];
    }


    echo json_encode(array_values($appointments));
    exit;
}

/*
|-----------------------------------------------------------------------
| POST → Update appointment STATUS only
|-----------------------------------------------------------------------
*/
if ($method === "POST") {

    $data = json_decode(file_get_contents("php://input"), true);
    $allowed = ['pending', 'confirmed', 'cancelled', 'no_show'];

    if (
        empty($data["id"]) ||
        empty($data["status"]) ||
        !in_array($data["status"], $allowed)
    ) {
        http_response_code(422);
        echo json_encode(["success" => false]);
        exit;
    }
    // Check if appointment already has a transaction
    $check = $conn->prepare("
    SELECT COUNT(*) 
    FROM spa_transactions 
    WHERE appointment_id = ?
");
    $check->bind_param("i", $data["id"]);
    $check->execute();
    $check->bind_result($hasTransaction);
    $check->fetch();
    $check->close();

    if ($hasTransaction > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Cannot manually change status after transaction exists"
        ]);
        exit;
    }


    $stmt = $conn->prepare("
        UPDATE appointments
        SET status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $data["status"], $data["id"]);
    $stmt->execute();

    echo json_encode(["success" => true]);
    exit;
}

/*
|-----------------------------------------------------------------------
| PUT → Create OR update appointment with multiple services
|-----------------------------------------------------------------------
*/
if ($method === "PUT") {

    $data = json_decode(file_get_contents("php://input"), true);

    if (
        (empty($data["client_id"]) && empty($data["new_client_data"])) ||
        empty($data["services"]) ||
        empty($data["appointment_date"]) ||
        empty($data["start_time"]) ||
        empty($data["end_time"])
    ) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit;
    }

    // --- Handle overnight appointment ---
    $startTs = strtotime($data["start_time"]);
    $endTs   = strtotime($data["end_time"]);
    $overnight = false;

    if ($endTs <= $startTs) {
        $overnight = true;
        $endTs += 86400; // +1 day
    }

    // --- New client ---
    if (!empty($data["new_client_data"])) {
        $nc = $data["new_client_data"];
        $stmt = $conn->prepare("
            INSERT INTO clients (full_name, contact_number, email, address, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssss",
            $nc["full_name"],
            $nc["contact_number"],
            $nc["email"],
            $nc["address"],
            $nc["notes"]
        );
        $stmt->execute();
        $data["client_id"] = $stmt->insert_id;
    }

    // --- Staff conflict check (overnight-safe) ---
    foreach ($data["services"] as $s) {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM appointment_services aps
            JOIN appointments a ON a.id = aps.appointment_id
            WHERE aps.employee_id = ?
            AND a.appointment_date = ?
            AND (
                    (? < a.end_time AND ? > a.start_time)
                OR (? <= a.start_time AND a.end_time < a.start_time)
            )
        ");
        $stmt->bind_param(
            "issss",
            $s["staff_id"],
            $data["appointment_date"],
            $data["start_time"],
            $data["end_time"],
            $data["start_time"]
        );
        $stmt->execute();
        $stmt->bind_result($conflict);
        $stmt->fetch();
        $stmt->close();

        if ($conflict > 0) {
            echo json_encode([
                "success" => false,
                "message" => "Staff already booked for this time"
            ]);
            exit;
        }
    }

    // --- Create appointment ---
    if (empty($data["id"])) {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO appointments
            (client_id, appointment_date, start_time, end_time, status, source, notes)
            VALUES (?, ?, ?, ?, 'confirmed', 'admin', ?)
        ");
        $stmt->bind_param(
            "issss",
            $data["client_id"],
            $data["appointment_date"],
            $data["start_time"],
            $data["end_time"],
            $data["notes"]
        );
        $stmt->execute();
        $appointmentId = $stmt->insert_id;

        $stmt = $conn->prepare("
            INSERT INTO appointment_services (appointment_id, service_id, variant_id, employee_id)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($data["services"] as $s) {
            $stmt->bind_param(
                "iiii",
                $appointmentId,
                $s["service_id"],
                $s["variant_id"],
                $s["staff_id"]
            );
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(["success" => true, "id" => $appointmentId]);
        exit;
    }

    echo json_encode(["success" => true]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
