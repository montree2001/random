<?php
// student_portal.php - พอร์ทัลนักเรียน
require_once 'config/database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$current_academic_year_query = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$current_academic_year_stmt = $db->prepare($current_academic_year_query);
$current_academic_year_stmt->execute();
$current_academic_year = $current_academic_year_stmt->fetch();

// ดึงข้อมูลการแข่งขันที่เปิดรับสมัคร
$open_competitions_query = "
    SELECT 
        c.*,
        cc.category_name,
        cc.category_type,
        cc.gender_restriction,
        COUNT(cr.registration_id) as registered_count
    FROM competitions c
    JOIN sports_categories cc ON c.category_id = cc.category_id
    LEFT JOIN athlete_registrations cr ON c.competition_id = cr.competition_id 
        AND cr.status IN ('registered', 'confirmed')
    WHERE c.status = 'registration_open'
        AND c.registration_start <= NOW()
        AND c.registration_end >= NOW()
    GROUP BY c.competition_id
    ORDER BY c.competition_date ASC, c.competition_time ASC
";
$open_competitions_stmt = $db->prepare($open_competitions_query);
$open_competitions_stmt->execute();
$open_competitions = $open_competitions_stmt->fetchAll();

// ดึงข้อมูลการแข่งขันที่กำลังจะมาถึง
$upcoming_competitions_query = "
    SELECT 
        c.*,
        cc.category_name,
        cc.category_type
    FROM competitions c
    JOIN sports_categories cc ON c.category_id = cc.category_id
    WHERE c.status IN ('upcoming', 'registration_open')
        AND c.competition_date >= CURDATE()
    ORDER BY c.competition_date ASC, c.competition_time ASC
    LIMIT 10
";
$upcoming_competitions_stmt = $db->prepare($upcoming_competitions_query);
$upcoming_competitions_stmt->execute();
$upcoming_competitions = $upcoming_competitions_stmt->fetchAll();

