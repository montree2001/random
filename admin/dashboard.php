<?php
// admin/dashboard.php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// สถิติรวม
$stats = [];

// จำนวนนักเรียนทั้งหมด
$query = "SELECT COUNT(*) as total FROM students WHERE status = 'กำลังศึกษา'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_students'] = $stmt->fetch()['total'];

// จำนวนสี
$query = "SELECT COUNT(*) as total FROM sport_colors WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_colors'] = $stmt->fetch()['total'];

// จำนวนนักเรียนที่จัดสีแล้ว
$query = "SELECT COUNT(*) as total FROM student_sport_colors ssc 
          JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id 
          WHERE ay.is_active = 1 AND ssc.is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['assigned_students'] = $stmt->fetch()['total'];

// จำนวนการแข่งขัน
$query = "SELECT COUNT(*) as total FROM sport_competitions sc 
          JOIN academic_years ay ON sc.academic_year_id = ay.academic_year_id 
          WHERE ay.is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_competitions'] = $stmt->fetch()['total'];

// สถิติตามสี
$query = "SELECT sc.color_name, sc.color_code, COUNT(ssc.student_id) as student_count
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
          LEFT JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id
          WHERE sc.is_active = 1 AND (ay.is_active = 1 OR ay.is_active IS NULL)
          GROUP BY sc.color_id, sc.color_name, sc.color_code
          ORDER BY student_count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$color_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการกีฬาสี - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .color-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'menu.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard</h2>
                    <div class="text-muted">
                        <i class="fas fa-calendar"></i> 
                        <?php echo date('d/m/Y H:i:s'); ?>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <h3 class="text-primary"><?php echo number_format($stats['total_students']); ?></h3>
                            <p class="mb-0">นักเรียนทั้งหมด</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stat-card text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-palette fa-2x"></i>
                            </div>
                            <h3 class="text-success"><?php echo number_format($stats['total_colors']); ?></h3>
                            <p class="mb-0">จำนวนสี</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stat-card text-center">
                            <div class="text-info mb-2">
                                <i class="fas fa-user-check fa-2x"></i>
                            </div>
                            <h3 class="text-info"><?php echo number_format($stats['assigned_students']); ?></h3>
                            <p class="mb-0">จัดสีแล้ว</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stat-card text-center">
                            <div class="text-warning mb-2">
                                <i class="fas fa-trophy fa-2x"></i>
                            </div>
                            <h3 class="text-warning"><?php echo number_format($stats['total_competitions']); ?></h3>
                            <p class="mb-0">การแข่งขัน</p>
                        </div>
                    </div>
                </div>
                
                <!-- Color Distribution -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie"></i> การกระจายตัวตามสี</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($color_stats as $color): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="color-badge" style="background-color: <?php echo $color['color_code']; ?>"></span>
                                        <div class="flex-grow-1">
                                            <strong><?php echo $color['color_name']; ?></strong>
                                            <div class="progress mt-1" style="height: 20px;">
                                                <div class="progress-bar" 
                                                     style="width: <?php echo $stats['total_students'] > 0 ? ($color['student_count'] / $stats['total_students']) * 100 : 0; ?>%; background-color: <?php echo $color['color_code']; ?>">
                                                    <?php echo $color['student_count']; ?> คน
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tasks"></i> เมนูด่วน</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="random_colors.php" class="btn btn-primary">
                                        <i class="fas fa-random"></i> สุ่มสีใหม่
                                    </a>
                                    <a href="assign_colors.php" class="btn btn-success">
                                        <i class="fas fa-user-plus"></i> จัดสีรายบุคคล
                                    </a>
                                    <a href="competitions.php" class="btn btn-warning">
                                        <i class="fas fa-plus"></i> เพิ่มการแข่งขัน
                                    </a>
                                    <a href="../student_check.php" class="btn btn-info" target="_blank">
                                        <i class="fas fa-search"></i> หน้าตรวจสอบนักเรียน
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>