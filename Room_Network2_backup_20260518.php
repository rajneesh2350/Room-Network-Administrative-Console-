<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(); // Start only if needed
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect to login if not authenticated
if (!isset($_SESSION['loggedin'])) {
    header("Location: https://igipess.du.ac.in/login.php");
    exit;
}

// --- ADMIN TOGGLE ---
$admin = "Yes"; // Set to "No" to hide all editing and management features

// --- DATABASE CONNECTION ---
include ("./log/config/conn.php");
date_default_timezone_set('Asia/Kolkata');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- CRUD OPERATIONS & FILE UPLOAD (Secured by $admin variable) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin === "Yes") {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $floor = $conn->real_escape_string($_POST['floor']);
        $room_no = $conn->real_escape_string($_POST['room_no']);
        $description = $conn->real_escape_string($_POST['desc']);
        $icon = $conn->real_escape_string($_POST['icon'] ?: 'fa-door-open');

        // Handle hardware fields
        $networking = !empty($_POST['networking']) ? "'" . $conn->real_escape_string($_POST['networking']) . "'" : "NULL";
        $int_board = !empty($_POST['interactive_board']) ? "'" . $conn->real_escape_string($_POST['interactive_board']) . "'" : "NULL";
        $wifi = !empty($_POST['wifi_router']) ? "'" . $conn->real_escape_string($_POST['wifi_router']) . "'" : "NULL";
        $cctv = !empty($_POST['cctv']) ? "'" . $conn->real_escape_string($_POST['cctv']) . "'" : "NULL";
        $ups = !empty($_POST['ups']) ? "'" . $conn->real_escape_string($_POST['ups']) . "'" : "NULL";
        $audio_video = !empty($_POST['audio_video']) ? "'" . $conn->real_escape_string($_POST['audio_video']) . "'" : "NULL";
        $remarks = !empty($_POST['remarks']) ? "'" . $conn->real_escape_string($_POST['remarks']) . "'" : "NULL";

        // Handle Dimensions & Location
        $width = !empty($_POST['width']) ? "'" . $conn->real_escape_string($_POST['width']) . "'" : "NULL";
        $length = !empty($_POST['length']) ? "'" . $conn->real_escape_string($_POST['length']) . "'" : "NULL";
        $latitude = !empty($_POST['latitude']) ? "'" . $conn->real_escape_string($_POST['latitude']) . "'" : "NULL";
        $longitude = !empty($_POST['longitude']) ? "'" . $conn->real_escape_string($_POST['longitude']) . "'" : "NULL";

        // Handle Seating Plan JSON
        $seating_plan = !empty($_POST['seating_plan']) ? "'" . $conn->real_escape_string($_POST['seating_plan']) . "'" : "NULL";

        // --- ROOM MEMBERS LOGIC ---
        $room_members = "NULL";
        if (isset($_POST['room_members']) && is_array($_POST['room_members'])) {
            $rm_escaped = $conn->real_escape_string(json_encode($_POST['room_members']));
            $room_members = "'" . $rm_escaped . "'";
        }
        $in_charge_id = !empty($_POST['in_charge_id']) ? (int)$_POST['in_charge_id'] : "NULL";

        // --- FILE UPLOAD LOGIC ---
        $old_image = $_POST['old_image'] ?? '';
        $room_image_val = !empty($old_image) ? "'" . $conn->real_escape_string($old_image) . "'" : "NULL";

        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "./images/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES["room_image"]["name"]));
            $target_file = $target_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
                if (move_uploaded_file($_FILES["room_image"]["tmp_name"], $target_file)) {
                    $room_image_val = "'" . $conn->real_escape_string($file_name) . "'";
                }
            }
        }
        // -------------------------

        if ($action === 'add') {
            $sql = "INSERT INTO `igpess_network` (`floor`, `room_no`, `description`, `icon`, `networking`, `interactive_board`, `wifi_router`, `cctv`, `ups`, `audio_video`, `remarks`, `room_image`, `width`, `length`, `latitude`, `longitude`, `room_members`, `in_charge_id`, `seating_plan`)
                    VALUES ('$floor', '$room_no', '$description', '$icon', $networking, $int_board, $wifi, $cctv, $ups, $audio_video, $remarks, $room_image_val, $width, $length, $latitude, $longitude, $room_members, $in_charge_id, $seating_plan)";
        } else {
            $id = (int)$_POST['id'];
            $sql = "UPDATE `igpess_network` SET
                        `floor`='$floor',
                        `room_no`='$room_no',
                        `description`='$description',
                        `icon`='$icon',
                        `networking`=$networking,
                        `interactive_board`=$int_board,
                        `wifi_router`=$wifi,
                        `cctv`=$cctv,
                        `ups`=$ups,
                        `audio_video`=$audio_video,
                        `remarks`=$remarks,
                        `room_image`=$room_image_val,
                        `width`=$width,
                        `length`=$length,
                        `latitude`=$latitude,
                        `longitude`=$longitude,
                        `room_members`=$room_members,
                        `in_charge_id`=$in_charge_id,
                        `seating_plan`=$seating_plan
                    WHERE `id`=$id";
        }

        // Error Catcher
        if (!$conn->query($sql)) {
            $dbError = addslashes($conn->error);
            echo "<script>
                alert('DATABASE ERROR: $dbError\\n\\nCheck if all columns exist in your table!');
                window.history.back();
            </script>";
            exit;
        }

        $redirectTab = $_POST['redirect_tab'] ?? 'manage';
        echo "<script>
            location.replace('" . $_SERVER['PHP_SELF'] . "?tab=" . urlencode($redirectTab) . "');
        </script>";
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM `igpess_network` WHERE `id`=$id");
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=manage");
        exit;
    }
}

// --- 1. FETCH ORG CHART DATA & BUILD VIRTUAL IDENTITIES ---
$org_sql = "SELECT id, category, role, name, pic FROM org_chart ORDER BY sort_order, name";
$org_result = $conn->query($org_sql);
$orgStaff = [];
$staffMap = [];

if ($org_result && $org_result->num_rows > 0) {
    while($row = $org_result->fetch_assoc()) {
        $raw_names = preg_split('/(<br\s*\/?>|\n)/i', $row['name']);
        $valid_names = [];
        foreach($raw_names as $n) {
            $n = trim(strip_tags($n));
            if($n !== '') $valid_names[] = $n;
        }
        if(count($valid_names) == 0) {
            $valid_names[] = trim(strip_tags($row['name']));
        }
        foreach($valid_names as $index => $split_name) {
            $new_id = ($index == 0) ? (int)$row['id'] : -1 * ((int)$row['id'] * 1000 + $index);
            $staffEntry = [
                'id' => $new_id,
                'real_org_id' => $row['id'],
                'category' => $row['category'],
                'role' => $row['role'],
                'name' => $split_name,
                'pic' => $row['pic']
            ];
            $orgStaff[] = $staffEntry;
            $staffMap[$new_id] = $staffEntry;
        }
    }
}

// --- 2. FETCH ROOM DATA & BUILD ROBUST SEARCH INDEX ---
$sql = "SELECT * FROM igpess_network ORDER BY FIELD(floor, 'Ground Floor', 'First Floor', 'Second Floor'), CAST(room_no AS UNSIGNED)";
$result = $conn->query($sql);

