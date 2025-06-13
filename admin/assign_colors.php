<?php
// admin/assign_colors.php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
/* แสดงผล Error */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// จัดสีให้นักเรียน
if (isset($_POST['assign_color'])) {
    $student_id = $_POST['student_id'];
    $color_id = $_POST['color_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (!empty($student_id) && !empty($color_id)) {
        try {
            $db->beginTransaction();
            
            // ตรวจสอบจำนวนสมาชิกสูงสุดของสี
            $query = "SELECT sc.max_members, COUNT(ssc.student_id) as current_count
                     FROM sport_colors sc
                     LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                            AND ssc.academic_year_id = ? AND ssc.is_active = 1
                     WHERE sc.color_id = ?
                     GROUP BY sc.color_id";
            $stmt = $db->prepare($query);
            $stmt->execute([$current_year['academic_year_id'], $color_id]);
            $color_info = $stmt->fetch();
            
            if ($color_info['max_members'] && $color_info['current_count'] >= $color_info['max_members']) {
                // ตรวจสอบว่านักเรียนคนนี้มีสีนี้อยู่แล้วหรือไม่
                $query = "SELECT COUNT(*) as count FROM student_sport_colors 
                         WHERE student_id = ? AND color_id = ? AND academic_year_id = ? AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$student_id, $color_id, $current_year['academic_year_id']]);
                $existing = $stmt->fetch();
                
                if ($existing['count'] == 0) {
                    throw new Exception('สีนี้เต็มแล้ว (จำนวนสมาชิกสูงสุด: ' . $color_info['max_members'] . ' คน)');
                }
            }
            
            // ลบสีเดิม (ทำให้ inactive)
            $query = "UPDATE student_sport_colors 
                     SET is_active = 0 
                     WHERE student_id = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $current_year['academic_year_id']]);
            
            // เพิ่มสีใหม่
            $query = "INSERT INTO student_sport_colors 
                     (student_id, color_id, academic_year_id, assigned_by, notes) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $color_id, $current_year['academic_year_id'], $_SESSION['admin_id'], $notes]);
            
            $db->commit();
            $message = 'จัดสีเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณาเลือกนักเรียนและสี';
        $message_type = 'warning';
    }
}

