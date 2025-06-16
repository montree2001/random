<?php
// payment_upload.php - หน้าอัพโหลดสลิปสำหรับนักเรียน (ปรับปรุงใหม่)
require_once 'config/database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student_code = trim($_POST['student_code']);
        $amount = floatval($_POST['amount']);
        $transfer_date = $_POST['transfer_date'];
        $transfer_time = $_POST['transfer_time'] ?? null;
        $bank_from = trim($_POST['bank_from']) ?: null;
        $bank_to = trim($_POST['bank_to']) ?: null;
        $ref_number = trim($_POST['ref_number']) ?: null;
        $notes = trim($_POST['notes']) ?: null;

        // ตรวจสอบข้อมูลนักเรียน - ลองทั้งสองตาราง
        $student = null;
        
        // ลองค้นหาในตาราง student_sport_colors ก่อน (ตามผลจาก debug)
        $student_query1 = "
            SELECT s.student_id, ssc.color_id, ssc.academic_year_id, 'sport' as table_used
            FROM students s
            JOIN student_sport_colors ssc ON s.student_id = ssc.student_id
            JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id
            WHERE s.student_code = ? AND ay.is_active = 1 AND s.status = 'กำลังศึกษา' AND ssc.is_active = 1
            LIMIT 1
        ";
        
        try {
            $student_stmt1 = $db->prepare($student_query1);
            $student_stmt1->execute([$student_code]);
            $student = $student_stmt1->fetch();
            
            if ($student) {
                // พบข้อมูลในตาราง student_sport_colors
                error_log("Found student in student_sport_colors: " . json_encode($student));
            }
        } catch (Exception $e) {
            error_log("Error with student_sport_colors table: " . $e->getMessage());
        }
        
        // ถ้าไม่เจอ ลองค้นหาในตาราง student_colors
        if (!$student) {
            $student_query2 = "
                SELECT s.student_id, sc.color_id, sc.academic_year_id, 'colors' as table_used
                FROM students s
                JOIN student_colors sc ON s.student_id = sc.student_id
                JOIN academic_years ay ON sc.academic_year_id = ay.academic_year_id
                WHERE s.student_code = ? AND ay.is_active = 1 AND s.status = 'กำลังศึกษา'
                LIMIT 1
            ";
            
            try {
                $student_stmt2 = $db->prepare($student_query2);
                $student_stmt2->execute([$student_code]);
                $student = $student_stmt2->fetch();
                
                if ($student) {
                    error_log("Found student in student_colors: " . json_encode($student));
                }
            } catch (Exception $e) {
                error_log("Error with student_colors table: " . $e->getMessage());
            }
        }

        if (!$student) {
            throw new Exception('ไม่พบรหัสนักเรียนหรือยังไม่ได้จัดสี');
        }

        // ตรวจสอบไฟล์รูปภาพ
        if (!isset($_FILES['slip_image']) || $_FILES['slip_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('กรุณาอัพโหลดรูปสลิป');
        }

        $file = $_FILES['slip_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('รองรับเฉพาะไฟล์ JPG, JPEG และ PNG');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('ไฟล์ใหญ่เกินไป (สูงสุด 5MB)');
        }

        // สร้างโฟลเดอร์ถ้ายังไม่มี
        $upload_dir = 'uploads/slips/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // สร้างชื่อไฟล์ใหม่
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'slip_' . $student_code . '_' . date('YmdHis') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // อัพโหลดไฟล์
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
        }

        // บันทึกข้อมูลลงฐานข้อมูล
        $insert_query = "
            INSERT INTO payment_slips 
            (student_id, color_id, academic_year_id, amount, transfer_date, transfer_time, 
             slip_image, bank_from, bank_to, ref_number, notes, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([
            $student['student_id'],
            $student['color_id'], 
            $student['academic_year_id'],
            $amount,
            $transfer_date,
            $transfer_time,
            $new_filename,
            $bank_from,
            $bank_to,
            $ref_number,
            $notes
        ]);

        $success_upload = true;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // ลบไฟล์ที่อัพโหลดแล้วถ้าเกิดข้อผิดพลาด
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
    }
}

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$current_academic_year_query = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$current_academic_year_stmt = $db->prepare($current_academic_year_query);
$current_academic_year_stmt->execute();
$current_academic_year = $current_academic_year_stmt->fetch();

