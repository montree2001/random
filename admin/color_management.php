<?php
// admin/color_management.php - ระบบจัดการสีกีฬา
session_start();
require_once '../config/database.php';

// ตรวจสอบการเข้าสู่ระบบ Admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$query_year = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$stmt_year = $db->prepare($query_year);
$stmt_year->execute();
$current_year = $stmt_year->fetch();

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_color':
                    $color_name = trim($_POST['color_name']);
                    $color_code = trim($_POST['color_code']);
                    $description = trim($_POST['description']);
                    $max_members = !empty($_POST['max_members']) ? (int)$_POST['max_members'] : null;
                    
                    $query = "INSERT INTO sport_colors (color_name, color_code, description, max_members) 
                             VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$color_name, $color_code, $description, $max_members]);
                    
                    $message = 'เพิ่มสีใหม่เรียบร้อยแล้ว';
                    $message_type = 'success';
                    break;
                    
                case 'edit_color':
                    $color_id = (int)$_POST['color_id'];
                    $color_name = trim($_POST['color_name']);
                    $color_code = trim($_POST['color_code']);
                    $description = trim($_POST['description']);
                    $max_members = !empty($_POST['max_members']) ? (int)$_POST['max_members'] : null;
                    
                    $query = "UPDATE sport_colors 
                             SET color_name = ?, color_code = ?, description = ?, max_members = ?
                             WHERE color_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$color_name, $color_code, $description, $max_members, $color_id]);
                    
                    $message = 'แก้ไขข้อมูลสีเรียบร้อยแล้ว';
                    $message_type = 'success';
                    break;
                    
                case 'toggle_color':
                    $color_id = (int)$_POST['color_id'];
                    $is_active = (int)$_POST['is_active'];
                    
                    $query = "UPDATE sport_colors SET is_active = ? WHERE color_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$is_active, $color_id]);
                    
                    $message = $is_active ? 'เปิดใช้งานสีแล้ว' : 'ปิดใช้งานสีแล้ว';
                    $message_type = 'success';
                    break;
                    
                case 'random_colors':
                    // การสุ่มสีแบบสมดุล
                    $balance_gender = isset($_POST['balance_gender']);
                    $balance_department = isset($_POST['balance_department']);
                    $balance_level = isset($_POST['balance_level']);
                    $selected_students = $_POST['selected_students'] ?? [];
                    
                    if (!empty($selected_students)) {
                        $result = randomAssignColors($db, $selected_students, $current_year['academic_year_id'], 
                                                   $balance_gender, $balance_department, $balance_level, $_SESSION['admin_id']);
                        $message = $result['message'];
                        $message_type = $result['type'];
                    } else {
                        $message = 'กรุณาเลือกนักเรียนที่ต้องการสุ่มสี';
                        $message_type = 'warning';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// ดึงข้อมูลสีทั้งหมด
$query = "SELECT sc.*, 
                 COUNT(ssc.student_id) as current_count,
                 ROUND(AVG(CASE WHEN s.title = 'นาย' THEN 1 ELSE 0 END) * 100, 1) as male_percentage
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          LEFT JOIN students s ON ssc.student_id = s.student_id
          GROUP BY sc.color_id
          ORDER BY sc.color_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$colors = $stmt->fetchAll();

// ดึงสถิติรวม
$query = "SELECT 
            COUNT(DISTINCT s.student_id) as total_students,
            COUNT(DISTINCT CASE WHEN ssc.student_id IS NOT NULL THEN s.student_id END) as assigned_students,
            COUNT(DISTINCT sc.color_id) as active_colors
          FROM students s
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id AND sc.is_active = 1
          WHERE s.status = 'กำลังศึกษา'";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$stats = $stmt->fetch();

// ฟังก์ชันสุ่มสีแบบสมดุล
function randomAssignColors($db, $student_ids, $academic_year_id, $balance_gender, $balance_department, $balance_level, $admin_id) {
    try {
        $db->beginTransaction();
        
        // ดึงข้อมูลนักเรียนที่เลือก
        $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
        $query = "SELECT s.student_id, s.student_code, u.title, u.first_name, u.last_name,
                         c.level, d.department_name, c.group_number
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN classes c ON s.current_class_id = c.class_id
                  LEFT JOIN departments d ON c.department_id = d.department_id
                  WHERE s.student_id IN ($placeholders) AND s.status = 'กำลังศึกษา'";
        $stmt = $db->prepare($query);
        $stmt->execute($student_ids);
        $students = $stmt->fetchAll();
        
        // ดึงสีที่ใช้งานได้
        $query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $colors = $stmt->fetchAll();
        
        if (empty($colors)) {
            throw new Exception('ไม่มีสีที่ใช้งานได้');
        }
        
        // ลบการกำหนดสีเดิม
        $query = "UPDATE student_sport_colors 
                 SET is_active = 0 
                 WHERE student_id IN ($placeholders) AND academic_year_id = ?";
        $params = array_merge($student_ids, [$academic_year_id]);
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // จัดกลุ่มนักเรียนตามเกณฑ์
        $groups = organizeStudents($students, $balance_gender, $balance_department, $balance_level);
        
        // แจกสีให้แต่ละกลุ่ม
        $assigned_count = 0;
        foreach ($groups as $group) {
            $assigned_count += assignColorsToGroup($db, $group, $colors, $academic_year_id, $admin_id);
        }
        
        $db->commit();
        return [
            'message' => "สุ่มสีเรียบร้อยแล้ว จำนวน {$assigned_count} คน",
            'type' => 'success'
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return [
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            'type' => 'danger'
        ];
    }
}

function organizeStudents($students, $balance_gender, $balance_department, $balance_level) {
    $groups = [];
    
    if ($balance_department && $balance_level) {
        // จัดกลุ่มตามแผนก + ระดับชั้น
        foreach ($students as $student) {
            $key = $student['department_name'] . '_' . $student['level'];
            $groups[$key][] = $student;
        }
    } elseif ($balance_department) {
        // จัดกลุ่มตามแผนก
        foreach ($students as $student) {
            $groups[$student['department_name']][] = $student;
        }
    } elseif ($balance_level) {
        // จัดกลุ่มตามระดับชั้น
        foreach ($students as $student) {
            $groups[$student['level']][] = $student;
        }
    } else {
        // ไม่จัดกลุ่ม
        $groups['all'] = $students;
    }
    
    // หากต้องการสมดุลเพศ ให้แยกกลุ่มเพศภายในแต่ละกลุ่ม
    if ($balance_gender) {
        $new_groups = [];
        foreach ($groups as $key => $group) {
            $male_students = array_filter($group, function($s) { return $s['title'] === 'นาย'; });
            $female_students = array_filter($group, function($s) { return in_array($s['title'], ['นาง', 'นางสาว']); });
            
            if (!empty($male_students)) {
                $new_groups[$key . '_male'] = array_values($male_students);
            }
            if (!empty($female_students)) {
                $new_groups[$key . '_female'] = array_values($female_students);
            }
        }
        $groups = $new_groups;
    }
    
    return $groups;
}

function assignColorsToGroup($db, $students, $colors, $academic_year_id, $admin_id) {
    $color_count = count($colors);
    $assigned_count = 0;
    
    // สุ่มลำดับนักเรียน
    shuffle($students);
    
    foreach ($students as $index => $student) {
        $color = $colors[$index % $color_count];
        
        $query = "INSERT INTO student_sport_colors 
                 (student_id, color_id, academic_year_id, assigned_by, assignment_method) 
                 VALUES (?, ?, ?, ?, 'random')";
        $stmt = $db->prepare($query);
        $stmt->execute([$student['student_id'], $color['color_id'], $academic_year_id, $admin_id]);
        
        $assigned_count++;
    }
    
    return $assigned_count;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสีกีฬา</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <style>
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #ddd;
            display: inline-block;
            margin-right: 10px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .color-card {
            transition: transform 0.2s;
            border-radius: 10px;
        }
        .color-card:hover {
            transform: translateY(-5px);
        }
        .select2-container {
            width: 100% !important;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-palette"></i> ระบบจัดการสีกีฬา</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> หน้าหลัก</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- แสดงข้อความ -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- สถิติ -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?= number_format($stats['total_students']) ?></h4>
                        <p class="mb-0">นักเรียนทั้งหมด</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h4><?= number_format($stats['assigned_students']) ?></h4>
                        <p class="mb-0">ได้สีแล้ว</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-user-times fa-2x mb-2"></i>
                        <h4><?= number_format($stats['total_students'] - $stats['assigned_students']) ?></h4>
                        <p class="mb-0">ยังไม่ได้สี</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-palette fa-2x mb-2"></i>
                        <h4><?= number_format($stats['active_colors']) ?></h4>
                        <p class="mb-0">สีที่ใช้งาน</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- แท็บ -->
        <ul class="nav nav-tabs" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="colors-tab" data-bs-toggle="tab" data-bs-target="#colors" type="button">
                    <i class="fas fa-palette"></i> จัดการสี
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assign" type="button">
                    <i class="fas fa-random"></i> สุ่มสี
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button">
                    <i class="fas fa-user-edit"></i> เปลี่ยนสีรายบุคคล
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="statistics-tab" data-bs-toggle="tab" data-bs-target="#statistics" type="button">
                    <i class="fas fa-chart-bar"></i> สถิติ
                </button>
            </li>
        </ul>

        <div class="tab-content" id="mainTabsContent">
            <!-- แท็บจัดการสี -->
            <div class="tab-pane fade show active" id="colors" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-palette"></i> จัดการสี</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#colorModal">
                            <i class="fas fa-plus"></i> เพิ่มสีใหม่
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($colors as $color): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card color-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="color-preview" style="background-color: <?= htmlspecialchars($color['color_code']) ?>"></div>
                                            <h6 class="mb-0"><?= htmlspecialchars($color['color_name']) ?></h6>
                                        </div>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($color['description']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?= $color['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $color['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน' ?>
                                            </span>
                                            <span class="text-muted small">
                                                <?= number_format($color['current_count']) ?> คน
                                                <?php if ($color['max_members']): ?>
                                                / <?= number_format($color['max_members']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                    onclick="editColor(<?= htmlspecialchars(json_encode($color), ENT_QUOTES) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?= $color['is_active'] ? 'warning' : 'success' ?>"
                                                    onclick="toggleColor(<?= $color['color_id'] ?>, <?= $color['is_active'] ? 0 : 1 ?>)">
                                                <i class="fas fa-<?= $color['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- แท็บสุ่มสี -->
            <div class="tab-pane fade" id="assign" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-random"></i> สุ่มสีให้นักเรียน</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="randomColorForm">
                            <input type="hidden" name="action" value="random_colors">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">เลือกนักเรียนที่ต้องการสุ่มสี</label>
                                    <select name="selected_students[]" id="studentSelect" class="form-select" multiple>
                                        <!-- จะถูกโหลดด้วย AJAX -->
                                    </select>
                                    <div class="form-text">ใช้ Ctrl+Click เพื่อเลือกหลายคน</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ตัวเลือกการสุ่ม</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="balance_gender" id="balanceGender" checked>
                                        <label class="form-check-label" for="balanceGender">
                                            แบ่งสีตามเพศให้สมดุล
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="balance_department" id="balanceDepartment" checked>
                                        <label class="form-check-label" for="balanceDepartment">
                                            แบ่งสีตามแผนกให้สมดุล
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="balance_level" id="balanceLevel" checked>
                                        <label class="form-check-label" for="balanceLevel">
                                            แบ่งสีตามระดับชั้นให้สมดุล
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-random"></i> สุ่มสี
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไขสี -->
    <div class="modal fade" id="colorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="colorModalTitle">เพิ่มสีใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="colorForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="colorAction" value="add_color">
                        <input type="hidden" name="color_id" id="colorId">
                        
                        <div class="mb-3">
                            <label for="colorName" class="form-label">ชื่อสี *</label>
                            <input type="text" class="form-control" name="color_name" id="colorName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="colorCode" class="form-label">รหัสสี *</label>
                            <input type="color" class="form-control" name="color_code" id="colorCode" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="colorDescription" class="form-label">คำอธิบาย</label>
                            <textarea class="form-control" name="description" id="colorDescription" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="maxMembers" class="form-label">จำนวนสมาชิกสูงสุด</label>
                            <input type="number" class="form-control" name="max_members" id="maxMembers" min="1">
                            <div class="form-text">เว้นว่างหากไม่จำกัด</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // เริ่มต้น Select2 สำหรับเลือกนักเรียน
            initStudentSelect();
        });

        function initStudentSelect() {
            $('#studentSelect').select2({
                theme: 'bootstrap-5',
                placeholder: 'ค้นหานักเรียน...',
                allowClear: true,
                ajax: {
                    url: 'ajax/search_students.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            exclude_assigned: true,
                            academic_year_id: <?= $current_year['academic_year_id'] ?>
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function(student) {
                                return {
                                    id: student.student_id,
                                    text: student.student_code + ' - ' + student.full_name + 
                                          ' (' + student.level + ' ' + student.department_name + ')'
                                };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
        }

        function editColor(color) {
            $('#colorModalTitle').text('แก้ไขสี');
            $('#colorAction').val('edit_color');
            $('#colorId').val(color.color_id);
            $('#colorName').val(color.color_name);
            $('#colorCode').val(color.color_code);
            $('#colorDescription').val(color.description);
            $('#maxMembers').val(color.max_members);
            $('#colorModal').modal('show');
        }

        function toggleColor(colorId, isActive) {
            if (confirm('ต้องการ' + (isActive ? 'เปิด' : 'ปิด') + 'ใช้งานสีนี้หรือไม่?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_color">
                    <input type="hidden" name="color_id" value="${colorId}">
                    <input type="hidden" name="is_active" value="${isActive}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reset modal เมื่อปิด
        $('#colorModal').on('hidden.bs.modal', function () {
            $('#colorModalTitle').text('เพิ่มสีใหม่');
            $('#colorAction').val('add_color');
            $('#colorForm')[0].reset();
        });
    </script>
</body>
</html>