// ลบสี
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $student_id = (int)$_GET['remove'];
    
    $query = "UPDATE student_sport_colors 
             SET is_active = 0 
             WHERE student_id = ? AND academic_year_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$student_id, $current_year['academic_year_id']])) {
        $message = 'ลบสีเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// สุ่มสีอัตโนมัติ
if (isset($_POST['auto_assign'])) {
    $balance_gender = isset($_POST['balance_gender']);
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (!empty($selected_students)) {
        try {
            $db->beginTransaction();
            
            // ลบสีเดิมของนักเรียนที่เลือก
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            $query = "UPDATE student_sport_colors 
                     SET is_active = 0 
                     WHERE student_id IN ($placeholders) AND academic_year_id = ?";
            $params = array_merge($selected_students, [$current_year['academic_year_id']]);
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // ดึงข้อมูลนักเรียนที่เลือก
            $query = "SELECT s.student_id, s.title, u.first_name, u.last_name 
                     FROM students s 
                     JOIN users u ON s.user_id = u.user_id 
                     WHERE s.student_id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute($selected_students);
            $students = $stmt->fetchAll();
            
            // ดึงสีที่ใช้งานอยู่
            $query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $colors = $stmt->fetchAll();
            
            if (empty($colors)) {
                throw new Exception('ไม่มีสีที่ใช้งานได้');
            }
            
            // แบ่งนักเรียนตามเพศถ้าเลือกแบ่งเท่าๆ กัน
            if ($balance_gender) {
                $male_students = array_filter($students, function($s) {
                    return $s['title'] === 'นาย';
                });
                $female_students = array_filter($students, function($s) {
                    return in_array($s['title'], ['นาง', 'นางสาว']);
                });
                
                shuffle($colors);
                $color_count = count($colors);
                
                // แบ่งชายเท่าๆ กัน
                if (!empty($male_students)) {
                    $male_per_color = ceil(count($male_students) / $color_count);
                    $male_shuffled = $male_students;
                    shuffle($male_shuffled);
                    
                    $male_index = 0;
                    foreach ($colors as $color) {
                        for ($i = 0; $i < $male_per_color && $male_index < count($male_shuffled); $i++) {
                            $student = $male_shuffled[$male_index];
                            assignColorToStudent($db, $student['student_id'], $color['color_id'], 
                                               $current_year['academic_year_id'], $_SESSION['admin_id'], 'สุ่มอัตโนมัติ (แบ่งตามเพศ)');
                            $male_index++;
                        }
                    }
                }
                
                // แบ่งหญิงเท่าๆ กัน
                if (!empty($female_students)) {
                    $female_per_color = ceil(count($female_students) / $color_count);
                    $female_shuffled = $female_students;
                    shuffle($female_shuffled);
                    
                    $female_index = 0;
                    foreach ($colors as $color) {
                        for ($i = 0; $i < $female_per_color && $female_index < count($female_shuffled); $i++) {
                            $student = $female_shuffled[$female_index];
                            assignColorToStudent($db, $student['student_id'], $color['color_id'], 
                                               $current_year['academic_year_id'], $_SESSION['admin_id'], 'สุ่มอัตโนมัติ (แบ่งตามเพศ)');
                            $female_index++;
                        }
                    }
                }
            } else {
                // สุ่มแบบปกติ
                shuffle($students);
                shuffle($colors);
                
                $students_per_color = ceil(count($students) / count($colors));
                $student_index = 0;
                
                foreach ($colors as $color) {
                    for ($i = 0; $i < $students_per_color && $student_index < count($students); $i++) {
                        $student = $students[$student_index];
                        assignColorToStudent($db, $student['student_id'], $color['color_id'], 
                                           $current_year['academic_year_id'], $_SESSION['admin_id'], 'สุ่มอัตโนมัติ');
                        $student_index++;
                    }
                }
            }
            
            $db->commit();
            $message = 'สุ่มสีเรียบร้อยแล้ว จำนวน ' . count($selected_students) . ' คน';
            $message_type = 'success';
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณาเลือกนักเรียน';
        $message_type = 'warning';
    }
}

// ฟังก์ชันจัดสี
function assignColorToStudent($db, $student_id, $color_id, $academic_year_id, $admin_id, $notes = '') {
    $query = "INSERT INTO student_sport_colors (student_id, color_id, academic_year_id, assigned_by, notes) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$student_id, $color_id, $academic_year_id, $admin_id, $notes]);
}

// ค้นหา
$search = $_GET['search'] ?? '';
$color_filter = $_GET['color_filter'] ?? '';
$class_filter = $_GET['class_filter'] ?? '';
$gender_filter = $_GET['gender_filter'] ?? '';

// ดึงรายชื่อนักเรียน
$where_conditions = ["s.status = 'กำลังศึกษา'"];
$params = [$current_year['academic_year_id']];

if (!empty($search)) {
    $where_conditions[] = "(s.student_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($color_filter)) {
    if ($color_filter === 'no_color') {
        $where_conditions[] = "(ssc.color_id IS NULL OR ssc.is_active = 0)";
    } else {
        $where_conditions[] = "ssc.color_id = ? AND ssc.is_active = 1";
        $params[] = $color_filter;
    }
}

if (!empty($class_filter)) {
    $where_conditions[] = "c.class_id = ?";
    $params[] = $class_filter;
}

if (!empty($gender_filter)) {
    if ($gender_filter === 'male') {
        $where_conditions[] = "s.title = 'นาย'";
    } elseif ($gender_filter === 'female') {
        $where_conditions[] = "s.title IN ('นาง', 'นางสาว')";
    }
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name,
                 c.class_id, c.level, d.department_name, c.group_number,
                 sc.color_id, sc.color_name, sc.color_code, ssc.assigned_date, ssc.notes
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          WHERE $where_clause
          ORDER BY c.level, d.department_name, c.group_number, u.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// ดึงสีทั้งหมด
$query = "SELECT sc.*, 
                 COUNT(ssc.student_id) as current_count,
                 (CASE WHEN sc.max_members IS NULL THEN 'ไม่จำกัด' 
                       ELSE CONCAT(COUNT(ssc.student_id), '/', sc.max_members, ' คน') END) as member_info
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          WHERE sc.is_active = 1 
          GROUP BY sc.color_id
          ORDER BY sc.color_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$colors = $stmt->fetchAll();

// ดึงชั้นเรียนทั้งหมด
$query = "SELECT c.*, d.department_name 
          FROM classes c 
          JOIN departments d ON c.department_id = d.department_id 
          WHERE c.academic_year_id = ? 
          ORDER BY c.level, d.department_name, c.group_number";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$classes = $stmt->fetchAll();

// สถิติสี
$query = "SELECT sc.color_name, sc.color_code, sc.max_members,
                 COUNT(ssc.student_id) as student_count,
                 COUNT(CASE WHEN s.title = 'นาย' THEN 1 END) as male_count,
                 COUNT(CASE WHEN s.title IN ('นาง', 'นางสาว') THEN 1 END) as female_count
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          LEFT JOIN students s ON ssc.student_id = s.student_id
          WHERE sc.is_active = 1
          GROUP BY sc.color_id
          ORDER BY student_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$color_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดสีให้นักเรียน - ระบบกีฬาสี</title>
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
        .student-row:hover {
            background-color: #f8f9fa;
        }
        .color-stat-card {
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .color-stat-card:hover {
            transform: translateY(-2px);
        }
        .gender-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar">
                <div class="p-3 text-white">
                    <h4><i class="fas fa-medal"></i> กีฬาสี</h4>
                    <p class="mb-0">วิทยาลัยการอาชีพปราสาท</p>
                    <hr>
                    <p class="mb-0">สวัสดี, <?php echo $_SESSION['admin_name']; ?></p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i> หน้าหลัก
                    </a>
                    <a class="nav-link" href="colors.php">
                        <i class="fas fa-palette"></i> จัดการสี
                    </a>
                    <a class="nav-link active" href="assign_colors.php">
                        <i class="fas fa-users"></i> จัดสีให้นักเรียน
                    </a>
                    <a class="nav-link" href="random_colors.php">
                        <i class="fas fa-random"></i> สุ่มสี
                    </a>
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-money-bill"></i> บันทึกการจ่ายเงิน
                    </a>
                    <a class="nav-link" href="athletes.php">
                        <i class="fas fa-running"></i> นักกีฬา
                    </a>
                    <a class="nav-link" href="competitions.php">
                        <i class="fas fa-trophy"></i> การแข่งขัน
                    </a>
                    <a class="nav-link" href="schedules.php">
                        <i class="fas fa-calendar"></i> ตารางแข่ง
                    </a>
                    <hr>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users"></i> จัดสีให้นักเรียน</h2>
                    <div>
                        <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#autoAssignModal">
                            <i class="fas fa-random"></i> สุ่มสีอัตโนมัติ
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                            <i class="fas fa-plus"></i> จัดสีรายบุคคล
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- สถิติสี -->
                <div class="row mb-4">
                    <?php foreach ($color_stats as $stat): ?>
                        <div class="col-md-2 mb-3">
                            <div class="card color-stat-card h-100" style="border-left-color: <?php echo $stat['color_code']; ?>">
                                <div class="card-body text-center p-2">
                                    <div class="color-badge mx-auto mb-2" style="width: 30px; height: 30px; background-color: <?php echo $stat['color_code']; ?>"></div>
                                    <h6 class="card-title mb-2"><?php echo $stat['color_name']; ?></h6>
                                    <div class="d-flex justify-content-center mb-2">
                                        <span class="badge bg-primary me-1"><?php echo $stat['student_count']; ?> คน</span>
                                    </div>
                                    <div class="small">
                                        <span class="gender-badge badge bg-info me-1">
                                            <i class="fas fa-mars"></i> <?php echo $stat['male_count']; ?>
                                        </span>
                                        <span class="gender-badge badge bg-danger">
                                            <i class="fas fa-venus"></i> <?php echo $stat['female_count']; ?>
                                        </span>
                                    </div>
                                    <?php if ($stat['max_members']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">สูงสุด: <?php echo $stat['max_members']; ?> คน</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
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
                                <label class="form-label">เพศ</label>
                                <select class="form-select" name="gender_filter">
                                    <option value="">ทุกเพศ</option>
                                    <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>ชาย</option>
                                    <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>หญิง</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">สี</label>
                                <select class="form-select" name="color_filter">
                                    <option value="">ทุกสี</option>
                                    <option value="no_color" <?php echo $color_filter === 'no_color' ? 'selected' : ''; ?>>ยังไม่มีสี</option>
                                    <?php foreach ($colors as $color): ?>
                                        <option value="<?php echo $color['color_id']; ?>" 
                                                <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                            <?php echo $color['color_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ชั้นเรียน</label>
                                <select class="form-select" name="class_filter">
                                    <option value="">ทุกชั้น</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" 
                                                <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['level'] . ' ' . $class['department_name'] . ' ' . $class['group_number']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                                <a href="assign_colors.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> ล้าง
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- รายชื่อนักเรียน -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> รายชื่อนักเรียน (<?php echo count($students); ?> คน)</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllStudents()">
                                <i class="fas fa-check-square"></i> เลือกทั้งหมด
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllStudents()">
                                <i class="fas fa-square"></i> ล้างการเลือก
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>รหัสนักเรียน</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>เพศ</th>
                                        <th>ชั้นเรียน</th>
                                        <th>สี</th>
                                        <th>วันที่จัดสี</th>
                                        <th>หมายเหตุ</th>
                                        <th width="120">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr class="student-row">
                                            <td>
                                                <input type="checkbox" class="student-checkbox" 
                                                       value="<?php echo $student['student_id']; ?>">
                                            </td>
                                            <td>
                                                <strong><?php echo $student['student_code']; ?></strong>
                                            </td>
                                            <td><?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $student['title'] === 'นาย' ? 'bg-info' : 'bg-danger'; ?>">
                                                    <i class="fas <?php echo $student['title'] === 'นาย' ? 'fa-mars' : 'fa-venus'; ?>"></i>
                                                    <?php echo $student['title'] === 'นาย' ? 'ชาย' : 'หญิง'; ?>
                                                </span>
                                            </td>
                                            <td>
                                            <small><?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?></small>
                                           </td>
                                           <td>
                                               <?php if ($student['color_name']): ?>
                                                   <span class="color-badge" style="background-color: <?php echo $student['color_code']; ?>"></span>
                                                   <strong><?php echo $student['color_name']; ?></strong>
                                               <?php else: ?>
                                                   <span class="text-muted">ยังไม่มีสี</span>
                                               <?php endif; ?>
                                           </td>
                                           <td>
                                               <?php echo $student['assigned_date'] ? date('d/m/Y H:i', strtotime($student['assigned_date'])) : '-'; ?>
                                           </td>
                                           <td>
                                               <small><?php echo $student['notes'] ? htmlspecialchars($student['notes']) : '-'; ?></small>
                                           </td>
                                           <td>
                                               <div class="btn-group" role="group">
                                                   <button type="button" class="btn btn-sm btn-outline-primary" 
                                                           onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)"
                                                           title="แก้ไข">
                                                       <i class="fas fa-edit"></i>
                                                   </button>
                                                   
                                                   <?php if ($student['color_name']): ?>
                                                       <a href="?remove=<?php echo $student['student_id']; ?>" 
                                                          class="btn btn-sm btn-outline-danger"
                                                          onclick="return confirm('ต้องการลบสี?')"
                                                          title="ลบสี">
                                                           <i class="fas fa-trash"></i>
                                                       </a>
                                                   <?php endif; ?>
                                               </div>
                                           </td>
                                       </tr>
                                   <?php endforeach; ?>
                                   
                                   <?php if (empty($students)): ?>
                                       <tr>
                                           <td colspan="9" class="text-center py-4">
                                               <i class="fas fa-users fa-3x text-muted"></i>
                                               <p class="mt-2 text-muted">ไม่พบข้อมูลนักเรียน</p>
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
   
   <!-- Assign Color Modal -->
   <div class="modal fade" id="assignModal" tabindex="-1">
       <div class="modal-dialog">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title"><i class="fas fa-palette"></i> จัดสีให้นักเรียน</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <form method="POST" id="assignForm">
                   <div class="modal-body">
                       <div class="mb-3">
                           <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                           <select class="form-select" name="student_id" id="studentSelect" required>
                               <option value="">-- เลือกนักเรียน --</option>
                               <?php foreach ($students as $student): ?>
                                   <option value="<?php echo $student['student_id']; ?>" 
                                           data-current-color="<?php echo $student['color_name'] ?: 'ไม่มี'; ?>">
                                       <?php echo $student['student_code'] . ' - ' . $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                       <?php if ($student['color_name']): ?>
                                           (สีปัจจุบัน: <?php echo $student['color_name']; ?>)
                                       <?php endif; ?>
                                   </option>
                               <?php endforeach; ?>
                           </select>
                           <div id="currentColorInfo" class="form-text"></div>
                       </div>
                       
                       <div class="mb-3">
                           <label class="form-label">เลือกสี <span class="text-danger">*</span></label>
                           <select class="form-select" name="color_id" required>
                               <option value="">-- เลือกสี --</option>
                               <?php foreach ($colors as $color): ?>
                                   <option value="<?php echo $color['color_id']; ?>" 
                                           data-color="<?php echo $color['color_code']; ?>"
                                           data-members="<?php echo $color['member_info']; ?>">
                                       <?php echo $color['color_name'] . ' (' . $color['member_info'] . ')'; ?>
                                   </option>
                               <?php endforeach; ?>
                           </select>
                           <div class="form-text">
                               <div id="colorPreview" class="d-none mt-2">
                                   <span id="colorBadge" class="color-badge me-2"></span>
                                   <span id="colorInfo"></span>
                               </div>
                           </div>
                       </div>
                       
                       <div class="mb-3">
                           <label class="form-label">หมายเหตุ</label>
                           <textarea class="form-control" name="notes" rows="3" 
                                     placeholder="บันทึกเหตุผลหรือหมายเหตุพิเศษ"></textarea>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                       <button type="submit" name="assign_color" class="btn btn-primary">จัดสี</button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <!-- Auto Assign Modal -->
   <div class="modal fade" id="autoAssignModal" tabindex="-1">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title"><i class="fas fa-random"></i> สุ่มสีอัตโนมัติ</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <form method="POST" id="autoAssignForm">
                   <div class="modal-body">
                       <div class="row mb-3">
                           <div class="col-md-6">
                               <div class="form-check">
                                   <input class="form-check-input" type="checkbox" name="balance_gender" 
                                          id="balance_gender" checked>
                                   <label class="form-check-label" for="balance_gender">
                                       <strong>แบ่งชาย-หญิงเท่าๆ กัน</strong>
                                       <div class="form-text">จัดสรรให้จำนวนชาย-หญิงในแต่ละสีใกล้เคียงกัน</div>
                                   </label>
                               </div>
                           </div>
                           <div class="col-md-6">
                               <div class="text-end">
                                   <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllInModal()">
                                       <i class="fas fa-check-square"></i> เลือกทั้งหมด
                                   </button>
                                   <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllInModal()">
                                       <i class="fas fa-square"></i> ล้างการเลือก
                                   </button>
                               </div>
                           </div>
                       </div>
                       
                       <div class="alert alert-warning">
                           <i class="fas fa-exclamation-triangle"></i>
                           <strong>คำเตือน:</strong> การสุ่มสีจะลบสีเดิมของนักเรียนที่เลือกทั้งหมด
                       </div>
                       
                       <div class="mb-3">
                           <label class="form-label">เลือกนักเรียนที่ต้องการสุ่มสี:</label>
                           <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                               <?php 
                               $current_class = '';
                               foreach ($students as $student): 
                                   $class_info = $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number'];
                                   if ($current_class !== $class_info):
                                       if ($current_class !== '') echo '</div>';
                                       $current_class = $class_info;
                                       echo '<div class="mb-3">';
                                       echo '<h6 class="text-primary border-bottom pb-1">' . $class_info . '</h6>';
                                   endif;
                               ?>
                                   <div class="form-check">
                                       <input class="form-check-input auto-student-checkbox" type="checkbox" 
                                              name="selected_students[]" value="<?php echo $student['student_id']; ?>"
                                              id="auto_student_<?php echo $student['student_id']; ?>">
                                       <label class="form-check-label" for="auto_student_<?php echo $student['student_id']; ?>">
                                           <div class="d-flex justify-content-between align-items-center">
                                               <div>
                                                   <strong><?php echo $student['student_code']; ?></strong>
                                                   <?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                                   <span class="badge <?php echo $student['title'] === 'นาย' ? 'bg-info' : 'bg-danger'; ?> ms-2">
                                                       <i class="fas <?php echo $student['title'] === 'นาย' ? 'fa-mars' : 'fa-venus'; ?>"></i>
                                                   </span>
                                               </div>
                                               <?php if ($student['color_name']): ?>
                                                   <div>
                                                       <span class="color-badge" style="background-color: <?php echo $student['color_code']; ?>"></span>
                                                       <small><?php echo $student['color_name']; ?></small>
                                                   </div>
                                               <?php endif; ?>
                                           </div>
                                       </label>
                                   </div>
                               <?php 
                               endforeach; 
                               if ($current_class !== '') echo '</div>';
                               ?>
                           </div>
                       </div>
                       
                       <div id="selectionSummary" class="alert alert-info d-none">
                           <i class="fas fa-info-circle"></i>
                           <span id="summaryText"></span>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                       <button type="submit" name="auto_assign" class="btn btn-warning" 
                               onclick="return confirm('ต้องการสุ่มสีใหม่? การดำเนินการนี้จะลบสีเดิมของนักเรียนที่เลือกทั้งหมด')">
                           <i class="fas fa-random"></i> สุ่มสี
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
       // ฟังก์ชันจัดการการเลือกนักเรียน
       function toggleSelectAll() {
           const selectAll = document.getElementById('selectAll');
           const checkboxes = document.querySelectorAll('.student-checkbox');
           checkboxes.forEach(checkbox => {
               checkbox.checked = selectAll.checked;
           });
       }
       
       function selectAllStudents() {
           const checkboxes = document.querySelectorAll('.student-checkbox');
           checkboxes.forEach(checkbox => {
               checkbox.checked = true;
           });
           document.getElementById('selectAll').checked = true;
       }
       
       function clearAllStudents() {
           const checkboxes = document.querySelectorAll('.student-checkbox');
           checkboxes.forEach(checkbox => {
               checkbox.checked = false;
           });
           document.getElementById('selectAll').checked = false;
       }
       
       function selectAllInModal() {
           const checkboxes = document.querySelectorAll('.auto-student-checkbox');
           checkboxes.forEach(checkbox => {
               checkbox.checked = true;
           });
           updateSelectionSummary();
       }
       
       function clearAllInModal() {
           const checkboxes = document.querySelectorAll('.auto-student-checkbox');
           checkboxes.forEach(checkbox => {
               checkbox.checked = false;
           });
           updateSelectionSummary();
       }
       
       // อัปเดตสรุปการเลือก
       function updateSelectionSummary() {
           const checkboxes = document.querySelectorAll('.auto-student-checkbox:checked');
           const summary = document.getElementById('selectionSummary');
           const summaryText = document.getElementById('summaryText');
           
           if (checkboxes.length > 0) {
               // นับจำนวนชาย-หญิง
               let maleCount = 0;
               let femaleCount = 0;
               
               checkboxes.forEach(checkbox => {
                   const label = checkbox.nextElementSibling;
                   const genderBadge = label.querySelector('.badge');
                   if (genderBadge.classList.contains('bg-info')) {
                       maleCount++;
                   } else {
                       femaleCount++;
                   }
               });
               
               summaryText.innerHTML = `เลือกแล้ว ${checkboxes.length} คน (ชาย: ${maleCount} คน, หญิง: ${femaleCount} คน)`;
               summary.classList.remove('d-none');
           } else {
               summary.classList.add('d-none');
           }
       }
       
       // ฟังก์ชันแก้ไขนักเรียน
       function editStudent(student) {
           const modal = new bootstrap.Modal(document.getElementById('assignModal'));
           const form = document.getElementById('assignForm');
           
           // ตั้งค่าข้อมูลในฟอร์ม
           form.querySelector('select[name="student_id"]').value = student.student_id;
           form.querySelector('select[name="color_id"]').value = student.color_id || '';
           form.querySelector('textarea[name="notes"]').value = student.notes || '';
           
           // อัปเดตข้อมูลสีปัจจุบัน
           updateCurrentColorInfo();
           updateColorPreview();
           
           modal.show();
       }
       
       // อัปเดตข้อมูลสีปัจจุบัน
       function updateCurrentColorInfo() {
           const studentSelect = document.getElementById('studentSelect');
           const currentColorInfo = document.getElementById('currentColorInfo');
           const selectedOption = studentSelect.options[studentSelect.selectedIndex];
           
           if (selectedOption && selectedOption.getAttribute('data-current-color')) {
               const currentColor = selectedOption.getAttribute('data-current-color');
               currentColorInfo.innerHTML = currentColor !== 'ไม่มี' ? 
                   `<i class="fas fa-info-circle text-info"></i> สีปัจจุบัน: <strong>${currentColor}</strong>` : 
                   '<i class="fas fa-exclamation-circle text-warning"></i> ยังไม่มีสี';
           } else {
               currentColorInfo.innerHTML = '';
           }
       }
       
       // อัปเดตตัวอย่างสี
       function updateColorPreview() {
           const colorSelect = document.querySelector('select[name="color_id"]');
           const colorPreview = document.getElementById('colorPreview');
           const colorBadge = document.getElementById('colorBadge');
           const colorInfo = document.getElementById('colorInfo');
           const selectedOption = colorSelect.options[colorSelect.selectedIndex];
           
           if (selectedOption && selectedOption.value) {
               const colorCode = selectedOption.getAttribute('data-color');
               const members = selectedOption.getAttribute('data-members');
               
               colorBadge.style.backgroundColor = colorCode;
               colorInfo.textContent = `สมาชิก: ${members}`;
               colorPreview.classList.remove('d-none');
           } else {
               colorPreview.classList.add('d-none');
           }
       }
       
       // Event Listeners
       document.addEventListener('DOMContentLoaded', function() {
           // ตัวอย่างสี
           document.querySelector('select[name="color_id"]').addEventListener('change', updateColorPreview);
           document.getElementById('studentSelect').addEventListener('change', updateCurrentColorInfo);
           
           // สรุปการเลือกในโมดอลสุ่มสี
           document.querySelectorAll('.auto-student-checkbox').forEach(checkbox => {
               checkbox.addEventListener('change', updateSelectionSummary);
           });
           
           // รีเซ็ตฟอร์มเมื่อปิดโมดอล
           document.getElementById('assignModal').addEventListener('hidden.bs.modal', function() {
               document.getElementById('assignForm').reset();
               document.getElementById('currentColorInfo').innerHTML = '';
               document.getElementById('colorPreview').classList.add('d-none');
           });
           
           document.getElementById('autoAssignModal').addEventListener('hidden.bs.modal', function() {
               document.getElementById('autoAssignForm').reset();
               clearAllInModal();
           });
       });
   </script>
</body>
</html>