<?php
// PHP Data Structure for IGIPESS Building
$buildingData = [
    "Ground Floor & Basement" => [
        ["no" => "1", "desc" => "Principal's Office", "icon" => "fa-user-tie"],
        ["no" => "1 A", "desc" => "Photocopy Room", "icon" => "fa-print"],
        ["no" => "2", "desc" => "Accounts Section", "icon" => "fa-calculator"],
        ["no" => "3", "desc" => "Sr. P.A. to Principal's Office", "icon" => "fa-user-cog"],
        ["no" => "4", "desc" => "Administrative Officer's Office", "icon" => "fa-briefcase"],
        ["no" => "4 A", "desc" => "Despatch Section", "icon" => "fa-envelope"],
        ["no" => "4 B", "desc" => "Bursar Room", "icon" => "fa-money-bill-wave"],
        ["no" => "5", "desc" => "Badminton Court & Yoga Lab", "icon" => "fa-running"],
        ["no" => "6", "desc" => "Library", "icon" => "fa-book-reader"],
        ["no" => "7-8", "desc" => "Computer Centre", "icon" => "fa-desktop"],
        ["no" => "8 A", "desc" => "Class Room B.Sc. Part-I Sec. A / Student Dealing / Gym", "icon" => "fa-chalkboard-teacher"],
        ["no" => "9", "desc" => "Teacher's Room (Prof. Sandeep Tiwari, etc.)", "icon" => "fa-users"],
        ["no" => "10", "desc" => "Teacher's Room (Prof. J.P. Sharma, etc.)", "icon" => "fa-users"],
        ["no" => "11", "desc" => "Multi-Utility Gym (MUG) - Basement", "icon" => "fa-dumbbell"],
        ["no" => "12", "desc" => "Teacher's Room (Prof. Ashok Kumar Singh, etc.)", "icon" => "fa-users"],
        ["no" => "13", "desc" => "Medical Centre & Physiotherapy Lab", "icon" => "fa-clinic-medical"],
        ["no" => "14", "desc" => "Main Store", "icon" => "fa-boxes"],
        ["no" => "15", "desc" => "NAAC/ IQAC/ NCC Room (Prof. Lalit Sharma, etc.)", "icon" => "fa-award"],
        ["no" => "15 A", "desc" => "Class Room B.Sc. Part I Sec. B", "icon" => "fa-chalkboard"],
        ["no" => "16", "desc" => "Biomechanics Lab", "icon" => "fa-microscope"],
        ["no" => "17", "desc" => "New Seminar Hall", "icon" => "fa-bullhorn"]
    ],
    "First Floor" => [
        ["no" => "18", "desc" => "Exercise Physiology Lab", "icon" => "fa-heartbeat"],
        ["no" => "19", "desc" => "Guest Room", "icon" => "fa-bed"],
        ["no" => "20", "desc" => "Teacher's Room (Prof. Anil Vanaik, etc.)", "icon" => "fa-users"],
        ["no" => "21", "desc" => "Staff Room", "icon" => "fa-users-cog"],
        ["no" => "22", "desc" => "Conference Room", "icon" => "fa-handshake"],
        ["no" => "23", "desc" => "Dept. of Physical Education and Sports Sciences (D.U.)", "icon" => "fa-university"],
        ["no" => "24", "desc" => "Teacher's Room (Prof. Dhananjoy Shaw, etc.)", "icon" => "fa-users"],
        ["no" => "25", "desc" => "Class Room B.Sc. Part II (A)", "icon" => "fa-chalkboard"],
        ["no" => "25 A", "desc" => "Class Room B.Sc. Part II (C)", "icon" => "fa-chalkboard"],
        ["no" => "26", "desc" => "Class Room B.Sc. Part II (B)", "icon" => "fa-chalkboard"],
        ["no" => "27", "desc" => "Class Room B.P.Ed. Part-II (B)", "icon" => "fa-chalkboard"],
        ["no" => "28", "desc" => "Class Room B.P.Ed. Part- II (A)", "icon" => "fa-chalkboard"],
        ["no" => "29", "desc" => "Class Room M.P.Ed. Part I", "icon" => "fa-chalkboard"],
        ["no" => "30", "desc" => "Class Room M.P.Ed. Part II", "icon" => "fa-chalkboard"],
        ["no" => "31", "desc" => "Class Room B.P.Ed. Part- I (A)", "icon" => "fa-chalkboard"],
        ["no" => "32", "desc" => "Class Room B.P.Ed. Part- I (B)", "icon" => "fa-chalkboard"],
        ["no" => "32 A", "desc" => "Class Room B.Sc. I Sec (C)", "icon" => "fa-chalkboard"],
        ["no" => "33", "desc" => "Anatomy & Physiology Lab", "icon" => "fa-bone"]
    ],
    "Second Floor" => [
        ["no" => "34", "desc" => "Teacher's Room (Prof. Rajbir Singh, etc.)", "icon" => "fa-users"],
        ["no" => "35", "desc" => "Teacher's Room (Prof. Sarita Tyagi, etc.)", "icon" => "fa-users"],
        ["no" => "36", "desc" => "Store (Misc. Items)", "icon" => "fa-box-open"],
        ["no" => "37", "desc" => "Class Room", "icon" => "fa-chalkboard"],
        ["no" => "38", "desc" => "Class Room B.Sc. Part-III (A)", "icon" => "fa-chalkboard"],
        ["no" => "39", "desc" => "Class Room B.Sc. Part-III (B)", "icon" => "fa-chalkboard"],
        ["no" => "40", "desc" => "Audio-Visual Lab", "icon" => "fa-video"],
        ["no" => "41", "desc" => "Audio Visual Lab", "icon" => "fa-headphones"],
        ["no" => "42", "desc" => "Class Room B.Sc. Class-III (C)", "icon" => "fa-chalkboard"],
        ["no" => "43", "desc" => "Class Room", "icon" => "fa-chalkboard"],
        ["no" => "44", "desc" => "Class Room", "icon" => "fa-chalkboard"],
        ["no" => "45", "desc" => "Behavioural Science Lab", "icon" => "fa-brain"]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IGIPESS 3D Building Layout</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-banner {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        /* 3D Card Styling */
        .room-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            height: 100%;
            border: none;
            box-shadow: 20px 20px 60px #d9d9d9, -20px -20px 60px #ffffff;
            transform-style: preserve-3d; /* Key for 3D effect inside */
            display: flex;
            align-items: center;
            border-left: 5px solid #2a5298;
        }
        .room-card-inner {
            transform: translateZ(30px); /* Pushes content out in 3D space */
            width: 100%;
        }
        .room-icon {
            font-size: 2.5rem;
            color: #2a5298;
            margin-bottom: 15px;
        }
        .room-number {
            font-weight: bold;
            font-size: 1.2rem;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        .room-desc {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.4;
        }
        .nav-pills .nav-link {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            color: #1e3c72;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active {
            background-color: #1e3c72;
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3);
        }
    </style>
</head>
<body>

    <header class="header-banner text-center">
        <div class="container">
            <h1 class="display-5 fw-bold"><i class="fas fa-building me-3"></i>IGIPESS Campus Layout</h1>
            <p class="lead">Interactive 3D Virtual Directory</p>
        </div>
    </header>

    <div class="container pb-5">
        <ul class="nav nav-pills justify-content-center mb-5" id="floorTabs" role="tablist">
            <?php
            $i = 0;
            foreach($buildingData as $floorName => $rooms):
                $active = $i === 0 ? 'active' : '';
                $id = "floor-" . $i;
            ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active ?>" id="<?= $id ?>-tab" data-bs-toggle="pill" data-bs-target="#<?= $id ?>" type="button" role="tab" aria-selected="true">
                    <?= $floorName ?>
                </button>
            </li>
            <?php
            $i++;
            endforeach;
            ?>
        </ul>

        <div class="tab-content" id="floorTabsContent">
            <?php
            $i = 0;
            foreach($buildingData as $floorName => $rooms):
                $active = $i === 0 ? 'show active' : '';
                $id = "floor-" . $i;
            ?>
            <div class="tab-pane fade <?= $active ?>" id="<?= $id ?>" role="tabpanel" tabindex="0">
                <div class="row g-4">
                    <?php foreach($rooms as $room): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <div class="room-card" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-perspective="1000">
                                <div class="room-card-inner text-center">
                                    <i class="fas <?= $room['icon'] ?> room-icon"></i>
                                    <div class="room-number">Room <?= $room['no'] ?></div>
                                    <div class="room-desc"><?= $room['desc'] ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            $i++;
            endforeach;
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.0/vanilla-tilt.min.js"></script>

    <script>
        // Optional: Re-initialize tilt when tabs are changed so the 3D effect works smoothly
        var triggerTabList = [].slice.call(document.querySelectorAll('#floorTabs button'))
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('shown.bs.tab', function (event) {
                VanillaTilt.init(document.querySelectorAll(".room-card"));
            })
        });
    </script>
</body>
</html>