<?php
include ("./log/config/conn.php"); 
session_start();

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data['action'] === 'save_client') {
        $unique_id = generateClientID();
        $stmt = $conn->prepare("INSERT INTO sports_clients (client_unique_id, full_name, phone, email, age, gender, sport_type, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssiss", $unique_id, $data['full_name'], $data['phone'], $data['email'], $data['age'], $data['gender'], $data['sport_type']);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'client_id' => $unique_id, 'client_db_id' => $conn->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
        }
        exit;
    }

    if ($data['action'] === 'save_measurements') {
        $stmt = $conn->prepare("INSERT INTO sports_measurements (client_id, session_id, height_cm, weight_kg, bmi, waist_circumference_cm, neck_circumference_cm, hip_circumference_cm, body_fat_percentage, muscle_mass_kg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddddddd", $data['client_id'], $data['session_id'], $data['height'], $data['weight'], $data['bmi'], $data['waist'], $data['neck'], $data['hip'], $data['body_fat'], $data['muscle_mass']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($data['action'] === 'save_biomechanics') {
        $stmt = $conn->prepare("INSERT INTO sports_biomechanics (client_id, session_id, exercise_type, knee_angle, hip_angle, back_angle, ankle_angle, neck_angle, shoulder_angle, elbow_angle, wrist_angle, pelvic_tilt, com_x, com_y, symmetry_score, posture_score, risk_level, alerts, recommendations, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiiiiiiiiddiissss", $data['client_id'], $data['session_id'], $data['exercise'], $data['knee'], $data['hip'], $data['back'], $data['ankle'], $data['neck'], $data['shoulder'], $data['elbow'], $data['wrist'], $data['pelvic_tilt'], $data['com_x'], $data['com_y'], $data['symmetry_score'], $data['posture_score'], $data['risk_level'], $data['alerts'], $data['recommendations'], $data['photo_path']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($data['action'] === 'get_records') {
        $response = ['status' => 'success', 'clients' => [], 'measurements' => [], 'biomechanics' => []];
        $clients = $conn->query("SELECT * FROM sports_clients ORDER BY id DESC LIMIT 50");
        $response['clients'] = $clients->fetch_all(MYSQLI_ASSOC);
        $measQuery = "SELECT m.*, c.full_name, c.sport_type FROM sports_measurements m JOIN sports_clients c ON m.client_id = c.id ORDER BY m.measurement_date DESC LIMIT 100";
        $measurements = $conn->query($measQuery);
        $response['measurements'] = $measurements->fetch_all(MYSQLI_ASSOC);
        $bioQuery = "SELECT b.*, c.full_name, c.sport_type, c.age, c.gender, c.phone, c.email FROM sports_biomechanics b JOIN sports_clients c ON b.client_id = c.id ORDER BY b.analysis_timestamp DESC LIMIT 100";
        $biomechanics = $conn->query($bioQuery);
        $response['biomechanics'] = $biomechanics->fetch_all(MYSQLI_ASSOC);
        echo json_encode($response);
        exit;
    }

    if ($data['action'] === 'delete_record') {
        $table = $conn->real_escape_string($data['table']);
        $id = intval($data['id']);
        $conn->query("DELETE FROM $table WHERE id = $id");
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($data['action'] === 'get_athlete_comparison') {
        $athlete1 = intval($data['athlete1']);
        $athlete2 = intval($data['athlete2']);
        $query1 = "SELECT AVG(knee_angle) as knee, AVG(hip_angle) as hip, AVG(back_angle) as back, AVG(ankle_angle) as ankle, AVG(posture_score) as score FROM sports_biomechanics WHERE client_id = $athlete1";
        $query2 = "SELECT AVG(knee_angle) as knee, AVG(hip_angle) as hip, AVG(back_angle) as back, AVG(ankle_angle) as ankle, AVG(posture_score) as score FROM sports_biomechanics WHERE client_id = $athlete2";
        $result1 = $conn->query($query1)->fetch_assoc();
        $result2 = $conn->query($query2)->fetch_assoc();
        echo json_encode(['status' => 'success', 'athlete1' => $result1, 'athlete2' => $result2]);
        exit;
    }
}

function generateClientID() { return 'CLI-' . strtoupper(uniqid()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Sports Biomechanics Pro | Advanced Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0a0a1a 0%, #050510 100%); color: #eef; font-family: 'Segoe UI', Roboto, system-ui; padding-bottom: 40px; }
        .navbar { background: rgba(0,0,0,0.9); backdrop-filter: blur(12px); border-bottom: 2px solid #00b4db; }
        .navbar-brand { font-weight: bold; background: linear-gradient(45deg, #00b4db, #00ff88); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .main-container { max-width: 1600px; margin: 90px auto 20px; padding: 0 20px; }

        /* Video Container */
        .video-container { position: relative; width: 100%; border-radius: 28px; overflow: hidden; aspect-ratio: 16/9; background: #000; margin-bottom: 15px; }
        video, canvas { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
        video { position: relative; z-index: 1; }
        canvas { position: absolute; z-index: 2; }
        .capture-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 30; display: none; justify-content: center; align-items: center; flex-direction: column; border-radius: 28px; }
        .capture-overlay.active { display: flex; }
        .capture-guide-circle { width: 200px; height: 200px; border: 4px solid #00b4db; border-radius: 50%; display: flex; justify-content: center; align-items: center; flex-direction: column; background: rgba(0,0,0,0.6); animation: pulse-border 1s infinite; }
        @keyframes pulse-border { 0%, 100% { border-color: #00b4db; } 50% { border-color: #00ff88; } }
        .capture-guide-circle i { font-size: 60px; color: #00b4db; }
        .capture-timer { font-size: 60px; font-weight: bold; color: #ffaa00; margin-top: 20px; }

        /* Cards */
        .client-card { background: rgba(20,20,35,0.95); backdrop-filter: blur(10px); border-radius: 28px; border: 1px solid rgba(0,180,219,0.4); overflow: hidden; margin-bottom: 20px; }
        .client-card .card-header { background: linear-gradient(90deg, rgba(0,180,219,0.2), rgba(0,131,176,0.05)); padding: 15px 20px; border-bottom: 1px solid rgba(0,180,219,0.3); }
        .form-control, .form-select { background: rgba(0,0,0,0.6); border: 1px solid rgba(0,180,219,0.4); color: white; border-radius: 40px; padding: 10px 16px; }
        .form-control:focus, .form-select:focus { outline: none; box-shadow: 0 0 8px #00b4db; background: #0a0a1a; border-color: #00b4db; }
        .measure-card { background: linear-gradient(135deg, rgba(0,180,219,0.1), rgba(0,131,176,0.05)); border-radius: 20px; padding: 12px; text-align: center; border: 1px solid rgba(0,180,219,0.2); }
        .measure-value { font-size: 1.6rem; font-weight: bold; color: #00b4db; }
        .angle-card { background: rgba(0,0,0,0.5); border-radius: 20px; padding: 10px; text-align: center; border-left: 3px solid #00b4db; }
        .angle-value { font-size: 1.4rem; font-weight: bold; color: #00b4db; }
        .exercise-btn { background: transparent; border: 1px solid #00b4db; color: #00b4db; padding: 6px 16px; border-radius: 40px; margin: 3px; transition: all 0.3s; cursor: pointer; }
        .exercise-btn.active, .exercise-btn:hover { background: #00b4db; color: #000; }
        .capture-btn { background: linear-gradient(45deg, #00b4db, #0083b0); border: none; padding: 14px 25px; border-radius: 40px; font-weight: bold; color: white; transition: all 0.3s; }
        .capture-btn:hover { transform: scale(1.02); box-shadow: 0 5px 20px rgba(0,180,219,0.4); }
        .one-click-btn { background: linear-gradient(45deg, #00ff88, #00b4db); font-size: 1.2rem; padding: 16px; }
        .posture-ring { width: 100px; height: 100px; border-radius: 50%; background: conic-gradient(#00b4db 0deg, #333 0deg); display: flex; align-items: center; justify-content: center; margin: 0 auto; }
        .posture-score { font-size: 2rem; font-weight: bold; }

        /* Data Table */
        .data-table-container { background: rgba(20,20,35,0.95); border-radius: 28px; border: 1px solid rgba(0,180,219,0.4); overflow: hidden; backdrop-filter: blur(4px); margin-top: 20px; }
        .card-header-custom { background: linear-gradient(90deg, rgba(0,180,219,0.2), rgba(0,131,176,0.05)); padding: 18px 24px; border-bottom: 1px solid rgba(0,180,219,0.3); }
        .table-search { background: rgba(0,0,0,0.6); border: 1px solid #00b4db; color: white; border-radius: 40px; padding: 8px 18px; transition: all 0.2s; }
        .table-search:focus { outline: none; box-shadow: 0 0 8px #00b4db; background: #0a0a1a; }
        .filter-btn { background: #1e2a3a; border: none; color: #00b4db; border-radius: 40px; padding: 0 18px; transition: 0.2s; }
        .filter-btn:hover { background: #00b4db; color: #000; }
        .record-row { cursor: pointer; transition: all 0.15s; }
        .record-row:hover { background: rgba(0,180,219,0.2); transform: scale(1.002); }
        .delete-row-btn { background: #ff3366; border: none; color: white; padding: 4px 12px; border-radius: 40px; font-size: 12px; transition: 0.2s; }
        .delete-row-btn:hover { background: #ff001a; transform: scale(0.97); }
        .thumbnail-img { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; border: 1px solid #00b4db; cursor: pointer; transition: 0.2s; }
        .thumbnail-img:hover { transform: scale(1.1); border-color: #00ff88; }

        /* Modal Styles */
        .modal-content { background: linear-gradient(145deg, #10101e, #0b0b18); border: 1px solid #00b4db; border-radius: 32px; color: #f0f0f0; box-shadow: 0 25px 40px rgba(0,0,0,0.6); }
        .modal-header { border-bottom: 1px solid rgba(0,180,219,0.4); background: rgba(0,0,0,0.4); border-radius: 32px 32px 0 0; }
        .detail-card { background: rgba(0,0,0,0.45); border-radius: 24px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #00b4db; }
        .angle-badge { background: rgba(0,180,219,0.2); padding: 8px 16px; border-radius: 36px; font-weight: bold; }
        .risk-tag { display: inline-block; padding: 6px 14px; border-radius: 40px; font-weight: bold; font-size: 0.8rem; }
        .risk-low { background: #00ff8833; color: #aaffdd; border-left: 4px solid #00ff88; }
        .risk-moderate { background: #ffaa0033; color: #ffdd99; border-left-color: #ffaa00; }
        .risk-high { background: #ff660033; color: #ffbb99; border-left-color: #ff6600; }
        .risk-critical { background: #ff003344; color: #ffaaaa; border-left-color: #ff0033; }
        .progress-custom { height: 8px; background: #2a2a3a; border-radius: 8px; overflow: hidden; margin: 8px 0; }
        .progress-fill-custom { background: linear-gradient(90deg, #00b4db, #00ff88); height: 100%; border-radius: 8px; }
        .metric-value { font-size: 2rem; font-weight: 800; background: linear-gradient(135deg, #fff, #00b4db); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .chart-box { background: rgba(0,0,0,0.35); border-radius: 24px; padding: 16px; margin-top: 16px; }
        .recommendation-item { background: rgba(0,255,136,0.1); border-left: 3px solid #00ff88; padding: 10px 14px; margin: 8px 0; border-radius: 16px; font-size: 0.9rem; }
        .alert-item-sm { background: rgba(255,51,102,0.15); border-left: 3px solid #ff3366; padding: 8px 12px; border-radius: 14px; margin: 5px 0; }
        .risk-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 0.75rem; }
        .risk-low { background: #00ff88; color: #000; }
        .risk-moderate { background: #ffaa00; color: #000; }
        .risk-high { background: #ff6600; color: #fff; }
        .risk-critical { background: #ff0033; color: #fff; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .alert-item { background: rgba(255,51,102,0.15); border-left: 3px solid #ff3366; padding: 8px 12px; margin: 8px 0; border-radius: 8px; }
        .recommendation-item { background: rgba(0,255,136,0.1); border-left: 3px solid #00ff88; padding: 8px 12px; margin: 8px 0; border-radius: 8px; }

        .loading-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.85); z-index: 1050; display: none; align-items: center; justify-content: center; flex-direction: column; }
        .spinner-custom { width: 50px; height: 50px; border: 4px solid #00b4db; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .status-indicator { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); padding: 5px 12px; border-radius: 20px; font-size: 11px; z-index: 15; color: #ffaa00; }
        .badge-modern { background: #1e2f3f; padding: 5px 12px; border-radius: 30px; font-weight: 500; }
        .btn-outline-accent { border: 1px solid #00b4db; background: transparent; color: #00b4db; border-radius: 40px; transition: 0.2s; }
        .btn-outline-accent:hover { background: #00b4db; color: #000; }

        @keyframes fadeSlide { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
        .modal-body { animation: fadeSlide 0.3s ease; }
        @media (max-width: 992px) { .measure-value { font-size: 1.2rem; } .angle-value { font-size: 1.1rem; } }
    </style>
</head>
<body>

<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="#"><i class="fa-solid fa-microchip"></i> AI Biomechanics Pro | Athletic Intelligence</a>
        <div><span class="badge bg-info bg-opacity-50" id="fps-counter"><i class="fa-regular fa-clock"></i> FPS: 0</span></div>
    </div>
</nav>

<div class="main-container">
    <div class="row g-4">
        <!-- Left Column - Client Registration -->
        <div class="col-lg-4">
            <div class="client-card">
                <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-user-plus"></i> Client Registration</h5></div>
                <div class="card-body p-3">
                    <form id="clientForm">
                        <div class="row g-2">
                            <div class="col-12"><input type="text" class="form-control" id="full_name" placeholder="Full Name *" required></div>
                            <div class="col-6"><input type="tel" class="form-control" id="phone" placeholder="Phone *" required></div>
                            <div class="col-6"><input type="email" class="form-control" id="email" placeholder="Email"></div>
                            <div class="col-4"><input type="number" class="form-control" id="age" placeholder="Age"></div>
                            <div class="col-4"><select class="form-select" id="gender"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                            <div class="col-4"><select class="form-select" id="sport_type"><option value="Cricket">Cricket</option><option value="Football">Football</option><option value="Basketball">Basketball</option><option value="Tennis">Tennis</option><option value="Athletics">Athletics</option><option value="Badminton">Badminton</option><option value="Swimming">Swimming</option><option value="Gym/Training">Gym/Training</option></select></div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="client-card">
                <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-ruler-combined"></i> Body Measurements</h5></div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="height_val">0</div><div class="measure-label">Height (cm)</div><input type="range" class="form-range mt-2" id="height_slider" min="100" max="220" step="0.5" value="170"></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="weight_val">0</div><div class="measure-label">Weight (kg)</div><input type="range" class="form-range mt-2" id="weight_slider" min="30" max="150" step="0.5" value="70"></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="waist_val">0</div><div class="measure-label">Waist (cm)</div><input type="range" class="form-range mt-2" id="waist_slider" min="50" max="150" step="0.5" value="80"></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="neck_val">0</div><div class="measure-label">Neck (cm)</div><input type="range" class="form-range mt-2" id="neck_slider" min="30" max="60" step="0.5" value="38"></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="hip_val">0</div><div class="measure-label">Hip (cm)</div><input type="range" class="form-range mt-2" id="hip_slider" min="60" max="140" step="0.5" value="90"></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="bmi_val">0</div><div class="measure-label">BMI</div><div id="bmi_status" class="small"></div></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="bodyfat_val">0%</div><div class="measure-label">Body Fat %</div></div></div>
                        <div class="col-6"><div class="measure-card"><div class="measure-value" id="muscle_val">0 kg</div><div class="measure-label">Muscle Mass</div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Column - Video Feed -->
        <div class="col-lg-5">
            <div class="video-container">
                <video class="input_video" autoplay playsinline></video>
                <canvas class="output_canvas" width="1280" height="720"></canvas>
                <div class="capture-overlay" id="captureOverlay">
                    <div class="capture-guide-circle"><i class="fa-solid fa-camera"></i><span style="margin-top: 10px;">Capturing</span></div>
                    <div class="capture-timer" id="captureTimer"></div>
                    <div class="capture-status" id="captureStatus">Get ready...</div>
                </div>
                <div class="status-indicator" id="statusIndicator"><i class="fa-regular fa-circle"></i> Ready</div>
            </div>
            <div class="mt-3 text-center">
                <label class="mb-2"><i class="fa-solid fa-dumbbell"></i> Select Activity:</label>
                <div><button class="exercise-btn active" data-exercise="squat" onclick="setExercise('squat')">🏋️ Squat</button><button class="exercise-btn" data-exercise="deadlift" onclick="setExercise('deadlift')">💪 Deadlift</button><button class="exercise-btn" data-exercise="overhead_press" onclick="setExercise('overhead_press')">🏋️ Overhead Press</button><button class="exercise-btn" data-exercise="running" onclick="setExercise('running')">🏃 Running</button><button class="exercise-btn" data-exercise="lunge" onclick="setExercise('lunge')">🦵 Lunge</button></div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-3"><div class="angle-card"><div class="angle-value" id="knee-angle">0°</div><div class="measure-label">Knee Angle</div></div></div>
                <div class="col-3"><div class="angle-card"><div class="angle-value" id="hip-angle">0°</div><div class="measure-label">Hip Angle</div></div></div>
                <div class="col-3"><div class="angle-card"><div class="angle-value" id="back-angle">0°</div><div class="measure-label">Back Angle</div></div></div>
                <div class="col-3"><div class="angle-card"><div class="angle-value" id="ankle-angle">0°</div><div class="measure-label">Ankle Angle</div></div></div>
            </div>
            <button class="capture-btn one-click-btn w-100 mt-3" id="oneClickCaptureBtn"><i class="fa-solid fa-camera-retro"></i> 📸 ONE CLICK CAPTURE & SAVE</button>
        </div>

        <!-- Right Column - Quick Analysis -->
        <div class="col-lg-3">
            <div class="client-card">
                <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-chart-line"></i> Quick Analysis</h5></div>
                <div class="card-body p-3">
                    <div class="text-center mb-3"><span id="risk-indicator" class="risk-badge risk-low">LOW RISK</span><div class="mt-2"><div class="posture-ring" id="posture-ring"><div class="posture-score" id="posture-score">0</div></div><div class="small mt-1">Posture Score</div></div></div>
                    <h6 class="mt-3"><i class="fa-solid fa-triangle-exclamation text-danger"></i> Injury Alerts</h6>
                    <div id="alerts-list" style="max-height: 120px; overflow-y: auto;"><div class="alert-item">Waiting for pose detection...</div></div>
                    <h6 class="mt-3"><i class="fa-solid fa-lightbulb text-success"></i> Corrections</h6>
                    <div id="recommendations-list" style="max-height: 120px; overflow-y: auto;"><div class="recommendation-item">Position yourself in front of camera</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="data-table-container">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
            <h5 class="mb-0"><i class="fa-solid fa-database me-2"></i> Biomechanical Records Database</h5>
            <div class="d-flex gap-2 mt-2 mt-sm-0">
                <input type="text" id="globalSearch" class="table-search" placeholder="🔍 Search athlete...">
                <select id="recordTypeFilter" class="table-search">
                    <option value="all">📁 All Records</option>
                    <option value="clients">👤 Clients</option>
                    <option value="measurements">📏 Measurements</option>
                    <option value="biomechanics">🧬 Biomechanics</option>
                </select>
                <button class="filter-btn" id="refreshBtn"><i class="fa-solid fa-rotate-right"></i> Sync</button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 520px;">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead style="position: sticky; top: 0; background: #0f0f1c; z-index: 2;">
                    <tr class="text-uppercase small"><th>ID</th><th>Type</th><th>Client</th><th>Key Metrics</th><th>Date</th><th>Snapshot</th><th>Action</th></tr>
                </thead>
                <tbody id="dynamicTableBody"><tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-info"></div> Loading athlete data...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL FOR DETAILED ANALYSIS + GRAPHS -->
<div class="modal fade" id="biomechanicsModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"><i class="fa-solid fa-chart-line me-2"></i>Advanced Biomechanical Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalDetailBody"><div class="text-center py-4"><div class="spinner-border text-info"></div> loading insights...</div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-accent" data-bs-dismiss="modal"><i class="fa-regular fa-circle-xmark"></i> Close</button>
                <button type="button" class="btn btn-primary" id="printReportBtn" style="background:#00b4db; border:none;"><i class="fa-regular fa-file-pdf"></i> Export Summary</button>
            </div>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="loading-overlay"><div class="spinner-custom"></div><div class="mt-3 fw-bold">Loading analytics...</div></div>

<script>
    // DOM Elements for Video
    const videoElement = document.querySelector('.input_video');
    const canvasElement = document.querySelector('.output_canvas');
    const canvasCtx = canvasElement.getContext('2d');
    const kneeAngleElem = document.getElementById('knee-angle');
    const hipAngleElem = document.getElementById('hip-angle');
    const backAngleElem = document.getElementById('back-angle');
    const ankleAngleElem = document.getElementById('ankle-angle');
    const riskIndicator = document.getElementById('risk-indicator');
    const alertsList = document.getElementById('alerts-list');
    const recommendationsList = document.getElementById('recommendations-list');
    const postureScoreElem = document.getElementById('posture-score');
    const fpsCounter = document.getElementById('fps-counter');
    const captureOverlay = document.getElementById('captureOverlay');
    const captureTimer = document.getElementById('captureTimer');
    const captureStatus = document.getElementById('captureStatus');
    const statusIndicator = document.getElementById('statusIndicator');
    const oneClickCaptureBtn = document.getElementById('oneClickCaptureBtn');

    let currentExercise = 'squat';
    let frameCount = 0;
    let lastTime = performance.now();
    let currentAngles = { knee: 0, hip: 0, back: 0, ankle: 0, neck: 0, shoulder: 0, elbow: 0, wrist: 0, pelvic_tilt: 0, com_x: 0, com_y: 0, symmetry: 100 };
    let currentRisk = 'low';
    let currentScore = 0;
    let currentAlerts = [];
    let currentRecommendations = [];
    let currentPhotoData = null;
    let isCapturing = false;
    let captureTimerInterval = null;
    let allRecords = { clients: [], measurements: [], biomechanics: [] };
    let chartsCache = { anglesChart: null };

    const thresholds = {
        squat: { knee: { min: 70, max: 110, optimal: [90,100] }, hip: { min: 60, max: 100, optimal: [70,90] }, back: { min: 0, max: 25, optimal: [0,15] }, ankle: { min: 50, max: 90, optimal: [60,80] }, neck: { min: 0, max: 15, optimal: [0,10] }, shoulder: { min: 160, max: 180, optimal: [170,180] } },
        deadlift: { knee: { min: 120, max: 160, optimal: [130,150] }, hip: { min: 20, max: 60, optimal: [30,50] }, back: { min: 0, max: 15, optimal: [0,10] }, ankle: { min: 70, max: 100, optimal: [80,90] }, neck: { min: 0, max: 20, optimal: [0,15] }, shoulder: { min: 150, max: 175, optimal: [160,170] } },
        overhead_press: { knee: { min: 170, max: 180, optimal: [175,180] }, hip: { min: 170, max: 180, optimal: [175,180] }, back: { min: 0, max: 20, optimal: [0,10] }, ankle: { min: 80, max: 100, optimal: [85,95] }, neck: { min: 0, max: 10, optimal: [0,5] }, shoulder: { min: 120, max: 180, optimal: [150,180] } },
        running: { knee: { min: 140, max: 175, optimal: [150,170] }, hip: { min: 150, max: 180, optimal: [160,175] }, back: { min: 0, max: 20, optimal: [5,15] }, ankle: { min: 60, max: 110, optimal: [70,100] }, neck: { min: 0, max: 25, optimal: [5,20] }, shoulder: { min: 60, max: 120, optimal: [80,100] } },
        lunge: { knee: { min: 80, max: 110, optimal: [85,100] }, hip: { min: 70, max: 100, optimal: [75,95] }, back: { min: 0, max: 20, optimal: [0,15] }, ankle: { min: 60, max: 90, optimal: [65,85] }, neck: { min: 0, max: 15, optimal: [0,10] }, shoulder: { min: 170, max: 180, optimal: [175,180] } }
    };

    function initMeasurements() {
        ['height_slider', 'weight_slider', 'waist_slider', 'neck_slider', 'hip_slider'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateMeasurements);
        });
        updateMeasurements();
    }

    function updateMeasurements() {
        const height = parseFloat(document.getElementById('height_slider').value);
        const weight = parseFloat(document.getElementById('weight_slider').value);
        const waist = parseFloat(document.getElementById('waist_slider').value);
        const neck = parseFloat(document.getElementById('neck_slider').value);
        const hip = parseFloat(document.getElementById('hip_slider').value);
        document.getElementById('height_val').innerText = height;
        document.getElementById('weight_val').innerText = weight;
        document.getElementById('waist_val').innerText = waist;
        document.getElementById('neck_val').innerText = neck;
        document.getElementById('hip_val').innerText = hip;
        const bmi = weight / Math.pow(height / 100, 2);
        document.getElementById('bmi_val').innerText = bmi.toFixed(1);
        let bmiStatus = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Normal' : bmi < 30 ? 'Overweight' : 'Obese';
        document.getElementById('bmi_status').innerHTML = bmiStatus;
        const gender = document.getElementById('gender')?.value || 'Male';
        let bodyFat = gender === 'Male' ? 86.010 * Math.log10(waist - neck) - 70.041 * Math.log10(height) + 36.76 : 163.205 * Math.log10(waist + hip - neck) - 97.684 * Math.log10(height) - 78.387;
        bodyFat = Math.max(5, Math.min(50, bodyFat));
        document.getElementById('bodyfat_val').innerText = bodyFat.toFixed(1) + '%';
        const muscleMass = weight * (1 - bodyFat / 100) * 0.5;
        document.getElementById('muscle_val').innerText = muscleMass.toFixed(1) + ' kg';
        return { height, weight, waist, neck, hip, bmi, bodyFat, muscleMass };
    }

    function calculateAngle(pointA, pointB, pointC) {
        if (!pointA || !pointB || !pointC) return 0;
        const vectorAB = { x: pointA.x - pointB.x, y: pointA.y - pointB.y };
        const vectorCB = { x: pointC.x - pointB.x, y: pointC.y - pointB.y };
        const dotProduct = vectorAB.x * vectorCB.x + vectorAB.y * vectorCB.y;
        const magAB = Math.sqrt(vectorAB.x ** 2 + vectorAB.y ** 2);
        const magCB = Math.sqrt(vectorCB.x ** 2 + vectorCB.y ** 2);
        if (magAB === 0 || magCB === 0) return 0;
        return Math.round(Math.acos(Math.min(1, Math.max(-1, dotProduct / (magAB * magCB)))) * 180 / Math.PI);
    }

    function evaluateAngle(angle, joint) {
        const t = thresholds[currentExercise][joint];
        if (!t) return 'optimal';
        if (angle >= t.optimal[0] && angle <= t.optimal[1]) return 'optimal';
        if (angle >= t.min && angle <= t.max) return 'good';
        if ((angle < t.min && angle > t.min - 15) || (angle > t.max && angle < t.max + 15)) return 'warning';
        return 'danger';
    }

    function analyzeBiomechanics(landmarks) {
        if (!landmarks) return null;
        const leftShoulder = landmarks[11], rightShoulder = landmarks[12], leftElbow = landmarks[13];
        const leftHip = landmarks[23], rightHip = landmarks[24], leftKnee = landmarks[25], rightKnee = landmarks[26];
        const leftAnkle = landmarks[27], rightAnkle = landmarks[28], leftHeel = landmarks[29], nose = landmarks[0];

        let knee = leftHip && leftKnee && leftAnkle ? calculateAngle(leftHip, leftKnee, leftAnkle) : 0;
        let hip = leftShoulder && leftHip && leftKnee ? calculateAngle(leftShoulder, leftHip, leftKnee) : 0;
        let ankle = leftKnee && leftAnkle && leftHeel ? calculateAngle(leftKnee, leftAnkle, leftHeel) : 0;
        let back = 0;
        if (leftShoulder && leftHip && rightShoulder && rightHip) {
            const centerShoulder = { x: (leftShoulder.x + rightShoulder.x)/2, y: (leftShoulder.y + rightShoulder.y)/2 };
            const centerHip = { x: (leftHip.x + rightHip.x)/2, y: (leftHip.y + rightHip.y)/2 };
            back = Math.abs(Math.round(Math.atan2(centerHip.y - centerShoulder.y, centerHip.x - centerShoulder.x) * 180 / Math.PI));
        }
        let neck = 0;
        if (nose && leftShoulder && rightShoulder) {
            const centerShoulder = { x: (leftShoulder.x + rightShoulder.x)/2, y: (leftShoulder.y + rightShoulder.y)/2 };
            neck = Math.min(45, Math.abs(Math.round(Math.atan2(nose.y - centerShoulder.y, nose.x - centerShoulder.x) * 180 / Math.PI)));
        }
        let shoulder = leftElbow && leftShoulder && leftHip ? calculateAngle(leftElbow, leftShoulder, leftHip) : 0;
        let pelvic_tilt = leftHip && rightHip ? Math.min(30, Math.abs((leftHip.y - rightHip.y) * 90)) : 0;
        let com_x = leftHip && rightHip ? Math.round(((leftHip.x + rightHip.x)/2) * 100) : 0;
        let com_y = leftHip && rightHip ? Math.round(((leftHip.y + rightHip.y)/2) * 100) : 0;
        let symmetry = 100;
        if (leftKnee && rightKnee && knee > 0) {
            const rightKneeVal = calculateAngle(rightHip, rightKnee, rightAnkle);
            symmetry = Math.min(100, Math.round(100 - (Math.abs(knee - rightKneeVal) / ((knee + rightKneeVal)/2) * 100)));
        }

        currentAngles = { knee, hip, back, ankle, neck, shoulder, elbow: 0, wrist: 0, pelvic_tilt, com_x, com_y, symmetry };

        const kneeStatus = evaluateAngle(knee, 'knee');
        const hipStatus = evaluateAngle(hip, 'hip');
        const backStatus = evaluateAngle(back, 'back');
        const neckStatus = evaluateAngle(neck, 'neck');

        let alerts = [];
        if (backStatus === 'danger') alerts.push("⚠️ CRITICAL: Poor back position - Spinal injury risk!");
        else if (backStatus === 'warning') alerts.push("⚠️ Back angle suboptimal - Adjust posture");
        if (kneeStatus === 'danger') alerts.push("⚠️ Knee angle dangerous - ACL injury risk!");
        else if (kneeStatus === 'warning') alerts.push("⚠️ Knee angle needs adjustment");
        if (hipStatus === 'danger') alerts.push("⚠️ Hip mobility issue - Lower back compensation risk");
        if (neckStatus === 'danger') alerts.push("⚠️ Poor neck posture - Cervical strain risk");
        if (symmetry < 70) alerts.push(`⚠️ Significant asymmetry detected (${symmetry}%)`);

        let recommendations = [];
        if (backStatus !== 'optimal') recommendations.push("✅ Maintain neutral spine, engage core");
        if (kneeStatus !== 'optimal') recommendations.push("✅ Adjust knee position, track over toes");
        if (hipStatus !== 'optimal') recommendations.push("✅ Push hips back, maintain hip hinge");
        if (neckStatus !== 'optimal') recommendations.push("✅ Keep neck neutral, look slightly forward");
        if (symmetry < 80) recommendations.push("✅ Focus on bilateral symmetry - strengthen weaker side");

        let score = 100;
        if (backStatus === 'danger') score -= 40; else if (backStatus === 'warning') score -= 20;
        if (kneeStatus === 'danger') score -= 25; else if (kneeStatus === 'warning') score -= 12;
        if (hipStatus === 'danger') score -= 20; else if (hipStatus === 'warning') score -= 10;
        if (neckStatus === 'danger') score -= 15; else if (neckStatus === 'warning') score -= 8;
        if (symmetry < 70) score -= 20; else if (symmetry < 85) score -= 10;
        score = Math.max(0, Math.min(100, score));

        let risk = score < 30 ? 'critical' : score < 50 ? 'high' : score < 75 ? 'moderate' : 'low';
        currentRisk = risk; currentScore = score; currentAlerts = alerts; currentRecommendations = recommendations;

        kneeAngleElem.innerHTML = knee + '°'; hipAngleElem.innerHTML = hip + '°'; backAngleElem.innerHTML = back + '°'; ankleAngleElem.innerHTML = ankle + '°';
        postureScoreElem.innerHTML = score;
        document.getElementById('posture-ring').style.background = `conic-gradient(#00b4db ${score * 3.6}deg, #333 ${score * 3.6}deg)`;
        riskIndicator.className = `risk-badge risk-${risk}`; riskIndicator.innerHTML = risk.toUpperCase() + ' RISK';
        alertsList.innerHTML = alerts.length ? alerts.map(a => `<div class="alert-item">${a}</div>`).join('') : '<div class="alert-item"><i class="fa-solid fa-check-circle" style="color:#00ff88"></i> No immediate risks</div>';
        recommendationsList.innerHTML = recommendations.length ? recommendations.map(r => `<div class="recommendation-item">${r}</div>`).join('') : '<div class="recommendation-item">✅ Great form!</div>';

        return { angles: currentAngles, alerts, recommendations, score, risk };
    }

    oneClickCaptureBtn.addEventListener('click', startOneClickCapture);
    function startOneClickCapture() {
        if (isCapturing) return;
        if (!document.getElementById('full_name').value.trim() || !document.getElementById('phone').value.trim()) {
            alert('Please fill Name and Phone before capturing!'); return;
        }
        isCapturing = true; captureOverlay.classList.add('active');
        let countdown = 3; captureTimer.textContent = countdown;
        captureTimerInterval = setInterval(() => {
            countdown--; captureTimer.textContent = countdown;
            if (countdown <= 0) { clearInterval(captureTimerInterval); capturePhotoAndSaveAll(); }
        }, 1000);
    }

    async function capturePhotoAndSaveAll() {
        const video = document.querySelector('.input_video');
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 1280; canvas.height = video.videoHeight || 720;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        currentPhotoData = canvas.toDataURL('image/jpeg', 0.85);
        await saveAllData();
        captureOverlay.classList.remove('active'); isCapturing = false;
        statusIndicator.innerHTML = '<i class="fa-regular fa-circle-check"></i> Ready';
    }

    async function saveAllData() {
        showLoader();
        try {
            const measurements = updateMeasurements();
            const clientData = { action: 'save_client', full_name: document.getElementById('full_name').value, phone: document.getElementById('phone').value, email: document.getElementById('email').value, age: document.getElementById('age').value || 0, gender: document.getElementById('gender').value, sport_type: document.getElementById('sport_type').value };
            const clientRes = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(clientData) });
            const clientResult = await clientRes.json();
            if (clientResult.status !== 'success') throw new Error('Client registration failed');
            const clientDbId = clientResult.client_db_id;
            const sessionId = 'SES-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
            await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'save_measurements', client_id: clientDbId, session_id: sessionId, height: measurements.height, weight: measurements.weight, bmi: measurements.bmi, waist: measurements.waist, neck: measurements.neck, hip: measurements.hip, body_fat: measurements.bodyFat, muscle_mass: measurements.muscleMass }) });
            await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'save_biomechanics', client_id: clientDbId, session_id: sessionId, exercise: currentExercise, knee: currentAngles.knee, hip: currentAngles.hip, back: currentAngles.back, ankle: currentAngles.ankle, neck: currentAngles.neck, shoulder: currentAngles.shoulder, elbow: currentAngles.elbow, wrist: currentAngles.wrist, pelvic_tilt: currentAngles.pelvic_tilt, com_x: currentAngles.com_x, com_y: currentAngles.com_y, symmetry_score: currentAngles.symmetry, posture_score: currentScore, risk_level: currentRisk, alerts: currentAlerts.slice(0,3).join('; '), recommendations: currentRecommendations.slice(0,3).join('; '), photo_path: currentPhotoData }) });
            alert('✅ Success! All data saved!');
            document.getElementById('full_name').value = ''; document.getElementById('phone').value = ''; document.getElementById('email').value = ''; document.getElementById('age').value = '';
            currentPhotoData = null; fetchAllRecords();
        } catch (error) { console.error(error); alert('Error: ' + error.message); }
        hideLoader();
    }

    async function fetchAllRecords() {
        showLoader();
        try {
            const res = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'get_records' }) });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            if (data.status === 'success') {
                allRecords = { clients: data.clients || [], measurements: data.measurements || [], biomechanics: data.biomechanics || [] };
                renderTable();
            }
        } catch (err) { console.error(err); document.getElementById('dynamicTableBody').innerHTML = `<tr><td colspan="7" class="text-center text-danger">⚠️ Failed to load data.确保后端运行正常.</td></tr>`; }
        finally { hideLoader(); }
    }

    async function deleteRecord(table, id, event) {
        if (event) event.stopPropagation();
        if (!confirm('⚠️ Delete this record permanently?')) return;
        showLoader();
        await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'delete_record', table: table, id: id }) });
        await fetchAllRecords();
        hideLoader();
    }

    function renderTable() {
        const filterType = document.getElementById('recordTypeFilter').value;
        const searchTerm = document.getElementById('globalSearch').value.toLowerCase();
        let rows = [];

        if (filterType === 'all' || filterType === 'clients') {
            allRecords.clients.forEach(c => {
                if (searchTerm && !c.full_name.toLowerCase().includes(searchTerm) && !c.phone.includes(searchTerm)) return;
                rows.push({ type: 'client', table: 'sports_clients', id: c.id, name: c.full_name, details: `${c.sport_type || 'N/A'} | ${c.age || '?'}y | ${c.gender}`, date: c.registration_date || c.created_at, photo: null, raw: c });
            });
        }
        if (filterType === 'all' || filterType === 'measurements') {
            allRecords.measurements.forEach(m => {
                const client = allRecords.clients.find(c => c.id == m.client_id);
                const clientName = client ? client.full_name : 'Athlete';
                if (searchTerm && !clientName.toLowerCase().includes(searchTerm)) return;
                rows.push({ type: 'measurement', table: 'sports_measurements', id: m.id, name: clientName, details: `📏 H:${m.height_cm}cm | W:${m.weight_kg}kg | BMI:${m.bmi} | BF:${m.body_fat_percentage}%`, date: m.measurement_date, photo: null, raw: m });
            });
        }
        if (filterType === 'all' || filterType === 'biomechanics') {
            allRecords.biomechanics.forEach(b => {
                const client = allRecords.clients.find(c => c.id == b.client_id);
                const clientName = client ? client.full_name : 'Unknown';
                if (searchTerm && !clientName.toLowerCase().includes(searchTerm)) return;
                let riskIcon = b.risk_level === 'low' ? '🟢' : b.risk_level === 'moderate' ? '🟡' : b.risk_level === 'high' ? '🟠' : '🔴';
                rows.push({ type: 'biomechanics', table: 'sports_biomechanics', id: b.id, name: clientName, details: `${b.exercise_type || 'squat'} | 🧘 Posture:${b.posture_score || 0} | ${riskIcon} ${b.risk_level}`, date: b.analysis_timestamp || b.created_at, photo: b.photo_path, raw: b, clientInfo: client });
            });
        }

        rows.sort((a,b) => new Date(b.date) - new Date(a.date));
        const tbody = document.getElementById('dynamicTableBody');
        if (rows.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fa-regular fa-folder-open"></i> No records match</td></tr>'; return; }
        tbody.innerHTML = rows.map(r => `<tr class="record-row" data-type="${r.type}" data-id="${r.id}" data-table="${r.table}" onclick="openDetailedModal('${r.type}', ${r.id})"><td class="fw-bold">#${r.id}</td><td><span class="badge ${r.type === 'biomechanics' ? 'bg-success' : (r.type === 'measurement' ? 'bg-info' : 'bg-primary')} px-3 py-1 rounded-pill">${r.type === 'biomechanics' ? '🧬 Motion' : (r.type === 'measurement' ? '📊 Body' : '👤 Profile')}</span></td><td><i class="fa-regular fa-user"></i> ${escapeHtml(r.name)}</td><td class="small">${escapeHtml(r.details)}</td><td class="small">${new Date(r.date).toLocaleString()}</td><td>${r.photo ? `<img src="${r.photo}" class="thumbnail-img" onclick="event.stopPropagation();showImagePopup('${r.photo.replace(/'/g, "\\'")}')" alt="frame">` : '<span class="text-muted">—</span>'}</td><td><button class="delete-row-btn" onclick="deleteRecord('${r.table}', ${r.id}, event)"><i class="fa-regular fa-trash-can"></i> Delete</button></td></tr>`).join('');
    }

    async function openDetailedModal(type, id) {
        showLoader();
        let record = null, client = null;
        if (type === 'biomechanics') {
            record = allRecords.biomechanics.find(b => b.id == id);
            if (record) client = allRecords.clients.find(c => c.id == record.client_id);
        } else if (type === 'measurement') {
            record = allRecords.measurements.find(m => m.id == id);
            if (record) client = allRecords.clients.find(c => c.id == record.client_id);
        } else if (type === 'client') {
            record = allRecords.clients.find(c => c.id == id);
            client = record;
        }

        if (!record) { hideLoader(); alert('Record not found'); return; }

        const modalBody = document.getElementById('modalDetailBody');
        if (type === 'biomechanics') {
            const angles = { knee: record.knee_angle || 0, hip: record.hip_angle || 0, back: record.back_angle || 0, ankle: record.ankle_angle || 0, posture: record.posture_score || 0 };
            const riskClass = record.risk_level || 'low';
            const riskText = riskClass.toUpperCase();
            const alertsArr = record.alerts ? record.alerts.split(';') : [];
            const recsArr = record.recommendations ? record.recommendations.split(';') : [];
            const photoExists = record.photo_path && record.photo_path.startsWith('data:image');

            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="detail-card">
                            <h5><i class="fa-solid fa-bone me-2"></i>Joint Kinematics & Posture Metrics</h5>
                            <div class="row mt-3">
                                <div class="col-6 col-md-3 text-center"><div class="angle-badge"><span class="small">Knee</span><div class="metric-value">${angles.knee}°</div></div></div>
                                <div class="col-6 col-md-3 text-center"><div class="angle-badge"><span class="small">Hip</span><div class="metric-value">${angles.hip}°</div></div></div>
                                <div class="col-6 col-md-3 text-center"><div class="angle-badge"><span class="small">Back/Spine</span><div class="metric-value">${angles.back}°</div></div></div>
                                <div class="col-6 col-md-3 text-center"><div class="angle-badge"><span class="small">Ankle</span><div class="metric-value">${angles.ankle}°</div></div></div>
                            </div>
                            <div class="chart-box mt-3"><canvas id="anglesRadarChart" width="400" height="300" style="max-height:250px; width:100%"></canvas></div>
                        </div>
                        <div class="detail-card mt-3">
                            <h5><i class="fa-regular fa-lightbulb"></i> Injury Prevention & Alerts</h5>
                            <div class="mt-2"><span class="risk-tag risk-${riskClass}">⚠️ Risk Level: ${riskText}</span></div>
                            <div class="progress-custom mt-2"><div class="progress-fill-custom" style="width: ${Math.min(100, angles.posture)}%"></div></div>
                            <div><strong>Posture Score:</strong> ${angles.posture}/100</div>
                            <hr class="bg-secondary">
                            <div><i class="fa-solid fa-triangle-exclamation text-danger"></i> <strong>Alerts</strong></div>
                            <div>${alertsArr.length ? alertsArr.map(a => `<div class="alert-item-sm">${escapeHtml(a)}</div>`).join('') : '<div class="alert-item-sm text-success">✅ No critical alerts</div>'}</div>
                            <div class="mt-3"><i class="fa-solid fa-microphone-lines text-success"></i> <strong>Corrections / Recommendations</strong></div>
                            <div>${recsArr.length ? recsArr.map(r => `<div class="recommendation-item">${escapeHtml(r)}</div>`).join('') : '<div class="recommendation-item">✅ Maintain proper form, keep core engaged</div>'}</div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="detail-card">
                            <h5><i class="fa-regular fa-id-card"></i> Athlete Snapshot</h5>
                            <p><strong>${client ? escapeHtml(client.full_name) : 'N/A'}</strong><br>📞 ${client ? client.phone : '—'} | 🏅 ${client ? client.sport_type : '—'} | Age ${client ? client.age : '—'}</p>
                            ${photoExists ? `<div class="text-center mt-2"><img src="${record.photo_path}" style="max-width:100%; border-radius: 24px; border: 2px solid #00b4db; max-height: 220px;" alt="capture"></div>` : '<div class="text-center text-muted"><i class="fa-regular fa-camera"></i> No motion capture photo saved</div>'}
                        </div>
                        <div class="detail-card mt-3">
                            <h5><i class="fa-regular fa-chart-bar"></i> Performance Indicators</h5>
                            <div class="mt-2"><span>Symmetry / Stability</span><div class="progress-custom"><div class="progress-fill-custom" style="width: ${record.symmetry_score || 75}%"></div></div><span class="small">${record.symmetry_score || 75}% symmetry</span></div>
                            <div class="mt-2"><span>Center of Mass (X,Y)</span><div><code>${record.com_x || 0}, ${record.com_y || 0}</code></div></div>
                            <div class="mt-2"><span>Exercise Type:</span> <strong class="text-info">${record.exercise_type || 'squat'}</strong></div>
                            <div class="mt-2"><span>Pelvic Tilt:</span> <strong>${record.pelvic_tilt || 0}°</strong></div>
                        </div>
                    </div>
                </div>
            `;
            setTimeout(() => {
                const ctxRadar = document.getElementById('anglesRadarChart')?.getContext('2d');
                if (ctxRadar) {
                    if (chartsCache.anglesChart) chartsCache.anglesChart.destroy();
                    chartsCache.anglesChart = new Chart(ctxRadar, {
                        type: 'radar',
                        data: {
                            labels: ['Knee Angle', 'Hip Angle', 'Back Angle', 'Ankle Angle', 'Posture Score'],
                            datasets: [{ label: `${client ? client.full_name : 'Athlete'} (Current)`, data: [angles.knee, angles.hip, angles.back, angles.ankle, angles.posture], backgroundColor: 'rgba(0, 180, 219, 0.25)', borderColor: '#00b4db', borderWidth: 2, pointBackgroundColor: '#00ff88', pointBorderColor: '#fff', pointRadius: 5 }]
                        },
                        options: { responsive: true, maintainAspectRatio: true, scales: { r: { beginAtZero: true, max: 180, ticks: { color: '#ccc', stepSize: 30 }, grid: { color: '#334455' } } }, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw}°` } } } }
                    });
                }
            }, 100);
        } else if (type === 'measurement') {
            modalBody.innerHTML = `<div class="row g-4"><div class="col-md-6"><div class="detail-card"><h5><i class="fa-solid fa-ruler"></i> Body Composition Metrics</h5><ul class="list-unstyled mt-2"><li><strong>Height:</strong> ${record.height_cm} cm</li><li><strong>Weight:</strong> ${record.weight_kg} kg</li><li><strong>BMI:</strong> ${record.bmi}</li><li><strong>Waist/Hip:</strong> ${record.waist_circumference_cm} / ${record.hip_circumference_cm} cm</li><li><strong>Body Fat %:</strong> ${record.body_fat_percentage}%</li><li><strong>Muscle Mass:</strong> ${record.muscle_mass_kg} kg</li></ul></div></div><div class="col-md-6"><div class="detail-card"><h5><i class="fa-regular fa-chart-line"></i> Body Composition Chart</h5><canvas id="bodyCompChart" style="max-height:240px"></canvas></div><div class="detail-card mt-2"><h6>Client: ${client ? client.full_name : 'N/A'}</h6><span>Sport: ${client?.sport_type || '—'}</span></div></div></div>`;
            setTimeout(() => {
                const ctx = document.getElementById('bodyCompChart')?.getContext('2d');
                if (ctx) new Chart(ctx, { type: 'bar', data: { labels: ['BMI', 'Body Fat %', 'Muscle Mass (kg)'], datasets: [{ label: 'Values', data: [record.bmi || 0, record.body_fat_percentage || 0, record.muscle_mass_kg || 0], backgroundColor: '#00b4db' }] }, options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } } } });
            }, 100);
        } else {
            modalBody.innerHTML = `<div class="detail-card"><h5>👤 Athlete Profile</h5><p><strong>${record.full_name}</strong><br>📞 ${record.phone}<br>📧 ${record.email || '—'}<br>🏅 Sport: ${record.sport_type}<br>⚧ Gender: ${record.gender}<br>📅 Registered: ${new Date(record.registration_date).toLocaleString()}</p></div><div class="detail-card"><h6>Total Sessions: ${allRecords.biomechanics.filter(b=>b.client_id==record.id).length} motion analyses</h6><p>Click on biomechanics records to see movement deep-dive.</p></div>`;
        }
        const modal = new bootstrap.Modal(document.getElementById('biomechanicsModal'));
        modal.show();
        hideLoader();
        document.getElementById('printReportBtn').onclick = () => { window.print(); };
    }

    function showImagePopup(src) { const w = window.open(); w.document.write(`<html><head><title>Motion capture frame</title><style>body{margin:0;background:#000;display:flex;justify-content:center;align-items:center;height:100vh;} img{max-width:90vw;max-height:90vh;border-radius:24px;box-shadow:0 0 30px cyan;}</style></head><body><img src="${src}"></body></html>`); }
    function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' })[m]); }
    function setExercise(ex) { currentExercise = ex; document.querySelectorAll('.exercise-btn').forEach(b=>b.classList.remove('active')); document.querySelector(`[data-exercise="${ex}"]`).classList.add('active'); }
    function showLoader() { document.getElementById('loadingOverlay').style.display = 'flex'; }
    function hideLoader() { document.getElementById('loadingOverlay').style.display = 'none'; }

    // MediaPipe Setup
    let poseResults = null, handResults = null;
    function onPoseResults(results) { poseResults = results; drawCombined(); }
    function onHandResults(results) { handResults = results; drawCombined(); }
    function drawCombined() {
        if (!poseResults) return;
        canvasCtx.save(); canvasCtx.clearRect(0,0,canvasElement.width,canvasElement.height); canvasCtx.drawImage(poseResults.image,0,0,canvasElement.width,canvasElement.height);
        if(poseResults.poseLandmarks) { drawConnectors(canvasCtx, poseResults.poseLandmarks, POSE_CONNECTIONS, { color: '#00ff88', lineWidth: 3 }); drawLandmarks(canvasCtx, poseResults.poseLandmarks, { color: '#00b4db', lineWidth: 2, radius: 5 }); analyzeBiomechanics(poseResults.poseLandmarks); }
        else alertsList.innerHTML = '<div class="alert-item">No person detected. Stand in front of camera</div>';
        if(handResults && handResults.multiHandLandmarks) for(const landmarks of handResults.multiHandLandmarks) { drawConnectors(canvasCtx, landmarks, HAND_CONNECTIONS, { color: '#ffaa00', lineWidth: 2 }); drawLandmarks(canvasCtx, landmarks, { color: '#ff6600', lineWidth: 1, radius: 3 }); }
        canvasCtx.restore();
        frameCount++; const now=performance.now(); if(now-lastTime>=1000){ fpsCounter.innerHTML=`FPS: ${frameCount}`; frameCount=0; lastTime=now; }
    }

    const pose = new Pose({ locateFile: f=>`https://cdn.jsdelivr.net/npm/@mediapipe/pose/${f}` });
    pose.setOptions({ modelComplexity:1, smoothLandmarks:true, minDetectionConfidence:0.5, minTrackingConfidence:0.5 });
    pose.onResults(onPoseResults);
    const hands = new Hands({ locateFile: f=>`https://cdn.jsdelivr.net/npm/@mediapipe/hands/${f}` });
    hands.setOptions({ maxNumHands:2, modelComplexity:1, minDetectionConfidence:0.5, minTrackingConfidence:0.5 });
    hands.onResults(onHandResults);
    const camera = new Camera(videoElement, { onFrame:async()=>{ await pose.send({image:videoElement}); await hands.send({image:videoElement}); }, width:1280, height:720 });
    camera.start();

    document.getElementById('globalSearch').addEventListener('keyup', () => renderTable());
    document.getElementById('recordTypeFilter').addEventListener('change', () => renderTable());
    document.getElementById('refreshBtn').addEventListener('click', () => fetchAllRecords());
    window.deleteRecord = deleteRecord;
    window.openDetailedModal = openDetailedModal;
    window.showImagePopup = showImagePopup;
    window.setExercise = setExercise;

    initMeasurements();
    fetchAllRecords();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>