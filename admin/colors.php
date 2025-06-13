<?php
// admin/colors.php (ปรับปรุงแล้ว)
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

// เพิ่มสีใหม่
if (isset($_POST['add_color'])) {
    $color_name = trim($_POST['color_name']);
    $color_code = trim($_POST['color_code']);
    $max_members = !empty($_POST['max_members']) ? (int)$_POST['max_members'] : null;
    $fee_amount = !empty($_POST['fee_amount']) ? (float)$_POST['fee_amount'] : 0.00;
    $bank_name = trim($_POST['bank_name']);
    $bank_account_number = trim($_POST['bank_account_number']);
    $bank_account_name = trim($_POST['bank_account_name']);
    
    if (!empty($color_name) && !empty($color_code)) {
        $query = "INSERT INTO sport_colors (color_name, color_code, max_members, fee_amount, bank_name, bank_account_number, bank_account_name) 
                  VALUES (:color_name, :color_code, :max_members, :fee_amount, :bank_name, :bank_account_number, :bank_account_name)";
        $stmt = $db->prepare($query);
        
        try {
            $stmt->bindParam(':color_name', $color_name);
            $stmt->bindParam(':color_code', $color_code);
            $stmt->bindParam(':max_members', $max_members);
            $stmt->bindParam(':fee_amount', $fee_amount);
            $stmt->bindParam(':bank_name', $bank_name);
            $stmt->bindParam(':bank_account_number', $bank_account_number);
            $stmt->bindParam(':bank_account_name', $bank_account_name);
            
            if ($stmt->execute()) {
                $message = 'เพิ่มสีใหม่เรียบร้อยแล้ว';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'ชื่อสีนี้มีอยู่แล้ว';
                $message_type = 'danger';
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// แก้ไขสี
if (isset($_POST['edit_color'])) {
    $color_id = $_POST['color_id'];
    $color_name = trim($_POST['color_name']);
    $color_code = trim($_POST['color_code']);
    $max_members = !empty($_POST['max_members']) ? (int)$_POST['max_members'] : null;
    $fee_amount = !empty($_POST['fee_amount']) ? (float)$_POST['fee_amount'] : 0.00;
    $bank_name = trim($_POST['bank_name']);
    $bank_account_number = trim($_POST['bank_account_number']);
    $bank_account_name = trim($_POST['bank_account_name']);
    
    if (!empty($color_id) && !empty($color_name) && !empty($color_code)) {
        $query = "UPDATE sport_colors 
                  SET color_name = :color_name, color_code = :color_code, max_members = :max_members, 
                      fee_amount = :fee_amount, bank_name = :bank_name, bank_account_number = :bank_account_number, 
                      bank_account_name = :bank_account_name
                  WHERE color_id = :color_id";
        $stmt = $db->prepare($query);
        
        try {
            $stmt->bindParam(':color_id', $color_id);
            $stmt->bindParam(':color_name', $color_name);
            $stmt->bindParam(':color_code', $color_code);
            $stmt->bindParam(':max_members', $max_members);
            $stmt->bindParam(':fee_amount', $fee_amount);
            $stmt->bindParam(':bank_name', $bank_name);
            $stmt->bindParam(':bank_account_number', $bank_account_number);
            $stmt->bindParam(':bank_account_name', $bank_account_name);
            
            if ($stmt->execute()) {
                $message = 'แก้ไขข้อมูลสีเรียบร้อยแล้ว';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'ชื่อสีนี้มีอยู่แล้ว';
                $message_type = 'danger';
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// ลบสี
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $color_id = (int)$_GET['delete'];
    
    // ตรวจสอบว่ามีนักเรียนใช้สีนี้หรือไม่
    $check_query = "SELECT COUNT(*) as count FROM student_sport_colors WHERE color_id = :color_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':color_id', $color_id);
    $check_stmt->execute();
    $usage = $check_stmt->fetch();
    
    if ($usage['count'] > 0) {
        $message = 'ไม่สามารถลบสีนี้ได้ เนื่องจากมีนักเรียนใช้งานอยู่';
        $message_type = 'warning';
    } else {
        $query = "DELETE FROM sport_colors WHERE color_id = :color_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':color_id', $color_id);
        
        if ($stmt->execute()) {
            $message = 'ลบสีเรียบร้อยแล้ว';
            $message_type = 'success';
        }
    }
}

// เปิด/ปิดการใช้งานสี
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $color_id = (int)$_GET['toggle'];
    
    $query = "UPDATE sport_colors SET is_active = NOT is_active WHERE color_id = :color_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':color_id', $color_id);
    
    if ($stmt->execute()) {
        $message = 'เปลี่ยนสถานะเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// ดึงข้อมูลสีทั้งหมด
$query = "SELECT sc.*, 
          COUNT(ssc.student_id) as student_count,
          COUNT(ps.slip_id) as slip_count,
          COUNT(CASE WHEN ps.status = 'approved' THEN 1 END) as approved_slips,
          SUM(CASE WHEN ps.status = 'approved' THEN ps.amount ELSE 0 END) as total_received
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
          LEFT JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id
          LEFT JOIN payment_slips ps ON sc.color_id = ps.color_id AND ps.academic_year_id = ay.academic_year_id
          WHERE ay.is_active = 1 OR ay.is_active IS NULL
          GROUP BY sc.color_id
          ORDER BY sc.color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสี - ระบบกีฬาสี</title>
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
        .color-preview {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .bank-info {
            font-size: 0.85rem;
            line-height: 1.3;
        }
        .color-card {
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .color-card:hover {
            transform: translateY(-2px);
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
                    <h2><i class="fas fa-palette"></i> จัดการสี</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addColorModal">
                        <i class="fas fa-plus"></i> เพิ่มสีใหม่
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Colors Grid -->
                <div class="row">
                    <?php foreach ($colors as $color): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card color-card h-100" style="border-left-color: <?php echo $color['color_code']; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="color-preview me-3" style="background-color: <?php echo $color['color_code']; ?>"></div>
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?php echo $color['color_name']; ?></h5>
                                            <small class="text-muted"><?php echo $color['color_code']; ?></small>
                                            <?php if (!$color['is_active']): ?>
                                                <span class="badge bg-secondary ms-2">ปิดใช้งาน</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- ข้อมูลค่าบำรุง -->
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">ค่าบำรุง</small>
                                                <div class="fw-bold text-success">
                                                    <?php echo number_format($color['fee_amount'], 2); ?> บาท
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">สมาชิก</small>
                                                <div class="fw-bold">
                                                    <?php echo $color['student_count']; ?> คน
                                                    <?php if ($color['max_members']): ?>
                                                        <small class="text-muted">/ <?php echo $color['max_members']; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ข้อมูลธนาคาร -->
                                    <?php if ($color['bank_name']): ?>
                                        <div class="mb-3 bank-info">
                                            <div class="bg-light p-2 rounded">
                                                <div class="fw-bold"><?php echo $color['bank_name']; ?></div>
                                                <div><?php echo $color['bank_account_number']; ?></div>
                                                <div class="text-muted"><?php echo $color['bank_account_name']; ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- สถิติการรับเงิน -->
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">สลิปที่ส่ง</small>
                                                <div class="fw-bold text-info"><?php echo $color['slip_count']; ?> ใบ</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">อนุมัติแล้ว</small>
                                                <div class="fw-bold text-primary"><?php echo $color['approved_slips']; ?> ใบ</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">ยอดรับรวม</small>
                                            <div class="fw-bold text-success">
                                                <?php echo number_format($color['total_received'], 2); ?> บาท
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                onclick="editColor(<?php echo htmlspecialchars(json_encode($color)); ?>)"
                                                title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <a href="?toggle=<?php echo $color['color_id']; ?>" 
                                           class="btn btn-outline-<?php echo $color['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                           onclick="return confirm('ต้องการเปลี่ยนสถานะ?')"
                                           title="<?php echo $color['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>">
                                            <i class="fas fa-<?php echo $color['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                        </a>
                                        
                                        <?php if ($color['student_count'] == 0): ?>
                                            <a href="?delete=<?php echo $color['color_id']; ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('ต้องการลบสีนี้?')"
                                               title="ลบ">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="verify_slips.php?color_filter=<?php echo $color['color_id']; ?>" 
                                           class="btn btn-outline-info btn-sm"
                                           title="ดูสลิป">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($colors)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-palette fa-5x text-muted"></i>
                                <h4 class="mt-3 text-muted">ยังไม่มีข้อมูลสี</h4>
                                <p class="text-muted">เริ่มต้นด้วยการเพิ่มสีใหม่</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addColorModal">
                                    <i class="fas fa-plus"></i> เพิ่มสีแรก
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Color Modal -->
    <div class="modal fade" id="addColorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มสีใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="colorForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ชื่อสี <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="color_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">รหัสสี (Hex) <span class="text-danger">*</span></label>
                                    <input type="color" class="form-control" name="color_code" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">จำนวนสมาชิกสูงสุด</label>
                                    <input type="number" class="form-control" name="max_members" min="1" placeholder="ไม่จำกัด">
                                    <div class="form-text">ปล่อยว่างหากไม่ต้องการจำกัด</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ค่าบำรุงสี (บาท) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="fee_amount" step="0.01" min="0" value="150.00" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3"><i class="fas fa-university"></i> ข้อมูลบัญชีรับเงิน</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">ธนาคาร</label>
                                    <select class="form-select" name="bank_name">
                                        <option value="">เลือกธนาคาร</option>
                                        <option value="ธนาคารกรุงไทย">ธนาคารกรุงไทย</option>
                                        <option value="ธนาคารกสิกรไทย">ธนาคารกสิกรไทย</option>
                                        <option value="ธนาคารไทยพาณิชย์">ธนาคารไทยพาณิชย์</option>
                                        <option value="ธนาคารกรุงเทพ">ธนาคารกรุงเทพ</option>
                                        <option value="ธนาคารกรุงศรีอยุธยา">ธนาคารกรุงศรีอยุธยา</option>
                                        <option value="ธนาคารทหารไทยธนชาต">ธนาคารทหารไทยธนชาต</option>
                                        <option value="ธนาคารออมสิน">ธนาคารออมสิน</option>
                                        <option value="ธนาคารอาคารสงเคราะห์">ธนาคารอาคารสงเคราะห์</option>
                                        <option value="ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร">ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">เลขที่บัญชี</label>
                                    <input type="text" class="form-control" name="bank_account_number" 
                                           placeholder="หมายเลขบัญชี">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ชื่อบัญชี</label>
                                    <input type="text" class="form-control" name="bank_account_name" 
                                           placeholder="ชื่อเจ้าของบัญชี"
                                           value="กิจกรรมนักเรียน วิทยาลัยการอาชีพปราสาท">
                                </div>
                            </div>
                        </div>
                        
                        <!-- แสดงตัวอย่างสี -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>ตัวอย่าง</h6>
                            <div class="d-flex align-items-center">
                                <div id="colorPreview" class="color-preview me-3" style="background-color: #ff0000"></div>
                                <div>
                                    <div class="fw-bold" id="colorNamePreview">ชื่อสี</div>
                                    <div class="text-muted" id="colorCodePreview">#ff0000</div>
                                    <div class="text-success" id="feePreview">150.00 บาท</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_color" class="btn btn-primary">เพิ่มสี</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // อัพเดตตัวอย่างสี
        function updateColorPreview() {
            const colorName = document.querySelector('input[name="color_name"]').value || 'ชื่อสี';
            const colorCode = document.querySelector('input[name="color_code"]').value || '#ff0000';
            const feeAmount = document.querySelector('input[name="fee_amount"]').value || '0.00';
            
            document.getElementById('colorPreview').style.backgroundColor = colorCode;
            document.getElementById('colorNamePreview').textContent = colorName;
            document.getElementById('colorCodePreview').textContent = colorCode;
            document.getElementById('feePreview').textContent = parseFloat(feeAmount).toFixed(2) + ' บาท';
        }
        
        // แก้ไขสี
        function editColor(color) {
            const modal = new bootstrap.Modal(document.getElementById('addColorModal'));
            const form = document.getElementById('colorForm');
            
            // เปลี่ยนหัวข้อโมดอล
            document.querySelector('#addColorModal .modal-title').innerHTML = 
                '<i class="fas fa-edit"></i> แก้ไขข้อมูลสี';
            
            // ตั้งค่าข้อมูลในฟอร์ม
            form.querySelector('input[name="color_name"]').value = color.color_name;
            form.querySelector('input[name="color_code"]').value = color.color_code;
            form.querySelector('input[name="max_members"]').value = color.max_members || '';
            form.querySelector('input[name="fee_amount"]').value = color.fee_amount || '0.00';
            form.querySelector('select[name="bank_name"]').value = color.bank_name || '';
            form.querySelector('input[name="bank_account_number"]').value = color.bank_account_number || '';
            form.querySelector('input[name="bank_account_name"]').value = color.bank_account_name || '';
            
            // เพิ่ม hidden input สำหรับ color_id
            let colorIdInput = form.querySelector('input[name="color_id"]');
            if (!colorIdInput) {
                colorIdInput = document.createElement('input');
                colorIdInput.type = 'hidden';
                colorIdInput.name = 'color_id';
                form.appendChild(colorIdInput);
            }
            colorIdInput.value = color.color_id;
            
            // เปลี่ยนปุ่มส่ง
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึกการแก้ไข';
            submitBtn.setAttribute('name', 'edit_color');
            
            updateColorPreview();
            modal.show();
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // อัพเดตตัวอย่างเมื่อเปลี่ยนข้อมูล
            document.querySelector('input[name="color_name"]').addEventListener('input', updateColorPreview);
            document.querySelector('input[name="color_code"]').addEventListener('input', updateColorPreview);
            document.querySelector('input[name="fee_amount"]').addEventListener('input', updateColorPreview);
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            document.getElementById('addColorModal').addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('colorForm');
                form.reset();
                
                // รีเซ็ตหัวข้อและปุ่ม
                document.querySelector('#addColorModal .modal-title').innerHTML = 
                    '<i class="fas fa-plus"></i> เพิ่มสีใหม่';
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> เพิ่มสี';
                submitBtn.setAttribute('name', 'add_color');
                
                // ลบ color_id ถ้ามี
                const colorIdInput = form.querySelector('input[name="color_id"]');
                if (colorIdInput) {
                    colorIdInput.remove();
                }
                
                // รีเซ็ตตัวอย่าง
                document.querySelector('input[name="fee_amount"]').value = '150.00';
                updateColorPreview();
            });
            
            // ตั้งค่าตัวอย่างเริ่มต้น
            updateColorPreview();
        });
    </script>
</body>
</html>