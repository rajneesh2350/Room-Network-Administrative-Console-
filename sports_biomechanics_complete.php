<?php
require_once 'conn.php';
session_start();

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $data = json_decode(file_get_contents('php://input'), true);

    // Save client data
    if ($data['action'] === 'save_client') {
        $unique_id = 'SPT' . date('Ymd') . rand(1000, 9999);
        $stmt = $conn->prepare("INSERT INTO sports_clients (client_unique_id, full_name, phone, email, age, gender, sport_type, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssiss", $unique_id, $data['full_name'], $data['phone'], $data['email'], $data['age'], $data['gender'], $data['sport_type']);

        if ($stmt->execute()) {
            $client_id = $conn->insert_id;
            $_SESSION['current_client_id'] = $client_id;
            $_SESSION['current_session_id'] = 'SES' . date('YmdHis') . rand(100, 999);
            echo json_encode(['status' => 'success', 'client_id' => $unique_id, 'db_client_id' => $client_id, 'message' => 'Client registered successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $conn->error]);
        }
        exit;
    }

    // Save measurements with photo
    if ($data['action'] === 'save_measurements') {
        $photo_data = $data['photo_data'] ?? '';
        $photo_path = '';

        if ($photo_data) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $photo_path = $upload_dir . $_SESSION['current_session_id'] . '.jpg';
            $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
            file_put_contents($photo_path, base64_decode($photo_data));
        }

        $stmt = $conn->prepare("INSERT INTO sports_measurements (client_id, session_id, height_cm, weight_kg, bmi, waist_circumference_cm, neck_circumference_cm, hip_circumference_cm, shoulder_width_cm, body_fat_percentage, measurement_method, photo_path, measurement_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'automated', ?, NOW())");
        $stmt->bind_param("isiddddddds", $_SESSION['current_client_id'], $_SESSION['current_session_id'], $data['height'], $data['weight'], $data['bmi'], $data['waist'], $data['neck'], $data['hip'], $data['shoulder'], $data['body_fat'], $photo_path);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Save biomechanics analysis
    if ($data['action'] === 'save_biomechanics') {
        $stmt = $conn->prepare("INSERT INTO sports_biomechanics (client_id, session_id, exercise_type, knee_angle, hip_angle, back_angle, ankle_angle, shoulder_angle, posture_score, risk_level, risk_factors, alerts, recommendations, injury_potential, analysis_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isiiiiiiissssd", $_SESSION['current_client_id'], $_SESSION['current_session_id'], $data['exercise'], $data['knee'], $data['hip'], $data['back'], $data['ankle'], $data['shoulder'], $data['posture_score'], $data['risk_level'], $data['risk_factors'], $data['alerts'], $data['recommendations'], $data['injury_potential']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Professional Sports Biomechanics & Body Analysis System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #ffffff;
            font-family: 'Segoe UI', 'Poppins', sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(0,0,0,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 2px solid #00b4db;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #00b4db, #00ff88);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }

        .main-container {
            max-width: 1600px;
            margin: 80px auto 20px;
            padding: 0 20px;
        }

        /* Cards */
        .glass-card {
            background: rgba(20,20,40,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(0,180,219,0.3);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,180,219,0.2);
        }

        .card-header {
            background: linear-gradient(135deg, #00b4db, #0083b0);
            padding: 15px 20px;
            border: none;
            font-weight: bold;
        }

        /* Form Controls */
        .form-control, .form-select {
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(0,180,219,0.3);
            color: white;
            border-radius: 10px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(0,0,0,0.7);
            border-color: #00b4db;
            box-shadow: 0 0 10px rgba(0,180,219,0.3);
            color: white;
        }

        .form-label {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            color: #00b4db;
        }

        /* Video Container */
        .video-container {
            position: relative;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 16/9;
        }

        video, canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        canvas {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
        }

        /* Countdown Overlay */
        .countdown-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 30;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .countdown-number {
            font-size: 8rem;
            font-weight: bold;
            color: #00b4db;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        /* Measurement Cards */
        .measure-card {
            background: linear-gradient(135deg, rgba(0,180,219,0.15), rgba(0,131,176,0.05));
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(0,180,219,0.2);
        }

        .measure-value {
            font-size: 1.6rem;
            font-weight: bold;
            color: #00b4db;
        }

        .measure-label {
            font-size: 0.7rem;
            color: #aaa;
        }

        .ai-line {
            position: absolute;
            background: linear-gradient(90deg, transparent, #00ff88, transparent);
            height: 3px;
            z-index: 20;
            pointer-events: none;
            box-shadow: 0 0 10px #00ff88;
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(45deg, #00b4db, #0083b0);
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(0,180,219,0.4);
        }

        .btn-success-custom {
            background: linear-gradient(45deg, #00ff88, #00cc66);
            border: none;
            color: #000;
            font-weight: bold;
        }

        /* Risk Badges */
        .risk-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.75rem;
        }
        .risk-low { background: #00ff88; color: #000; }
        .risk-moderate { background: #ffaa00; color: #000; }
        .risk-high { background: #ff6600; color: #fff; }
        .risk-critical { background: #ff0033; color: #fff; animation: blink 1s infinite; }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Angle Cards */
        .angle-card {
            background: rgba(0,0,0,0.5);
            border-radius: 10px;
            padding: 8px;
            text-align: center;
        }
        .angle-value { font-size: 1.3rem; font-weight: bold; color: #00b4db; }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #00b4db;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive */
        @media (max-width: 992px) {
            .measure-value { font-size: 1.1rem; }
            .angle-value { font-size: 1rem; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fa-solid fa-microchip"></i> AI Sports Biomechanics Pro
        </a>
        <div class="d-flex">
            <span id="fps-counter" class="badge bg-info me-2">FPS: 0</span>
            <button class="btn btn-outline-info btn-sm" onclick="viewReport()">
                <i class="fa-solid fa-chart-line"></i> Report
            </button>
        </div>
    </div>
</nav>

<div class="main-container">
    <div class="row g-4">
        <!-- LEFT COLUMN: Client Form -->
        <div class="col-lg-4">
            <div class="glass-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-user-plus"></i> Client Registration</h5>
                </div>
                <div class="card-body p-3">
                    <form id="clientForm">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label"><i class="fa-solid fa-user"></i> Full Name *</label>
                                <input type="text" class="form-control" id="full_name" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><i class="fa-solid fa-phone"></i> Phone *</label>
                                <input type="tel" class="form-control" id="phone" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><i class="fa-solid fa-envelope"></i> Email</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                            <div class="col-4">
                                <label class="form-label"><i class="fa-solid fa-calendar"></i> Age</label>
                                <input type="number" class="form-control" id="age">
                            </div>
                            <div class="col-4">
                                <label class="form-label"><i class="fa-solid fa-venus-mars"></i> Gender</label>
                                <select class="form-select" id="gender">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label"><i class="fa-solid fa-futbol"></i> Sport</label>
                                <select class="form-select" id="sport_type">
                                    <option value="Cricket">🏏 Cricket</option>
                                    <option value="Football">⚽ Football</option>
                                    <option value="Basketball">🏀 Basketball</option>
                                    <option value="Tennis">🎾 Tennis</option>
                                    <option value="Athletics">🏃 Athletics</option>
                                    <option value="Badminton">🏸 Badminton</option>
                                    <option value="Swimming">🏊 Swimming</option>
                                    <option value="Gym">💪 Gym/Training</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary-custom w-100 mt-3">
                            <i class="fa-solid fa-save"></i> Register Client
                        </button>
                    </form>
                </div>
            </div>

            <!-- Body Measurements (Auto-filled from AI) -->
            <div class="glass-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-ruler-combined"></i> Body Measurements</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="height_val">--</div>
                                <div class="measure-label">Height (cm)</div>
                                <input type="number" class="form-control form-control-sm mt-1" id="height_input" placeholder="Auto-detected" step="0.5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="weight_val">--</div>
                                <div class="measure-label">Weight (kg)</div>
                                <input type="number" class="form-control form-control-sm mt-1" id="weight_input" placeholder="Enter manually" step="0.5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="waist_val">--</div>
                                <div class="measure-label">Waist (cm) <i class="fa-solid fa-microchip text-info"></i></div>
                                <input type="number" class="form-control form-control-sm mt-1" id="waist_input" placeholder="Auto-detected" step="0.5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="neck_val">--</div>
                                <div class="measure-label">Neck (cm) <i class="fa-solid fa-microchip text-info"></i></div>
                                <input type="number" class="form-control form-control-sm mt-1" id="neck_input" placeholder="Auto-detected" step="0.5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="hip_val">--</div>
                                <div class="measure-label">Hip (cm)</div>
                                <input type="number" class="form-control form-control-sm mt-1" id="hip_input" placeholder="Optional" step="0.5">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="bmi_val">--</div>
                                <div class="measure-label">BMI</div>
                                <span id="bmi_status" class="small"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="bodyfat_val">--</div>
                                <div class="measure-label">Body Fat %</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="shoulder_val">--</div>
                                <div class="measure-label">Shoulder Width (cm)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MIDDLE COLUMN: Video Feed -->
        <div class="col-lg-5">
            <div class="glass-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-video"></i> Pose Detection & Auto-Measurement</h5>
                </div>
                <div class="card-body p-0">
                    <div class="video-container">
                        <video class="input_video" autoplay playsinline></video>
                        <canvas class="output_canvas" width="1280" height="720"></canvas>
                        <div class="countdown-overlay" id="countdownOverlay">
                            <div class="countdown-number" id="countdownNumber">3</div>
                            <div class="mt-3">Stand still! Capturing body measurements...</div>
                        </div>
                        <!-- AI Measurement Lines -->
                        <div id="aiLines"></div>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="angle-card">
                                <div class="angle-value" id="knee_angle">0°</div>
                                <div class="measure-label">Knee</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="angle-card">
                                <div class="angle-value" id="hip_angle">0°</div>
                                <div class="measure-label">Hip</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="angle-card">
                                <div class="angle-value" id="back_angle">0°</div>
                                <div class="measure-label">Back</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-info flex-grow-1" onclick="setExercise('squat')">🏋️ Squat</button>
                        <button class="btn btn-outline-info flex-grow-1" onclick="setExercise('deadlift')">💪 Deadlift</button>
                        <button class="btn btn-outline-info flex-grow-1" onclick="setExercise('running')">🏃 Running</button>
                        <button class="btn btn-outline-info flex-grow-1" onclick="setExercise('lunge')">🦵 Lunge</button>
                    </div>

                    <button class="btn-success-custom w-100 mt-3" id="captureBtn" onclick="startPhotoCapture()">
                        <i class="fa-solid fa-camera"></i> Capture Photo & Save Measurements
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Analysis -->
        <div class="col-lg-3">
            <div class="glass-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-chart-line"></i> Injury Risk Analysis</h5>
                </div>
                <div class="card-body p-3">
                    <div class="text-center mb-3">
                        <span id="risk_indicator" class="risk-badge risk-low">LOW RISK</span>
                        <div class="mt-2">
                            <div style="width: 100px; height: 100px; margin: 0 auto; position: relative;">
                                <canvas id="scoreRing" width="100" height="100"></canvas>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                                    <span id="posture_score" style="font-size: 1.5rem; font-weight: bold;">0</span>
                                    <span style="font-size: 0.7rem;">%</span>
                                </div>
                            </div>
                            <div class="small">Posture Score</div>
                        </div>
                    </div>

                    <h6><i class="fa-solid fa-triangle-exclamation text-danger"></i> Injury Alerts</h6>
                    <div id="alerts_list" style="max-height: 150px; overflow-y: auto; font-size: 0.85rem;">
                        <div class="alert alert-secondary">Waiting for pose detection...</div>
                    </div>

                    <h6 class="mt-3"><i class="fa-solid fa-lightbulb text-success"></i> Corrections</h6>
                    <div id="recommendations_list" style="max-height: 150px; overflow-y: auto; font-size: 0.85rem;">
                        <div class="alert alert-info">Position yourself in front of camera</div>
                    </div>

                    <button class="btn-primary-custom w-100 mt-3" onclick="saveAnalysis()">
                        <i class="fa-solid fa-floppy-disk"></i> Save Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="mt-3">Saving data...</div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-file-alt"></i> Client Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportContent">
                Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================
    // CONFIGURATION & STATE
    // ==========================================

    const videoElement = document.querySelector('.input_video');
    const canvasElement = document.querySelector('.output_canvas');
    const canvasCtx = canvasElement.getContext('2d');

    let currentClientId = null;
    let currentSessionId = null;
    let isClientRegistered = false;
    let currentExercise = 'squat';
    let currentAngles = { knee: 0, hip: 0, back: 0, ankle: 0, shoulder: 0 };
    let currentMeasurements = {
        height: 0, weight: 70, waist: 0, neck: 0, hip: 0, shoulder: 0, bmi: 0, bodyFat: 0
    };
    let currentRisk = 'low';
    let currentScore = 0;
    let currentAlerts = [];
    let currentRecommendations = [];
    let calibrationPixelHeight = 0;
    let calibrationHeight = 170;
    let isCalibrated = false;
    let frameCount = 0;
    let lastTime = performance.now();

    // ==========================================
    // CLIENT REGISTRATION
    // ==========================================

    document.getElementById('clientForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        showLoading();

        const data = {
            action: 'save_client',
            full_name: document.getElementById('full_name').value,
            phone: document.getElementById('phone').value,
            email: document.getElementById('email').value,
            age: document.getElementById('age').value,
            gender: document.getElementById('gender').value,
            sport_type: document.getElementById('sport_type').value
        };

        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.status === 'success') {
            currentClientId = result.db_client_id;
            currentSessionId = 'SES' + new Date().toISOString().replace(/[-:.]/g, '') + Math.floor(Math.random() * 1000);
            isClientRegistered = true;
            alert('✓ Client Registered! ID: ' + result.client_id);
        } else {
            alert('✗ Registration failed: ' + result.message);
        }
        hideLoading();
    });

    // ==========================================
    // AUTO-MEASUREMENTS FROM POSE LANDMARKS
    // ==========================================

    function calculateRealDistance(pixelDistance, referencePixel, referenceCm) {
        return (pixelDistance / referencePixel) * referenceCm;
    }

    function getPixelDistance(point1, point2) {
        if (!point1 || !point2) return 0;
        return Math.hypot(point1.x - point2.x, point1.y - point2.y);
    }

    function calculateBodyMeasurements(landmarks) {
        if (!landmarks) return;

        // Landmark indices
        const NOSE = 0;
        const LEFT_SHOULDER = 11;
        const RIGHT_SHOULDER = 12;
        const LEFT_HIP = 23;
        const RIGHT_HIP = 24;
        const LEFT_ANKLE = 27;
        const RIGHT_ANKLE = 28;
        const LEFT_HEEL = 31;

        const nose = landmarks[NOSE];
        const leftShoulder = landmarks[LEFT_SHOULDER];
        const rightShoulder = landmarks[RIGHT_SHOULDER];
        const leftHip = landmarks[LEFT_HIP];
        const rightHip = landmarks[RIGHT_HIP];
        const leftAnkle = landmarks[LEFT_ANKLE];

        if (!nose || !leftAnkle) return;

        // Calculate pixel distances
        const pixelHeight = getPixelDistance(nose, leftAnkle);
        const pixelWaist = getPixelDistance(leftHip, rightHip);
        const pixelNeck = getPixelDistance(leftShoulder, rightShoulder) * 0.55;
        const pixelShoulder = getPixelDistance(leftShoulder, rightShoulder);

        // Draw AI measurement lines on video
        drawAILines(nose, leftAnkle, leftHip, rightHip, leftShoulder, rightShoulder);

        // If we have calibration, calculate real measurements
        if (pixelHeight > 0) {
            if (!isCalibrated) {
                calibrationPixelHeight = pixelHeight;
                isCalibrated = true;
                document.getElementById('height_accuracy').innerHTML = 'Calibrated';
            }

            if (isCalibrated && calibrationPixelHeight > 0) {
                currentMeasurements.height = Math.round(calculateRealDistance(pixelHeight, calibrationPixelHeight, calibrationHeight));
                currentMeasurements.waist = Math.round(calculateRealDistance(pixelWaist, calibrationPixelHeight, calibrationHeight) * 1.15);
                currentMeasurements.neck = Math.round(calculateRealDistance(pixelNeck, calibrationPixelHeight, calibrationHeight));
                currentMeasurements.shoulder = Math.round(calculateRealDistance(pixelShoulder, calibrationPixelHeight, calibrationHeight));

                // Get weight from input or use default
                const weightInput = document.getElementById('weight_input').value;
                currentMeasurements.weight = weightInput ? parseFloat(weightInput) : 70;

                // Calculate BMI
                const heightM = currentMeasurements.height / 100;
                currentMeasurements.bmi = (currentMeasurements.weight / (heightM * heightM)).toFixed(1);

                // Calculate Body Fat % (US Navy method)
                if (currentMeasurements.waist > 0 && currentMeasurements.neck > 0) {
                    const gender = document.getElementById('gender').value;
                    if (gender === 'Male') {
                        currentMeasurements.bodyFat = 86.010 * Math.log10(currentMeasurements.waist - currentMeasurements.neck) -
                                                     70.041 * Math.log10(currentMeasurements.height) + 36.76;
                    } else {
                        const hip = currentMeasurements.hip || 90;
                        currentMeasurements.bodyFat = 163.205 * Math.log10(currentMeasurements.waist + hip - currentMeasurements.neck) -
                                                     97.684 * Math.log10(currentMeasurements.height) - 78.387;
                    }
                    currentMeasurements.bodyFat = Math.max(8, Math.min(45, Math.round(currentMeasurements.bodyFat)));
                }

                updateMeasurementUI();
            }
        }
    }

    function drawAILines(nose, leftAnkle, leftHip, rightHip, leftShoulder, rightShoulder) {
        const aiLinesDiv = document.getElementById('aiLines');
        if (!aiLinesDiv) return;

        const container = document.querySelector('.video-container');
        const rect = container.getBoundingClientRect();

        // Convert normalized coordinates to pixel positions
        function getPixelX(x) { return x * rect.width; }
        function getPixelY(y) { return y * rect.height; }

        let linesHtml = '';

        // Height line (nose to ankle)
        if (nose && leftAnkle) {
            linesHtml += `<div style="position: absolute; left: ${getPixelX(nose.x) - 40}px; top: ${getPixelY(nose.y)}px; color: #00ff88; font-size: 10px; z-index: 25;">📏 Height</div>`;
        }

        // Waist line (between hips)
        if (leftHip && rightHip) {
            const midX = (getPixelX(leftHip.x) + getPixelX(rightHip.x)) / 2;
            const midY = (getPixelY(leftHip.y) + getPixelY(rightHip.y)) / 2;
            linesHtml += `<div style="position: absolute; left: ${midX - 30}px; top: ${midY - 15}px; background: #00ff88; width: 60px; height: 2px; z-index: 25; box-shadow: 0 0 10px #00ff88;"></div>`;
            linesHtml += `<div style="position: absolute; left: ${midX}px; top: ${midY - 25}px; color: #00ff88; font-size: 10px; z-index: 25;">Waist: ${currentMeasurements.waist || '--'}cm</div>`;
        }

        // Neck line
        if (leftShoulder && rightShoulder) {
            const midX = (getPixelX(leftShoulder.x) + getPixelX(rightShoulder.x)) / 2;
            const topY = getPixelY(leftShoulder.y) - 15;
            linesHtml += `<div style="position: absolute; left: ${midX - 20}px; top: ${topY}px; background: #00ff88; width: 40px; height: 2px; z-index: 25;"></div>`;
            linesHtml += `<div style="position: absolute; left: ${midX}px; top: ${topY - 10}px; color: #00ff88; font-size: 10px; z-index: 25;">Neck: ${currentMeasurements.neck || '--'}cm</div>`;
        }

        aiLinesDiv.innerHTML = linesHtml;
    }

    function updateMeasurementUI() {
        document.getElementById('height_val').innerHTML = currentMeasurements.height || '--';
        document.getElementById('height_input').value = currentMeasurements.height || '';
        document.getElementById('waist_val').innerHTML = currentMeasurements.waist || '--';
        document.getElementById('waist_input').value = currentMeasurements.waist || '';
        document.getElementById('neck_val').innerHTML = currentMeasurements.neck || '--';
        document.getElementById('neck_input').value = currentMeasurements.neck || '';
        document.getElementById('shoulder_val').innerHTML = currentMeasurements.shoulder || '--';
        document.getElementById('bmi_val').innerHTML = currentMeasurements.bmi || '--';
        document.getElementById('bodyfat_val').innerHTML = currentMeasurements.bodyFat ? currentMeasurements.bodyFat + '%' : '--';

        // BMI Status
        const bmi = parseFloat(currentMeasurements.bmi);
        let bmiStatus = '';
        if (bmi < 18.5) bmiStatus = 'Underweight';
        else if (bmi < 25) bmiStatus = 'Normal ✓';
        else if (bmi < 30) bmiStatus = 'Overweight';
        else bmiStatus = 'Obese';
        document.getElementById('bmi_status').innerHTML = bmiStatus;
    }

    // Allow user to manually correct measurements
    document.getElementById('height_input').addEventListener('change', (e) => {
        currentMeasurements.height = parseFloat(e.target.value);
        updateMeasurementUI();
    });
    document.getElementById('waist_input').addEventListener('change', (e) => {
        currentMeasurements.waist = parseFloat(e.target.value);
        updateMeasurementUI();
    });
    document.getElementById('neck_input').addEventListener('change', (e) => {
        currentMeasurements.neck = parseFloat(e.target.value);
        updateMeasurementUI();
    });
    document.getElementById('weight_input').addEventListener('change', (e) => {
        currentMeasurements.weight = parseFloat(e.target.value);
        updateMeasurementUI();
    });

    // ==========================================
    // ANGLE CALCULATIONS
    // ==========================================

    function calculateAngle(pointA, pointB, pointC) {
        if (!pointA || !pointB || !pointC) return 0;
        const vectorAB = { x: pointA.x - pointB.x, y: pointA.y - pointB.y };
        const vectorCB = { x: pointC.x - pointB.x, y: pointC.y - pointB.y };
        const dot = vectorAB.x * vectorCB.x + vectorAB.y * vectorCB.y;
        const magAB = Math.hypot(vectorAB.x, vectorAB.y);
        const magCB = Math.hypot(vectorCB.x, vectorCB.y);
        const angle = Math.acos(dot / (magAB * magCB)) * 180 / Math.PI;
        return Math.round(angle);
    }

    function calculateAngles(landmarks) {
        const leftHip = landmarks[23];
        const leftKnee = landmarks[25];
        const leftAnkle = landmarks[27];
        const leftShoulder = landmarks[11];
        const rightShoulder = landmarks[12];
        const rightHip = landmarks[24];
        const leftElbow = landmarks[13];
        const leftWrist = landmarks[15];

        if (leftHip && leftKnee && leftAnkle) {
            currentAngles.knee = calculateAngle(leftHip, leftKnee, leftAnkle);
        }
        if (leftShoulder && leftHip && leftKnee) {
            currentAngles.hip = calculateAngle(leftShoulder, leftHip, leftKnee);
        }
        if (leftShoulder && leftHip && rightHip) {
            const centerShoulder = { x: (leftShoulder.x + rightShoulder.x)/2, y: (leftShoulder.y + rightShoulder.y)/2 };
            const centerHip = { x: (leftHip.x + rightHip.x)/2, y: (leftHip.y + rightHip.y)/2 };
            currentAngles.back = Math.abs(Math.round(Math.atan2(centerHip.y - centerShoulder.y, centerHip.x - centerShoulder.x) * 180 / Math.PI));
        }
        if (leftElbow && leftShoulder && leftWrist) {
            currentAngles.shoulder = calculateAngle(leftElbow, leftShoulder, leftWrist);
        }

        document.getElementById('knee_angle').innerHTML = currentAngles.knee + '°';
        document.getElementById('hip_angle').innerHTML = currentAngles.hip + '°';
        document.getElementById('back_angle').innerHTML = currentAngles.back + '°';

        return currentAngles;
    }

    // ==========================================
    // INJURY PREDICTION ALGORITHM
    // ==========================================

    function predictInjuryRisk(angles, exercise, measurements) {
        const alerts = [];
        const recommendations = [];
        let riskLevel = 'low';
        let score = 100;
        let injuryPotential = 0;

        // Knee injury assessment
        if (exercise === 'squat') {
            if (angles.knee < 70) {
                alerts.push("⚠️ CRITICAL: Knee angle too acute! ACL/PCL rupture risk");
                recommendations.push("✅ Don't go too deep, keep knees aligned with toes");
                score -= 40;
                injuryPotential += 9;
                riskLevel = 'critical';
            } else if (angles.knee < 85) {
                alerts.push("⚠️ Knee angle approaching danger zone");
                recommendations.push("✅ Control descent, don't bounce at bottom");
                score -= 20;
                injuryPotential += 5;
                if (riskLevel !== 'critical') riskLevel = 'high';
            }
        }

        if (exercise === 'deadlift') {
            if (angles.knee < 130) {
                alerts.push("⚠️ Knees too bent - quad dominant, hamstring strain risk");
                recommendations.push("✅ Push hips back more, initiate with hamstrings");
                score -= 25;
                injuryPotential += 6;
                riskLevel = 'high';
            }
        }

        // Back injury assessment (MOST CRITICAL)
        if (angles.back > 25) {
            alerts.push("🔴 CRITICAL: Severe back rounding! Immediate disc herniation risk!");
            recommendations.push("🚨 STOP! Reset form, use belt, reduce weight by 50%");
            score -= 50;
            injuryPotential += 10;
            riskLevel = 'critical';
        } else if (angles.back > 18) {
            alerts.push("⚠️ Back angle excessive - lower back strain risk");
            recommendations.push("✅ Keep chest up, brace core, maintain neutral spine");
            score -= 30;
            injuryPotential += 7;
            if (riskLevel !== 'critical') riskLevel = 'high';
        } else if (angles.back > 12) {
            recommendations.push("📐 Slight back rounding, focus on keeping spine straight");
            score -= 10;
            injuryPotential += 3;
            if (riskLevel === 'low') riskLevel = 'moderate';
        }

        // Hip assessment
        if (angles.hip < 65 && exercise === 'squat') {
            alerts.push("⚠️ Limited hip mobility - lower back compensation risk");
            recommendations.push("✅ Improve hip mobility with stretching, widen stance");
            score -= 15;
            injuryPotential += 4;
        }

        // Knee valgus detection (approximated)
        if (angles.knee > 95 && angles.knee < 110 && exercise === 'squat') {
            recommendations.push("🦵 Control knee tracking, push knees outward");
            score -= 5;
        }

        // BMI impact
        if (measurements.bmi > 30) {
            alerts.push("⚠️ High BMI increases joint loading - modify impact exercises");
            recommendations.push("✅ Start with low-impact activities, focus on mobility");
            score -= 10;
            injuryPotential += 3;
        }

        // Calculate final posture score (0-100)
        score = Math.max(0, Math.min(100, score));

        // Risk level determination
        if (score < 40) riskLevel = 'critical';
        else if (score < 60) riskLevel = 'high';
        else if (score < 80) riskLevel = 'moderate';
        else riskLevel = 'low';

        currentScore = score;
        currentRisk = riskLevel;
        currentAlerts = alerts;
        currentRecommendations = recommendations;

        return { score, riskLevel, alerts, recommendations, injuryPotential };
    }

    function updateAnalysisUI(analysis) {
        if (!analysis) return;

        // Update risk badge
        const riskElem = document.getElementById('risk_indicator');
        riskElem.className = `risk-badge risk-${analysis.riskLevel}`;
        riskElem.innerHTML = analysis.riskLevel.toUpperCase() + ' RISK';

        // Update posture score
        document.getElementById('posture_score').innerHTML = analysis.score;

        // Draw score ring
        drawScoreRing(analysis.score);

        // Update alerts
        if (analysis.alerts.length > 0) {
            document.getElementById('alerts_list').innerHTML = analysis.alerts.map(a =>
                `<div class="alert alert-danger alert-sm p-2 mb-1">${a}</div>`
            ).join('');
        } else {
            document.getElementById('alerts_list').innerHTML = '<div class="alert alert-success p-2 mb-1">✓ No immediate injury risks detected</div>';
        }

        // Update recommendations
        if (analysis.recommendations.length > 0) {
            document.getElementById('recommendations_list').innerHTML = analysis.recommendations.map(r =>
                `<div class="alert alert-info p-2 mb-1">${r}</div>`
            ).join('');
        } else {
            document.getElementById('recommendations_list').innerHTML = '<div class="alert alert-success p-2 mb-1">✓ Good form! Keep it up!</div>';
        }
    }

    function drawScoreRing(score) {
        const canvas = document.getElementById('scoreRing');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const angle = (score / 100) * 360;

        ctx.clearRect(0, 0, 100, 100);
        ctx.beginPath();
        ctx.arc(50, 50, 40, 0, 2 * Math.PI);
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 8;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(50, 50, 40, -Math.PI / 2, (angle * Math.PI / 180) - Math.PI / 2);
        ctx.strokeStyle = score > 80 ? '#00ff88' : (score > 60 ? '#ffaa00' : '#ff0033');
        ctx.lineWidth = 8;
        ctx.stroke();
    }

    // ==========================================
    // PHOTO CAPTURE WITH COUNTDOWN
    // ==========================================

    async function startPhotoCapture() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }

        const overlay = document.getElementById('countdownOverlay');
        const countdownNum = document.getElementById('countdownNumber');

        overlay.style.display = 'flex';

        for (let i = 3; i >= 1; i--) {
            countdownNum.innerHTML = i;
            await new Promise(resolve => setTimeout(resolve, 1000));
        }

        countdownNum.innerHTML = "📸";
        await new Promise(resolve => setTimeout(resolve, 500));

        // Capture photo from video
        const video = document.querySelector('.input_video');
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const photoData = canvas.toDataURL('image/jpeg', 0.9);

        overlay.style.display = 'none';

        await saveAllMeasurements(photoData);
    }

    async function saveAllMeasurements(photoData) {
        showLoading();

        // Save measurements with photo
        const measurementsData = {
            action: 'save_measurements',
            height: currentMeasurements.height,
            weight: currentMeasurements.weight,
            bmi: currentMeasurements.bmi,
            waist: currentMeasurements.waist,
            neck: currentMeasurements.neck,
            hip: currentMeasurements.hip || 90,
            shoulder: currentMeasurements.shoulder,
            body_fat: currentMeasurements.bodyFat,
            photo_data: photoData
        };

        await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(measurementsData)
        });

        alert('✓ Measurements and photo saved successfully!');
        hideLoading();
    }

    async function saveAnalysis() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }

        showLoading();

        const analysis = predictInjuryRisk(currentAngles, currentExercise, currentMeasurements);

        const data = {
            action: 'save_biomechanics',
            exercise: currentExercise,
            knee: currentAngles.knee,
            hip: currentAngles.hip,
            back: currentAngles.back,
            ankle: currentAngles.ankle,
            shoulder: currentAngles.shoulder,
            posture_score: analysis.score,
            risk_level: analysis.riskLevel,
            risk_factors: analysis.alerts.join('; '),
            alerts: analysis.alerts.join('; '),
            recommendations: analysis.recommendations.join('; '),
            injury_potential: analysis.injuryPotential
        };

        await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });

        alert('✓ Biomechanics analysis saved!');
        hideLoading();
    }

    function setExercise(exercise) {
        currentExercise = exercise;
        document.querySelectorAll('.btn-outline-info').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }

    async function viewReport() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('reportModal'));
        document.getElementById('reportContent').innerHTML = '<div class="text-center">Loading report...</div>';
        modal.show();

        // For now, show basic info
        document.getElementById('reportContent').innerHTML = `
            <div class="text-center">
                <h4>Client Report</h4>
                <p>Name: ${document.getElementById('full_name').value}</p>
                <p>Phone: ${document.getElementById('phone').value}</p>
                <p>Sport: ${document.getElementById('sport_type').value}</p>
                <hr>
                <p>Height: ${currentMeasurements.height} cm</p>
                <p>Weight: ${currentMeasurements.weight} kg</p>
                <p>BMI: ${currentMeasurements.bmi}</p>
                <p>Body Fat: ${currentMeasurements.bodyFat}%</p>
                <hr>
                <p>Latest Posture Score: ${currentScore}%</p>
                <p>Risk Level: ${currentRisk.toUpperCase()}</p>
            </div>
        `;
    }

    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // ==========================================
    // MEDIAPIPE POSE DETECTION
    // ==========================================

    function onPoseResults(results) {
        canvasCtx.save();
        canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
        canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

        if (results.poseLandmarks) {
            drawConnectors(canvasCtx, results.poseLandmarks, POSE_CONNECTIONS, { color: '#00ff88', lineWidth: 3 });
            drawLandmarks(canvasCtx, results.poseLandmarks, { color: '#00b4db', lineWidth: 2, radius: 4 });

            calculateAngles(results.poseLandmarks);
            calculateBodyMeasurements(results.poseLandmarks);

            const analysis = predictInjuryRisk(currentAngles, currentExercise, currentMeasurements);
            updateAnalysisUI(analysis);
        }

        canvasCtx.restore();

        frameCount++;
        const now = performance.now();
        if (now - lastTime >= 1000) {
            document.getElementById('fps-counter').innerHTML = `FPS: ${frameCount}`;
            frameCount = 0;
            lastTime = now;
        }
    }

    // Initialize MediaPipe
    const pose = new Pose({
        locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
    });
    pose.setOptions({
        modelComplexity: 1,
        smoothLandmarks: true,
        minDetectionConfidence: 0.5,
        minTrackingConfidence: 0.5
    });
    pose.onResults(onPoseResults);

    const camera = new Camera(videoElement, {
        onFrame: async () => { await pose.send({ image: videoElement }); },
        width: 1280,
        height: 720
    });
    camera.start().then(() => {
        console.log('Camera started');
        // Calibration popup
        setTimeout(() => {
            const height = prompt('For accurate measurements, please enter your actual height in cm:', '170');
            if (height) {
                calibrationHeight = parseFloat(height);
                isCalibrated = false;
                alert(`Calibrated! Stand straight for auto-measurement.`);
            }
        }, 2000);
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>