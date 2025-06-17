<?php
// student_payment_upload.php - หน้าสำหรับนักเรียนอัพโหลดสลิป
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// ดึงข้อมูลสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();

// ประมวลผลการอัพโหลดสลิป
if (isset($_POST['upload_slip'])) {
    $student_code = trim($_POST['student_code']);
    $color_id = $_POST['color_id'];
    $amount = $_POST['amount'];
    $transfer_date = $_POST['transfer_date'];
    $transfer_time = $_POST['transfer_time'];
    $bank_from = trim($_POST['bank_from']);
    $bank_to = trim($_POST['bank_to']);
    $ref_number = trim($_POST['ref_number']);
    $notes = trim($_POST['notes']);
    
    // ตรวจสอบรหัสนักเรียน
    $query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.student_code = ? AND s.status = 'กำลังศึกษา'";
    $stmt = $db->prepare($query);
    $stmt->execute([$student_code]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $message = 'ไม่พบรหัสนักเรียน ' . $student_code . ' ในระบบ';
        $message_type = 'danger';
    } else {
        // จัดการอัพโหลดไฟล์
        if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] == 0) {
            $upload_dir = 'uploads/payment_slips/';
            
            // สร้างโฟลเดอร์ถ้าไม่มี
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_types)) {
                $message = 'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPG, JPEG, PNG, GIF';
                $message_type = 'danger';
            } else if ($_FILES['slip_image']['size'] > 5000000) { // 5MB
                $message = 'ขนาดไฟล์ใหญ่เกินไป (สูงสุด 5MB)';
                $message_type = 'danger';
            } else {
                $new_filename = 'slip_' . $student_code . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['slip_image']['tmp_name'], $upload_path)) {
                    try {
                        // บันทึกข้อมูลสลิปลงฐานข้อมูล
                        $query = "INSERT INTO payment_slips 
                                 (student_id, color_id, academic_year_id, amount, transfer_date, transfer_time, 
                                  slip_image, bank_from, bank_to, ref_number, notes, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            $student['student_id'],
                            $color_id,
                            $current_year['academic_year_id'],
                            $amount,
                            $transfer_date,
                            $transfer_time,
                            $new_filename,
                            $bank_from,
                            $bank_to,
                            $ref_number,
                            $notes
                        ]);
                        
                        $message = 'อัพโหลดสลิปเรียบร้อยแล้ว รอการตรวจสอบจากเจ้าหน้าที่';
                        $message_type = 'success';
                        
                        // รีเซ็ตฟอร์ม
                        $_POST = array();
                        
                    } catch (Exception $e) {
                        unlink($upload_path); // ลบไฟล์ที่อัพโหลดไว้
                        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'ไม่สามารถอัพโหลดไฟล์ได้';
                    $message_type = 'danger';
                }
            }
        } else {
            $message = 'กรุณาเลือกไฟล์สลิป';
            $message_type = 'warning';
        }
    }
}