// ดึงข้อมูลสีทั้งหมด
$colors_query = "SELECT * FROM colors WHERE is_active = 1 ORDER BY color_name";
$colors_stmt = $db->prepare($colors_query);
$colors_stmt->execute();
$colors = $colors_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พอร์ทัลนักเรียน - ระบบจัดการสีและกีฬาสี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }
        .service-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            height: 100%;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .service-card .card-body {
            padding: 2rem;
            text-align: center;
        }
        .service-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .competition-card {
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .competition-card:hover {
            transform: translateX(5px);
        }
        .color-preview {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid #fff;
            box-shadow: 0 0 3px rgba(0,0,0,0.3);
        }
        .quick-access {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
        }
        .info-banner {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-palette me-2"></i>ระบบจัดการสีและกีฬาสี
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>หน้าแรก
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_portal.php">
                            <i class="fas fa-user me-1"></i>พอร์ทัลนักเรียน
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">
                <i class="fas fa-graduation-cap me-3"></i>พอร์ทัลนักเรียน
            </h1>
            <p class="lead">ระบบจัดการสีและกีฬาสีสำหรับนักเรียน</p>
            <?php if ($current_academic_year): ?>
            <p class="mb-0">
                <i class="fas fa-calendar-alt me-2"></i>
                ปีการศึกษา <?php echo $current_academic_year['year']; ?> ภาคเรียนที่ <?php echo $current_academic_year['semester']; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Quick Access Section -->
        <div class="quick-access">
            <h4 class="mb-4"><i class="fas fa-rocket me-2"></i>เข้าใช้งานด่วน</h4>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <a href="payment_upload.php" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-receipt me-2"></i>อัพโหลดสลิปการชำระเงิน
                    </a>
                </div>
                <div class="col-md-6 mb-3">
                    <a href="#check-color" class="btn btn-outline-primary btn-lg w-100">
                        <i class="fas fa-search me-2"></i>ตรวจสอบสีของฉัน
                    </a>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <div class="row mb-5">
            <div class="col-12 mb-4">
                <h3><i class="fas fa-concierge-bell me-2"></i>บริการสำหรับนักเรียน</h3>
                <p class="text-muted">เลือกบริการที่ต้องการใช้งาน</p>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-palette service-icon"></i>
                        <h5 class="card-title">ตรวจสอบสี</h5>
                        <p class="card-text">ตรวจสอบสีที่ได้รับมอบหมายและข้อมูลการจัดสี</p>
                        <a href="#check-color" class="btn btn-primary">ตรวจสอบ</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-receipt service-icon"></i>
                        <h5 class="card-title">ชำระค่าบำรุงสี</h5>
                        <p class="card-text">อัพโหลดสลิปการโอนเงินค่าบำรุงสี</p>
                        <a href="payment_upload.php" class="btn btn-primary">อัพโหลดสลิป</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-medal service-icon"></i>
                        <h5 class="card-title">สมัครเป็นนักกีฬา</h5>
                        <p class="card-text">ลงทะเบียนเป็นนักกีฬาในการแข่งขันต่างๆ</p>
                        <a href="#register-athlete" class="btn btn-primary">สมัครเลย</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt service-icon"></i>
                        <h5 class="card-title">ตารางการแข่งขัน</h5>
                        <p class="card-text">ดูตารางการแข่งขันและรายละเอียดต่างๆ</p>
                        <a href="#competition-schedule" class="btn btn-primary">ดูตาราง</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-trophy service-icon"></i>
                        <h5 class="card-title">ผลการแข่งขัน</h5>
                        <p class="card-text">ดูผลการแข่งขันและคะแนนแต่ละสี</p>
                        <a href="#competition-results" class="btn btn-primary">ดูผล</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card service-card">
                    <div class="card-body">
                        <i class="fas fa-users service-icon"></i>
                        <h5 class="card-title">รายชื่อนักกีฬา</h5>
                        <p class="card-text">ดูรายชื่อนักกีฬาแต่ละสีและประเภทกีฬา</p>
                        <a href="#athlete-list" class="btn btn-primary">ดูรายชื่อ</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check Color Section -->
        <div id="check-color" class="mb-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>ตรวจสอบสีของฉัน</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="check_student_code" class="form-label">รหัสนักเรียน</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="check_student_code" 
                                   placeholder="กรอกรหัสนักเรียน 11 หลัก"
                                   maxlength="11">
                            <div class="form-text">กรอกรหัสนักเรียนเพื่อดูข้อมูลสี</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="button" class="btn btn-primary" onclick="checkStudentColor()">
                                <i class="fas fa-search me-2"></i>ตรวจสอบ
                            </button>
                        </div>
                    </div>
                    
                    <div id="colorResult" class="mt-4" style="display: none;">
                        <div class="alert alert-info">
                            <div id="colorResultContent"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Competitions Section -->
        <?php if (!empty($open_competitions)): ?>
        <div id="register-athlete" class="mb-5">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-medal me-2"></i>การแข่งขันที่เปิดรับสมัคร</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($open_competitions as $competition): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card competition-card">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($competition['competition_name']); ?></h6>
                                    <p class="card-text">
                                        <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($competition['category_name']); ?>
                                        <span class="badge bg-secondary ms-2">
                                            <?php echo $competition['category_type'] === 'individual' ? 'เดี่ยว' : 'ทีม'; ?>
                                        </span>
                                    </p>
                                    <p class="card-text">
                                        <i class="fas fa-calendar me-2"></i><?php echo date('d/m/Y', strtotime($competition['competition_date'])); ?>
                                        <i class="fas fa-clock ms-3 me-2"></i><?php echo date('H:i', strtotime($competition['competition_time'])); ?>
                                    </p>
                                    <p class="card-text">
                                        <i class="fas fa-users me-2"></i>ลงทะเบียนแล้ว: <?php echo $competition['registered_count']; ?> คน
                                    </p>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-user-plus me-2"></i>ลงทะเบียน
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Competitions Section -->
        <div id="competition-schedule" class="mb-5">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>การแข่งขันที่กำลังจะมาถึง</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_competitions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">ยังไม่มีการแข่งขันที่กำหนด</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>การแข่งขัน</th>
                                    <th>ประเภท</th>
                                    <th>วันที่แข่งขัน</th>
                                    <th>เวลา</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_competitions as $competition): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($competition['competition_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($competition['category_name']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $competition['category_type'] === 'individual' ? 'เดี่ยว' : 'ทีม'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($competition['competition_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($competition['competition_time'])); ?></td>
                                    <td>
                                        <?php
                                        $status_text = [
                                            'upcoming' => 'กำลังจะมาถึง',
                                            'registration_open' => 'เปิดรับสมัคร',
                                            'registration_closed' => 'ปิดรับสมัคร'
                                        ];
                                        $status_class = [
                                            'upcoming' => 'bg-warning',
                                            'registration_open' => 'bg-success',
                                            'registration_closed' => 'bg-secondary'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_class[$competition['status']] ?? 'bg-secondary'; ?>">
                                            <?php echo $status_text[$competition['status']] ?? $competition['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colors Information Section -->
        <div class="mb-5">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-palette me-2"></i>ข้อมูลสีทั้งหมด</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($colors as $color): ?>
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <span class="color-preview" style="background-color: <?php echo $color['color_code']; ?>"></span>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($color['color_name']); ?></h6>
                                    <small class="text-muted"><?php echo $color['color_code']; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">
                <i class="fas fa-graduation-cap me-2"></i>
                ระบบจัดการสีและกีฬาสี - วิทยาลัยการอาชีพปราสาท
            </p>
            <small class="text-muted">พัฒนาโดยทีมงานไอที</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // ตรวจสอบสีของนักเรียน
        function checkStudentColor() {
            const studentCode = document.getElementById('check_student_code').value.trim();
            
            if (!studentCode) {
                alert('กรุณากรอกรหัสนักเรียน');
                return;
            }
            
            if (studentCode.length !== 11) {
                alert('รหัสนักเรียนต้องเป็น 11 หลัก');
                return;
            }
            
            $.ajax({
                url: 'get_student_color.php',
                method: 'POST',
                data: { student_code: studentCode },
                dataType: 'json',
                success: function(response) {
                    const resultDiv = document.getElementById('colorResult');
                    const contentDiv = document.getElementById('colorResultContent');
                    
                    if (response.success) {
                        contentDiv.innerHTML = `
                            <h6><i class="fas fa-user me-2"></i>ข้อมูลนักเรียน</h6>
                            <p><strong>รหัสนักเรียน:</strong> ${response.data.student_code}</p>
                            <p><strong>ชื่อ-นามสกุล:</strong> ${response.data.student_name}</p>
                            <hr>
                            <h6><i class="fas fa-palette me-2"></i>ข้อมูลสี</h6>
                            <div class="d-flex align-items-center mb-2">
                                <span class="color-preview" style="background-color: ${response.data.color_code}"></span>
                                <strong>${response.data.color_name}</strong>
                                <span class="badge bg-secondary ms-2">${response.data.color_code}</span>
                            </div>
                            <p><strong>ปีการศึกษา:</strong> ${response.data.academic_year}/${response.data.semester}</p>
                            <p><strong>วันที่จัดสี:</strong> ${new Date(response.data.assignment_date).toLocaleDateString('th-TH')}</p>
                            ${response.data.slip_count > 0 ? `
                                <hr>
                                <h6><i class="fas fa-receipt me-2"></i>ข้อมูลการชำระเงิน</h6>
                                <p>ส่งสลิปแล้ว: ${response.data.slip_count} ครั้ง</p>
                                <p>อนุมัติแล้ว: ${response.data.approved_slip_count} ครั้ง</p>
                            ` : ''}
                            ${response.warning ? `<div class="alert alert-warning mt-3"><small><i class="fas fa-exclamation-triangle me-2"></i>${response.warning}</small></div>` : ''}
                        `;
                        resultDiv.className = 'mt-4';
                        resultDiv.querySelector('.alert').className = 'alert alert-success';
                    } else {
                        contentDiv.innerHTML = `
                            <i class="fas fa-exclamation-circle me-2"></i>${response.message}
                        `;
                        resultDiv.className = 'mt-4';
                        resultDiv.querySelector('.alert').className = 'alert alert-danger';
                    }
                    
                    resultDiv.style.display = 'block';
                },
                error: function() {
                    const resultDiv = document.getElementById('colorResult');
                    const contentDiv = document.getElementById('colorResultContent');
                    
                    contentDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i>เกิดข้อผิดพลาดในการตรวจสอบข้อมูล';
                    resultDiv.className = 'mt-4';
                    resultDiv.querySelector('.alert').className = 'alert alert-danger';
                    resultDiv.style.display = 'block';
                }
            });
        }
        
        // Enter key support
        document.getElementById('check_student_code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                checkStudentColor();
            }
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>