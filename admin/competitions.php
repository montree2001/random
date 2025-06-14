<?php
// admin/competitions.php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// ฟังก์ชันช่วยในการ format ตัวเลข (แก้ปัญหา PHP 8.3)
function safe_number_format($number, $decimals = 0, $decimal_separator = '.', $thousands_separator = ',') {
    if ($number === null || $number === '') {
        return '0';
    }
    return number_format((float)$number, $decimals, $decimal_separator, $thousands_separator);
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// เพิ่มการแข่งขัน
if (isset($_POST['add_competition'])) {
    $competition_name = trim($_POST['competition_name']);
    $sport_type = trim($_POST['sport_type']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    $max_teams = !empty($_POST['max_teams']) ? (int)$_POST['max_teams'] : null;
    
    if (!empty($competition_name) && !empty($sport_type) && !empty($start_date) && !empty($end_date)) {
        try {
            $query = "INSERT INTO sport_competitions 
                     (competition_name, sport_type, description, start_date, end_date, location, max_teams, academic_year_id, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $competition_name, 
                $sport_type, 
                $description, 
                $start_date, 
                $end_date, 
                $location, 
                $max_teams, 
                $current_year['academic_year_id'], 
                $_SESSION['admin_id']
            ]);
            
            $message = 'เพิ่มการแข่งขันเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// แก้ไขการแข่งขัน
if (isset($_POST['update_competition'])) {
    $competition_id = $_POST['competition_id'];
    $competition_name = trim($_POST['competition_name']);
    $sport_type = trim($_POST['sport_type']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    $max_teams = !empty($_POST['max_teams']) ? (int)$_POST['max_teams'] : null;
    $status = $_POST['status'];
    
    if (!empty($competition_id) && !empty($competition_name) && !empty($sport_type)) {
        try {
            $query = "UPDATE sport_competitions 
                     SET competition_name = ?, sport_type = ?, description = ?, start_date = ?, end_date = ?, 
                         location = ?, max_teams = ?, status = ?
                     WHERE competition_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $competition_name, 
                $sport_type, 
                $description, 
                $start_date, 
                $end_date, 
                $location, 
                $max_teams, 
                $status,
                $competition_id
            ]);
            
            $message = 'แก้ไขการแข่งขันเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// ลบการแข่งขัน
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $competition_id = (int)$_GET['delete'];
    
    try {
        $db->beginTransaction();
        
        // ตรวจสอบว่ามีตารางแข่งขันหรือไม่
        $query = "SELECT COUNT(*) as count FROM sport_schedules WHERE competition_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$competition_id]);
        $schedule_count = $stmt->fetch();
        
        if ($schedule_count['count'] > 0) {
            throw new Exception('ไม่สามารถลบการแข่งขันนี้ได้ เนื่องจากมีตารางแข่งขันแล้ว');
        }
        
        // ลบการแข่งขัน
        $query = "DELETE FROM sport_competitions WHERE competition_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$competition_id]);
        
        $db->commit();
        $message = 'ลบการแข่งขันเรียบร้อยแล้ว';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $db->rollback();
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// ค้นหาและกรอง
$search = $_GET['search'] ?? '';
$sport_filter = $_GET['sport_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$month_filter = $_GET['month_filter'] ?? '';

// ดึงข้อมูลการแข่งขัน
$where_conditions = ["sc.academic_year_id = ?"];
$params = [$current_year['academic_year_id']];

if (!empty($search)) {
    $where_conditions[] = "(sc.competition_name LIKE ? OR sc.sport_type LIKE ? OR sc.location LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($sport_filter)) {
    $where_conditions[] = "sc.sport_type = ?";
    $params[] = $sport_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sc.status = ?";
    $params[] = $status_filter;
}

if (!empty($month_filter)) {
    $where_conditions[] = "DATE_FORMAT(sc.start_date, '%Y-%m') = ?";
    $params[] = $month_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT sc.*, 
                 COUNT(ss.schedule_id) as schedule_count,
                 CONCAT(COALESCE(admin.title, ''), admin.first_name, ' ', admin.last_name) as created_by_name
          FROM sport_competitions sc
          LEFT JOIN sport_schedules ss ON sc.competition_id = ss.competition_id
          LEFT JOIN admin_users admin ON sc.created_by = admin.admin_id
          WHERE $where_clause
          GROUP BY sc.competition_id
          ORDER BY sc.start_date DESC, sc.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$competitions = $stmt->fetchAll();

// ดึงประเภทกีฬาจาก sport_athletes
$query = "SELECT DISTINCT sport_type FROM sport_athletes 
          WHERE academic_year_id = ? 
          ORDER BY sport_type";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$sports_from_athletes = $stmt->fetchAll();

// ดึงประเภทกีฬาจาก competitions
$query = "SELECT DISTINCT sport_type FROM sport_competitions 
          WHERE academic_year_id = ? 
          ORDER BY sport_type";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$sports_from_competitions = $stmt->fetchAll();

// รวมประเภทกีฬา
$all_sports = array_unique(array_merge(
    array_column($sports_from_athletes, 'sport_type'),
    array_column($sports_from_competitions, 'sport_type')
));
sort($all_sports);

// สถิติ
$query = "SELECT 
            COUNT(*) as total_competitions,
            COUNT(CASE WHEN status = 'กำลังรับสมัคร' THEN 1 END) as registration_count,
            COUNT(CASE WHEN status = 'กำลังแข่งขัน' THEN 1 END) as ongoing_count,
            COUNT(CASE WHEN status = 'จบแล้ว' THEN 1 END) as finished_count,
            COUNT(CASE WHEN status = 'ยกเลิก' THEN 1 END) as cancelled_count
          FROM sport_competitions 
          WHERE academic_year_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$stats = $stmt->fetch();

// สถิติตามประเภทกีฬา
$query = "SELECT sport_type, 
                 COUNT(*) as competition_count,
                 COUNT(CASE WHEN status = 'จบแล้ว' THEN 1 END) as finished_count
          FROM sport_competitions 
          WHERE academic_year_id = ?
          GROUP BY sport_type
          ORDER BY competition_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$sport_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการแข่งขัน - ระบบกีฬาสี</title>
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
        .competition-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .competition-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .sport-stat-card {
            border-radius: 10px;
            transition: all 0.3s;
        }
        .sport-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
         <?php include 'menu.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-trophy"></i> จัดการการแข่งขัน</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitionModal">
                        <i class="fas fa-plus"></i> เพิ่มการแข่งขัน
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- สถิติรวม -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center bg-primary text-white">
                            <div class="card-body">
                                <i class="fas fa-trophy fa-2x mb-2"></i>
                                <h5>การแข่งขันทั้งหมด</h5>
                                <h3><?php echo safe_number_format($stats['total_competitions']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                                <h5>กำลังรับสมัคร</h5>
                                <h3><?php echo safe_number_format($stats['registration_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-warning text-dark">
                            <div class="card-body">
                                <i class="fas fa-play-circle fa-2x mb-2"></i>
                                <h5>กำลังแข่งขัน</h5>
                                <h3><?php echo safe_number_format($stats['ongoing_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h5>จบแล้ว</h5>
                                <h3><?php echo safe_number_format($stats['finished_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-secondary text-white">
                            <div class="card-body">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h5>ยกเลิก</h5>
                                <h3><?php echo safe_number_format($stats['cancelled_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-dark text-white">
                            <div class="card-body">
                                <i class="fas fa-futbol fa-2x mb-2"></i>
                                <h5>ประเภทกีฬา</h5>
                                <h3><?php echo count($all_sports); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- สถิติตามประเภทกีฬา -->
                <?php if (!empty($sport_stats)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> สถิติตามประเภทกีฬา</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($sport_stats as $stat): 
                                        $colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'];
                                        $color = $colors[array_search($stat['sport_type'], array_column($sport_stats, 'sport_type')) % count($colors)];
                                    ?>
                                        <div class="col-md-3 mb-3">
                                            <div class="card sport-stat-card h-100" style="border-left: 4px solid <?php echo $color; ?>">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-primary">
                                                        <i class="fas fa-trophy"></i> <?php echo $stat['sport_type']; ?>
                                                    </h6>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <strong><?php echo $stat['competition_count']; ?></strong>
                                                            <br><small>การแข่งขัน</small>
                                                        </div>
                                                        <div class="col-6">
                                                            <strong><?php echo $stat['finished_count']; ?></strong>
                                                            <br><small>จบแล้ว</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ฟิลเตอร์ -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="ชื่อการแข่งขัน/กีฬา/สถานที่" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ประเภทกีฬา</label>
                                <select class="form-select" name="sport_filter">
                                    <option value="">ทุกประเภท</option>
                                    <?php foreach ($all_sports as $sport): ?>
                                        <option value="<?php echo $sport; ?>" 
                                                <?php echo $sport_filter === $sport ? 'selected' : ''; ?>>
                                            <?php echo $sport; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">สถานะ</label>
                                <select class="form-select" name="status_filter">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="กำลังรับสมัคร" <?php echo $status_filter === 'กำลังรับสมัคร' ? 'selected' : ''; ?>>กำลังรับสมัคร</option>
                                    <option value="กำลังแข่งขัน" <?php echo $status_filter === 'กำลังแข่งขัน' ? 'selected' : ''; ?>>กำลังแข่งขัน</option>
                                    <option value="จบแล้ว" <?php echo $status_filter === 'จบแล้ว' ? 'selected' : ''; ?>>จบแล้ว</option>
                                    <option value="ยกเลิก" <?php echo $status_filter === 'ยกเลิก' ? 'selected' : ''; ?>>ยกเลิก</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">เดือน</label>
                                <input type="month" class="form-control" name="month_filter" value="<?php echo $month_filter; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                                <a href="competitions.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-refresh"></i> ล้าง
                                </a>
                                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- รายการการแข่งขัน -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> รายการการแข่งขัน (<?php echo count($competitions); ?> รายการ)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="competitionsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ชื่อการแข่งขัน</th>
                                        <th>ประเภทกีฬา</th>
                                        <th>วันที่แข่งขัน</th>
                                        <th>สถานที่</th>
                                        <th>จำนวนทีมสูงสุด</th>
                                        <th>ตารางแข่ง</th>
                                        <th>สถานะ</th>
                                        <th>ผู้สร้าง</th>
                                        <th width="120">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($competitions as $competition): ?>
                                        <tr class="competition-card" 
                                            style="border-left-color: <?php 
                                                echo $competition['status'] === 'กำลังรับสมัคร' ? '#17a2b8' : 
                                                    ($competition['status'] === 'กำลังแข่งขัน' ? '#ffc107' : 
                                                    ($competition['status'] === 'จบแล้ว' ? '#28a745' : '#6c757d')); 
                                            ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($competition['competition_name']); ?></strong>
                                                <?php if ($competition['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($competition['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $competition['sport_type']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($competition['start_date'])); ?></strong>
                                                <?php if ($competition['start_date'] !== $competition['end_date']): ?>
                                                    <br><small>ถึง <?php echo date('d/m/Y', strtotime($competition['end_date'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $competition['location'] ?: '-'; ?></td>
                                            <td>
                                                <?php echo $competition['max_teams'] ? safe_number_format($competition['max_teams']) . ' ทีม' : 'ไม่จำกัด'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo safe_number_format($competition['schedule_count']); ?> นัด</span>
                                                <?php if ($competition['schedule_count'] > 0): ?>
                                                    <br><a href="schedules.php?competition_id=<?php echo $competition['competition_id']; ?>" 
                                                           class="btn btn-sm btn-outline-info mt-1">
                                                        <i class="fas fa-calendar"></i> ดูตาราง
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-badge <?php 
                                                    echo $competition['status'] === 'กำลังรับสมัคร' ? 'bg-info' : 
                                                        ($competition['status'] === 'กำลังแข่งขัน' ? 'bg-warning text-dark' : 
                                                        ($competition['status'] === 'จบแล้ว' ? 'bg-success' : 'bg-secondary')); 
                                                ?>">
                                                    <?php echo $competition['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo $competition['created_by_name'] ?? 'ไม่ระบุ'; ?></small>
                                                <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($competition['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editCompetition(<?php echo htmlspecialchars(json_encode($competition)); ?>)"
                                                            title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="schedules.php?competition_id=<?php echo $competition['competition_id']; ?>" 
                                                       class="btn btn-sm btn-outline-info"
                                                       title="จัดการตารางแข่ง">
                                                        <i class="fas fa-calendar"></i>
                                                    </a>
                                                    <?php if ($competition['schedule_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $competition['competition_id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('ต้องการลบการแข่งขันนี้?')"
                                                           title="ลบ">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($competitions)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-trophy fa-3x text-muted"></i>
                                                <p class="mt-2 text-muted">ยังไม่มีข้อมูลการแข่งขัน</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Competition Modal -->
    <div class="modal fade" id="addCompetitionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มการแข่งขัน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="competitionForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ชื่อการแข่งขัน <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="competition_name" 
                                           placeholder="เช่น การแข่งขันฟุตบอลกีฬาสี" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ประเภทกีฬา <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="sport_type" 
                                           placeholder="เช่น ฟุตบอล, วอลเลย์บอล" 
                                           list="sportsList" required>
                                    <datalist id="sportsList">
                                        <?php foreach ($all_sports as $sport): ?>
                                            <option value="<?php echo $sport; ?>">
                                        <?php endforeach; ?>
                                        <option value="ฟุตบอล">
                                        <option value="วอลเลย์บอล">
                                        <option value="บาสเกตบอล">
                                        <option value="แบดมินตัน">
                                        <option value="เซปักตะกร้อ">
                                        <option value="ปิงปอง">
                                        <option value="กรีฑา">
                                        <option value="ว่ายน้ำ">
                                    </datalist>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">วันที่เริ่มแข่งขัน <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">สถานที่แข่งขัน</label>
                                    <input type="text" class="form-control" name="location" 
                                           placeholder="เช่น สนามฟุตบอลหลัง, โรงยิม">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">จำนวนทีมสูงสุด</label>
                                    <input type="number" class="form-control" name="max_teams" min="2" 
                                           placeholder="เว้นว่างหากไม่จำกัด">
                                    <div class="form-text">ระบุจำนวนทีมสูงสุดที่สามารถเข้าร่วมได้</div>
                                </div>
                                
                                <div class="mb-3" id="statusGroup" style="display: none;">
                                    <label class="form-label">สถานะ</label>
                                    <select class="form-select" name="status">
                                        <option value="กำลังรับสมัคร">กำลังรับสมัคร</option>
                                        <option value="กำลังแข่งขัน">กำลังแข่งขัน</option>
                                        <option value="จบแล้ว">จบแล้ว</option>
                                        <option value="ยกเลิก">ยกเลิก</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">รายละเอียด</label>
                                    <textarea class="form-control" name="description" rows="3" 
                                              placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับการแข่งขัน"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- แสดงข้อมูลสรุป -->
                        <div id="competitionSummary" class="alert alert-info d-none">
                            <h6><i class="fas fa-info-circle"></i> สรุปข้อมูลการแข่งขัน</h6>
                            <div id="summaryContent"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_competition" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // อัปเดตสรุปข้อมูลการแข่งขัน
        function updateCompetitionSummary() {
            const competitionName = document.querySelector('input[name="competition_name"]').value;
            const sportType = document.querySelector('input[name="sport_type"]').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const location = document.querySelector('input[name="location"]').value;
            const maxTeams = document.querySelector('input[name="max_teams"]').value;
            
            if (competitionName && sportType && startDate && endDate) {
                const summary = document.getElementById('competitionSummary');
                const content = document.getElementById('summaryContent');
                
                const startDateFormatted = new Date(startDate).toLocaleDateString('th-TH');
                const endDateFormatted = new Date(endDate).toLocaleDateString('th-TH');
                const dateRange = startDate === endDate ? startDateFormatted : `${startDateFormatted} - ${endDateFormatted}`;
                
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>การแข่งขัน:</strong> ${competitionName}<br>
                            <strong>ประเภทกีฬา:</strong> ${sportType}
                        </div>
                        <div class="col-md-6">
                            <strong>วันที่แข่งขัน:</strong> ${dateRange}<br>
                            <strong>สถานที่:</strong> ${location || 'ไม่ระบุ'}<br>
                            <strong>จำนวนทีมสูงสุด:</strong> ${maxTeams || 'ไม่จำกัด'}
                        </div>
                    </div>
                `;
                
                summary.classList.remove('d-none');
            } else {
                document.getElementById('competitionSummary').classList.add('d-none');
            }
        }
        
        // แก้ไขการแข่งขัน
        function editCompetition(competition) {
            const modal = new bootstrap.Modal(document.getElementById('addCompetitionModal'));
            const form = document.getElementById('competitionForm');
            
            // เปลี่ยนหัวข้อโมดอล
            document.querySelector('#addCompetitionModal .modal-title').innerHTML = 
                '<i class="fas fa-edit"></i> แก้ไขการแข่งขัน';
            
            // ตั้งค่าข้อมูลในฟอร์ม
            form.querySelector('input[name="competition_name"]').value = competition.competition_name;
            form.querySelector('input[name="sport_type"]').value = competition.sport_type;
            form.querySelector('input[name="start_date"]').value = competition.start_date;
            form.querySelector('input[name="end_date"]').value = competition.end_date;
            form.querySelector('input[name="location"]').value = competition.location || '';
            form.querySelector('input[name="max_teams"]').value = competition.max_teams || '';
            form.querySelector('textarea[name="description"]').value = competition.description || '';
            form.querySelector('select[name="status"]').value = competition.status;
            
            // แสดงกลุ่มสถานะ
            document.getElementById('statusGroup').style.display = 'block';
            
            // เพิ่ม hidden input สำหรับ competition_id
            let competitionIdInput = form.querySelector('input[name="competition_id"]');
            if (!competitionIdInput) {
                competitionIdInput = document.createElement('input');
                competitionIdInput.type = 'hidden';
                competitionIdInput.name = 'competition_id';
                form.appendChild(competitionIdInput);
            }
            competitionIdInput.value = competition.competition_id;
            
            // เปลี่ยนปุ่มส่ง
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> อัปเดต';
            submitBtn.setAttribute('name', 'update_competition');
            
            updateCompetitionSummary();
            modal.show();
        }
        
        // ส่งออก Excel
        function exportToExcel() {
            const table = document.getElementById('competitionsTable');
            let csv = [];
            
            // หัวตาราง
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push('"' + th.textContent.trim() + '"');
            });
            csv.push(headers.join(','));
            
            // ข้อมูล
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach((td, index) => {
                    if (index < 8) { // ไม่รวมคอลัมน์จัดการ
                        row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
                    }
                });
                if (row.length > 0) {
                    csv.push(row.join(','));
                }
            });
            
            // ดาวน์โหลดไฟล์
            const csvContent = "\uFEFF" + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'competitions_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // อัปเดตสรุปเมื่อเปลี่ยนข้อมูล
            document.querySelector('input[name="competition_name"]').addEventListener('input', updateCompetitionSummary);
            document.querySelector('input[name="sport_type"]').addEventListener('input', updateCompetitionSummary);
            document.querySelector('input[name="start_date"]').addEventListener('change', updateCompetitionSummary);
            document.querySelector('input[name="end_date"]').addEventListener('change', updateCompetitionSummary);
            document.querySelector('input[name="location"]').addEventListener('input', updateCompetitionSummary);
            document.querySelector('input[name="max_teams"]').addEventListener('input', updateCompetitionSummary);
            
            // ตั้งค่าวันที่เริ่มต้นเป็นวันปัจจุบัน
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="start_date"]').value = today;
            document.querySelector('input[name="end_date"]').value = today;
            
            // อัปเดตวันที่สิ้นสุดเมื่อเปลี่ยนวันที่เริ่มต้น
            document.querySelector('input[name="start_date"]').addEventListener('change', function() {
                const startDate = this.value;
                const endDateInput = document.querySelector('input[name="end_date"]');
                if (endDateInput.value < startDate) {
                    endDateInput.value = startDate;
                }
                endDateInput.min = startDate;
            });
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            document.getElementById('addCompetitionModal').addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('competitionForm');
                form.reset();
                
                // รีเซ็ตหัวข้อและปุ่ม
                document.querySelector('#addCompetitionModal .modal-title').innerHTML = 
                    '<i class="fas fa-plus"></i> เพิ่มการแข่งขัน';
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึก';
                submitBtn.setAttribute('name', 'add_competition');
                
                // ซ่อนกลุ่มสถานะ
                document.getElementById('statusGroup').style.display = 'none';
                
                // ลบ competition_id ถ้ามี
                const competitionIdInput = form.querySelector('input[name="competition_id"]');
                if (competitionIdInput) {
                    competitionIdInput.remove();
                }
                
                // ซ่อนข้อมูลสรุป
                document.getElementById('competitionSummary').classList.add('d-none');
                
                // ตั้งค่าวันที่เริ่มต้น
                const today = new Date().toISOString().split('T')[0];
                document.querySelector('input[name="start_date"]').value = today;
                document.querySelector('input[name="end_date"]').value = today;
            });
        });
    </script>
</body>
</html>