<?php
// admin/colors.php
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
    
    if (!empty($color_name) && !empty($color_code)) {
        $query = "INSERT INTO sport_colors (color_name, color_code, max_members) VALUES (:color_name, :color_code, :max_members)";
        $stmt = $db->prepare($query);
        
        try {
            $stmt->bindParam(':color_name', $color_name);
            $stmt->bindParam(':color_code', $color_code);
            $stmt->bindParam(':max_members', $max_members);
            
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
          COUNT(ssc.student_id) as student_count
          FROM sport_colors sc
          LEFT JOIN student_sport_colors ssc ON sc.color_id = ssc.color_id 
          LEFT JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id
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
                    <a class="nav-link active" href="colors.php">
                        <i class="fas fa-palette"></i> จัดการสี
                    </a>
                    <a class="nav-link" href="assign_colors.php">
                        <i class="fas fa-users"></i> จัดสีให้นักเรียน
                    </a>
                    <a class="nav-link" href="random_colors.php">
                        <i class="fas fa-random"></i> สุ่มสี
                    </a>
                    <a class="nav-link" href="payments.php">
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
                
                <!-- Colors Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>สี</th>
                                        <th>ชื่อสี</th>
                                        <th>รหัสสี</th>
                                        <th>จำนวนสมาชิก</th>
                                        <th>สมาชิกสูงสุด</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($colors as $color): ?>
                                        <tr>
                                            <td>
                                                <div class="color-preview" style="background-color: <?php echo $color['color_code']; ?>"></div>
                                            </td>
                                            <td><strong><?php echo $color['color_name']; ?></strong></td>
                                            <td><code><?php echo $color['color_code']; ?></code></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $color['student_count']; ?> คน</span>
                                            </td>
                                            <td>
                                                <?php echo $color['max_members'] ? $color['max_members'] . ' คน' : 'ไม่จำกัด'; ?>
                                            </td>
                                            <td>
                                                <?php if ($color['is_active']): ?>
                                                    <span class="badge bg-success">เปิดใช้งาน</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">ปิดใช้งาน</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="?toggle=<?php echo $color['color_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary"
                                                       onclick="return confirm('ต้องการเปลี่ยนสถานะ?')">
                                                        <i class="fas fa-toggle-on"></i>
                                                    </a>
                                                    
                                                    <?php if ($color['student_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $color['color_id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('ต้องการลบสีนี้?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($colors)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-palette fa-3x text-muted"></i>
                                                <p class="mt-2 text-muted">ยังไม่มีข้อมูลสี</p>
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
    
    <!-- Add Color Modal -->
    <div class="modal fade" id="addColorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มสีใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
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
</body>
</html>