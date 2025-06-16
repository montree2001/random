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
    $position = trim($_POST['position']) ?: NULL;
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;
    $notes = trim($_POST['notes']) ?: NULL;
    
    if (!empty($student_id) && !empty($sport_type)) {
        try {
            // ตรวจสอบว่านักเรียนมีสีแล้วหรือไม่
            $query = "SELECT COUNT(*) FROM student_sport_colors 
                     WHERE student_id = ? AND academic_year_id = ? AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $current_year['academic_year_id']]);
            $has_color = $stmt->fetchColumn();
            
            if (!$has_color) {
                throw new Exception('นักเรียนยังไม่มีสี กรุณาจัดสีให้นักเรียนก่อน');
            }
            
            // ตรวจสอบว่าซ้ำหรือไม่
            $query = "SELECT COUNT(*) FROM sport_athletes 
                     WHERE student_id = ? AND sport_type = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $sport_type, $current_year['academic_year_id']]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                throw new Exception('นักเรียนคนนี้ลงทะเบียนกีฬาประเภทนี้แล้ว');
            }
            
            // เพิ่มนักกีฬา
            $query = "INSERT INTO sport_athletes 
                     (student_id, sport_type, position, is_captain, notes, academic_year_id) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $sport_type, $position, $is_captain, $notes, 
                           $current_year['academic_year_id']]);
            
            $message = 'เพิ่มนักกีฬาเรียบร้อยแล้ว';
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

