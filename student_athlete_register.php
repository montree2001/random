<?php
// student_athlete_register.php - หน้าสมัครนักกีฬาสำหรับนักเรียน
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$query_year = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$stmt_year = $db->prepare($query_year);
$stmt_year->execute();
$current_year = $stmt_year->fetch();

// ดึงข้อมูลประเภทกีฬาที่มีอยู่
$query_sports = "SELECT DISTINCT sport_type FROM sport_athletes 
                WHERE academic_year_id = ? 
                UNION 
                SELECT DISTINCT category_name as sport_type FROM sports_categories WHERE is_active = 1
                ORDER BY sport_type";
$stmt_sports = $db->prepare($query_sports);
$stmt_sports->execute([$current_year['academic_year_id']]);
$sport_types = $stmt_sports->fetchAll();

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student_code = trim($_POST['student_code']);
        $sport_type = trim($_POST['sport_type']);
        $position = trim($_POST['position']) ?: NULL;
        $notes = trim($_POST['notes']) ?: NULL;
        $phone = trim($_POST['phone']) ?: NULL;
        $emergency_contact = trim($_POST['emergency_contact']) ?: NULL;
        
        if (empty($student_code) || empty($sport_type)) {
            throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
        }
        
        // ตรวจสอบรหัสนักเรียน
        $query_check = "SELECT s.*, u.first_name, u.last_name, c.level, d.department_name, c.group_number,
                              sc.color_name, sc.color_code
                       FROM students s 
                       JOIN users u ON s.user_id = u.user_id
                       LEFT JOIN classes c ON s.current_class_id = c.class_id
                       LEFT JOIN departments d ON c.department_id = d.department_id
                       LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                              AND ssc.academic_year_id = ? AND ssc.is_active = 1
                       LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                       WHERE s.student_code = ? AND s.status = 'กำลังศึกษา'";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->execute([$current_year['academic_year_id'], $student_code]);
        $student = $stmt_check->fetch();
        
        if (!$student) {
            throw new Exception('ไม่พบรหัสนักเรียนในระบบ หรือสถานะไม่ใช่กำลังศึกษา');
        }
        
        if (!$student['color_name']) {
            throw new Exception('นักเรียนยังไม่ได้รับการจัดสี กรุณาติดต่อครูที่ปรึกษา');
        }
        
        // ตรวจสอบว่าสมัครกีฬานี้แล้วหรือยัง (ทั้งที่อนุมัติแล้วและรออนุมัติ)
        $query_exist = "SELECT * FROM sport_athletes 
                       WHERE student_id = ? AND sport_type = ? AND academic_year_id = ?";
        $stmt_exist = $db->prepare($query_exist);
        $stmt_exist->execute([$student['student_id'], $sport_type, $current_year['academic_year_id']]);
        
        if ($stmt_exist->fetch()) {
            throw new Exception('คุณได้สมัครกีฬาประเภทนี้แล้ว');
        }
        
        // บันทึกข้อมูลการสมัคร (สถานะรออนุมัติ โดยใช้ is_captain = -1 เป็นสัญลักษณ์รออนุมัติ)
        $query_insert = "INSERT INTO sport_athletes 
                        (student_id, sport_type, position, notes, academic_year_id, is_captain, created_at) 
                        VALUES (?, ?, ?, ?, ?, -1, NOW())";
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->execute([
            $student['student_id'], 
            $sport_type, 
            $position, 
            $notes . ($phone ? "\nเบอร์โทร: " . $phone : "") . ($emergency_contact ? "\nติดต่อฉุกเฉิน: " . $emergency_contact : ""),
            $current_year['academic_year_id']
        ]);
        
        $message = 'ส่งใบสมัครเรียบร้อยแล้ว! รอการอนุมัติจากแอดมิน';
        $message_type = 'success';
        
        // รีเซ็ตฟอร์ม
        $_POST = array();
        
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'danger';
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

// ดึงสถิติการสมัคร
$query_stats = "SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN is_captain = -1 THEN 1 END) as pending_applications,
                COUNT(CASE WHEN is_captain != -1 THEN 1 END) as approved_applications,
                COUNT(DISTINCT sport_type) as sport_types_count
                FROM sport_athletes 
                WHERE academic_year_id = ?";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute([$current_year['academic_year_id']]);
