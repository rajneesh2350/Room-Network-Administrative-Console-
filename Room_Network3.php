<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['loggedin'])) {
    header("Location: https://igipess.du.ac.in/login.php");
    exit;
}

$admin = "Yes";

include("./log/config/conn.php");
date_default_timezone_set('Asia/Kolkata');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function ensure_dir($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function set_flash($icon, $title, $text)
{
    $_SESSION['room_network_flash'] = [
        'icon' => $icon,
        'title' => $title,
        'text' => $text
    ];
}

function redirect_tab($tab)
{
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=" . urlencode($tab));
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db_has_column(mysqli $conn, $table, $column)
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_table_column(mysqli $conn, $table, $column, $definition)
{
    if (!db_has_column($conn, $table, $column)) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function upload_one($inputName, $targetDir, array $allowedExts, $oldFile = '')
{
    ensure_dir($targetDir);
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return $oldFile;
    }

    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return $oldFile;
    }

    $safe = time() . '_' . mt_rand(1000, 9999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES[$inputName]['name']));
    $dest = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $safe;
    if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $dest)) {
        if (!empty($oldFile)) {
            $oldPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . basename($oldFile);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
        return $safe;
    }
    return $oldFile;
}

function upload_many($inputName, $targetDir, array $allowedExts)
{
    ensure_dir($targetDir);
    $files = [];
    if (!isset($_FILES[$inputName]['name']) || !is_array($_FILES[$inputName]['name'])) {
        return $files;
    }

    foreach ($_FILES[$inputName]['name'] as $i => $name) {
        if (($_FILES[$inputName]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            continue;
        }
        $safe = time() . '_' . $i . '_' . mt_rand(1000, 9999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($name));
        $dest = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $safe;
        if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $dest)) {
            $files[] = $safe;
        }
    }
    return $files;
}

function delete_file_if_exists($dir, $file)
{
    if (empty($file)) {
        return;
    }
    $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . basename($file);
    if (is_file($path)) {
        @unlink($path);
    }
}

function csv_header_key($value)
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