$buildingData = ["Ground Floor" => [], "First Floor" => [], "Second Floor" => []];
$allRooms = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {

        $searchTags = [
            $row['floor'],
            $row['room_no'],
            $row['description'],
            $row['remarks']
        ];

        if ($row['networking'] === 'Yes') $searchTags[] = 'net networking';
        if ($row['interactive_board'] === 'Yes') $searchTags[] = 'board interactive';
        if ($row['wifi_router'] === 'Yes') $searchTags[] = 'wi-fi wifi router';
        if ($row['cctv'] === 'Yes') $searchTags[] = 'cctv camera';
        if ($row['ups'] === 'Yes') $searchTags[] = 'ups power';
        if ($row['audio_video'] === 'Yes') $searchTags[] = 'a/v audio video av';

        $membersArr = json_decode($row['room_members'] ?? '[]', true) ?: [];
        foreach($membersArr as $mid) {
            if(isset($staffMap[$mid])) {
                $searchTags[] = $staffMap[$mid]['name'];
                $searchTags[] = $staffMap[$mid]['role'];
            }
        }

        // Auto Calculate SqFt
        if(!empty($row['width']) && !empty($row['length'])) {
            $row['sqft'] = round($row['width'] * $row['length'], 2);
        } else {
            $row['sqft'] = null;
        }

        // Extract Valid Seating Capacity for display/search
        $seatData = json_decode($row['seating_plan'] ?? '{}', true);
        $totalValidSeats = 0;
        if($seatData) {
            if(($seatData['type'] ?? '') === 'grid') {
                $r = (int)($seatData['r'] ?? 0);
                $c = (int)($seatData['c'] ?? 0);
                $b = is_array($seatData['b'] ?? null) ? count($seatData['b']) : 0;
                $totalValidSeats = ($r * $c) - $b;
            } elseif(($seatData['type'] ?? '') === 'open') {
                $totalValidSeats = (int)($seatData['total'] ?? 0);
            }
        }
        $row['total_capacity'] = $totalValidSeats;
        if($totalValidSeats > 0) $searchTags[] = "capacity $totalValidSeats seats";

        $row['search_index'] = strtolower(implode(' ', $searchTags));
        $buildingData[$row['floor']][] = $row;
        $allRooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IGIPESS IT & Building Layout</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        .header-banner { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2rem 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }

        /* 3D Card Styling */
        .room-card-wrapper { transition: opacity 0.3s ease; }
        .room-card { background: #ffffff; border-radius: 15px; padding: 20px; height: 100%; border: none; box-shadow: 15px 15px 40px #d9d9d9, -15px -15px 40px #ffffff; transform-style: preserve-3d; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; border-left: 5px solid #2a5298; text-align: center; position: relative;}
        .room-card-inner { transform: translateZ(30px); width: 100%; }
        .card-room-image { width: 100%; height: 110px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .room-icon { font-size: 2rem; color: #2a5298; margin-bottom: 5px; }
        .room-number { font-weight: bold; font-size: 1.2rem; color: #e74c3c; margin-bottom: 5px; }
        .room-desc { font-size: 0.85rem; color: #555; line-height: 1.3; margin-bottom: 15px; min-height: 38px;}
        .hardware-badges span { font-size: 0.75rem; margin: 2px; }
        .room-remarks { font-size: 0.75rem; color: #888; font-style: italic; margin-top: 10px; }

        .edit-icon-card { position: absolute; top: 15px; right: 15px; z-index: 999; cursor: pointer; background: rgba(255,255,255,0.95); border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transform: translateZ(50px); }
        .edit-icon-card:hover { background: #ffc107; color: #fff !important; transform: translateZ(60px) scale(1.1); }

        .nav-pills .nav-link { border-radius: 50px; padding: 10px 25px; font-weight: 600; color: #1e3c72; margin: 0 5px; transition: all 0.3s ease; border: 1px solid transparent; }
        .nav-pills .nav-link:hover { border-color: #1e3c72; color: #1e3c72; }
        .nav-pills .nav-link.active { background-color: #1e3c72; box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); color: white; border-color: #1e3c72;}
        .search-container { max-width: 350px; }
        .search-container input:focus { box-shadow: 0 0 10px rgba(30,60,114,0.2); border-color: #1e3c72; }
        .fa-select { font-family: 'Font Awesome 6 Free', 'Segoe UI', sans-serif; font-weight: 900; }

        /* --- PRO 3D HORIZONTAL MAP STYLES --- */
        .map-wrapper { width: 100%; border-radius: 20px; overflow: hidden; position: relative; background: radial-gradient(circle at 50% 50%, #0f172a 0%, #020617 100%); box-shadow: 0 20px 50px rgba(0,0,0,0.4); border: 1px solid #1e293b; }
        .map-overlay-text { position: absolute; top: 30px; left: 40px; pointer-events: none; z-index: 10; }
        .map-overlay-text h2 { color: #38bdf8; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; text-shadow: 0 0 15px rgba(56,189,248,0.5); margin:0;}
        .map-overlay-text p { color: #94a3b8; font-size: 1.1rem; }
        .svg-container { width: 100%; height: auto; display: block; cursor: crosshair; }
        .isometric-layer { transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.5s ease; }

        .layer-ground { transform: translate(500px, 450px) scale(1, 0.5) rotate(45deg); opacity: 0.9; }
        .layer-first  { transform: translate(1400px, 450px) scale(1, 0.5) rotate(45deg); opacity: 0.9; }
        .layer-second { transform: translate(2300px, 450px) scale(1, 0.5) rotate(45deg); opacity: 0.9; }
        .layer-ground:hover { transform: translate(500px, 400px) scale(1, 0.5) rotate(45deg); opacity: 1; filter: drop-shadow(0 30px 40px rgba(0,0,0,0.6)); }
        .layer-first:hover  { transform: translate(1400px, 400px) scale(1, 0.5) rotate(45deg); opacity: 1; filter: drop-shadow(0 30px 40px rgba(0,0,0,0.6)); }
        .layer-second:hover { transform: translate(2300px, 400px) scale(1, 0.5) rotate(45deg); opacity: 1; filter: drop-shadow(0 30px 40px rgba(0,0,0,0.6)); }

        .floor-base { fill: rgba(15, 23, 42, 0.85); stroke: #38bdf8; stroke-width: 2px; filter: drop-shadow(0 20px 30px rgba(0,0,0,0.8)); }
        .floor-grid { stroke: rgba(56, 189, 248, 0.1); stroke-width: 1px; }
        .floor-label { font-family: 'Segoe UI', sans-serif; font-size: 45px; font-weight: 900; fill: rgba(56,189,248,0.20); pointer-events: none; letter-spacing: 12px; }

        .staircase-group { cursor: pointer; transition: 0.3s; }
        .staircase-block { fill: rgba(15, 23, 42, 0.9); stroke: #0ea5e9; stroke-width: 3px; stroke-dasharray: 8; transition: 0.3s; }
        .stair-text { font-family: 'Segoe UI', sans-serif; font-weight: 900; fill: #38bdf8; transition: 0.3s; }
        .stair-sub { font-family: 'Segoe UI', sans-serif; font-weight: bold; fill: #7dd3fc; opacity: 0.8; transition: 0.3s; }
        .staircase-group:hover .staircase-block { fill: rgba(14, 165, 233, 0.3); stroke: #fff; stroke-dasharray: 0; filter: drop-shadow(0 0 20px rgba(56,189,248,0.8)); }
        .staircase-group:hover .stair-text { fill: #fff; font-size: 70px; }
        .staircase-group:hover .stair-sub { fill: #fff; opacity: 1; }

        @keyframes hologram-pulse {
            0% { fill: rgba(14, 165, 233, 0.2); stroke-width: 1px; filter: drop-shadow(0 0 5px rgba(14,165,233,0)); }
            50% { fill: rgba(14, 165, 233, 0.4); stroke-width: 2px; filter: drop-shadow(0 0 15px rgba(14,165,233,0.6)); }
            100% { fill: rgba(14, 165, 233, 0.2); stroke-width: 1px; filter: drop-shadow(0 0 5px rgba(14,165,233,0)); }
        }
        .map-room { fill: rgba(14, 165, 233, 0.2); stroke: #7dd3fc; stroke-width: 1px; cursor: pointer; transition: all 0.3s ease; animation: hologram-pulse 4s infinite alternate; }
        .room-text { font-family: 'Segoe UI', sans-serif; font-size: 22px; font-weight: 800; fill: #bae6fd; pointer-events: none; text-anchor: middle; alignment-baseline: middle;}
        .interactive-room:hover .map-room { fill: #38bdf8; stroke: #fff; stroke-width: 3px; transform: translate(-4px, -4px); filter: drop-shadow(10px 10px 15px rgba(0,0,0,0.5)); animation: none; }
        .interactive-room:hover .room-text { fill: #ffffff; transform: translate(-4px, -4px); font-size: 26px; }
        .room-hidden { opacity: 0.1 !important; pointer-events: none !important; }

        #adminMap { height: 250px; width: 100%; border-radius: 8px; border: 1px solid #ccc; z-index: 1;}
        #viewMap { height: 200px; width: 100%; border-radius: 8px; border: 2px solid #0ea5e9; z-index: 1;}

        .staff-scroll-box { max-height: 250px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 0.375rem; padding: 10px; background: #fff;}
        .staff-item:hover { background-color: #f8f9fa; border-radius: 5px; }

        /* SEATING GRID STYLES */
        .seat-box { width: 38px; height: 38px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: bold; transition: 0.2s; user-select: none; border: 2px solid transparent;}
        .seat-box.editable { cursor: pointer; }
        .seat-active { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
        .seat-active:hover.editable { background-color: #a3cfbb; }
        .seat-blocked { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; text-decoration: line-through; opacity: 0.6; }
        .seat-blocked:hover.editable { opacity: 1; }
        .seat-desk { width: 100%; height: 15px; background: #6c757d; border-radius: 4px; margin-bottom: 15px; text-align: center; color: white; font-size: 0.6rem; font-weight: bold; letter-spacing: 2px;}
        .seating-grid { display: grid; gap: 8px; justify-content: center; margin: 0 auto;}

        /* --- PRINT STYLES --- */
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { background: white !important; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
            .print-table th, .print-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: middle; }
            .print-table th { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact; text-align: center; }
            .floor-header { background-color: #1e3c72 !important; color: white !important; -webkit-print-color-adjust: exact; font-weight: bold; text-align: center; font-size: 12pt;}
            .text-center-print { text-align: center !important; }
            tbody.floor-group:nth-of-type(n+2) { break-before: page; page-break-before: always; }
            .tab-pane.active { display: block !important; opacity: 1 !important; }
            .row { display: flex !important; flex-wrap: wrap !important; margin: 0 !important;}
            .col-print-5 { flex: 0 0 20% !important; max-width: 20% !important; padding: 4px !important; margin-bottom: 8px !important; }
            .mb-4 { margin-bottom: 0 !important; }
            .room-card { border: 1px solid #444 !important; border-left: 5px solid #2a5298 !important; box-shadow: none !important; transform: none !important; padding: 8px 4px !important; page-break-inside: avoid; break-inside: avoid; height: 160px !important; overflow: hidden; }
            .card-room-image { display: none !important; }
            .card-personnel-block { display: none !important; }
            .room-icon { font-size: 1.1rem !important; margin-bottom: 2px !important; }
            .room-number { font-size: 0.85rem !important; margin-bottom: 2px !important; }
            .room-desc { font-size: 0.7rem !important; margin-bottom: 5px !important; line-height: 1.1; }
            .hardware-badges span { font-size: 0.55rem !important; padding: 2px 3px !important; margin: 1px !important;}
            .room-remarks { font-size: 0.6rem !important; margin-top: 4px !important; }
            .edit-icon-card { display: none !important; }
            .room-card-wrapper { display: block !important; }
        }
    </style>
</head>
<body>

    <header class="header-banner text-center no-print">
        <div class="container">
            <h1 class="display-5 fw-bold"><i class="fas fa-network-wired me-3"></i>IGIPESS Campus & IT Layout</h1>
            <p class="lead mb-0">Interactive 3D Directory & Hardware Management</p>
        </div>
    </header>

    <div class="container-fluid pb-5 mt-4 px-4">

        <?php
        $activeTab = $_GET['tab'] ?? 'all';
        if ($admin !== "Yes" && $activeTab === 'manage') {
            $activeTab = 'all';
        }
        ?>

        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm border no-print gap-3">

            <div class="input-group w-auto flex-grow-1" style="max-width: 500px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-pill text-primary ps-4"><i class="fas fa-search"></i></span>
                <input type="text" id="globalSearch" class="form-control bg-light border-start-0 rounded-end-pill shadow-none py-2" placeholder="Search rooms, staff, hardware, capacity...">
            </div>

            <div class="d-flex gap-2 flex-wrap justify-content-center">
                <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold shadow-sm">
                    <i class="fas fa-print me-2"></i> Print Layout
                </button>

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
            </div>
        </div>

        <ul class="nav nav-pills justify-content-center align-items-center mb-5 no-print" id="floorTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link <?= $activeTab=='all'?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" id="btn-tab-all"><i class="fas fa-border-all me-1"></i> ALL Rooms</button>
            </li>
            <?php foreach(["Ground Floor", "First Floor", "Second Floor"] as $floorName): ?>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab==md5($floorName)?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#tab-<?= md5($floorName) ?>" type="button" id="btn-tab-<?= md5($floorName) ?>"><?= $floorName ?></button>
            </li>
            <?php endforeach; ?>
            <li class="nav-item ms-md-3">
                <button class="nav-link bg-dark text-white <?= $activeTab=='map'?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#tab-map" type="button" id="btn-tab-map"><i class="fas fa-cubes me-1"></i> 3D Horizontal Map</button>
            </li>

            <?php if ($admin === "Yes"): ?>
            <li class="nav-item ms-md-3 border-start ps-3">
                <button class="nav-link bg-danger text-white <?= $activeTab=='manage'?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#tab-manage" type="button" id="btn-tab-manage"><i class="fas fa-cog me-1"></i> Manage Rooms (Admin)</button>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade <?= $activeTab=='all'?'show active':'' ?>" id="tab-all">
                <div class="no-print">
                    <?php foreach($buildingData as $floorName => $rooms): if(empty($rooms)) continue; ?>
                        <div class="floor-section">
                            <h4 class="mt-4 mb-3 text-primary border-bottom pb-2"><i class="fas fa-layer-group me-2"></i><?= strtoupper($floorName) ?></h4>
                            <div class="row g-4 mb-5">
                                <?php foreach($rooms as $room): ?>
                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 room-card-wrapper" data-search="<?= htmlspecialchars($room['search_index']) ?>">
                                        <div class="room-card" data-tilt data-tilt-max="10" data-tilt-speed="400" onclick="openViewModal('<?= addslashes(htmlspecialchars($room['room_no'])) ?>')" style="cursor: pointer;">

                                            <?php if ($admin === "Yes"): ?>
                                            <div class="edit-icon-card text-muted" onclick='event.stopPropagation(); openModal("edit", <?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "all")' title="Edit Room"><i class="fas fa-edit"></i></div>
                                            <?php endif; ?>

                                            <div class="room-card-inner">
                                                <?php $displayImg = !empty($room['room_image']) ? './images/' . htmlspecialchars($room['room_image']) : './images/1.jpg'; ?>
                                                <img src="<?= $displayImg ?>" class="card-room-image shadow-sm" alt="Room Photo">

                                                <i class="fas <?= htmlspecialchars($room['icon']) ?> room-icon"></i>
                                                <div class="room-number">Room <?= htmlspecialchars($room['room_no']) ?></div>
                                                <div class="room-desc"><?= htmlspecialchars($room['description']) ?></div>

                                                <div class="hardware-badges mt-2 border-top pt-2">
                                                    <?php if($room['networking'] == 'Yes') echo '<span class="badge bg-primary"><i class="fas fa-network-wired"></i> Net</span>'; ?>
                                                    <?php if($room['interactive_board'] == 'Yes') echo '<span class="badge bg-success"><i class="fas fa-chalkboard-teacher"></i> Board</span>'; ?>
                                                    <?php if($room['wifi_router'] == 'Yes') echo '<span class="badge bg-info text-dark"><i class="fas fa-wifi"></i> Wi-Fi</span>'; ?>
                                                    <?php if($room['cctv'] == 'Yes') echo '<span class="badge bg-danger"><i class="fas fa-video"></i> CCTV</span>'; ?>
                                                    <?php if($room['ups'] == 'Yes') echo '<span class="badge bg-warning text-dark"><i class="fas fa-plug"></i> UPS</span>'; ?>
                                                    <?php if($room['audio_video'] == 'Yes') echo '<span class="badge bg-secondary"><i class="fas fa-photo-video"></i> A/V</span>'; ?>
                                                </div>

                                                <div class="d-flex justify-content-center gap-2 align-items-center mt-2 flex-wrap">
                                                    <?php if(!empty($room['sqft'])): ?>
                                                        <div class="text-muted" style="font-size:0.75rem;"><i class="fas fa-vector-square me-1"></i><?= $room['sqft'] ?> Sq.Ft</div>
                                                    <?php endif; ?>

                                                    <?php if($room['total_capacity'] > 0): ?>
                                                        <div class="text-primary fw-bold" style="font-size:0.75rem;"><i class="fas fa-chair me-1"></i><?= $room['total_capacity'] ?> Seats</div>
                                                    <?php endif; ?>

                                                    <?php if(!empty($room['latitude']) && !empty($room['longitude'])): ?>
                                                        <a href="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d11779.47360734976!2d77.07083470314716!3d28.628010064343254!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x57f44963a604a8b0!2sIndira+Gandhi+Institute+of+Physical+Education+%26+Sports+Sciences!5e0!3m2!1sen!2sus!4v1553252979402<?= htmlspecialchars($room['latitude']) ?>,<?= htmlspecialchars($room['longitude']) ?>" target="_blank" onclick="event.stopPropagation()" class="badge bg-dark text-white text-decoration-none p-1 shadow-sm border border-secondary" title="Open Location in Google Maps">
                                                            <i class="fas fa-map-marker-alt text-danger"></i> Geo-Pin
                                                        </a>
                                                    <?php endif; ?>
                                                </div>

                                                <?php
                                                $membersArr = json_decode($room['room_members'] ?? '[]', true) ?: [];
                                                if (!empty($membersArr)):
                                                ?>
                                                <div class="mt-3 w-100 text-start bg-light p-2 rounded shadow-sm border card-personnel-block">
                                                    <div class="text-primary mb-2" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800;"><i class="fas fa-user-tie me-1"></i> Room Personnel</div>
                                                    <?php
                                                    $inChargeId = $room['in_charge_id'];
                                                    usort($membersArr, function($a, $b) use ($inChargeId) {
                                                        if ($a == $inChargeId) return -1;
                                                        if ($b == $inChargeId) return 1;
                                                        return 0;
                                                    });

                                                    foreach($membersArr as $mid):
                                                        $staff = $staffMap[$mid] ?? null;
                                                        if($staff):
                                                            $isCharge = ($mid == $inChargeId);
                                                            $staffPic = !empty($staff['pic']) ? './images/' . htmlspecialchars($staff['pic']) : './images/default_user.png';
                                                    ?>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <img src="<?= $staffPic ?>" class="rounded-circle shadow-sm border <?= $isCharge ? 'border-danger border-2' : 'border-secondary' ?> me-2" width="28" height="28" style="object-fit:cover;" onerror="this.src='./images/1.jpg';">
                                                        <div style="line-height: 1.1;">
                                                            <span class="fw-bold text-dark" style="font-size: 0.75rem;"><?= htmlspecialchars($staff['name']) ?></span>
                                                            <?php if($isCharge): ?><span class="badge bg-danger ms-1" style="font-size: 0.5rem; padding: 0.2em 0.4em;">IN-CHARGE</span><?php endif; ?>
                                                            <div class="text-muted mt-1" style="font-size: 0.65rem;"><?= htmlspecialchars($staff['role']) ?></div>
                                                        </div>
                                                    </div>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="print-container d-none d-print-block">
                    <h2 class="text-center mb-4">IGIPESS - Complete Room & Network Master Chart</h2>
                    <table class="print-table bg-white">
                        <thead>
                            <tr>
                                <th width="6%">Room</th><th width="15%">Description</th><th width="8%">Network</th><th width="8%">Int. Board</th><th width="8%">Wi-Fi</th><th width="8%">CCTV</th><th width="7%">UPS</th><th width="6%">A/V</th><th width="9%">Capacity</th><th width="25%">Remarks</th>
                            </tr>
                        </thead>
                        <?php foreach($buildingData as $floorName => $rooms): if(empty($rooms)) continue; ?>
                        <tbody class="floor-group">
                            <tr><td colspan="10" class="floor-header"><?= strtoupper($floorName) ?></td></tr>
                            <?php foreach($rooms as $room): ?>
                                <tr>
                                    <td class="text-center-print"><strong><?= htmlspecialchars($room['room_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($room['description']) ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['networking'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['interactive_board'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['wifi_router'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['cctv'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['ups'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= htmlspecialchars($room['audio_video'] ?? '-') ?></td>
                                    <td class="text-center-print"><?= $room['total_capacity'] > 0 ? $room['total_capacity'] . ' Seats' : '-' ?></td>
                                    <td><?= htmlspecialchars($room['remarks'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <?php foreach(["Ground Floor", "First Floor", "Second Floor"] as $floorName): ?>
            <div class="tab-pane fade <?= $activeTab==md5($floorName)?'show active':'' ?>" id="tab-<?= md5($floorName) ?>">
                <h2 class="text-center d-none d-print-block mb-4">IGIPESS - <?= strtoupper($floorName) ?> Map Directory</h2>
                <div class="row floor-section">
                    <?php if(empty($buildingData[$floorName])): ?>
                        <div class="col-12 text-center text-muted"><p>No rooms added to this floor yet.</p></div>
                    <?php else: ?>
                        <?php foreach($buildingData[$floorName] as $room): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-print-5 mb-4 room-card-wrapper" data-search="<?= htmlspecialchars($room['search_index']) ?>">
                                <div class="room-card" data-tilt data-tilt-max="10" data-tilt-speed="400" onclick="openViewModal('<?= addslashes(htmlspecialchars($room['room_no'])) ?>')" style="cursor: pointer;">

                                    <?php if ($admin === "Yes"): ?>
                                    <div class="edit-icon-card text-muted" onclick='event.stopPropagation(); openModal("edit", <?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "<?= md5($floorName) ?>")'><i class="fas fa-edit"></i></div>
                                    <?php endif; ?>

                                    <div class="room-card-inner">
                                        <?php $displayImg = !empty($room['room_image']) ? './images/' . htmlspecialchars($room['room_image']) : './images/1.jpg'; ?>
                                        <img src="<?= $displayImg ?>" class="card-room-image shadow-sm" alt="Room Photo">

                                        <i class="fas <?= htmlspecialchars($room['icon']) ?> room-icon"></i>
                                        <div class="room-number">Room <?= htmlspecialchars($room['room_no']) ?></div>
                                        <div class="room-desc"><?= htmlspecialchars($room['description']) ?></div>
                                        <div class="hardware-badges mt-2 border-top pt-2">
                                            <?php if($room['networking'] == 'Yes') echo '<span class="badge bg-primary"><i class="fas fa-network-wired"></i> Net</span>'; ?>
                                            <?php if($room['interactive_board'] == 'Yes') echo '<span class="badge bg-success"><i class="fas fa-chalkboard-teacher"></i> Board</span>'; ?>
                                            <?php if($room['wifi_router'] == 'Yes') echo '<span class="badge bg-info text-dark"><i class="fas fa-wifi"></i> Wi-Fi</span>'; ?>
                                            <?php if($room['cctv'] == 'Yes') echo '<span class="badge bg-danger"><i class="fas fa-video"></i> CCTV</span>'; ?>
                                            <?php if($room['ups'] == 'Yes') echo '<span class="badge bg-warning text-dark"><i class="fas fa-plug"></i> UPS</span>'; ?>
                                            <?php if($room['audio_video'] == 'Yes') echo '<span class="badge bg-secondary"><i class="fas fa-photo-video"></i> A/V</span>'; ?>
                                        </div>

                                        <div class="d-flex justify-content-center gap-2 align-items-center mt-2 flex-wrap">
                                            <?php if(!empty($room['sqft'])): ?>
                                                <div class="text-muted" style="font-size:0.75rem;"><i class="fas fa-vector-square me-1"></i><?= $room['sqft'] ?> Sq.Ft</div>
                                            <?php endif; ?>

                                            <?php if($room['total_capacity'] > 0): ?>
                                                <div class="text-primary fw-bold" style="font-size:0.75rem;"><i class="fas fa-chair me-1"></i><?= $room['total_capacity'] ?> Seats</div>
                                            <?php endif; ?>

                                            <?php if(!empty($room['latitude']) && !empty($room['longitude'])): ?>
                                                <a href="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d11779.47360734976!2d77.07083470314716!3d28.628010064343254!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x57f44963a604a8b0!2sIndira+Gandhi+Institute+of+Physical+Education+%26+Sports+Sciences!5e0!3m2!1sen!2sus!4v1553252979402<?= htmlspecialchars($room['latitude']) ?>,<?= htmlspecialchars($room['longitude']) ?>" target="_blank" onclick="event.stopPropagation()" class="badge bg-dark text-white text-decoration-none p-1 shadow-sm border border-secondary" title="Open Location in Google Maps">
                                                    <i class="fas fa-map-marker-alt text-danger"></i> Geo-Pin
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <?php
                                        $membersArr = json_decode($room['room_members'] ?? '[]', true) ?: [];
                                        if (!empty($membersArr)):
                                        ?>
                                        <div class="mt-3 w-100 text-start bg-light p-2 rounded shadow-sm border card-personnel-block">
                                            <div class="text-primary mb-2" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800;"><i class="fas fa-user-tie me-1"></i> Room Personnel</div>
                                            <?php
                                            $inChargeId = $room['in_charge_id'];
                                            usort($membersArr, function($a, $b) use ($inChargeId) {
                                                if ($a == $inChargeId) return -1;
                                                if ($b == $inChargeId) return 1;
                                                return 0;
                                            });

                                            foreach($membersArr as $mid):
                                                $staff = $staffMap[$mid] ?? null;
                                                if($staff):
                                                    $isCharge = ($mid == $inChargeId);
                                                    $staffPic = !empty($staff['pic']) ? './images/' . htmlspecialchars($staff['pic']) : './images/default_user.png';
                                            ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <img src="<?= $staffPic ?>" class="rounded-circle shadow-sm border <?= $isCharge ? 'border-danger border-2' : 'border-secondary' ?> me-2" width="28" height="28" style="object-fit:cover;" onerror="this.src='./images/1.jpg';">
                                                <div style="line-height: 1.1;">
                                                    <span class="fw-bold text-dark" style="font-size: 0.75rem;"><?= htmlspecialchars($staff['name']) ?></span>
                                                    <?php if($isCharge): ?><span class="badge bg-danger ms-1" style="font-size: 0.5rem; padding: 0.2em 0.4em;">IN-CHARGE</span><?php endif; ?>
                                                    <div class="text-muted mt-1" style="font-size: 0.65rem;"><?= htmlspecialchars($staff['role']) ?></div>
                                                </div>
                                            </div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="tab-pane fade <?= $activeTab=='map'?'show active':'' ?> no-print" id="tab-map">
                <div class="map-wrapper mb-5">

                    <div class="map-overlay-text">
                        <h2><i class="fas fa-satellite me-3"></i>Campus Blueprint</h2>
                        <p>SYSTEM STATUS: <span class="text-success fw-bold">ONLINE</span></p>
                        <p class="small text-muted mb-0"><i class="fas fa-mouse me-1"></i> Pan across to see all floors. Hover over a floor to lift it. Click the central core to view that floor.</p>
                    </div>

                    <svg class="svg-container" viewBox="0 0 2800 1100">
                        <defs>
                            <pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse">
                                <rect width="100" height="100" fill="none" class="floor-grid"/>
                                <path d="M 100 0 L 0 0 0 100" fill="none" class="floor-grid"/>
                            </pattern>
                        </defs>

                        <?php
                        $svgFloors = [
                            "Ground Floor" => ["class" => "layer-ground", "abbr" => "GF"],
                            "First Floor"  => ["class" => "layer-first",  "abbr" => "FF"],
                            "Second Floor" => ["class" => "layer-second", "abbr" => "SF"]
                        ];

                        foreach($svgFloors as $fName => $fData):
                            $layerClass = $fData["class"];
                            $abbr = $fData["abbr"];
                            $rooms = $buildingData[$fName] ?? [];
                            $tabId = md5($fName);
                        ?>
                        <g class="isometric-layer <?= $layerClass ?>">
                            <rect class="floor-base" x="0" y="0" width="600" height="600" rx="15"/>
                            <rect x="0" y="0" width="600" height="600" fill="url(#grid)" rx="15" pointer-events="none"/>
                            <text class="floor-label" x="300" y="570" transform="rotate(-90 300 570)"><?= strtoupper($fName) ?></text>

                            <g class="staircase-group" transform="translate(200, 200)" onclick="switchTab('<?= $tabId ?>')">
                                <title>Click to view all <?= $fName ?> rooms</title>
                                <rect class="staircase-block" x="0" y="0" width="200" height="200" rx="15"/>
                                <text class="stair-text" x="100" y="95" text-anchor="middle" alignment-baseline="middle" font-size="60px"><?= $abbr ?></text>
                                <text class="stair-sub" x="100" y="145" text-anchor="middle" font-size="16px">VIEW FLOOR TAB</text>
                            </g>

                            <?php
                            $slot = 0;
                            foreach($rooms as $room):
                                while (true) {
                                    $col = $slot % 6;
                                    $row = floor($slot / 6);
                                    if (($col == 2 || $col == 3) && ($row == 2 || $row == 3)) {
                                        $slot++;
                                    } else {
                                        break;
                                    }
                                }
                                $x = 10 + ($col * 100);
                                $y = 10 + ($row * 100);
                                $slot++;
                            ?>
                            <g class="interactive-room" data-room="<?= htmlspecialchars($room['room_no']) ?>" data-search="<?= htmlspecialchars($room['search_index']) ?>">
                                <title>Room <?= htmlspecialchars($room['room_no']) ?> - <?= htmlspecialchars($room['description']) ?></title>
                                <rect class="map-room" x="<?= $x ?>" y="<?= $y ?>" width="80" height="80" rx="8"/>
                                <text class="room-text" x="<?= $x + 40 ?>" y="<?= $y + 40 ?>"><?= htmlspecialchars($room['room_no']) ?></text>
                            </g>
                            <?php endforeach; ?>

                        </g>
                        <?php endforeach; ?>

                    </svg>
                </div>
            </div>

            <?php if ($admin === "Yes"): ?>
            <div class="tab-pane fade <?= $activeTab=='manage'?'show active':'' ?> no-print" id="tab-manage">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
                        <h4 class="mb-0 text-primary"><i class="fas fa-database me-2"></i>Database & Hardware Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openModal('add')"><i class="fas fa-plus me-1"></i> Add Room</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="manageTable" style="white-space: nowrap;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Floor</th><th>Room</th><th>Description</th><th>Dimensions</th>
                                        <th>Capacity</th>
                                        <th>Staff Assigned</th>
                                        <th>Net</th><th>Board</th><th>Wi-Fi</th><th>CCTV</th><th>UPS</th><th>A/V</th><th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($allRooms as $room): ?>
                                    <tr class="manage-row" data-search="<?= htmlspecialchars($room['search_index']) ?>">
                                        <td><?= htmlspecialchars($room['floor']) ?></td>
                                        <td>
                                            <?php $displayImg = !empty($room['room_image']) ? './images/' . htmlspecialchars($room['room_image']) : './images/1.jpg'; ?>
                                            <img src="<?= $displayImg ?>" class="rounded-circle border me-2" width="30" height="30" style="object-fit: cover;">
                                            <strong><?= htmlspecialchars($room['room_no']) ?></strong>
                                        </td>
                                        <td><i class="fas <?= htmlspecialchars($room['icon']) ?> me-1 text-muted"></i> <?= htmlspecialchars($room['description']) ?></td>
                                        <td><?= !empty($room['sqft']) ? $room['sqft'] . ' ft²' : '-' ?></td>

                                        <td><?= $room['total_capacity'] > 0 ? '<span class="badge bg-info text-dark">'.$room['total_capacity'].' Seats</span>' : '-' ?></td>

                                        <td style="font-size: 0.85rem;">
                                            <?php
                                            $membersArr = json_decode($room['room_members'] ?? '[]', true) ?: [];
                                            $staffNames = [];
                                            foreach($membersArr as $mid) {
                                                if(isset($staffMap[$mid])) {
                                                    $name = htmlspecialchars($staffMap[$mid]['name']);
                                                    if($mid == $room['in_charge_id']) {
                                                        $name = "<strong>{$name} (?)</strong>";
                                                    }
                                                    $staffNames[] = $name;
                                                }
                                            }
                                            echo !empty($staffNames) ? implode('<br>', $staffNames) : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>

                                        <td><?= htmlspecialchars($room['networking'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($room['interactive_board'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($room['wifi_router'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($room['cctv'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($room['ups'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($room['audio_video'] ?? '-') ?></td>
                                        <td class="d-none export-remarks"><?= htmlspecialchars($room['remarks'] ?? '-') ?></td>
                                        <td class="text-end export-ignore">
                                            <button class="btn btn-sm btn-warning me-1" onclick='openModal("edit", <?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "manage")'><i class="fas fa-edit"></i></button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this room?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $room['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if ($admin === "Yes"): ?>
    <div class="modal fade no-print" id="roomModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="modalTitle">Add Room</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="add">
                        <input type="hidden" name="id" id="modalId">
                        <input type="hidden" name="redirect_tab" id="modalTab" value="manage">
                        <input type="hidden" name="old_image" id="modalOldImage">

                        <h6 class="text-primary border-bottom pb-2">1. Basic Info & Image</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Floor</label>
                                <select name="floor" id="modalFloor" class="form-select" required>
                                    <option value="Ground Floor">Ground Floor</option>
                                    <option value="First Floor">First Floor</option>
                                    <option value="Second Floor">Second Floor</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Room No.</label>
                                <input type="text" name="room_no" id="modalRoomNo" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room Type (Icon)</label>
                                <select name="icon" id="modalIcon" class="form-select fa-select" required>
                                    <option value="fa-door-open">? Default Room</option>
                                    <option value="fa-chalkboard">? Class Room</option>
                                    <option value="fa-chalkboard-teacher">? Interactive Room / Lab</option>
                                    <option value="fa-users">? Staff / Teachers Room</option>
                                    <option value="fa-desktop">? Computer Centre</option>
                                    <option value="fa-bullhorn">? Seminar Hall</option>
                                    <option value="fa-restroom">? Washroom</option>
                                    <option value="fa-coffee">? Canteen / Cafeteria</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="desc" id="modalDesc" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Map a Picture (JPG, PNG)</label>
                                <input type="file" name="room_image" id="modalImage" class="form-control" accept=".jpg, .jpeg, .png, .gif">
                                <div id="currentImageWrapper" class="mt-2 d-none p-2 border rounded bg-light">
                                     <span class="badge bg-secondary mb-1">Current Photo</span><br>
                                     <img id="currentImagePreview" src="" height="60" class="rounded border">
                                </div>
                            </div>
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mt-2">2. Room Members & Leadership</h6>
                        <div class="row mb-3">
                            <div class="col-12">
                                <p class="text-muted small mb-2"><i class="fas fa-users"></i> Check the box to assign a member to this room. Select the Radio button to designate them as <strong>IN CHARGE</strong>.</p>
                                <div class="staff-scroll-box shadow-sm">
                                    <input type="text" id="staffSearchModal" class="form-control form-control-sm mb-3 sticky-top" placeholder="?? Search Staff by Name or Role...">
                                    <div id="staffListWrapper">
                                        <?php foreach($orgStaff as $staff): ?>
                                            <div class="d-flex align-items-center mb-2 staff-item px-2 py-1 border-bottom" data-name="<?= strtolower($staff['name']. ' ' . $staff['role']) ?>">

                                                <div class="form-check me-3 mb-0" title="Assign to Room">
                                                    <input class="form-check-input member-checkbox" type="checkbox" name="room_members[]" value="<?= $staff['id'] ?>" id="staff_<?= $staff['id'] ?>">
                                                </div>

                                                <div class="form-check me-3 mb-0" title="Mark as In-Charge">
                                                    <input class="form-check-input incharge-radio" type="radio" name="in_charge_id" value="<?= $staff['id'] ?>" id="incharge_<?= $staff['id'] ?>">
                                                    <label class="form-check-label text-danger small fw-bold" for="incharge_<?= $staff['id'] ?>">IN CHARGE</label>
                                                </div>

                                                <?php $staffPic = !empty($staff['pic']) ? './images/' . $staff['pic'] : './images/default_user.png'; ?>
                                                <img src="<?= htmlspecialchars($staffPic) ?>" class="rounded-circle border border-secondary me-3" width="35" height="35" style="object-fit: cover;" onerror="this.src='./images/1.jpg';">

                                                <div>
                                                    <div class="fw-bold lh-1" style="font-size:0.95rem;"><?= htmlspecialchars($staff['name']) ?></div>
                                                    <div class="text-muted mt-1" style="font-size: 0.75rem;"><span class="badge bg-secondary text-white"><?= htmlspecialchars($staff['category']) ?></span> <?= htmlspecialchars($staff['role']) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mt-3">3. Comprehensive Seating Plan</h6>
                        <div class="row bg-light border rounded p-3 mb-3 mx-1 shadow-sm">
                            <div class="col-md-3 mb-2">
                                <label class="form-label fw-bold">Layout Type</label>
                                <select id="seatLayoutType" class="form-select" onchange="toggleSeatingInputs()">
                                    <option value="none">No Seating Map</option>
                                    <option value="grid">Interactive Grid Map</option>
                                    <option value="open">Open (Just Total Count)</option>
                                </select>
                            </div>

                            <div class="col-md-2 mb-2 seat-grid-input d-none">
                                <label class="form-label">Rows</label>
                                <input type="number" id="seatRows" class="form-control" value="5" min="1" max="50">
                            </div>
                            <div class="col-md-2 mb-2 seat-grid-input d-none">
                                <label class="form-label">Columns</label>
                                <input type="number" id="seatCols" class="form-control" value="5" min="1" max="50">
                            </div>
                            <div class="col-md-3 mb-2 seat-grid-input d-none">
                                <label class="form-label">Number Prefix</label>
                                <input type="text" id="seatPrefix" class="form-control" placeholder="e.g. A" value="">
                            </div>
                            <div class="col-md-2 mb-2 seat-grid-input d-none d-flex align-items-end">
                                <button type="button" class="btn btn-dark w-100" onclick="generateSeatingGrid()"><i class="fas fa-magic"></i> Draw Map</button>
                            </div>

                            <div class="col-md-4 mb-2 seat-open-input d-none">
                                <label class="form-label">Total Number of Seats</label>
                                <input type="number" id="seatTotalOpen" class="form-control" placeholder="e.g. 150">
                            </div>

                            <div class="col-12 mt-3 text-center seat-grid-input d-none">
                                <p class="text-muted small mb-1"><i class="fas fa-mouse-pointer"></i> Click a seat below to block/reserve it (Turns Red). It will be subtracted from the valid capacity.</p>
                                <div id="seatingGridBuilder" class="d-inline-block border p-3 bg-white rounded shadow-sm" style="overflow-x: auto; max-width: 100%;">
                                    <div class="text-muted fst-italic py-3">Map not generated yet.</div>
                                </div>
                            </div>

                            <div class="col-12 mt-2">
                                <div class="badge bg-success fs-6 mt-1 w-100 p-2 text-start"><i class="fas fa-chair me-2"></i>Calculated Valid Capacity: <span id="seatCalculatedTotal">0</span></div>
                            </div>

                            <input type="hidden" name="seating_plan" id="modalSeatingPlanJson" value="">
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mt-2">4. Dimensions & Location (OpenStreetMap)</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Width (ft)</label>
                                <input type="number" step="0.01" name="width" id="modalWidth" class="form-control" placeholder="e.g. 20">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Length (ft)</label>
                                <input type="number" step="0.01" name="length" id="modalLength" class="form-control" placeholder="e.g. 30">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Square Feet</label>
                                <input type="text" id="modalSqft" class="form-control bg-light text-primary fw-bold" readonly placeholder="Auto-calculated">
                            </div>

                            <div class="col-md-12 mb-3">
                                <p class="text-muted small mb-1"><i class="fas fa-map-marker-alt"></i> Click on the map to automatically set the Geo-Location for this room.</p>
                                <div id="adminMap"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="modalLat" class="form-control" placeholder="28.631123">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="modalLng" class="form-control" placeholder="77.071437">
                            </div>
                        </div>

                        <h6 class="text-primary border-bottom pb-2 mt-2">5. IT & Hardware Setup</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Networking</label><select name="networking" id="modalNet" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Interactive Board</label><select name="interactive_board" id="modalBoard" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Wi-Fi Router</label><select name="wifi_router" id="modalWifi" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">CCTV</label><select name="cctv" id="modalCctv" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">UPS</label><select name="ups" id="modalUps" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Audio/Video</label><select name="audio_video" id="modalAv" class="form-select"><option value="">N/A</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
                            <div class="col-md-12 mb-3"><label class="form-label">Remarks</label><input type="text" name="remarks" id="modalRemarks" class="form-control" placeholder="Any special notes about this room..."></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100" onclick="finalizeSeatingJson()"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade no-print" id="mapInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white border-info border-bottom border-3">
                    <h5 class="modal-title"><i id="mapViewIcon" class="fas fa-door-open me-2 text-info"></i> Room <span id="mapViewRoomNo"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center bg-light">

                    <div class="row mb-4 px-2">
                        <div class="col-sm-6 mb-3 mb-sm-0">
                            <span class="badge bg-primary mb-2 shadow-sm"><i class="fas fa-map me-1"></i> Floor Plan Location</span>
                            <img id="mapViewFloorImage" src="" alt="Floor Plan" class="img-fluid rounded shadow-sm border border-2 border-primary" style="height: 180px; width: 100%; object-fit: cover;">
                        </div>
                        <div class="col-sm-6">
                            <span class="badge bg-success mb-2 shadow-sm"><i class="fas fa-camera me-1"></i> Actual Room Photo</span>
                            <img id="mapViewRoomPhoto" src="" alt="Room Photo" class="img-fluid rounded shadow-sm border border-2 border-success" style="height: 180px; width: 100%; object-fit: cover;">
                        </div>
                    </div>

                    <h4 class="text-primary fw-bold mb-2" id="mapViewDesc"></h4>

                    <div class="mb-4">
                        <span class="badge bg-secondary px-3 py-2 fs-6 shadow-sm me-2"><i class="fas fa-ruler-combined me-2"></i><span id="mapViewDimensions">Dimensions Not Set</span></span>
                        <span class="badge bg-info text-dark px-3 py-2 fs-6 shadow-sm"><i class="fas fa-chair me-2"></i>Capacity: <span id="mapViewCapacity">Unknown</span></span>
                    </div>

                    <div id="viewSeatingBlock" class="mb-4 d-none p-3 bg-white border rounded text-center shadow-sm">
                        <h6 class="text-primary border-bottom pb-2 mb-3 text-start"><i class="fas fa-th me-2"></i>Seating Plan Layout</h6>
                        <div class="d-flex justify-content-center mb-2 small text-muted">
                            <span class="me-3"><span class="d-inline-block bg-success rounded-circle" style="width:10px;height:10px;"></span> Available</span>
                            <span><span class="d-inline-block bg-danger rounded-circle" style="width:10px;height:10px;"></span> Blocked</span>
                        </div>
                        <div id="viewSeatingMap" style="overflow-x:auto;"></div>
                    </div>

                    <div class="mb-4 p-3 bg-white border rounded text-start shadow-sm">
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-users me-2"></i>Assigned Room Members</h6>
                        <div id="mapViewMembers" class="row g-2">
                            </div>
                    </div>

                    <div class="row g-3 text-start mb-4">
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-network-wired text-primary me-2"></i> Net: <strong id="mapViewNet"></strong></div></div>
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-chalkboard-teacher text-success me-2"></i> Board: <strong id="mapViewBoard"></strong></div></div>
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-wifi text-info me-2"></i> Wi-Fi: <strong id="mapViewWifi"></strong></div></div>
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-video text-danger me-2"></i> CCTV: <strong id="mapViewCctv"></strong></div></div>
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-plug text-warning me-2"></i> UPS: <strong id="mapViewUps"></strong></div></div>
                        <div class="col-6"><div class="p-2 bg-white border rounded shadow-sm"><i class="fas fa-photo-video text-secondary me-2"></i> A/V: <strong id="mapViewAv"></strong></div></div>
                    </div>

                    <div id="viewMapWrapper" class="text-start d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-dark"><i class="fas fa-map-pin me-1"></i> Global Geo-Location</span>
                            <a id="mapViewGoogleLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary py-0 shadow-sm"><i class="fas fa-external-link-alt"></i> Open in Google Maps</a>
                        </div>
                        <div id="viewMap" class="shadow-sm"></div>
                    </div>

                    <div id="mapViewRemarksBox" class="mt-3 text-start p-3 bg-white border border-info rounded shadow-sm text-muted d-none">
                        <strong><i class="fas fa-info-circle text-info"></i> Remarks: </strong> <span id="mapViewRemarks" class="fst-italic"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.0/vanilla-tilt.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <script>
        const allBuildingRooms = <?= json_encode($allRooms, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const orgStaffList = <?= json_encode($orgStaff, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const floorImageMapping = {
            "Ground Floor": "./images/gf.jpg",
            "First Floor": "./images/ff.jpg",
            "Second Floor": "./images/sf.jpg"
        };

        let adminMap, adminMarker;
        let viewMap, viewMarker;
        const defaultLat = 28.631123;
        const defaultLng = 77.071437;

        // --- EXPORT ENGINE ---
        function exportData(type) {
            const rows = document.querySelectorAll('#manageTable tbody tr');
            let data = [];
            const headers = ['Floor', 'Room', 'Description', 'Dimensions', 'Capacity', 'Staff Assigned', 'Net', 'Board', 'Wi-Fi', 'CCTV', 'UPS', 'A/V', 'Remarks'];

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    let rowData = [];
                    const cells = row.querySelectorAll('td');
                    for (let i = 0; i < 13; i++) {
                        if (cells[i]) {
                            rowData.push(cells[i].textContent.trim().replace(/\r?\n|\r/g, ' '));
                        }
                    }
                    if (rowData.length > 0) data.push(rowData);
                }
            });

            if (data.length === 0) {
                alert("No filtered data available to export!");
                return;
            }

            const dateStr = new Date().toISOString().slice(0, 10);
            const fileName = `IGIPESS_Filtered_Rooms_${dateStr}`;

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
            }
            else if (type === 'pdf') {
                let html = `
                    <div style="font-family: Arial, sans-serif; padding: 20px;">
                        <h2 style="text-align:center; color:#1e3c72; margin-bottom: 5px;">IGIPESS Interactive Directory</h2>
                        <p style="text-align:center; font-size: 12px; color: #555; margin-top: 0;">Filtered Room & Hardware Export (${dateStr})</p>
                        <table style="width:100%; border-collapse: collapse; font-size: 10px;" border="1">
                            <tr style="background-color:#f2f2f2; text-align: left;">
                                <th style="padding:6px;">${headers.join('</th><th style="padding:6px;">')}</th>
                            </tr>`;
                data.forEach(row => {
                    html += `<tr><td style="padding:5px;">${row.join('</td><td style="padding:5px;">')}</td></tr>`;
                });
                html += '</table></div>';

                html2pdf().set({
                    margin: 10,
                    filename: fileName + '.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
                }).from(tempDiv).save();
            }
        }

        // --- SEARCH ENGINE ---
        document.getElementById('globalSearch').addEventListener('input', function(e) {
            const searchTerms = e.target.value.toLowerCase().trim().split(/\s+/);
            const checkMatch = (data) => {
                if (!data) return false;
                return searchTerms.every(term => data.includes(term));
            };

            document.querySelectorAll('.room-card-wrapper').forEach(card => {
                const searchData = card.getAttribute('data-search');
                card.style.display = checkMatch(searchData) ? '' : 'none';
            });

            document.querySelectorAll('.manage-row').forEach(row => {
                const searchData = row.getAttribute('data-search');
                row.style.display = checkMatch(searchData) ? '' : 'none';
            });

            document.querySelectorAll('.interactive-room').forEach(svgRoom => {
                const searchData = svgRoom.getAttribute('data-search');
                if (checkMatch(searchData) || e.target.value.trim() === '') {
                    svgRoom.classList.remove('room-hidden');
                } else {
                    svgRoom.classList.add('room-hidden');
                }
            });

            document.querySelectorAll('.floor-section').forEach(section => {
                const visibleCards = section.querySelectorAll('.room-card-wrapper[style=""]').length +
                                     section.querySelectorAll('.room-card-wrapper:not([style*="display: none"])').length;
                if(visibleCards === 0 && e.target.value.trim() !== '') {
                    section.style.display = 'none';
                } else {
                    section.style.display = '';
                }
            });
        });

        // --- IFRAME PERFECT RESIZE ENGINE ---
        function autoResizeIframe() {
            if (window.frameElement) {
                window.frameElement.style.height = 'auto';
                window.frameElement.style.height = document.documentElement.scrollHeight + 50 + 'px';
            }
        }
        window.addEventListener('load', autoResizeIframe);
        window.addEventListener('resize', autoResizeIframe);

        if (typeof VanillaTilt !== 'undefined') {
            VanillaTilt.init(document.querySelectorAll(".room-card"));
        }

        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', () => {
                if (typeof VanillaTilt !== 'undefined') VanillaTilt.init(document.querySelectorAll(".room-card"));
                autoResizeIframe();
            });
        });

        function switchTab(tabIdHash) {
            const targetBtn = document.getElementById('btn-tab-' + tabIdHash);
            if(targetBtn) {
                targetBtn.click();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // --- COMPREHENSIVE SEATING SYSTEM (FRONTEND) ---
        let currentBlockedSeats = [];

        function toggleSeatingInputs() {
            let type = document.getElementById('seatLayoutType').value;
            document.querySelectorAll('.seat-grid-input').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.seat-open-input').forEach(el => el.classList.add('d-none'));

            if (type === 'grid') {
                document.querySelectorAll('.seat-grid-input').forEach(el => el.classList.remove('d-none'));
            } else if (type === 'open') {
                document.querySelectorAll('.seat-open-input').forEach(el => el.classList.remove('d-none'));
            }
            updateSeatTotalDisplay();
            autoResizeIframe();
        }

        function generateSeatingGrid(existingBlocked = []) {
            currentBlockedSeats = existingBlocked;
            let rows = parseInt(document.getElementById('seatRows').value) || 0;
            let cols = parseInt(document.getElementById('seatCols').value) || 0;
            let prefix = document.getElementById('seatPrefix').value.trim();
            let container = document.getElementById('seatingGridBuilder');

            if(rows <= 0 || cols <= 0) {
                container.innerHTML = '<div class="text-danger fst-italic py-3">Please enter valid Rows and Columns.</div>';
                return;
            }

            let html = `<div class="seating-grid" style="grid-template-columns: repeat(${cols}, 1fr);">`;
            // Add a Teacher Desk indicator at the top
            html += `<div style="grid-column: 1 / -1;"><div class="seat-desk">TEACHER / INSTRUCTOR</div></div>`;

            for(let r = 1; r <= rows; r++) {
                for(let c = 1; c <= cols; c++) {
                    let seatNum = (r - 1) * cols + c;
                    let seatLabel = prefix + seatNum;
                    let seatId = `${r}-${c}`;
                    let isBlocked = currentBlockedSeats.includes(seatId);
                    let blockClass = isBlocked ? 'seat-blocked' : 'seat-active';

                    html += `<div class="seat-box editable ${blockClass}" data-id="${seatId}" onclick="toggleSeatBlock(this)">${seatLabel}</div>`;
                }
            }
            html += `</div>`;
            container.innerHTML = html;
            updateSeatTotalDisplay();
            autoResizeIframe();
        }

        function toggleSeatBlock(el) {
            let seatId = el.getAttribute('data-id');
            if(currentBlockedSeats.includes(seatId)) {
                currentBlockedSeats = currentBlockedSeats.filter(id => id !== seatId);
                el.classList.remove('seat-blocked');
                el.classList.add('seat-active');
            } else {
                currentBlockedSeats.push(seatId);
                el.classList.remove('seat-active');
                el.classList.add('seat-blocked');
            }
            updateSeatTotalDisplay();
        }

        function updateSeatTotalDisplay() {
            let type = document.getElementById('seatLayoutType').value;
            let total = 0;

            if(type === 'grid') {
                let rows = parseInt(document.getElementById('seatRows').value) || 0;
                let cols = parseInt(document.getElementById('seatCols').value) || 0;
                total = (rows * cols) - currentBlockedSeats.length;
            } else if (type === 'open') {
                total = parseInt(document.getElementById('seatTotalOpen').value) || 0;
            }

            document.getElementById('seatCalculatedTotal').innerText = total;
            document.getElementById('seatTotalOpen').addEventListener('input', function() {
                if(document.getElementById('seatLayoutType').value === 'open') {
                    document.getElementById('seatCalculatedTotal').innerText = this.value || 0;
                }
            });
        }

        function finalizeSeatingJson() {
            let type = document.getElementById('seatLayoutType').value;
            let finalJson = "";

            if (type === 'grid') {
                let obj = {
                    type: 'grid',
                    r: parseInt(document.getElementById('seatRows').value) || 0,
                    c: parseInt(document.getElementById('seatCols').value) || 0,
                    p: document.getElementById('seatPrefix').value.trim(),
                    b: currentBlockedSeats
                };
                finalJson = JSON.stringify(obj);
            } else if (type === 'open') {
                let obj = {
                    type: 'open',
                    total: parseInt(document.getElementById('seatTotalOpen').value) || 0
                };
                finalJson = JSON.stringify(obj);
            }

            document.getElementById('modalSeatingPlanJson').value = finalJson;
        }

        function renderReadOnlyMap(containerId, jsonString) {
            let container = document.getElementById(containerId);
            container.innerHTML = "";

            if(!jsonString) return false;
            let data = {};
            try { data = JSON.parse(jsonString); } catch(e) { return false; }

            if(data.type === 'grid') {
                let html = `<div class="seating-grid" style="grid-template-columns: repeat(${data.c}, 1fr);">`;
                html += `<div style="grid-column: 1 / -1;"><div class="seat-desk">TEACHER / INSTRUCTOR</div></div>`;

                for(let r = 1; r <= data.r; r++) {
                    for(let c = 1; c <= data.c; c++) {
                        let seatNum = (r - 1) * data.c + c;
                        let seatLabel = (data.p || '') + seatNum;
                        let seatId = `${r}-${c}`;
                        let isBlocked = (data.b || []).includes(seatId);
                        let blockClass = isBlocked ? 'seat-blocked' : 'seat-active';

                        html += `<div class="seat-box ${blockClass}">${seatLabel}</div>`;
                    }
                }
                html += `</div>`;
                container.innerHTML = html;
                return true;
            }
            return false;
        }

        // --- VIEW MODAL LOGIC ---
        function openViewModal(roomNo) {
            const roomData = allBuildingRooms.find(r => String(r.room_no) === String(roomNo));

            if (roomData) {
                document.getElementById('mapViewRoomNo').innerText = roomData.room_no;
                document.getElementById('mapViewDesc').innerText = roomData.description;
                document.getElementById('mapViewIcon').className = 'fas me-2 text-info ' + roomData.icon;

                const floorImgSrc = floorImageMapping[roomData.floor] || "./images/gf.jpg";
                document.getElementById('mapViewFloorImage').src = floorImgSrc;

                const roomImgSrc = roomData.room_image ? "./images/" + roomData.room_image : "./images/1.jpg";
                document.getElementById('mapViewRoomPhoto').src = roomImgSrc;

                if (roomData.width && roomData.length && roomData.sqft) {
                    document.getElementById('mapViewDimensions').innerText = `${roomData.width} ft × ${roomData.length} ft = ${roomData.sqft} Sq.Ft`;
                } else {
                    document.getElementById('mapViewDimensions').innerText = "Dimensions Not Set";
                }

                document.getElementById('mapViewCapacity').innerText = roomData.total_capacity > 0 ? `${roomData.total_capacity} Seats` : 'Unknown';

                // Display Interactive Seat Map if available
                let mapWrapper = document.getElementById('viewSeatingBlock');
                if (renderReadOnlyMap('viewSeatingMap', roomData.seating_plan)) {
                    mapWrapper.classList.remove('d-none');
                } else {
                    mapWrapper.classList.add('d-none');
                }

                // Render Members
                let membersHtml = '';
                if (roomData.room_members && roomData.room_members !== "null") {
                    let parsedMembers = [];
                    try { parsedMembers = JSON.parse(roomData.room_members); } catch(e){}

                    parsedMembers.forEach(id => {
                        let staff = orgStaffList.find(s => s.id == id);
                        if(staff) {
                            let isCharge = (roomData.in_charge_id == staff.id);
                            let badge = isCharge ? '<span class="badge bg-danger ms-2 shadow-sm"><i class="fas fa-star text-warning"></i> IN CHARGE</span>' : '';
                            let border = isCharge ? 'border-danger border-2' : 'border-secondary';
                            let pic = staff.pic ? './images/' + staff.pic : './images/default_user.png';

                            membersHtml += `
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-2 border rounded shadow-sm ${isCharge ? 'bg-light' : 'bg-white'}">
                                        <img src="${pic}" class="rounded-circle border ${border} me-3" width="45" height="45" style="object-fit:cover;" onerror="this.src='./images/1.jpg';">
                                        <div class="text-start">
                                            <div class="fw-bold mb-0" style="font-size:0.9rem;">${staff.name} ${badge}</div>
                                            <div class="text-muted mt-1" style="font-size:0.75rem;"><span class="badge bg-secondary text-white">${staff.category}</span> ${staff.role}</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }

                if(membersHtml === '') {
                    membersHtml = '<div class="col-12"><p class="text-muted small mb-0 fst-italic">No members currently assigned to this room.</p></div>';
                }
                document.getElementById('mapViewMembers').innerHTML = membersHtml;

                if (roomData.latitude && roomData.longitude) {
                    document.getElementById('viewMapWrapper').classList.remove('d-none');
                    let lat = parseFloat(roomData.latitude);
                    let lng = parseFloat(roomData.longitude);

                    document.getElementById('mapViewGoogleLink').href = `https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d11779.47360734976!2d77.07083470314716!3d28.628010064343254!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x57f44963a604a8b0!2sIndira+Gandhi+Institute+of+Physical+Education+%26+Sports+Sciences!5e0!3m2!1sen!2sus!4v1553252979402${lat},${lng}`;

                    if(!viewMap) {
                        viewMap = L.map('viewMap').setView([lat, lng], 18);
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(viewMap);
                        viewMarker = L.marker([lat, lng]).addTo(viewMap).bindPopup("<b>Room " + roomData.room_no + "</b>").openPopup();
                    } else {
                        viewMap.setView([lat, lng], 18);
                        viewMarker.setLatLng([lat, lng]).bindPopup("<b>Room " + roomData.room_no + "</b>").openPopup();
                    }
                } else {
                    document.getElementById('viewMapWrapper').classList.add('d-none');
                }

                document.getElementById('mapViewNet').innerText = roomData.networking || 'No';
                document.getElementById('mapViewBoard').innerText = roomData.interactive_board || 'No';
                document.getElementById('mapViewWifi').innerText = roomData.wifi_router || 'No';
                document.getElementById('mapViewCctv').innerText = roomData.cctv || 'No';
                document.getElementById('mapViewUps').innerText = roomData.ups || 'No';
                document.getElementById('mapViewAv').innerText = roomData.audio_video || 'No';

                if (roomData.remarks) {
                    document.getElementById('mapViewRemarksBox').classList.remove('d-none');
                    document.getElementById('mapViewRemarks').innerText = roomData.remarks;
                } else {
                    document.getElementById('mapViewRemarksBox').classList.add('d-none');
                }

                new bootstrap.Modal(document.getElementById('mapInfoModal')).show();
            }
        }

        // --- ROOM CLICK HANDLER FOR 3D MAP ---
        document.querySelectorAll('.interactive-room').forEach(roomEl => {
            roomEl.addEventListener('click', function() {
                openViewModal(this.getAttribute('data-room'));
            });
        });

        <?php if ($admin === "Yes"): ?>

        // Staff Member Modal Search Filter
        document.getElementById('staffSearchModal').addEventListener('input', function(e) {
            let val = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.staff-item').forEach(item => {
                if (item.getAttribute('data-name').includes(val)) {
                    item.style.setProperty('display', 'flex', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });
        });

        document.querySelectorAll('.incharge-radio').forEach(rb => {
            rb.addEventListener('change', function() {
                if(this.checked) {
                    document.getElementById('staff_' + this.value).checked = true;
                }
            });
        });

        function calculateSqFt() {
            let w = parseFloat(document.getElementById('modalWidth').value) || 0;
            let l = parseFloat(document.getElementById('modalLength').value) || 0;
            if (w > 0 && l > 0) {
                document.getElementById('modalSqft').value = (w * l).toFixed(2) + " Sq.Ft";
            } else {
                document.getElementById('modalSqft').value = "";
            }
        }
        document.getElementById('modalWidth').addEventListener('input', calculateSqFt);
        document.getElementById('modalLength').addEventListener('input', calculateSqFt);

        const roomModalEl = document.getElementById('roomModal');
        roomModalEl.addEventListener('shown.bs.modal', function () {
            let lat = parseFloat(document.getElementById('modalLat').value) || defaultLat;
            let lng = parseFloat(document.getElementById('modalLng').value) || defaultLng;

            if(!adminMap) {
                adminMap = L.map('adminMap').setView([lat, lng], 18);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(adminMap);

                adminMarker = L.marker([lat, lng], {draggable: true}).addTo(adminMap);

                adminMap.on('click', function(e) {
                    adminMarker.setLatLng(e.latlng);
                    document.getElementById('modalLat').value = e.latlng.lat.toFixed(6);
                    document.getElementById('modalLng').value = e.latlng.lng.toFixed(6);
                });

                adminMarker.on('dragend', function(e) {
                    var position = adminMarker.getLatLng();
                    document.getElementById('modalLat').value = position.lat.toFixed(6);
                    document.getElementById('modalLng').value = position.lng.toFixed(6);
                });
            } else {
                adminMap.setView([lat, lng], 18);
                adminMarker.setLatLng([lat, lng]);
                adminMap.invalidateSize();
            }
            autoResizeIframe();
        });

        document.getElementById('modalLat').addEventListener('change', updateAdminPin);
        document.getElementById('modalLng').addEventListener('change', updateAdminPin);
        function updateAdminPin() {
            if(adminMap) {
                let lat = parseFloat(document.getElementById('modalLat').value) || defaultLat;
                let lng = parseFloat(document.getElementById('modalLng').value) || defaultLng;
                adminMap.setView([lat, lng], 18);
                adminMarker.setLatLng([lat, lng]);
            }
        }

        function openModal(action, data = null, tabId = 'manage') {
            document.getElementById('modalAction').value = action;
            document.getElementById('modalTitle').innerText = action === 'add' ? 'Add New Room' : 'Edit Room';
            document.getElementById('modalTab').value = tabId;
            document.getElementById('modalImage').value = '';

            // Reset Staff Checks
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.incharge-radio').forEach(rb => rb.checked = false);

            // Reset Seating
            document.getElementById('seatLayoutType').value = 'none';
            document.getElementById('seatRows').value = '5';
            document.getElementById('seatCols').value = '5';
            document.getElementById('seatPrefix').value = '';
            document.getElementById('seatTotalOpen').value = '';
            currentBlockedSeats = [];
            document.getElementById('seatingGridBuilder').innerHTML = '<div class="text-muted fst-italic py-3">Map not generated yet.</div>';
            toggleSeatingInputs();

            if (action === 'edit' && data) {
                document.getElementById('modalId').value = data.id;
                document.getElementById('modalFloor').value = data.floor;
                document.getElementById('modalRoomNo').value = data.room_no;
                document.getElementById('modalDesc').value = data.description;
                document.getElementById('modalIcon').value = data.icon || 'fa-door-open';

                document.getElementById('modalOldImage').value = data.room_image || '';
                if(data.room_image) {
                    document.getElementById('currentImageWrapper').classList.remove('d-none');
                    document.getElementById('currentImagePreview').src = './images/' + data.room_image;
                } else {
                    document.getElementById('currentImageWrapper').classList.add('d-none');
                }

                // Populate Dimensions & Location
                document.getElementById('modalWidth').value = data.width || '';
                document.getElementById('modalLength').value = data.length || '';
                document.getElementById('modalLat').value = data.latitude || '';
                document.getElementById('modalLng').value = data.longitude || '';
                calculateSqFt();

                // Populate Seating Plan
                if(data.seating_plan) {
                    let sp = {};
                    try{ sp = JSON.parse(data.seating_plan); } catch(e){}
                    if(sp.type) {
                        document.getElementById('seatLayoutType').value = sp.type;
                        if(sp.type === 'grid') {
                            document.getElementById('seatRows').value = sp.r || 5;
                            document.getElementById('seatCols').value = sp.c || 5;
                            document.getElementById('seatPrefix').value = sp.p || '';
                            generateSeatingGrid(sp.b || []);
                        } else if(sp.type === 'open') {
                            document.getElementById('seatTotalOpen').value = sp.total || 0;
                        }
                        toggleSeatingInputs();
                    }
                }

                // Populate Staff Members
                if (data.room_members && data.room_members !== "null") {
                    let parsedMembers = [];
                    try { parsedMembers = JSON.parse(data.room_members); } catch(e){}
                    parsedMembers.forEach(id => {
                        let cb = document.getElementById('staff_' + id);
                        if (cb) cb.checked = true;
                    });
                }

                // Populate In Charge
                if (data.in_charge_id) {
                    let rb = document.getElementById('incharge_' + data.in_charge_id);
                    if (rb) rb.checked = true;
                }

                document.getElementById('modalNet').value = data.networking || '';
                document.getElementById('modalBoard').value = data.interactive_board || '';
                document.getElementById('modalWifi').value = data.wifi_router || '';
                document.getElementById('modalCctv').value = data.cctv || '';
                document.getElementById('modalUps').value = data.ups || '';
                document.getElementById('modalAv').value = data.audio_video || '';
                document.getElementById('modalRemarks').value = data.remarks || '';
            } else {
                ['Id','RoomNo','Desc','Net','Board','Wifi','Cctv','Ups','Av','Remarks','OldImage','Width','Length','Lat','Lng','Sqft'].forEach(f => document.getElementById('modal'+f).value = '');
                document.getElementById('modalIcon').value = 'fa-door-open';
                document.getElementById('currentImageWrapper').classList.add('d-none');
            }
            new bootstrap.Modal(document.getElementById('roomModal')).show();
        }
        <?php endif; ?>
    </script>
</body>
</html>