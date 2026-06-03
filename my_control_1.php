<?php
// ==========================================
// 1. PHP BACKEND: Handles the Volume POST request
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['volume'])) {
        // Sanitize input to ensure it's between 0 and 100
        $volume = intval($_POST['volume']);
        $volume = max(0, min(100, $volume));

        $os = PHP_OS;
        $message = "";

        try {
                if (stristr($os, 'Darwin')) {
                        // MacOS
                        shell_exec("osascript -e 'set volume output volume $volume'");
                        $message = "Mac volume set to $volume%";
                } elseif (stristr($os, 'WIN')) {
                        // Windows (Requires nircmd.exe in the same folder or system path)
                        // NirCmd uses a scale of 0 to 65535
                        $nircmdVolume = intval($volume * 655.35);
                        shell_exec("nircmd.exe setsysvolume $nircmdVolume");
                        $message = "Windows volume set to $volume%";
                } else {
                        // Linux (PulseAudio/ALSA)
                        shell_exec("amixer -D pulse sset Master $volume%");
                        $message = "Linux volume set to $volume%";
                }

                echo json_encode(['status' => 'success', 'volume' => $volume, 'msg' => $message]);
        } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'msg' => 'Failed to execute command.']);
        }

        // Stop execution so HTML doesn't render on API calls
        exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AI Hand Gesture Volume Control</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/@mediapipe/control_utils/control_utils.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js" crossorigin="anonymous"></script>

        <style>
                body {
                        background-color: #121212;
                        color: #ffffff;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .app-container {
                        max-width: 800px;
                        margin: 40px auto;
                }
                .video-container {
                        position: relative;
                        width: 100%;
                        border-radius: 15px;
                        overflow: hidden;
                        box-shadow: 0 10px 30px rgba(0,255,150,0.2);
                        background-color: #000;
                }
                /* Mirror the video so it feels like a looking glass */
                video, canvas {
                        width: 100%;
                        height: auto;
                        transform: scaleX(-1);
                }
                canvas {
                        position: absolute;
                        top: 0;
                        left: 0;
                        z-index: 10;
                }
                .controls-card {
                        background-color: #1e1e1e;
                        border: none;
                        border-radius: 15px;
                        margin-top: 20px;
                }
                .progress {
                        height: 30px;
                        border-radius: 15px;
                        background-color: #333;
                }
                .progress-bar {
                        background: linear-gradient(90deg, #00b4db 0%, #0083b0 100%);
                        font-weight: bold;
                        font-size: 1.1rem;
                        transition: width 0.1s ease;
                }
                .volume-icon {
                        font-size: 2.5rem;
                        color: #00b4db;
                        width: 60px;
                        text-align: center;
                }
                #status-indicator {
                        display: inline-block;
                        width: 12px;
                        height: 12px;
                        border-radius: 50%;
                        background-color: red;
                        margin-right: 8px;
                }
                .tracking-active {
                        background-color: #00ff00 !important;
                        box-shadow: 0 0 10px #00ff00;
                }
        </style>
</head>
<body>

<div class="container app-container">
        <div class="text-center mb-4">
                <h1 class="fw-bold"><i class="fa-solid fa-hand-sparkles text-info"></i> Gesture Volume Control</h1>
                <p class="text-muted">Pinch your index finger and thumb to change system volume.</p>
        </div>

        <div class="video-container">
                <video class="input_video" autoplay playsinline></video>
                <canvas class="output_canvas" width="640" height="480"></canvas>
        </div>

        <div class="card controls-card p-4 shadow">
                <div class="d-flex align-items-center mb-3">
                        <span id="status-indicator"></span>
                        <span id="status-text" class="fw-bold">Initializing AI...</span>
                </div>

                <div class="d-flex align-items-center">
                        <div class="volume-icon">
                                <i id="vol-icon" class="fa-solid fa-volume-low"></i>
                        </div>
                        <div class="flex-grow-1 mx-3">
                                <div class="progress">
                                        <div id="volume-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                        </div>
                </div>
        </div>
</div>

