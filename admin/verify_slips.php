<?php
// verify_slips.php - หน้าตรวจสอบสลิปของแอดมิน
require_once '../config/database.php';
session_start();

// ตรวจสอบสิทธิ์แอดมิน
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: login.php');
//     exit;
// }

$database = new Database();
$db = $database->getConnection();

// จัดการการอัปเดตสถานะสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_slip'])) {
    $slip_id = $_POST['slip_id'];
    $action = $_POST['action']; // approve, reject
    $verification_notes = $_POST['verification_notes'] ?? '';
    $verified_by = $_SESSION['admin_id'] ?? 1; // ใช้ admin_id จาก session

    try {
        $db->beginTransaction();
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $update_query = "UPDATE payment_slips 
                        SET status = ?, 
                            verified_by = ?, 
                            verified_at = NOW(), 
                            verification_notes = ? 
                        WHERE slip_id = ?";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$status, $verified_by, $verification_notes, $slip_id]);
        
        $db->commit();
        $success_message = ($action === 'approve') ? 'อนุมัติสลิปเรียบร้อยแล้ว' : 'ปฏิเสธสลิปเรียบร้อยแล้ว';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ดึงปีการศึกษาปัจจุบัน
$current_academic_year_query = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$current_academic_year_stmt = $db->prepare($current_academic_year_query);
$current_academic_year_stmt->execute();
$current_academic_year = $current_academic_year_stmt->fetch();

if (!$current_academic_year) {
    $error_message = 'ไม่พบปีการศึกษาปัจจุบัน กรุณาตั้งค่าปีการศึกษาปัจจุบัน';
}

// ตัวกรองและการค้นหา
$status_filter = $_GET['status_filter'] ?? 'pending';
$color_filter = $_GET['color_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// สร้าง WHERE clause
$where_conditions = [];
$params = [];

if ($current_academic_year) {
    $where_conditions[] = "ps.academic_year_id = ?";
    $params[] = $current_academic_year['academic_year_id'];
}

if ($status_filter !== 'all') {
    $where_conditions[] = "ps.status = ?";
    $params[] = $status_filter;
}

if ($color_filter) {
    $where_conditions[] = "ps.color_id = ?";
    $params[] = $color_filter;
}

if ($date_from) {
    $where_conditions[] = "ps.transfer_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "ps.transfer_date <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(s.student_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR ps.ref_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ดึงข้อมูลสลิป
$slips_query = "
    SELECT 
        ps.*,
        s.student_code,
        s.title as student_title,
        u.first_name,
        u.last_name,
        c.color_name,
        c.color_code,
        ay.year as academic_year,
        ay.semester,
        cl.level,
        d.department_name,
        cl.group_number,
        admin_u.first_name as verified_by_first_name,
        admin_u.last_name as verified_by_last_name
    FROM payment_slips ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN colors c ON ps.color_id = c.color_id
    JOIN academic_years ay ON ps.academic_year_id = ay.academic_year_id
    LEFT JOIN classes cl ON s.current_class_id = cl.class_id
    LEFT JOIN departments d ON cl.department_id = d.department_id
    LEFT JOIN users admin_u ON ps.verified_by = admin_u.user_id
    $where_clause
    ORDER BY ps.created_at DESC
";

$slips_stmt = $db->prepare($slips_query);
$slips_stmt->execute($params);
$slips = $slips_stmt->fetchAll();

// นับจำนวนตามสถานะ
$status_counts_query = "
    SELECT 
        ps.status,
        COUNT(*) as count,
        COALESCE(SUM(ps.amount), 0) as total_amount
    FROM payment_slips ps
    " . ($current_academic_year ? "WHERE ps.academic_year_id = ?" : "") . "
    GROUP BY ps.status
";
$status_counts_stmt = $db->prepare($status_counts_query);
if ($current_academic_year) {
    $status_counts_stmt->execute([$current_academic_year['academic_year_id']]);
} else {
    $status_counts_stmt->execute();
}

$status_counts = [];
$status_amounts = [];
while ($row = $status_counts_stmt->fetch()) {
    $status_counts[$row['status']] = $row['count'];
    $status_amounts[$row['status']] = $row['total_amount'];
}

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
    <title>ตรวจสอบสลิปการโอนเงิน - ระบบกีฬาสี</title>
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
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .slip-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .slip-thumbnail:hover {
            transform: scale(1.1);
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d1edff;
            color: #084298;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .verify-modal .modal-dialog {
            max-width: 800px;
        }
        .slip-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 10px;
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
                    <h2><i class="fas fa-receipt"></i> ตรวจสอบสลิปการโอนเงิน</h2>
                    <?php if ($current_academic_year): ?>
                    <div class="text-muted">
                        ปีการศึกษา <?php echo $current_academic_year['year']; ?> ภาคเรียนที่ <?php echo $current_academic_year['semester']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- สถิติรวม -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center bg-warning text-dark">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h5>รอตรวจสอบ</h5>
                                <h3><?php echo number_format($status_counts['pending'] ?? 0); ?></h3>
                                <small><?php echo number_format($status_amounts['pending'] ?? 0, 0); ?> บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <i class="fas fa-check fa-2x mb-2"></i>
                                <h5>อนุมัติแล้ว</h5>
                                <h3><?php echo number_format($status_counts['approved'] ?? 0); ?></h3>
                                <small><?php echo number_format($status_amounts['approved'] ?? 0, 0); ?> บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-danger text-white">
                            <div class="card-body">
                                <i class="fas fa-times fa-2x mb-2"></i>
                                <h5>ปฏิเสธ</h5>
                                <h3><?php echo number_format($status_counts['rejected'] ?? 0); ?></h3>
                                <small><?php echo number_format($status_amounts['rejected'] ?? 0, 0); ?> บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <i class="fas fa-money-bill fa-2x mb-2"></i>
                                <h5>ยอดรวม</h5>
                                <h3><?php echo number_format(array_sum($status_counts)); ?></h3>
                                <small><?php echo number_format(array_sum($status_amounts), 0); ?> บาท</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ฟิลเตอร์ -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="รหัส/ชื่อนักเรียน" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">สถานะ</label>
                                <select class="form-select" name="status_filter">
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">สี</label>
                                <select class="form-select" name="color_filter">
                                    <option value="">ทุกสี</option>
                                    <?php foreach ($colors as $color): ?>
                                        <option value="<?php echo $color['color_id']; ?>" 
                                                <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($color['color_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">วันที่เริ่มต้น</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">วันที่สิ้นสุด</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                                <a href="verify_slips.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> ล้าง
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- รายการสลิป -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> รายการสลิปการโอนเงิน (<?php echo count($slips); ?> รายการ)</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> ส่งออก Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="slipsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>วันที่ส่ง</th>
                                        <th>นักเรียน</th>
                                        <th>ชั้นเรียน</th>
                                        <th>สี</th>
                                        <th>จำนวนเงิน</th>
                                        <th>วันที่โอน</th>
                                        <th>สลิป</th>
                                        <th>สถานะ</th>
                                        <th>ผู้ตรวจสอบ</th>
                                        <th width="120">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($slips)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-receipt fa-3x text-muted"></i>
                                            <p class="mt-2 text-muted">ไม่พบข้อมูลสลิป</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($slips as $slip): ?>
                                        <tr class="status-<?php echo $slip['status']; ?>">
                                            <td>
                                                <small><?php echo date('d/m/Y H:i', strtotime($slip['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($slip['student_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars(($slip['student_title'] ?? '') . $slip['first_name'] . ' ' . $slip['last_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($slip['level']): ?>
                                                <small><?php echo htmlspecialchars($slip['level'] . ' ' . ($slip['department_name'] ?? '') . ' ' . ($slip['group_number'] ?? '')); ?></small>
                                                <?php else: ?>
                                                <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="color-badge" style="background-color: <?php echo $slip['color_code']; ?>"></span>
                                                <?php echo htmlspecialchars($slip['color_name']); ?>
                                            </td>
                                            <td>
                                                <strong class="text-primary">
                                                    <?php echo number_format($slip['amount'], 2); ?> บาท
                                                </strong>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($slip['transfer_date'])); ?>
                                                <?php if ($slip['transfer_time']): ?>
                                                    <br><small><?php echo date('H:i', strtotime($slip['transfer_time'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <img src="../uploads/slips/<?php echo htmlspecialchars($slip['slip_image']); ?>" 
                                                     class="slip-thumbnail" 
                                                     onclick="showSlipModal('<?php echo htmlspecialchars($slip['slip_image']); ?>', '<?php echo htmlspecialchars($slip['student_code']); ?>')"
                                                     alt="สลิป">
                                            </td>
                                            <td>
                                                <?php if ($slip['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">รอตรวจสอบ</span>
                                                <?php elseif ($slip['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">อนุมัติแล้ว</span>
                                                    <?php if ($slip['verified_at']): ?>
                                                        <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($slip['verified_at'])); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">ปฏิเสธ</span>
                                                    <?php if ($slip['verified_at']): ?>
                                                        <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($slip['verified_at'])); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php 
                                                    if ($slip['verified_by_first_name']) {
                                                        echo htmlspecialchars($slip['verified_by_first_name'] . ' ' . $slip['verified_by_last_name']);
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewSlipDetail(<?php echo htmlspecialchars(json_encode($slip)); ?>)"
                                                            title="ดูรายละเอียด">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($slip['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                onclick="verifySlip(<?php echo $slip['slip_id']; ?>, 'approve')"
                                                                title="อนุมัติ">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="verifySlip(<?php echo $slip['slip_id']; ?>, 'reject')"
                                                                title="ปฏิเสธ">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Slip Image Modal -->
    <div class="modal fade" id="slipImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">สลิปการโอนเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalSlipImage" class="slip-preview" alt="สลิป">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Slip Detail Modal -->
    <div class="modal fade verify-modal" id="slipDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">รายละเอียดสลิป</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="slipDetailContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyModalTitle">ตรวจสอบสลิป</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="verifyForm">
                    <div class="modal-body">
                        <input type="hidden" name="slip_id" id="verifySlipId">
                        <input type="hidden" name="action" id="verifyAction">
                        
                        <div class="alert" id="verifyAlert">
                            <div id="verifyMessage"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุการตรวจสอบ</label>
                            <textarea class="form-control" name="verification_notes" rows="3" 
                                      placeholder="บันทึกเหตุผลหรือข้อสังเกต"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="verify_slip" class="btn" id="verifySubmitBtn">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // แสดงภาพสลิป
        function showSlipModal(imageName, studentCode) {
            document.getElementById('modalSlipImage').src = '../uploads/slips/' + imageName;
            document.querySelector('#slipImageModal .modal-title').textContent = 'สลิปการโอนเงิน - ' + studentCode;
            new bootstrap.Modal(document.getElementById('slipImageModal')).show();
        }
        
        // ดูรายละเอียดสลิป
        function viewSlipDetail(slip) {
            const content = document.getElementById('slipDetailContent');
            
            const statusText = {
                'pending': 'รอตรวจสอบ',
                'approved': 'อนุมัติแล้ว',
                'rejected': 'ปฏิเสธ'
            };
            
            const statusClass = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'danger'
            };
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>ข้อมูลนักเรียน</h6>
                        <p><strong>รหัสนักเรียน:</strong> ${slip.student_code}</p>
                        <p><strong>ชื่อ-นามสกุล:</strong> ${(slip.student_title || '') + slip.first_name} ${slip.last_name}</p>
                        ${slip.level ? `<p><strong>ชั้นเรียน:</strong> ${slip.level} ${slip.department_name || ''} ${slip.group_number || ''}</p>` : ''}
                        <p><strong>สี:</strong> 
                            <span class="color-badge" style="background-color: ${slip.color_code}"></span>
                            ${slip.color_name}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>ข้อมูลการโอนเงิน</h6>
                        <p><strong>จำนวนเงิน:</strong> ${parseFloat(slip.amount).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</p>
                        <p><strong>วันที่โอน:</strong> ${new Date(slip.transfer_date).toLocaleDateString('th-TH')}</p>
                        ${slip.transfer_time ? `<p><strong>เวลาโอน:</strong> ${slip.transfer_time}</p>` : ''}
                        ${slip.bank_from ? `<p><strong>ธนาคารต้นทาง:</strong> ${slip.bank_from}</p>` : ''}
                        ${slip.bank_to ? `<p><strong>ธนาคารปลายทาง:</strong> ${slip.bank_to}</p>` : ''}
                        ${slip.ref_number ? `<p><strong>หมายเลขอ้างอิง:</strong> ${slip.ref_number}</p>` : ''}
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6>สถานะ</h6>
                        <span class="badge bg-${statusClass[slip.status]}">${statusText[slip.status]}</span>
                        ${slip.verified_at ? `<p class="mt-2 mb-0"><small>ตรวจสอบเมื่อ: ${new Date(slip.verified_at).toLocaleString('th-TH')}</small></p>` : ''}
                        ${slip.verified_by_first_name ? `<p class="mb-0"><small>โดย: ${slip.verified_by_first_name} ${slip.verified_by_last_name}</small></p>` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6>สลิปการโอนเงิน</h6>
                        <img src="../uploads/slips/${slip.slip_image}" 
                             class="img-fluid rounded" 
                             style="max-height: 200px; cursor: pointer;"
                             onclick="showSlipModal('${slip.slip_image}', '${slip.student_code}')"
                             alt="สลิป">
                    </div>
                </div>
                ${slip.notes ? `
                <hr>
                <h6>หมายเหตุจากนักเรียน</h6>
                <p>${slip.notes}</p>
                ` : ''}
                ${slip.verification_notes ? `
                <hr>
                <h6>หมายเหตุการตรวจสอบ</h6>
                <p>${slip.verification_notes}</p>
                ` : ''}
            `;
            
            new bootstrap.Modal(document.getElementById('slipDetailModal')).show();
        }
        
        // ตรวจสอบสลิป
        function verifySlip(slipId, action) {
            document.getElementById('verifySlipId').value = slipId;
            document.getElementById('verifyAction').value = action;
            
            const modal = document.getElementById('verifyModal');
            const title = document.getElementById('verifyModalTitle');
            const alert = document.getElementById('verifyAlert');
            const message = document.getElementById('verifyMessage');
            const submitBtn = document.getElementById('verifySubmitBtn');
            
            if (action === 'approve') {
                title.textContent = 'อนุมัติสลิป';
                alert.className = 'alert alert-success';
                message.innerHTML = '<i class="fas fa-check-circle me-2"></i>คุณต้องการอนุมัติสลิปนี้หรือไม่?';
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>อนุมัติ';
            } else {
                title.textContent = 'ปฏิเสธสลิป';
                alert.className = 'alert alert-danger';
                message.innerHTML = '<i class="fas fa-times-circle me-2"></i>คุณต้องการปฏิเสธสลิปนี้หรือไม่?<br><small>กรุณาระบุเหตุผลในหมายเหตุ</small>';
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-times me-2"></i>ปฏิเสธ';
            }
            
            new bootstrap.Modal(modal).show();
        }
        
        // ส่งออก Excel
        function exportToExcel() {
            const table = document.getElementById('slipsTable');
            let csv = [];
            
            const headers = [];
            table.querySelectorAll('thead th').forEach((th, index) => {
                if (index < 9) { // ไม่รวมคอลัมน์จัดการ
                    headers.push('"' + th.textContent.trim() + '"');
                }
            });
            csv.push(headers.join(','));
            
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach((td, index) => {
                    if (index < 9) { // ไม่รวมคอลัมน์จัดการ
                        let content = td.textContent.trim().replace(/"/g, '""');
                        content = content.replace(/[\u{1F300}-\u{1F6FF}]/gu, '');
                        row.push('"' + content + '"');
                    }
                });
                if (row.length > 0) {
                    csv.push(row.join(','));
                }
            });
            
            const csvContent = "\uFEFF" + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'payment_slips_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // รีเซ็ตฟอร์มเมื่อปิดโมดอล
        document.getElementById('verifyModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('verifyForm').reset();
        });
        
        // Auto refresh for pending slips (every 30 seconds)
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status_filter') === 'pending' || !urlParams.get('status_filter')) {
                // Only refresh if viewing pending slips and no manual search/filter
                if (!urlParams.get('search') && !urlParams.get('date_from')) {
                    window.location.reload();
                }
            }
        }, 30000);
    </script>
</body>
</html>