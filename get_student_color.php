<?php
// get_student_color.php - ตรวจสอบข้อมูลสีของนักเรียน (ไฟล์นี้ให้วางในโฟลเดอร์เดียวกับ payment_upload.php)
require_once 'config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['student_code'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $student_code = trim($_POST['student_code']);
    
    // ตรวจสอบรหัสนักเรียน
    if (strlen($student_code) !== 11 || !ctype_digit($student_code)) {
        echo json_encode(['success' => false, 'message' => 'รหัสนักเรียนต้องเป็นตัวเลข 11 หลัก']);
        exit;
    }
    
    // ค้นหาข้อมูลนักเรียนและสีที่ได้รับมอบหมาย
    $query = "
        SELECT 
            s.student_id,
            s.student_code,
            u.first_name,
            u.last_name,
            c.color_id,
            c.color_name,
            c.color_code,
            ay.year as academic_year,
            ay.semester,
            sc.assignment_date,
            sc.assignment_method
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN student_colors sc ON s.student_id = sc.student_id AND sc.academic_year_id = (
            SELECT academic_year_id FROM academic_years WHERE is_active = 1 LIMIT 1
        )
        LEFT JOIN colors c ON sc.color_id = c.color_id
        LEFT JOIN academic_years ay ON sc.academic_year_id = ay.academic_year_id
        WHERE s.student_code = ? AND s.status = 'กำลังศึกษา'
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$student_code]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสนักเรียนหรือนักเรียนไม่ได้อยู่ในสถานะกำลังศึกษา']);
        exit;
    }
    
    if (!$student['color_id']) {
        echo json_encode(['success' => false, 'message' => 'นักเรียนยังไม่ได้รับการจัดสี']);
        exit;
    }
    
    // ตรวจสอบว่าเคยอัพโหลดสลิปแล้วหรือไม่
    $slip_check_query = "
        SELECT COUNT(*) as slip_count, 
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM payment_slips 
        WHERE student_id = ? AND academic_year_id = (
            SELECT academic_year_id FROM academic_years WHERE is_active = 1
        )
    ";
    $slip_stmt = $db->prepare($slip_check_query);
    $slip_stmt->execute([$student['student_id']]);
    $slip_info = $slip_stmt->fetch();
    
    $response = [
        'success' => true,
        'data' => [
            'student_id' => $student['student_id'],
            'student_code' => $student['student_code'],
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'color_id' => $student['color_id'],
            'color_name' => $student['color_name'],
            'color_code' => $student['color_code'],
            'academic_year' => $student['academic_year'],
            'semester' => $student['semester'],
            'assignment_date' => $student['assignment_date'],
            'assignment_method' => $student['assignment_method'],
            'slip_count' => $slip_info['slip_count'],
            'approved_slip_count' => $slip_info['approved_count']
        ]
    ];
    
    // เพิ่มข้อความเตือนถ้าเคยอัพโหลดแล้ว
    if ($slip_info['slip_count'] > 0) {
        if ($slip_info['approved_count'] > 0) {
            $response['warning'] = 'นักเรียนได้ชำระเงินและได้รับการอนุมัติแล้ว ' . $slip_info['approved_count'] . ' ครั้ง';
        } else {
            $response['warning'] = 'นักเรียนมีสลิปที่รอการตรวจสอบอยู่ ' . $slip_info['slip_count'] . ' ใบ';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}