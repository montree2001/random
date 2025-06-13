<?php
// student_check.php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$search_result = null;
$search_term = '';
$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// ค้นหาข้อมูลนักเรียน
if (isset($_POST['search_student']) || isset($_GET['search'])) {
    $search_term = trim($_POST['search_term'] ?? $_GET['search'] ?? '');
    
    if (!empty($search_term)) {
        // ค้นหาด้วยรหัสนักเรียนหรือชื่อ
        $query = "SELECT s.student_id, s.student_code, s.title, u.first_name, u.last_name, u.phone_number,
                         c.level, d.department_name, c.group_number,
                         sc.color_id, sc.color_name, sc.color_code, ssc.assigned_date,
                         CASE 
                            WHEN ssc.color_id IS NOT NULL THEN 'มีสีแล้ว'
                            ELSE 'ยังไม่ได้รับสี'
                         END as color_status
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN classes c ON s.current_class_id = c.class_id
                  LEFT JOIN departments d ON c.department_id = d.department_id
                  LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                           AND ssc.academic_year_id = ? AND ssc.is_active = 1
                  LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                  WHERE s.status = 'กำลังศึกษา' 
                  AND (s.student_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? 
                       OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
                  ORDER BY s.student_code";
        
        $search_param = "%$search_term%";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_year['academic_year_id'], $search_param, $search_param, $search_param, $search_param]);
        $search_result = $stmt->fetchAll();
        
        if (empty($search_result)) {
            $message = 'ไม่พบข้อมูลนักเรียนที่ค้นหา';
            $message_type = 'warning';
        }
    } else {
        $message = 'กรุณากรอกรหัสนักเรียนหรือชื่อที่ต้องการค้นหา';
        $message_type = 'warning';
    }
}

// ดึงสถิติสี
$query = "SELECT sc.color_name, sc.color_code, COUNT(ssc.student_id) as student_count
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
                   AND ssc.academic_year_id = ? AND ssc.is_active = 1
          WHERE sc.is_active = 1
          GROUP BY sc.color_id
          ORDER BY student_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$color_stats = $stmt->fetchAll();

