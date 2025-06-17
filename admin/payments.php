<?php
// admin/payments.php (ปรับปรุงใหม่)
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

// อนุมัติสลิป
if (isset($_POST['approve_slip'])) {
    $slip_id = $_POST['slip_id'];
    
    try {
        $db->beginTransaction();
        
        // ดึงข้อมูลสลิป
        $slip_query = "SELECT * FROM payment_slips WHERE slip_id = ?";
        $slip_stmt = $db->prepare($slip_query);
        $slip_stmt->execute([$slip_id]);
        $slip = $slip_stmt->fetch();
        
        if ($slip && $slip['status'] === 'pending') {
            // อัปเดตสถานะสลิป
            $update_slip_query = "UPDATE payment_slips SET status = 'approved', verified_by = ?, verified_at = NOW() WHERE slip_id = ?";
            $update_slip_stmt = $db->prepare($update_slip_query);
            $update_slip_stmt->execute([$_SESSION['admin_id'], $slip_id]);
            
            // บันทึกการจ่ายเงินใน sport_color_payments
            $payment_query = "INSERT INTO sport_color_payments 
                             (student_id, academic_year_id, amount, payment_date, payment_method, receipt_number, notes, recorded_by, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $payment_stmt = $db->prepare($payment_query);
            $payment_stmt->execute([
                $slip['student_id'],
                $slip['academic_year_id'],
                $slip['amount'],
                $slip['transfer_date'],
                'โอนเงิน',
                $slip['ref_number'] ?: 'สลิป-' . $slip['slip_id'],
                'อนุมัติจากสลิป ID: ' . $slip['slip_id'] . ($slip['notes'] ? ' | ' . $slip['notes'] : ''),
                $_SESSION['admin_id']
            ]);
            
            $db->commit();
            $message = 'อนุมัติสลิปเรียบร้อยแล้ว';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// ปฏิเสธสลิป
if (isset($_POST['reject_slip'])) {
    $slip_id = $_POST['slip_id'];
    $reject_reason = trim($_POST['reject_reason']);
    
    try {
        // อัปเดตสถานะและเก็บเหตุผลในหมายเหตุ
        $query = "UPDATE payment_slips SET status = 'rejected', verified_by = ?, verified_at = NOW(), notes = CONCAT(COALESCE(notes, ''), ' | ปฏิเสธ: ', ?) WHERE slip_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['admin_id'], $reject_reason, $slip_id]);
        
        $message = 'ปฏิเสธสลิปเรียบร้อยแล้ว';
        $message_type = 'warning';
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// บันทึกการจ่ายเงินสด
if (isset($_POST['add_cash_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $receipt_number = trim($_POST['receipt_number']);
    $notes = trim($_POST['notes']);
    
    if (!empty($student_id) && !empty($amount) && !empty($payment_date)) {
        try {
            $query = "INSERT INTO sport_color_payments 
                     (student_id, academic_year_id, amount, payment_date, payment_method, receipt_number, notes, recorded_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $student_id, 
                $current_year['academic_year_id'], 
                $amount, 
                $payment_date, 
                'เงินสด', 
                $receipt_number, 
                $notes, 
                $_SESSION['admin_id']
            ]);
            
            $new_payment_id = $db->lastInsertId();
            $message = 'บันทึกการจ่ายเงินสดเรียบร้อยแล้ว';
            $message_type = 'success';
            $show_print_button = true;
            
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// ลบการจ่ายเงิน
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $payment_id = (int)$_GET['delete'];
    
    $query = "DELETE FROM sport_color_payments WHERE payment_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$payment_id])) {
        $message = 'ลบข้อมูลการจ่ายเงินเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// ดึงสลิปที่รออนุมัติ
$pending_slips_query = "
    SELECT ps.*, s.student_code, s.title, u.first_name, u.last_name,
           sc.color_name, sc.color_code, sc.fee_amount,
           c.level, d.department_name, c.group_number
    FROM payment_slips ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
             AND ssc.academic_year_id = ps.academic_year_id AND ssc.is_active = 1
    LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE ps.status = 'pending' AND ps.academic_year_id = ?
    ORDER BY ps.created_at ASC
";
$pending_slips_stmt = $db->prepare($pending_slips_query);
$pending_slips_stmt->execute([$current_year['academic_year_id']]);
$pending_slips = $pending_slips_stmt->fetchAll();

// ค้นหา
$search = $_GET['search'] ?? '';
$payment_method_filter = $_GET['payment_method_filter'] ?? '';
$color_filter = $_GET['color_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ดึงข้อมูลการจ่ายเงิน (รวมทั้งเงินสดและสลิป)
$where_conditions = ["p.academic_year_id = ?"];
$params = [$current_year['academic_year_id']];

if (!empty($search)) {
    $where_conditions[] = "(s.student_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.receipt_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($payment_method_filter)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $payment_method_filter;
}

if (!empty($color_filter)) {
    $where_conditions[] = "ssc.color_id = ? AND ssc.is_active = 1";
    $params[] = $color_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "p.payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "p.payment_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT p.*, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number,
                 sc.color_name, sc.color_code, sc.fee_amount,
                 CONCAT(admin.title, admin.first_name, ' ', admin.last_name) as recorded_by_name,
                 ps.slip_id, ps.slip_image, ps.status as slip_status
          FROM sport_color_payments p
          JOIN students s ON p.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = p.academic_year_id AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN admin_users admin ON p.recorded_by = admin.admin_id
          LEFT JOIN payment_slips ps ON p.receipt_number = ps.ref_number 
                   AND ps.student_id = s.student_id AND ps.status = 'approved'
          WHERE $where_clause
          ORDER BY p.payment_date DESC, p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// สรุปยอดเงินตามประเภท
$query = "SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            COUNT(CASE WHEN payment_method = 'เงินสด' THEN 1 END) as cash_count,
            SUM(CASE WHEN payment_method = 'เงินสด' THEN amount ELSE 0 END) as cash_amount,
            COUNT(CASE WHEN payment_method = 'โอนเงิน' THEN 1 END) as transfer_count,
            SUM(CASE WHEN payment_method = 'โอนเงิน' THEN amount ELSE 0 END) as transfer_amount
          FROM sport_color_payments 
          WHERE academic_year_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$summary = $stmt->fetch();

// สรุปยอดเงินตามสี
$query = "SELECT sc.color_id, sc.color_name, sc.color_code, sc.fee_amount,
                 COUNT(ssc.student_id) as total_students,
                 COUNT(p.payment_id) as paid_count,
                 SUM(p.amount) as total_received,
                 COUNT(ps.slip_id) as slip_count,
                 COUNT(CASE WHEN ps.status = 'pending' THEN 1 END) as pending_slips
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          LEFT JOIN sport_color_payments p ON ssc.student_id = p.student_id 
                   AND p.academic_year_id = ?
          LEFT JOIN payment_slips ps ON sc.color_id = ps.color_id 
                   AND ps.academic_year_id = ?
          WHERE sc.is_active = 1
          GROUP BY sc.color_id
          ORDER BY sc.color_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id'], $current_year['academic_year_id'], $current_year['academic_year_id']]);
$color_summary = $stmt->fetchAll();

// ดึงสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();

// ดึงรายชื่อนักเรียนที่มีสี
$query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number, sc.color_name, sc.color_code, sc.fee_amount
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                AND ssc.academic_year_id = ? AND ssc.is_active = 1
          JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          WHERE s.status = 'กำลังศึกษา'
          ORDER BY c.level, d.department_name, c.group_number, u.first_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$students_with_colors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการจ่ายเงิน - ระบบกีฬาสี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .color-summary-card {
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .color-summary-card:hover {
            transform: translateY(-2px);
        }
        .amount-highlight {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .slip-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
        }
        .payment-source-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
        .pending-slip-card {
            border-left: 4px solid #ffc107;
            background: #fff9c4;
        }
        .slip-large {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }
        .tabs-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #667eea;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .pending-badge {
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            color: white;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                    <h2><i class="fas fa-money-bill"></i> บันทึกการจ่ายเงิน</h2>
                    <div>
                        <?php if (count($pending_slips) > 0): ?>
                        <span class="badge pending-badge me-2">
                            <i class="fas fa-clock"></i> รออนุมัติ <?php echo count($pending_slips); ?> ใบ
                        </span>
                        <?php endif; ?>
                        <a href="verify_slips.php" class="btn btn-info me-2">
                            <i class="fas fa-receipt"></i> ตรวจสอบสลิป
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="fas fa-plus"></i> บันทึกเงินสด
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <?php if (isset($show_print_button) && $show_print_button && isset($new_payment_id)): ?>
                            <div class="mt-3">
                                <div class="alert alert-success border-0 bg-light-success">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <strong>พิมพ์ใบเสร็จ:</strong> 
                                    ไปที่แท็บ "รายการการจ่ายเงิน" แล้วคลิกปุ่ม 
                                    <span class="badge bg-success">🖨️</span> 
                                    เพื่อพิมพ์ใบเสร็จ
                                </div>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- สรุปยอดเงินรวม -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-body text-center">
                                <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                <h5>ยอดรวมทั้งหมด</h5>
                                <div class="amount-highlight"><?php echo number_format($summary['total_amount'], 2); ?> บาท</div>
                                <small>จากการจ่าย <?php echo number_format($summary['total_payments']); ?> ครั้ง</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                                <h5>เงินสด</h5>
                                <div class="amount-highlight"><?php echo number_format($summary['cash_amount'], 2); ?> บาท</div>
                                <small><?php echo number_format($summary['cash_count']); ?> ครั้ง</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                                <h5>โอนเงิน</h5>
                                <div class="amount-highlight"><?php echo number_format($summary['transfer_amount'], 2); ?> บาท</div>
                                <small><?php echo number_format($summary['transfer_count']); ?> ครั้ง</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- สรุปยอดเงินตามสี -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="fas fa-chart-bar"></i> สรุปการรับเงินตามสี</h5>
                    </div>
                    <?php foreach ($color_summary as $color): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card color-summary-card h-100" style="border-left-color: <?php echo $color['color_code']; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="color-badge" style="background-color: <?php echo $color['color_code']; ?>"></span>
                                        <h6 class="mb-0"><?php echo $color['color_name']; ?></h6>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">สมาชิก</small>
                                            <div class="fw-bold"><?php echo $color['total_students']; ?> คน</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">จ่ายแล้ว</small>
                                            <div class="fw-bold text-success"><?php echo $color['paid_count']; ?> คน</div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">ยอดรับรวม</small>
                                        <div class="fw-bold text-primary">
                                            <?php echo number_format($color['total_received'], 2); ?> บาท
                                        </div>
                                    </div>
                                    <?php if ($color['pending_slips'] > 0): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-clock"></i> รอตรวจสอบ <?php echo $color['pending_slips']; ?> ใบ
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <?php 
                                        $expected_total = $color['total_students'] * $color['fee_amount'];
                                        $progress = $expected_total > 0 ? ($color['total_received'] / $expected_total * 100) : 0;
                                        ?>
                                        <div class="progress-bar" style="width: <?php echo min($progress, 100); ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        เป้าหมาย: <?php echo number_format($expected_total, 2); ?> บาท
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tabs -->
                <div class="tabs-container">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-slips-tab" data-bs-toggle="tab" 
                                    data-bs-target="#pending-slips" type="button" role="tab">
                                <i class="fas fa-clock me-2"></i>สลิปรออนุมัติ
                                <?php if (count($pending_slips) > 0): ?>
                                    <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_slips); ?></span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-records-tab" data-bs-toggle="tab" 
                                    data-bs-target="#payment-records" type="button" role="tab">
                                <i class="fas fa-list me-2"></i>รายการการจ่ายเงิน
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-4">
                        <!-- สลิปรออนุมัติ -->
                        <div class="tab-pane fade show active" id="pending-slips" role="tabpanel">
                            <?php if (count($pending_slips) > 0): ?>
                                <div class="row">
                                    <?php foreach ($pending_slips as $slip): ?>
                                        <div class="col-lg-6 mb-4">
                                            <div class="card pending-slip-card">
                                                <div class="card-header">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo $slip['student_code']; ?> - 
                                                            <?php echo $slip['title'] . $slip['first_name'] . ' ' . $slip['last_name']; ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($slip['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <div class="mb-2">
                                                                <strong>สี:</strong>
                                                                <?php if ($slip['color_name']): ?>
                                                                    <span class="color-badge" style="background-color: <?php echo $slip['color_code']; ?>"></span>
                                                                    <?php echo $slip['color_name']; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">ไม่พบข้อมูลสี</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="mb-2">
                                                                <strong>จำนวนเงิน:</strong> 
                                                                <span class="text-success fw-bold"><?php echo number_format($slip['amount'], 2); ?> บาท</span>
                                                            </div>
                                                            <div class="mb-2">
                                                                <strong>วันที่โอน:</strong> <?php echo date('d/m/Y', strtotime($slip['transfer_date'])); ?>
                                                                <?php if ($slip['transfer_time']): ?>
                                                                    เวลา <?php echo $slip['transfer_time']; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($slip['bank_from']): ?>
                                                                <div class="mb-2">
                                                                    <strong>ธนาคารต้นทาง:</strong> <?php echo $slip['bank_from']; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($slip['ref_number']): ?>
                                                                <div class="mb-2">
                                                                    <strong>หมายเลขอ้างอิง:</strong> <code><?php echo $slip['ref_number']; ?></code>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($slip['notes']): ?>
                                                                <div class="mb-2">
                                                                    <strong>หมายเหตุ:</strong> <?php echo htmlspecialchars($slip['notes']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-4 text-center">
                                                            <img src="../uploads/slips/<?php echo $slip['slip_image']; ?>" 
                                                                 class="slip-large border rounded" 
                                                                 onclick="showSlipModal('<?php echo $slip['slip_image']; ?>', '<?php echo $slip['student_code']; ?>')"
                                                                 alt="สลิป" style="cursor: pointer;">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-6">
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('ต้องการอนุมัติสลิปนี้?')">
                                                                <input type="hidden" name="slip_id" value="<?php echo $slip['slip_id']; ?>">
                                                                <button type="submit" name="approve_slip" class="btn btn-success btn-sm w-100">
                                                                    <i class="fas fa-check"></i> อนุมัติ
                                                                </button>
                                                            </form>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <button type="button" class="btn btn-danger btn-sm w-100" 
                                                                    onclick="showRejectModal(<?php echo $slip['slip_id']; ?>, '<?php echo $slip['student_code']; ?>')">
                                                                <i class="fas fa-times"></i> ปฏิเสธ
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                                    <h4 class="text-muted">ไม่มีสลิปรออนุมัติ</h4>
                                    <p class="text-muted">สลิปทั้งหมดได้รับการตรวจสอบแล้ว</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- รายการการจ่ายเงิน -->
                        <div class="tab-pane fade" id="payment-records" role="tabpanel">
                            <!-- ฟิลเตอร์ -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <input type="hidden" name="tab" value="payment-records">
                                        <div class="col-md-2">
                                            <label class="form-label">ค้นหา</label>
                                            <input type="text" class="form-control" name="search" 
                                                   placeholder="รหัส/ชื่อ/เลขใบเสร็จ" value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">วิธีการจ่าย</label>
                                            <select class="form-select" name="payment_method_filter">
                                                <option value="">ทั้งหมด</option>
                                                <option value="เงินสด" <?php echo $payment_method_filter === 'เงินสด' ? 'selected' : ''; ?>>เงินสด</option>
                                                <option value="โอนเงิน" <?php echo $payment_method_filter === 'โอนเงิน' ? 'selected' : ''; ?>>โอนเงิน</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">สี</label>
                                            <select class="form-select" name="color_filter">
                                                <option value="">ทุกสี</option>
                                                <?php foreach ($colors as $color): ?>
                                                    <option value="<?php echo $color['color_id']; ?>" 
                                                            <?php echo $color_filter == $color['color_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $color['color_name']; ?>
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
                                            <a href="payments.php" class="btn btn-secondary me-2">
                                                <i class="fas fa-refresh"></i> ล้าง
                                            </a>
                                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                                <i class="fas fa-file-excel"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- รายการการจ่ายเงิน -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-list"></i> รายการการจ่ายเงิน (<?php echo count($payments); ?> รายการ)</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="text-muted me-3">ยอดรวมในหน้านี้: </span>
                                        <strong class="text-primary me-3">
                                            <?php 
                                            $page_total = array_sum(array_column($payments, 'amount'));
                                            echo number_format($page_total, 2); 
                                            ?> บาท
                                        </strong>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-print"></i> พิมพ์/ส่งออก
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><h6 class="dropdown-header">เมนูพิมพ์และส่งออก</h6></li>
                                                <li><a class="dropdown-item" href="#" onclick="printAllReceipts()">
                                                    <i class="fas fa-print me-2"></i>เปิดใบเสร็จทั้งหมด
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportToExcel()">
                                                    <i class="fas fa-file-excel me-2"></i>ส่งออก Excel
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick="window.print()">
                                                    <i class="fas fa-print me-2"></i>พิมพ์หน้านี้
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="paymentsTable">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>วันที่จ่าย</th>
                                                    <th>รหัสนักเรียน</th>
                                                    <th>ชื่อ-นามสกุล</th>
                                                    <th>ชั้นเรียน</th>
                                                    <th>สี</th>
                                                    <th>จำนวนเงิน</th>
                                                    <th>วิธีการจ่าย</th>
                                                    <th>เลขใบเสร็จ/อ้างอิง</th>
                                                    <th>สลิป</th>
                                                    <th>ผู้บันทึก</th>
                                                    <th>หมายเหตุ</th>
                                                    <th width="100">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payments as $payment): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('H:i', strtotime($payment['created_at'])); ?></small>
                                                        </td>
                                                        <td><strong><?php echo $payment['student_code']; ?></strong></td>
                                                        <td><?php echo $payment['title'] . $payment['first_name'] . ' ' . $payment['last_name']; ?></td>
                                                        <td>
                                                            <small><?php echo $payment['level'] . ' ' . $payment['department_name'] . ' ' . $payment['group_number']; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['color_name']): ?>
                                                                <span class="color-badge" style="background-color: <?php echo $payment['color_code']; ?>"></span>
                                                                <?php echo $payment['color_name']; ?>
                                                                <?php if ($payment['amount'] == $payment['fee_amount']): ?>
                                                                    <br><span class="badge bg-success payment-source-badge">ถูกต้อง</span>
                                                                <?php else: ?>
                                                                    <br><span class="badge bg-warning payment-source-badge">ไม่ตรง</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong class="text-success"><?php echo number_format($payment['amount'], 2); ?> บาท</strong>
                                                            <?php if ($payment['color_name'] && $payment['amount'] != $payment['fee_amount']): ?>
                                                                <br><small class="text-muted">ควรเป็น <?php echo number_format($payment['fee_amount'], 2); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php 
                                                                echo $payment['payment_method'] === 'เงินสด' ? 'bg-success' : 'bg-info'; 
                                                            ?>">
                                                                <i class="fas fa-<?php echo $payment['payment_method'] === 'เงินสด' ? 'hand-holding-usd' : 'mobile-alt'; ?>"></i>
                                                                <?php echo $payment['payment_method']; ?>
                                                            </span>
                                                            <?php if ($payment['slip_id']): ?>
                                                                <br><span class="badge bg-primary payment-source-badge">จากสลิป</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['receipt_number']): ?>
                                                                <code><?php echo $payment['receipt_number']; ?></code>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['slip_image']): ?>
                                                                <img src="../uploads/slips/<?php echo $payment['slip_image']; ?>" 
                                                                     class="slip-thumbnail" 
                                                                     onclick="showSlipModal('<?php echo $payment['slip_image']; ?>', '<?php echo $payment['student_code']; ?>')"
                                                                     alt="สลิป" title="คลิกดูสลิป">
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo $payment['recorded_by_name']; ?></small>
                                                        </td>
                                                        <td>
                                                            <small><?php echo $payment['notes'] ? htmlspecialchars($payment['notes']) : '-'; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if (!$payment['slip_id']): // ถ้าไม่ใช่จากสลิป สามารถลบได้ ?>
                                                                <a href="?delete=<?php echo $payment['payment_id']; ?>" 
                                                                   class="btn btn-sm btn-outline-danger"
                                                                   onclick="return confirm('ต้องการลบรายการนี้?')"
                                                                   title="ลบ">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($payments)): ?>
                                                    <tr>
                                                        <td colspan="12" class="text-center py-4">
                                                            <i class="fas fa-money-bill fa-3x text-muted"></i>
                                                            <p class="mt-2 text-muted">ยังไม่มีข้อมูลการจ่ายเงิน</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Cash Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> บันทึกการจ่ายเงินสด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="paymentForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>หมายเหตุ:</strong> ฟอร์มนี้สำหรับบันทึกการรับเงินสดเท่านั้น 
                            สำหรับการโอนเงินให้นักเรียนส่งสลิปผ่านระบบ
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                                    <select class="form-select" name="student_id" id="studentSelect" required>
                                        <option value="">-- เลือกนักเรียน --</option>
                                        <?php foreach ($students_with_colors as $student): ?>
                                            <option value="<?php echo $student['student_id']; ?>" 
                                                    data-color="<?php echo $student['color_name']; ?>"
                                                    data-color-code="<?php echo $student['color_code']; ?>"
                                                    data-fee="<?php echo $student['fee_amount']; ?>"
                                                    data-class="<?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?>">
                                                <?php echo $student['student_code'] . ' - ' . $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                                (<?php echo $student['color_name']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="studentInfo" class="form-text"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                                        <span class="input-group-text">บาท</span>
                                        <button class="btn btn-outline-secondary" type="button" id="useStandardFee">
                                            ใช้ค่าบำรุงมาตรฐาน
                                        </button>
                                    </div>
                                    <div id="feeInfo" class="form-text"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">วันที่จ่าย <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">เลขที่ใบเสร็จ</label>
                                    <input type="text" class="form-control" name="receipt_number" 
                                           placeholder="เลขที่ใบเสร็จ (ถ้ามี)">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea class="form-control" name="notes" rows="4" 
                                              placeholder="บันทึกเพิ่มเติม เช่น การให้ส่วนลด หรือหมายเหตุพิเศษ"></textarea>
                                </div>
                                
                                <!-- แสดงข้อมูลสรุป -->
                                <div id="colorInfo" class="d-none">
                                    <div class="p-3 bg-light rounded">
                                        <h6><i class="fas fa-info-circle"></i> ข้อมูลสี</h6>
                                        <div id="colorDisplay"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- แสดงข้อมูลสรุป -->
                        <div id="paymentSummary" class="alert alert-primary d-none">
                            <h6><i class="fas fa-info-circle"></i> สรุปการจ่ายเงิน</h6>
                            <div id="summaryContent"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_cash_payment" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกการจ่ายเงินสด
                        </button>
                    </div>
                    
                    <!-- หมายเหตุสำหรับการพิมพ์ใบเสร็จ -->
                    <div class="alert alert-info mx-3 mb-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>การพิมพ์ใบเสร็จ:</strong> หลังบันทึกเรียบร้อยแล้ว ไปที่แท็บ "รายการการจ่ายเงิน" แล้วคลิกปุ่ม <span class="badge bg-success">🖨️</span> เพื่อพิมพ์ใบเสร็จ
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Slip Modal -->
    <div class="modal fade" id="rejectSlipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times"></i> ปฏิเสธสลิป</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="slip_id" id="rejectSlipId">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>หมายเหตุ:</strong> กรุณาระบุเหตุผลในการปฏิเสธสลิปนี้
                        </div>
                        <div class="mb-3">
                            <label class="form-label">เหตุผลในการปฏิเสธ <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reject_reason" rows="4" 
                                      placeholder="เช่น ภาพไม่ชัด, จำนวนเงินไม่ตรง, หมายเลขอ้างอิงผิด" required></textarea>
                        </div>
                        <div id="rejectStudentInfo"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="reject_slip" class="btn btn-danger">
                            <i class="fas fa-times"></i> ปฏิเสธสลิป
                        </button>
                    </div>
                </form>
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
                    <img id="modalSlipImage" class="img-fluid" style="max-height: 500px;" alt="สลิป">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // ตรวจสอบ URL parameter สำหรับแสดง tab ที่ถูกต้อง
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab === 'payment-records') {
                // เปลี่ยนไปแสดง tab รายการการจ่ายเงิน
                document.getElementById('pending-slips-tab').classList.remove('active');
                document.getElementById('payment-records-tab').classList.add('active');
                document.getElementById('pending-slips').classList.remove('show', 'active');
                document.getElementById('payment-records').classList.add('show', 'active');
            }
        });
        
        // แสดง modal ปฏิเสธสลิป
        function showRejectModal(slipId, studentCode) {
            document.getElementById('rejectSlipId').value = slipId;
            document.getElementById('rejectStudentInfo').innerHTML = `
                <div class="alert alert-info">
                    <strong>รหัสนักเรียน:</strong> ${studentCode}
                </div>
            `;
            new bootstrap.Modal(document.getElementById('rejectSlipModal')).show();
        }
        
        // อัปเดตข้อมูลนักเรียน
        function updateStudentInfo() {
            const studentSelect = document.getElementById('studentSelect');
            const studentInfo = document.getElementById('studentInfo');
            const colorInfo = document.getElementById('colorInfo');
            const colorDisplay = document.getElementById('colorDisplay');
            const feeInfo = document.getElementById('feeInfo');
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const color = selectedOption.getAttribute('data-color');
                const colorCode = selectedOption.getAttribute('data-color-code');
                const fee = selectedOption.getAttribute('data-fee');
                const classInfo = selectedOption.getAttribute('data-class');
                
                studentInfo.innerHTML = `
                    <i class="fas fa-info-circle text-info"></i> 
                    ชั้นเรียน: <strong>${classInfo}</strong>
                `;
                
                colorDisplay.innerHTML = `
                    <div class="d-flex align-items-center">
                        <span class="color-badge me-2" style="background-color: ${colorCode}"></span>
                        <div>
                            <strong>${color}</strong><br>
                            <small>ค่าบำรุงมาตรฐาน: ${parseFloat(fee).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</small>
                        </div>
                    </div>
                `;
                
                feeInfo.innerHTML = `
                    <i class="fas fa-money-bill text-success"></i> 
                    ค่าบำรุงมาตรฐานสำหรับ${color}: <strong>${parseFloat(fee).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</strong>
                `;
                
                colorInfo.classList.remove('d-none');
                document.getElementById('useStandardFee').setAttribute('data-fee', fee);
                updatePaymentSummary();
            } else {
                studentInfo.innerHTML = '';
                colorInfo.classList.add('d-none');
                feeInfo.innerHTML = '';
                document.getElementById('paymentSummary').classList.add('d-none');
            }
        }
        
        // ใช้ค่าบำรุงมาตรฐาน
        document.getElementById('useStandardFee').addEventListener('click', function() {
            const fee = this.getAttribute('data-fee');
            if (fee) {
                document.querySelector('input[name="amount"]').value = parseFloat(fee).toFixed(2);
                updatePaymentSummary();
            }
        });
        
        // อัปเดตสรุปการจ่ายเงิน
        function updatePaymentSummary() {
            const studentSelect = document.getElementById('studentSelect');
            const amountInput = document.querySelector('input[name="amount"]');
            const paymentDateInput = document.querySelector('input[name="payment_date"]');
            
            const selectedStudent = studentSelect.options[studentSelect.selectedIndex];
            
            if (selectedStudent && selectedStudent.value && amountInput.value && paymentDateInput.value) {
                const summary = document.getElementById('paymentSummary');
                const content = document.getElementById('summaryContent');
                
                const fee = selectedStudent.getAttribute('data-fee');
                const amount = parseFloat(amountInput.value);
                const standardFee = parseFloat(fee);
                
                let statusText = '';
                let statusClass = '';
                
                if (amount === standardFee) {
                    statusText = 'ตรงตามค่าบำรุงมาตรฐาน';
                    statusClass = 'text-success';
                } else if (amount > standardFee) {
                    statusText = `เกินค่าบำรุง ${(amount - standardFee).toFixed(2)} บาท`;
                    statusClass = 'text-info';
                } else {
                    statusText = `ต่ำกว่าค่าบำรุง ${(standardFee - amount).toFixed(2)} บาท`;
                    statusClass = 'text-warning';
                }
                
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>นักเรียน:</strong> ${selectedStudent.text.split(' (')[0]}<br>
                            <strong>สี:</strong> ${selectedStudent.getAttribute('data-color')}
                        </div>
                        <div class="col-md-6">
                            <strong>จำนวนเงิน:</strong> ${amount.toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท<br>
                            <strong>วันที่:</strong> ${new Date(paymentDateInput.value).toLocaleDateString('th-TH')}<br>
                            <span class="${statusClass}"><strong>${statusText}</strong></span>
                        </div>
                    </div>
                `;
                
                summary.classList.remove('d-none');
            } else {
                document.getElementById('paymentSummary').classList.add('d-none');
            }
        }
        
        // แสดงภาพสลิป
        function showSlipModal(imageName, studentCode) {
            document.getElementById('modalSlipImage').src = '../uploads/slips/' + imageName;
            document.querySelector('#slipImageModal .modal-title').textContent = 'สลิปการโอนเงิน - ' + studentCode;
            new bootstrap.Modal(document.getElementById('slipImageModal')).show();
        }
        
        // ส่งออก Excel
        function exportToExcel() {
            const table = document.getElementById('paymentsTable');
            let csv = [];
            
            // หัวตาราง
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push('"' + th.textContent.trim() + '"');
            });
            csv.push(headers.join(','));
            
            // ข้อมูล
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach((td, index) => {
                    if (index < 11) { // ไม่รวมคอลัมน์จัดการ
                        let content = td.textContent.trim().replace(/"/g, '""');
                        content = content.replace(/[\u{1F300}-\u{1F6FF}]/gu, '');
                        row.push('"' + content + '"');
                    }
                });
                if (row.length > 0) {
                    csv.push(row.join(','));
                }
            });
            
            // ดาวน์โหลดไฟล์
            const csvContent = "\uFEFF" + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'payment_records_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // พิมพ์ใบเสร็จทั้งหมด
        function printAllReceipts() {
            const receiptLinks = document.querySelectorAll('a[href*="print_receipt.php"]');
            
            if (receiptLinks.length === 0) {
                alert('ไม่มีรายการการจ่ายเงินให้พิมพ์');
                return;
            }
            
            if (confirm('ต้องการเปิดใบเสร็จทั้งหมด ' + receiptLinks.length + ' ใบ?\n\nจะเปิดหน้าต่างใหม่สำหรับแต่ละใบเสร็จ')) {
                let printedCount = 0;
                
                receiptLinks.forEach((link, index) => {
                    setTimeout(() => {
                        window.open(link.href, '_blank');
                        printedCount++;
                        
                        if (printedCount === receiptLinks.length) {
                            setTimeout(() => {
                                alert('เปิดใบเสร็จทั้งหมดแล้ว ' + printedCount + ' ใบ\nกรุณาพิมพ์จากแต่ละหน้าต่าง');
                            }, 1000);
                        }
                    }, index * 500); // หน่วงเวลาเพื่อไม่ให้เปิดพร้อมกันทุกหน้า
                });
            }
        }
        
        // พิมพ์ใบเสร็จทั้งหมด
        function printAllReceipts() {
            const receiptLinks = document.querySelectorAll('a[href*="print_receipt.php"]');
            
            if (receiptLinks.length === 0) {
                alert('ไม่มีรายการการจ่ายเงินให้พิมพ์');
                return;
            }
            
            if (confirm('ต้องการเปิดใบเสร็จทั้งหมด ' + receiptLinks.length + ' ใบ?\n\nจะเปิดหน้าต่างใหม่สำหรับแต่ละใบเสร็จ')) {
                let printedCount = 0;
                
                receiptLinks.forEach((link, index) => {
                    setTimeout(() => {
                        window.open(link.href, '_blank');
                        printedCount++;
                        
                        if (printedCount === receiptLinks.length) {
                            setTimeout(() => {
                                alert('เปิดใบเสร็จทั้งหมดแล้ว ' + printedCount + ' ใบ\nกรุณาพิมพ์จากแต่ละหน้าต่าง');
                            }, 1000);
                        }
                    }, index * 500); // หน่วงเวลาเพื่อไม่ให้เปิดพร้อมกันทุกหน้า
                });
            }
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // เปิดใช้งาน Tooltip
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            // Initialize Select2
            $('#studentSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#addPaymentModal'),
                placeholder: '-- ค้นหาและเลือกนักเรียน --',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "ไม่พบข้อมูลนักเรียน";
                    },
                    searching: function() {
                        return "กำลังค้นหา...";
                    },
                    inputTooShort: function() {
                        return "กรุณาพิมพ์อย่างน้อย 1 ตัวอักษร";
                    }
                },
                templateResult: function(data) {
                    if (!data.id) {
                        return data.text;
                    }
                    
                    const $option = $(data.element);
                    const colorName = $option.data('color');
                    const colorCode = $option.data('color-code');
                    const fee = $option.data('fee');
                    const classInfo = $option.data('class');
                    
                    const $result = $(
                        '<div class="d-flex align-items-center">' +
                            '<span class="color-badge me-2" style="background-color: ' + colorCode + '; width: 16px; height: 16px; border-radius: 50%; display: inline-block;"></span>' +
                            '<div>' +
                                '<div><strong>' + data.text.split(' (')[0] + '</strong></div>' +
                                '<small class="text-muted">' + classInfo + ' | ' + colorName + ' | ' + parseFloat(fee).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท</small>' +
                            '</div>' +
                        '</div>'
                    );
                    
                    return $result;
                },
                templateSelection: function(data) {
                    if (!data.id) {
                        return data.text;
                    }
                    
                    const $option = $(data.element);
                    const colorName = $option.data('color');
                    
                    return data.text.split(' (')[0] + ' (' + colorName + ')';
                }
            });
            
            // อัปเดตข้อมูลนักเรียนเมื่อเลือกใน Select2
            $('#studentSelect').on('select2:select', function(e) {
                updateStudentInfo();
            });
            
            $('#studentSelect').on('select2:clear', function(e) {
                updateStudentInfo();
            });
            
            // อัปเดตสรุปเมื่อเปลี่ยนข้อมูล
            document.querySelector('input[name="amount"]').addEventListener('input', updatePaymentSummary);
            document.querySelector('input[name="payment_date"]').addEventListener('change', updatePaymentSummary);
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            document.getElementById('addPaymentModal').addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('paymentForm');
                form.reset();
                
                // รีเซ็ต Select2
                $('#studentSelect').val(null).trigger('change');
                
                // ซ่อนข้อมูลสรุป
                document.getElementById('studentInfo').innerHTML = '';
                document.getElementById('colorInfo').classList.add('d-none');
                document.getElementById('feeInfo').innerHTML = '';
                document.getElementById('paymentSummary').classList.add('d-none');
            });
            
            // รีเซ็ตฟอร์มปฏิเสธเมื่อปิดโมดอล
            document.getElementById('rejectSlipModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('rejectForm').reset();
            });
            
            // ตั้งค่าวันที่เป็นวันปัจจุบัน
            document.querySelector('input[name="payment_date"]').value = new Date().toISOString().split('T')[0];
        });
    </script>
</body>
</html>