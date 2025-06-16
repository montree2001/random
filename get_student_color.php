<?php
// get_student_color.php - ตรวจสอบข้อมูลสีของนักเรียน (ใช้ตาราง sport_colors)
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
    
    // ดึงปีการศึกษาปัจจุบัน
    $academic_year_query = "SELECT academic_year_id FROM academic_years WHERE is_active = 1 LIMIT 1";
    $academic_year_stmt = $db->prepare($academic_year_query);
    $academic_year_stmt->execute();
    $current_academic_year = $academic_year_stmt->fetch();
    
    if (!$current_academic_year) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบปีการศึกษาปัจจุบัน กรุณาติดต่อเจ้าหน้าที่']);
        exit;
    }
    
    // ค้นหาข้อมูลนักเรียนและสีโดยใช้ตาราง sport_colors (ตามผลจาก debug)
    $query = "
        SELECT 
            s.student_id,
            s.student_code,
            u.first_name,
            u.last_name,
            sc.color_id,
            sc.color_name,
            sc.color_code,
            sc.fee_amount,
            ay.year as academic_year,
            ay.semester,
            ssc.assigned_date,
            'sport_colors' as table_used
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id AND ssc.academic_year_id = ? AND ssc.is_active = 1
        LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
        LEFT JOIN academic_years ay ON ssc.academic_year_id = ay.academic_year_id
        WHERE s.student_code = ? AND s.status = 'กำลังศึกษา'
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$current_academic_year['academic_year_id'], $student_code]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสนักเรียนหรือนักเรียนไม่ได้อยู่ในสถานะกำลังศึกษา']);
        exit;
    }
    
    if (!$student['color_id']) {
        // ตรวจสอบว่ามีข้อมูลการจัดสีหรือไม่
        $debug_query = "SELECT COUNT(*) as count FROM student_sport_colors WHERE student_id = ?";
        $debug_stmt = $db->prepare($debug_query);
        $debug_stmt->execute([$student['student_id']]);
        $debug_result = $debug_stmt->fetch();
        
        if ($debug_result['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'นักเรียนมีการจัดสี แต่ไม่ใช่ในปีการศึกษาปัจจุบัน กรุณาติดต่อเจ้าหน้าที่',
                'debug' => [
                    'student_id' => $student['student_id'],
                    'color_assignments' => $debug_result['count'],
                    'current_academic_year' => $current_academic_year['academic_year_id']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'นักเรียนยังไม่ได้รับการจัดสี']);
        }
        exit;
    }
    
    // ตรวจสอบข้อมูลสลิป
    $slip_check_query = "
        SELECT COUNT(*) as slip_count, 
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM payment_slips 
        WHERE student_id = ? AND academic_year_id = ?
    ";
    $slip_stmt = $db->prepare($slip_check_query);
    $slip_stmt->execute([$student['student_id'], $current_academic_year['academic_year_id']]);
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
            'fee_amount' => floatval($student['fee_amount'] ?? 150.00), // ยอดเงินจากฐานข้อมูล
            'academic_year' => $student['academic_year'],
            'semester' => $student['semester'],
            'assignment_date' => $student['assigned_date'],
            'slip_count' => $slip_info['slip_count'],
            'approved_slip_count' => $slip_info['approved_count'],
            'pending_slip_count' => $slip_info['pending_count'],
            'table_used' => $student['table_used']
        ]
    ];
    
    // เพิ่มข้อความเตือนถ้าเคยอัพโหลดแล้ว
    if ($slip_info['slip_count'] > 0) {
        if ($slip_info['approved_count'] > 0) {
            $response['warning'] = 'นักเรียนได้ชำระเงินและได้รับการอนุมัติแล้ว ' . $slip_info['approved_count'] . ' ครั้ง';
        } elseif ($slip_info['pending_count'] > 0) {
            $response['warning'] = 'นักเรียนมีสลิปที่รอการตรวจสอบอยู่ ' . $slip_info['pending_count'] . ' ใบ';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}