<script>
        // ==========================================
        // 2. JAVASCRIPT: MediaPipe Hand Tracking Logic
        // ==========================================

        const videoElement = document.getElementsByClassName('input_video')[0];
        const canvasElement = document.getElementsByClassName('output_canvas')[0];
        const canvasCtx = canvasElement.getContext('2d');

        const volumeBar = document.getElementById('volume-bar');
        const volIcon = document.getElementById('vol-icon');
        const statusIndicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');

        let lastSentVolume = -1;
        let lastSentTime = 0;

        // Send Volume to PHP safely (Throttled to prevent freezing the PC)
        function sendVolumeToPHP(volume) {
                const now = Date.now();
                // Only send if 300ms have passed AND volume changed by at least 2%
                if (now - lastSentTime > 300 && Math.abs(volume - lastSentVolume) >= 2) {

                        const formData = new FormData();
                        formData.append('volume', volume);

                        fetch('My_Control.php', {
                                method: 'POST',
                                body: formData
                        }).then(response => response.json())
                            .catch(err => console.error("PHP Error:", err));

                        lastSentVolume = volume;
                        lastSentTime = now;
                }
        }

        // Function executed every frame by MediaPipe
        function onResults(results) {
                // Clear canvas
                canvasCtx.save();
                canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);

                // Draw the video frame
                canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

                if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {

                        // Update UI Status
                        statusIndicator.classList.add('tracking-active');
                        statusText.innerText = "Tracking Hand...";

                        // Get first hand detected
                        const landmarks = results.multiHandLandmarks[0];

                        // Draw Hand Skeleton
                        drawConnectors(canvasCtx, landmarks, HAND_CONNECTIONS, {color: '#00ff00', lineWidth: 4});
                        drawLandmarks(canvasCtx, landmarks, {color: '#ff0000', lineWidth: 2, radius: 4});

                        // Get Thumb (4) and Index (8) tip coordinates
                        const thumb = landmarks[4];
                        const index = landmarks[8];

                        // Draw a line specifically between Thumb and Index
                        canvasCtx.beginPath();
                        canvasCtx.moveTo(thumb.x * canvasElement.width, thumb.y * canvasElement.height);
                        canvasCtx.lineTo(index.x * canvasElement.width, index.y * canvasElement.height);
                        canvasCtx.strokeStyle = "#00b4db";
                        canvasCtx.lineWidth = 4;
                        canvasCtx.stroke();

                        // Calculate distance using Pythagorean theorem
                        const distance = Math.hypot(index.x - thumb.x, index.y - thumb.y);

                        // Map distance to volume percentage (0 to 100)
                        // Adjust these values if your hands are closer/further from camera
                        const minDistance = 0.03; // Pinch closed
                        const maxDistance = 0.25; // Fingers wide open

                        let volumePercentage = ((distance - minDistance) / (maxDistance - minDistance)) * 100;

                        // Clamp value between 0 and 100
                        volumePercentage = Math.max(0, Math.min(100, volumePercentage));
                        const finalVolume = Math.round(volumePercentage);

                        // Update UI
                        volumeBar.style.width = finalVolume + "%";
                        volumeBar.innerText = finalVolume + "%";

                        // Update Icons dynamically
                        if (finalVolume === 0) {
                                volIcon.className = "fa-solid fa-volume-xmark text-danger";
                        } else if (finalVolume < 50) {
                                volIcon.className = "fa-solid fa-volume-low text-info";
                        } else {
                                volIcon.className = "fa-solid fa-volume-high text-success";
                        }

                        // Send to PHP Server
                        sendVolumeToPHP(finalVolume);

                } else {
                        // Hand lost
                        statusIndicator.classList.remove('tracking-active');
                        statusText.innerText = "No hand detected. Waiting...";
                }
                canvasCtx.restore();
        }

        // Initialize MediaPipe Hands AI
        const hands = new Hands({locateFile: (file) => {
                return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`;
        }});
        hands.setOptions({
                maxNumHands: 1, // Only track one hand for volume
                modelComplexity: 1,
                minDetectionConfidence: 0.7,
                minTrackingConfidence: 0.7
        });
        hands.onResults(onResults);

        // Start Webcam
        const camera = new Camera(videoElement, {
                onFrame: async () => {
                        await hands.send({image: videoElement});
                },
                width: 640,
                height: 480
        });

        camera.start().then(() => {
                statusText.innerText = "Camera active. Waiting for hand...";
        }).catch(err => {
                statusText.innerText = "Camera access denied or error.";
                alert("Please allow camera permissions for this to work!");
        });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>