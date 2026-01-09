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

    if (!empty($_GET["status"])) {
        $conditions[] = "a.status = ?";
        $params[] = $_GET["status"];
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

    $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

    $sql = "
        SELECT DISTINCT
            a.id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.created_at,
            a.notes,
            c.full_name AS client_name
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
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
        $appointments[$row["id"]] = $row;
        $appointments[$row["id"]]["services"] = [];
    }

    // --- Load services + staff (SAFE IN QUERY) ---
    if ($appointments) {
        $ids = array_keys($appointments);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql2 = "
           SELECT
                aps.appointment_id,

                s.name AS service_name,
                s.base_price,

                v.name AS variant_name,
                v.price AS variant_price,
                v.duration_minutes,

                e.full_name AS staff_name,

                p.name AS product_name,
                sp.quantity AS product_qty
            FROM appointment_services aps
            JOIN services s ON aps.service_id = s.id
            LEFT JOIN service_variants v ON aps.variant_id = v.id
            JOIN employees e ON aps.employee_id = e.id
            LEFT JOIN service_products sp ON sp.service_id = s.id
            LEFT JOIN products p ON p.id = sp.product_id
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
                    "service_name"  => $row2["service_name"],
                    "variant_name"  => $row2["variant_name"],
                    "price"         => $row2["variant_price"] ?? $row2["base_price"],
                    "duration"      => $row2["duration_minutes"],
                    "staff_name"    => $row2["staff_name"],
                    "products"      => []
                ];
            }

            if ($row2["product_name"]) {
                $appointments[$aid]["services_map"][$key]["products"][] = [
                    "name" => $row2["product_name"],
                    "qty"  => $row2["product_qty"]
                ];
            }
        }
    }

    foreach ($appointments as &$a) {
        if (isset($a["services_map"])) {
            $a["services"] = array_values($a["services_map"]);
            unset($a["services_map"]);
        }
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
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

    if (
        empty($data["id"]) ||
        empty($data["status"]) ||
        !in_array($data["status"], $allowed)
    ) {
        http_response_code(422);
        echo json_encode(["success" => false]);
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
