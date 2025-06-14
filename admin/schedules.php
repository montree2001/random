<?php
// admin/schedules.php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

// ฟังก์ชันช่วยในการ format ตัวเลข (แก้ปัญหา PHP 8.3)
function safe_number_format($number, $decimals = 0, $decimal_separator = '.', $thousands_separator = ',') {
    if ($number === null || $number === '') {
        return '0';
    }
    return number_format((float)$number, $decimals, $decimal_separator, $thousands_separator);
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ดึงปีการศึกษาปัจจุบัน
$query = "SELECT * FROM academic_years WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current_year = $stmt->fetch();

// ดึงข้อมูลการแข่งขันที่เลือก
$selected_competition = null;
$competition_id = $_GET['competition_id'] ?? '';

if (!empty($competition_id) && is_numeric($competition_id)) {
    $query = "SELECT * FROM sport_competitions WHERE competition_id = ? AND academic_year_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$competition_id, $current_year['academic_year_id']]);
    $selected_competition = $stmt->fetch();
}

// เพิ่มตารางแข่ง
if (isset($_POST['add_schedule'])) {
    $competition_id = $_POST['competition_id'];
    $match_name = trim($_POST['match_name']);
    $team1_color_id = $_POST['team1_color_id'];
    $team2_color_id = $_POST['team2_color_id'];
    $match_date = $_POST['match_date'];
    $match_time = $_POST['match_time'];
    $location = trim($_POST['location']);
    $round = trim($_POST['round']);
    $notes = trim($_POST['notes']);
    
    $match_datetime = $match_date . ' ' . $match_time;
    
    if (!empty($competition_id) && !empty($match_name) && !empty($team1_color_id) && 
        !empty($team2_color_id) && !empty($match_date) && !empty($match_time)) {
        
        if ($team1_color_id === $team2_color_id) {
            $message = 'ไม่สามารถจับสีเดียวกันแข่งขันได้';
            $message_type = 'warning';
        } else {
            try {
                $query = "INSERT INTO sport_schedules 
                         (competition_id, match_name, team1_color_id, team2_color_id, match_date, location, round, notes, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $competition_id, 
                    $match_name, 
                    $team1_color_id, 
                    $team2_color_id, 
                    $match_datetime, 
                    $location, 
                    $round, 
                    $notes, 
                    $_SESSION['admin_id']
                ]);
                
                $message = 'เพิ่มตารางแข่งเรียบร้อยแล้ว';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// แก้ไขตารางแข่ง
if (isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $match_name = trim($_POST['match_name']);
    $team1_color_id = $_POST['team1_color_id'];
    $team2_color_id = $_POST['team2_color_id'];
    $match_date = $_POST['match_date'];
    $match_time = $_POST['match_time'];
    $location = trim($_POST['location']);
    $round = trim($_POST['round']);
    $score_team1 = $_POST['score_team1'] !== '' ? (int)$_POST['score_team1'] : null;
    $score_team2 = $_POST['score_team2'] !== '' ? (int)$_POST['score_team2'] : null;
    $winner_color_id = $_POST['winner_color_id'] !== '' ? $_POST['winner_color_id'] : null;
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    $match_datetime = $match_date . ' ' . $match_time;
    
    if (!empty($schedule_id) && !empty($match_name) && !empty($team1_color_id) && !empty($team2_color_id)) {
        if ($team1_color_id === $team2_color_id) {
            $message = 'ไม่สามารถจับสีเดียวกันแข่งขันได้';
            $message_type = 'warning';
        } else {
            try {
                $query = "UPDATE sport_schedules 
                         SET match_name = ?, team1_color_id = ?, team2_color_id = ?, match_date = ?, location = ?, 
                             round = ?, score_team1 = ?, score_team2 = ?, winner_color_id = ?, status = ?, notes = ?
                         WHERE schedule_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $match_name, 
                    $team1_color_id, 
                    $team2_color_id, 
                    $match_datetime, 
                    $location, 
                    $round, 
                    $score_team1, 
                    $score_team2, 
                    $winner_color_id, 
                    $status, 
                    $notes, 
                    $schedule_id
                ]);
                
                $message = 'แก้ไขตารางแข่งเรียบร้อยแล้ว';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $message_type = 'warning';
    }
}

// ลบตารางแข่ง
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    
    $query = "DELETE FROM sport_schedules WHERE schedule_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$schedule_id])) {
        $message = 'ลบตารางแข่งเรียบร้อยแล้ว';
        $message_type = 'success';
    }
}

// ดึงข้อมูลการแข่งขันทั้งหมด
$query = "SELECT * FROM sport_competitions 
          WHERE academic_year_id = ? 
          ORDER BY start_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_year['academic_year_id']]);