// ตรวจสอบสถานะสลิปของนักเรียน
$slips = [];
if (isset($_POST['check_status'])) {
    $check_student_code = trim($_POST['check_student_code']);
    
    $query = "SELECT ps.*, sc.color_name, sc.color_code, s.student_code, 
                     CONCAT(s.title, u.first_name, ' ', u.last_name) as student_name
              FROM payment_slips ps
              JOIN students s ON ps.student_id = s.student_id
              JOIN users u ON s.user_id = u.user_id
              JOIN sport_colors sc ON ps.color_id = sc.color_id
              WHERE s.student_code = ? AND ps.academic_year_id = ?
              ORDER BY ps.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$check_student_code, $current_year['academic_year_id']]);
    $slips = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพโหลดสลิปการโอนเงิน - ระบบกีฬาสี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        .card {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .color-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .slip-preview {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-approved { background-color: #28a745; color: #fff; }
        .status-rejected { background-color: #dc3545; color: #fff; }
        
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
        .step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        .step:last-child::before {
            display: none;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            position: relative;
            z-index: 2;
            font-weight: bold;
        }
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step.completed::before {
            background: #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <h1><i class="fas fa-receipt me-2"></i>อัพโหลดสลิปการโอนเงิน</h1>
            <p class="lead">ระบบอัพโหลดสลิปการจ่ายค่าบำรุงสี ปีการศึกษา <?php echo $current_year['year']; ?>/<?php echo $current_year['semester']; ?></p>
        </div>
    </div>

    <div class="container my-5">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- ฟอร์มอัพโหลดสลิป -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>อัพโหลดสลิปการโอนเงิน</h5>
                    </div>
                    <div class="card-body">
                        <!-- Step Indicator -->
                        <div class="step-indicator mb-4">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <small>กรอกข้อมูล</small>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <small>อัพโหลดสลิป</small>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <small>รอการตรวจสอบ</small>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <small>เสร็จสิ้น</small>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">รหัสนักเรียน <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="student_code" required 
                                               placeholder="เช่น 68100010001" maxlength="11"
                                               value="<?php echo isset($_POST['student_code']) ? htmlspecialchars($_POST['student_code']) : ''; ?>">
                                        <div class="form-text">กรอกรหัสนักเรียน 11 หลัก</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">สี <span class="text-danger">*</span></label>
                                        <select class="form-select" name="color_id" required>
                                            <option value="">-- เลือกสี --</option>
                                            <?php foreach ($colors as $color): ?>
                                                <option value="<?php echo $color['color_id']; ?>" 
                                                        <?php echo (isset($_POST['color_id']) && $_POST['color_id'] == $color['color_id']) ? 'selected' : ''; ?>>
                                                    <?php echo $color['color_name']; ?> (<?php echo number_format($color['fee_amount']); ?> บาท)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="amount" step="0.01" min="0" required
                                               value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">วันที่โอน <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="transfer_date" required
                                               value="<?php echo isset($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">เวลาโอน</label>
                                        <input type="time" class="form-control" name="transfer_time"
                                               value="<?php echo isset($_POST['transfer_time']) ? $_POST['transfer_time'] : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ธนาคารต้นทาง</label>
                                        <input type="text" class="form-control" name="bank_from" 
                                               placeholder="เช่น ธนาคารกรุงไทย"
                                               value="<?php echo isset($_POST['bank_from']) ? htmlspecialchars($_POST['bank_from']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ธนาคารปลายทาง</label>
                                        <input type="text" class="form-control" name="bank_to" 
                                               placeholder="เช่น ธนาคารกสิกรไทย"
                                               value="<?php echo isset($_POST['bank_to']) ? htmlspecialchars($_POST['bank_to']) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">หมายเลขอ้างอิง</label>
                                <input type="text" class="form-control" name="ref_number" 
                                       placeholder="หมายเลขอ้างอิงจากสลิป"
                                       value="<?php echo isset($_POST['ref_number']) ? htmlspecialchars($_POST['ref_number']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">รูปสลิป <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="slip_image" accept="image/*" required id="slipFile">
                                <div class="form-text">อนุญาตไฟล์: JPG, JPEG, PNG, GIF (สูงสุด 5MB)</div>
                                
                                <!-- พรีวิวรูป -->
                                <div id="imagePreview" class="mt-3" style="display: none;">
                                    <img id="previewImg" class="slip-preview" alt="ตัวอย่างสลิป">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">หมายเหตุ</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="upload_slip" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i>อัพโหลดสลิป
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลบัญชีและตรวจสอบสถานะ -->
            <div class="col-lg-4">
                <!-- ข้อมูลบัญชี -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-university me-2"></i>ข้อมูลบัญชีสำหรับโอน</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($colors as $color): ?>
                            <div class="mb-3 p-3 border rounded">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="color-badge" style="background-color: <?php echo $color['color_code']; ?>"></span>
                                    <strong><?php echo $color['color_name']; ?></strong>
                                </div>
                                <?php if ($color['bank_name']): ?>
                                    <small class="text-muted d-block">ธนาคาร: <?php echo $color['bank_name']; ?></small>
                                    <small class="text-muted d-block">เลขที่: <?php echo $color['bank_account_number']; ?></small>
                                    <small class="text-muted d-block">ชื่อบัญชี: <?php echo $color['bank_account_name']; ?></small>
                                    <small class="text-success d-block mt-1"><strong><?php echo number_format($color['fee_amount']); ?> บาท</strong></small>
                                <?php else: ?>
                                    <small class="text-muted">ไม่มีข้อมูลบัญชี</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ตรวจสอบสถานะ -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-search me-2"></i>ตรวจสอบสถานะสลิป</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" name="check_student_code" 
                                       placeholder="รหัสนักเรียน" maxlength="11">
                                <button type="submit" name="check_status" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($slips)): ?>
                            <div class="mt-3">
                                <h6>รายการสลิป:</h6>
                                <?php foreach ($slips as $slip): ?>
                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>
                                                <span class="color-badge" style="background-color: <?php echo $slip['color_code']; ?>"></span>
                                                <?php echo $slip['color_name']; ?>
                                            </small>
                                            <span class="status-badge status-<?php echo $slip['status']; ?>">
                                                <?php 
                                                echo $slip['status'] === 'pending' ? 'รอตรวจสอบ' : 
                                                    ($slip['status'] === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ'); 
                                                ?>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block">
                                            <?php echo number_format($slip['amount']); ?> บาท - 
                                            <?php echo date('d/m/Y', strtotime($slip['transfer_date'])); ?>
                                        </small>
                                        <?php if ($slip['verification_notes']): ?>
                                            <small class="text-info d-block">หมายเหตุ: <?php echo htmlspecialchars($slip['verification_notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (isset($_POST['check_status'])): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-search fa-2x mb-2"></i>
                                <p>ไม่พบข้อมูลสลิป</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // พรีวิวรูปภาพ
        document.getElementById('slipFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('imagePreview').style.display = 'none';
            }
        });

        // อัปเดต Step Indicator
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const steps = document.querySelectorAll('.step');
            steps[0].classList.remove('active');
            steps[0].classList.add('completed');
            steps[1].classList.add('active');
        });

        // ตรวจสอบรหัสนักเรียน
        document.querySelector('input[name="student_code"]').addEventListener('input', function(e) {
            const value = e.target.value.replace(/\D/g, ''); // เฉพาะตัวเลข
            e.target.value = value;
        });
    </script>
</body>
</html>