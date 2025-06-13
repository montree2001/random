<?php
// admin/random_colors.php
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

// สุ่มสี
if (isset($_POST['randomize_colors'])) {
    $selected_students = $_POST['selected_students'] ?? [];
    $balance_gender = isset($_POST['balance_gender']);
    
    if (!empty($selected_students)) {
        try {
            $db->beginTransaction();
            
            // ล้างสีเดิมของนักเรียนที่เลือก
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            $query = "DELETE FROM student_sport_colors 
                     WHERE student_id IN ($placeholders) 
                     AND academic_year_id = ?";
            $stmt = $db->prepare($query);
            $params = array_merge($selected_students, [$current_year['academic_year_id']]);
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
            $query = "SELECT * FROM sport_colors WHERE is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $colors = $stmt->fetchAll();
            
            if (empty($colors)) {
                throw new Exception('ไม่มีสีที่ใช้งานได้');
            }
            
            // แบ่งนักเรียนตามเพศ
            if ($balance_gender) {
                $male_students = array_filter($students, function($s) {
                    return $s['title'] === 'นาย';
                });
                $female_students = array_filter($students, function($s) {
                    return $s['title'] === 'นางสาว';
                });
                
                // สุ่มสีแยกตามเพศ
                shuffle($colors);
                $color_count = count($colors);
                
                // แบ่งชายเท่าๆ กัน
                $male_per_color = ceil(count($male_students) / $color_count);
                $male_shuffled = $male_students;
                shuffle($male_shuffled);
                
                // แบ่งหญิงเท่าๆ กัน
                $female_per_color = ceil(count($female_students) / $color_count);
                $female_shuffled = $female_students;
                shuffle($female_shuffled);
                
                $male_index = 0;
                $female_index = 0;
                
                foreach ($colors as $color_index => $color) {
                    // จัดสีให้ชาย
                    for ($i = 0; $i < $male_per_color && $male_index < count($male_shuffled); $i++) {
                        $student = $male_shuffled[$male_index];
                        $this->assignColor($db, $student['student_id'], $color['color_id'], $current_year['academic_year_id'], $_SESSION['admin_id']);
                        $male_index++;
                    }
                    
                    // จัดสีให้หญิง
                    for ($i = 0; $i < $female_per_color && $female_index < count($female_shuffled); $i++) {
                        $student = $female_shuffled[$female_index];
                        $this->assignColor($db, $student['student_id'], $color['color_id'], $current_year['academic_year_id'], $_SESSION['admin_id']);
                        $female_index++;
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
                        $this->assignColor($db, $student['student_id'], $color['color_id'], $current_year['academic_year_id'], $_SESSION['admin_id']);
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
function assignColor($db, $student_id, $color_id, $academic_year_id, $admin_id) {
    $query = "INSERT INTO student_sport_colors (student_id, color_id, academic_year_id, assigned_by) 
              VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$student_id, $color_id, $academic_year_id, $admin_id]);
}

// ดึงรายชื่อนักเรียน
$query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number,
                 sc.color_name, sc.color_code
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = ?
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          WHERE s.status = 'กำลังศึกษา'
          ORDER BY c.level, d.department_name, c.group_number, u.first_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$students = $stmt->fetchAll();

// สถิติสี
$query = "SELECT sc.color_name, sc.color_code, COUNT(ssc.student_id) as student_count
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ?
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
    <title>สุ่มสี - ระบบกีฬาสี</title>
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
        .student-card {
            border-left: 4px solid #ddd;
            transition: all 0.3s;
        }
        .student-card.has-color {
            border-left-width: 6px;
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
                    <h2><i class="fas fa-random"></i> สุ่มสี</h2>
                    <div class="text-muted">
                        ปีการศึกษา <?php echo $current_year['year']; ?> ภาคเรียนที่ <?php echo $current_year['semester']; ?>
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
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> สถิติการแบ่งสี</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($color_stats as $stat): ?>
                                        <div class="col-md-2 text-center mb-3">
                                            <div class="color-badge mx-auto" style="width: 40px; height: 40px; background-color: <?php echo $stat['color_code']; ?>"></div>
                                            <h6><?php echo $stat['color_name']; ?></h6>
                                            <span class="badge bg-primary"><?php echo $stat['student_count']; ?> คน</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ฟอร์มสุ่มสี -->
                <form method="POST" id="randomForm">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-dice"></i> สุ่มสี</h5>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="balance_gender" id="balance_gender" checked>
                                    <label class="form-check-label" for="balance_gender">
                                        แบ่งชาย-หญิงเท่าๆ กัน
                                    </label>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAll()">
                                    <i class="fas fa-check-square"></i> เลือกทั้งหมด
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAll()">
                                    <i class="fas fa-square"></i> ล้างการเลือก
                                </button>
                                <button type="submit" name="randomize_colors" class="btn btn-warning btn-sm" 
                                        onclick="return confirm('ต้องการสุ่มสีใหม่? การดำเนินการนี้จะลบสีเดิมทั้งหมด')">
                                    <i class="fas fa-random"></i> สุ่มสี
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($students as $student): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="student-card card p-2 <?php echo $student['color_name'] ? 'has-color' : ''; ?>" 
                                             style="<?php echo $student['color_code'] ? 'border-left-color: ' . $student['color_code'] : ''; ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="selected_students[]" 
                                                       value="<?php echo $student['student_id']; ?>"
                                                       id="student_<?php echo $student['student_id']; ?>">
                                                <label class="form-check-label w-100" for="student_<?php echo $student['student_id']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo $student['student_code']; ?></strong>
                                                            <?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?>
                                                            </small>
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
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_students[]"]');
            checkboxes.forEach(cb => cb.checked = true);
        }
        
        function clearAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_students[]"]');
            checkboxes.forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>