// แก้ไขนักกีฬา
if (isset($_POST['edit_athlete'])) {
    $athlete_id = $_POST['athlete_id'];
    $sport_type = trim($_POST['sport_type']);
    $position = trim($_POST['position']) ?: NULL;
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;
    $notes = trim($_POST['notes']) ?: NULL;
    
    if (!empty($athlete_id) && !empty($sport_type)) {
        try {
            $query = "UPDATE sport_athletes 
                     SET sport_type = ?, position = ?, is_captain = ?, notes = ?, updated_at = NOW()
                     WHERE athlete_id = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$sport_type, $position, $is_captain, $notes, 
                           $athlete_id, $current_year['academic_year_id']]);
            
            $message = 'แก้ไขข้อมูลนักกีฬาเรียบร้อยแล้ว';
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

// อนุมัติการสมัครนักกีฬา
if (isset($_POST['approve_application'])) {
    $athlete_id = $_POST['athlete_id'];
    $is_captain = isset($_POST['make_captain']) ? 1 : 0;
    
    if (!empty($athlete_id)) {
        try {
            $db->beginTransaction();
            
            // อัปเดตสถานะเป็นอนุมัติ
            $query = "UPDATE sport_athletes 
                     SET is_captain = ?, updated_at = NOW()
                     WHERE athlete_id = ? AND academic_year_id = ? AND is_captain = -1";
            $stmt = $db->prepare($query);
            $stmt->execute([$is_captain, $athlete_id, $current_year['academic_year_id']]);
            
            if ($stmt->rowCount() > 0) {
                $db->commit();
                $message = 'อนุมัติการสมัครเรียบร้อยแล้ว';
                $message_type = 'success';
            } else {
                throw new Exception('ไม่พบรายการสมัครที่ต้องการอนุมัติ');
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// ปฏิเสธการสมัครนักกีฬา
if (isset($_POST['reject_application'])) {
    $athlete_id = $_POST['athlete_id'];
    
    if (!empty($athlete_id)) {
        try {
            $query = "DELETE FROM sport_athletes 
                     WHERE athlete_id = ? AND academic_year_id = ? AND is_captain = -1";
            $stmt = $db->prepare($query);
            $stmt->execute([$athlete_id, $current_year['academic_year_id']]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'ปฏิเสธการสมัครเรียบร้อยแล้ว';
                $message_type = 'warning';
            } else {
                $message = 'ไม่พบรายการสมัครที่ต้องการปฏิเสธ';
                $message_type = 'danger';
            }
            
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// ลบนักกีฬา
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $athlete_id = (int)$_GET['delete'];
    
    $query = "DELETE FROM sport_athletes 
             WHERE athlete_id = ? AND academic_year_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$athlete_id, $current_year['academic_year_id']])) {
        $message = 'ลบนักกีฬาเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// เพิ่มประเภทกีฬาใหม่
if (isset($_POST['add_sport_type'])) {
    $sport_name = trim($_POST['sport_name']);
    $sport_description = trim($_POST['sport_description']) ?: NULL;
    
    if (!empty($sport_name)) {
        try {
            // ตรวจสอบว่าซ้ำหรือไม่
            $query = "SELECT COUNT(*) FROM sport_athletes 
                     WHERE sport_type = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$sport_name, $current_year['academic_year_id']]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                throw new Exception('ประเภทกีฬานี้มีอยู่แล้ว');
            }
            
            // ถ้าไม่มีให้เพิ่มเสร็จสิ้น (ไม่ต้องสร้างใน database จริง เพราะเป็น field ใน sport_athletes)
            $message = 'เพิ่มประเภทกีฬา "' . $sport_name . '" เรียบร้อยแล้ว สามารถเลือกใช้ตอนเพิ่มนักกีฬาได้';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกชื่อประเภทกีฬา';
        $message_type = 'warning';
    }
}

// ลบประเภทกีฬา
if (isset($_POST['delete_sport_type'])) {
    $sport_type = $_POST['sport_type_to_delete'];
    
    if (!empty($sport_type)) {
        try {
            $db->beginTransaction();
            
            // ลบนักกีฬาในประเภทนี้ทั้งหมด
            $query = "DELETE FROM sport_athletes 
                     WHERE sport_type = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$sport_type, $current_year['academic_year_id']]);
            $deleted_count = $stmt->rowCount();
            
            $db->commit();
            
            $message = 'ลบประเภทกีฬา "' . $sport_type . '" และนักกีฬา ' . $deleted_count . ' คน เรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// ดาวน์โหลดรายชื่อนักกีฬา
if (isset($_GET['export'])) {
    $export_sport = $_GET['export_sport'] ?? '';
    
    // ป้องกัน error output ก่อนส่ง header
    ob_clean();
    
    // กำหนด header สำหรับดาวน์โหลด CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="athletes_list_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // เขียน BOM สำหรับ UTF-8 เพื่อให้ Excel แสดงภาษาไทยถูกต้อง
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header - เพิ่ม escape parameter สำหรับ PHP 8.3+
    fputcsv($output, [
        'ลำดับ', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ระดับ', 'แผนก/กลุ่ม', 
        'สี', 'ประเภทกีฬา', 'ตำแหน่ง', 'สถานะ', 'หมายเหตุ'
    ], ',', '"', '\\');
    
    // สร้างคำสั่ง SQL สำหรับ export
    $export_where = ["sa.academic_year_id = ?"];
    $export_params = [$current_year['academic_year_id']];
    
    if (!empty($export_sport)) {
        $export_where[] = "sa.sport_type = ?";
        $export_params[] = $export_sport;
    }
    
    $export_where_clause = "WHERE " . implode(" AND ", $export_where);
    
    $export_query = "SELECT sa.*, s.student_code, s.title, u.first_name, u.last_name,
                           c.level, d.department_name, c.group_number,
                           sc.color_name
                    FROM sport_athletes sa
                    JOIN students s ON sa.student_id = s.student_id
                    JOIN users u ON s.user_id = u.user_id
                    LEFT JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id 
                             AND ssc.academic_year_id = sa.academic_year_id AND ssc.is_active = 1
                    LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                    LEFT JOIN classes c ON s.current_class_id = c.class_id
                    LEFT JOIN departments d ON c.department_id = d.department_id
                    $export_where_clause
                    ORDER BY sa.sport_type, sa.is_captain DESC, u.first_name";
    
    $export_stmt = $db->prepare($export_query);
    $export_stmt->execute($export_params);
    $export_athletes = $export_stmt->fetchAll();
    
    // เขียนข้อมูล - เพิ่ม escape parameter สำหรับ PHP 8.3+
    $row_number = 1;
    foreach ($export_athletes as $athlete) {
        fputcsv($output, [
            $row_number++,
            $athlete['student_code'],
            $athlete['title'] . $athlete['first_name'] . ' ' . $athlete['last_name'],
            $athlete['level'] ?: '-',
            ($athlete['department_name'] ?: '-') . '/' . ($athlete['group_number'] ?: '-'),
            $athlete['color_name'] ?: '-',
            $athlete['sport_type'],
            $athlete['position'] ?: '-',
            $athlete['is_captain'] ? 'หัวหน้าทีม' : 'สมาชิก',
            $athlete['notes'] ?: '-'
        ], ',', '"', '\\');
    }
    
    fclose($output);
    exit;
}

// ดึงข้อมูลรายการรออนุมัติ
$query_pending = "SELECT sa.*, s.student_code, s.title, u.first_name, u.last_name,
                        c.level, d.department_name, c.group_number,
                        sc.color_name, sc.color_code, ssc.color_id
                 FROM sport_athletes sa
                 JOIN students s ON sa.student_id = s.student_id
                 JOIN users u ON s.user_id = u.user_id
                 LEFT JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id 
                          AND ssc.academic_year_id = sa.academic_year_id AND ssc.is_active = 1
                 LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                 LEFT JOIN classes c ON s.current_class_id = c.class_id
                 LEFT JOIN departments d ON c.department_id = d.department_id
                 WHERE sa.academic_year_id = ? AND sa.is_captain = -1
                 ORDER BY sa.created_at DESC";

$stmt_pending = $db->prepare($query_pending);
$stmt_pending->execute([$current_year['academic_year_id']]);
$pending_applications = $stmt_pending->fetchAll();

// ค้นหาและกรองข้อมูล
$search = $_GET['search'] ?? '';
$sport_filter = $_GET['sport_filter'] ?? '';
$color_filter = $_GET['color_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';
$captain_filter = $_GET['captain_filter'] ?? '';

// สร้าง WHERE clause
$where_conditions = ["sa.academic_year_id = ?"];
$params = [$current_year['academic_year_id']];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_code LIKE ? OR sa.sport_type LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($sport_filter)) {
    $where_conditions[] = "sa.sport_type = ?";
    $params[] = $sport_filter;
}

if (!empty($color_filter)) {
    $where_conditions[] = "ssc.color_id = ?";
    $params[] = $color_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "d.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($captain_filter)) {
    $where_conditions[] = "sa.is_captain = ?";
    $params[] = $captain_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// ดึงข้อมูลนักกีฬา
$query = "SELECT sa.*, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number,
                 sc.color_name, sc.color_code, ssc.color_id
          FROM sport_athletes sa
          JOIN students s ON sa.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN student_sport_colors ssc ON sa.student_id = ssc.student_id 
                   AND ssc.academic_year_id = sa.academic_year_id AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          $where_clause
          ORDER BY sa.sport_type, sa.is_captain DESC, u.first_name";

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

// ดึงสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();

// ดึงแผนกทั้งหมด
$query = "SELECT * FROM departments ORDER BY department_name";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll();

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

// สถิติ (รวมทั้งอนุมัติแล้วและรออนุมัติ)
$query = "SELECT 
            COUNT(*) as total_athletes,
            COUNT(CASE WHEN is_captain != -1 THEN 1 END) as approved_athletes,
            COUNT(CASE WHEN is_captain = -1 THEN 1 END) as pending_athletes,
            COUNT(DISTINCT sport_type) as total_sports,
            COUNT(CASE WHEN is_captain = 1 THEN 1 END) as total_captains,
            COUNT(DISTINCT CASE WHEN is_captain != -1 THEN student_id END) as unique_students
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .athlete-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .athlete-card:hover {
            transform: translateX(5px);
        }
        .captain-badge {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
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
                    <div class="btn-group">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-download"></i> ดาวน์โหลดรายชื่อ
                        </button>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#manageSportsModal">
                            <i class="fas fa-cogs"></i> จัดการประเภทกีฬา
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAthleteModal">
                            <i class="fas fa-plus"></i> เพิ่มนักกีฬา
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- สถิติ -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-running fa-2x text-primary mb-2"></i>
                            <h4><?php echo number_format($stats['approved_athletes']); ?></h4>
                            <p class="mb-0 text-muted">นักกีฬาที่อนุมัติ</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4><?php echo number_format($stats['pending_athletes']); ?></h4>
                            <p class="mb-0 text-muted">รออนุมัติ</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-trophy fa-2x text-success mb-2"></i>
                            <h4><?php echo number_format($stats['total_sports']); ?></h4>
                            <p class="mb-0 text-muted">ประเภทกีฬา</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-crown fa-2x text-warning mb-2"></i>
                            <h4><?php echo number_format($stats['total_captains']); ?></h4>
                            <p class="mb-0 text-muted">หัวหน้าทีม</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h4><?php echo number_format($stats['unique_students']); ?></h4>
                            <p class="mb-0 text-muted">นักเรียนเป็นนักกีฬา</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="fas fa-file-alt fa-2x text-secondary mb-2"></i>
                            <h4><?php echo number_format($stats['total_athletes']); ?></h4>
                            <p class="mb-0 text-muted">ใบสมัครทั้งหมด</p>
                        </div>
                    </div>
                </div>

                <!-- แท็บนำทาง -->
                <ul class="nav nav-tabs" id="athletesTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="approved-tab" data-bs-toggle="tab" 
                                data-bs-target="#approved" type="button" role="tab">
                            <i class="fas fa-users"></i> นักกีฬาที่อนุมัติแล้ว
                            <span class="badge bg-primary ms-1"><?php echo count($athletes); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" 
                                data-bs-target="#pending" type="button" role="tab">
                            <i class="fas fa-clock"></i> รายการรออนุมัติ
                            <span class="badge bg-warning ms-1"><?php echo count($pending_applications); ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="athletesTabContent">
                    <!-- แท็บนักกีฬาที่อนุมัติแล้ว -->
                    <div class="tab-pane fade show active" id="approved" role="tabpanel">
                        <!-- ส่วนกรองข้อมูล -->
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="tab" value="approved">
                                <div class="col-md-3">
                                    <label class="form-label">ค้นหา</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="ชื่อ รหัส หรือกีฬา">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ประเภทกีฬา</label>
                                    <select name="sport_filter" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($sports as $sport): ?>
                                            <option value="<?php echo $sport['sport_type']; ?>" 
                                                    <?php echo $sport_filter == $sport['sport_type'] ? 'selected' : ''; ?>>
                                                <?php echo $sport['sport_type']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">สี</label>
                                    <select name="color_filter" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($colors as $color): ?>
                                            <option value="<?php echo $color['color_id']; ?>" 
                                                    <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                                <?php echo $color['color_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">แผนก</label>
                                    <select name="department_filter" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>" 
                                                    <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                                <?php echo $dept['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หัวหน้าทีม</label>
                                    <select name="captain_filter" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <option value="1" <?php echo $captain_filter == '1' ? 'selected' : ''; ?>>หัวหน้าทีม</option>
                                        <option value="0" <?php echo $captain_filter == '0' ? 'selected' : ''; ?>>สมาชิก</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <a href="athletes.php" class="btn btn-secondary">
                                            <i class="fas fa-refresh"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- รายการนักกีฬา -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> รายการนักกีฬาที่อนุมัติแล้ว 
                                    <span class="badge bg-primary"><?php echo count($athletes); ?> คน</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($athletes)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-running fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">ไม่พบข้อมูลนักกีฬา</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($athletes as $athlete): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card athlete-card" 
                                                     style="border-left-color: <?php echo $athlete['color_code'] ?? '#ccc'; ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h6 class="card-title mb-1">
                                                                    <?php if ($athlete['color_code']): ?>
                                                                        <span class="color-badge" 
                                                                              style="background-color: <?php echo $athlete['color_code']; ?>"></span>
                                                                    <?php endif; ?>
                                                                    <?php echo $athlete['title'] . $athlete['first_name'] . ' ' . $athlete['last_name']; ?>
                                                                    <?php if ($athlete['is_captain']): ?>
                                                                        <span class="captain-badge">
                                                                            <i class="fas fa-crown"></i> หัวหน้าทีม
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <p class="text-muted mb-1">
                                                                    <small>
                                                                        <i class="fas fa-id-card"></i> <?php echo $athlete['student_code']; ?> |
                                                                        <i class="fas fa-graduation-cap"></i> <?php echo $athlete['level']; ?> 
                                                                        <?php echo $athlete['department_name']; ?>/<?php echo $athlete['group_number']; ?>
                                                                    </small>
                                                                </p>
                                                                <div class="mb-2">
                                                                    <span class="badge bg-info">
                                                                        <i class="fas fa-trophy"></i> <?php echo $athlete['sport_type']; ?>
                                                                    </span>
                                                                    <?php if ($athlete['position']): ?>
                                                                        <span class="badge bg-secondary">
                                                                            <i class="fas fa-map-marker-alt"></i> <?php echo $athlete['position']; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if ($athlete['color_name']): ?>
                                                                        <span class="badge" style="background-color: <?php echo $athlete['color_code']; ?>;">
                                                                            <?php echo $athlete['color_name']; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if ($athlete['notes']): ?>
                                                                    <p class="text-muted mb-0">
                                                                        <small><i class="fas fa-sticky-note"></i> <?php echo $athlete['notes']; ?></small>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                                        data-bs-toggle="dropdown">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item" href="#" 
                                                                           onclick="editAthlete(<?php echo htmlspecialchars(json_encode($athlete)); ?>)">
                                                                            <i class="fas fa-edit"></i> แก้ไข
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger" 
                                                                           href="?delete=<?php echo $athlete['athlete_id']; ?>"
                                                                           onclick="return confirm('คุณแน่ใจว่าต้องการลบนักกีฬาคนนี้?')">
                                                                            <i class="fas fa-trash"></i> ลบ
                                                                        </a>
                                                                    </li>
                                                                </ul>
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

                    <!-- แท็บรายการรออนุมัติ -->
                    <div class="tab-pane fade" id="pending" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock"></i> รายการสมัครรออนุมัติ 
                                    <span class="badge bg-warning"><?php echo count($pending_applications); ?> รายการ</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_applications)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <p class="text-muted">ไม่มีรายการรออนุมัติ</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($pending_applications as $pending): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card" style="border-left: 4px solid #ffc107;">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h6 class="card-title mb-1">
                                                                    <?php if ($pending['color_code']): ?>
                                                                        <span class="color-badge" 
                                                                              style="background-color: <?php echo $pending['color_code']; ?>"></span>
                                                                    <?php endif; ?>
                                                                    <?php echo $pending['title'] . $pending['first_name'] . ' ' . $pending['last_name']; ?>
                                                                    <span class="badge bg-warning">
                                                                        <i class="fas fa-clock"></i> รออนุมัติ
                                                                    </span>
                                                                </h6>
                                                                <p class="text-muted mb-1">
                                                                    <small>
                                                                        <i class="fas fa-id-card"></i> <?php echo $pending['student_code']; ?> |
                                                                        <i class="fas fa-graduation-cap"></i> <?php echo $pending['level']; ?> 
                                                                        <?php echo $pending['department_name']; ?>/<?php echo $pending['group_number']; ?>
                                                                    </small>
                                                                </p>
                                                                <div class="mb-2">
                                                                    <span class="badge bg-info">
                                                                        <i class="fas fa-trophy"></i> <?php echo $pending['sport_type']; ?>
                                                                    </span>
                                                                    <?php if ($pending['position']): ?>
                                                                        <span class="badge bg-secondary">
                                                                            <i class="fas fa-map-marker-alt"></i> <?php echo $pending['position']; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if ($pending['color_name']): ?>
                                                                        <span class="badge" style="background-color: <?php echo $pending['color_code']; ?>;">
                                                                            <?php echo $pending['color_name']; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if ($pending['notes']): ?>
                                                                    <p class="text-muted mb-2">
                                                                        <small><i class="fas fa-sticky-note"></i> <?php echo $pending['notes']; ?></small>
                                                                    </p>
                                                                <?php endif; ?>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-calendar"></i> สมัครเมื่อ: 
                                                                    <?php echo date('d/m/Y H:i', strtotime($pending['created_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- ปุ่มจัดการ -->
                                                        <div class="mt-3 d-flex gap-2">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="athlete_id" value="<?php echo $pending['athlete_id']; ?>">
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="checkbox" name="make_captain" 
                                                                           id="captain_<?php echo $pending['athlete_id']; ?>">
                                                                    <label class="form-check-label" for="captain_<?php echo $pending['athlete_id']; ?>">
                                                                        <small>กำหนดเป็นหัวหน้าทีม</small>
                                                                    </label>
                                                                </div>
                                                                <button type="submit" name="approve_application" 
                                                                        class="btn btn-success btn-sm"
                                                                        onclick="return confirm('คุณแน่ใจว่าต้องการอนุมัติการสมัครนี้?')">
                                                                    <i class="fas fa-check"></i> อนุมัติ
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="athlete_id" value="<?php echo $pending['athlete_id']; ?>">
                                                                <button type="submit" name="reject_application" 
                                                                        class="btn btn-danger btn-sm"
                                                                        onclick="return confirm('คุณแน่ใจว่าต้องการปฏิเสธการสมัครนี้?')">
                                                                    <i class="fas fa-times"></i> ปฏิเสธ
                                                                </button>
                                                            </form>
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
                </div>

                <!-- ส่วนกรองข้อมูล -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">ค้นหา</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="ชื่อ รหัส หรือกีฬา">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ประเภทกีฬา</label>
                            <select name="sport_filter" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($sports as $sport): ?>
                                    <option value="<?php echo $sport['sport_type']; ?>" 
                                            <?php echo $sport_filter == $sport['sport_type'] ? 'selected' : ''; ?>>
                                        <?php echo $sport['sport_type']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">สี</label>
                            <select name="color_filter" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($colors as $color): ?>
                                    <option value="<?php echo $color['color_id']; ?>" 
                                            <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                        <?php echo $color['color_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">แผนก</label>
                            <select name="department_filter" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['department_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">หัวหน้าทีม</label>
                            <select name="captain_filter" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <option value="1" <?php echo $captain_filter == '1' ? 'selected' : ''; ?>>หัวหน้าทีม</option>
                                <option value="0" <?php echo $captain_filter == '0' ? 'selected' : ''; ?>>สมาชิก</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="athletes.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- รายการนักกีฬา -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> รายการนักกีฬา 
                            <span class="badge bg-primary"><?php echo count($athletes); ?> คน</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($athletes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-running fa-3x text-muted mb-3"></i>
                                <p class="text-muted">ไม่พบข้อมูลนักกีฬา</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($athletes as $athlete): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card athlete-card" 
                                             style="border-left-color: <?php echo $athlete['color_code'] ?? '#ccc'; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="card-title mb-1">
                                                            <?php if ($athlete['color_code']): ?>
                                                                <span class="color-badge" 
                                                                      style="background-color: <?php echo $athlete['color_code']; ?>"></span>
                                                            <?php endif; ?>
                                                            <?php echo $athlete['title'] . $athlete['first_name'] . ' ' . $athlete['last_name']; ?>
                                                            <?php if ($athlete['is_captain']): ?>
                                                                <span class="captain-badge">
                                                                    <i class="fas fa-crown"></i> หัวหน้าทีม
                                                                </span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <p class="text-muted mb-1">
                                                            <small>
                                                                <i class="fas fa-id-card"></i> <?php echo $athlete['student_code']; ?> |
                                                                <i class="fas fa-graduation-cap"></i> <?php echo $athlete['level']; ?> 
                                                                <?php echo $athlete['department_name']; ?>/<?php echo $athlete['group_number']; ?>
                                                            </small>
                                                        </p>
                                                        <div class="mb-2">
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-trophy"></i> <?php echo $athlete['sport_type']; ?>
                                                            </span>
                                                            <?php if ($athlete['position']): ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-map-marker-alt"></i> <?php echo $athlete['position']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($athlete['color_name']): ?>
                                                                <span class="badge" style="background-color: <?php echo $athlete['color_code']; ?>;">
                                                                    <?php echo $athlete['color_name']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($athlete['notes']): ?>
                                                            <p class="text-muted mb-0">
                                                                <small><i class="fas fa-sticky-note"></i> <?php echo $athlete['notes']; ?></small>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                                data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="editAthlete(<?php echo htmlspecialchars(json_encode($athlete)); ?>)">
                                                                    <i class="fas fa-edit"></i> แก้ไข
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" 
                                                                   href="?delete=<?php echo $athlete['athlete_id']; ?>"
                                                                   onclick="return confirm('คุณแน่ใจว่าต้องการลบนักกีฬาคนนี้?')">
                                                                    <i class="fas fa-trash"></i> ลบ
                                                                </a>
                                                            </li>
                                                        </ul>
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

                <!-- สถิติตามประเภทกีฬา -->
                <?php if (!empty($sport_stats)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> สถิติตามประเภทกีฬา
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($sport_stats as $sport_stat): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="card-title"><?php echo $sport_stat['sport_type']; ?></h6>
                                                <div class="row">
                                                    <div class="col">
                                                        <h5 class="text-primary"><?php echo $sport_stat['athlete_count']; ?></h5>
                                                        <small class="text-muted">นักกีฬา</small>
                                                    </div>
                                                    <div class="col">
                                                        <h5 class="text-warning"><?php echo $sport_stat['captain_count']; ?></h5>
                                                        <small class="text-muted">หัวหน้าทีม</small>
                                                    </div>
                                                    <div class="col">
                                                        <h5 class="text-info"><?php echo $sport_stat['color_count']; ?></h5>
                                                        <small class="text-muted">สี</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไขนักกีฬา -->
    <div class="modal fade" id="addAthleteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> เพิ่มนักกีฬาใหม่
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="athleteForm" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                                <select name="student_id" class="form-select" id="studentSelect" required>
                                    <option value="">-- เลือกนักเรียน --</option>
                                    <?php foreach ($available_students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>"
                                                data-color="<?php echo $student['color_name']; ?>"
                                                data-level="<?php echo $student['level']; ?>"
                                                data-department="<?php echo $student['department_name']; ?>"
                                                data-group="<?php echo $student['group_number']; ?>">
                                            <?php echo $student['student_code']; ?> - 
                                            <?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                            (<?php echo $student['color_name']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเภทกีฬา <span class="text-danger">*</span></label>
                                <input type="text" name="sport_type" class="form-control" 
                                       placeholder="เช่น ฟุตบอล, วิ่ง 100 เมตร" required
                                       list="sportsList">
                                <datalist id="sportsList">
                                    <?php foreach ($sports as $sport): ?>
                                        <option value="<?php echo $sport['sport_type']; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">ตำแหน่ง</label>
                                <input type="text" name="position" class="form-control" 
                                       placeholder="เช่น กองหลัง, นักวิ่ง">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_captain" id="isCaptain">
                                    <label class="form-check-label" for="isCaptain">
                                        <i class="fas fa-crown text-warning"></i> เป็นหัวหน้าทีม
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="หมายเหตุเพิ่มเติม"></textarea>
                        </div>
                        
                        <!-- ข้อมูลที่แสดงเมื่อเลือกนักเรียน -->
                        <div id="studentInfo" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> ข้อมูลนักเรียน</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>สี:</strong> <span id="studentColor"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>ระดับ:</strong> <span id="studentLevel"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>แผนก:</strong> <span id="studentDepartment"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>กลุ่ม:</strong> <span id="studentGroup"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_athlete" class="btn btn-primary">
                            <i class="fas fa-plus"></i> เพิ่มนักกีฬา
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ดาวน์โหลดรายชื่อ -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-download"></i> ดาวน์โหลดรายชื่อนักกีฬา
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">เลือกประเภทกีฬา</label>
                            <select name="export_sport" class="form-select">
                                <option value="">ทุกประเภทกีฬา</option>
                                <?php foreach ($sports as $sport): ?>
                                    <option value="<?php echo $sport['sport_type']; ?>">
                                        <?php echo $sport['sport_type']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> รายละเอียดไฟล์</h6>
                            <ul class="mb-0">
                                <li>รูปแบบไฟล์: CSV (เปิดใน Excel ได้)</li>
                                <li>การเข้ารหัส: UTF-8 พร้อม BOM (รองรับภาษาไทย)</li>
                                <li>ข้อมูล: รหัส, ชื่อ, ระดับ, แผนก, สี, ประเภทกีฬา, ตำแหน่ง</li>
                                <li>การเรียงลำดับ: ตามประเภทกีฬา > หัวหน้าทีม > ชื่อ</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="export" value="1" class="btn btn-success">
                            <i class="fas fa-download"></i> ดาวน์โหลด
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal จัดการประเภทกีฬา -->
    <div class="modal fade" id="manageSportsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cogs"></i> จัดการประเภทกีฬา
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- แท็บ -->
                    <ul class="nav nav-tabs" id="sportsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="add-sport-tab" data-bs-toggle="tab" 
                                    data-bs-target="#add-sport" type="button" role="tab">
                                <i class="fas fa-plus"></i> เพิ่มประเภทกีฬา
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="manage-sport-tab" data-bs-toggle="tab" 
                                    data-bs-target="#manage-sport" type="button" role="tab">
                                <i class="fas fa-list"></i> จัดการประเภทกีฬา
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="sportsTabContent">
                        <!-- แท็บเพิ่มประเภทกีฬา -->
                        <div class="tab-pane fade show active" id="add-sport" role="tabpanel">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">ชื่อประเภทกีฬา <span class="text-danger">*</span></label>
                                    <input type="text" name="sport_name" class="form-control" 
                                           placeholder="เช่น ฟุตบอล, วิ่ง 100 เมตร, แบดมินตัน" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">รายละเอียด</label>
                                    <textarea name="sport_description" class="form-control" rows="3" 
                                              placeholder="รายละเอียดของประเภทกีฬา (ไม่บังคับ)"></textarea>
                                </div>
                                
                                <button type="submit" name="add_sport_type" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> เพิ่มประเภทกีฬา
                                </button>
                            </form>
                        </div>
                        
                        <!-- แท็บจัดการประเภทกีฬา -->
                        <div class="tab-pane fade" id="manage-sport" role="tabpanel">
                            <?php if (empty($sports)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">ยังไม่มีประเภทกีฬา</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th width="50%">ประเภทกีฬา</th>
                                                <th width="20%">จำนวนนักกีฬา</th>
                                                <th width="20%">หัวหน้าทีม</th>
                                                <th width="10%">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sport_stats as $sport_stat): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-trophy text-warning"></i>
                                                        <?php echo $sport_stat['sport_type']; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $sport_stat['athlete_count']; ?> คน</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $sport_stat['captain_count']; ?> คน</span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="deleteSportType('<?php echo addslashes($sport_stat['sport_type']); ?>', <?php echo $sport_stat['athlete_count']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>คำเตือน:</strong> การลบประเภทกีฬาจะลบนักกีฬาในประเภทนั้นทั้งหมด
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ยืนยันการลบประเภทกีฬา -->
    <div class="modal fade" id="deleteSportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> ยืนยันการลบ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>คุณแน่ใจว่าต้องการลบประเภทกีฬา <strong id="deleteSportName"></strong>?</p>
                        <p class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            การลบจะทำให้นักกีฬา <span id="deleteAthleteCount"></span> คน ในประเภทนี้ถูกลบด้วย
                        </p>
                        <input type="hidden" name="sport_type_to_delete" id="sportTypeToDelete">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="delete_sport_type" class="btn btn-danger">
                            <i class="fas fa-trash"></i> ลบประเภทกีฬา
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // เริ่มต้น Select2
            $('#studentSelect').select2({
                theme: 'bootstrap-5',
                placeholder: '-- เลือกนักเรียน --',
                allowClear: true,
                dropdownParent: $('#addAthleteModal')
            });
            
            // แสดงข้อมูลนักเรียนเมื่อเลือก
            $('#studentSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    $('#studentColor').text(selectedOption.data('color'));
                    $('#studentLevel').text(selectedOption.data('level'));
                    $('#studentDepartment').text(selectedOption.data('department'));
                    $('#studentGroup').text(selectedOption.data('group'));
                    $('#studentInfo').show();
                } else {
                    $('#studentInfo').hide();
                }
            });
            
            // จัดการแท็บ - ถ้า URL มี parameter tab
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab === 'pending') {
                $('#pending-tab').click();
            }
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            $('#addAthleteModal').on('hidden.bs.modal', function() {
                const form = document.getElementById('athleteForm');
                form.reset();
                $('#studentSelect').val(null).trigger('change');
                $('#studentInfo').hide();
                
                // รีเซ็ตหัวข้อและปุ่ม
                $('.modal-title').html('<i class="fas fa-plus"></i> เพิ่มนักกีฬาใหม่');
                $('button[type="submit"]').html('<i class="fas fa-plus"></i> เพิ่มนักกีฬา').attr('name', 'add_athlete');
                
                // ลบ athlete_id ถ้ามี
                $('input[name="athlete_id"]').remove();
            });
            
            // รีเซ็ตฟอร์มจัดการประเภทกีฬาเมื่อปิดโมดอล
            $('#manageSportsModal').on('hidden.bs.modal', function() {
                // รีเซ็ตไปที่แท็บแรก
                $('#add-sport-tab').click();
                // รีเซ็ตฟอร์ม
                $('#add-sport form')[0].reset();
            });
        });
        
        // ฟังก์ชันแก้ไขนักกีฬา
        function editAthlete(athlete) {
            const modal = new bootstrap.Modal(document.getElementById('addAthleteModal'));
            const form = document.getElementById('athleteForm');
            
            // เปลี่ยนหัวข้อโมดอล
            $('.modal-title').html('<i class="fas fa-edit"></i> แก้ไขข้อมูลนักกีฬา');
            
            // ตั้งค่าข้อมูลในฟอร์ม
            $('#studentSelect').val(athlete.student_id).trigger('change');
            $('input[name="sport_type"]').val(athlete.sport_type);
            $('input[name="position"]').val(athlete.position || '');
            $('input[name="is_captain"]').prop('checked', athlete.is_captain == 1);
            $('textarea[name="notes"]').val(athlete.notes || '');
            
            // เพิ่ม hidden input สำหรับ athlete_id
            if (!$('input[name="athlete_id"]').length) {
                const athleteIdInput = document.createElement('input');
                athleteIdInput.type = 'hidden';
                athleteIdInput.name = 'athlete_id';
                form.appendChild(athleteIdInput);
            }
            $('input[name="athlete_id"]').val(athlete.athlete_id);
            
            // เปลี่ยนปุ่มส่ง
            $('button[type="submit"]').html('<i class="fas fa-save"></i> บันทึกการแก้ไข').attr('name', 'edit_athlete');
            
            modal.show();
        }
        
        // ฟังก์ชันลบประเภทกีฬา
        function deleteSportType(sportType, athleteCount) {
            $('#deleteSportName').text(sportType);
            $('#deleteAthleteCount').text(athleteCount);
            $('#sportTypeToDelete').val(sportType);
            
            const modal = new bootstrap.Modal(document.getElementById('deleteSportModal'));
            modal.show();
        }
    </script>
</body>
</html>