$competitions = $stmt->fetchAll();

// ดึงข้อมูลสีทั้งหมด
$query = "SELECT * FROM sport_colors WHERE is_active = 1 ORDER BY color_name";
$stmt = $db->prepare($query);
$stmt->execute();
$colors = $stmt->fetchAll();

// ดึงข้อมูลตารางแข่ง
$schedules = [];
if ($selected_competition) {
    $query = "SELECT ss.*, 
                     sc1.color_name as team1_color_name, sc1.color_code as team1_color_code,
                     sc2.color_name as team2_color_name, sc2.color_code as team2_color_code,
                     winner.color_name as winner_color_name, winner.color_code as winner_color_code,
                     CONCAT(COALESCE(admin.title, ''), admin.first_name, ' ', admin.last_name) as created_by_name
              FROM sport_schedules ss
              JOIN sport_colors sc1 ON ss.team1_color_id = sc1.color_id
              JOIN sport_colors sc2 ON ss.team2_color_id = sc2.color_id
              LEFT JOIN sport_colors winner ON ss.winner_color_id = winner.color_id
              LEFT JOIN admin_users admin ON ss.created_by = admin.admin_id
              WHERE ss.competition_id = ?
              ORDER BY ss.match_date ASC, ss.created_at ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_competition['competition_id']]);
    $schedules = $stmt->fetchAll();
}

