<?php
// payment_upload.php - หน้าอัพโหลดสลิปสำหรับนักเรียน
require_once 'config/database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

// ตรวจสอบการส่งฟอร์ม
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

        // ตรวจสอบข้อมูลนักเรียน
        $student_query = "
            SELECT s.student_id, sc.color_id, sc.academic_year_id 
            FROM students s
            JOIN student_colors sc ON s.student_id = sc.student_id
            JOIN academic_years ay ON sc.academic_year_id = ay.academic_year_id
            WHERE s.student_code = ? AND ay.is_current = 1
        ";
        $student_stmt = $db->prepare($student_query);
        $student_stmt->execute([$student_code]);
        $student = $student_stmt->fetch();

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

        $success_message = 'อัพโหลดสลิปเรียบร้อยแล้ว รอการตรวจสอบจากเจ้าหน้าที่';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // ลบไฟล์ที่อัพโหลดแล้วถ้าเกิดข้อผิดพลาด
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
    }
}

// ดึงข้อมูลปีการศึกษาปัจจุบัน
$current_academic_year_query = "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1";
$current_academic_year_stmt = $db->prepare($current_academic_year_query);
$current_academic_year_stmt->execute();
$current_academic_year = $current_academic_year_stmt->fetch();

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
    <title>อัพโหลดสลิปการชำระเงิน - ระบบจัดการสีและกีฬาสี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .upload-area:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
            transform: scale(1.02);
        }
        .preview-container {
            max-width: 300px;
            margin: 20px auto;
        }
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
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
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>หน้าแรก
                </a>
                <a class="nav-link" href="student_portal.php">
                    <i class="fas fa-user me-1"></i>พอร์ทัลนักเรียน
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-receipt me-2"></i>อัพโหลดสลิปการชำระเงิน</h2>
                        <p class="text-muted">อัพโหลดสลิปการโอนเงินค่าบำรุงสี</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <?php if ($current_academic_year): ?>
        <div class="info-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5><i class="fas fa-info-circle me-2"></i>ข้อมูลการชำระเงิน</h5>
                    <p class="mb-2">ปีการศึกษา: <?php echo $current_academic_year['year']; ?> ภาคเรียนที่ <?php echo $current_academic_year['semester']; ?></p>
                    <p class="mb-0">กรุณาอัพโหลดสลิปการโอนเงินค่าบำรุงสีให้ครบถ้วนและชัดเจน</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <!-- Student Information Section -->
            <div class="form-section">
                <h5 class="section-title"><i class="fas fa-user me-2"></i>ข้อมูลนักเรียน</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label for="student_code" class="form-label">รหัสนักเรียน <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="student_code" 
                               name="student_code" 
                               placeholder="กรอกรหัสนักเรียน 11 หลัก"
                               maxlength="11"
                               pattern="[0-9]{11}"
                               required>
                        <div class="form-text">กรอกรหัสนักเรียน 11 หลัก</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">สีปัจจุบัน</label>
                        <div id="studentColorInfo" class="p-3 bg-light rounded" style="display: none;">
                            <div id="colorDisplay"></div>
                        </div>
                        <div id="noColorInfo" class="p-3 bg-light rounded text-muted">
                            กรอกรหัสนักเรียนเพื่อดูข้อมูลสี
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Information Section -->
            <div class="form-section">
                <h5 class="section-title"><i class="fas fa-money-bill me-2"></i>ข้อมูลการชำระเงิน</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label for="amount" class="form-label">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="amount" 
                               name="amount" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00"
                               required>
                    </div>
                    <div class="col-md-4">
                        <label for="transfer_date" class="form-label">วันที่โอนเงิน <span class="text-danger">*</span></label>
                        <input type="date" 
                               class="form-control" 
                               id="transfer_date" 
                               name="transfer_date" 
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    <div class="col-md-4">
                        <label for="transfer_time" class="form-label">เวลาที่โอนเงิน</label>
                        <input type="time" 
                               class="form-control" 
                               id="transfer_time" 
                               name="transfer_time">
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="bank_from" class="form-label">ธนาคารต้นทาง</label>
                        <input type="text" 
                               class="form-control" 
                               id="bank_from" 
                               name="bank_from" 
                               placeholder="เช่น ธนาคารกสิกรไทย">
                    </div>
                    <div class="col-md-6">
                        <label for="bank_to" class="form-label">ธนาคารปลายทาง</label>
                        <input type="text" 
                               class="form-control" 
                               id="bank_to" 
                               name="bank_to" 
                               placeholder="เช่น ธนาคารไทยพาณิชย์">
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="ref_number" class="form-label">หมายเลขอ้างอิง</label>
                        <input type="text" 
                               class="form-control" 
                               id="ref_number" 
                               name="ref_number" 
                               placeholder="หมายเลขอ้างอิงจากสลิป">
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" 
                                  id="notes" 
                                  name="notes" 
                                  rows="2" 
                                  placeholder="หมายเหตุเพิ่มเติม (ไม่บังคับ)"></textarea>
                    </div>
                </div>
            </div>

            <!-- File Upload Section -->
            <div class="form-section">
                <h5 class="section-title"><i class="fas fa-image me-2"></i>อัพโหลดรูปสลิป</h5>
                
                <div class="upload-area" id="uploadArea">
                    <input type="file" 
                           id="slip_image" 
                           name="slip_image" 
                           accept="image/*" 
                           required 
                           style="display: none;">
                    
                    <div id="uploadPrompt">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>ลากและวางรูปภาพที่นี่</h5>
                        <p class="text-muted">หรือคลิกเพื่อเลือกไฟล์</p>
                        <div class="mt-3">
                            <span class="badge bg-info me-2">JPG</span>
                            <span class="badge bg-info me-2">JPEG</span>
                            <span class="badge bg-info">PNG</span>
                        </div>
                        <p class="small text-muted mt-2">ขนาดไฟล์สูงสุด 5MB</p>
                    </div>
                    
                    <div id="imagePreview" style="display: none;">
                        <div class="preview-container">
                            <img id="previewImg" class="preview-image" alt="Preview">
                        </div>
                        <p class="mt-2 mb-0">คลิกเพื่อเปลี่ยนรูป</p>
                    </div>
                </div>
                
                <div class="form-text mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    กรุณาถ่ายรูปสลิปให้ชัดเจน เห็นข้อมูลครบถ้วน
                </div>
            </div>

            <!-- Submit Section -->
            <div class="form-section text-center">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                    <i class="fas fa-upload me-2"></i>อัพโหลดสลิป
                </button>
                <div class="mt-3">
                    <small class="text-muted">
                        ข้อมูลจะถูกส่งไปยังเจ้าหน้าที่เพื่อตรวจสอบและอนุมัติ
                    </small>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('slip_image');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');

        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('รองรับเฉพาะไฟล์ JPG, JPEG และ PNG');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('ไฟล์ใหญ่เกินไป (สูงสุด 5MB)');
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                uploadPrompt.style.display = 'none';
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        // Student code lookup
        $('#student_code').on('input', function() {
            const studentCode = $(this).val();
            
            if (studentCode.length === 11) {
                // Check student color
                $.ajax({
                    url: 'ajax/get_student_color.php',
                    method: 'POST',
                    data: { student_code: studentCode },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#colorDisplay').html(
                                '<div class="d-flex align-items-center">' +
                                '<span class="color-preview" style="background-color: ' + response.data.color_code + '"></span>' +
                                '<strong>' + response.data.color_name + '</strong>' +
                                '</div>' +
                                '<small class="text-muted">ปีการศึกษา ' + response.data.academic_year + '/' + response.data.semester + '</small>'
                            );
                            $('#studentColorInfo').show();
                            $('#noColorInfo').hide();
                        } else {
                            $('#studentColorInfo').hide();
                            $('#noColorInfo').show().html(
                                '<span class="text-danger">' + response.message + '</span>'
                            );
                        }
                    },
                    error: function() {
                        $('#studentColorInfo').hide();
                        $('#noColorInfo').show().html(
                            '<span class="text-danger">เกิดข้อผิดพลาดในการตรวจสอบข้อมูล</span>'
                        );
                    }
                });
            } else {
                $('#studentColorInfo').hide();
                $('#noColorInfo').show().html('กรอกรหัสนักเรียนเพื่อดูข้อมูลสี');
            }
        });

        // Set default transfer date to today
        document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];

        // Form validation
        $('#uploadForm').on('submit', function(e) {
            const studentCode = $('#student_code').val();
            const amount = $('#amount').val();
            const transferDate = $('#transfer_date').val();
            const file = $('#slip_image')[0].files[0];

            if (!studentCode || studentCode.length !== 11) {
                e.preventDefault();
                alert('กรุณากรอกรหัสนักเรียน 11 หลัก');
                return;
            }

            if (!amount || amount <= 0) {
                e.preventDefault();
                alert('กรุณากรอกจำนวนเงินที่ถูกต้อง');
                return;
            }

            if (!transferDate) {
                e.preventDefault();
                alert('กรุณาเลือกวันที่โอนเงิน');
                return;
            }

            if (!file) {
                e.preventDefault();
                alert('กรุณาอัพโหลดรูปสลิป');
                return;
            }

            // Disable submit button to prevent double submission
            $('#submitBtn').prop('disabled', true).html(
                '<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัพโหลด...'
            );
        });
    </script>
</body>
</html>