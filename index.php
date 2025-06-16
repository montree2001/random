<?php
// index.php - หน้าแรกระบบจัดการสีและกีฬาสี
require_once 'config/database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

// ตรวจสอบปีการศึกษาปัจจุบัน
$current_academic_year_query = "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1";
$current_academic_year_stmt = $db->prepare($current_academic_year_query);
$current_academic_year_stmt->execute();
$current_academic_year = $current_academic_year_stmt->fetch();

// ดึงข้อมูลสถิติสี
$color_stats_query = "
    SELECT 
        c.color_id,
        c.color_name,
        c.color_code,
        COUNT(sc.student_id) as student_count,
        COUNT(CASE WHEN s.title = 'นาย' THEN 1 END) as male_count,
        COUNT(CASE WHEN s.title = 'นางสาว' THEN 1 END) as female_count
    FROM colors c
    LEFT JOIN student_colors sc ON c.color_id = sc.color_id 
        AND sc.academic_year_id = ?
    LEFT JOIN students s ON sc.student_id = s.student_id
    WHERE c.is_active = 1
    GROUP BY c.color_id, c.color_name, c.color_code
    ORDER BY c.color_name
";
$color_stats_stmt = $db->prepare($color_stats_query);
$color_stats_stmt->execute([$current_academic_year['academic_year_id']]);
$color_stats = $color_stats_stmt->fetchAll();

// ดึงข้อมูลการแข่งขันที่กำลังจะมาถึง
$upcoming_competitions_query = "
    SELECT 
        c.*,
        cc.category_name,
        cc.category_type,
        COUNT(cr.registration_id) as registered_count
    FROM competitions c
    JOIN competition_categories cc ON c.category_id = cc.category_id
    LEFT JOIN competition_registrations cr ON c.competition_id = cr.competition_id 
        AND cr.status = 'confirmed'
    WHERE c.status IN ('upcoming', 'registration_open')
        AND c.competition_date >= CURDATE()
    GROUP BY c.competition_id
    ORDER BY c.competition_date ASC, c.competition_time ASC
    LIMIT 5
";
$upcoming_competitions_stmt = $db->prepare($upcoming_competitions_query);
$upcoming_competitions_stmt->execute();
$upcoming_competitions = $upcoming_competitions_stmt->fetchAll();

// ดึงข้อมูลสลิปที่รออนุมัติ
$pending_slips_query = "
    SELECT COUNT(*) as pending_count 
    FROM payment_slips 
    WHERE status = 'pending'