// ดึงข้อมูลการจ่ายเงินของนักเรียน (ถ้าค้นหาแล้ว)
$payment_data = [];
if ($search_result && count($search_result) == 1) {
    $student_id = $search_result[0]['student_id'];
    $query = "SELECT * FROM sport_color_payments 
              WHERE student_id = ? AND academic_year_id = ?
              ORDER BY payment_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$student_id, $current_year['academic_year_id']]);
    $payment_data = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบสีกีฬาสี - วิทยาลัยการอาชีพปราสาท</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            max-width: 1200px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .search-section {
            padding: 2rem;
            background: white;
            border-radius: 15px;
            margin: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.3s;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
        }
        
        .color-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 15px;
            border: 3px solid #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,0.3);
        }
        
        .color-badge-small {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .search-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border: none;
        }
        
        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .student-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .payment-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .no-payment-badge {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .logo-section {
            margin-bottom: 1rem;
        }
        
        .logo-section i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header-section {
                border-radius: 15px 15px 0 0;
                padding: 1.5rem;
            }
            
            .result-card {
                margin: 0.5rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <div class="logo-section">
                    <i class="fas fa-medal"></i>
                </div>
                <h1><i class="fas fa-palette me-2"></i>ระบบตรวจสอบสีกีฬาสี</h1>
                <p class="mb-0">วิทยาลัยการอาชีพปราสาท</p>
                <p class="mt-2 mb-0">ปีการศึกษา <?php echo $current_year['year']; ?> ภาคเรียนที่ <?php echo $current_year['semester']; ?></p>
            </div>
            
            <!-- Search Section -->
            <div class="search-section">
                <div class="search-form">
                    <h4 class="text-center mb-4"><i class="fas fa-search me-2"></i>ค้นหาข้อมูลของคุณ</h4>
                    <form method="POST" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">รหัสนักเรียน หรือ ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control form-control-lg" name="search_term" 
                                   placeholder="กรอกรหัสนักเรียน หรือ ชื่อ-นามสกุล" 
                                   value="<?php echo htmlspecialchars($search_term); ?>" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                ตัวอย่าง: 12345678901 หรือ สมศักดิ์ ใจดี
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="search_student" class="btn btn-search w-100">
                                <i class="fas fa-search me-2"></i>ค้นหา
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> mt-3" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'warning' ? 'exclamation-triangle' : 'info-circle'; ?> me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Search Results -->
            <?php if ($search_result): ?>
                <?php foreach ($search_result as $student): ?>
                    <div class="result-card" style="border-left-color: <?php echo $student['color_code'] ?: '#ddd'; ?>">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="student-info">
                                    <h5 class="mb-3">
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        <?php echo $student['title'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                                    </h5>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <p class="mb-2">
                                                <strong><i class="fas fa-id-card me-2 text-info"></i>รหัสนักเรียน:</strong><br>
                                                <span class="fs-5 text-primary fw-bold"><?php echo $student['student_code']; ?></span>
                                            </p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-2">
                                                <strong><i class="fas fa-graduation-cap me-2 text-success"></i>ชั้นเรียน:</strong><br>
                                                <?php echo $student['level'] . ' ' . $student['department_name'] . ' ' . $student['group_number']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($student['phone_number']): ?>
                                        <p class="mb-0">
                                            <strong><i class="fas fa-phone me-2 text-warning"></i>เบอร์โทร:</strong>
                                            <?php echo $student['phone_number']; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- ข้อมูลการจ่ายเงิน -->
                                <?php if (!empty($payment_data)): ?>
                                    <div class="mt-3">
                                        <h6><i class="fas fa-money-bill me-2 text-success"></i>ประวัติการจ่ายเงิน</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>วันที่จ่าย</th>
                                                        <th>จำนวนเงิน</th>
                                                        <th>วิธีการจ่าย</th>
                                                        <th>เลขใบเสร็จ</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payment_data as $payment): ?>
                                                        <tr>
                                                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                                            <td class="text-success fw-bold"><?php echo number_format($payment['amount'], 2); ?> บาท</td>
                                                            <td><?php echo $payment['payment_method']; ?></td>
                                                            <td><?php echo $payment['receipt_number'] ?: '-'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-end">
                                            <span class="payment-badge">
                                                <i class="fas fa-check-circle me-1"></i>
                                                จ่ายเงินแล้ว ยอดรวม <?php echo number_format(array_sum(array_column($payment_data, 'amount')), 2); ?> บาท
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-end">
                                        <span class="no-payment-badge">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            ยังไม่ได้จ่ายเงินค่าบำรุงสี
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center">
                                    <?php if ($student['color_name']): ?>
                                        <div class="mb-3">
                                            <div class="color-badge mx-auto" 
                                                 style="background-color: <?php echo $student['color_code']; ?>"></div>
                                        </div>
                                        <h4 class="text-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <?php echo $student['color_name']; ?>
                                        </h4>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-calendar me-1"></i>
                                            ได้รับสีเมื่อ: <?php echo date('d/m/Y', strtotime($student['assigned_date'])); ?>
                                        </p>
                                        <div class="badge bg-success fs-6 px-3 py-2">
                                            <i class="fas fa-star me-1"></i>คุณได้รับสีแล้ว!
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <i class="fas fa-question-circle fa-5x text-muted"></i>
                                        </div>
                                        <h4 class="text-warning">
                                            <i class="fas fa-hourglass-half me-2"></i>
                                            ยังไม่ได้รับสี
                                        </h4>
                                        <div class="badge bg-warning text-dark fs-6 px-3 py-2">
                                            <i class="fas fa-clock me-1"></i>รอการจัดสี
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Color Statistics -->
            <?php if (!empty($color_stats)): ?>
                <div class="search-section">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-chart-pie me-2"></i>สถิติการแบ่งสี ปีการศึกษา <?php echo $current_year['year']; ?>
                    </h4>
                    <div class="row">
                        <?php foreach ($color_stats as $stat): ?>
                            <div class="col-md-2 col-sm-4 col-6 mb-3">
                                <div class="stats-card">
                                    <div class="color-badge-small mx-auto mb-2" 
                                         style="background-color: <?php echo $stat['color_code']; ?>; width: 40px; height: 40px;"></div>
                                    <h6 class="mb-1"><?php echo $stat['color_name']; ?></h6>
                                    <div class="badge bg-primary"><?php echo number_format($stat['student_count']); ?> คน</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="text-center p-4 text-muted">
                <p class="mb-2">
                    <i class="fas fa-school me-2"></i>
                    วิทยาลัยการอาชีพปราสาท
                </p>
                <p class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    หากมีข้อสงสัยเกี่ยวกับการจัดสี โปรดติดต่อฝ่ายกิจกรรมนักเรียน
                </p>
                <p class="mt-2 mb-0">
                    <a href="admin/login.php" class="text-decoration-none text-muted">
                        <i class="fas fa-cog me-1"></i>สำหรับผู้ดูแลระบบ
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto focus on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search_term"]');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });
        
        // Add enter key listener
        document.querySelector('input[name="search_term"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('button[name="search_student"]').click();
            }
        });
        
        // Add animation for result cards
        if (document.querySelectorAll('.result-card').length > 0) {
            document.querySelectorAll('.result-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
    </script>
</body>
</html>