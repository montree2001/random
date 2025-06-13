<?php
// admin/payments.php
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

// บันทึกการจ่ายเงิน
if (isset($_POST['add_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
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
                $payment_method, 
                $receipt_number, 
                $notes, 
                $_SESSION['admin_id']
            ]);
            
            $message = 'บันทึกการจ่ายเงินเรียบร้อยแล้ว';
            $message_type = 'success';
            
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

// ค้นหา
$search = $_GET['search'] ?? '';
$payment_method_filter = $_GET['payment_method_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ดึงข้อมูลการจ่ายเงิน
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
                 sc.color_name, sc.color_code,
                 CONCAT(admin.title, admin.first_name, ' ', admin.last_name) as recorded_by_name
          FROM sport_color_payments p
          JOIN students s ON p.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN classes c ON s.current_class_id = c.class_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                   AND ssc.academic_year_id = p.academic_year_id AND ssc.is_active = 1
          LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
          LEFT JOIN admin_users admin ON p.recorded_by = admin.admin_id
          WHERE $where_clause
          ORDER BY p.payment_date DESC, p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// สรุปยอดเงิน
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

// ดึงรายชื่อนักเรียนที่มีสี
$query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name,
                 c.level, d.department_name, c.group_number, sc.color_name
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
        .amount-highlight {
            font-size: 1.25rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar">
                <div class="p-3 text-white">
                    <h4><i class="fas fa-medal"></i> กีฬาสี</h4>
                    <p class="mb-0">วิทยาลัยการอาชีพปราสาท</p>
                    <hr>
                    <p class="mb-0">สวัสดี, <?php echo $_SESSION['admin_name']; ?></p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i> หน้าหลัก
                    </a>
                    <a class="nav-link" href="colors.php">
                        <i class="fas fa-palette"></i> จัดการสี
                    </a>
                    <a class="nav-link" href="assign_colors.php">
                        <i class="fas fa-users"></i> จัดสีให้นักเรียน
                    </a>
                    <a class="nav-link" href="random_colors.php">
                        <i class="fas fa-random"></i> สุ่มสี
                    </a>
                    <a class="nav-link active" href="payments.php">
                        <i class="fas fa-money-bill"></i> บันทึกการจ่ายเงิน
                    </a>
                    <a class="nav-link" href="athletes.php">
                        <i class="fas fa-running"></i> นักกีฬา
                    </a>
                    <a class="nav-link" href="competitions.php">
                        <i class="fas fa-trophy"></i> การแข่งขัน
                    </a>
                    <a class="nav-link" href="schedules.php">
                        <i class="fas fa-calendar"></i> ตารางแข่ง
                    </a>
                    <hr>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 p-4"><!-- Main Content -->
           <div class="col-md-9 p-4">
               <div class="d-flex justify-content-between align-items-center mb-4">
                   <h2><i class="fas fa-money-bill"></i> บันทึกการจ่ายเงิน</h2>
                   <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                       <i class="fas fa-plus"></i> บันทึกการจ่ายเงิน
                   </button>
               </div>
               
               <?php if ($message): ?>
                   <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                       <?php echo $message; ?>
                       <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                   </div>
               <?php endif; ?>
               
               <!-- สรุปยอดเงิน -->
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
                               <i class="fas fa-credit-card fa-2x mb-2"></i>
                               <h5>โอนเงิน</h5>
                               <div class="amount-highlight"><?php echo number_format($summary['transfer_amount'], 2); ?> บาท</div>
                               <small><?php echo number_format($summary['transfer_count']); ?> ครั้ง</small>
                           </div>
                       </div>
                   </div>
               </div>
               
               <!-- ฟิลเตอร์ -->
               <div class="card mb-4">
                   <div class="card-body">
                       <form method="GET" class="row g-3">
                           <div class="col-md-3">
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
                                   <option value="อื่นๆ" <?php echo $payment_method_filter === 'อื่นๆ' ? 'selected' : ''; ?>>อื่นๆ</option>
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
                           <div class="col-md-3 d-flex align-items-end">
                               <button type="submit" class="btn btn-primary me-2">
                                   <i class="fas fa-search"></i> ค้นหา
                               </button>
                               <a href="payments.php" class="btn btn-secondary me-2">
                                   <i class="fas fa-refresh"></i> ล้าง
                               </a>
                               <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                   <i class="fas fa-file-excel"></i> Excel
                               </button>
                           </div>
                       </form>
                   </div>
               </div>
               
               <!-- รายการการจ่ายเงิน -->
               <div class="card">
                   <div class="card-header d-flex justify-content-between align-items-center">
                       <h5><i class="fas fa-list"></i> รายการการจ่ายเงิน (<?php echo count($payments); ?> รายการ)</h5>
                       <div>
                           <span class="text-muted">ยอดรวมในหน้านี้: </span>
                           <strong class="text-primary">
                               <?php 
                               $page_total = array_sum(array_column($payments, 'amount'));
                               echo number_format($page_total, 2); 
                               ?> บาท
                           </strong>
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
                                       <th>เลขใบเสร็จ</th>
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
                                               <?php else: ?>
                                                   <span class="text-muted">-</span>
                                               <?php endif; ?>
                                           </td>
                                           <td>
                                               <strong class="text-success"><?php echo number_format($payment['amount'], 2); ?> บาท</strong>
                                           </td>
                                           <td>
                                               <span class="badge <?php 
                                                   echo $payment['payment_method'] === 'เงินสด' ? 'bg-success' : 
                                                       ($payment['payment_method'] === 'โอนเงิน' ? 'bg-info' : 'bg-secondary'); 
                                               ?>">
                                                   <?php echo $payment['payment_method']; ?>
                                               </span>
                                           </td>
                                           <td>
                                               <?php if ($payment['receipt_number']): ?>
                                                   <code><?php echo $payment['receipt_number']; ?></code>
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
                                               <div class="btn-group" role="group">
                                                   <button type="button" class="btn btn-sm btn-outline-primary" 
                                                           onclick="editPayment(<?php echo htmlspecialchars(json_encode($payment)); ?>)"
                                                           title="แก้ไข">
                                                       <i class="fas fa-edit"></i>
                                                   </button>
                                                   <a href="?delete=<?php echo $payment['payment_id']; ?>" 
                                                      class="btn btn-sm btn-outline-danger"
                                                      onclick="return confirm('ต้องการลบรายการนี้?')"
                                                      title="ลบ">
                                                       <i class="fas fa-trash"></i>
                                                   </a>
                                               </div>
                                           </td>
                                       </tr>
                                   <?php endforeach; ?>
                                   
                                   <?php if (empty($payments)): ?>
                                       <tr>
                                           <td colspan="11" class="text-center py-4">
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
   
   <!-- Add Payment Modal -->
   <div class="modal fade" id="addPaymentModal" tabindex="-1">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title"><i class="fas fa-plus"></i> บันทึกการจ่ายเงิน</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <form method="POST" id="paymentForm">
                   <div class="modal-body">
                       <div class="row">
                           <div class="col-md-6">
                               <div class="mb-3">
                                   <label class="form-label">เลือกนักเรียน <span class="text-danger">*</span></label>
                                   <select class="form-select" name="student_id" id="studentSelect" required>
                                       <option value="">-- เลือกนักเรียน --</option>
                                       <?php foreach ($students_with_colors as $student): ?>
                                           <option value="<?php echo $student['student_id']; ?>" 
                                                   data-color="<?php echo $student['color_name']; ?>"
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
                                   </div>
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">วันที่จ่าย <span class="text-danger">*</span></label>
                                   <input type="date" class="form-control" name="payment_date" 
                                          value="<?php echo date('Y-m-d'); ?>" required>
                               </div>
                           </div>
                           
                           <div class="col-md-6">
                               <div class="mb-3">
                                   <label class="form-label">วิธีการจ่าย <span class="text-danger">*</span></label>
                                   <select class="form-select" name="payment_method" required>
                                       <option value="">-- เลือกวิธีการจ่าย --</option>
                                       <option value="เงินสด">เงินสด</option>
                                       <option value="โอนเงิน">โอนเงิน</option>
                                       <option value="อื่นๆ">อื่นๆ</option>
                                   </select>
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">เลขที่ใบเสร็จ</label>
                                   <input type="text" class="form-control" name="receipt_number" 
                                          placeholder="เลขที่ใบเสร็จ (ถ้ามี)">
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">หมายเหตุ</label>
                                   <textarea class="form-control" name="notes" rows="3" 
                                             placeholder="บันทึกเพิ่มเติม"></textarea>
                               </div>
                           </div>
                       </div>
                       
                       <!-- แสดงข้อมูลสรุป -->
                       <div id="paymentSummary" class="alert alert-info d-none">
                           <h6><i class="fas fa-info-circle"></i> สรุปการจ่ายเงิน</h6>
                           <div id="summaryContent"></div>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                       <button type="submit" name="add_payment" class="btn btn-primary">
                           <i class="fas fa-save"></i> บันทึก
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
       // อัปเดตข้อมูลนักเรียน
       function updateStudentInfo() {
           const studentSelect = document.getElementById('studentSelect');
           const studentInfo = document.getElementById('studentInfo');
           const selectedOption = studentSelect.options[studentSelect.selectedIndex];
           
           if (selectedOption && selectedOption.value) {
               const color = selectedOption.getAttribute('data-color');
               const classInfo = selectedOption.getAttribute('data-class');
               
               studentInfo.innerHTML = `
                   <i class="fas fa-info-circle text-info"></i> 
                   ชั้นเรียน: <strong>${classInfo}</strong> | 
                   สี: <strong>${color}</strong>
               `;
               updatePaymentSummary();
           } else {
               studentInfo.innerHTML = '';
               document.getElementById('paymentSummary').classList.add('d-none');
           }
       }
       
       // อัปเดตสรุปการจ่ายเงิน
       function updatePaymentSummary() {
           const studentSelect = document.getElementById('studentSelect');
           const amountInput = document.querySelector('input[name="amount"]');
           const paymentMethodSelect = document.querySelector('select[name="payment_method"]');
           const paymentDateInput = document.querySelector('input[name="payment_date"]');
           
           const selectedStudent = studentSelect.options[studentSelect.selectedIndex];
           
           if (selectedStudent && selectedStudent.value && amountInput.value && 
               paymentMethodSelect.value && paymentDateInput.value) {
               
               const summary = document.getElementById('paymentSummary');
               const content = document.getElementById('summaryContent');
               
               content.innerHTML = `
                   <div class="row">
                       <div class="col-md-6">
                           <strong>นักเรียน:</strong> ${selectedStudent.text.split(' (')[0]}<br>
                           <strong>สี:</strong> ${selectedStudent.getAttribute('data-color')}
                       </div>
                       <div class="col-md-6">
                           <strong>จำนวนเงิน:</strong> ${parseFloat(amountInput.value).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท<br>
                           <strong>วิธีการจ่าย:</strong> ${paymentMethodSelect.value}<br>
                           <strong>วันที่:</strong> ${new Date(paymentDateInput.value).toLocaleDateString('th-TH')}
                       </div>
                   </div>
               `;
               
               summary.classList.remove('d-none');
           } else {
               document.getElementById('paymentSummary').classList.add('d-none');
           }
       }
       
       // แก้ไขการจ่ายเงิน
       function editPayment(payment) {
           const modal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
           const form = document.getElementById('paymentForm');
           
           // เปลี่ยนหัวข้อโมดอล
           document.querySelector('#addPaymentModal .modal-title').innerHTML = 
               '<i class="fas fa-edit"></i> แก้ไขการจ่ายเงิน';
           
           // ตั้งค่าข้อมูลในฟอร์ม
           form.querySelector('select[name="student_id"]').value = payment.student_id;
           form.querySelector('input[name="amount"]').value = payment.amount;
           form.querySelector('input[name="payment_date"]').value = payment.payment_date;
           form.querySelector('select[name="payment_method"]').value = payment.payment_method;
           form.querySelector('input[name="receipt_number"]').value = payment.receipt_number || '';
           form.querySelector('textarea[name="notes"]').value = payment.notes || '';
           
           // เพิ่ม hidden input สำหรับ payment_id
           let paymentIdInput = form.querySelector('input[name="payment_id"]');
           if (!paymentIdInput) {
               paymentIdInput = document.createElement('input');
               paymentIdInput.type = 'hidden';
               paymentIdInput.name = 'payment_id';
               form.appendChild(paymentIdInput);
           }
           paymentIdInput.value = payment.payment_id;
           
           // เปลี่ยนปุ่มส่ง
           const submitBtn = form.querySelector('button[type="submit"]');
           submitBtn.innerHTML = '<i class="fas fa-save"></i> อัปเดต';
           submitBtn.setAttribute('name', 'update_payment');
           
           updateStudentInfo();
           modal.show();
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
                   if (index < 10) { // ไม่รวมคอลัมน์จัดการ
                       row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
                   }
               });
               if (row.length > 0) {
                   csv.push(row.join(','));
               }
           });
           
           // ดาวน์โหลดไฟล์
           const csvContent = "\uFEFF" + csv.join('\n'); // เพิ่ม BOM สำหรับ UTF-8
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
       
       // Event Listeners
       document.addEventListener('DOMContentLoaded', function() {
           // อัปเดตข้อมูลนักเรียนเมื่อเลือก
           document.getElementById('studentSelect').addEventListener('change', updateStudentInfo);
           
           // อัปเดตสรุปเมื่อเปลี่ยนข้อมูล
           document.querySelector('input[name="amount"]').addEventListener('input', updatePaymentSummary);
           document.querySelector('select[name="payment_method"]').addEventListener('change', updatePaymentSummary);
           document.querySelector('input[name="payment_date"]').addEventListener('change', updatePaymentSummary);
           
           // รีเซ็ตฟอร์มเมื่อปิดโมดอล
           document.getElementById('addPaymentModal').addEventListener('hidden.bs.modal', function() {
               const form = document.getElementById('paymentForm');
               form.reset();
               
               // รีเซ็ตหัวข้อและปุ่ม
               document.querySelector('#addPaymentModal .modal-title').innerHTML = 
                   '<i class="fas fa-plus"></i> บันทึกการจ่ายเงิน';
               
               const submitBtn = form.querySelector('button[type="submit"]');
               submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึก';
               submitBtn.setAttribute('name', 'add_payment');
               
               // ลบ payment_id ถ้ามี
               const paymentIdInput = form.querySelector('input[name="payment_id"]');
               if (paymentIdInput) {
                   paymentIdInput.remove();
               }
               
               // ซ่อนข้อมูลสรุป
               document.getElementById('studentInfo').innerHTML = '';
               document.getElementById('paymentSummary').classList.add('d-none');
           });
           
           // ตั้งค่าวันที่เป็นวันปัจจุบัน
           document.querySelector('input[name="payment_date"]').value = new Date().toISOString().split('T')[0];
       });
   </script>
</body>
</html>