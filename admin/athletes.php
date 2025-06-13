
<?php
// admin/athletes.php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// เพิ่มนักกีฬา
if (isset($_POST['add_athlete'])) {
    $student_id = $_POST['student_id'];
    $sport_type = trim($_POST['sport_type']);
    $position = trim($_POST['position']);
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;
    $notes = trim($_POST['notes']);
    
    if (!empty($student_id) && !empty($sport_type)) {
        try {
            $db->beginTransaction();
            
            // ตรวจสอบว่านักเรียนลงทะเบียนกีฬานี้แล้วหรือไม่
            $query = "SELECT COUNT(*) as count FROM sport_athletes 
                     WHERE student_id = ? AND sport_type = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $sport_type, $current_year['academic_year_id']]);
            $existing = $stmt->fetch();
            
            if ($existing['count'] > 0) {
                throw new Exception('นักเรียนคนนี้ได้ลงทะเบียนกีฬาประเภทนี้แล้ว');
            }
            
            // ถ้ากำหนดเป็นหัวหน้าทีม ต้องยกเลิกหัวหน้าทีมคนเดิม
            if ($is_captain) {
                // ดึงข้อมูลสีของนักเรียน
                $query = "SELECT color_id FROM student_sport_colors 
                         WHERE student_id = ? AND academic_year_id = ? AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$student_id, $current_year['academic_year_id']]);
                $student_color = $stmt->fetch();
                
                if ($student_color) {
                    // ยกเลิกหัวหน้าทีมคนเดิมในสีและกีฬาเดียวกัน
                    $query = "UPDATE sport_athletes sa
                             JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id
                             SET sa.is_captain = 0
                             WHERE sa.sport_type = ? 
                             AND sa.academic_year_id = ?
                             AND ssc.color_id = ?
                             AND ssc.academic_year_id = ?
                             AND ssc.is_active = 1
                             AND sa.is_captain = 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $sport_type, 
                        $current_year['academic_year_id'], 
                        $student_color['color_id'], 
                        $current_year['academic_year_id']
                    ]);
                }
            }
            
            // เพิ่มนักกีฬาใหม่
            $query = "INSERT INTO sport_athletes 
                     (student_id, sport_type, position, is_captain, academic_year_id, registered_by, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $student_id, 
                $sport_type, 
                $position, 
                $is_captain, 
                $current_year['academic_year_id'], 
                $_SESSION['admin_id'], 
                $notes
            ]);
            
            $db->commit();
            $message = 'เพิ่มนักกีฬาเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// แก้ไขนักกีฬา
if (isset($_POST['update_athlete'])) {
    $athlete_id = $_POST['athlete_id'];
    $sport_type = trim($_POST['sport_type']);
    $position = trim($_POST['position']);
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;
    $notes = trim($_POST['notes']);
    
    if (!empty($athlete_id) && !empty($sport_type)) {
        try {
            $db->beginTransaction();
            
            // ดึงข้อมูลนักกีฬาเดิม
            $query = "SELECT * FROM sport_athletes WHERE athlete_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$athlete_id]);
            $old_athlete = $stmt->fetch();
            
            // ถ้ากำหนดเป็นหัวหน้าทีม
            if ($is_captain && !$old_athlete['is_captain']) {
                // ดึงข้อมูลสีของนักเรียน
                $query = "SELECT color_id FROM student_sport_colors 
                         WHERE student_id = ? AND academic_year_id = ? AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$old_athlete['student_id'], $current_year['academic_year_id']]);
                $student_color = $stmt->fetch();
                
                if ($student_color) {
                    // ยกเลิกหัวหน้าทีมคนเดิมในสีและกีฬาเดียวกัน
                    $query = "UPDATE sport_athletes sa
                             JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id
                             SET sa.is_captain = 0
                             WHERE sa.sport_type = ? 
                             AND sa.academic_year_id = ?
                             AND ssc.color_id = ?
                             AND ssc.academic_year_id = ?
                             AND ssc.is_active = 1
                             AND sa.is_captain = 1
                             AND sa.athlete_id != ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $sport_type, 
                        $current_year['academic_year_id'], 
                        $student_color['color_id'], 
                        $current_year['academic_year_id'],
                        $athlete_id
                    ]);
                }
            }
            
            // อัปเดตข้อมูลนักกีฬา
            $query = "UPDATE sport_athletes 
                     SET sport_type = ?, position = ?, is_captain = ?, notes = ?
                     WHERE athlete_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$sport_type, $position, $is_captain, $notes, $athlete_id]);
            
            $db->commit();
            $message = 'แก้ไขข้อมูลนักกีฬาเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// ลบนักกีฬา
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $athlete_id = (int)$_GET['delete'];
    
    $query = "DELETE FROM sport_athletes WHERE athlete_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$athlete_id])) {
        $message = 'ลบนักกีฬาเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// ค้นหาและกรอง
