<?php
// ==========================================
// SPORTS BIOMECHANICS & INJURY PREVENTION SYSTEM
// ==========================================

session_start();

// Handle AJAX requests for logging analysis data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['action'])) {
        $logFile = 'injury_analysis_log.json';

        if ($data['action'] === 'log_analysis') {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'exercise' => $data['exercise'] ?? 'unknown',
                'risk_level' => $data['risk_level'] ?? 'unknown',
                'angles' => $data['angles'] ?? [],
                'alerts' => $data['alerts'] ?? [],
                'recommendations' => $data['recommendations'] ?? []
            ];

            $existing = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            array_unshift($existing, $logEntry);
            $existing = array_slice($existing, 0, 100); // Keep last 100 entries

            file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($data['action'] === 'get_history') {
            $existing = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            echo json_encode(['status' => 'success', 'history' => $existing]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AI Sports Biomechanics & Injury Prevention System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #ffffff;
            font-family: 'Segoe UI', 'Poppins', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(0,0,0,0.8) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,255,150,0.3);
        }

        .app-container {
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .video-container {
            position: relative;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            background-color: #000;
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

        .analysis-panel {
            background: rgba(30,30,40,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(0,255,150,0.2);
        }

        .angle-card {
            background: rgba(0,0,0,0.5);
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            transition: transform 0.3s;
            border-left: 4px solid #00b4db;
        }

        .angle-card:hover {
            transform: translateY(-5px);
        }

        .angle-value {
            font-size: 2rem;
            font-weight: bold;
            color: #00b4db;
        }

        .angle-label {
            font-size: 0.85rem;
            color: #aaa;
            margin-top: 5px;
        }

        .risk-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .risk-low { background: #00ff00; color: #000; }
        .risk-moderate { background: #ffa500; color: #000; }
        .risk-high { background: #ff0000; color: #fff; }
        .risk-critical { background: #8b0000; color: #fff; animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .alert-item {
            background: rgba(255,0,0,0.2);
            border-left: 4px solid #ff0000;
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .recommendation-item {
            background: rgba(0,255,150,0.1);
            border-left: 4px solid #00ff00;
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .exercise-selector {
            background: rgba(0,0,0,0.5);
            border-radius: 15px;
            padding: 10px;
        }

        .exercise-btn {
            background: transparent;
            border: 2px solid #00b4db;
            color: #00b4db;
            padding: 8px 20px;
            border-radius: 25px;
            margin: 5px;
            transition: all 0.3s;
        }

        .exercise-btn.active, .exercise-btn:hover {
            background: #00b4db;
            color: #000;
        }

        .feedback-icon {
            font-size: 3rem;
            animation: bounce 1s ease infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(0,180,219,0.2), rgba(0,131,176,0.2));
            border-radius: 15px;
            padding: 15px;
            text-align: center;
        }

        .joint-angle {
            position: relative;
            display: inline-block;
        }

        .skeleton-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            z-index: 20;
        }

        @media (max-width: 768px) {
            .angle-value { font-size: 1.2rem; }
            .analysis-panel { padding: 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fa-solid fa-bone"></i> AI Sports Biomechanics & Injury Prevention
        </a>
        <div class="d-flex">
            <span id="fps-counter" class="badge bg-info me-2">FPS: 0</span>
            <button class="btn btn-outline-info btn-sm" onclick="toggleHistory()">
                <i class="fa-solid fa-chart-line"></i> History
            </button>
        </div>
    </div>
</nav>

<div class="app-container">
    <div class="row g-4">
        <!-- Video Feed Column -->
        <div class="col-lg-8">
            <div class="video-container">
                <video class="input_video" autoplay playsinline></video>
                <canvas class="output_canvas" width="1280" height="720"></canvas>
                <div class="skeleton-overlay">
                    <i class="fa-solid fa-person-walking"></i> Full Body Tracking Active
                </div>
            </div>

            <!-- Exercise Selector -->
            <div class="exercise-selector mt-3">
                <label class="mb-2"><i class="fa-solid fa-dumbbell"></i> Select Activity:</label>
                <div>
                    <button class="exercise-btn active" data-exercise="squat" onclick="setExercise('squat')">
                        <i class="fa-solid fa-person-walking-arrow-right"></i> Squat
                    </button>
                    <button class="exercise-btn" data-exercise="deadlift" onclick="setExercise('deadlift')">
                        <i class="fa-solid fa-weight-hanging"></i> Deadlift
                    </button>
                    <button class="exercise-btn" data-exercise="overhead_press" onclick="setExercise('overhead_press')">
                        <i class="fa-solid fa-hand-fist"></i> Overhead Press
                    </button>
                    <button class="exercise-btn" data-exercise="running" onclick="setExercise('running')">
                        <i class="fa-solid fa-person-running"></i> Running Form
                    </button>
                    <button class="exercise-btn" data-exercise="lunge" onclick="setExercise('lunge')">
                        <i class="fa-solid fa-person-walking-ladder"></i> Lunge
                    </button>
                </div>
            </div>
        </div>

        <!-- Analysis Column -->
        <div class="col-lg-4">
            <div class="analysis-panel">
                <h5 class="mb-3">
                    <i class="fa-solid fa-chart-simple"></i> Real-Time Biomechanical Analysis
                    <span id="risk-indicator" class="risk-badge risk-low ms-2">Low Risk</span>
                </h5>

                <!-- Key Angles -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="angle-card">
                            <div class="angle-value" id="knee-angle">0°</div>
                            <div class="angle-label"><i class="fa-solid fa-leg"></i> Knee Angle</div>
                            <small class="text-muted" id="knee-status">Optimal: 90-100°</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="angle-card">
                            <div class="angle-value" id="hip-angle">0°</div>
                            <div class="angle-label"><i class="fa-solid fa-hip"></i> Hip Angle</div>
                            <small class="text-muted" id="hip-status">Optimal: 70-90°</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="angle-card">
                            <div class="angle-value" id="back-angle">0°</div>
                            <div class="angle-label"><i class="fa-solid fa-spine"></i> Back Angle</div>
                            <small class="text-muted" id="back-status">Straight: 0-20°</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="angle-card">
                            <div class="angle-value" id="ankle-angle">0°</div>
                            <div class="angle-label"><i class="fa-solid fa-shoe-prints"></i> Ankle Angle</div>
                            <small class="text-muted" id="ankle-status">Optimal: 60-80°</small>
                        </div>
                    </div>
                </div>

                <!-- Posture Assessment -->
                <div class="stat-card mb-3">
                    <h6><i class="fa-solid fa-person"></i> Posture Assessment</h6>
                    <div id="posture-score" class="display-4">0%</div>
                    <div class="progress mt-2">
                        <div id="posture-progress" class="progress-bar bg-success" style="width: 0%"></div>
                    </div>
                    <div id="posture-message" class="mt-2 small">Analyzing posture...</div>
                </div>

                <!-- Alerts & Recommendations -->
                <div id="alerts-container">
                    <h6><i class="fa-solid fa-triangle-exclamation"></i> Injury Risk Alerts</h6>
                    <div id="alerts-list" class="mb-3">
                        <div class="alert-item">Waiting for pose detection...</div>
                    </div>
                </div>

                <div id="recommendations-container">
                    <h6><i class="fa-solid fa-lightbulb"></i> Real-Time Corrections</h6>
                    <div id="recommendations-list">
                        <div class="recommendation-item">Position yourself in front of camera</div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <div class="stat-card">
                            <i class="fa-solid fa-clock"></i>
                            <div id="analysis-count">0</div>
                            <small>Analyses Performed</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <i class="fa-solid fa-flag-checkered"></i>
                            <div id="good-posture-count">0</div>
                            <small>Good Postures</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-history"></i> Analysis History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="history-content">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================
    // SPORTS BIOMECHANICS ANALYSIS SYSTEM
    // ==========================================

    // DOM Elements
    const videoElement = document.querySelector('.input_video');
    const canvasElement = document.querySelector('.output_canvas');
    const canvasCtx = canvasElement.getContext('2d');

    // Analysis Elements
    const kneeAngleElem = document.getElementById('knee-angle');
    const hipAngleElem = document.getElementById('hip-angle');
    const backAngleElem = document.getElementById('back-angle');
    const ankleAngleElem = document.getElementById('ankle-angle');
    const riskIndicator = document.getElementById('risk-indicator');
    const alertsList = document.getElementById('alerts-list');
    const recommendationsList = document.getElementById('recommendations-list');
    const postureScoreElem = document.getElementById('posture-score');
    const postureProgress = document.getElementById('posture-progress');
    const analysisCountElem = document.getElementById('analysis-count');
    const goodPostureCountElem = document.getElementById('good-posture-count');
    const fpsCounter = document.getElementById('fps-counter');

    // State variables
    let currentExercise = 'squat';
    let analysisCount = 0;
    let goodPostureCount = 0;
    let frameCount = 0;
    let lastTime = performance.now();
    let lastLogTime = 0;

    // Angle thresholds for different exercises
    const exerciseThresholds = {
        squat: {
            knee: { min: 70, max: 110, optimal: [90, 100] },
            hip: { min: 60, max: 100, optimal: [70, 90] },
            back: { min: 0, max: 30, optimal: [0, 20] },
            ankle: { min: 50, max: 90, optimal: [60, 80] },
            alerts: {
                knee_valgus: "Knee cave detected - risk of ACL injury! Keep knees aligned with toes",
                back_rounding: "Lower back rounding - risk of disc herniation! Maintain neutral spine",
                depth_insufficient: "Squat depth insufficient - quadriceps imbalance risk",
                heels_lifting: "Heels lifting - ankle mobility issue, risk of fall"
            }
        },
        deadlift: {
            knee: { min: 120, max: 160, optimal: [130, 150] },
            hip: { min: 20, max: 60, optimal: [30, 50] },
            back: { min: 0, max: 15, optimal: [0, 10] },
            ankle: { min: 70, max: 100, optimal: [80, 90] },
            alerts: {
                back_rounding: "⚠️ CRITICAL: Rounded back - high risk of herniated disc!",
                knees_over_toes: "Knees extending past toes - patellar tendon stress",
                hips_too_high: "Hips too high - hamstring strain risk",
                bar_path: "Bar not traveling vertically - lower back strain"
            }
        },
        overhead_press: {
            knee: { min: 170, max: 180, optimal: [175, 180] },
            hip: { min: 170, max: 180, optimal: [175, 180] },
            back: { min: 0, max: 25, optimal: [0, 15] },
            ankle: { min: 80, max: 100, optimal: [85, 95] },
            alerts: {
                lumbar_hyperextension: "Lower back arching - risk of spinal compression!",
                uneven_shoulders: "Uneven shoulder height - rotator cuff injury risk",
                head_protrusion: "Head forward posture - neck strain",
                elbow_flare: "Elbows flaring out - shoulder impingement risk"
            }
        },
        running: {
            knee: { min: 140, max: 175, optimal: [150, 170] },
            hip: { min: 150, max: 180, optimal: [160, 175] },
            back: { min: 0, max: 20, optimal: [5, 15] },
            ankle: { min: 60, max: 110, optimal: [70, 100] },
            alerts: {
                overstriding: "Overstriding - high impact, risk of shin splints!",
                heel_striking: "Heel striking - knee and hip joint stress",
                excessive_vertical: "Excessive vertical oscillation - energy waste",
                pelvic_drop: "Pelvic drop on one side - IT band syndrome risk"
            }
        },
        lunge: {
            knee: { min: 80, max: 110, optimal: [85, 100] },
            hip: { min: 70, max: 100, optimal: [75, 95] },
            back: { min: 0, max: 20, optimal: [0, 15] },
            ankle: { min: 60, max: 90, optimal: [65, 85] },
            alerts: {
                knee_past_toes: "Knee extending past toes - patellofemoral pain risk",
                torso_lean: "Excessive forward lean - lumbar strain",
                hip_drop: "Hip dropping - gluteus medius weakness",
                balance_issue: "Balance issue - ankle sprain risk"
            }
        }
    };

    // Function to calculate angle between three points
    function calculateAngle(pointA, pointB, pointC) {
        const vectorAB = { x: pointA.x - pointB.x, y: pointA.y - pointB.y };
        const vectorCB = { x: pointC.x - pointB.x, y: pointC.y - pointB.y };

        const dotProduct = vectorAB.x * vectorCB.x + vectorAB.y * vectorCB.y;
        const magnitudeAB = Math.sqrt(vectorAB.x ** 2 + vectorAB.y ** 2);
        const magnitudeCB = Math.sqrt(vectorCB.x ** 2 + vectorCB.y ** 2);

        const angle = Math.acos(dotProduct / (magnitudeAB * magnitudeCB)) * 180 / Math.PI;
        return Math.round(angle);
    }

    // Calculate back angle using shoulder and hip points
    function calculateBackAngle(shoulder, hip, hip2 = null) {
        const leftShoulder = shoulder;
        const rightShoulder = { x: shoulder.x + 0.1, y: shoulder.y };
        const leftHip = hip;
        const rightHip = hip2 || { x: hip.x + 0.1, y: hip.y };

        const centerShoulder = { x: (leftShoulder.x + rightShoulder.x) / 2, y: (leftShoulder.y + rightShoulder.y) / 2 };
        const centerHip = { x: (leftHip.x + rightHip.x) / 2, y: (leftHip.y + rightHip.y) / 2 };

        const angle = Math.atan2(centerHip.y - centerShoulder.y, centerHip.x - centerShoulder.x) * 180 / Math.PI;
        return Math.abs(Math.round(angle));
    }

    // Main analysis function
    function analyzeBiomechanics(landmarks) {
        if (!landmarks) return null;

        // Extract key landmarks (MediaPipe Pose indices)
        // 11: left shoulder, 12: right shoulder, 13: left elbow, 14: right elbow
        // 15: left wrist, 16: right wrist, 23: left hip, 24: right hip
        // 25: left knee, 26: right knee, 27: left ankle, 28: right ankle, 31: left heel, 32: right heel

        const leftShoulder = landmarks[11];
        const rightShoulder = landmarks[12];
        const leftHip = landmarks[23];
        const rightHip = landmarks[24];
        const leftKnee = landmarks[25];
        const rightKnee = landmarks[26];
        const leftAnkle = landmarks[27];
        const rightAnkle = landmarks[28];

        // Calculate angles (using left side for analysis)
        let kneeAngle = 0, hipAngle = 0, ankleAngle = 0, backAngle = 0;

        if (leftHip && leftKnee && leftAnkle) {
            kneeAngle = calculateAngle(leftHip, leftKnee, leftAnkle);
        }

        if (leftShoulder && leftHip && leftKnee) {
            hipAngle = calculateAngle(leftShoulder, leftHip, leftKnee);
        }

        if (leftKnee && leftAnkle && landmarks[31]) {
            ankleAngle = calculateAngle(leftKnee, leftAnkle, landmarks[31]);
        }

        if (leftShoulder && leftHip) {
            backAngle = calculateBackAngle(leftShoulder, leftHip);
        }

        // Get thresholds for current exercise
        const thresholds = exerciseThresholds[currentExercise];

        // Evaluate each angle
        const evaluations = {
            knee: evaluateAngle(kneeAngle, thresholds.knee),
            hip: evaluateAngle(hipAngle, thresholds.hip),
            back: evaluateAngle(backAngle, thresholds.back),
            ankle: evaluateAngle(ankleAngle, thresholds.ankle)
        };

        // Generate alerts and recommendations
        const alerts = [];
        const recommendations = [];

        // Knee analysis
        if (evaluations.knee.status === 'danger') {
            alerts.push(`⚠️ Knee angle ${kneeAngle}° - ${getKneeAlert(currentExercise)}`);
            recommendations.push("✅ Bend knees to 90°, track over toes, don't let knees cave inward");
        } else if (evaluations.knee.status === 'warning') {
            recommendations.push(`📐 Adjust knee angle to ${thresholds.knee.optimal[0]}-${thresholds.knee.optimal[1]}°`);
        }

        // Back analysis (most critical for injury prevention)
        if (evaluations.back.status === 'danger') {
            alerts.push(`🔴 CRITICAL: Back angle ${backAngle}° - ${getBackAlert(currentExercise)}`);
            recommendations.push("✅ Maintain neutral spine, engage core, avoid rounding or hyperextension");
        } else if (evaluations.back.status === 'warning') {
            recommendations.push("📏 Keep back straight, brace your core muscles");
        }

        // Hip analysis
        if (evaluations.hip.status === 'danger') {
            alerts.push(`⚠️ Hip angle ${hipAngle}° - Limited hip mobility, risk of lower back compensation`);
            recommendations.push("✅ Push hips back, maintain hip hinge pattern");
        } else if (evaluations.hip.status === 'warning') {
            recommendations.push("🦵 Activate glutes, control hip movement");
        }

        // Ankle analysis
        if (evaluations.ankle.status === 'danger') {
            alerts.push(`⚠️ Ankle angle ${ankleAngle}° - Limited dorsiflexion, risk of knee compensation`);
            recommendations.push("✅ Improve ankle mobility with stretching exercises");
        }

        // Calculate overall posture score (0-100%)
        const scoreWeights = { knee: 0.25, hip: 0.25, back: 0.35, ankle: 0.15 };
        let totalScore = 0;

        for (const [joint, evalData] of Object.entries(evaluations)) {
            let jointScore = 0;
            if (evalData.status === 'optimal') jointScore = 100;
            else if (evalData.status === 'good') jointScore = 75;
            else if (evalData.status === 'warning') jointScore = 50;
            else if (evalData.status === 'danger') jointScore = 25;
            totalScore += jointScore * scoreWeights[joint];
        }

        totalScore = Math.round(totalScore);

        // Determine risk level
        let riskLevel = 'low';
        if (totalScore < 40) riskLevel = 'critical';
        else if (totalScore < 60) riskLevel = 'high';
        else if (totalScore < 80) riskLevel = 'moderate';
        else riskLevel = 'low';

        // Update counters
        analysisCount++;
        if (totalScore >= 80) goodPostureCount++;

        // Log analysis periodically (every 30 frames)
        if (frameCount - lastLogTime > 30) {
            logAnalysisToServer(riskLevel, { knee: kneeAngle, hip: hipAngle, back: backAngle, ankle: ankleAngle }, alerts, recommendations);
            lastLogTime = frameCount;
        }

        return {
            angles: { knee: kneeAngle, hip: hipAngle, back: backAngle, ankle: ankleAngle },
            evaluations,
            alerts,
            recommendations,
            totalScore,
            riskLevel
        };
    }

    function evaluateAngle(angle, thresholds) {
        if (!thresholds) return { status: 'unknown', value: angle };

        const [optMin, optMax] = thresholds.optimal;
        const { min, max } = thresholds;

        if (angle >= optMin && angle <= optMax) return { status: 'optimal', value: angle };
        if (angle >= min && angle <= max) return { status: 'good', value: angle };
        if ((angle < min && angle > min - 15) || (angle > max && angle < max + 15)) return { status: 'warning', value: angle };
        return { status: 'danger', value: angle };
    }

    function getKneeAlert(exercise) {
        const alerts = {
            squat: "Knee angle outside safe range - patellar tendonitis risk",
            deadlift: "Knee hyperextension - ligament strain risk",
            overhead_press: "Knee buckling - instability risk",
            running: "Knee angle too straight - joint impact stress",
            lunge: "Knee past toes - patellofemoral pain syndrome"
        };
        return alerts[exercise] || "Knee angle potentially dangerous - adjust position";
    }

    function getBackAlert(exercise) {
        const alerts = {
            squat: "Lower back rounding - disc herniation risk! Keep chest up",
            deadlift: "⚠️ SEVERE: Rounded back - high risk of spinal injury! STOP and reset",
            overhead_press: "Back hyperextension - spinal compression! Brace core",
            running: "Excessive forward lean - lower back strain",
            lunge: "Torso leaning too far - lumbar lordosis risk"
        };
        return alerts[exercise] || "Back position compromised - risk of spinal injury";
    }

    function getRiskClass(riskLevel) {
        return `risk-${riskLevel}`;
    }

    async function logAnalysisToServer(riskLevel, angles, alerts, recommendations) {
        try {
            await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    action: 'log_analysis',
                    exercise: currentExercise,
                    risk_level: riskLevel,
                    angles: angles,
                    alerts: alerts,
                    recommendations: recommendations.slice(0, 3)
                })
            });
        } catch(e) { console.error('Log error:', e); }
    }

    function setExercise(exercise) {
        currentExercise = exercise;
        document.querySelectorAll('.exercise-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-exercise="${exercise}"]`).classList.add('active');

        recommendationsList.innerHTML = `<div class="recommendation-item">🎯 ${exercise.toUpperCase()} mode activated. Perform the movement for analysis.</div>`;
    }

    async function toggleHistory() {
        const modal = new bootstrap.Modal(document.getElementById('historyModal'));
        const historyContent = document.getElementById('history-content');
        historyContent.innerHTML = '<div class="text-center">Loading history...</div>';
        modal.show();

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'get_history' })
            });
            const data = await response.json();

            if (data.history && data.history.length > 0) {
                let html = '<div class="list-group">';
                data.history.slice(0, 20).forEach(entry => {
                    html += `
                        <div class="list-group-item bg-dark text-white">
                            <div class="d-flex justify-content-between">
                                <strong>${entry.exercise.toUpperCase()}</strong>
                                <span class="risk-badge risk-${entry.risk_level}">${entry.risk_level} risk</span>
                            </div>
                            <small>${new Date(entry.timestamp).toLocaleString()}</small>
                            <div class="mt-1 small">Angles: Knee ${entry.angles.knee}°, Hip ${entry.angles.hip}°, Back ${entry.angles.back}°</div>
                            ${entry.alerts.length ? `<div class="text-danger small mt-1">⚠️ ${entry.alerts[0]}</div>` : ''}
                        </div>
                    `;
                });
                html += '</div>';
                historyContent.innerHTML = html;
            } else {
                historyContent.innerHTML = '<div class="text-center">No analysis history yet.</div>';
            }
        } catch(e) {
            historyContent.innerHTML = '<div class="text-danger">Error loading history</div>';
        }
    }

    // MediaPipe Pose Detection
    function onPoseResults(results) {
        canvasCtx.save();
        canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
        canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

        if (results.poseLandmarks) {
            drawConnectors(canvasCtx, results.poseLandmarks, POSE_CONNECTIONS, { color: '#00ff00', lineWidth: 3 });
            drawLandmarks(canvasCtx, results.poseLandmarks, { color: '#ff0000', lineWidth: 2, radius: 5 });

            const analysis = analyzeBiomechanics(results.poseLandmarks);

            if (analysis) {
                // Update UI
                kneeAngleElem.innerHTML = `${analysis.angles.knee}°`;
                hipAngleElem.innerHTML = `${analysis.angles.hip}°`;
                backAngleElem.innerHTML = `${analysis.angles.back}°`;
                ankleAngleElem.innerHTML = `${analysis.angles.ankle}°`;

                postureScoreElem.innerHTML = `${analysis.totalScore}%`;
                postureProgress.style.width = `${analysis.totalScore}%`;

                riskIndicator.className = `risk-badge ${getRiskClass(analysis.riskLevel)}`;
                riskIndicator.innerHTML = `${analysis.riskLevel.toUpperCase()} Risk`;

                if (analysis.alerts.length > 0) {
                    alertsList.innerHTML = analysis.alerts.map(alert => `<div class="alert-item"><i class="fa-solid fa-bell"></i> ${alert}</div>`).join('');
                } else {
                    alertsList.innerHTML = '<div class="alert-item"><i class="fa-solid fa-check-circle" style="color:#00ff00"></i> No immediate risks detected</div>';
                }

                if (analysis.recommendations.length > 0) {
                    recommendationsList.innerHTML = analysis.recommendations.map(rec => `<div class="recommendation-item"><i class="fa-solid fa-arrow-right"></i> ${rec}</div>`).join('');
                }

                analysisCountElem.innerHTML = analysisCount;
                goodPostureCountElem.innerHTML = goodPostureCount;
            }
        } else {
            alertsList.innerHTML = '<div class="alert-item">No person detected. Stand in front of camera for full body analysis.</div>';
            recommendationsList.innerHTML = '<div class="recommendation-item">Position yourself so full body is visible</div>';
        }

        canvasCtx.restore();

        // FPS calculation
        frameCount++;
        const now = performance.now();
        if (now - lastTime >= 1000) {
            fpsCounter.innerHTML = `FPS: ${frameCount}`;
            frameCount = 0;
            lastTime = now;
        }
    }

    // Initialize MediaPipe Pose
    const pose = new Pose({
        locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
    });

    pose.setOptions({
        modelComplexity: 1,
        smoothLandmarks: true,
        enableSegmentation: false,
        smoothSegmentation: false,
        minDetectionConfidence: 0.5,
        minTrackingConfidence: 0.5
    });

    pose.onResults(onPoseResults);

    // Start camera
    const camera = new Camera(videoElement, {
        onFrame: async () => { await pose.send({ image: videoElement }); },
        width: 1280,
        height: 720
    });

    camera.start().then(() => {
        console.log("Camera started - Sports Biomechanics System Active");
    }).catch(err => {
        alert("Please allow camera access for full body analysis!");
        console.error(err);
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>