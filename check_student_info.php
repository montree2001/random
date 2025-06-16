<?php
// check_student_info.php - ตรวจสอบข้อมูลนักเรียนสำหรับ AJAX
header('Content-Type: application/json; charset=utf-8');

require_once 'config/database.php';

$response = array('success' => false, 'message' => '', 'student' => null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_code'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $student_code = trim($_POST['student_code']);
        
        if (empty($student_code)) {
            throw new Exception('กรุณากรอกรหัสนักเรียน');
        }
        
        // ดึงข้อมูลปีการศึกษาปัจจุบัน
        $query_year = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
        $stmt_year = $db->prepare($query_year);
        $stmt_year->execute();
        $current_year = $stmt_year->fetch();
        
        if (!$current_year) {
            throw new Exception('ไม่พบข้อมูลปีการศึกษาปัจจุบัน');
        }
        
        // ค้นหาข้อมูลนักเรียน
        $query = "SELECT s.*, u.first_name, u.last_name, c.level, d.department_name, c.group_number,
                        sc.color_name, sc.color_code
                 FROM students s 
                 JOIN users u ON s.user_id = u.user_id
                 LEFT JOIN classes c ON s.current_class_id = c.class_id
                 LEFT JOIN departments d ON c.department_id = d.department_id
                 LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id 
                        AND ssc.academic_year_id = ? AND ssc.is_active = 1
                 LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                 WHERE s.student_code = ? AND s.status = 'กำลังศึกษา'";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$current_year['academic_year_id'], $student_code]);
        $student = $stmt->fetch();
        
        if (!$student) {
            throw new Exception('ไม่พบรหัสนักเรียนในระบบ หรือสถานะไม่ใช่กำลังศึกษา');
        }
        
        $response['success'] = true;
        $response['message'] = 'พบข้อมูลนักเรียน';
        $response['student'] = array(
            'student_id' => $student['student_id'],
            'student_code' => $student['student_code'],
            'title' => $student['title'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'level' => $student['level'],
            'department_name' => $student['department_name'],
            'group_number' => $student['group_number'],
            'color_name' => $student['color_name'],
            'color_code' => $student['color_code']
        );
        
        if (!$student['color_name']) {
            $response['message'] = 'พบข้อมูลนักเรียน แต่ยังไม่ได้รับการจัดสี';
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'ข้อมูลไม่ถูกต้อง';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>