";
$pending_slips_stmt = $db->prepare($pending_slips_query);
$pending_slips_stmt->execute();
$pending_slips = $pending_slips_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสีและกีฬาสี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .color-card {
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .color-card:hover {
            transform: translateY(-2px);
        }
        .dashboard-card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 25px;
            margin: 0 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-palette me-2"></i>ระบบจัดการสีและกีฬาสี
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">หน้าแรก</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">จัดการสี</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="manage_colors.php">จัดการข้อมูลสี</a></li>
                            <li><a class="dropdown-item" href="assign_colors.php">จัดสีนักเรียน</a></li>
                            <li><a class="dropdown-item" href="random_colors.php">สุ่มสี</a></li>
                            <li><a class="dropdown-item" href="transfer_colors.php">โยกย้ายสี</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">กีฬา</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="sport_categories.php">จัดการประเภทกีฬา</a></li>
                            <li><a class="dropdown-item" href="competitions.php">การแข่งขัน</a></li>
                            <li><a class="dropdown-item" href="athletes.php">นักกีฬา</a></li>
                            <li><a class="dropdown-item" href="match_schedule.php">ตารางแข่งขัน</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">การเงิน</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="payment_upload.php">อัพโหลดสลิป</a></li>
                            <li><a class="dropdown-item" href="verify_slips.php">ตรวจสอบสลิป</a></li>
                            <li><a class="dropdown-item" href="payment_reports.php">รายงานการชำระ</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="student_portal.php">
                            <i class="fas fa-user"></i> พอร์ทัลนักเรียน
                        </a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_login.php">
                            <i class="fas fa-cog"></i> ผู้ดูแลระบบ
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด</h2>
                        <p class="text-muted">ภาพรวมระบบจัดการสีและกีฬาสี</p>
                    </div>
                    <?php if($current_academic_year): ?>
                    <div class="text-end">
                        <h5 class="mb-0">ปีการศึกษา <?php echo $current_academic_year['year']; ?></h5>
                        <small class="text-muted">ภาคเรียนที่ <?php echo $current_academic_year['semester']; ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card text-white bg-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title">สีทั้งหมด</h5>
                                <h2 class="mb-0"><?php echo count($color_stats); ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-palette stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card text-white bg-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title">นักเรียนจัดสีแล้ว</h5>
                                <h2 class="mb-0"><?php echo array_sum(array_column($color_stats, 'student_count')); ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card text-white bg-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title">การแข่งขันใกล้เข้า</h5>
                                <h2 class="mb-0"><?php echo count($upcoming_competitions); ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-medal stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card text-white bg-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title">สลิปรออนุมัติ</h5>
                                <h2 class="mb-0"><?php echo $pending_slips['pending_count']; ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Color Statistics -->
            <div class="col-lg-8 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>สถิติการจัดสี</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($color_stats)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">ยังไม่มีการจัดสี</h5>
                            <p class="text-muted">เริ่มต้นโดยการสร้างสีและจัดสีให้กับนักเรียน</p>
                            <a href="manage_colors.php" class="btn btn-primary">จัดการสี</a>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach($color_stats as $color): ?>
                            <div class="col-md-6 mb-3">
                                <div class="color-card card" style="border-left-color: <?php echo $color['color_code']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($color['color_name']); ?></h6>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge rounded-pill me-2" style="background-color: <?php echo $color['color_code']; ?>; color: white;">
                                                        <?php echo $color['color_code']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <h4 class="mb-0"><?php echo $color['student_count']; ?></h4>
                                                <small class="text-muted">คน</small>
                                                <div class="mt-1">
                                                    <small class="text-primary">
                                                        <i class="fas fa-mars"></i> <?php echo $color['male_count']; ?>
                                                    </small>
                                                    <small class="text-danger ms-2">
                                                        <i class="fas fa-venus"></i> <?php echo $color['female_count']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Competitions -->
            <div class="col-lg-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>การแข่งขันใกล้เข้า</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($upcoming_competitions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-3">ยังไม่มีการแข่งขันที่กำหนด</p>
                            <a href="competitions.php" class="btn btn-outline-primary btn-sm">สร้างการแข่งขัน</a>
                        </div>
                        <?php else: ?>
                        <?php foreach($upcoming_competitions as $competition): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($competition['competition_name']); ?></h6>
                            <p class="text-muted mb-1 small">
                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($competition['category_name']); ?>
                                <span class="badge bg-secondary ms-2"><?php echo $competition['category_type'] === 'individual' ? 'เดี่ยว' : 'ทีม'; ?></span>
                            </p>
                            <p class="text-muted mb-1 small">
                                <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($competition['competition_date'])); ?>
                                <i class="fas fa-clock ms-2 me-1"></i><?php echo date('H:i', strtotime($competition['competition_time'])); ?>
                            </p>
                            <p class="text-muted mb-0 small">
                                <i class="fas fa-users me-1"></i>ลงทะเบียนแล้ว <?php echo $competition['registered_count']; ?> คน
                            </p>
                        </div>
                        <?php endforeach; ?>
                        <div class="text-center">
                            <a href="competitions.php" class="btn btn-outline-primary btn-sm">ดูทั้งหมด</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>เมนูด่วน</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="random_colors.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center p-3">
                                    <i class="fas fa-random fa-2x mb-2"></i>
                                    <span>สุ่มสี</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="assign_colors.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column justify-content-center align-items-center p-3">
                                    <i class="fas fa-user-edit fa-2x mb-2"></i>
                                    <span>จัดสีรายบุคคล</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="athletes.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center align-items-center p-3">
                                    <i class="fas fa-medal fa-2x mb-2"></i>
                                    <span>ลงทะเบียนนักกีฬา</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="payment_upload.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column justify-content-center align-items-center p-3">
                                    <i class="fas fa-receipt fa-2x mb-2"></i>
                                    <span>อัพโหลดสลิป</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>