// รายการธนาคาร
$banks = [
    'ธนาคารกรุงเทพ',
    'ธนาคารกสิกรไทย',
    'ธนาคารไทยพาณิชย์',
    'ธนาคารกรุงไทย',
    'ธนาคารทหารไทยธนชาต',
    'ธนาคารกรุงศรีอยุธยา',
    'ธนาคารเกียรตินาคิน',
    'ธนาคารซีไอเอ็มบี ไทย',
    'ธนาคารทิสโก้',
    'ธนาคารยูโอบี',
    'ธนาคารแลนด์ แอนด์ เฮ้าส์',
    'ธนาคารไอซีบีซี (ไทย)',
    'ธนาคารพัฒนาวิสาหกิจขนาดกลางและขนาดย่อมแห่งประเทศไทย',
    'ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร',
    'ธนาคารออมสิน',
    'ธนาคารอาคารสงเคราะห์',
    'ธนาคารอิสลามแห่งประเทศไทย',
    'ธนาคารเกียรตินาคินภัทร'
];

// ข้อมูลธนาคารตามสี (ในการใช้งานจริงควรเก็บในฐานข้อมูล)
$bank_accounts = [
    'default' => [
        'bank_name' => 'ธนาคารกรุงไทย',
        'account_number' => '1234567890',
        'account_name' => 'กิจกรรมนักเรียน วิทยาลัยการอาชีพปราสาท',
        'fee_amount' => 150.00
    ]
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งหลักฐานการโอนเงินค่าบำรุงสี - วิทยาลัยการอาชีพปราสาท</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 900px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .content-section {
            padding: 2rem;
        }
        
        .bank-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid;
        }
        
        .color-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            border: 3px solid #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,0.3);
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9ff;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }
        
        .upload-area.dragover {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .slip-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <div class="mb-3">
                    <i class="fas fa-money-bill-transfer fa-3x"></i>
                </div>
                <h1><i class="fas fa-upload me-2"></i>ส่งหลักฐานการโอนเงิน</h1>
                <p class="mb-0">ค่าบำรุงกิจกรรมกีฬาสี</p>
                <p class="mt-2 mb-0">วิทยาลัยการอาชีพปราสาท</p>
                <?php if ($current_academic_year): ?>
                <p class="mt-2 mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    ปีการศึกษา <?php echo $current_academic_year['year']; ?> ภาคเรียนที่ <?php echo $current_academic_year['semester']; ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="content-section">
                <?php if (isset($success_upload) && $success_upload): ?>
                <!-- Success Section -->
                <div id="successSection" class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h3 class="text-success">ส่งหลักฐานการโอนเงินเรียบร้อยแล้ว!</h3>
                    <p class="text-muted">
                        ระบบได้รับหลักฐานการโอนเงินของคุณแล้ว<br>
                        กรุณารอการตรวจสอบจากเจ้าหน้าที่ภายใน 1-2 วันทำการ
                    </p>
                    <div class="mt-4">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="location.reload()">
                            <i class="fas fa-plus"></i> ส่งหลักฐานใหม่
                        </button>
                        <a href="student_portal.php" class="btn btn-outline-success">
                            <i class="fas fa-home"></i> กลับหน้าหลัก
                        </a>
                    </div>
                </div>
                <?php else: ?>
                
                <!-- Search Student Section -->
                <div class="form-section mb-4">
                    <h4 class="mb-3"><i class="fas fa-search me-2"></i>ค้นหาข้อมูลของคุณ</h4>
                    <form id="searchForm" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">รหัสนักเรียน</label>
                            <input type="text" class="form-control form-control-lg" id="studentCode" 
                                   placeholder="กรอกรหัสนักเรียน 11 หลัก" maxlength="11" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-submit w-100">
                                <i class="fas fa-search me-2"></i>ค้นหา
                            </button>
                        </div>
                    </form>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Student Info Section (Hidden initially) -->
                <div id="studentInfoSection" class="d-none">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step completed">
                            <div class="step-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <small>ตรวจสอบข้อมูล</small>
                        </div>
                        <div class="step active">
                            <div class="step-circle">
                                <i class="fas fa-money-bill"></i>
                            </div>
                            <small>โอนเงิน</small>
                        </div>
                        <div class="step">
                            <div class="step-circle">
                                <i class="fas fa-upload"></i>
                            </div>
                            <small>ส่งหลักฐาน</small>
                        </div>
                        <div class="step">
                            <div class="step-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <small>เสร็จสิ้น</small>
                        </div>
                    </div>
                    
                    <!-- Student Info Card -->
                    <div class="form-section mb-4">
                        <h5><i class="fas fa-user me-2 text-primary"></i>ข้อมูลของคุณ</h5>
                        <div id="studentInfoDisplay"></div>
                    </div>
                    
                    <!-- Bank Info Card -->
                    <div id="bankInfoCard">
                        <!-- จะแสดงข้อมูลธนาคารที่นี่ -->
                    </div>
                    
                    <!-- Upload Form -->
                    <div class="form-section">
                        <h5><i class="fas fa-upload me-2 text-success"></i>อัพโหลดหลักฐานการโอนเงิน</h5>
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" id="hiddenStudentCode" name="student_code">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">จำนวนเงินที่โอน <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="amount" id="amount" 
                                                   step="0.01" min="0" required readonly>
                                            <span class="input-group-text">บาท</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">วันที่โอนเงิน <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="transfer_date" id="transferDate" 
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">เวลาที่โอนเงิน</label>
                                        <input type="time" class="form-control" name="transfer_time" id="transferTime">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ธนาคารต้นทาง</label>
                                        <select class="form-select" name="bank_from" id="bankFrom">
                                            <option value="">เลือกธนาคาร</option>
                                            <?php foreach ($banks as $bank): ?>
                                            <option value="<?php echo htmlspecialchars($bank); ?>"><?php echo htmlspecialchars($bank); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">หมายเลขอ้างอิง</label>
                                        <input type="text" class="form-control" name="ref_number" id="refNumber" 
                                               placeholder="หมายเลขอ้างอิงจากสลิป">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">อัพโหลดรูปสลิป <span class="text-danger">*</span></label>
                                        <div class="upload-area" onclick="document.getElementById('slipImage').click()">
                                            <div id="uploadPrompt">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                                <p class="mb-2"><strong>คลิกเพื่อเลือกไฟล์</strong></p>
                                                <p class="text-muted mb-0">หรือลากไฟล์มาวางที่นี่</p>
                                                <small class="text-muted">รองรับไฟล์: JPG, JPEG, PNG (ขนาดไม่เกิน 5MB)</small>
                                            </div>
                                            <div id="imagePreview" class="d-none">
                                                <img id="previewImg" class="slip-preview" alt="ตัวอย่างสลิป">
                                                <p class="mt-2 mb-0">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeImage()">
                                                        <i class="fas fa-trash"></i> ลบภาพ
                                                    </button>
                                                </p>
                                            </div>
                                        </div>
                                        <input type="file" name="slip_image" id="slipImage" 
                                               accept="image/jpeg,image/jpg,image/png" 
                                               style="display: none;" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">หมายเหตุเพิ่มเติม</label>
                                        <textarea class="form-control" name="notes" id="notes" rows="3" 
                                                  placeholder="ข้อมูลเพิ่มเติม (ถ้ามี)"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-submit btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>ส่งหลักฐานการโอนเงิน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Loading Section -->
                <div id="loadingSection" class="text-center d-none">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-3">กำลังค้นหาข้อมูล...</p>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // ค้นหาข้อมูลนักเรียน
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const studentCode = document.getElementById('studentCode').value.trim();
            
            if (!studentCode) {
                alert('กรุณากรอกรหัสนักเรียน');
                return;
            }
            
            if (studentCode.length !== 11) {
                alert('รหัสนักเรียนต้องเป็น 11 หลัก');
                return;
            }
            
            // แสดง loading
            document.getElementById('loadingSection').classList.remove('d-none');
            
            // ค้นหาข้อมูลนักเรียน
            $.ajax({
                url: 'get_student_color.php',
                method: 'POST',
                data: { student_code: studentCode },
                dataType: 'json',
                success: function(response) {
                    document.getElementById('loadingSection').classList.add('d-none');
                    
                    if (response.success) {
                        showStudentInfo(response.data);
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    document.getElementById('loadingSection').classList.add('d-none');
                    alert('เกิดข้อผิดพลาดในการค้นหาข้อมูล');
                }
            });
        });
        
        // แสดงข้อมูลนักเรียน
        function showStudentInfo(student) {
            const studentInfoDisplay = document.getElementById('studentInfoDisplay');
            const bankInfoCard = document.getElementById('bankInfoCard');
            const amount = document.getElementById('amount');
            const hiddenStudentCode = document.getElementById('hiddenStudentCode');
            
            // ตั้งค่า hidden field
            hiddenStudentCode.value = student.student_code;
            
            // แสดงข้อมูลนักเรียน
            studentInfoDisplay.innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <h6><strong>${student.student_name}</strong></h6>
                        <p class="mb-1"><strong>รหัสนักเรียน:</strong> ${student.student_code}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <span class="color-badge" style="background-color: ${student.color_code}"></span>
                            <strong>${student.color_name}</strong>
                        </div>
                    </div>
                </div>
            `;
            
            // ข้อมูลธนาคารเริ่มต้น (ควรดึงจากฐานข้อมูลจริง)
            const bankInfo = {
                bank_name: 'ธนาคารกรุงไทย',
                account_number: '1234567890',
                account_name: 'กิจกรรมนักเรียน วิทยาลัยการอาชีพปราสาท',
                fee_amount: student.fee_amount || 150.00 // ใช้ยอดเงินจากฐานข้อมูล
            };
            
            // แสดงข้อมูลธนาคาร
            bankInfoCard.innerHTML = `
                <div class="bank-info-card" style="border-left-color: ${student.color_code}">
                    <h5><i class="fas fa-university me-2"></i>ข้อมูลการโอนเงิน ${student.color_name}</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>ธนาคาร:</strong> ${bankInfo.bank_name}</p>
                            <p class="mb-2"><strong>เลขที่บัญชี:</strong> ${bankInfo.account_number}</p>
                            <p class="mb-2"><strong>ชื่อบัญชี:</strong> ${bankInfo.account_name}</p>
                        </div>
                        <div class="col-md-6">
                            <div class="text-end">
                                <p class="mb-1"><strong>จำนวนเงินที่ต้องโอน</strong></p>
                                <h3 class="text-success mb-0">${bankInfo.fee_amount.toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</h3>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>หมายเหตุ:</strong> กรุณาโอนเงินให้ตรงตามจำนวนที่กำหนด และเก็บหลักฐานการโอนเงินไว้อัพโหลดในขั้นตอนถัดไป
                    </div>
                </div>
            `;
            
            // ตั้งค่าจำนวนเงิน
            amount.value = bankInfo.fee_amount.toFixed(2);
            
            // ตั้งค่าวันที่เป็นวันปัจจุบัน
            document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
            
            // แสดงส่วนข้อมูลนักเรียน
            document.getElementById('studentInfoSection').classList.remove('d-none');
            
            // เลื่อนไปยังส่วนข้อมูลนักเรียน
            document.getElementById('studentInfoSection').scrollIntoView({ behavior: 'smooth' });
        }
        
        // จัดการการอัพโหลดไฟล์
        const slipImage = document.getElementById('slipImage');
        const uploadArea = document.querySelector('.upload-area');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        slipImage.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                slipImage.files = files;
                handleFileSelect();
            }
        });
        
        function handleFileSelect() {
            const file = slipImage.files[0];
            
            if (!file) return;
            
            // ตรวจสอบประเภทไฟล์
            if (!file.type.match('image.*')) {
                alert('กรุณาเลือกไฟล์ภาพเท่านั้น');
                slipImage.value = '';
                return;
            }
            
            // ตรวจสอบขนาดไฟล์ (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('ขนาดไฟล์ต้องไม่เกิน 5MB');
                slipImage.value = '';
                return;
            }
            
            // แสดงตัวอย่างภาพ
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                uploadPrompt.classList.add('d-none');
                imagePreview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
        
        function removeImage() {
            slipImage.value = '';
            uploadPrompt.classList.remove('d-none');
            imagePreview.classList.add('d-none');
        }
        
        // ส่งข้อมูล
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            // ตรวจสอบข้อมูล
            if (!slipImage.files[0]) {
                e.preventDefault();
                alert('กรุณาเลือกรูปสลิป');
                return;
            }
            
            if (!document.getElementById('transferDate').value) {
                e.preventDefault();
                alert('กรุณาเลือกวันที่โอนเงิน');
                return;
            }
            
            // แสดง loading
            document.getElementById('studentInfoSection').classList.add('d-none');
            document.getElementById('loadingSection').classList.remove('d-none');
            document.getElementById('loadingSection').querySelector('p').textContent = 'กำลังอัพโหลด...';
        });
        
        // Enter key support for student code
        document.getElementById('studentCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>