$stats = $stmt_stats->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครเป็นนักกีฬา - กีฬาสี วิทยาลัยการอาชีพปราสาท</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        .hero-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            text-align: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
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
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .status-open {
            background: #d4edda;
            color: #155724;
        }
        .status-upcoming {
            background: #fff3cd;
            color: #856404;
        }
        .competition-card {
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
        }
        .student-info-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1><i class="fas fa-medal"></i> ระบบสมัครนักกีฬา</h1>
            <p class="lead">กีฬาสี วิทยาลัยการอาชีพปราสาท ปีการศึกษา <?php echo $current_year['year']; ?></p>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h4><?php echo number_format($stats['total_applications']); ?></h4>
                        <small>ใบสมัครทั้งหมด</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4><?php echo number_format($stats['pending_applications']); ?></h4>
                        <small>รออนุมัติ</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4><?php echo number_format($stats['approved_applications']); ?></h4>
                        <small>อนุมัติแล้ว</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-trophy fa-2x text-info mb-2"></i>
                        <h4><?php echo number_format($stats['sport_types_count']); ?></h4>
                        <small>ประเภทกีฬา</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- ฟอร์มสมัคร -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-edit"></i> แบบฟอร์มสมัครนักกีฬา</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="athleteForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_code" class="form-label">
                                            <i class="fas fa-id-card"></i> รหัสนักเรียน <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="student_code" name="student_code" 
                                               placeholder="กรอกรหัสนักเรียน" required
                                               value="<?php echo htmlspecialchars($_POST['student_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone"></i> เบอร์โทรศัพท์
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="เบอร์โทรติดต่อ"
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div id="student_info" style="display: none;"></div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sport_type" class="form-label">
                                            <i class="fas fa-running"></i> ประเภทกีฬา <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="sport_type" name="sport_type" required>
                                            <option value="">เลือกประเภทกีฬา</option>
                                            <?php foreach ($sport_types as $sport): ?>
                                                <option value="<?php echo htmlspecialchars($sport['sport_type']); ?>"
                                                        <?php echo (($_POST['sport_type'] ?? '') == $sport['sport_type']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sport['sport_type']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="position" class="form-label">
                                            <i class="fas fa-chess-pawn"></i> ตำแหน่ง/หน้าที่
                                        </label>
                                        <input type="text" class="form-control" id="position" name="position" 
                                               placeholder="เช่น กองหน้า, กองกลาง, ผู้รักษาประตู"
                                               value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="emergency_contact" class="form-label">
                                    <i class="fas fa-user-shield"></i> ผู้ติดต่อฉุกเฉิน
                                </label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       placeholder="ชื่อและเบอร์โทรผู้ปกครอง หรือผู้ติดต่อฉุกเฉิน"
                                       value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note"></i> หมายเหตุ/ข้อมูลเพิ่มเติม
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" 
                                          placeholder="ประสบการณ์ด้านกีฬา, การบาดเจ็บ, โรคประจำตัว, หรือข้อมูลที่สำคัญอื่นๆ"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> หมายเหตุสำคัญ</h6>
                                <ul class="mb-0">
                                    <li>นักเรียนต้องมีสีประจำตัวก่อนจึงจะสามารถสมัครได้</li>
                                    <li>ใบสมัครจะต้องรอการอนุมัติจากแอดมิน</li>
                                    <li>สามารถสมัครได้หลายประเภทกีฬา</li>
                                    <li>ข้อมูลที่กรอกจะถูกส่งให้ครูผู้ดูแล</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> ส่งใบสมัครนักกีฬา
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- รายการการแข่งขัน -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy"></i> รายการการแข่งขัน</h5>
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
                                        <h6 class="mb-0"><?php echo htmlspecialchars($comp['competition_name']); ?></h6>
                                        <span class="status-badge <?php echo $comp['status'] === 'registration_open' ? 'status-open' : 'status-upcoming'; ?>">
                                            <?php echo $comp['status'] === 'registration_open' ? 'เปิดรับสมัคร' : 'เร็วๆ นี้'; ?>
                                        </span>
                                    </div>
                                    <p class="text-muted mb-1">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('d/m/Y', strtotime($comp['competition_date'])); ?>
                                        <?php if ($comp['competition_time']): ?>
                                            เวลา <?php echo date('H:i', strtotime($comp['competition_time'])); ?> น.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($comp['location']): ?>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($comp['location']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($comp['category_name']): ?>
                                        <p class="mb-0">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($comp['category_name']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ข้อมูลติดต่อ -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-phone"></i> ติดต่อสอบถาม</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-user"></i> งานกิจกรรมนักเรียน นักศึกษา</p>
                        <p><i class="fas fa-phone"></i> โทร: 044-661-315</p>
                        <p><i class="fas fa-envelope"></i> อีเมล: prasat_tech@hotmail.com</p>
                        <p class="mb-0"><i class="fas fa-map-marker-alt"></i> วิทยาลัยการอาชีพปราสาท</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // เริ่มต้น Select2
            $('#sport_type').select2({
                placeholder: 'เลือกประเภทกีฬา',
                allowClear: true
            });

            // ตรวจสอบข้อมูลนักเรียนเมื่อพิมพ์รหัส
            $('#student_code').on('blur', function() {
                const studentCode = $(this).val().trim();
                if (studentCode.length >= 5) {
                    checkStudentInfo(studentCode);
                }
            });

            function checkStudentInfo(studentCode) {
                // สร้าง AJAX request ง่ายๆ
                $.post('check_student_info.php', {student_code: studentCode})
                .done(function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            const student = data.student;
                            let colorBadge = '';
                            if (student.color_name) {
                                colorBadge = `<span class="badge" style="background-color: ${student.color_code}; color: white;">${student.color_name}</span>`;
                            } else {
                                colorBadge = '<span class="badge bg-warning text-dark">ยังไม่มีสี</span>';
                            }
                            
                            $('#student_info').html(`
                                <div class="student-info-card">
                                    <h6><i class="fas fa-user"></i> ข้อมูลนักเรียน</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>ชื่อ:</strong> ${student.title}${student.first_name} ${student.last_name}</p>
                                            <p><strong>ระดับ:</strong> ${student.level || '-'}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>แผนก:</strong> ${student.department_name || '-'}/${student.group_number || '-'}</p>
                                            <p><strong>สี:</strong> ${colorBadge}</p>
                                        </div>
                                    </div>
                                </div>
                            `).show();
                        } else {
                            $('#student_info').html(`
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> ${data.message}
                                </div>
                            `).show();
                        }
                    } catch (e) {
                        $('#student_info').hide();
                    }
                })
                .fail(function() {
                    $('#student_info').hide();
                });
            }
        });
    </script>
</body>
</html>