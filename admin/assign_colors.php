<?php
// admin/assign_colors.php
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

// จัดสีให้นักเรียน
if (isset($_POST['assign_color'])) {
    $student_id = $_POST['student_id'];
    $color_id = $_POST['color_id'];
    $notes = trim($_POST['notes']);
    
    if (!empty($student_id) && !empty($color_id)) {
        try {
            // ลบสีเดิม
            $query = "DELETE FROM student_sport_colors 
                     WHERE student_id = ? AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $current_year['academic_year_id']]);
            
            // เพิ่มสีใหม่
            $query = "INSERT INTO student_sport_colors 
                     (student_id, color_id, academic_year_id, assigned_by, notes) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$student_id, $color_id, $current_year['academic_year_id'], $_SESSION['admin_id'], $notes]);
            
            $message = 'จัดสีเรียบร้อยแล้ว';
            $message_type = 'success';
            
        } catch (Exception $e) {
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
    
    $query = "DELETE FROM student_sport_colors 
             WHERE student_id = ? AND academic_year_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$student_id, $current_year['academic_year_id']])) {
        $message = 'ลบสีเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// ค้นหา
$search = $_GET['search'] ?? '';
$color_filter = $_GET['color_filter'] ?? '';
$class_filter = $_GET['class_filter'] ?? '';

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
        $where_conditions[] = "ssc.color_id IS NULL";
    } else {
        $where_conditions[] = "ssc.color_id = ?";
        $params[] = $color_filter;
    }
}

if (!empty($class_filter)) {
    $where_conditions[] = "c.class_id = ?";
    $params[] = $class_filter;
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
                   AND ssc.academic_year_id = ?
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          WHERE $where_clause
          ORDER BY c.level, d.department_name, c.group_number, u.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// ดึงสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
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
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                        <i class="fas fa-plus"></i> จัดสีรายบุคคล
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- ฟิลเตอร์ -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="รหัส/ชื่อนักเรียน" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">กรองตามสี</label>
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
                                <label class="form-label">กรองตามชั้น</label>
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
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>รหัสนักเรียน</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>ชั้นเรียน</th>
                                        <th>สี</th>
                                        <th>วันที่จัดสี</th>
                                        <th>หมายเหตุ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['student_code']; ?></td>
                                            <td><?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                            <td><?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?></td>
                                            <td>
                                                <?php if ($student['color_name']): ?>
                                                    <span class="color-badge" style="background-color: <?php echo $student['color_code']; ?>"></span>
                                                    <?php echo $student['color_name']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">ยังไม่มีสี</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $student['assigned_date'] ? date('d/m/Y', strtotime($student['assigned_date'])) : '-'; ?>
                                            </td>
                                            <td><?php echo $student['notes'] ?: '-'; ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <?php if ($student['color_name']): ?>
                                                        <a href="?remove=<?php echo $student['student_id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('ต้องการลบสี?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
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
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                            <select class="form-select" name="student_id" required>
                                <option value="">-- เลือกนักเรียน --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['student_id']; ?>">
                                        <?php echo $student['student_code'] . ' - ' . $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เลือกสี <span class="text-danger">*</span></label>
                            <select class="form-select" name="color_id" required>
                                <option value="">-- เลือกสี --</option>
                                <?php foreach ($colors as $color): ?>
                                    <option value="<?php echo $color['color_id']; ?>">
                                        <?php echo $color['color_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStudent(student) {
            // เปิด modal และใส่ข้อมูล
            const modal = new bootstrap.Modal(document.getElementById('assignModal'));
            document.querySelector('select[name="student_id"]').value = student.student_id;
            document.querySelector('select[name="color_id"]').value = student.color_id || '';
            document.querySelector('textarea[name="notes"]').value = student.notes || '';
            modal.show();
        }
    </script>
</body>
</html>