$search = $_GET['search'] ?? '';
$sport_filter = $_GET['sport_filter'] ?? '';
$color_filter = $_GET['color_filter'] ?? '';
$position_filter = $_GET['position_filter'] ?? '';
$captain_filter = $_GET['captain_filter'] ?? '';

// ดึงข้อมูลนักกีฬา
$where_conditions = ["sa.academic_year_id = ?"];
$params = [$current_year['academic_year_id']];

if (!empty($search)) {
    $where_conditions[] = "(s.student_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($sport_filter)) {
    $where_conditions[] = "sa.sport_type = ?";
    $params[] = $sport_filter;
}

if (!empty($color_filter)) {
    $where_conditions[] = "ssc.color_id = ? AND ssc.is_active = 1";
    $params[] = $color_filter;
}

if (!empty($position_filter)) {
    $where_conditions[] = "sa.position LIKE ?";
    $params[] = "%$position_filter%";
}

if (!empty($captain_filter)) {
    $where_conditions[] = "sa.is_captain = ?";
    $params[] = $captain_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT sa.*, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number,
                 sc.color_name, sc.color_code,
                 CONCAT(admin.title, admin.first_name, ' ', admin.last_name) as registered_by_name
          FROM sport_athletes sa
          JOIN students s ON sa.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = sa.academic_year_id AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN admin_users admin ON sa.registered_by = admin.admin_id
          WHERE $where_clause
          ORDER BY sa.sport_type, sc.color_name, sa.is_captain DESC, u.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$athletes = $stmt->fetchAll();

// ดึงประเภทกีฬาทั้งหมด
$query = "SELECT DISTINCT sport_type FROM sport_athletes 
          WHERE academic_year_id = ? 
          ORDER BY sport_type";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$sports = $stmt->fetchAll();

// ดึงตำแหน่งทั้งหมด
$query = "SELECT DISTINCT position FROM sport_athletes 
          WHERE academic_year_id = ? AND position IS NOT NULL AND position != ''
          ORDER BY position";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$positions = $stmt->fetchAll();

// ดึงสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();

// ดึงนักเรียนที่มีสีแล้ว
$query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number, sc.color_name
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                AND ssc.academic_year_id = ? AND ssc.is_active = 1
          JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          WHERE s.status = 'กำลังศึกษา'
          ORDER BY c.level, d.department_name, c.group_number, u.first_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$available_students = $stmt->fetchAll();

// สถิติ
$query = "SELECT 
            COUNT(*) as total_athletes,
            COUNT(DISTINCT sport_type) as total_sports,
            COUNT(CASE WHEN is_captain = 1 THEN 1 END) as total_captains,
            COUNT(DISTINCT student_id) as unique_students
          FROM sport_athletes 
          WHERE academic_year_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$stats = $stmt->fetch();

// สถิติตามประเภทกีฬา
$query = "SELECT sa.sport_type, 
                 COUNT(*) as athlete_count,
                 COUNT(CASE WHEN sa.is_captain = 1 THEN 1 END) as captain_count,
                 COUNT(DISTINCT ssc.color_id) as color_count
          FROM sport_athletes sa
          LEFT JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id 
                   AND ssc.academic_year_id = sa.academic_year_id AND ssc.is_active = 1
          WHERE sa.academic_year_id = ?
          GROUP BY sa.sport_type
          ORDER BY athlete_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$sport_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการนักกีฬา - ระบบกีฬาสี</title>
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
        .color-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .captain-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            font-weight: bold;
        }
        .sport-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .sport-card:hover {
            transform: translateY(-2px);
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
                    <h2><i class="fas fa-running"></i> จัดการนักกีฬา</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAthleteModal">
                        <i class="fas fa-plus"></i> เพิ่มนักกีฬา
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
                    <div class="col-md-3">
                        <div class="card text-center bg-primary text-white">
                            <div class="card-body">
                                <i class="fas fa-running fa-2x mb-2"></i>
                                <h5>นักกีฬาทั้งหมด</h5>
                                <h3><?php echo number_format($stats['total_athletes']); ?></h3>
                                <small>จาก <?php echo number_format($stats['unique_students']); ?> คน</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <i class="fas fa-futbol fa-2x mb-2"></i>
                                <h5>ประเภทกีฬา</h5>
                                <h3><?php echo number_format($stats['total_sports']); ?></h3>
                                <small>ประเภท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-warning text-dark">
                            <div class="card-body">
                                <i class="fas fa-crown fa-2x mb-2"></i>
                                <h5>หัวหน้าทีม</h5>
                                <h3><?php echo number_format($stats['total_captains']); ?></h3>
                                <small>คน</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h5>นักเรียนที่ลงทะเบียน</h5>
                                <h3><?php echo number_format($stats['unique_students']); ?></h3>
                                <small>คน</small>
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
                                        <div class="col-md-4 mb-3">
                                            <div class="card sport-card h-100" style="border-left-color: <?php echo $color; ?>">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">
                                                        <i class="fas fa-trophy"></i> <?php echo $stat['sport_type']; ?>
                                                    </h6>
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <strong><?php echo $stat['athlete_count']; ?></strong>
                                                            <br><small>นักกีฬา</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?php echo $stat['captain_count']; ?></strong>
                                                            <br><small>หัวหน้า</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?php echo $stat['color_count']; ?></strong>
                                                            <br><small>สี</small>
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
                            <div class="col-md-2">
                                <label class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="รหัส/ชื่อนักเรียน" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ประเภทกีฬา</label>
                                <select class="form-select" name="sport_filter">
                                    <option value="">ทุกประเภท</option>
                                    <?php foreach ($sports as $sport): ?>
                                        <option value="<?php echo $sport['sport_type']; ?>" 
                                                <?php echo $sport_filter === $sport['sport_type'] ? 'selected' : ''; ?>>
                                            <?php echo $sport['sport_type']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">สี</label>
                                <select class="form-select" name="color_filter">
                                    <option value="">ทุกสี</option>
                                    <?php foreach ($colors as $color): ?>
                                        <option value="<?php echo $color['color_id']; ?>" 
                                                <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                            <?php echo $color['color_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ตำแหน่ง</label>
                                <select class="form-select" name="position_filter">
                                    <option value="">ทุกตำแหน่ง</option>
                                    <?php foreach ($positions as $pos): ?>
                                        <option value="<?php echo $pos['position']; ?>" 
                                                <?php echo $position_filter === $pos['position'] ? 'selected' : ''; ?>>
                                            <?php echo $pos['position']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">หัวหน้าทีม</label>
                                <select class="form-select" name="captain_filter">
                                    <option value="">ทั้งหมด</option>
                                    <option value="1" <?php echo $captain_filter === '1' ? 'selected' : ''; ?>>หัวหน้าทีม</option>
                                    <option value="0" <?php echo $captain_filter === '0' ? 'selected' : ''; ?>>สมาชิก</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                                <a href="athletes.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> ล้าง
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- รายชื่อนักกีฬา -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> รายชื่อนักกีฬา (<?php echo count($athletes); ?> คน)</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="athletesTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>รหัสนักเรียน</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>ชั้นเรียน</th>
                                        <th>สี</th>
                                        <th>ประเภทกีฬา</th>
                                        <th>ตำแหน่ง</th>
                                        <th>สถานะ</th>
                                        <th>วันที่ลงทะเบียน</th>
                                        <th>ผู้บันทึก</th>
                                        <th>หมายเหตุ</th>
                                        <th width="120">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($athletes as $athlete): ?>
                                        <tr>
                                            <td><strong><?php echo $athlete['student_code']; ?></strong></td>
                                            <td><?php echo $athlete['title'] . $athlete['first_name'] . ' ' . $athlete['last_name']; ?></td>
                                            <td>
                                                <small><?php echo $athlete['level'] . ' ' . $athlete['department_name'] . ' ' . $athlete['group_number']; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($athlete['color_name']): ?>
                                                    <span class="color-badge" style="background-color: <?php echo $athlete['color_code']; ?>"></span>
                                                    <?php echo $athlete['color_name']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">ไม่มีสี</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $athlete['sport_type']; ?></span>
                                            </td>
                                            <td><?php echo $athlete['position'] ?: '-'; ?></td>
                                            <td>
                                                <?php if ($athlete['is_captain']): ?>
                                                    <span class="badge captain-badge">
                                                        <i class="fas fa-crown"></i> หัวหน้าทีม
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">สมาชิก</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y H:i', strtotime($athlete['registered_date'])); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo $athlete['registered_by_name']; ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo $athlete['notes'] ? htmlspecialchars($athlete['notes']) : '-'; ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editAthlete(<?php echo htmlspecialchars(json_encode($athlete)); ?>)"
                                                            title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $athlete['athlete_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('ต้องการลบนักกีฬาคนนี้?')"
                                                       title="ลบ">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($athletes)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <i class="fas fa-running fa-3x text-muted"></i>
                                                <p class="mt-2 text-muted">ยังไม่มีข้อมูลนักกีฬา</p>
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
    
    <!-- Add/Edit Athlete Modal -->
    <div class="modal fade" id="addAthleteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มนักกีฬา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="athleteForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                                    <select class="form-select" name="student_id" id="studentSelect" required>
                                        <option value="">-- เลือกนักเรียน --</option>
                                        <?php foreach ($available_students as $student): ?>
                                            <option value="<?php echo $student['student_id']; ?>" 
                                                    data-color="<?php echo $student['color_name']; ?>"
                                                    data-class="<?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?>">
                                                <?php echo $student['student_code'] . ' - ' . $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                                (<?php echo $student['color_name']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="studentInfo" class="form-text"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ประเภทกีฬา <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="sport_type" 
                                           placeholder="เช่น ฟุตบอล, วอลเลย์บอล, บาสเกตบอล" 
                                           list="sportsList" required>
                                    <datalist id="sportsList">
                                        <option value="ฟุตบอล">
                                        <option value="วอลเลย์บอล">
                                        <option value="บาสเกตบอล">
                                        <option value="แบดมินตัน">
                                        <option value="เซปักตะกร้อ">
                                        <option value="ปิงปอง">
                                        <option value="เทนนิส">
                                        <option value="วิ่ง">
                                        <option value="กรีฑา">
                                        <option value="ว่ายน้ำ">
                                        <?php foreach ($sports as $sport): ?>
                                            <option value="<?php echo $sport['sport_type']; ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ตำแหน่ง</label>
                                    <input type="text" class="form-control" name="position" 
                                           placeholder="เช่น กองหลัง, กองกลาง, กองหน้า" 
                                           list="positionsList">
                                    <datalist id="positionsList">
                                        <option value="ผู้รักษาประตู">
                                        <option value="กองหลัง">
                                        <option value="กองกลาง">
                                        <option value="กองหน้า">
                                        <option value="ตัวรับ">
                                        <option value="ตัวตั้ง">
                                        <option value="ตัวเสริม">
                                        <option value="ตัวตบ">
                                        <option value="การ์ด">
                                        <option value="ฟอร์เวิร์ด">
                                        <option value="เซ็นเตอร์">
                                        <?php foreach ($positions as $pos): ?>
                                            <option value="<?php echo $pos['position']; ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_captain" id="is_captain">
                                        <label class="form-check-label" for="is_captain">
                                            <strong>หัวหน้าทีม</strong>
                                        </label>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle text-info"></i>
                                            หากเลือกเป็นหัวหน้าทีม จะยกเลิกหัวหน้าทีมคนเดิมในสีและกีฬาเดียวกันโดยอัตโนมัติ
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea class="form-control" name="notes" rows="4" 
                                              placeholder="บันทึกข้อมูลเพิ่มเติม เช่น ประสบการณ์, รางวัลที่เคยได้รับ, ฯลฯ"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- แสดงข้อมูลสรุป -->
                        <div id="athleteSummary" class="alert alert-info d-none">
                            <h6><i class="fas fa-info-circle"></i> สรุปข้อมูลนักกีฬา</h6>
                            <div id="summaryContent"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_athlete" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // อัปเดตข้อมูลนักเรียน
        function updateStudentInfo() {
            const studentSelect = document.getElementById('studentSelect');
            const studentInfo = document.getElementById('studentInfo');
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const color = selectedOption.getAttribute('data-color');
                const classInfo = selectedOption.getAttribute('data-class');
                
                studentInfo.innerHTML = `
                    <i class="fas fa-info-circle text-info"></i> 
                    ชั้นเรียน: <strong>${classInfo}</strong> | 
                    สี: <strong>${color}</strong>
                `;
                updateAthleteSummary();
            } else {
                studentInfo.innerHTML = '';
                document.getElementById('athleteSummary').classList.add('d-none');
            }
        }
        
        // อัปเดตสรุปข้อมูลนักกีฬา
        function updateAthleteSummary() {
            const studentSelect = document.getElementById('studentSelect');
            const sportInput = document.querySelector('input[name="sport_type"]');
            const positionInput = document.querySelector('input[name="position"]');
            const captainCheck = document.getElementById('is_captain');
            
            const selectedStudent = studentSelect.options[studentSelect.selectedIndex];
            
            if (selectedStudent && selectedStudent.value && sportInput.value) {
                const summary = document.getElementById('athleteSummary');
                const content = document.getElementById('summaryContent');
                
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>นักเรียน:</strong> ${selectedStudent.text.split(' (')[0]}<br>
                            <strong>สี:</strong> ${selectedStudent.getAttribute('data-color')}
                        </div>
                        <div class="col-md-6">
                            <strong>ประเภทกีฬา:</strong> ${sportInput.value}<br>
                            <strong>ตำแหน่ง:</strong> ${positionInput.value || 'ไม่ระบุ'}<br>
                            <strong>สถานะ:</strong> ${captainCheck.checked ? 'หัวหน้าทีม' : 'สมาชิก'}
                        </div>
                    </div>
                `;
                
                summary.classList.remove('d-none');
            } else {
                document.getElementById('athleteSummary').classList.add('d-none');
            }
        }
        
        // แก้ไขนักกีฬา
        function editAthlete(athlete) {
            const modal = new bootstrap.Modal(document.getElementById('addAthleteModal'));
            const form = document.getElementById('athleteForm');
            
            // เปลี่ยนหัวข้อโมดอล
            document.querySelector('#addAthleteModal .modal-title').innerHTML = 
                '<i class="fas fa-edit"></i> แก้ไขข้อมูลนักกีฬา';
            
            // ตั้งค่าข้อมูลในฟอร์ม
            form.querySelector('select[name="student_id"]').value = athlete.student_id;
            form.querySelector('input[name="sport_type"]').value = athlete.sport_type;
            form.querySelector('input[name="position"]').value = athlete.position || '';
            form.querySelector('input[name="is_captain"]').checked = athlete.is_captain == 1;
            form.querySelector('textarea[name="notes"]').value = athlete.notes || '';
            
            // เพิ่ม hidden input สำหรับ athlete_id
            let athleteIdInput = form.querySelector('input[name="athlete_id"]');
            if (!athleteIdInput) {
                athleteIdInput = document.createElement('input');
                athleteIdInput.type = 'hidden';
                athleteIdInput.name = 'athlete_id';
                form.appendChild(athleteIdInput);
            }
            athleteIdInput.value = athlete.athlete_id;
            
            // เปลี่ยนปุ่มส่ง
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> อัปเดต';
            submitBtn.setAttribute('name', 'update_athlete');
            
            updateStudentInfo();
            modal.show();
        }
        
        // ส่งออก Excel
        function exportToExcel() {
            const table = document.getElementById('athletesTable');
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
                    if (index < 10) { // ไม่รวมคอลัมน์จัดการ
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
                link.setAttribute('download', 'athletes_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // อัปเดตข้อมูลนักเรียนเมื่อเลือก
            document.getElementById('studentSelect').addEventListener('change', updateStudentInfo);
            
            // อัปเดตสรุปเมื่อเปลี่ยนข้อมูล
            document.querySelector('input[name="sport_type"]').addEventListener('input', updateAthleteSummary);
            document.querySelector('input[name="position"]').addEventListener('input', updateAthleteSummary);
            document.getElementById('is_captain').addEventListener('change', updateAthleteSummary);
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            document.getElementById('addAthleteModal').addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('athleteForm');
                form.reset();
                
                // รีเซ็ตหัวข้อและปุ่ม
                document.querySelector('#addAthleteModal .modal-title').innerHTML = 
                    '<i class="fas fa-plus"></i> เพิ่มนักกีฬา';
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึก';
                submitBtn.setAttribute('name', 'add_athlete');
                
                // ลบ athlete_id ถ้ามี
                const athleteIdInput = form.querySelector('input[name="athlete_id"]');
                if (athleteIdInput) {
                    athleteIdInput.remove();
                }
                
                // ซ่อนข้อมูลสรุป
                document.getElementById('studentInfo').innerHTML = '';
                document.getElementById('athleteSummary').classList.add('d-none');
            });
        });
    </script>
</body>
</html>