function ensure_admin_schema(mysqli $conn)
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS room_facility (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(150) NOT NULL,
            icon VARCHAR(100) DEFAULT 'fa-building',
            overview TEXT NULL,
            status VARCHAR(100) DEFAULT 'Active',
            contact_person VARCHAR(255) NULL,
            contact_phone VARCHAR(100) NULL,
            location_note VARCHAR(255) NULL,
            capacity_label VARCHAR(100) NULL,
            cover_image VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS room_facility_room_link (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            facility_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_room_facility (room_id, facility_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS room_facility_record (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facility_id INT NOT NULL,
            record_year VARCHAR(20) NOT NULL,
            record_title VARCHAR(255) NOT NULL,
            item_summary TEXT NULL,
            equipment_count VARCHAR(100) NULL,
            status VARCHAR(100) DEFAULT 'Active',
            register_pdf VARCHAR(255) NULL,
            photos_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS exam_seat_booking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            exam_date DATE NOT NULL,
            exam_time VARCHAR(20) NULL,
            exam_ampm VARCHAR(10) NULL,
            exam_shift VARCHAR(20) NULL,
            project_name VARCHAR(255) NOT NULL,
            seat_label VARCHAR(100) NOT NULL,
            roll_no VARCHAR(150) NULL,
            candidate_name VARCHAR(255) NULL,
            booking_status VARCHAR(50) DEFAULT 'Reserved',
            session_remarks TEXT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_room_exam_seat (room_id, exam_date, seat_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS room_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            doc_year VARCHAR(20) NOT NULL,
            doc_title VARCHAR(255) NOT NULL,
            doc_description TEXT NULL,
            doc_file VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_doc_year (doc_year),
            UNIQUE KEY uniq_room_year (room_id, doc_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($queries as $query) {
        $conn->query($query);
    }

    ensure_table_column($conn, 'room_facility', 'icon', "VARCHAR(100) DEFAULT 'fa-building'");
    ensure_table_column($conn, 'room_facility', 'overview', "TEXT NULL");
    ensure_table_column($conn, 'room_facility', 'status', "VARCHAR(100) DEFAULT 'Active'");
    ensure_table_column($conn, 'room_facility', 'contact_person', "VARCHAR(255) NULL");
    ensure_table_column($conn, 'room_facility', 'contact_phone', "VARCHAR(100) NULL");
    ensure_table_column($conn, 'room_facility', 'location_note', "VARCHAR(255) NULL");
    ensure_table_column($conn, 'room_facility', 'capacity_label', "VARCHAR(100) NULL");
    ensure_table_column($conn, 'room_facility', 'cover_image', "VARCHAR(255) NULL");

    ensure_table_column($conn, 'room_facility_record', 'item_summary', "TEXT NULL");
    ensure_table_column($conn, 'room_facility_record', 'equipment_count', "VARCHAR(100) NULL");
    ensure_table_column($conn, 'room_facility_record', 'status', "VARCHAR(100) DEFAULT 'Active'");
    ensure_table_column($conn, 'room_facility_record', 'register_pdf', "VARCHAR(255) NULL");
    ensure_table_column($conn, 'room_facility_record', 'photos_json', "LONGTEXT NULL");

    ensure_table_column($conn, 'exam_seat_booking', 'exam_time', "VARCHAR(20) NULL");
    ensure_table_column($conn, 'exam_seat_booking', 'exam_ampm', "VARCHAR(10) NULL");
    ensure_table_column($conn, 'exam_seat_booking', 'exam_shift', "VARCHAR(20) NULL");
    if (!db_has_column($conn, 'exam_seat_booking', 'booking_status')) {
        $conn->query("ALTER TABLE `exam_seat_booking` ADD COLUMN `booking_status` VARCHAR(50) DEFAULT 'Reserved'");
        if (db_has_column($conn, 'exam_seat_booking', 'status')) {
            $conn->query("UPDATE `exam_seat_booking` SET `booking_status` = `status` WHERE (`booking_status` IS NULL OR `booking_status` = '')");
        }
    }
    ensure_table_column($conn, 'exam_seat_booking', 'candidate_name', "VARCHAR(255) NULL");
    ensure_table_column($conn, 'exam_seat_booking', 'session_remarks', "TEXT NULL");
    ensure_table_column($conn, 'exam_seat_booking', 'notes', "TEXT NULL");
}

function room_seat_labels($seatingPlanJson)
{
    $labels = [];
    if (empty($seatingPlanJson)) {
        return $labels;
    }

    $plan = json_decode($seatingPlanJson, true);
    if (!is_array($plan)) {
        return $labels;
    }

    if (($plan['type'] ?? '') === 'grid') {
        $rows = (int)($plan['r'] ?? 0);
        $cols = (int)($plan['c'] ?? 0);
        $prefix = trim((string)($plan['p'] ?? 'S'));
        $blocked = is_array($plan['b'] ?? null) ? $plan['b'] : [];

        for ($r = 1; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                $seatId = $r . '-' . $c;
                if (in_array($seatId, $blocked, true)) {
                    continue;
                }
                $labels[] = $prefix . ((($r - 1) * $cols) + $c);
            }
        }
    } elseif (($plan['type'] ?? '') === 'open') {
        $total = (int)($plan['total'] ?? 0);
        for ($i = 1; $i <= $total; $i++) {
            $labels[] = 'S' . $i;
        }
    }

    return $labels;
}

ensure_dir(__DIR__ . '/images');
ensure_dir(__DIR__ . '/uploads/facilities/covers');
ensure_dir(__DIR__ . '/uploads/facilities/photos');
ensure_dir(__DIR__ . '/uploads/facilities/registers');
ensure_dir(__DIR__ . '/uploads/room_documents');
ensure_admin_schema($conn);

$hasSeatingPlanColumn = db_has_column($conn, 'igpess_network', 'seating_plan');

$facilityCategories = [
    'Laboratories',
    'Library',
    'Indoor Sports Facility',
    'Outdoor Sports Facility',
    'Gymnasium & Fitness Centre',
    'Multiutility Gym',
    'Multipurpose Hall',
    'Assembly Hall',
    'IT-Infrastructure'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin === "Yes") {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_room') {
        $id = (int)($_POST['id'] ?? 0);
        $floor = trim($_POST['floor'] ?? '');
        $roomNo = trim($_POST['room_no'] ?? '');
        $desc = trim($_POST['desc'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-door-open');
        $width = trim($_POST['width'] ?? '');
        $length = trim($_POST['length'] ?? '');
        $lat = trim($_POST['latitude'] ?? '');
        $lng = trim($_POST['longitude'] ?? '');
        $networking = trim($_POST['networking'] ?? '');
        $interactiveBoard = trim($_POST['interactive_board'] ?? '');
        $wifi = trim($_POST['wifi_router'] ?? '');
        $cctv = trim($_POST['cctv'] ?? '');
        $ups = trim($_POST['ups'] ?? '');
        $audioVideo = trim($_POST['audio_video'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $members = isset($_POST['room_members']) && is_array($_POST['room_members']) ? array_map('intval', $_POST['room_members']) : [];
        $inChargeId = !empty($_POST['in_charge_id']) ? (int)$_POST['in_charge_id'] : null;
        $facilityIds = isset($_POST['facility_ids']) && is_array($_POST['facility_ids']) ? array_map('intval', $_POST['facility_ids']) : [];
        $oldImage = trim($_POST['old_image'] ?? '');
        $roomImage = upload_one('room_image', __DIR__ . '/images', ['jpg', 'jpeg', 'png', 'gif'], $oldImage);
        $roomMembersJson = !empty($members) ? json_encode(array_values($members)) : null;
        $seatingPlan = $hasSeatingPlanColumn ? trim($_POST['seating_plan'] ?? '') : null;

        $oldDocFile = trim($_POST['old_doc_file'] ?? '');
        $docYear = trim($_POST['doc_year'] ?? '');
        $docTitle = trim($_POST['doc_title'] ?? '');
        $docDescription = trim($_POST['doc_description'] ?? '');
        $docFile = upload_one('room_document', __DIR__ . '/uploads/room_documents', ['pdf'], $oldDocFile);

        if ($id > 0) {
            $sql = "UPDATE igpess_network SET
                floor=?,
                room_no=?,
                description=?,
                icon=?,
                networking=?,
                interactive_board=?,
                wifi_router=?,
                cctv=?,
                ups=?,
                audio_video=?,
                remarks=?,
                room_image=?,
                width=?,
                length=?,
                latitude=?,
                longitude=?,
                room_members=?,
                in_charge_id=?";
            if ($hasSeatingPlanColumn) {
                $sql .= ", seating_plan=?";
            }
            $sql .= " WHERE id=?";

            $stmt = $conn->prepare($sql);
            if ($hasSeatingPlanColumn) {
                $stmt->bind_param(
                    'sssssssssssssssssisi',
                    $floor,
                    $roomNo,
                    $desc,
                    $icon,
                    $networking,
                    $interactiveBoard,
                    $wifi,
                    $cctv,
                    $ups,
                    $audioVideo,
                    $remarks,
                    $roomImage,
                    $width,
                    $length,
                    $lat,
                    $lng,
                    $roomMembersJson,
                    $inChargeId,
                    $seatingPlan,
                    $id
                );
            } else {
                $stmt->bind_param(
                    'sssssssssssssssssi',
                    $floor,
                    $roomNo,
                    $desc,
                    $icon,
                    $networking,
                    $interactiveBoard,
                    $wifi,
                    $cctv,
                    $ups,
                    $audioVideo,
                    $remarks,
                    $roomImage,
                    $width,
                    $length,
                    $lat,
                    $lng,
                    $roomMembersJson,
                    $inChargeId,
                    $id
                );
            }
            $stmt->execute();
            $roomId = $id;

            if ($docFile && $docYear && $docTitle) {
                $conn->query("DELETE FROM room_documents WHERE room_id = " . $roomId . " AND doc_year = '" . $conn->real_escape_string($docYear) . "'");
                $stmtDoc = $conn->prepare("INSERT INTO room_documents (room_id, doc_year, doc_title, doc_description, doc_file) VALUES (?, ?, ?, ?, ?)");
                $stmtDoc->bind_param('issss', $roomId, $docYear, $docTitle, $docDescription, $docFile);
                $stmtDoc->execute();
            }
        } else {
            if ($hasSeatingPlanColumn) {
                $stmt = $conn->prepare("INSERT INTO igpess_network (floor, room_no, description, icon, networking, interactive_board, wifi_router, cctv, ups, audio_video, remarks, room_image, width, length, latitude, longitude, room_members, in_charge_id, seating_plan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    'sssssssssssssssssis',
                    $floor,
                    $roomNo,
                    $desc,
                    $icon,
                    $networking,
                    $interactiveBoard,
                    $wifi,
                    $cctv,
                    $ups,
                    $audioVideo,
                    $remarks,
                    $roomImage,
                    $width,
                    $length,
                    $lat,
                    $lng,
                    $roomMembersJson,
                    $inChargeId,
                    $seatingPlan
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO igpess_network (floor, room_no, description, icon, networking, interactive_board, wifi_router, cctv, ups, audio_video, remarks, room_image, width, length, latitude, longitude, room_members, in_charge_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    'sssssssssssssssssi',
                    $floor,
                    $roomNo,
                    $desc,
                    $icon,
                    $networking,
                    $interactiveBoard,
                    $wifi,
                    $cctv,
                    $ups,
                    $audioVideo,
                    $remarks,
                    $roomImage,
                    $width,
                    $length,
                    $lat,
                    $lng,
                    $roomMembersJson,
                    $inChargeId
                );
            }
            $stmt->execute();
            $roomId = $stmt->insert_id;

            if ($docFile && $docYear && $docTitle && $roomId > 0) {
                $stmtDoc = $conn->prepare("INSERT INTO room_documents (room_id, doc_year, doc_title, doc_description, doc_file) VALUES (?, ?, ?, ?, ?)");
                $stmtDoc->bind_param('issss', $roomId, $docYear, $docTitle, $docDescription, $docFile);
                $stmtDoc->execute();
            }
        }

        foreach ($facilityIds as $facilityId) {
            if ($facilityId > 0) {
                $conn->query("INSERT IGNORE INTO room_facility_room_link (room_id, facility_id) VALUES (" . (int)$roomId . ", " . (int)$facilityId . ")");
            }
        }

        set_flash('success', 'Room saved', 'Room details, personnel, hardware, linked facilities and documents were updated.');
        redirect_tab('manage');
    }

    if ($action === 'delete_room') {
        $id = (int)($_POST['id'] ?? 0);
        $imgRes = $conn->query("SELECT room_image FROM igpess_network WHERE id=" . $id);
        $imgRow = $imgRes ? $imgRes->fetch_assoc() : null;

        $docRes = $conn->query("SELECT doc_file FROM room_documents WHERE room_id=" . $id);
        if ($docRes) {
            while ($doc = $docRes->fetch_assoc()) {
                delete_file_if_exists(__DIR__ . '/uploads/room_documents', $doc['doc_file']);
            }
        }
        $conn->query("DELETE FROM room_documents WHERE room_id=" . $id);
        $conn->query("DELETE FROM room_facility_room_link WHERE room_id=" . $id);
        $conn->query("DELETE FROM exam_seat_booking WHERE room_id=" . $id);
        $conn->query("DELETE FROM igpess_network WHERE id=" . $id);
        if (!empty($imgRow['room_image'])) {
            delete_file_if_exists(__DIR__ . '/images', $imgRow['room_image']);
        }
        set_flash('success', 'Room deleted', 'The room, its seat bookings, and documents were removed.');
        redirect_tab('manage');
    }

    if ($action === 'save_facility') {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $name = trim($_POST['facility_name'] ?? '');
        $category = trim($_POST['facility_category'] ?? '');
        $icon = trim($_POST['facility_icon'] ?? 'fa-building');
        $overview = trim($_POST['facility_overview'] ?? '');
        $status = trim($_POST['facility_status'] ?? 'Active');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $locationNote = trim($_POST['location_note'] ?? '');
        $capacityLabel = trim($_POST['capacity_label'] ?? '');
        $oldCover = trim($_POST['old_cover_image'] ?? '');
        $coverImage = upload_one('cover_image', __DIR__ . '/uploads/facilities/covers', ['jpg', 'jpeg', 'png', 'gif'], $oldCover);

        if ($facilityId > 0) {
            $stmt = $conn->prepare("UPDATE room_facility SET name=?, category=?, icon=?, overview=?, status=?, contact_person=?, contact_phone=?, location_note=?, capacity_label=?, cover_image=? WHERE id=?");
            $stmt->bind_param('ssssssssssi', $name, $category, $icon, $overview, $status, $contactPerson, $contactPhone, $locationNote, $capacityLabel, $coverImage, $facilityId);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO room_facility (name, category, icon, overview, status, contact_person, contact_phone, location_note, capacity_label, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssssss', $name, $category, $icon, $overview, $status, $contactPerson, $contactPhone, $locationNote, $capacityLabel, $coverImage);
            $stmt->execute();
        }

        set_flash('success', 'Facility saved', 'Facility profile has been created or updated.');
        redirect_tab('facilities');
    }

    if ($action === 'delete_facility') {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $rowRes = $conn->query("SELECT cover_image FROM room_facility WHERE id=" . $facilityId);
        $row = $rowRes ? $rowRes->fetch_assoc() : null;
        $recordRes = $conn->query("SELECT register_pdf, photos_json FROM room_facility_record WHERE facility_id=" . $facilityId);
        if ($recordRes) {
            while ($record = $recordRes->fetch_assoc()) {
                delete_file_if_exists(__DIR__ . '/uploads/facilities/registers', $record['register_pdf'] ?? '');
                $photos = json_decode($record['photos_json'] ?? '[]', true);
                if (is_array($photos)) {
                    foreach ($photos as $photo) {
                        delete_file_if_exists(__DIR__ . '/uploads/facilities/photos', $photo);
                    }
                }
            }
        }
        $conn->query("DELETE FROM room_facility_room_link WHERE facility_id=" . $facilityId);
        $conn->query("DELETE FROM room_facility_record WHERE facility_id=" . $facilityId);
        $conn->query("DELETE FROM room_facility WHERE id=" . $facilityId);
        if (!empty($row['cover_image'])) {
            delete_file_if_exists(__DIR__ . '/uploads/facilities/covers', $row['cover_image']);
        }
        set_flash('success', 'Facility deleted', 'Facility profile was deleted and all room links and year records were cleaned safely.');
        redirect_tab('facilities');
    }

    if ($action === 'save_facility_record') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $facilityId = (int)($_POST['record_facility_id'] ?? 0);
        $recordYear = trim($_POST['record_year'] ?? '');
        $recordTitle = trim($_POST['record_title'] ?? '');
        $itemSummary = trim($_POST['item_summary'] ?? '');
        $equipmentCount = trim($_POST['equipment_count'] ?? '');
        $status = trim($_POST['record_status'] ?? 'Active');
        $oldPdf = trim($_POST['old_register_pdf'] ?? '');
        $existingPhotos = json_decode($_POST['existing_photos_json'] ?? '[]', true);
        $existingPhotos = is_array($existingPhotos) ? $existingPhotos : [];
        $registerPdf = upload_one('register_pdf', __DIR__ . '/uploads/facilities/registers', ['pdf'], $oldPdf);
        $newPhotos = upload_many('record_photos', __DIR__ . '/uploads/facilities/photos', ['jpg', 'jpeg', 'png', 'gif']);
        $photosJson = json_encode(array_values(array_merge($existingPhotos, $newPhotos)));

        if ($recordId > 0) {
            $stmt = $conn->prepare("UPDATE room_facility_record SET record_year=?, record_title=?, item_summary=?, equipment_count=?, status=?, register_pdf=?, photos_json=? WHERE id=?");
            $stmt->bind_param('sssssssi', $recordYear, $recordTitle, $itemSummary, $equipmentCount, $status, $registerPdf, $photosJson, $recordId);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO room_facility_record (facility_id, record_year, record_title, item_summary, equipment_count, status, register_pdf, photos_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssssss', $facilityId, $recordYear, $recordTitle, $itemSummary, $equipmentCount, $status, $registerPdf, $photosJson);
            $stmt->execute();
        }

        set_flash('success', 'Facility record saved', 'Year-wise inventory/register record was updated.');
        redirect_tab('facilities');
    }

    if ($action === 'delete_facility_record') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $res = $conn->query("SELECT register_pdf, photos_json FROM room_facility_record WHERE id=" . $recordId);
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            delete_file_if_exists(__DIR__ . '/uploads/facilities/registers', $row['register_pdf'] ?? '');
            $photos = json_decode($row['photos_json'] ?? '[]', true);
            if (is_array($photos)) {
                foreach ($photos as $photo) {
                    delete_file_if_exists(__DIR__ . '/uploads/facilities/photos', $photo);
                }
            }
        }
        $conn->query("DELETE FROM room_facility_record WHERE id=" . $recordId);
        set_flash('success', 'Record deleted', 'Selected year-wise facility record was removed.');
        redirect_tab('facilities');
    }

    if ($action === 'save_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $roomId = (int)($_POST['booking_room_id'] ?? 0);
        $examDate = trim($_POST['exam_date'] ?? '');
        $examTime = trim($_POST['exam_time'] ?? '');
        $examAmpm = trim($_POST['exam_ampm'] ?? '');
        $examShift = trim($_POST['exam_shift'] ?? '');
        $projectName = trim($_POST['project_name'] ?? '');
        $seatLabel = trim($_POST['seat_label'] ?? '');
        $rollNo = trim($_POST['roll_no'] ?? '');
        $candidateName = trim($_POST['candidate_name'] ?? '');
        $bookingStatus = trim($_POST['booking_status'] ?? 'Reserved');
        $sessionRemarks = trim($_POST['session_remarks'] ?? '');
        $notes = trim($_POST['booking_notes'] ?? '');

        if ($bookingId > 0) {
            $stmt = $conn->prepare("UPDATE exam_seat_booking SET room_id=?, exam_date=?, exam_time=?, exam_ampm=?, exam_shift=?, project_name=?, seat_label=?, roll_no=?, candidate_name=?, booking_status=?, session_remarks=?, notes=? WHERE id=?");
            if (!$stmt) {
                throw new Exception("Exam seat booking update query failed: " . $conn->error);
            }
            $stmt->bind_param('isssssssssssi', $roomId, $examDate, $examTime, $examAmpm, $examShift, $projectName, $seatLabel, $rollNo, $candidateName, $bookingStatus, $sessionRemarks, $notes, $bookingId);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO exam_seat_booking (room_id, exam_date, exam_time, exam_ampm, exam_shift, project_name, seat_label, roll_no, candidate_name, booking_status, session_remarks, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Exam seat booking insert query failed: " . $conn->error);
            }
            $stmt->bind_param('isssssssssss', $roomId, $examDate, $examTime, $examAmpm, $examShift, $projectName, $seatLabel, $rollNo, $candidateName, $bookingStatus, $sessionRemarks, $notes);
            $stmt->execute();
        }

        set_flash('success', 'Seat booking saved', 'Seat allocation for examination purpose has been updated.');
        redirect_tab('booking');
    }

    if ($action === 'import_booking_csv') {
        $roomId = (int)($_POST['import_room_id'] ?? 0);
        $examDate = trim($_POST['import_exam_date'] ?? '');
        $examTime = trim($_POST['import_exam_time'] ?? '');
        $examAmpm = trim($_POST['import_exam_ampm'] ?? '');
        $examShift = trim($_POST['import_exam_shift'] ?? '');
        $projectName = trim($_POST['import_project_name'] ?? '');
        $bookingStatus = trim($_POST['import_booking_status'] ?? 'Reserved');
        $sessionRemarks = trim($_POST['import_session_remarks'] ?? '');

        if ($roomId <= 0 || $examDate === '') {
            throw new Exception('Room and exam date are required for CSV import.');
        }
        if (!isset($_FILES['booking_csv']) || $_FILES['booking_csv']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please choose a valid CSV file for import.');
        }

        $handle = fopen($_FILES['booking_csv']['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Unable to read the uploaded CSV file.');
        }

        $firstRow = fgetcsv($handle);
        if ($firstRow === false) {
            fclose($handle);
            throw new Exception('CSV file is empty.');
        }

        $normalizedHeaders = array_map('csv_header_key', $firstRow);
        $hasHeader = count(array_intersect($normalizedHeaders, ['seat_label', 'seat', 'roll_no', 'roll', 'candidate_name', 'candidate', 'notes', 'note', 'project_paper', 'project_name', 'paper_name', 'paper'])) >= 2;

        $rows = [];
        if ($hasHeader) {
            while (($data = fgetcsv($handle)) !== false) {
                if (count(array_filter($data, static function ($v) { return trim((string)$v) !== ''; })) === 0) {
                    continue;
                }
                $assoc = [];
                foreach ($normalizedHeaders as $idx => $header) {
                    $assoc[$header] = trim((string)($data[$idx] ?? ''));
                }
                $rows[] = $assoc;
            }
        } else {
            $rows[] = $firstRow;
            while (($data = fgetcsv($handle)) !== false) {
                if (count(array_filter($data, static function ($v) { return trim((string)$v) !== ''; })) === 0) {
                    continue;
                }
                $rows[] = $data;
            }
        }
        fclose($handle);

        $imported = 0;
        foreach ($rows as $row) {
            if ($hasHeader) {
                $seatLabel = trim((string)($row['seat_label'] ?? $row['seat'] ?? ''));
                $rowProjectName = trim((string)($row['project_paper'] ?? $row['project_name'] ?? $row['paper_name'] ?? $row['paper'] ?? $row['project'] ?? ''));
                $rollNo = trim((string)($row['roll_no'] ?? $row['roll'] ?? ''));
                $candidateName = trim((string)($row['candidate_name'] ?? $row['candidate'] ?? $row['name'] ?? ''));
                $notes = trim((string)($row['notes'] ?? $row['note'] ?? ''));
            } else {
                $seatLabel = trim((string)($row[0] ?? ''));
                $rowProjectName = trim((string)($row[4] ?? $row[5] ?? ''));
                $rollNo = trim((string)($row[1] ?? ''));
                $candidateName = trim((string)($row[2] ?? ''));
                $notes = trim((string)($row[3] ?? ''));
            }

            if ($seatLabel === '') {
                continue;
            }

            $finalProjectName = $rowProjectName !== '' ? $rowProjectName : $projectName;
            if ($finalProjectName === '') {
                continue;
            }

            $seatLabel = preg_replace('/\s+/', '', $seatLabel);

            $checkStmt = $conn->prepare("SELECT id FROM exam_seat_booking WHERE room_id=? AND exam_date=? AND seat_label=?");
            if (!$checkStmt) {
                throw new Exception("CSV import validation query failed: " . $conn->error);
            }
            $checkStmt->bind_param('iss', $roomId, $examDate, $seatLabel);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();

            if ($existing) {
                $updateStmt = $conn->prepare("UPDATE exam_seat_booking SET exam_time=?, exam_ampm=?, exam_shift=?, project_name=?, roll_no=?, candidate_name=?, booking_status=?, session_remarks=?, notes=? WHERE id=?");
                if (!$updateStmt) {
                    throw new Exception("CSV import update query failed: " . $conn->error);
                }
                $existingId = (int)$existing['id'];
                $updateStmt->bind_param('sssssssssi', $examTime, $examAmpm, $examShift, $finalProjectName, $rollNo, $candidateName, $bookingStatus, $sessionRemarks, $notes, $existingId);
                $updateStmt->execute();
            } else {
                $insertStmt = $conn->prepare("INSERT INTO exam_seat_booking (room_id, exam_date, exam_time, exam_ampm, exam_shift, project_name, seat_label, roll_no, candidate_name, booking_status, session_remarks, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$insertStmt) {
                    throw new Exception("CSV import insert query failed: " . $conn->error);
                }
                $insertStmt->bind_param('isssssssssss', $roomId, $examDate, $examTime, $examAmpm, $examShift, $finalProjectName, $seatLabel, $rollNo, $candidateName, $bookingStatus, $sessionRemarks, $notes);
                $insertStmt->execute();
            }

            $imported++;
        }

        set_flash('success', 'CSV imported', $imported . ' booking rows were imported into the seat matrix.');
        redirect_tab('booking');
    }

    if ($action === 'delete_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $conn->query("DELETE FROM exam_seat_booking WHERE id=" . $bookingId);
        set_flash('success', 'Seat booking deleted', 'Seat booking entry was removed.');
        redirect_tab('booking');
    }

    if ($action === 'delete_room_document') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $docRes = $conn->query("SELECT doc_file FROM room_documents WHERE id=" . $docId . " AND room_id=" . $roomId);
        $doc = $docRes ? $docRes->fetch_assoc() : null;
        if ($doc) {
            delete_file_if_exists(__DIR__ . '/uploads/room_documents', $doc['doc_file']);
            $conn->query("DELETE FROM room_documents WHERE id=" . $docId);
            set_flash('success', 'Document deleted', 'The room document was removed.');
        } else {
            set_flash('error', 'Not found', 'Document not found.');
        }
        redirect_tab('manage');
    }
}

$orgStaff = [];
$staffMap = [];
$org_sql = "SELECT id, category, role, name, pic FROM org_chart ORDER BY sort_order, name";
$org_result = $conn->query($org_sql);
if ($org_result && $org_result->num_rows > 0) {
    while ($row = $org_result->fetch_assoc()) {
        $rawNames = preg_split('/(<br\s*\/?>|\n)/i', $row['name']);
        $validNames = [];
        foreach ($rawNames as $n) {
            $n = trim(strip_tags($n));
            if ($n !== '') {
                $validNames[] = $n;
            }
        }
        if (empty($validNames)) {
            $validNames[] = trim(strip_tags($row['name']));
        }
        foreach ($validNames as $index => $splitName) {
            $virtualId = ($index === 0) ? (int)$row['id'] : -1 * ((int)$row['id'] * 1000 + $index);
            $staff = [
                'id' => $virtualId,
                'real_org_id' => (int)$row['id'],
                'category' => $row['category'],
                'role' => $row['role'],
                'name' => $splitName,
                'pic' => $row['pic']
            ];
            $orgStaff[] = $staff;
            $staffMap[$virtualId] = $staff;
        }
    }
}

$facilities = [];
$facilityRecordsByFacility = [];
$facilityLinksByRoom = [];
$bookings = [];
$bookingsByRoom = [];

$facilityRes = $conn->query("SELECT * FROM room_facility ORDER BY category, name");
if ($facilityRes) {
    while ($row = $facilityRes->fetch_assoc()) {
        $facilities[(int)$row['id']] = $row;
    }
}

$linkRes = $conn->query("SELECT * FROM room_facility_room_link");
if ($linkRes) {
    while ($row = $linkRes->fetch_assoc()) {
        $roomId = (int)$row['room_id'];
        $facilityId = (int)$row['facility_id'];
        if (!isset($facilityLinksByRoom[$roomId])) {
            $facilityLinksByRoom[$roomId] = [];
        }
        $facilityLinksByRoom[$roomId][] = $facilityId;
    }
}

$recordRes = $conn->query("SELECT * FROM room_facility_record ORDER BY record_year DESC, id DESC");
if ($recordRes) {
    while ($row = $recordRes->fetch_assoc()) {
        $row['photos'] = json_decode($row['photos_json'] ?? '[]', true);
        if (!is_array($row['photos'])) {
            $row['photos'] = [];
        }
        $facilityRecordsByFacility[(int)$row['facility_id']][] = $row;
    }
}

$bookingRes = $conn->query("SELECT b.*, r.room_no, r.description FROM exam_seat_booking b LEFT JOIN igpess_network r ON r.id = b.room_id ORDER BY b.exam_date DESC, r.room_no ASC, b.seat_label ASC");
if ($bookingRes) {
    while ($row = $bookingRes->fetch_assoc()) {
        $bookings[] = $row;
        $bookingsByRoom[(int)$row['room_id']][] = $row;
    }
}

$roomSql = "SELECT * FROM igpess_network ORDER BY FIELD(floor, 'Ground Floor', 'First Floor', 'Second Floor'), CAST(room_no AS UNSIGNED), room_no";
$roomResult = $conn->query($roomSql);
$buildingData = ["Ground Floor" => [], "First Floor" => [], "Second Floor" => []];
$allRooms = [];

$roomDocuments = [];
$docRes = $conn->query("SELECT * FROM room_documents ORDER BY doc_year DESC");
if ($docRes) {
    while ($row = $docRes->fetch_assoc()) {
        $roomDocuments[(int)$row['room_id']][] = $row;
    }
}

if ($roomResult && $roomResult->num_rows > 0) {
    while ($row = $roomResult->fetch_assoc()) {
        $row['member_ids'] = json_decode($row['room_members'] ?? '[]', true) ?: [];
        $row['sqft'] = (!empty($row['width']) && !empty($row['length'])) ? round(((float)$row['width']) * ((float)$row['length']), 2) : null;
        $row['linked_facility_ids'] = $facilityLinksByRoom[(int)$row['id']] ?? [];
        $row['linked_facilities'] = [];
        foreach ($row['linked_facility_ids'] as $facilityId) {
            if (isset($facilities[$facilityId])) {
                $row['linked_facilities'][] = [
                    'id' => $facilityId,
                    'name' => $facilities[$facilityId]['name'],
                    'icon' => $facilities[$facilityId]['icon'],
                    'category' => $facilities[$facilityId]['category']
                ];
            }
        }
        $row['seat_labels'] = $hasSeatingPlanColumn ? room_seat_labels($row['seating_plan'] ?? '') : [];
        $row['total_capacity'] = count($row['seat_labels']);
        $row['bookings'] = $bookingsByRoom[(int)$row['id']] ?? [];
        $row['documents'] = $roomDocuments[(int)$row['id']] ?? [];
        $row['search_index'] = strtolower(implode(' ', array_filter([
            $row['floor'],
            $row['room_no'],
            $row['description'],
            $row['remarks'],
            $row['networking'],
            $row['interactive_board'],
            $row['wifi_router'],
            $row['cctv'],
            $row['ups'],
            $row['audio_video'],
            implode(' ', array_map(static function ($f) {
                return $f['name'] . ' ' . $f['category'];
            }, $row['linked_facilities']))
        ])));
        $buildingData[$row['floor']][] = $row;
        $allRooms[] = $row;
    }
}

$stats = [
    'rooms' => count($allRooms),
    'facilities' => count($facilities),
    'seat_capacity' => array_sum(array_map(static function ($room) {
        return (int)$room['total_capacity'];
    }, $allRooms)),
    'bookings' => count($bookings)
];

$activeTab = $_GET['tab'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IGIPESS Room Network Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --brand: #153a64;
            --brand2: #1f5f93;
            --accent: #f4b63f;
            --soft: #eff5fb;
        }
        body {
            background: linear-gradient(180deg, #f7fbff 0%, #eef4fb 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #223449;
        }
        .hero, .panel, .room-card, .metric-box, .modal-content {
            border-radius: 22px;
            border: 0;
            box-shadow: 0 18px 44px rgba(34, 52, 73, 0.09);
        }
        .hero {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand2) 100%);
            color: #fff;
            padding: 2rem;
            overflow: hidden;
            position: relative;
        }
        .hero::after {
            content: "";
            position: absolute;
            right: -60px;
            bottom: -60px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(244, 182, 63, 0.18);
        }
        .metric-box {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 1rem;
            min-height: 95px;
        }
        .panel {
            background: #fff;
            padding: 1.25rem;
        }
        .room-card {
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
            padding: 1rem;
            height: 100%;
            cursor: pointer;
            transition: 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .room-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 50px rgba(34, 52, 73, 0.15);
        }
        .room-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--brand), var(--accent));
        }
        .room-image {
            width: 100%;
            height: 145px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid #d8e4ef;
        }
        .room-icon {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            background: var(--soft);
            color: var(--brand2);
            font-size: 1.2rem;
        }
        .room-number {
            font-weight: 800;
            color: var(--brand);
            font-size: 1.12rem;
        }
        .nav-pills .nav-link {
            border-radius: 999px;
            color: var(--brand);
            background: #fff;
            border: 1px solid #d6e2ee;
            font-weight: 700;
            margin: 0.2rem;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(90deg, var(--brand), var(--brand2));
            border-color: transparent;
            color: #fff;
        }
        .seat-grid {
            display: grid;
            gap: 8px;
            justify-content: start;
        }
        .seat-box {
            min-width: 54px;
            min-height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.72rem;
            font-weight: 700;
            border: 2px solid transparent;
            padding: 0.25rem;
            line-height: 1.15;
        }
        .seat-editable { cursor: pointer; }
        .seat-active { background: #d9f5e5; color: #0d6a43; border-color: #b0e1c6; }
        .seat-blocked { background: #fde1e1; color: #a61f2a; border-color: #f4bcc1; text-decoration: line-through; }
        .seat-booked { background: #dbeafe; color: #1d4ed8; border-color: #a4c3ff; }
        .seat-open { background: #edf9f2; color: #0f7a47; border-color: #c7ead3; }
        .seat-desk {
            width: 100%;
            background: #556372;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 0.45rem;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .seat-scroll-wrap {
            max-height: 55vh;
            min-height: 140px;
            overflow: auto;
            padding: 0.35rem;
            border-radius: 14px;
            background: #fbfdff;
        }
        .seat-builder-wrap {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0.5rem;
            padding-bottom: 1rem;
        }
        .facility-chip {
            border-radius: 999px;
            background: #ecf7ff;
            color: #0f5f95;
            border: 1px solid #bedef6;
            padding: 0.35rem 0.7rem;
            font-size: 0.74rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            cursor: pointer;
        }
        .photo-thumb {
            width: 74px;
            height: 74px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #d8e4ef;
        }
        .search-input {
            border-radius: 999px;
        }
        .section-title {
            color: var(--brand);
            font-weight: 800;
        }
        .admin-floating {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 2;
        }
        .small-muted {
            font-size: 0.8rem;
            color: #6d8096;
        }
        .doc-chip {
            border-radius: 8px;
            background: #fff3e0;
            color: #c96b00;
            border: 1px solid #ffdead;
            padding: 0.25rem 0.6rem;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        /* Map container styles */
        #adminMap {
            height: 300px;
            width: 100%;
            border-radius: 12px;
            border: 2px solid var(--brand);
            z-index: 1;
            margin-bottom: 10px;
        }
        .map-help-text {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .dimension-preview {
            background: var(--soft);
            border-radius: 10px;
            padding: 8px 12px;
        }
        @media print {
            .no-print { display: none !important; }
            @page { size: A4 landscape; margin: 6mm; }
            .room-sheet {
                page-break-after: always;
                break-inside: avoid;
                page-break-inside: avoid;
                margin-bottom: 5mm;
            }
            .room-sheet:last-child {
                page-break-after: auto;
            }
        }
        .modal-dialog-scrollable .modal-content {
            max-height: 95vh;
            display: flex;
            flex-direction: column;
        }
        .modal-dialog-scrollable .modal-body {
            overflow-y: auto;
            flex: 1;
        }
        .modal-dialog-scrollable .modal-footer {
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }
        .accordion-body {
            max-height: 400px;
            overflow-y: auto;
        }
        .seat-builder-wrap {
            max-height: 450px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .candidate-table th, .candidate-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            font-size: 9px;
            text-align: left;
        }
        .candidate-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .seat-number {
            font-weight: bold;
            text-align: center;
            width: 50px;
        }
        .vertical-numbering {
            display: inline-block;
            font-size: 7px;
            color: #666;
            margin-left: 2px;
        }
        .export-ignore {
            display: none !important;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-lg-4 px-3">
    <div class="hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <h1 class="h2 fw-bold mb-2"><i class="fas fa-network-wired me-2"></i>Room Network Administrative Console</h1>
                <p class="mb-0 text-white-50">Rooms, staff, hardware, facilities, yearly lab records and exam seating are handled in one admin workflow.</p>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6"><div class="metric-box"><div class="small text-white-50">Room Cards</div><div class="display-6 fw-bold"><?= (int)$stats['rooms'] ?></div></div></div>
                    <div class="col-6"><div class="metric-box"><div class="small text-white-50">Facilities</div><div class="display-6 fw-bold"><?= (int)$stats['facilities'] ?></div></div></div>
                    <div class="col-6"><div class="metric-box"><div class="small text-white-50">Seat Capacity</div><div class="display-6 fw-bold"><?= (int)$stats['seat_capacity'] ?></div></div></div>
                    <div class="col-6"><div class="metric-box"><div class="small text-white-50">Seat Bookings</div><div class="display-6 fw-bold"><?= (int)$stats['bookings'] ?></div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mb-4 no-print">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white rounded-start-pill"><i class="fas fa-search text-primary"></i></span>
                    <input type="text" id="globalSearch" class="form-control border-start-0 shadow-none search-input" placeholder="Search room, facility, staff, hardware, seats...">
                </div>
            </div>
            <div class="col-lg-7 text-lg-end">
                <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
                    <button class="btn btn-outline-secondary rounded-pill" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
                    <div class="dropdown">
                        <button class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cloud-download-alt me-2"></i> Export Data
                        </button>
                        <ul class="dropdown-menu shadow-lg border-0 rounded-3 mt-2">
                            <li><h6 class="dropdown-header text-muted fw-bold">Download Visible Data</h6></li>
                            <li><a class="dropdown-item py-2" href="#" onclick="exportData('csv')"><i class="fas fa-file-csv text-success me-2"></i> Export to CSV</a></li>
                            <li><a class="dropdown-item py-2" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel text-success me-2"></i> Export to Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf text-danger me-2"></i> Export to PDF</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-primary rounded-pill" onclick="openRoomEditor('add')"><i class="fas fa-plus me-2"></i>Add Room</button>
                    <button class="btn btn-success rounded-pill" onclick="openFacilityEditor('add')"><i class="fas fa-building-circle-check me-2"></i>Add Facility</button>
                    <button class="btn btn-warning rounded-pill text-dark" onclick="openBookingEditor()"><i class="fas fa-chair me-2"></i>Seat Booking</button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-4 no-print">
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'all' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-all" type="button">All Rooms</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'ground' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-ground" type="button">Ground Floor</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'first' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-first" type="button">First Floor</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'second' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-second" type="button">Second Floor</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'manage' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-manage" type="button">Room Registry</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'facilities' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-facilities" type="button">Facilities</button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'booking' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-booking" type="button">Exam Seat Desk</button></li>
    </ul>

    <div class="tab-content">
        <?php
        $floorTabs = [
            'all' => ['label' => 'All Rooms', 'rooms' => $allRooms],
            'ground' => ['label' => 'Ground Floor', 'rooms' => $buildingData['Ground Floor'] ?? []],
            'first' => ['label' => 'First Floor', 'rooms' => $buildingData['First Floor'] ?? []],
            'second' => ['label' => 'Second Floor', 'rooms' => $buildingData['Second Floor'] ?? []]
        ];
        foreach ($floorTabs as $tabKey => $tabData):
        ?>
        <div class="tab-pane fade <?= $activeTab === $tabKey ? 'show active' : '' ?>" id="tab-<?= h($tabKey) ?>">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0"><?= h($tabData['label']) ?></h3>
                    <span class="badge text-bg-light"><?= count($tabData['rooms']) ?> rooms</span>
                </div>
                <div class="row g-4 floor-wrap">
                    <?php foreach ($tabData['rooms'] as $room): ?>
                        <?php $displayImg = !empty($room['room_image']) ? './images/' . h($room['room_image']) : './images/1.jpg'; ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 room-card-wrapper" data-search="<?= h($room['search_index']) ?>">
                            <div class="room-card" onclick="openViewModal('<?= addslashes((string)$room['room_no']) ?>')">
                                <div class="admin-floating no-print">
                                    <button type="button" class="btn btn-sm btn-light" onclick='event.stopPropagation(); openRoomEditor("edit", <?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-pen"></i></button>
                                </div>
                                <img src="<?= $displayImg ?>" class="room-image mb-3" alt="Room Image" onerror="this.src='./images/1.jpg'">
                                <div class="d-flex gap-3 align-items-start mb-2">
                                    <div class="room-icon"><i class="fas <?= h($room['icon'] ?: 'fa-door-open') ?>"></i></div>
                                    <div>
                                        <div class="room-number">Room <?= h($room['room_no']) ?></div>
                                        <div class="small-muted"><?= h($room['description']) ?></div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php if ($room['networking'] === 'Yes'): ?><span class="badge bg-primary-subtle text-primary">Net</span><?php endif; ?>
                                    <?php if ($room['interactive_board'] === 'Yes'): ?><span class="badge bg-success-subtle text-success">Board</span><?php endif; ?>
                                    <?php if ($room['wifi_router'] === 'Yes'): ?><span class="badge bg-info-subtle text-info-emphasis">Wi-Fi</span><?php endif; ?>
                                    <?php if ($room['cctv'] === 'Yes'): ?><span class="badge bg-danger-subtle text-danger">CCTV</span><?php endif; ?>
                                    <?php if ($room['ups'] === 'Yes'): ?><span class="badge bg-warning-subtle text-warning-emphasis">UPS</span><?php endif; ?>
                                    <?php if ($room['audio_video'] === 'Yes'): ?><span class="badge bg-secondary-subtle text-secondary-emphasis">A/V</span><?php endif; ?>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><div class="panel py-2 px-2"><div class="small-muted">Seats</div><div class="fw-bold text-primary"><?= (int)$room['total_capacity'] ?></div></div></div>
                                    <div class="col-6"><div class="panel py-2 px-2"><div class="small-muted">Bookings</div><div class="fw-bold text-success"><?= count($room['bookings']) ?></div></div></div>
                                </div>
                                <?php if (!empty($room['linked_facilities'])): ?>
                                    <div class="mb-3">
                                        <div class="small-muted fw-semibold mb-2">Linked Facilities</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($room['linked_facilities'] as $facility): ?>
                                                <button type="button" class="facility-chip border-0" onclick="event.stopPropagation(); openFacilityViewer(<?= (int)$facility['id'] ?>)">
                                                    <i class="fas <?= h($facility['icon']) ?>"></i><?= h($facility['name']) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($room['documents'])): ?>
                                    <div class="mt-2">
                                        <i class="fas fa-file-pdf text-danger me-1"></i>
                                        <span class="small text-muted"><?= count($room['documents']) ?> document(s)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="tab-pane fade <?= $activeTab === 'manage' ? 'show active' : '' ?>" id="tab-manage">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0">Room Registry</h3>
                    <button class="btn btn-primary rounded-pill" onclick="openRoomEditor('add')"><i class="fas fa-plus me-2"></i>Add Room</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="manageTable">
                        <thead class="table-light">
                            <tr>
                                <th>Floor</th>
                                <th>Room</th>
                                <th>Description</th>
                                <th>Seats</th>
                                <th>Facilities</th>
                                <th>Staff</th>
                                <th>Documents</th>
                                <th class="text-end no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allRooms as $room): ?>
                            <tr class="manage-row" data-search="<?= h($room['search_index']) ?>">
                                <td><?= h($room['floor']) ?></td>
                                <td>
                                    <div class="fw-bold text-primary">Room <?= h($room['room_no']) ?></div>
                                    <div class="small-muted"><?= $room['sqft'] ? h($room['sqft']) . ' sq.ft' : 'Size pending' ?></div>
                                </div>
                                <td><?= h($room['description']) ?></td>
                                <td><span class="badge text-bg-info"><?= (int)$room['total_capacity'] ?></span></td>
                                <td>
                                    <?php if (!empty($room['linked_facilities'])): ?>
                                        <?php foreach ($room['linked_facilities'] as $facility): ?>
                                            <span class="facility-chip"><i class="fas <?= h($facility['icon']) ?>"></i><?= h($facility['name']) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not linked</span>
                                    <?php endif; ?>
                                </div>
                                <td>
                                    <?php
                                    $names = [];
                                    foreach ($room['member_ids'] as $memberId) {
                                        if (isset($staffMap[$memberId])) {
                                            $label = $staffMap[$memberId]['name'];
                                            if ((int)$room['in_charge_id'] === (int)$memberId) {
                                                $label .= ' (In-Charge)';
                                            }
                                            $names[] = h($label);
                                        }
                                    }
                                    echo !empty($names) ? implode('<br>', $names) : '<span class="text-muted">No personnel</span>';
                                    ?>
                                </div>
                                <td>
                                    <?php if (!empty($room['documents'])): ?>
                                        <?php foreach ($room['documents'] as $doc): ?>
                                            <div class="doc-chip mb-1">
                                                <i class="fas fa-file-pdf"></i>
                                                <span><?= h($doc['doc_year']) ?>: <?= h($doc['doc_title']) ?></span>
                                                <a href="./uploads/room_documents/<?= h($doc['doc_file']) ?>" target="_blank" class="text-danger" onclick="event.stopPropagation()">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No documents</span>
                                    <?php endif; ?>
                                </div>
                                <td class="text-end no-print">
                                    <button class="btn btn-sm btn-warning" onclick='openRoomEditor("edit", <?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="openBookingEditor(<?= (int)$room['id'] ?>)"><i class="fas fa-chair"></i></button>
                                    <form method="POST" class="d-inline confirm-form" data-confirm-title="Delete room?" data-confirm-text="This will delete the room and its seat bookings and documents.">
                                        <input type="hidden" name="action" value="delete_room">
                                        <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'facilities' ? 'show active' : '' ?>" id="tab-facilities">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0">Facilities</h3>
                    <button class="btn btn-success rounded-pill" onclick="openFacilityEditor('add')"><i class="fas fa-plus me-2"></i>Add Facility</button>
                </div>
                <div class="row g-4">
                    <?php if (empty($facilities)): ?>
                        <div class="col-12"><div class="alert alert-light border">No facility has been added yet.</div></div>
                    <?php endif; ?>
                    <?php foreach ($facilities as $facility): ?>
                        <?php
                        $facilityId = (int)$facility['id'];
                        $cover = !empty($facility['cover_image']) ? './uploads/facilities/covers/' . h($facility['cover_image']) : './images/1.jpg';
                        $linkedRooms = array_values(array_filter($allRooms, static function ($room) use ($facilityId) {
                            return in_array($facilityId, $room['linked_facility_ids'], true);
                        }));
                        $records = $facilityRecordsByFacility[$facilityId] ?? [];
                        ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="room-card h-100">
                                <img src="<?= $cover ?>" class="room-image mb-3" alt="Facility Cover" onerror="this.src='./images/1.jpg'">
                                <div class="room-number"><?= h($facility['name']) ?></div>
                                <div class="small-muted mb-2"><?= h($facility['category']) ?></div>
                                <p class="small-muted mb-3"><?= h($facility['overview']) ?></p>
                                <div class="row g-2 mb-3">
                                    <div class="col-4"><div class="panel py-2 px-2"><div class="small-muted">Rooms</div><div class="fw-bold"><?= count($linkedRooms) ?></div></div></div>
                                    <div class="col-4"><div class="panel py-2 px-2"><div class="small-muted">Records</div><div class="fw-bold"><?= count($records) ?></div></div></div>
                                    <div class="col-4"><div class="panel py-2 px-2"><div class="small-muted">Status</div><div class="fw-bold"><?= h($facility['status'] ?? 'Active') ?></div></div></div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="openFacilityViewer(<?= $facilityId ?>)"><i class="fas fa-eye me-1"></i>View</button>
                                    <button class="btn btn-warning btn-sm rounded-pill" onclick='openFacilityEditor("edit", <?= json_encode($facility, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-pen me-1"></i>Edit</button>
                                    <button class="btn btn-info btn-sm rounded-pill text-white" onclick="openFacilityRecordEditor('add', {facility_id: <?= $facilityId ?>})"><i class="fas fa-folder-plus me-1"></i>Add Record</button>
                                    <form method="POST" class="d-inline confirm-form" data-confirm-title="Delete facility?" data-confirm-text="This will safely unlink rooms and clean facility records.">
                                        <input type="hidden" name="action" value="delete_facility">
                                        <input type="hidden" name="facility_id" value="<?= $facilityId ?>">
                                        <button type="submit" class="btn btn-danger btn-sm rounded-pill"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'booking' ? 'show active' : '' ?>" id="tab-booking">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0">Examination Seat Desk</h3>
                    <button class="btn btn-warning rounded-pill text-dark" onclick="openBookingEditor()"><i class="fas fa-plus me-2"></i>Add Booking</button>
                </div>
                <div class="row g-3 mb-4">
                    <?php foreach ($allRooms as $room): ?>
                        <div class="col-xl-3 col-md-6">
                            <div class="panel h-100">
                                <div class="fw-bold text-primary">Room <?= h($room['room_no']) ?></div>
                                <div class="small-muted"><?= h($room['description']) ?></div>
                                <div class="mt-2"><span class="badge text-bg-info"><?= (int)$room['total_capacity'] ?> seats</span> <span class="badge text-bg-success"><?= count($room['bookings']) ?> bookings</span></div>
                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="openBookingEditor(<?= (int)$room['id'] ?>)">Manage</button>
                                    <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="printSeatingPlan(<?= (int)$room['id'] ?>)">Print Plan</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Room</th>
                                <th>Project</th>
                                <th>Seat</th>
                                <th>Roll No.</th>
                                <th>Candidate</th>
                                <th>Status</th>
                                <th class="text-end no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No seat bookings available.</div> </tbody>
                        <?php endif; ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= h($booking['exam_date']) ?></td>
                                <td><div class="fw-bold text-primary">Room <?= h($booking['room_no']) ?></div><div class="small-muted"><?= h($booking['description']) ?></div></td>
                                <td><?= h($booking['project_name']) ?></td>
                                <td><span class="badge text-bg-light"><?= h($booking['seat_label']) ?></span></td>
                                <td><?= h($booking['roll_no']) ?></td>
                                <td><?= h($booking['candidate_name']) ?></td>
                                <td><span class="badge text-bg-success"><?= h($booking['booking_status']) ?></span></td>
                                <td class="text-end no-print">
                                    <button class="btn btn-sm btn-warning" onclick='openBookingEditor(<?= (int)$booking["room_id"] ?>, <?= json_encode($booking, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-edit"></i></button>
                                    <form method="POST" class="d-inline confirm-form" data-confirm-title="Delete booking?" data-confirm-text="This booking row will be removed.">
                                        <input type="hidden" name="action" value="delete_booking">
                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Room View Modal -->
<div class="modal fade" id="roomViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold"><i id="viewRoomIcon" class="fas fa-door-open text-primary me-2"></i>Room <span id="viewRoomNo"></span></h5>
                    <div class="small-muted" id="viewRoomDesc"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <img id="viewRoomImage" src="./images/1.jpg" class="room-image mb-3" style="height:220px;" alt="Room Image">
                        <div class="panel mb-3">
                            <div class="small-muted">Dimensions</div>
                            <div class="fw-bold text-primary" id="viewRoomDimensions">Not set</div>
                        </div>
                        <div class="panel mb-3">
                            <div class="small-muted">Seat Capacity</div>
                            <div class="fw-bold text-primary"><span id="viewRoomCapacity">0</span> seats</div>
                            <div class="small-muted"><span id="viewRoomBookingCount">0</span> booking entries</div>
                        </div>
                        <div class="panel">
                            <div class="small-muted mb-2">Hardware Setup</div>
                            <div class="small">Networking: <strong id="viewNet">-</strong></div>
                            <div class="small">Board: <strong id="viewBoard">-</strong></div>
                            <div class="small">Wi-Fi: <strong id="viewWifi">-</strong></div>
                            <div class="small">CCTV: <strong id="viewCctv">-</strong></div>
                            <div class="small">UPS: <strong id="viewUps">-</strong></div>
                            <div class="small">A/V: <strong id="viewAv">-</strong></div>
                        </div>
                        <div class="panel mt-3">
                            <div class="small-muted mb-2">Year-wise Documents</div>
                            <div id="viewDocumentsList" class="small"></div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="panel mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-primary">Linked Facilities</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="viewManageSeatsBtn"><i class="fas fa-chair me-1"></i>Manage Seats</button>
                            </div>
                            <div id="viewFacilityWrap" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <div class="panel mb-3">
                            <div class="fw-bold text-primary mb-2">Room Personnel</div>
                            <div id="viewMembersWrap" class="row g-2"></div>
                        </div>
                        <div class="panel mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-primary">Seating Layout</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="viewPrintPlanBtn"><i class="fas fa-print me-1"></i>Print Plan</button>
                            </div>
                            <div id="viewSeatMap" class="seat-scroll-wrap"></div>
                        </div>
                        <div class="panel">
                            <div class="fw-bold text-primary mb-2">Remarks</div>
                            <div id="viewRemarks" class="small-muted">No remarks added.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Room Editor Modal -->
<div class="modal fade" id="roomEditorModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="max-height: 95vh; display: flex; flex-direction: column;">
            <form method="POST" enctype="multipart/form-data" id="roomEditorForm" style="display: flex; flex-direction: column; height: 100%;">
                <div class="modal-header border-0 pb-0 flex-shrink-0">
                    <h5 class="modal-title fw-bold" id="roomEditorTitle">Add Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="overflow-y: auto; flex: 1; padding-bottom: 0;">
                    <input type="hidden" name="action" value="save_room">
                    <input type="hidden" name="id" id="roomIdField">
                    <input type="hidden" name="old_image" id="roomOldImageField">
                    <input type="hidden" name="old_doc_file" id="roomOldDocField">
                    <input type="hidden" name="seating_plan" id="roomSeatingPlanField" value="">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="section-title">Room Profile</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Floor</label>
                                    <select class="form-select" name="floor" id="roomFloorField">
                                        <option value="Ground Floor">Ground Floor</option>
                                        <option value="First Floor">First Floor</option>
                                        <option value="Second Floor">Second Floor</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Room No.</label>
                                    <input type="text" class="form-control" name="room_no" id="roomNoField" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Icon</label>
                                    <select class="form-select" name="icon" id="roomIconField">
                                        <option value="fa-door-open">🚪 Room</option>
                                        <option value="fa-chalkboard">📚 Class Room</option>
                                        <option value="fa-chalkboard-user">💻 Smart Room</option>
                                        <option value="fa-desktop">🖥️ Computer Room</option>
                                        <option value="fa-book-open-reader">📖 Library</option>
                                        <option value="fa-dumbbell">💪 Gym / Sports</option>
                                        <option value="fa-building-columns">🏛️ Hall</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="desc" id="roomDescField" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Room Image</label>
                                    <input type="file" class="form-control" name="room_image" accept=".jpg,.jpeg,.png,.gif">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Image</label>
                                    <div class="border rounded p-2 bg-light">
                                        <img src="./images/1.jpg" id="roomPreviewImage" style="height:84px;width:130px;object-fit:cover;border-radius:12px;" onerror="this.src='./images/1.jpg'">
                                    </div>
                                </div>

                                <!-- Dimensions Section -->
                                <div class="col-12">
                                    <hr>
                                    <h6 class="section-title mt-2">📏 Room Dimensions & Area</h6>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Width (feet)</label>
                                    <input type="number" step="0.01" class="form-control" name="width" id="roomWidthField" placeholder="e.g., 20">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Length (feet)</label>
                                    <input type="number" step="0.01" class="form-control" name="length" id="roomLengthField" placeholder="e.g., 30">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Sq. Ft.</label>
                                    <input type="text" class="form-control bg-light fw-bold text-primary" id="roomSqftField" readonly placeholder="Auto">
                                </div>

                                <!-- Geo-Location Section -->
                                <div class="col-12">
                                    <hr>
                                    <h6 class="section-title mt-2">📍 Geo-Location Tagging</h6>
                                    <p class="small text-muted mb-2">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                        Click on the map to set the exact location of this room. Drag the marker to adjust.
                                    </p>
                                    <div id="adminMap"></div>
                                    <div class="map-help-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Tip: Zoom in for precise location. You can also manually enter coordinates below.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" class="form-control" name="latitude" id="roomLatField" placeholder="e.g., 28.631123">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" class="form-control" name="longitude" id="roomLngField" placeholder="e.g., 77.071437">
                                </div>
                            </div>

                            <!-- Hardware Accordion -->
                            <div class="accordion mt-4" id="roomHardwareAccordion">
                                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#roomHardwareCollapse">
                                            🖥️ Hardware and Remarks
                                        </button>
                                    </h2>
                                    <div id="roomHardwareCollapse" class="accordion-collapse collapse">
                                        <div class="accordion-body" style="max-height: 300px; overflow-y: auto;">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Networking</label>
                                                    <select class="form-select" name="networking" id="roomNetField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Interactive Board</label>
                                                    <select class="form-select" name="interactive_board" id="roomBoardField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Wi-Fi Router</label>
                                                    <select class="form-select" name="wifi_router" id="roomWifiField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">CCTV</label>
                                                    <select class="form-select" name="cctv" id="roomCctvField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">UPS</label>
                                                    <select class="form-select" name="ups" id="roomUpsField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Audio / Video</label>
                                                    <select class="form-select" name="audio_video" id="roomAvField">
                                                        <option value="">N/A</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea class="form-control" name="remarks" id="roomRemarksField" rows="3" placeholder="Any special notes about this room..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Year-wise Document Section -->
                            <div class="col-12 mt-4">
                                <hr>
                                <h6 class="section-title mt-2">📄 Year-wise Document (PDF)</h6>
                                <div class="small-muted mb-2">Upload PDF documents like item issuance/receipt records year-wise</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Document Year</label>
                                    <input type="text" class="form-control" name="doc_year" id="roomDocYearField" placeholder="e.g., 2024-25">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Document Title</label>
                                    <input type="text" class="form-control" name="doc_title" id="roomDocTitleField" placeholder="e.g., Lab Equipment Issuance Register">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea class="form-control" name="doc_description" id="roomDocDescField" rows="2" placeholder="Brief description of this document"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">PDF Document</label>
                                    <input type="file" class="form-control" name="room_document" accept=".pdf">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Document</label>
                                    <div id="roomCurrentDocInfo" class="border rounded p-2 bg-light small"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h6 class="section-title">👥 Personnel and Facility Mapping</h6>
                            <div class="mb-3">
                                <label class="form-label">Link Facilities</label>
                                <div class="border rounded p-3 bg-light" style="max-height:180px; overflow:auto;">
                                    <div class="row g-2">
                                        <?php foreach ($facilities as $facility): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input room-facility-checkbox" type="checkbox" name="facility_ids[]" value="<?= (int)$facility['id'] ?>" id="facility_link_<?= (int)$facility['id'] ?>">
                                                    <label class="form-check-label small" for="facility_link_<?= (int)$facility['id'] ?>">
                                                        <?= h($facility['name']) ?>
                                                        <span class="text-muted d-block"><?= h($facility['category']) ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Assign Personnel and In-Charge</label>
                                <div class="border rounded p-3 bg-light" style="max-height:260px; overflow:auto;">
                                    <?php foreach ($orgStaff as $staff): ?>
                                        <div class="d-flex align-items-center gap-3 border-bottom py-2">
                                            <input class="form-check-input room-member-check" type="checkbox" name="room_members[]" value="<?= (int)$staff['id'] ?>" id="staff_<?= (int)$staff['id'] ?>">
                                            <input class="form-check-input room-incharge-radio" type="radio" name="in_charge_id" value="<?= (int)$staff['id'] ?>" id="incharge_<?= (int)$staff['id'] ?>">
                                            <div>
                                                <div class="fw-semibold"><?= h($staff['name']) ?></div>
                                                <div class="small-muted"><?= h($staff['role']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($hasSeatingPlanColumn): ?>
                            <div class="accordion mt-4" id="roomSeatAccordion">
                                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#roomSeatCollapse">
                                            🪑 Seat Builder
                                        </button>
                                    </h2>
                                    <div id="roomSeatCollapse" class="accordion-collapse collapse">
                                        <div class="accordion-body" style="max-height: 450px; overflow-y: auto;">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Layout Type</label>
                                                    <select class="form-select" id="seatLayoutType">
                                                        <option value="none">No Seat Map</option>
                                                        <option value="grid">Grid Layout</option>
                                                        <option value="open">Open Seat Count</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 seat-grid-input d-none">
                                                    <label class="form-label">Rows</label>
                                                    <input type="number" class="form-control" id="seatRows" value="5" min="1" max="50">
                                                </div>
                                                <div class="col-md-2 seat-grid-input d-none">
                                                    <label class="form-label">Cols</label>
                                                    <input type="number" class="form-control" id="seatCols" value="5" min="1" max="50">
                                                </div>
                                                <div class="col-md-2 seat-grid-input d-none">
                                                    <label class="form-label">Prefix</label>
                                                    <input type="text" class="form-control" id="seatPrefix" value="S">
                                                </div>
                                                <div class="col-md-2 seat-grid-input d-none d-flex align-items-end">
                                                    <button type="button" class="btn btn-dark w-100" onclick="generateEditableSeatGrid()">
                                                        <i class="fas fa-table-cells"></i> Generate
                                                    </button>
                                                </div>
                                                <div class="col-md-6 seat-open-input d-none">
                                                    <label class="form-label">Total Seats</label>
                                                    <input type="number" class="form-control" id="seatTotalOpen" min="1">
                                                </div>
                                            </div>
                                            <div class="panel p-3 mt-3">
                                                <div id="editableSeatMap" class="seat-scroll-wrap" style="max-height: 300px; overflow: auto;">
                                                    <div class="text-muted">Seat map not generated yet.</div>
                                                </div>
                                                <div class="fw-bold text-primary mt-3">
                                                    <span id="seatCapacityPreview">0</span> valid seats
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning border mt-4 mb-0">
                                `seating_plan` column is not available in `igpess_network`, so seat maps are disabled until that column exists.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 flex-shrink-0" style="background: white; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill">Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Facility Editor Modal -->
<div class="modal fade" id="facilityEditorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="facilityEditorTitle">Add Facility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_facility">
                    <input type="hidden" name="facility_id" id="facilityIdField">
                    <input type="hidden" name="old_cover_image" id="facilityOldCoverField">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Facility Name</label><input type="text" class="form-control" name="facility_name" id="facilityNameField" required></div>
                        <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="facility_category" id="facilityCategoryField"><?php foreach ($facilityCategories as $category): ?><option value="<?= h($category) ?>"><?= h($category) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Icon</label><input type="text" class="form-control" name="facility_icon" id="facilityIconField" value="fa-building"></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="facility_status" id="facilityStatusField"><option value="Active">Active</option><option value="Operational">Operational</option><option value="Under Maintenance">Under Maintenance</option><option value="Restricted">Restricted</option></select></div>
                        <div class="col-md-4"><label class="form-label">Capacity Label</label><input type="text" class="form-control" name="capacity_label" id="facilityCapacityField" placeholder="e.g. 42 Systems"></div>
                        <div class="col-md-6"><label class="form-label">Contact Person</label><input type="text" class="form-control" name="contact_person" id="facilityContactPersonField"></div>
                        <div class="col-md-6"><label class="form-label">Contact Phone</label><input type="text" class="form-control" name="contact_phone" id="facilityContactPhoneField"></div>
                        <div class="col-12"><label class="form-label">Location Note</label><input type="text" class="form-control" name="location_note" id="facilityLocationField"></div>
                        <div class="col-12"><label class="form-label">Overview</label><textarea class="form-control" name="facility_overview" id="facilityOverviewField" rows="4"></textarea></div>
                        <div class="col-md-6"><label class="form-label">Cover Image</label><input type="file" class="form-control" name="cover_image" accept=".jpg,.jpeg,.png,.gif"></div>
                        <div class="col-md-6"><label class="form-label">Current Image</label><div class="border rounded p-2 bg-light"><img src="./images/1.jpg" id="facilityPreviewImage" style="height:84px;width:130px;object-fit:cover;border-radius:12px;" onerror="this.src='./images/1.jpg'"></div></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success rounded-pill">Save Facility</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Facility Viewer Modal -->
<div class="modal fade" id="facilityViewerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold"><i id="facilityViewIcon" class="fas fa-building text-success me-2"></i><span id="facilityViewName"></span></h5>
                    <div class="small-muted" id="facilityViewCategory"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <img src="./images/1.jpg" id="facilityViewImage" class="room-image mb-3" style="height:220px;" alt="Facility Image">
                        <div class="panel mb-3">
                            <div class="small-muted">Status</div>
                            <div class="fw-bold text-primary" id="facilityViewStatus"></div>
                        </div>
                        <div class="panel mb-3">
                            <div class="small-muted">Contact</div>
                            <div class="fw-bold" id="facilityViewContact"></div>
                            <div class="small-muted mt-1" id="facilityViewLocation"></div>
                        </div>
                        <div class="panel">
                            <div class="small-muted mb-2">Linked Rooms</div>
                            <div id="facilityViewRooms" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="panel mb-3">
                            <div class="fw-bold text-primary mb-2">Overview</div>
                            <div id="facilityViewOverview" class="small-muted"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold text-primary">Year-wise Inventory / Register Records</div>
                            <button type="button" class="btn btn-info text-white btn-sm rounded-pill" id="facilityAddRecordBtn"><i class="fas fa-plus me-1"></i>Add Record</button>
                        </div>
                        <div id="facilityRecordList" class="row g-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Facility Record Modal -->
<div class="modal fade" id="facilityRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="facilityRecordTitle">Add Facility Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_facility_record">
                    <input type="hidden" name="record_id" id="facilityRecordIdField">
                    <input type="hidden" name="record_facility_id" id="facilityRecordFacilityIdField">
                    <input type="hidden" name="old_register_pdf" id="facilityRecordOldPdfField">
                    <input type="hidden" name="existing_photos_json" id="facilityRecordExistingPhotosField">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Year</label><input type="text" class="form-control" name="record_year" id="facilityRecordYearField" placeholder="2025-26" required></div>
                        <div class="col-md-8"><label class="form-label">Record Title</label><input type="text" class="form-control" name="record_title" id="facilityRecordTitleField" required></div>
                        <div class="col-md-4"><label class="form-label">Equipment Count</label><input type="text" class="form-control" name="equipment_count" id="facilityRecordCountField" placeholder="e.g. 35 PCs"></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="record_status" id="facilityRecordStatusField"><option value="Active">Active</option><option value="Updated">Updated</option><option value="Archived">Archived</option><option value="Under Review">Under Review</option></select></div>
                        <div class="col-md-4"><label class="form-label">Register PDF</label><input type="file" class="form-control" name="register_pdf" accept=".pdf"></div>
                        <div class="col-12"><label class="form-label">Item Summary</label><textarea class="form-control" name="item_summary" id="facilityRecordSummaryField" rows="4"></textarea></div>
                        <div class="col-12"><label class="form-label">Photos</label><input type="file" class="form-control" name="record_photos[]" accept=".jpg,.jpeg,.png,.gif" multiple><div id="facilityRecordPhotoPreview" class="d-flex flex-wrap gap-2 mt-3"></div></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-chair text-warning me-2"></i>Examination Seat Booking</h5>
                    <div class="small-muted">Add, edit and delete room seat bookings for examinations.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="panel">
                            <form method="POST" id="bookingForm">
                                <input type="hidden" name="action" value="save_booking">
                                <input type="hidden" name="booking_id" id="bookingIdField">
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label">Room</label><select class="form-select" name="booking_room_id" id="bookingRoomField" required><?php foreach ($allRooms as $room): ?><option value="<?= (int)$room['id'] ?>">Room <?= h($room['room_no']) ?> - <?= h($room['description']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-6"><label class="form-label">Exam Date</label><input type="date" class="form-control" name="exam_date" id="bookingDateField" required></div>
                                    <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="booking_status" id="bookingStatusField"><option value="Reserved">Reserved</option><option value="Confirmed">Confirmed</option><option value="Present">Present</option><option value="Vacant">Vacant</option></select></div>
                                    <div class="col-md-4"><label class="form-label">Timing</label><input type="time" class="form-control" name="exam_time" id="bookingTimeField"></div>
                                    <div class="col-md-4"><label class="form-label">AM / PM</label><select class="form-select" name="exam_ampm" id="bookingAmpmField"><option value="">Auto</option><option value="AM">AM</option><option value="PM">PM</option></select></div>
                                    <div class="col-md-4"><label class="form-label">Shift</label><select class="form-select" name="exam_shift" id="bookingShiftField"><option value="">Auto</option><option value="Morning">Morning</option><option value="Evening">Evening</option></select></div>
                                    <div class="col-12"><label class="form-label">Project / Paper</label><input type="text" class="form-control" name="project_name" id="bookingProjectField" required></div>
                                    <div class="col-md-6"><label class="form-label">Seat Label</label><select class="form-select" name="seat_label" id="bookingSeatField" required></select></div>
                                    <div class="col-md-6"><label class="form-label">Roll No.</label><input type="text" class="form-control" name="roll_no" id="bookingRollField"></div>
                                    <div class="col-12"><label class="form-label">Candidate Name</label><input type="text" class="form-control" name="candidate_name" id="bookingCandidateField"></div>
                                    <div class="col-12"><label class="form-label">Session Remarks</label><textarea class="form-control" name="session_remarks" id="bookingSessionRemarksField" rows="2" placeholder="Printed once at the bottom for the whole room list."></textarea></div>
                                    <div class="col-12"><label class="form-label">Candidate Notes</label><textarea class="form-control" name="booking_notes" id="bookingNotesField" rows="2"></textarea></div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-warning text-dark rounded-pill">Save Booking</button>
                                    <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="resetBookingForm()">Reset</button>
                                </div>
                            </form>
                        </div>
                        <div class="panel mt-4">
                            <div class="fw-bold text-primary mb-3"><i class="fas fa-file-csv me-2"></i>Import Seat Matrix from CSV</div>
                            <form method="POST" enctype="multipart/form-data" id="bookingCsvForm">
                                <input type="hidden" name="action" value="import_booking_csv">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Fixed Room</label>
                                        <select class="form-select" name="import_room_id" id="importRoomField" required>
                                            <?php foreach ($allRooms as $room): ?>
                                                <option value="<?= (int)$room['id'] ?>">Room <?= h($room['room_no']) ?> - <?= h($room['description']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Exam Date</label>
                                        <input type="date" class="form-control" name="import_exam_date" id="importExamDateField" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="import_booking_status" id="importStatusField" required>
                                            <option value="Reserved">Reserved</option>
                                            <option value="Confirmed">Confirmed</option>
                                            <option value="Present">Present</option>
                                            <option value="Vacant">Vacant</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Timing</label>
                                        <input type="time" class="form-control" name="import_exam_time" id="importTimeField">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">AM / PM</label>
                                        <select class="form-select" name="import_exam_ampm" id="importAmpmField">
                                            <option value="">Auto</option>
                                            <option value="AM">AM</option>
                                            <option value="PM">PM</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Shift</label>
                                        <select class="form-select" name="import_exam_shift" id="importShiftField">
                                            <option value="">Auto</option>
                                            <option value="Morning">Morning</option>
                                            <option value="Evening">Evening</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Project / Paper (Default)</label>
                                        <input type="text" class="form-control" name="import_project_name" id="importProjectField" placeholder="Optional fixed value; CSV project/paper column can override row-wise">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Session Remarks</label>
                                        <textarea class="form-control" name="import_session_remarks" id="importRemarksField" rows="2" placeholder="Printed once at bottom for this imported room list."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">CSV File</label>
                                        <input type="file" class="form-control" name="booking_csv" accept=".csv,text/csv" required>
                                    </div>
                                    <div class="col-12">
                                        <div class="small-muted">
                                            CSV columns supported:
                                            <strong>Seat Label</strong>, <strong>Roll No</strong>, <strong>Candidate Name</strong>, <strong>Notes</strong>, <strong>Project / Paper</strong>.
                                            Example seat labels: `CL1`, `CL2`, `CL3` ... `CL40`.
                                        </div>
                                        <div class="small-muted mt-1">
                                            Header names can be: `Seat Label`, `Roll No`, `Candidate Name`, `Notes`, `Project / Paper` or `Project Name` or `Paper Name`.
                                            Without headers, the import will read columns in this order:
                                            `Seat Label`, `Roll No`, `Candidate Name`, `Notes`, `Project / Paper`.
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-success rounded-pill"><i class="fas fa-upload me-2"></i>Import CSV</button>
                                    <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="syncBookingImportFields()">Use Values from Booking Form</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="panel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-primary">Room Seat Preview</div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="bookingPrintBtn"><i class="fas fa-print me-1"></i>Print Current Room</button>
                            </div>
                            <div class="small-muted mb-3" id="bookingSummaryText">Select room and date to preview seats.</div>
                            <div id="bookingSeatPreview" class="seat-scroll-wrap"></div>
                            <div class="panel mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold text-primary">Print Multiple Rooms on Same Exam Date</div>
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill" id="bookingPrintSelectedBtn"><i class="fas fa-print me-1"></i>Print Selected Rooms</button>
                                </div>
                                <div class="small-muted mb-2">Choose rooms running on the same exam date. The print layout is compressed to fit two room sheets vertically on one A4 page.</div>
                                <div id="sameDateRoomSelector" class="row g-2"></div>
                            </div>
                            <div class="table-responsive mt-4">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light"><tr><th>Seat</th><th>Roll No.</th><th>Candidate</th><th>Project</th></tr></thead>
                                    <tbody id="bookingPreviewRows"><tr><td colspan="4" class="text-center text-muted py-3">No room selected. </tbody>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Map variables
let adminMap, adminMarker;
const defaultLat = 28.631123;
const defaultLng = 77.071437;
const allRooms = <?= json_encode($allRooms, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allStaff = <?= json_encode($orgStaff, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allFacilities = <?= json_encode(array_values($facilities), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const facilityRecordsByFacility = <?= json_encode($facilityRecordsByFacility, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allBookings = <?= json_encode($bookings, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const hasSeatingPlan = <?= $hasSeatingPlanColumn ? 'true' : 'false' ?>;

let roomViewModal;
let roomEditorModal;
let facilityEditorModal;
let facilityViewerModal;
let facilityRecordModal;
let bookingModal;
let currentBlockedSeats = [];

function byId(id) {
    return document.getElementById(id);
}

function getRoomById(id) {
    return allRooms.find(room => String(room.id) === String(id));
}

function getRoomByNo(roomNo) {
    return allRooms.find(room => String(room.room_no) === String(roomNo));
}

function getFacilityById(id) {
    return allFacilities.find(facility => String(facility.id) === String(id));
}

function calculateSqFt() {
    const w = parseFloat(byId('roomWidthField').value) || 0;
    const l = parseFloat(byId('roomLengthField').value) || 0;
    if (w > 0 && l > 0) {
        byId('roomSqftField').value = (w * l).toFixed(2) + ' sq.ft';
    } else {
        byId('roomSqftField').value = '';
    }
}

// Export Data Function
function exportData(type) {
    const rows = document.querySelectorAll('#manageTable tbody tr');
    let data = [];
    const headers = ['Floor', 'Room', 'Description', 'Dimensions', 'Staff Assigned', 'Net', 'Board', 'Wi-Fi', 'CCTV', 'UPS', 'A/V', 'Remarks'];

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            let rowData = [];
            const cells = row.querySelectorAll('td');
            for (let i = 0; i < 12; i++) {
                if (cells[i]) {
                    rowData.push(cells[i].textContent.trim().replace(/\r?\n|\r/g, ' '));
                }
            }
            // Add remarks from the hidden column if needed
            const remarksCell = row.querySelector('.export-remarks');
            if (remarksCell) {
                rowData.push(remarksCell.textContent.trim());
            }
            if (rowData.length > 0) data.push(rowData);
        }
    });

    if (data.length === 0) {
        Swal.fire({icon: 'info', title: 'No data', text: 'No filtered data available to export!'});
        return;
    }

    const dateStr = new Date().toISOString().slice(0, 10);
    const fileName = `IGIPESS_Rooms_${dateStr}`;

    if (type === 'csv') {
        let csvContent = headers.join(',') + '\n';
        data.forEach(row => {
            csvContent += row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',') + '\n';
        });
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileName + '.csv';
        link.click();
        Swal.fire({icon: 'success', title: 'Exported', text: 'CSV file downloaded successfully!'});
    }
    else if (type === 'excel') {
        let html = '<table border="1"><tr><th style="background-color:#1e3c72; color:white;">' + headers.join('</th><th style="background-color:#1e3c72; color:white;">') + '</th></tr>';
        data.forEach(row => {
            html += '<tr><td>' + row.join('</td><td>') + '</td></tr>';
        });
        html += '</table>';
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileName + '.xls';
        link.click();
        Swal.fire({icon: 'success', title: 'Exported', text: 'Excel file downloaded successfully!'});
    }
    else if (type === 'pdf') {
        let html = `
            <div style="font-family: Arial, sans-serif; padding: 20px;">
                <h2 style="text-align:center; color:#1e3c72; margin-bottom: 5px;">IGIPESS Room & Hardware Export</h2>
                <p style="text-align:center; font-size: 12px; color: #555; margin-top: 0;">Export Date: ${dateStr}</p>
                <table style="width:100%; border-collapse: collapse; font-size: 10px;" border="1">
                    <tr style="background-color:#f2f2f2; text-align: left;">
                        <th style="padding:6px;">${headers.join('</th><th style="padding:6px;">')}</th>
                    </tr>`;
        data.forEach(row => {
            html += `<tr><td style="padding:5px;">${row.join('</td><td style="padding:5px;">')}</td></tr>`;
        });
        html += '</table></div>';

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        html2pdf().set({
            margin: 10,
            filename: fileName + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
        }).from(tempDiv).save().then(() => {
            Swal.fire({icon: 'success', title: 'Exported', text: 'PDF file downloaded successfully!'});
        });
    }
}

function toggleSeatBuilder() {
    if (!hasSeatingPlan) return;
    const type = byId('seatLayoutType').value;
    document.querySelectorAll('.seat-grid-input').forEach(el => el.classList.toggle('d-none', type !== 'grid'));
    document.querySelectorAll('.seat-open-input').forEach(el => el.classList.toggle('d-none', type !== 'open'));
    updateSeatCapacityPreview();
}

function generateEditableSeatGrid(existingBlocked = []) {
    if (!hasSeatingPlan) return;
    currentBlockedSeats = [...existingBlocked];
    const rows = parseInt(byId('seatRows').value || 0, 10);
    const cols = parseInt(byId('seatCols').value || 0, 10);
    const prefix = (byId('seatPrefix').value || 'S').trim();
    const wrap = byId('editableSeatMap');

    if (rows <= 0 || cols <= 0) {
        wrap.innerHTML = '<div class="text-danger">Enter valid rows and columns.</div>';
        updateSeatCapacityPreview();
        return;
    }

    let html = `<div class="seat-grid" style="grid-template-columns:repeat(${cols}, minmax(54px, 1fr));">`;
    html += `<div style="grid-column:1 / -1;"><div class="seat-desk">TEACHER / INVIGILATOR DESK</div></div>`;
    for (let r = 1; r <= rows; r++) {
        for (let c = 1; c <= cols; c++) {
            const seatId = `${r}-${c}`;
            const label = `${prefix}${((r - 1) * cols) + c}`;
            const blocked = currentBlockedSeats.includes(seatId);
            html += `<div class="seat-box seat-editable ${blocked ? 'seat-blocked' : 'seat-active'}" data-seat-id="${seatId}" onclick="toggleSeatBlock(this)">${label}</div>`;
        }
    }
    html += `</div>`;
    wrap.innerHTML = html;
    updateSeatCapacityPreview();
}

function toggleSeatBlock(el) {
    const seatId = el.getAttribute('data-seat-id');
    if (currentBlockedSeats.includes(seatId)) {
        currentBlockedSeats = currentBlockedSeats.filter(id => id !== seatId);
        el.classList.remove('seat-blocked');
        el.classList.add('seat-active');
    } else {
        currentBlockedSeats.push(seatId);
        el.classList.remove('seat-active');
        el.classList.add('seat-blocked');
    }
    updateSeatCapacityPreview();
}

function updateSeatCapacityPreview() {
    if (!hasSeatingPlan) return;
    let total = 0;
    const type = byId('seatLayoutType').value;
    if (type === 'grid') {
        total = (parseInt(byId('seatRows').value || 0, 10) * parseInt(byId('seatCols').value || 0, 10)) - currentBlockedSeats.length;
    } else if (type === 'open') {
        total = parseInt(byId('seatTotalOpen').value || 0, 10);
    }
    byId('seatCapacityPreview').innerText = total > 0 ? total : 0;
}

function finalizeRoomSeatPlan() {
    if (!hasSeatingPlan) return;
    const type = byId('seatLayoutType').value;
    let payload = '';
    if (type === 'grid') {
        payload = JSON.stringify({
            type: 'grid',
            r: parseInt(byId('seatRows').value || 0, 10),
            c: parseInt(byId('seatCols').value || 0, 10),
            p: (byId('seatPrefix').value || 'S').trim(),
            b: currentBlockedSeats
        });
    } else if (type === 'open') {
        payload = JSON.stringify({
            type: 'open',
            total: parseInt(byId('seatTotalOpen').value || 0, 10)
        });
    }
    byId('roomSeatingPlanField').value = payload;
}

function renderSeatPreview(room, containerId, examDate = '') {
    const wrap = byId(containerId);
    wrap.innerHTML = '';
    if (!room || !room.seating_plan) {
        wrap.innerHTML = '<div class="text-muted">No seating map configured.</div>';
        return;
    }

    let plan;
    try {
        plan = JSON.parse(room.seating_plan);
    } catch (e) {
        wrap.innerHTML = '<div class="text-danger">Invalid seating map.</div>';
        return;
    }

    const bookings = (room.bookings || []).filter(item => !examDate || item.exam_date === examDate);
    const bookedMap = {};
    bookings.forEach(item => {
        bookedMap[item.seat_label] = item;
    });

    if (plan.type === 'grid') {
        let html = `<div class="seat-grid" style="grid-template-columns:repeat(${plan.c}, minmax(54px, 1fr));">`;
        html += `<div style="grid-column:1 / -1;"><div class="seat-desk">TEACHER / INVIGILATOR DESK</div></div>`;
        for (let r = 1; r <= plan.r; r++) {
            for (let c = 1; c <= plan.c; c++) {
                const seatId = `${r}-${c}`;
                const label = `${plan.p || 'S'}${((r - 1) * plan.c) + c}`;
                const blocked = Array.isArray(plan.b) && plan.b.includes(seatId);
                const booking = bookedMap[label];
                const seatClass = blocked ? 'seat-blocked' : (booking ? 'seat-booked' : 'seat-open');
                html += `<div class="seat-box ${seatClass}">${label}${booking ? `<br><span style="font-size:0.62rem">${booking.roll_no || 'Booked'}</span>` : ''}</div>`;
            }
        }
        html += `</div>`;
        wrap.innerHTML = html;
        return;
    }

    if (plan.type === 'open') {
        let html = `<div class="seat-grid" style="grid-template-columns:repeat(6, minmax(54px, 1fr));">`;
        for (let i = 1; i <= parseInt(plan.total || 0, 10); i++) {
            const label = `S${i}`;
            const booking = bookedMap[label];
            html += `<div class="seat-box ${booking ? 'seat-booked' : 'seat-open'}">${label}${booking ? `<br><span style="font-size:0.62rem">${booking.roll_no || 'Booked'}</span>` : ''}</div>`;
        }
        html += `</div>`;
        wrap.innerHTML = html;
    }
}

function openViewModal(roomNo) {
    const room = getRoomByNo(roomNo);
    if (!room) return;

    byId('viewRoomNo').innerText = room.room_no;
    byId('viewRoomDesc').innerText = room.description || '';
    byId('viewRoomIcon').className = `fas ${room.icon || 'fa-door-open'} text-primary me-2`;
    byId('viewRoomImage').src = room.room_image ? `./images/${room.room_image}` : './images/1.jpg';
    byId('viewRoomDimensions').innerText = room.sqft ? `${room.width} ft x ${room.length} ft = ${room.sqft} sq.ft` : 'Not set';
    byId('viewRoomCapacity').innerText = room.total_capacity || 0;
    byId('viewRoomBookingCount').innerText = (room.bookings || []).length;
    byId('viewNet').innerText = room.networking || 'No';
    byId('viewBoard').innerText = room.interactive_board || 'No';
    byId('viewWifi').innerText = room.wifi_router || 'No';
    byId('viewCctv').innerText = room.cctv || 'No';
    byId('viewUps').innerText = room.ups || 'No';
    byId('viewAv').innerText = room.audio_video || 'No';
    byId('viewRemarks').innerText = room.remarks || 'No remarks added.';

    const docWrap = byId('viewDocumentsList');
    docWrap.innerHTML = '';
    if (room.documents && room.documents.length > 0) {
        room.documents.forEach(doc => {
            const docDiv = document.createElement('div');
            docDiv.className = 'doc-chip mb-2 w-100';
            docDiv.innerHTML = `<i class="fas fa-file-pdf"></i> ${doc.doc_year}: ${doc.doc_title}<br><small class="text-muted">${doc.doc_description || ''}</small><br><a href="./uploads/room_documents/${doc.doc_file}" target="_blank" class="small">View PDF <i class="fas fa-external-link-alt"></i></a>`;
            docWrap.appendChild(docDiv);
        });
    } else {
        docWrap.innerHTML = '<span class="text-muted">No documents uploaded.</span>';
    }

    const facilityWrap = byId('viewFacilityWrap');
    facilityWrap.innerHTML = '';
    if ((room.linked_facilities || []).length > 0) {
        room.linked_facilities.forEach(facility => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'facility-chip border-0';
            btn.innerHTML = `<i class="fas ${facility.icon}"></i>${facility.name}`;
            btn.onclick = () => openFacilityViewer(facility.id);
            facilityWrap.appendChild(btn);
        });
    } else {
        facilityWrap.innerHTML = '<span class="text-muted">No facility linked.</span>';
    }

    const membersWrap = byId('viewMembersWrap');
    membersWrap.innerHTML = '';
    if ((room.member_ids || []).length > 0) {
        room.member_ids.forEach(memberId => {
            const staff = allStaff.find(item => String(item.id) === String(memberId));
            if (!staff) return;
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `<div class="panel py-2 px-3 ${String(room.in_charge_id) === String(memberId) ? 'border border-danger' : ''}">
                <div class="fw-semibold">${staff.name} ${String(room.in_charge_id) === String(memberId) ? '<span class="badge text-bg-danger ms-1">In-Charge</span>' : ''}</div>
                <div class="small-muted">${staff.role}</div>
            </div>`;
            membersWrap.appendChild(col);
        });
    } else {
        membersWrap.innerHTML = '<div class="col-12 text-muted">No personnel assigned.</div>';
    }

    renderSeatPreview(room, 'viewSeatMap');
    byId('viewManageSeatsBtn').onclick = () => openBookingEditor(room.id);
    byId('viewPrintPlanBtn').onclick = () => printSeatingPlan(room.id);
    roomViewModal.show();
}

function openRoomEditor(mode, room = null) {
    byId('roomEditorForm').reset();
    byId('roomEditorTitle').innerText = mode === 'edit' ? 'Edit Room' : 'Add Room';
    byId('roomIdField').value = room ? room.id : '';
    byId('roomOldImageField').value = room ? (room.room_image || '') : '';
    byId('roomPreviewImage').src = room && room.room_image ? `./images/${room.room_image}` : './images/1.jpg';

    byId('roomOldDocField').value = '';
    byId('roomDocYearField').value = '';
    byId('roomDocTitleField').value = '';
    byId('roomDocDescField').value = '';
    byId('roomCurrentDocInfo').innerHTML = '';

    document.querySelectorAll('.room-facility-checkbox, .room-member-check, .room-incharge-radio').forEach(el => el.checked = false);
    currentBlockedSeats = [];

    if (room) {
        byId('roomFloorField').value = room.floor || 'Ground Floor';
        byId('roomNoField').value = room.room_no || '';
        byId('roomDescField').value = room.description || '';
        byId('roomIconField').value = room.icon || 'fa-door-open';
        byId('roomWidthField').value = room.width || '';
        byId('roomLengthField').value = room.length || '';
        byId('roomLatField').value = room.latitude || '';
        byId('roomLngField').value = room.longitude || '';
        byId('roomNetField').value = room.networking || '';
        byId('roomBoardField').value = room.interactive_board || '';
        byId('roomWifiField').value = room.wifi_router || '';
        byId('roomCctvField').value = room.cctv || '';
        byId('roomUpsField').value = room.ups || '';
        byId('roomAvField').value = room.audio_video || '';
        byId('roomRemarksField').value = room.remarks || '';

        if (room.documents && room.documents.length > 0) {
            const existingDoc = room.documents[0];
            byId('roomDocYearField').value = existingDoc.doc_year || '';
            byId('roomDocTitleField').value = existingDoc.doc_title || '';
            byId('roomDocDescField').value = existingDoc.doc_description || '';
            byId('roomOldDocField').value = existingDoc.doc_file || '';
            byId('roomCurrentDocInfo').innerHTML = `<i class="fas fa-file-pdf text-danger"></i> Current: ${existingDoc.doc_year} - ${existingDoc.doc_title}<br><a href="./uploads/room_documents/${existingDoc.doc_file}" target="_blank">View PDF</a>`;
        }

        (room.linked_facility_ids || []).forEach(id => {
            const checkbox = byId('facility_link_' + id);
            if (checkbox) checkbox.checked = true;
        });
        (room.member_ids || []).forEach(id => {
            const checkbox = byId('staff_' + id);
            if (checkbox) checkbox.checked = true;
        });
        if (room.in_charge_id) {
            const radio = byId('incharge_' + room.in_charge_id);
            if (radio) radio.checked = true;
        }
        if (hasSeatingPlan && room.seating_plan) {
            try {
                const plan = JSON.parse(room.seating_plan);
                byId('seatLayoutType').value = plan.type || 'none';
                if (plan.type === 'grid') {
                    byId('seatRows').value = plan.r || 5;
                    byId('seatCols').value = plan.c || 5;
                    byId('seatPrefix').value = plan.p || 'S';
                    generateEditableSeatGrid(Array.isArray(plan.b) ? plan.b : []);
                } else if (plan.type === 'open') {
                    byId('seatTotalOpen').value = plan.total || '';
                }
            } catch (e) {}
        } else if (hasSeatingPlan) {
            byId('seatLayoutType').value = 'none';
            byId('editableSeatMap').innerHTML = '<div class="text-muted">Seat map not generated yet.</div>';
        }
    } else if (hasSeatingPlan) {
        byId('seatLayoutType').value = 'none';
        byId('seatRows').value = '5';
        byId('seatCols').value = '5';
        byId('seatPrefix').value = 'S';
        byId('seatTotalOpen').value = '';
        byId('editableSeatMap').innerHTML = '<div class="text-muted">Seat map not generated yet.</div>';
    }

    calculateSqFt();
    toggleSeatBuilder();
    roomEditorModal.show();
}

function openFacilityEditor(mode, facility = null) {
    byId('facilityEditorTitle').innerText = mode === 'edit' ? 'Edit Facility' : 'Add Facility';
    byId('facilityIdField').value = facility ? facility.id : '';
    byId('facilityOldCoverField').value = facility ? (facility.cover_image || '') : '';
    byId('facilityNameField').value = facility ? (facility.name || '') : '';
    byId('facilityCategoryField').value = facility ? (facility.category || 'Laboratories') : 'Laboratories';
    byId('facilityIconField').value = facility ? (facility.icon || 'fa-building') : 'fa-building';
    byId('facilityStatusField').value = facility ? (facility.status || 'Active') : 'Active';
    byId('facilityCapacityField').value = facility ? (facility.capacity_label || '') : '';
    byId('facilityContactPersonField').value = facility ? (facility.contact_person || '') : '';
    byId('facilityContactPhoneField').value = facility ? (facility.contact_phone || '') : '';
    byId('facilityLocationField').value = facility ? (facility.location_note || '') : '';
    byId('facilityOverviewField').value = facility ? (facility.overview || '') : '';
    byId('facilityPreviewImage').src = facility && facility.cover_image ? `./uploads/facilities/covers/${facility.cover_image}` : './images/1.jpg';
    facilityEditorModal.show();
}

function openFacilityViewer(facilityId) {
    const facility = getFacilityById(facilityId);
    if (!facility) return;

    byId('facilityViewName').innerText = facility.name || '';
    byId('facilityViewCategory').innerText = facility.category || '';
    byId('facilityViewIcon').className = `fas ${facility.icon || 'fa-building'} text-success me-2`;
    byId('facilityViewImage').src = facility.cover_image ? `./uploads/facilities/covers/${facility.cover_image}` : './images/1.jpg';
    byId('facilityViewStatus').innerText = facility.status || 'Active';
    byId('facilityViewContact').innerText = [facility.contact_person, facility.contact_phone].filter(Boolean).join(' | ') || 'No contact details';
    byId('facilityViewLocation').innerText = facility.location_note || 'No location note';
    byId('facilityViewOverview').innerText = facility.overview || 'No overview available.';

    const linkedRooms = allRooms.filter(room => (room.linked_facility_ids || []).map(String).includes(String(facilityId)));
    const roomWrap = byId('facilityViewRooms');
    roomWrap.innerHTML = '';
    if (linkedRooms.length > 0) {
        linkedRooms.forEach(room => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'facility-chip border-0';
            btn.textContent = `Room ${room.room_no}`;
            btn.onclick = () => {
                facilityViewerModal.hide();
                openViewModal(room.room_no);
            };
            roomWrap.appendChild(btn);
        });
    } else {
        roomWrap.innerHTML = '<span class="text-muted">No linked rooms.</span>';
    }

    byId('facilityAddRecordBtn').onclick = () => openFacilityRecordEditor('add', { facility_id: facilityId });
    const recordWrap = byId('facilityRecordList');
    recordWrap.innerHTML = '';
    const records = facilityRecordsByFacility[String(facilityId)] || facilityRecordsByFacility[facilityId] || [];
    if (records.length === 0) {
        recordWrap.innerHTML = '<div class="col-12"><div class="alert alert-light border">No year-wise records found.</div></div>';
    } else {
        records.forEach(record => {
            const photos = Array.isArray(record.photos) ? record.photos : [];
            const photoHtml = photos.map(photo => `<img src="./uploads/facilities/photos/${photo}" class="photo-thumb" onerror="this.src='./images/1.jpg'">`).join('');
            const card = document.createElement('div');
            card.className = 'col-md-6';
            card.innerHTML = `
                <div class="panel h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div>
                            <div class="fw-bold text-primary">${record.record_title}</div>
                            <div class="small-muted">${record.record_year} | ${record.status}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                            <form method="POST" class="confirm-form d-inline" data-confirm-title="Delete year record?" data-confirm-text="This facility record card will be removed.">
                                <input type="hidden" name="action" value="delete_facility_record">
                                <input type="hidden" name="record_id" value="${record.id}">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div class="small mb-2"><strong>Equipment Count:</strong> ${record.equipment_count || '-'}</div>
                    <div class="small-muted mb-3">${(record.item_summary || '').replace(/\n/g, '<br>')}</div>
                    <div class="d-flex flex-wrap gap-2 mb-3">${photoHtml || '<span class="text-muted">No photos</span>'}</div>
                    <div>${record.register_pdf ? `<a href="./uploads/facilities/registers/${record.register_pdf}" target="_blank" class="btn btn-outline-danger btn-sm rounded-pill"><i class="fas fa-file-pdf me-1"></i>Open PDF</a>` : '<span class="text-muted">No PDF uploaded</span>'}</div>
                </div>`;
            card.querySelector('.btn-warning').onclick = () => openFacilityRecordEditor('edit', record);
            recordWrap.appendChild(card);
        });
    }

    bindConfirmForms(recordWrap);
    facilityViewerModal.show();
}

function openFacilityRecordEditor(mode, record = null) {
    byId('facilityRecordTitle').innerText = mode === 'edit' ? 'Edit Facility Record' : 'Add Facility Record';
    byId('facilityRecordIdField').value = record && record.id ? record.id : '';
    byId('facilityRecordFacilityIdField').value = record && record.facility_id ? record.facility_id : '';
    byId('facilityRecordYearField').value = record && record.record_year ? record.record_year : '';
    byId('facilityRecordTitleField').value = record && record.record_title ? record.record_title : '';
    byId('facilityRecordCountField').value = record && record.equipment_count ? record.equipment_count : '';
    byId('facilityRecordStatusField').value = record && record.status ? record.status : 'Active';
    byId('facilityRecordSummaryField').value = record && record.item_summary ? record.item_summary : '';
    byId('facilityRecordOldPdfField').value = record && record.register_pdf ? record.register_pdf : '';
    const photos = record && Array.isArray(record.photos) ? record.photos : [];
    byId('facilityRecordExistingPhotosField').value = JSON.stringify(photos);
    byId('facilityRecordPhotoPreview').innerHTML = photos.map(photo => `<img src="./uploads/facilities/photos/${photo}" class="photo-thumb" onerror="this.src='./images/1.jpg'">`).join('');
    facilityRecordModal.show();
}

function refreshBookingSeatOptions() {
    const roomId = byId('bookingRoomField').value;
    const examDate = byId('bookingDateField').value;
    const room = getRoomById(roomId);
    const seatSelect = byId('bookingSeatField');
    seatSelect.innerHTML = '';

    if (!room) {
        byId('bookingSummaryText').innerText = 'Select room and date to preview seats.';
        byId('bookingSeatPreview').innerHTML = '';
        byId('bookingPreviewRows').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No room selected. </tbody>';
        return;
    }

    const editId = byId('bookingIdField').value;
    const currentBooking = allBookings.find(item => String(item.id) === String(editId));
    const bookings = (room.bookings || []).filter(item => !examDate || item.exam_date === examDate);
    const occupied = bookings.map(item => item.seat_label);

    (room.seat_labels || []).forEach(label => {
        const opt = document.createElement('option');
        const locked = occupied.includes(label) && (!currentBooking || currentBooking.seat_label !== label);
        opt.value = label;
        opt.textContent = locked ? `${label} (Booked)` : label;
        opt.disabled = locked;
        seatSelect.appendChild(opt);
    });

    if (!seatSelect.options.length) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No valid seats available';
        seatSelect.appendChild(opt);
    }

    byId('bookingSummaryText').innerText = `Room ${room.room_no} | ${room.total_capacity} seats configured | ${bookings.length} bookings for selected date`;
    renderSeatPreview(room, 'bookingSeatPreview', examDate);
    byId('bookingPreviewRows').innerHTML = bookings.length ? bookings.map(item => `<tr><td>${item.seat_label}</td><td>${item.roll_no || ''}</td><td>${item.candidate_name || ''}</td><td>${item.project_name || ''}</td>`).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">No bookings for selected date. </tbody>';
    renderSameDateRoomSelector(examDate);
}

function renderSameDateRoomSelector(examDate) {
    const wrap = byId('sameDateRoomSelector');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (!examDate) {
        wrap.innerHTML = '<div class="col-12 text-muted small">Select an exam date to list rooms available for same-date printing.</div>';
        return;
    }

    const projectName = byId('bookingProjectField').value || '';
    const map = new Map();
    allBookings.forEach(item => {
        if (item.exam_date !== examDate) return;
        if (projectName && item.project_name !== projectName) return;
        const room = getRoomById(item.room_id);
        if (!room) return;
        if (!map.has(String(room.id))) {
            map.set(String(room.id), {
                roomId: room.id,
                roomNo: room.room_no,
                description: room.description,
                count: 0
            });
        }
        map.get(String(room.id)).count++;
    });

    if (map.size === 0) {
        wrap.innerHTML = '<div class="col-12 text-muted small">No rooms found for the selected exam date.</div>';
        return;
    }

    const currentRoomId = byId('bookingRoomField').value;
    Array.from(map.values()).sort((a, b) => String(a.roomNo).localeCompare(String(b.roomNo), undefined, {numeric: true})).forEach(item => {
        const col = document.createElement('div');
        col.className = 'col-md-6';
        col.innerHTML = `
            <label class="border rounded-3 p-2 d-flex align-items-start gap-2 w-100 bg-light">
                <input class="form-check-input mt-1 same-date-room-check" type="checkbox" value="${item.roomId}" ${String(item.roomId) === String(currentRoomId) ? 'checked' : ''}>
                <span>
                    <span class="fw-semibold text-primary">Room ${item.roomNo}</span><br>
                    <span class="small text-muted">${item.description || ''}</span><br>
                    <span class="small text-muted">${item.count} candidate rows</span>
                </span>
            </label>`;
        wrap.appendChild(col);
    });
}

function resetBookingForm() {
    byId('bookingForm').reset();
    byId('bookingIdField').value = '';
    refreshBookingSeatOptions();
}

function syncBookingImportFields() {
    byId('importRoomField').value = byId('bookingRoomField').value || byId('importRoomField').value;
    byId('importExamDateField').value = byId('bookingDateField').value || byId('importExamDateField').value;
    byId('importStatusField').value = byId('bookingStatusField').value || byId('importStatusField').value;
    byId('importTimeField').value = byId('bookingTimeField').value || byId('importTimeField').value;
    byId('importAmpmField').value = byId('bookingAmpmField').value || byId('importAmpmField').value;
    byId('importShiftField').value = byId('bookingShiftField').value || byId('importShiftField').value;
    byId('importProjectField').value = byId('bookingProjectField').value || byId('importProjectField').value;
    byId('importRemarksField').value = byId('bookingSessionRemarksField').value || byId('importRemarksField').value;
}

function suggestShift(timeValue, ampmValue) {
    if (ampmValue === 'AM') return 'Morning';
    if (ampmValue === 'PM') return 'Evening';
    if (!timeValue) return '';
    const hour = parseInt(String(timeValue).split(':')[0] || '0', 10);
    return hour < 12 ? 'Morning' : 'Evening';
}

function syncShiftFromTime(prefix) {
    const timeEl = byId(prefix + 'TimeField');
    const ampmEl = byId(prefix + 'AmpmField');
    const shiftEl = byId(prefix + 'ShiftField');
    if (!timeEl || !ampmEl || !shiftEl) return;

    if (!ampmEl.value && timeEl.value) {
        const hour = parseInt(String(timeEl.value).split(':')[0] || '0', 10);
        ampmEl.value = hour < 12 ? 'AM' : 'PM';
    }
    shiftEl.value = suggestShift(timeEl.value, ampmEl.value);
}

function openBookingEditor(roomId = '', booking = null) {
    resetBookingForm();
    if (roomId) byId('bookingRoomField').value = roomId;
    if (booking) {
        byId('bookingIdField').value = booking.id || '';
        byId('bookingRoomField').value = booking.room_id || '';
        byId('bookingDateField').value = booking.exam_date || '';
        byId('bookingTimeField').value = booking.exam_time || '';
        byId('bookingAmpmField').value = booking.exam_ampm || '';
        byId('bookingShiftField').value = booking.exam_shift || '';
        byId('bookingProjectField').value = booking.project_name || '';
        byId('bookingRollField').value = booking.roll_no || '';
        byId('bookingCandidateField').value = booking.candidate_name || '';
        byId('bookingStatusField').value = booking.booking_status || 'Reserved';
        byId('bookingSessionRemarksField').value = booking.session_remarks || '';
        byId('bookingNotesField').value = booking.notes || '';
    }
    syncShiftFromTime('booking');
    refreshBookingSeatOptions();
    if (booking) byId('bookingSeatField').value = booking.seat_label || '';
    syncBookingImportFields();
    byId('bookingPrintBtn').onclick = () => {
        if (byId('bookingRoomField').value) {
            printSeatingPlan(byId('bookingRoomField').value, byId('bookingDateField').value || '', byId('bookingProjectField').value || '');
        }
    };
    byId('bookingPrintSelectedBtn').onclick = () => {
        printSelectedRoomsForDate();
    };
    bookingModal.show();
}

function generatePrintableSeatMap(room, examDate, projectName = '') {
    if (!room || !room.seating_plan) return '<div>No seating plan configured.</div>';
    let plan;
    try {
        plan = JSON.parse(room.seating_plan);
    } catch (e) {
        return '<div>Invalid seating plan.</div>';
    }

    const bookings = (room.bookings || []).filter(item => (!examDate || item.exam_date === examDate) && (!projectName || item.project_name === projectName));
    const bookedMap = {};
    bookings.forEach(item => bookedMap[item.seat_label] = item);

    if (plan.type === 'grid') {
        const rows = plan.r;
        const cols = plan.c;
        const blockedSeats = Array.isArray(plan.b) ? plan.b : [];

        let seatMatrix = [];
        let seatNumber = 1;

        for (let c = 1; c <= cols; c++) {
            for (let r = 1; r <= rows; r++) {
                const seatId = `${r}-${c}`;
                const label = `${plan.p || 'S'}${((r - 1) * cols) + c}`;
                const isBlocked = blockedSeats.includes(seatId);
                seatMatrix.push({
                    row: r,
                    col: c,
                    seatId: seatId,
                    label: label,
                    number: isBlocked ? null : seatNumber++,
                    isBlocked: isBlocked
                });
            }
        }

        let html = '<table class="candidate-table" style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
        html += '<thead>';
        html += '<tr style="background: #f0f0f0;">';
        for (let c = 1; c <= cols; c++) {
            const startNum = (c - 1) * rows + 1;
            const endNum = c * rows;
            html += `<th style="border:1px solid #000; padding:8px; text-align:center; font-size:11px;">Seats ${startNum}-${endNum}</th>`;
        }
        html += '</tr>';
        html += '<tr style="background: #e0e0e0;">';
        for (let c = 1; c <= cols; c++) {
            html += `<th style="border:1px solid #000; padding:6px; text-align:center; font-size:10px;">Roll No. | Name | Paper | Signature</th>`;
        }
        html += '</table></thead><tbody>';

        for (let r = 1; r <= rows; r++) {
            html += '<tr>';
            for (let c = 1; c <= cols; c++) {
                const seat = seatMatrix.find(s => s.row === r && s.col === c);
                if (seat && !seat.isBlocked) {
                    const booking = bookedMap[seat.label];
                    const rollNo = booking ? (booking.roll_no || '') : '';
                    const candidateName = booking ? (booking.candidate_name || '') : '';
                    const paperName = booking ? (booking.project_name || '') : '';
                    html += `<td style="border:1px solid #000; padding:8px; vertical-align:top;">
                        <div style="font-weight:bold; margin-bottom:5px; text-align:center; border-bottom:1px solid #ccc; padding-bottom:3px;">
                            Seat ${seat.label} <span style="font-size:9px; color:#666;">(#${seat.number})</span>
                        </div>
                        <div style="font-size:10px; margin-bottom:3px;"><strong>Roll No.:</strong> ${rollNo || '___________'}</div>
                        <div style="font-size:10px; margin-bottom:3px;"><strong>Name:</strong> ${candidateName || '_________________'}</div>
                        <div style="font-size:10px; margin-bottom:5px;"><strong>Paper:</strong> ${paperName || '_________________'}</div>
                        <div style="margin-top:8px; border-top:1px dashed #999; padding-top:5px; font-size:9px; text-align:center;">Signature: _________________</div>
                     </div>`;
                } else if (seat && seat.isBlocked) {
                    html += `<td style="border:1px solid #000; padding:8px; background:#fde1e1; text-align:center; vertical-align:middle;">
                        <div style="font-weight:bold;">${seat.label}</div>
                        <div style="color:#a61f2a; font-size:11px;">BLOCKED</div>
                     </div>`;
                } else {
                    html += `<td style="border:1px solid #000; padding:8px; text-align:center; vertical-align:middle; background:#f9f9f9;">
                        <span style="color:#999;">Empty</span>
                     </div>`;
                }
            }
            html += '</tr>';
        }
        html += '</tbody></table>';
        html += '<div style="margin-top: 15px; padding: 8px; background: #556372; color: #fff; text-align: center; border-radius: 5px; font-size: 11px;">TEACHER / INVIGILATOR DESK</div>';
        return html;
    }

    if (plan.type === 'open') {
        const totalSeats = parseInt(plan.total || 0, 10);
        const rows = 5;
        const cols = Math.ceil(totalSeats / rows);
        let seatMatrix = [];
        let seatNumber = 1;

        for (let c = 1; c <= cols; c++) {
            for (let r = 1; r <= rows; r++) {
                const seatIndex = (c - 1) * rows + r;
                if (seatIndex <= totalSeats) {
                    const label = `S${seatIndex}`;
                    seatMatrix.push({ row: r, col: c, label: label, number: seatNumber++, isBlocked: false });
                }
            }
        }

        let html = '<table class="candidate-table" style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
        html += '<thead>';
        html += '<tr style="background: #f0f0f0;">';
        for (let c = 1; c <= cols; c++) {
            const startNum = (c - 1) * rows + 1;
            const endNum = Math.min(c * rows, totalSeats);
            html += `<th style="border:1px solid #000; padding:8px; text-align:center; font-size:11px;">Seats ${startNum}-${endNum}</th>`;
        }
        html += '</tr>';
        html += '<tr style="background: #e0e0e0;">';
        for (let c = 1; c <= cols; c++) {
            html += `<th style="border:1px solid #000; padding:6px; text-align:center; font-size:10px;">Roll No. | Name | Paper | Signature</th>`;
        }
        html += '<tr></thead><tbody>';

        for (let r = 1; r <= rows; r++) {
            html += '<tr>';
            for (let c = 1; c <= cols; c++) {
                const seat = seatMatrix.find(s => s.row === r && s.col === c);
                if (seat && seat.number <= totalSeats) {
                    const booking = bookedMap[seat.label];
                    const rollNo = booking ? (booking.roll_no || '') : '';
                    const candidateName = booking ? (booking.candidate_name || '') : '';
                    const paperName = booking ? (booking.project_name || '') : '';
                    html += `<td style="border:1px solid #000; padding:8px; vertical-align:top;">
                        <div style="font-weight:bold; margin-bottom:5px; text-align:center; border-bottom:1px solid #ccc; padding-bottom:3px;">
                            Seat ${seat.label} <span style="font-size:9px; color:#666;">(#${seat.number})</span>
                        </div>
                        <div style="font-size:10px; margin-bottom:3px;"><strong>Roll No.:</strong> ${rollNo || '___________'}</div>
                        <div style="font-size:10px; margin-bottom:3px;"><strong>Name:</strong> ${candidateName || '_________________'}</div>
                        <div style="font-size:10px; margin-bottom:5px;"><strong>Paper:</strong> ${paperName || '_________________'}</div>
                        <div style="margin-top:8px; border-top:1px dashed #999; padding-top:5px; font-size:9px; text-align:center;">Signature: _________________</div>
                     </div>`;
                } else {
                    html += `<td style="border:1px solid #000; padding:8px; text-align:center; vertical-align:middle; background:#f9f9f9;">
                        <span style="color:#999;">Empty</span>
                     </div>`;
                }
            }
            html += '</tr>';
        }
        html += '</tbody></table>';
        html += '<div style="margin-top: 15px; padding: 8px; background: #556372; color: #fff; text-align: center; border-radius: 5px; font-size: 11px;">TEACHER / INVIGILATOR DESK</div>';
        return html;
    }
    return '<div>No seating plan configured.</div>';
}

function printSeatingPlan(roomId, examDate = '', projectName = '') {
    const room = getRoomById(roomId);
    if (!room) return;

    const bookings = (room.bookings || []).filter(item => (!examDate || item.exam_date === examDate) && (!projectName || item.project_name === projectName));
    const paperName = projectName || (bookings[0] ? bookings[0].project_name || '' : '');
    const sessionRemarks = bookings.find(item => item.session_remarks && item.session_remarks.trim() !== '');
    const remarksText = sessionRemarks ? sessionRemarks.session_remarks : '';
    const timeText = bookings[0] ? [bookings[0].exam_time || '', bookings[0].exam_ampm || '', bookings[0].exam_shift || ''].filter(Boolean).join(' | ') : '';
    let qrLink = '';
    if (room.latitude && room.longitude) {
        qrLink = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(room.latitude + ',' + room.longitude)}`;
    }
    const qrImg = qrLink ? `https://chart.googleapis.com/chart?chs=80x80&cht=qr&chl=${encodeURIComponent(qrLink)}` : '';
    const popup = window.open('', '_blank', 'width=1200,height=900');
    popup.document.open();
    popup.document.write(`
        <html>
        <head>
            <title>Room ${room.room_no} Seating Plan</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:Arial,sans-serif;padding:10px;color:#1f2937}
                .candidate-table{border-collapse:collapse;width:100%;margin-bottom:15px;}
                .candidate-table th,.candidate-table td{border:1px solid #000;}
                .candidate-table th{background:#f0f0f0;padding:8px;}
                .room-header{margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:10px;}
                .remarks{margin-top:15px;padding:8px;background:#f5f5f5;border-radius:5px;font-size:10px;}
                @page{size:A4 landscape;margin:8mm}
                @media print{body{padding:0}.candidate-table{break-inside:avoid;}}
            </style>
        </head>
        <body>
            <div class="room-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:15px;">
                <div>
                    <h2 style="font-size:16px;margin-bottom:4px;">IGIPESS Examination Seating Plan</h2>
                    <h3 style="font-size:14px;margin-bottom:4px;">Room ${room.room_no} - ${room.description || ''}</h3>
                    <div style="font-size:10px;margin-top:5px;">
                        <strong>Paper:</strong> ${paperName || '-'}<br>
                        <strong>Floor:</strong> ${room.floor || ''}<br>
                        <strong>Total Seats:</strong> ${room.total_capacity || 0}<br>
                        ${examDate ? '<strong>Exam Date:</strong> ' + examDate + '<br>' : ''}
                        ${timeText ? '<strong>Time/Shift:</strong> ' + timeText : ''}
                    </div>
                </div>
                ${qrImg ? `<div style="text-align:center"><img src="${qrImg}" width="80" height="80"><div style="font-size:9px;margin-top:4px;">Location QR</div></div>` : ''}
            </div>
            <div id="printSeatMap"></div>
            ${remarksText ? `<div class="remarks"><strong>Remarks:</strong> ${remarksText}</div>` : ''}
            <div style="margin-top:20px; text-align:right; font-size:9px;">
                _________________________<br>
                Invigilator Signature
            </div>
        </body>
        </html>
    `);
    popup.document.close();
    setTimeout(() => {
        const holder = popup.document.getElementById('printSeatMap');
        if (holder) holder.innerHTML = generatePrintableSeatMap(room, examDate, projectName);
        popup.focus();
        popup.print();
    }, 250);
}

function printSelectedRoomsForDate() {
    const examDate = byId('bookingDateField').value || '';
    const projectName = byId('bookingProjectField').value || '';
    const selectedIds = Array.from(document.querySelectorAll('.same-date-room-check:checked')).map(el => el.value);
    if (!examDate) {
        Swal.fire({icon: 'info', title: 'Select exam date', text: 'Choose an exam date first to print room layouts.'});
        return;
    }
    if (selectedIds.length === 0) {
        Swal.fire({icon: 'info', title: 'Select rooms', text: 'Choose at least one room from the same-date print list.'});
        return;
    }

    const rooms = selectedIds.map(getRoomById).filter(Boolean);
    const popup = window.open('', '_blank', 'width=1200,height=900');
    const sections = rooms.map(room => {
        const bookings = (room.bookings || []).filter(item => (!examDate || item.exam_date === examDate) && (!projectName || item.project_name === projectName));
        const paperName = projectName || (bookings[0] ? bookings[0].project_name || '' : '');
        const sessionRemarks = bookings.find(item => item.session_remarks && item.session_remarks.trim() !== '');
        const remarksText = sessionRemarks ? sessionRemarks.session_remarks : '';
        const timeText = bookings[0] ? [bookings[0].exam_time || '', bookings[0].exam_ampm || '', bookings[0].exam_shift || ''].filter(Boolean).join(' | ') : '';
        let qrLink = '';
        if (room.latitude && room.longitude) {
            qrLink = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(room.latitude + ',' + room.longitude)}`;
        }
        const qrImg = qrLink ? `https://chart.googleapis.com/chart?chs=70x70&cht=qr&chl=${encodeURIComponent(qrLink)}` : '';
        return `
            <section class="room-sheet" style="page-break-after:always; break-inside:avoid; margin-bottom:15px;">
                <div class="room-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:12px;border-bottom:1px solid #ccc;padding-bottom:8px;">
                    <div>
                        <h2 style="font-size:13px;margin:0 0 3px;">Room ${room.room_no} - ${room.description || ''}</h2>
                        <div style="font-size:9px;">
                            <strong>Paper:</strong> ${paperName || '-'}<br>
                            <strong>Date:</strong> ${examDate || '-'}${timeText ? ' | ' + timeText : ''}<br>
                            <strong>Floor:</strong> ${room.floor || ''} | <strong>Seats:</strong> ${room.total_capacity || 0}
                        </div>
                    </div>
                    ${qrImg ? `<div style="text-align:center"><img src="${qrImg}" width="55" height="55"><div style="font-size:7px;">Location QR</div></div>` : ''}
                </div>
                <div class="seat-map-container">${generatePrintableSeatMap(room, examDate, projectName)}</div>
                ${remarksText ? `<div style="margin-top:10px;padding:6px;background:#f5f5f5;font-size:8px;"><strong>Remarks:</strong> ${remarksText}</div>` : ''}
                <div style="margin-top:10px; text-align:right; font-size:8px; border-top:1px dashed #ccc; padding-top:6px;">
                    Invigilator Signature: _________________________
                </div>
            </section>`;
    }).join('');

    popup.document.open();
    popup.document.write(`
        <html>
        <head>
            <title>IGIPESS Multi-Room Seating Print</title>
            <style>
                @page { size: A4 landscape; margin: 8mm; }
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:Arial,sans-serif;color:#1f2937;background:#fff}
                .room-sheet{page-break-after:always;break-inside:avoid;margin-bottom:10px;}
                .room-sheet:last-child{page-break-after:auto}
                .candidate-table{border-collapse:collapse;width:100%;margin-bottom:12px;}
                .candidate-table th,.candidate-table td{border:1px solid #000;}
                .candidate-table th{background:#f0f0f0;padding:6px;}
                @media print { body{margin:0;padding:0} .room-sheet{page-break-after:always;break-inside:avoid} .room-sheet:last-child{page-break-after:auto} }
            </style>
        </head>
        <body>${sections}</body>
        </html>
    `);
    popup.document.close();
    setTimeout(() => { popup.focus(); popup.print(); }, 300);
}

function bindConfirmForms(scope = document) {
    scope.querySelectorAll('.confirm-form').forEach(form => {
        if (form.dataset.bound === 'yes') return;
        form.dataset.bound = 'yes';
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || 'This action cannot be undone.',
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    roomViewModal = new bootstrap.Modal(byId('roomViewModal'));
    roomEditorModal = new bootstrap.Modal(byId('roomEditorModal'));
    facilityEditorModal = new bootstrap.Modal(byId('facilityEditorModal'));
    facilityViewerModal = new bootstrap.Modal(byId('facilityViewerModal'));
    facilityRecordModal = new bootstrap.Modal(byId('facilityRecordModal'));
    bookingModal = new bootstrap.Modal(byId('bookingModal'));

    bindConfirmForms();

    byId('roomWidthField').addEventListener('input', calculateSqFt);
    byId('roomLengthField').addEventListener('input', calculateSqFt);

    function updateAdminPin() {
        if (adminMap) {
            const lat = parseFloat(byId('roomLatField').value) || defaultLat;
            const lng = parseFloat(byId('roomLngField').value) || defaultLng;
            if (!isNaN(lat) && !isNaN(lng)) {
                adminMap.setView([lat, lng], 18);
                adminMarker.setLatLng([lat, lng]);
            }
        }
    }

    byId('roomLatField').addEventListener('change', updateAdminPin);
    byId('roomLngField').addEventListener('change', updateAdminPin);

    byId('roomEditorModal').addEventListener('shown.bs.modal', function () {
        setTimeout(function() {
            let lat = parseFloat(byId('roomLatField').value) || defaultLat;
            let lng = parseFloat(byId('roomLngField').value) || defaultLng;
            if (!adminMap) {
                adminMap = L.map('adminMap').setView([lat, lng], 18);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
                    subdomains: 'abcd',
                    maxZoom: 19
                }).addTo(adminMap);
                adminMarker = L.marker([lat, lng], { draggable: true }).addTo(adminMap);
                adminMap.on('click', function(e) {
                    adminMarker.setLatLng(e.latlng);
                    byId('roomLatField').value = e.latlng.lat.toFixed(6);
                    byId('roomLngField').value = e.latlng.lng.toFixed(6);
                });
                adminMarker.on('dragend', function(e) {
                    const position = adminMarker.getLatLng();
                    byId('roomLatField').value = position.lat.toFixed(6);
                    byId('roomLngField').value = position.lng.toFixed(6);
                });
            } else {
                adminMap.setView([lat, lng], 18);
                adminMarker.setLatLng([lat, lng]);
                adminMap.invalidateSize();
            }
        }, 100);
    });

    if (hasSeatingPlan) {
        byId('seatLayoutType').addEventListener('change', toggleSeatBuilder);
        byId('seatRows').addEventListener('input', updateSeatCapacityPreview);
        byId('seatCols').addEventListener('input', updateSeatCapacityPreview);
        byId('seatTotalOpen').addEventListener('input', updateSeatCapacityPreview);
        byId('roomEditorForm').addEventListener('submit', finalizeRoomSeatPlan);
    }

    document.querySelectorAll('.room-incharge-radio').forEach(el => {
        el.addEventListener('change', function () {
            const memberCheck = byId('staff_' + this.value);
            if (memberCheck) memberCheck.checked = true;
        });
    });

    byId('bookingRoomField').addEventListener('change', refreshBookingSeatOptions);
    byId('bookingDateField').addEventListener('change', refreshBookingSeatOptions);
    byId('bookingRoomField').addEventListener('change', syncBookingImportFields);
    byId('bookingDateField').addEventListener('change', syncBookingImportFields);
    byId('bookingStatusField').addEventListener('change', syncBookingImportFields);
    byId('bookingProjectField').addEventListener('input', function () {
        syncBookingImportFields();
        renderSameDateRoomSelector(byId('bookingDateField').value || '');
    });
    byId('bookingTimeField').addEventListener('change', function () {
        syncShiftFromTime('booking');
        syncBookingImportFields();
    });
    byId('bookingAmpmField').addEventListener('change', function () {
        syncShiftFromTime('booking');
        syncBookingImportFields();
    });
    byId('bookingSessionRemarksField').addEventListener('input', syncBookingImportFields);

    byId('globalSearch').addEventListener('input', function () {
        const terms = this.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        const matchAll = hay => terms.every(term => hay.includes(term));
        document.querySelectorAll('.room-card-wrapper, .manage-row').forEach(el => {
            const hay = (el.getAttribute('data-search') || '').toLowerCase();
            el.style.display = terms.length === 0 || matchAll(hay) ? '' : 'none';
        });
    });

    <?php if (!empty($_SESSION['room_network_flash'])): ?>
    Swal.fire({
        icon: <?= json_encode($_SESSION['room_network_flash']['icon']) ?>,
        title: <?= json_encode($_SESSION['room_network_flash']['title']) ?>,
        text: <?= json_encode($_SESSION['room_network_flash']['text']) ?>,
        confirmButtonColor: '#153a64'
    });
    <?php unset($_SESSION['room_network_flash']); endif; ?>
});
</script>
</body>
</html>