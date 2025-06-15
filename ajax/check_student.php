<?php
// ajax/check_student.php - ตรวจสอบข้อมูลนักเรียน
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_code'])) {
    try {
        $student_code = trim($_POST['student_code']);
        
        // ดึงข้อมูลนักเรียนพร้อมสีที่ได้รับ
        $query = "SELECT s.*, 
                         d.department_name,
                         el.level_name as education_level,
                         sc.color_name,
                         sc.color_code
                  FROM students s
                  LEFT JOIN departments d ON s.department_id = d.department_id
                  LEFT JOIN education_levels el ON s.education_level_id = el.education_level_id
                  LEFT JOIN student_sport_colors ssc ON s.student_id = ssc.student_id AND ssc.is_active = 1
                  LEFT JOIN sport_colors sc ON ssc.color_id = sc.color_id
                  WHERE s.student_code = ? AND s.is_active = 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$student_code]);
        $student = $stmt->fetch();
        
        if ($student) {
            echo json_encode([
                'success' => true,
                'student' => $student
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ไม่พบข้อมูลนักเรียน'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ถูกต้อง'
    ]);
}
?>