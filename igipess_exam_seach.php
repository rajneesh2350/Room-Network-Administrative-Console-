<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("./log/config/conn.php");
date_default_timezone_set('Asia/Kolkata');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function eh($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$q = trim($_GET['q'] ?? '');
$examDate = trim($_GET['exam_date'] ?? '');
$project = trim($_GET['project_name'] ?? '');
$rows = [];

if ($q !== '' || $examDate !== '' || $project !== '') {
    $where = [];
    if ($q !== '') {
        $safeQ = $conn->real_escape_string($q);
        $where[] = "(b.roll_no LIKE '%{$safeQ}%' OR b.candidate_name LIKE '%{$safeQ}%' OR b.seat_label LIKE '%{$safeQ}%' OR r.room_no LIKE '%{$safeQ}%' OR b.project_name LIKE '%{$safeQ}%')";
    }
    if ($examDate !== '') {
        $safeDate = $conn->real_escape_string($examDate);
        $where[] = "b.exam_date = '{$safeDate}'";
    }
    if ($project !== '') {
        $safeProject = $conn->real_escape_string($project);
        $where[] = "b.project_name LIKE '%{$safeProject}%'";
    }

    $sql = "SELECT
                b.*,
                r.room_no,
                r.floor,
                r.description,
                r.latitude,
                r.longitude,
                r.remarks AS room_remarks
            FROM exam_seat_booking b
            LEFT JOIN igpess_network r ON r.id = b.room_id";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY b.exam_date DESC, r.room_no ASC, b.seat_label ASC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IGIPESS Exam Search</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --brand: #153a64;
            --brand2: #1f5f93;
        }
        body {
            background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #23364b;
        }
        .hero, .panel, .result-card {
            border-radius: 22px;
            border: 0;
            box-shadow: 0 18px 44px rgba(35, 54, 75, 0.08);
        }
        .hero {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand2) 100%);
            color: #fff;
            padding: 2rem;
        }
        .panel {
            background: #fff;
            padding: 1.25rem;
        }
        .result-card {
            background: #fff;
            padding: 1.1rem;
            height: 100%;
        }
        .result-title {
            color: var(--brand);
            font-weight: 800;
        }
        .meta {
            color: #62778f;
            font-size: 0.88rem;
        }
        .search-input {
            border-radius: 999px;
        }
        .badge-soft {
            background: #edf6ff;
            color: #0f5f95;
            border: 1px solid #c8e2f7;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="hero mb-4">
        <h1 class="h2 fw-bold mb-2"><i class="fas fa-magnifying-glass-location me-2"></i>IGIPESS Examination Search</h1>
        <p class="mb-0 text-white-50">Search by roll number, candidate name, seat label, room number, project / paper, or exam date to find room and exam details quickly.</p>
    </div>

    <div class="panel mb-4">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control search-input" name="q" value="<?= eh($q) ?>" placeholder="Roll No., Candidate Name, Seat Label, Room No...">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Exam Date</label>
                    <input type="date" class="form-control" name="exam_date" value="<?= eh($examDate) ?>">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Project / Paper</label>
                    <input type="text" class="form-control" name="project_name" value="<?= eh($project) ?>" placeholder="Paper name">
                </div>
                <div class="col-lg-1 d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($q !== '' || $examDate !== '' || $project !== ''): ?>
        <div class="panel mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="result-title">Search Results</div>
                <span class="badge text-bg-light"><?= count($rows) ?> match<?= count($rows) === 1 ? '' : 'es' ?></span>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($rows)): ?>
                <div class="col-12">
                    <div class="panel text-center text-muted py-4">No candidate or exam-seat entry matched your search.</div>
                </div>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
                <?php
                $mapLink = '';
                if (!empty($row['latitude']) && !empty($row['longitude'])) {
                    $mapLink = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($row['latitude'] . ',' . $row['longitude']);
                }
                ?>
                <div class="col-lg-6">
                    <div class="result-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <div class="result-title"><?= eh($row['candidate_name'] ?: 'Candidate') ?></div>
                                <div class="meta">Roll No.: <strong><?= eh($row['roll_no']) ?></strong></div>
                            </div>
                            <span class="badge badge-soft rounded-pill px-3 py-2"><?= eh($row['booking_status'] ?: 'Reserved') ?></span>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-6"><div class="meta">Paper: <strong><?= eh($row['project_name']) ?></strong></div></div>
                            <div class="col-md-6"><div class="meta">Exam Date: <strong><?= eh($row['exam_date']) ?></strong></div></div>
                            <div class="col-md-6"><div class="meta">Seat Label: <strong><?= eh($row['seat_label']) ?></strong></div></div>
                            <div class="col-md-6"><div class="meta">Time / Shift: <strong><?= eh(trim(($row['exam_time'] ?? '') . ' ' . ($row['exam_ampm'] ?? '') . ' ' . ($row['exam_shift'] ?? ''))) ?: '-' ?></strong></div></div>
                            <div class="col-md-6"><div class="meta">Room No.: <strong><?= eh($row['room_no']) ?></strong></div></div>
                            <div class="col-md-6"><div class="meta">Floor: <strong><?= eh($row['floor']) ?></strong></div></div>
                        </div>

                        <div class="mt-3">
                            <div class="meta">Room Details</div>
                            <div class="fw-semibold"><?= eh($row['description']) ?></div>
                        </div>

                        <?php if (!empty($row['session_remarks'])): ?>
                            <div class="mt-3">
                                <div class="meta">Exam Remarks</div>
                                <div><?= eh($row['session_remarks']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($row['room_remarks'])): ?>
                            <div class="mt-3">
                                <div class="meta">Room Remarks</div>
                                <div><?= eh($row['room_remarks']) ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <?php if ($mapLink !== ''): ?>
                                <a href="<?= eh($mapLink) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill"><i class="fas fa-location-dot me-1"></i>Open Room Location</a>
                            <?php endif; ?>
                            <a href="Room_Network1.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-door-open me-1"></i>Open Room Directory</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
