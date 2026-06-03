<?php
require_once 'conn.php';
session_start();

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data['action'] === 'save_client') {
        $unique_id = generateClientID();
        $stmt = $conn->prepare("INSERT INTO sports_clients (client_unique_id, full_name, phone, email, age, gender, sport_type, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssiss", $unique_id, $data['full_name'], $data['phone'], $data['email'], $data['age'], $data['gender'], $data['sport_type']);

        if ($stmt->execute()) {
            $_SESSION['current_client_id'] = $conn->insert_id;
            $_SESSION['current_session_id'] = generateSessionID();
            echo json_encode(['status' => 'success', 'client_id' => $unique_id, 'message' => 'Client registered successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
        }
        exit;
    }

    if ($data['action'] === 'save_measurements') {
        $stmt = $conn->prepare("INSERT INTO sports_measurements (client_id, session_id, height_cm, weight_kg, bmi, waist_circumference_cm, neck_circumference_cm, hip_circumference_cm, body_fat_percentage, muscle_mass_kg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddddddd", $_SESSION['current_client_id'], $_SESSION['current_session_id'], $data['height'], $data['weight'], $data['bmi'], $data['waist'], $data['neck'], $data['hip'], $data['body_fat'], $data['muscle_mass']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($data['action'] === 'save_biomechanics') {
        $stmt = $conn->prepare("INSERT INTO sports_biomechanics (client_id, session_id, exercise_type, knee_angle, hip_angle, back_angle, ankle_angle, posture_score, risk_level, alerts, recommendations) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiiiisss", $_SESSION['current_client_id'], $_SESSION['current_session_id'], $data['exercise'], $data['knee'], $data['hip'], $data['back'], $data['ankle'], $data['posture_score'], $data['risk_level'], $data['alerts'], $data['recommendations']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($data['action'] === 'get_history') {
        $client_id = $_SESSION['current_client_id'] ?? 0;
        $measurements = $conn->query("SELECT * FROM sports_measurements WHERE client_id = $client_id ORDER BY measurement_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
        $biomechanics = $conn->query("SELECT * FROM sports_biomechanics WHERE client_id = $client_id ORDER BY analysis_timestamp DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'measurements' => $measurements, 'biomechanics' => $biomechanics]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Professional Sports Biomechanics & Health Analysis System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #00b4db;
            --secondary: #0083b0;
            --dark: #0a0a0a;
            --darker: #050510;
            --success: #00ff88;
            --danger: #ff3366;
            --warning: #ffaa00;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--darker) 100%);
            color: #ffffff;
            font-family: 'Segoe UI', 'Poppins', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(0,0,0,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 2px solid var(--primary);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary), var(--success));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }

        .main-container {
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* Client Form Card */
        .client-card {
            background: rgba(20,20,35,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(0,180,219,0.3);
            overflow: hidden;
        }

        .client-card .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 15px 20px;
            border-bottom: none;
        }

        .form-control, .form-select {
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(0,180,219,0.3);
            color: white;
            border-radius: 10px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(0,0,0,0.7);
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(0,180,219,0.3);
            color: white;
        }

        .form-label {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            color: var(--primary);
        }

        /* Video Container */
        .video-container {
            position: relative;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
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

        /* Measurement Cards */
        .measure-card {
            background: linear-gradient(135deg, rgba(0,180,219,0.1), rgba(0,131,176,0.05));
            border-radius: 15px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(0,180,219,0.2);
            transition: all 0.3s;
        }

        .measure-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 5px 20px rgba(0,180,219,0.2);
        }

        .measure-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary);
        }

        .measure-label {
            font-size: 0.7rem;
            color: #aaa;
            text-transform: uppercase;
        }

        /* Angle Cards */
        .angle-card {
            background: rgba(0,0,0,0.5);
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            border-left: 3px solid var(--primary);
        }

        .angle-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
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
        .risk-critical { background: #ff0033; color: #fff; animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        /* Alert & Recommendation Items */
        .alert-item {
            background: rgba(255,51,102,0.15);
            border-left: 3px solid var(--danger);
            padding: 8px 12px;
            margin: 8px 0;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .recommendation-item {
            background: rgba(0,255,136,0.1);
            border-left: 3px solid var(--success);
            padding: 8px 12px;
            margin: 8px 0;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        /* Exercise Buttons */
        .exercise-btn {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 6px 15px;
            border-radius: 20px;
            margin: 3px;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .exercise-btn.active, .exercise-btn:hover {
            background: var(--primary);
            color: #000;
        }

        /* Capture Button */
        .capture-btn {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: bold;
            color: white;
            transition: all 0.3s;
        }

        .capture-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(0,180,219,0.4);
        }

        /* Posture Score */
        .posture-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(var(--primary) 0deg, #333 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .posture-score {
            font-size: 2rem;
            font-weight: bold;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .measure-value { font-size: 1.2rem; }
            .angle-value { font-size: 1.1rem; }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--primary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
                <i class="fa-solid fa-file-pdf"></i> Report
            </button>
        </div>
    </div>
</nav>

<div class="main-container" style="margin-top: 80px;">
    <div class="row g-4">
        <!-- Left Column: Client Form & Measurements -->
        <div class="col-lg-4">
            <!-- Client Registration Card -->
            <div class="client-card mb-4">
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
                                    <option value="Cricket">Cricket</option>
                                    <option value="Football">Football</option>
                                    <option value="Basketball">Basketball</option>
                                    <option value="Tennis">Tennis</option>
                                    <option value="Athletics">Athletics</option>
                                    <option value="Badminton">Badminton</option>
                                    <option value="Swimming">Swimming</option>
                                    <option value="Gym/Training">Gym/Training</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="capture-btn w-100 mt-3">
                            <i class="fa-solid fa-save"></i> Register Client
                        </button>
                    </form>
                </div>
            </div>

            <!-- Anthropometric Measurements -->
            <div class="client-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-ruler-combined"></i> Body Measurements</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="height_val">0</div>
                                <div class="measure-label">Height (cm)</div>
                                <input type="range" class="form-range mt-2" id="height_slider" min="100" max="220" step="0.5" value="170">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="weight_val">0</div>
                                <div class="measure-label">Weight (kg)</div>
                                <input type="range" class="form-range mt-2" id="weight_slider" min="30" max="150" step="0.5" value="70">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="waist_val">0</div>
                                <div class="measure-label">Waist (cm)</div>
                                <input type="range" class="form-range mt-2" id="waist_slider" min="50" max="150" step="0.5" value="80">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="neck_val">0</div>
                                <div class="measure-label">Neck (cm)</div>
                                <input type="range" class="form-range mt-2" id="neck_slider" min="30" max="60" step="0.5" value="38">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="hip_val">0</div>
                                <div class="measure-label">Hip (cm)</div>
                                <input type="range" class="form-range mt-2" id="hip_slider" min="60" max="140" step="0.5" value="90">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="bmi_val">0</div>
                                <div class="measure-label">BMI</div>
                                <div id="bmi_status" class="small"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="bodyfat_val">0%</div>
                                <div class="measure-label">Body Fat %</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="measure-card">
                                <div class="measure-value" id="muscle_val">0 kg</div>
                                <div class="measure-label">Muscle Mass</div>
                            </div>
                        </div>
                    </div>
                    <button class="capture-btn w-100 mt-3" onclick="saveMeasurements()">
                        <i class="fa-solid fa-camera"></i> Save Measurements
                    </button>
                </div>
            </div>
        </div>

        <!-- Middle Column: Video Feed & Exercise -->
        <div class="col-lg-5">
            <div class="video-container">
                <video class="input_video" autoplay playsinline></video>
                <canvas class="output_canvas" width="1280" height="720"></canvas>
                <div class="position-absolute bottom-0 start-0 p-2 bg-dark bg-opacity-75 rounded m-2" style="z-index: 20;">
                    <i class="fa-solid fa-person-walking"></i> Full Body Tracking Active
                </div>
            </div>

            <!-- Exercise Selector -->
            <div class="mt-3 text-center">
                <label class="mb-2"><i class="fa-solid fa-dumbbell"></i> Select Activity:</label>
                <div>
                    <button class="exercise-btn active" data-exercise="squat" onclick="setExercise('squat')">🏋️ Squat</button>
                    <button class="exercise-btn" data-exercise="deadlift" onclick="setExercise('deadlift')">💪 Deadlift</button>
                    <button class="exercise-btn" data-exercise="overhead_press" onclick="setExercise('overhead_press')">🏋️ Overhead Press</button>
                    <button class="exercise-btn" data-exercise="running" onclick="setExercise('running')">🏃 Running</button>
                    <button class="exercise-btn" data-exercise="lunge" onclick="setExercise('lunge')">🦵 Lunge</button>
                </div>
            </div>

            <!-- Real-time Angles -->
            <div class="row g-2 mt-2">
                <div class="col-3">
                    <div class="angle-card">
                        <div class="angle-value" id="knee-angle">0°</div>
                        <div class="measure-label">Knee Angle</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="angle-card">
                        <div class="angle-value" id="hip-angle">0°</div>
                        <div class="measure-label">Hip Angle</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="angle-card">
                        <div class="angle-value" id="back-angle">0°</div>
                        <div class="measure-label">Back Angle</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="angle-card">
                        <div class="angle-value" id="ankle-angle">0°</div>
                        <div class="measure-label">Ankle Angle</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Analysis & Feedback -->
        <div class="col-lg-3">
            <div class="client-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-chart-line"></i> Biomechanical Analysis</h5>
                </div>
                <div class="card-body p-3">
                    <!-- Risk Indicator -->
                    <div class="text-center mb-3">
                        <span id="risk-indicator" class="risk-badge risk-low">LOW RISK</span>
                        <div class="mt-2">
                            <div class="posture-ring" id="posture-ring">
                                <div class="posture-score" id="posture-score">0</div>
                            </div>
                            <div class="small mt-1">Posture Score</div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <h6 class="mt-3"><i class="fa-solid fa-triangle-exclamation text-danger"></i> Injury Alerts</h6>
                    <div id="alerts-list" style="max-height: 150px; overflow-y: auto;">
                        <div class="alert-item">Waiting for pose detection...</div>
                    </div>

                    <!-- Recommendations -->
                    <h6 class="mt-3"><i class="fa-solid fa-lightbulb text-success"></i> Corrections</h6>
                    <div id="recommendations-list" style="max-height: 150px; overflow-y: auto;">
                        <div class="recommendation-item">Position yourself in front of camera</div>
                    </div>

                    <!-- Capture Analysis Button -->
                    <button class="capture-btn w-100 mt-3" onclick="captureAnalysis()">
                        <i class="fa-solid fa-floppy-disk"></i> Save This Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <div class="mt-3">Saving data...</div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-chart-simple"></i> Client Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="report-content">
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
    // SPORTS BIOMECHANICS SYSTEM
    // ==========================================

    // DOM Elements
    const videoElement = document.querySelector('.input_video');
    const canvasElement = document.querySelector('.output_canvas');
    const canvasCtx = canvasElement.getContext('2d');

    // UI Elements
    const kneeAngleElem = document.getElementById('knee-angle');
    const hipAngleElem = document.getElementById('hip-angle');
    const backAngleElem = document.getElementById('back-angle');
    const ankleAngleElem = document.getElementById('ankle-angle');
    const riskIndicator = document.getElementById('risk-indicator');
    const alertsList = document.getElementById('alerts-list');
    const recommendationsList = document.getElementById('recommendations-list');
    const postureScoreElem = document.getElementById('posture-score');
    const fpsCounter = document.getElementById('fps-counter');

    // State
    let currentExercise = 'squat';
    let frameCount = 0;
    let lastTime = performance.now();
    let currentAngles = { knee: 0, hip: 0, back: 0, ankle: 0 };
    let currentRisk = 'low';
    let currentScore = 0;
    let currentAlerts = [];
    let currentRecommendations = [];
    let isClientRegistered = false;

    // Initialize measurements sliders
    function initMeasurements() {
        const heightSlider = document.getElementById('height_slider');
        const weightSlider = document.getElementById('weight_slider');
        const waistSlider = document.getElementById('waist_slider');
        const neckSlider = document.getElementById('neck_slider');
        const hipSlider = document.getElementById('hip_slider');

        heightSlider.addEventListener('input', updateMeasurements);
        weightSlider.addEventListener('input', updateMeasurements);
        waistSlider.addEventListener('input', updateMeasurements);
        neckSlider.addEventListener('input', updateMeasurements);
        hipSlider.addEventListener('input', updateMeasurements);

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

        // Calculate BMI
        const bmi = weight / Math.pow(height / 100, 2);
        document.getElementById('bmi_val').innerText = bmi.toFixed(1);

        let bmiStatus = '';
        if (bmi < 18.5) bmiStatus = 'Underweight';
        else if (bmi < 25) bmiStatus = 'Normal';
        else if (bmi < 30) bmiStatus = 'Overweight';
        else bmiStatus = 'Obese';
        document.getElementById('bmi_status').innerHTML = bmiStatus;

        // Calculate Body Fat % (US Navy Method for men/women approximation)
        let bodyFat = 0;
        const gender = document.getElementById('gender')?.value || 'Male';
        if (gender === 'Male') {
            bodyFat = 86.010 * Math.log10(waist - neck) - 70.041 * Math.log10(height) + 36.76;
        } else {
            bodyFat = 163.205 * Math.log10(waist + hip - neck) - 97.684 * Math.log10(height) - 78.387;
        }
        bodyFat = Math.max(5, Math.min(50, bodyFat));
        document.getElementById('bodyfat_val').innerText = bodyFat.toFixed(1) + '%';

        // Calculate Muscle Mass (approximation)
        const muscleMass = weight * (1 - bodyFat / 100) * 0.5;
        document.getElementById('muscle_val').innerText = muscleMass.toFixed(1) + ' kg';

        return { height, weight, waist, neck, hip, bmi, bodyFat, muscleMass };
    }

    // Calculate angle between three points
    function calculateAngle(pointA, pointB, pointC) {
        const vectorAB = { x: pointA.x - pointB.x, y: pointA.y - pointB.y };
        const vectorCB = { x: pointC.x - pointB.x, y: pointC.y - pointB.y };
        const dotProduct = vectorAB.x * vectorCB.x + vectorAB.y * vectorCB.y;
        const magAB = Math.sqrt(vectorAB.x ** 2 + vectorAB.y ** 2);
        const magCB = Math.sqrt(vectorCB.x ** 2 + vectorCB.y ** 2);
        const angle = Math.acos(dotProduct / (magAB * magCB)) * 180 / Math.PI;
        return Math.round(angle);
    }

    // Exercise thresholds
    const thresholds = {
        squat: { knee: { min: 70, max: 110, optimal: [90,100] }, hip: { min: 60, max: 100, optimal: [70,90] }, back: { min: 0, max: 25, optimal: [0,15] }, ankle: { min: 50, max: 90, optimal: [60,80] } },
        deadlift: { knee: { min: 120, max: 160, optimal: [130,150] }, hip: { min: 20, max: 60, optimal: [30,50] }, back: { min: 0, max: 15, optimal: [0,10] }, ankle: { min: 70, max: 100, optimal: [80,90] } },
        overhead_press: { knee: { min: 170, max: 180, optimal: [175,180] }, hip: { min: 170, max: 180, optimal: [175,180] }, back: { min: 0, max: 20, optimal: [0,10] }, ankle: { min: 80, max: 100, optimal: [85,95] } },
        running: { knee: { min: 140, max: 175, optimal: [150,170] }, hip: { min: 150, max: 180, optimal: [160,175] }, back: { min: 0, max: 20, optimal: [5,15] }, ankle: { min: 60, max: 110, optimal: [70,100] } },
        lunge: { knee: { min: 80, max: 110, optimal: [85,100] }, hip: { min: 70, max: 100, optimal: [75,95] }, back: { min: 0, max: 20, optimal: [0,15] }, ankle: { min: 60, max: 90, optimal: [65,85] } }
    };

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

        // Extract landmarks
        const leftShoulder = landmarks[11];
        const rightShoulder = landmarks[12];
        const leftHip = landmarks[23];
        const rightHip = landmarks[24];
        const leftKnee = landmarks[25];
        const rightKnee = landmarks[26];
        const leftAnkle = landmarks[27];
        const rightAnkle = landmarks[28];

        let knee = 0, hip = 0, back = 0, ankle = 0;

        if (leftHip && leftKnee && leftAnkle) {
            knee = calculateAngle(leftHip, leftKnee, leftAnkle);
        }
        if (leftShoulder && leftHip && leftKnee) {
            hip = calculateAngle(leftShoulder, leftHip, leftKnee);
        }
        if (leftKnee && leftAnkle && landmarks[31]) {
            ankle = calculateAngle(leftKnee, leftAnkle, landmarks[31]);
        }
        if (leftShoulder && leftHip) {
            const centerShoulder = { x: (leftShoulder.x + rightShoulder.x)/2, y: (leftShoulder.y + rightShoulder.y)/2 };
            const centerHip = { x: (leftHip.x + rightHip.x)/2, y: (leftHip.y + rightHip.y)/2 };
            back = Math.abs(Math.round(Math.atan2(centerHip.y - centerShoulder.y, centerHip.x - centerShoulder.x) * 180 / Math.PI));
        }

        currentAngles = { knee, hip, back, ankle };

        // Evaluate each angle
        const kneeStatus = evaluateAngle(knee, 'knee');
        const hipStatus = evaluateAngle(hip, 'hip');
        const backStatus = evaluateAngle(back, 'back');
        const ankleStatus = evaluateAngle(ankle, 'ankle');

        // Generate alerts
        const alerts = [];
        if (backStatus === 'danger') alerts.push("⚠️ CRITICAL: Poor back position - Spinal injury risk!");
        else if (backStatus === 'warning') alerts.push("⚠️ Back angle suboptimal - Adjust posture");

        if (kneeStatus === 'danger') alerts.push("⚠️ Knee angle dangerous - Ligament strain risk");
        else if (kneeStatus === 'warning') alerts.push("⚠️ Knee angle needs adjustment");

        if (hipStatus === 'danger') alerts.push("⚠️ Hip mobility issue - Lower back compensation risk");

        // Generate recommendations
        const recommendations = [];
        if (backStatus !== 'optimal') recommendations.push("✅ Maintain neutral spine, engage core");
        if (kneeStatus !== 'optimal') recommendations.push("✅ Adjust knee position, track over toes");
        if (hipStatus !== 'optimal') recommendations.push("✅ Push hips back, maintain hip hinge");

        // Calculate score
        let score = 100;
        if (backStatus === 'danger') score -= 40;
        else if (backStatus === 'warning') score -= 20;
        if (kneeStatus === 'danger') score -= 25;
        else if (kneeStatus === 'warning') score -= 12;
        if (hipStatus === 'danger') score -= 20;
        else if (hipStatus === 'warning') score -= 10;
        if (ankleStatus === 'danger') score -= 15;
        else if (ankleStatus === 'warning') score -= 8;
        score = Math.max(0, Math.min(100, score));

        // Determine risk
        let risk = 'low';
        if (score < 40) risk = 'critical';
        else if (score < 60) risk = 'high';
        else if (score < 80) risk = 'moderate';

        currentRisk = risk;
        currentScore = score;
        currentAlerts = alerts;
        currentRecommendations = recommendations;

        return { angles: currentAngles, alerts, recommendations, score, risk };
    }

    // Update UI
    function updateUI(analysis) {
        if (!analysis) return;

        kneeAngleElem.innerHTML = analysis.angles.knee + '°';
        hipAngleElem.innerHTML = analysis.angles.hip + '°';
        backAngleElem.innerHTML = analysis.angles.back + '°';
        ankleAngleElem.innerHTML = analysis.angles.ankle + '°';

        postureScoreElem.innerHTML = analysis.score;
        const ring = document.getElementById('posture-ring');
        if (ring) {
            ring.style.background = `conic-gradient(var(--primary) ${analysis.score * 3.6}deg, #333 ${analysis.score * 3.6}deg)`;
        }

        riskIndicator.className = `risk-badge risk-${analysis.risk}`;
        riskIndicator.innerHTML = analysis.risk.toUpperCase() + ' RISK';

        if (analysis.alerts.length > 0) {
            alertsList.innerHTML = analysis.alerts.map(a => `<div class="alert-item">${a}</div>`).join('');
        } else {
            alertsList.innerHTML = '<div class="alert-item"><i class="fa-solid fa-check-circle" style="color:#00ff88"></i> No immediate risks</div>';
        }

        if (analysis.recommendations.length > 0) {
            recommendationsList.innerHTML = analysis.recommendations.map(r => `<div class="recommendation-item">${r}</div>`).join('');
        }
    }

    // Client registration
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
            isClientRegistered = true;
            alert('Client Registered! ID: ' + result.client_id);
        } else {
            alert('Registration failed: ' + result.message);
        }
        hideLoading();
    });

    async function saveMeasurements() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }
        showLoading();

        const measurements = updateMeasurements();
        const data = {
            action: 'save_measurements',
            height: measurements.height,
            weight: measurements.weight,
            bmi: measurements.bmi,
            waist: measurements.waist,
            neck: measurements.neck,
            hip: measurements.hip,
            body_fat: measurements.bodyFat,
            muscle_mass: measurements.muscleMass
        };

        await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });

        alert('Measurements saved!');
        hideLoading();
    }

    async function captureAnalysis() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }
        showLoading();

        const data = {
            action: 'save_biomechanics',
            exercise: currentExercise,
            knee: currentAngles.knee,
            hip: currentAngles.hip,
            back: currentAngles.back,
            ankle: currentAngles.ankle,
            posture_score: currentScore,
            risk_level: currentRisk,
            alerts: currentAlerts.slice(0, 3).join('; '),
            recommendations: currentRecommendations.slice(0, 3).join('; ')
        };

        await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });

        alert('Analysis saved!');
        hideLoading();
    }

    async function viewReport() {
        if (!isClientRegistered) {
            alert('Please register client first!');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('reportModal'));
        const reportContent = document.getElementById('report-content');
        reportContent.innerHTML = '<div class="text-center">Loading report...</div>';
        modal.show();

        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'get_history' })
        });
        const data = await response.json();

        let html = `<div class="client-info mb-4">
            <h4>Client: ${document.getElementById('full_name').value}</h4>
            <p>Phone: ${document.getElementById('phone').value} | Sport: ${document.getElementById('sport_type').value}</p>
        </div>`;

        if (data.measurements && data.measurements.length > 0) {
            html += `<h5>📊 Measurement History</h5><div class="table-responsive"><table class="table table-dark table-sm">
                <thead><tr><th>Date</th><th>Height</th><th>Weight</th><th>BMI</th><th>Body Fat</th></tr></thead><tbody>`;
            data.measurements.forEach(m => {
                html += `<tr><td>${new Date(m.measurement_date).toLocaleDateString()}</td>
                        <td>${m.height_cm} cm</td><td>${m.weight_kg} kg</td>
                        <td>${m.bmi}</td><td>${m.body_fat_percentage}%</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (data.biomechanics && data.biomechanics.length > 0) {
            html += `<h5 class="mt-4">🏃 Biomechanics History</h5><div class="table-responsive"><table class="table table-dark table-sm">
                <thead><tr><th>Date</th><th>Exercise</th><th>Posture Score</th><th>Risk</th><th>Key Alert</th></tr></thead><tbody>`;
            data.biomechanics.forEach(b => {
                html += `<tr><td>${new Date(b.analysis_timestamp).toLocaleString()}</td>
                        <td>${b.exercise_type}</td><td>${b.posture_score}%</td>
                        <td><span class="risk-badge risk-${b.risk_level}">${b.risk_level}</span></td>
                        <td>${b.alerts?.substring(0, 50) || '-'}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        reportContent.innerHTML = html;
    }

    function setExercise(exercise) {
        currentExercise = exercise;
        document.querySelectorAll('.exercise-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-exercise="${exercise}"]`).classList.add('active');
    }

    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // MediaPipe Pose
    function onPoseResults(results) {
        canvasCtx.save();
        canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
        canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

        if (results.poseLandmarks) {
            drawConnectors(canvasCtx, results.poseLandmarks, POSE_CONNECTIONS, { color: '#00ff88', lineWidth: 3 });
            drawLandmarks(canvasCtx, results.poseLandmarks, { color: '#00b4db', lineWidth: 2, radius: 5 });

            const analysis = analyzeBiomechanics(results.poseLandmarks);
            if (analysis) updateUI(analysis);
        } else {
            alertsList.innerHTML = '<div class="alert-item">No person detected. Stand in front of camera</div>';
        }

        canvasCtx.restore();

        frameCount++;
        const now = performance.now();
        if (now - lastTime >= 1000) {
            fpsCounter.innerHTML = `FPS: ${frameCount}`;
            frameCount = 0;
            lastTime = now;
        }
    }

    // Initialize MediaPipe
    const pose = new Pose({
        locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
    });
    pose.setOptions({ modelComplexity: 1, smoothLandmarks: true, minDetectionConfidence: 0.5, minTrackingConfidence: 0.5 });
    pose.onResults(onPoseResults);

    const camera = new Camera(videoElement, {
        onFrame: async () => { await pose.send({ image: videoElement }); },
        width: 1280, height: 720
    });
    camera.start();

    // Initialize
    initMeasurements();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>