// สถิติตารางแข่ง
$schedule_stats = [];
if ($selected_competition) {
    $query = "SELECT 
                COUNT(*) as total_matches,
                COUNT(CASE WHEN status = 'รอแข่ง' THEN 1 END) as pending_matches,
                COUNT(CASE WHEN status = 'กำลังแข่ง' THEN 1 END) as ongoing_matches,
                COUNT(CASE WHEN status = 'จบแล้ว' THEN 1 END) as finished_matches,
                COUNT(CASE WHEN status = 'เลื่อน' THEN 1 END) as postponed_matches,
                COUNT(CASE WHEN status = 'ยกเลิก' THEN 1 END) as cancelled_matches
              FROM sport_schedules 
              WHERE competition_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_competition['competition_id']]);
    $schedule_stats = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตารางแข่ง - ระบบกีฬาสี</title>
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
        .match-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .match-card:hover {
            transform: translateY(-2px);
        }
        .vs-separator {
            font-size: 1.2rem;
            font-weight: bold;
            color: #6c757d;
        }
        .score-display {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .winner-highlight {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            font-weight: bold;
        }
        
        /* SELECT2 Bootstrap 5 Custom Styles */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(1.5em + 0.75rem + 2px);
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-top: 0.375rem;
            padding-bottom: 0.375rem;
            padding-left: 0.75rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.75rem);
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
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-money-bill"></i> บันทึกการจ่ายเงิน
                    </a>
                    <a class="nav-link" href="athletes.php">
                        <i class="fas fa-running"></i> นักกีฬา
                    </a>
                    <a class="nav-link" href="competitions.php">
                        <i class="fas fa-trophy"></i> การแข่งขัน
                    </a>
                    <a class="nav-link active" href="schedules.php">
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
                    <h2><i class="fas fa-calendar"></i> จัดการตารางแข่ง</h2>
                    <div>
                        <a href="competitions.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> กลับไปการแข่งขัน
                        </a>
                        <?php if ($selected_competition): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                <i class="fas fa-plus"></i> เพิ่มตารางแข่ง
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- เลือกการแข่งขัน -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5><i class="fas fa-trophy"></i> เลือกการแข่งขัน</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" name="competition_id" id="competitionSelect" onchange="this.form.submit()">
                                    <option value="">-- เลือกการแข่งขัน --</option>
                                    <?php foreach ($competitions as $comp): ?>
                                        <option value="<?php echo $comp['competition_id']; ?>" 
                                                <?php echo $competition_id == $comp['competition_id'] ? 'selected' : ''; ?>>
                                            <?php echo $comp['competition_name']; ?> 
                                            (<?php echo $comp['sport_type']; ?>) - 
                                            <?php echo date('d/m/Y', strtotime($comp['start_date'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <?php if ($selected_competition): ?>
                                    <div class="text-muted">
                                        <strong>สถานะ:</strong> 
                                        <span class="badge <?php 
                                            echo $selected_competition['status'] === 'กำลังรับสมัคร' ? 'bg-info' : 
                                                ($selected_competition['status'] === 'กำลังแข่งขัน' ? 'bg-warning text-dark' : 
                                                ($selected_competition['status'] === 'จบแล้ว' ? 'bg-success' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo $selected_competition['status']; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($selected_competition): ?>
                    <!-- ข้อมูลการแข่งขัน -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-info-circle text-primary"></i> 
                                        <?php echo $selected_competition['competition_name']; ?>
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>ประเภทกีฬา:</strong> <?php echo $selected_competition['sport_type']; ?></p>
                                            <p class="mb-1"><strong>วันที่แข่งขัน:</strong> 
                                                <?php echo date('d/m/Y', strtotime($selected_competition['start_date'])); ?>
                                                <?php if ($selected_competition['start_date'] !== $selected_competition['end_date']): ?>
                                                    - <?php echo date('d/m/Y', strtotime($selected_competition['end_date'])); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>สถานที่:</strong> <?php echo $selected_competition['location'] ?: 'ไม่ระบุ'; ?></p>
                                            <p class="mb-1"><strong>จำนวนทีมสูงสุด:</strong> 
                                                <?php echo $selected_competition['max_teams'] ? safe_number_format($selected_competition['max_teams']) . ' ทีม' : 'ไม่จำกัด'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($selected_competition['description']): ?>
                                        <p class="mb-0 text-muted"><small><?php echo $selected_competition['description']; ?></small></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php if (!empty($schedule_stats)): ?>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5><i class="fas fa-chart-pie"></i> สถิติตารางแข่ง</h5>
                                        <div class="row">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?php echo safe_number_format($schedule_stats['total_matches']); ?></h4>
                                                <small>นัดทั้งหมด</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?php echo safe_number_format($schedule_stats['finished_matches']); ?></h4>
                                                <small>จบแล้ว</small>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-6">
                                                <h4 class="text-warning"><?php echo safe_number_format($schedule_stats['ongoing_matches']); ?></h4>
                                                <small>กำลังแข่ง</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-info"><?php echo safe_number_format($schedule_stats['pending_matches']); ?></h4>
                                                <small>รอแข่ง</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ตารางแข่ง -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-list"></i> ตารางแข่ง (<?php echo count($schedules); ?> นัด)</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($schedules)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="schedulesTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>วันเวลา</th>
                                                <th>ชื่อนัด</th>
                                                <th>รอบ</th>
                                                <th>ทีม 1</th>
                                                <th>VS</th>
                                                <th>ทีม 2</th>
                                                <th>ผลการแข่งขัน</th>
                                                <th>สถานที่</th>
                                                <th>สถานะ</th>
                                                <th>ผู้สร้าง</th>
                                                <th width="120">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr class="match-card" 
                                                    style="border-left-color: <?php 
                                                        echo $schedule['status'] === 'รอแข่ง' ? '#17a2b8' : 
                                                            ($schedule['status'] === 'กำลังแข่ง' ? '#ffc107' : 
                                                            ($schedule['status'] === 'จบแล้ว' ? '#28a745' : '#6c757d')); 
                                                    ?>">
                                                    <td>
                                                        <strong><?php echo date('d/m/Y', strtotime($schedule['match_date'])); ?></strong>
                                                        <br><small><?php echo date('H:i', strtotime($schedule['match_date'])); ?> น.</small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($schedule['match_name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo $schedule['round'] ? '<span class="badge bg-secondary">' . $schedule['round'] . '</span>' : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="color-badge" style="background-color: <?php echo $schedule['team1_color_code']; ?>"></span>
                                                        <strong><?php echo $schedule['team1_color_name']; ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="vs-separator">VS</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="color-badge" style="background-color: <?php echo $schedule['team2_color_code']; ?>"></span>
                                                        <strong><?php echo $schedule['team2_color_name']; ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($schedule['status'] === 'จบแล้ว' && $schedule['score_team1'] !== null && $schedule['score_team2'] !== null): ?>
                                                            <div class="score-display">
                                                                <span class="<?php echo $schedule['winner_color_id'] == $schedule['team1_color_id'] ? 'winner-highlight' : ''; ?>">
                                                                    <?php echo $schedule['score_team1']; ?>
                                                                </span>
                                                                :
                                                                <span class="<?php echo $schedule['winner_color_id'] == $schedule['team2_color_id'] ? 'winner-highlight' : ''; ?>">
                                                                    <?php echo $schedule['score_team2']; ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($schedule['winner_color_name']): ?>
                                                                <small class="text-success">
                                                                    <i class="fas fa-crown"></i> <?php echo $schedule['winner_color_name']; ?> ชนะ
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">ยังไม่แข่ง</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $schedule['location'] ?: '-'; ?></td>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo $schedule['status'] === 'รอแข่ง' ? 'bg-info' : 
                                                                ($schedule['status'] === 'กำลังแข่ง' ? 'bg-warning text-dark' : 
                                                                ($schedule['status'] === 'จบแล้ว' ? 'bg-success' : 
                                                                ($schedule['status'] === 'เลื่อน' ? 'bg-secondary' : 'bg-danger'))); 
                                                        ?>">
                                                            <?php echo $schedule['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo $schedule['created_by_name'] ?? 'ไม่ระบุ'; ?></small>
                                                        <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($schedule['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)"
                                                                    title="แก้ไข">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <a href="?competition_id=<?php echo $competition_id; ?>&delete=<?php echo $schedule['schedule_id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('ต้องการลบตารางแข่งนี้?')"
                                                               title="ลบ">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar fa-3x text-muted"></i>
                                    <p class="mt-2 text-muted">ยังไม่มีตารางแข่งขัน</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                        <i class="fas fa-plus"></i> เพิ่มตารางแข่งแรก
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- ยังไม่ได้เลือกการแข่งขัน -->
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-alt fa-5x text-muted mb-3"></i>
                        <h4>เลือกการแข่งขันเพื่อจัดการตารางแข่ง</h4>
                        <p class="text-muted">กรุณาเลือกการแข่งขันจากรายการด้านบนเพื่อเริ่มจัดการตารางแข่งขัน</p>
                        <?php if (empty($competitions)): ?>
                            <a href="competitions.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> สร้างการแข่งขันใหม่
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Schedule Modal -->
    <?php if ($selected_competition): ?>
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มตารางแข่ง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="scheduleForm">
                    <input type="hidden" name="competition_id" value="<?php echo $selected_competition['competition_id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ชื่อนัด <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="match_name" 
                                           placeholder="เช่น รอบแรก นัดที่ 1" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">รอบการแข่งขัน</label>
                                    <input type="text" class="form-control" name="round" 
                                           placeholder="เช่น รอบแรก, รอบรอง, รอบชิงชนะเลิศ"
                                           list="roundsList">
                                    <datalist id="roundsList">
                                        <option value="รอบแรก">
                                        <option value="รอบสอง">
                                        <option value="รอบสาม">
                                        <option value="รอบรอง">
                                        <option value="รอบชิงชนะเลิศ">
                                        <option value="รอบชิงอันดับ 3">
                                    </datalist>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">วันที่แข่งขัน <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="match_date" 
                                           min="<?php echo $selected_competition['start_date']; ?>"
                                           max="<?php echo $selected_competition['end_date']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">เวลาแข่งขัน <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="match_time" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ทีม 1 (สี) <span class="text-danger">*</span></label>
                                    <select class="form-select" name="team1_color_id" id="team1Select" required>
                                        <option value="">-- เลือกสี --</option>
                                        <?php foreach ($colors as $color): ?>
                                            <option value="<?php echo $color['color_id']; ?>" 
                                                    data-color="<?php echo $color['color_code']; ?>">
                                                <?php echo $color['color_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ทีม 2 (สี) <span class="text-danger">*</span></label>
                                    <select class="form-select" name="team2_color_id" id="team2Select" required>
                                        <option value="">-- เลือกสี --</option>
                                        <?php foreach ($colors as $color): ?>
                                            <option value="<?php echo $color['color_id']; ?>" 
                                                    data-color="<?php echo $color['color_code']; ?>">
                                                <?php echo $color['color_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">สถานที่แข่งขัน</label>
                                    <input type="text" class="form-control" name="location" 
                                           placeholder="เช่น สนามฟุตบอลหลัง, โรงยิม"
                                           value="<?php echo $selected_competition['location']; ?>">
                                </div>
                                
                                <!-- ฟิลด์สำหรับการแก้ไข -->
                                <div id="editFields" style="display: none;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">คะแนนทีม 1</label>
                                                <input type="number" class="form-control" name="score_team1" min="0">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">คะแนนทีม 2</label>
                                                <input type="number" class="form-control" name="score_team2" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ทีมชนะ</label>
                                        <select class="form-select" name="winner_color_id">
                                            <option value="">-- เสมอ/ยังไม่แข่ง --</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">สถานะ</label>
                                        <select class="form-select" name="status">
                                            <option value="รอแข่ง">รอแข่ง</option>
                                            <option value="กำลังแข่ง">กำลังแข่ง</option>
                                            <option value="จบแล้ว">จบแล้ว</option>
                                            <option value="เลื่อน">เลื่อน</option>
                                            <option value="ยกเลิก">ยกเลิก</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="หมายเหตุเพิ่มเติม"></textarea>
                        </div>
                        
                        <!-- แสดงข้อมูลสรุป -->
                        <div id="scheduleSummary" class="alert alert-info d-none">
                            <h6><i class="fas fa-info-circle"></i> สรุปตารางแข่ง</h6>
                            <div id="summaryContent"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_schedule" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // อัปเดตสรุปตารางแข่ง
        function updateScheduleSummary() {
            const matchName = document.querySelector('input[name="match_name"]').value;
            const team1Select = document.getElementById('team1Select');
            const team2Select = document.getElementById('team2Select');
            const matchDate = document.querySelector('input[name="match_date"]').value;
            const matchTime = document.querySelector('input[name="match_time"]').value;
            const location = document.querySelector('input[name="location"]').value;
            const round = document.querySelector('input[name="round"]').value;
            
            if (matchName && team1Select.value && team2Select.value && matchDate && matchTime) {
                const team1Option = team1Select.options[team1Select.selectedIndex];
                const team2Option = team2Select.options[team2Select.selectedIndex];
                
                const summary = document.getElementById('scheduleSummary');
                const content = document.getElementById('summaryContent');
                
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>นัดการแข่งขัน:</strong> ${matchName}<br>
                            <strong>รอบ:</strong> ${round || 'ไม่ระบุ'}<br>
                            <strong>วันเวลา:</strong> ${new Date(matchDate).toLocaleDateString('th-TH')} ${matchTime} น.
                        </div>
                        <div class="col-md-6">
                            <strong>การแข่งขัน:</strong> ${team1Option.text} VS ${team2Option.text}<br>
                            <strong>สถานที่:</strong> ${location || 'ไม่ระบุ'}
                        </div>
                    </div>
                `;
                
                summary.classList.remove('d-none');
            } else {
                document.getElementById('scheduleSummary').classList.add('d-none');
            }
        }
        
        // อัปเดตตัวเลือกทีมชนะ
        function updateWinnerOptions() {
            const team1Select = document.getElementById('team1Select');
            const team2Select = document.getElementById('team2Select');
            const winnerSelect = document.querySelector('select[name="winner_color_id"]');
            
            if (team1Select.value && team2Select.value && winnerSelect) {
                const team1Option = team1Select.options[team1Select.selectedIndex];
                const team2Option = team2Select.options[team2Select.selectedIndex];
                
                winnerSelect.innerHTML = `
                    <option value="">-- เสมอ/ยังไม่แข่ง --</option>
                    <option value="${team1Select.value}">${team1Option.text}</option>
                    <option value="${team2Select.value}">${team2Option.text}</option>
                `;
            }
        }
        
        // แก้ไขตารางแข่ง
        function editSchedule(schedule) {
            const modal = new bootstrap.Modal(document.getElementById('addScheduleModal'));
            const form = document.getElementById('scheduleForm');
            
            // เปลี่ยนหัวข้อโมดอล
            document.querySelector('#addScheduleModal .modal-title').innerHTML = 
                '<i class="fas fa-edit"></i> แก้ไขตารางแข่ง';
            
            // ตั้งค่าข้อมูลในฟอร์ม
            form.querySelector('input[name="match_name"]').value = schedule.match_name;
            form.querySelector('input[name="round"]').value = schedule.round || '';
            form.querySelector('input[name="match_date"]').value = schedule.match_date.split(' ')[0];
            form.querySelector('input[name="match_time"]').value = schedule.match_date.split(' ')[1];
            form.querySelector('input[name="location"]').value = schedule.location || '';
            form.querySelector('textarea[name="notes"]').value = schedule.notes || '';
            
            // เลือกสีทีม
            $('#team1Select').val(schedule.team1_color_id).trigger('change');
            $('#team2Select').val(schedule.team2_color_id).trigger('change');
            
            // แสดงฟิลด์การแก้ไข
            document.getElementById('editFields').style.display = 'block';
            
            // ตั้งค่าคะแนนและผลการแข่งขัน
            form.querySelector('input[name="score_team1"]').value = schedule.score_team1 || '';
            form.querySelector('input[name="score_team2"]').value = schedule.score_team2 || '';
            form.querySelector('select[name="status"]').value = schedule.status;
            
            // อัปเดตตัวเลือกทีมชนะ
            setTimeout(function() {
                updateWinnerOptions();
                if (schedule.winner_color_id) {
                    form.querySelector('select[name="winner_color_id"]').value = schedule.winner_color_id;
                }
            }, 100);
            
            // เพิ่ม hidden input สำหรับ schedule_id
            let scheduleIdInput = form.querySelector('input[name="schedule_id"]');
            if (!scheduleIdInput) {
                scheduleIdInput = document.createElement('input');
                scheduleIdInput.type = 'hidden';
                scheduleIdInput.name = 'schedule_id';
                form.appendChild(scheduleIdInput);
            }
            scheduleIdInput.value = schedule.schedule_id;
            
            // เปลี่ยนปุ่มส่ง
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> อัปเดต';
            submitBtn.setAttribute('name', 'update_schedule');
            
            updateScheduleSummary();
            modal.show();
        }
        
        // ส่งออก Excel
        function exportToExcel() {
            const table = document.getElementById('schedulesTable');
            if (!table) return;
            
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
            const csvContent = "\uFEFF" + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'schedule_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize SELECT2 for competition selection
            $('#competitionSelect').select2({
                theme: 'bootstrap-5',
                placeholder: '-- เลือกการแข่งขัน --',
                allowClear: true,
                width: '100%'
            });
            
            // Initialize SELECT2 for team selection
            $('#team1Select, #team2Select').select2({
                theme: 'bootstrap-5',
                placeholder: '-- เลือกสี --',
                allowClear: true,
                width: '100%',
                templateResult: function(data) {
                    if (!data.id || data.id === '') {
                        return data.text;
                    }
                    
                    var $option = $(data.element);
                    var color = $option.data('color');
                    
                    var $result = $(
                        '<div class="select2-result-color">' +
                            '<span class="color-badge me-2" style="background-color: ' + color + '; width: 15px; height: 15px; border-radius: 50%; display: inline-block;"></span>' +
                            data.text +
                        '</div>'
                    );
                    
                    return $result;
                },
                templateSelection: function(data) {
                    if (!data.id || data.id === '') {
                        return data.text;
                    }
                    
                    var $option = $(data.element);
                    var color = $option.data('color');
                    
                    return $(
                        '<span>' +
                            '<span class="color-badge me-2" style="background-color: ' + color + '; width: 15px; height: 15px; border-radius: 50%; display: inline-block;"></span>' +
                            data.text +
                        '</span>'
                    );
                }
            });
            
            // Handle team selection change
            $('#team1Select, #team2Select').on('select2:select select2:clear', function (e) {
                updateScheduleSummary();
                updateWinnerOptions();
            });
            
            // อัปเดตสรุปเมื่อเปลี่ยนข้อมูล
            document.querySelector('input[name="match_name"]').addEventListener('input', updateScheduleSummary);
            document.querySelector('input[name="round"]').addEventListener('input', updateScheduleSummary);
            document.querySelector('input[name="match_date"]').addEventListener('change', updateScheduleSummary);
            document.querySelector('input[name="match_time"]').addEventListener('change', updateScheduleSummary);
            document.querySelector('input[name="location"]').addEventListener('input', updateScheduleSummary);
            
            // ตั้งค่าวันที่เริ่มต้น
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="match_date"]').value = today;
            document.querySelector('input[name="match_time"]').value = '09:00';
            
            // รีเซ็ตฟอร์มเมื่อปิดโมดอล
            document.getElementById('addScheduleModal').addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('scheduleForm');
                form.reset();
                
                // Clear SELECT2
                $('#team1Select, #team2Select').val(null).trigger('change');
                
                // รีเซ็ตหัวข้อและปุ่ม
                document.querySelector('#addScheduleModal .modal-title').innerHTML = 
                    '<i class="fas fa-plus"></i> เพิ่มตารางแข่ง';
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึก';
                submitBtn.setAttribute('name', 'add_schedule');
                
                // ซ่อนฟิลด์การแก้ไข
                document.getElementById('editFields').style.display = 'none';
                
                // ลบ schedule_id ถ้ามี
                const scheduleIdInput = form.querySelector('input[name="schedule_id"]');
                if (scheduleIdInput) {
                    scheduleIdInput.remove();
                }
                
                // ซ่อนข้อมูลสรุป
                document.getElementById('scheduleSummary').classList.add('d-none');
                
                // ตั้งค่าวันเวลาเริ่มต้น
                const today = new Date().toISOString().split('T')[0];
                document.querySelector('input[name="match_date"]').value = today;
                document.querySelector('input[name="match_time"]').value = '09:00';
            });
        });
    </script>
</body>
</html>