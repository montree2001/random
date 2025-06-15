<?php
// student_register_athlete.php - หน้าลงทะเบียนนักกีฬาสำหรับนักเรียน (ไม่ต้อง login)
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
/* แสดงผล Error */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$query_year = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$stmt_year = $db->prepare($query_year);
$stmt_year->execute();
$current_year = $stmt_year->fetch();

// ดึงข้อมูลประเภทกีฬาที่เปิดใช้งาน
$query_sports = "SELECT * FROM sports_categories WHERE is_active = 1 ORDER BY category_name";
$stmt_sports = $db->prepare($query_sports);
$stmt_sports->execute();
$sports_categories = $stmt_sports->fetchAll();

// ดึงข้อมูลแผนกวิชา
$query_dept = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$stmt_dept = $db->prepare($query_dept);
$stmt_dept->execute();
$departments = $stmt_dept->fetchAll();

// ดึงข้อมูลระดับชั้น
$query_level = "SELECT * FROM education_levels WHERE is_active = 1 ORDER BY level_name";
$stmt_level = $db->prepare($query_level);
$stmt_level->execute();
$education_levels = $stmt_level->fetchAll();

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student_code = trim($_POST['student_code']);
        $sport_type = trim($_POST['sport_type']);
        $position = trim($_POST['position'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // ตรวจสอบรหัสนักเรียน
        $query_check = "SELECT s.*, sc.color_name, sc.color_code 
                       FROM students s 
                       LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id AND ssc.is_active = 1
                       LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                       WHERE s.student_code = ? AND s.is_active = 1";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->execute([$student_code]);
        $student = $stmt_check->fetch();
        
        if (!$student) {
            throw new Exception('ไม่พบรหัสนักเรียนในระบบ');
        }
        
        // ตรวจสอบว่าลงทะเบียนกีฬานี้แล้วหรือยัง
        $query_exist = "SELECT * FROM sport_athletes 
                       WHERE student_id = ? AND sport_type = ? AND academic_year_id = ?";
        $stmt_exist = $db->prepare($query_exist);
        $stmt_exist->execute([$student['student_id'], $sport_type, $current_year['academic_year_id']]);
        
        if ($stmt_exist->fetch()) {
            throw new Exception('คุณได้ลงทะเบียนกีฬาประเภทนี้แล้ว');
        }
        
        // บันทึกข้อมูลนักกีฬา
        $query_insert = "INSERT INTO sport_athletes 
                        (student_id, sport_type, position, academic_year_id, notes, registered_date) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->execute([
            $student['student_id'], 
            $sport_type, 
            $position, 
            $current_year['academic_year_id'], 
            $notes
        ]);
        
        $message = 'ลงทะเบียนนักกีฬาเรียบร้อยแล้ว! รอการอนุมัติจากแอดมิน';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ดึงข้อมูลการแข่งขันที่เปิดรับสมัคร
$query_competitions = "SELECT c.*, sc.category_name, sc.max_participants, sc.gender_restriction
                      FROM competitions c
                      LEFT JOIN sports_categories sc ON c.category_id = sc.category_id
                      WHERE c.status IN ('upcoming', 'registration_open') 
                      AND c.academic_year_id = ?
                      ORDER BY c.competition_date, c.competition_time";
$stmt_comp = $db->prepare($query_competitions);
$stmt_comp->execute([$current_year['academic_year_id']]);
$competitions = $stmt_comp->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนนักกีฬา - กีฬาสี วิทยาลัยการอาชีพปราสาท</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .competition-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .competition-card:hover {
            transform: translateY(-5px);
        }
        .sport-badge {
            background: linear-gradient(45deg, #00d2ff, #3a7bd5);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-open {
            background-color: #d4edda;
            color: #155724;
        }
        .status-upcoming {
            background-color: #fff3cd;
            color: #856404;
        }
        .header-banner {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header-banner">
        <div class="container">
            <h1><i class="fas fa-medal"></i> ระบบลงทะเบียนนักกีฬา</h1>
            <p class="mb-0">กีฬาสี วิทยาลัยการอาชีพปราสาท</p>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- ฟอร์มลงทะเบียน -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-plus"></i> ลงทะเบียนเป็นนักกีฬา</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="athleteForm">
                            <div class="mb-3">
                                <label for="student_code" class="form-label">
                                    <i class="fas fa-id-card"></i> รหัสนักเรียน
                                </label>
                                <input type="text" class="form-control" id="student_code" name="student_code" 
                                       placeholder="กรอกรหัสนักเรียน" required>
                                <div id="student_info" class="mt-2"></div>
                            </div>

                            <div class="mb-3">
                                <label for="sport_type" class="form-label">
                                    <i class="fas fa-running"></i> ประเภทกีฬา
                                </label>
                                <select class="form-select" id="sport_type" name="sport_type" required>
                                    <option value="">เลือกประเภทกีฬา</option>
                                    <?php foreach ($sports_categories as $sport): ?>
                                        <option value="<?= htmlspecialchars($sport['category_name']) ?>">
                                            <?= htmlspecialchars($sport['category_name']) ?>
                                            (<?= $sport['category_type'] === 'team' ? 'ทีม' : 'บุคคล' ?>)
                                            <?php if ($sport['gender_restriction'] !== 'mixed'): ?>
                                                - <?= $sport['gender_restriction'] === 'male' ? 'ชาย' : 'หญิง' ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="position" class="form-label">
                                    <i class="fas fa-chess-pawn"></i> ตำแหน่ง/หน้าที่ (ถ้ามี)
                                </label>
                                <input type="text" class="form-control" id="position" name="position" 
                                       placeholder="เช่น กองหน้า, กองกลาง, ผู้รักษาประตู">
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note"></i> หมายเหตุ
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="ข้อมูลเพิ่มเติม เช่น ประสบการณ์ การบาดเจ็บ"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> ส่งใบสมัคร
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- รายการการแข่งขัน -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-trophy"></i> รายการการแข่งขัน</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($competitions)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>ยังไม่มีการแข่งขันที่เปิดรับสมัคร</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($competitions as $comp): ?>
                                <div class="competition-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?= htmlspecialchars($comp['competition_name']) ?></h6>
                                        <span class="status-badge <?= $comp['status'] === 'registration_open' ? 'status-open' : 'status-upcoming' ?>">
                                            <?= $comp['status'] === 'registration_open' ? 'เปิดรับสมัคร' : 'กำลังจะมา' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="sport-badge"><?= htmlspecialchars($comp['category_name']) ?></span>
                                    </div>
                                    
                                    <div class="row text-sm">
                                        <div class="col-6">
                                            <i class="fas fa-calendar"></i> 
                                            <?= date('d/m/Y', strtotime($comp['competition_date'])) ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-clock"></i> 
                                            <?= $comp['competition_time'] ? date('H:i', strtotime($comp['competition_time'])) : 'ไม่ระบุ' ?>
                                        </div>
                                        <?php if ($comp['location']): ?>
                                            <div class="col-12 mt-1">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($comp['location']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($comp['max_participants_per_color']): ?>
                                            <div class="col-12 mt-1">
                                                <i class="fas fa-users"></i> 
                                                สูงสุด <?= $comp['max_participants_per_color'] ?> คนต่อสี
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ข้อมูลการติดต่อ -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> ข้อมูลการติดต่อ</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-phone"></i> โทร: 044-661-315</p>
                        <p><i class="fas fa-envelope"></i> อีเมล: prasat_tech@hotmail.com</p>
                        <p class="mb-0"><i class="fas fa-map-marker-alt"></i> วิทยาลัยการอาชีพปราสาท</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#sport_type').select2({
                placeholder: 'เลือกประเภทกีฬา',
                allowClear: true
            });

            // ตรวจสอบข้อมูลนักเรียนเมื่อพิมพ์รหัส
            $('#student_code').on('blur', function() {
                const studentCode = $(this).val();
                if (studentCode) {
                    checkStudentInfo(studentCode);
                }
            });

            function checkStudentInfo(studentCode) {
                $.ajax({
                    url: 'ajax/check_student.php',
                    method: 'POST',
                    data: { student_code: studentCode },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const student = response.student;
                            let colorInfo = '';
                            if (student.color_name) {
                                colorInfo = `<span class="badge" style="background-color: ${student.color_code}; color: white;">${student.color_name}</span>`;
                            } else {
                                colorInfo = '<span class="badge bg-warning">ยังไม่ได้จัดสี</span>';
                            }
                            
                            $('#student_info').html(`
                                <div class="alert alert-info">
                                    <strong>${student.first_name} ${student.last_name}</strong><br>
                                    ชั้น: ${student.education_level} แผนก: ${student.department_name}<br>
                                    สี: ${colorInfo}
                                </div>
                            `);
                        } else {
                            $('#student_info').html(`
                                <div class="alert alert-danger">
                                    ไม่พบข้อมูลนักเรียน
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#student_info').html(`
                            <div class="alert alert-warning">
                                เกิดข้อผิดพลาดในการตรวจสอบข้อมูล
                            </div>
                        `);
                    }
                });
            }
        });
    </script>
</body>
</html>