<?php
include('db_connect.php');
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Add this function near the top, after session_start()
function safe_html($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ensure we have a mysqli connection variable available
if (!isset($mysqli)) {
    if (isset($conn)) $mysqli = $conn;
    elseif (isset($link)) $mysqli = $link;
    elseif (isset($db)) $mysqli = $db;
    else {
        die("Database connection not found. Make sure [db_connect.php](http://_vscodecontentref_/0) creates a mysqli connection in \$mysqli (or \$conn / \$link / \$db).");
    }
}

// ----------------- New: handle add / delete / edit student actions -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    // Delete scholar (by student_id)
    if ($action === 'delete_student' && !empty($_POST['student_id'])) {
        $sid = $_POST['student_id'];
        $del = $mysqli->prepare("DELETE FROM students WHERE student_id = ?");
        if ($del) {
            $del->bind_param('s', $sid);
            $del->execute();
            $del->close();
        }
        header('Location: admin_dashboard.php?tab=scholars');
        exit;
    }

    // Add scholar (full details)
    if ($action === 'add_student') {
        $sid   = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
        $name  = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
        $course = isset($_POST['course']) ? trim($_POST['course']) : '';
        $year   = isset($_POST['year']) ? trim($_POST['year']) : '';
        $section = isset($_POST['section']) ? trim($_POST['section']) : '';
        $scholarship = isset($_POST['scholarship_type']) ? trim($_POST['scholarship_type']) : '';

        if ($sid !== '' && $name !== '') {
            $ins = $mysqli->prepare(
                "INSERT INTO students (student_id, fullname, course, `year`, section, scholarship_type)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            if ($ins) {
                $ins->bind_param('ssssss', $sid, $name, $course, $year, $section, $scholarship);
                $ins->execute();
                $ins->close();
            } else {
                error_log("Prepare failed (insert student): " . $mysqli->error);
            }
        }
        header('Location: admin_dashboard.php?tab=scholars');
        exit;
    }

    // Edit scholar (update existing record)
    if ($action === 'edit_student' && !empty($_POST['student_id'])) {
        $sid   = trim($_POST['student_id']);
        $name  = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
        $course = isset($_POST['course']) ? trim($_POST['course']) : '';
        $year   = isset($_POST['year']) ? trim($_POST['year']) : '';
        $section = isset($_POST['section']) ? trim($_POST['section']) : '';
        $scholarship = isset($_POST['scholarship_type']) ? trim($_POST['scholarship_type']) : '';

        $upd = $mysqli->prepare(
            "UPDATE students SET fullname = ?, course = ?, `year` = ?, section = ?, scholarship_type = ? WHERE student_id = ?"
        );
        if ($upd) {
            $upd->bind_param('ssssss', $name, $course, $year, $section, $scholarship, $sid);
            $upd->execute();
            $upd->close();
        } else {
            error_log("Prepare failed (update student): " . $mysqli->error);
        }

        header('Location: admin_dashboard.php?tab=scholars');
        exit;
    }
}
// ---------------------------------------------------------------------------

// which tab to show
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'attendance';

// select ceremony: 'raising' (default) or 'retreat'
$ceremony = (isset($_GET['ceremony']) && $_GET['ceremony'] === 'retreat') ? 'retreat' : 'raising';
$filterDay = $ceremony === 'retreat' ? 'Friday' : 'Monday';

// Get the current date and start of week
$currentDate = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek = date('Y-m-d', strtotime('sunday this week'));

// Get the current week's Monday and Friday dates
$thisWeekMonday = date('Y-m-d', strtotime('monday this week'));
$thisWeekFriday = date('Y-m-d', strtotime('friday this week'));

// Set the specific day based on ceremony type
$filterDate = $ceremony === 'retreat' ? $thisWeekFriday : $thisWeekMonday;

// Modified query to only show current week's Monday or Friday
$q = "
  SELECT
    a.id,
    COALESCE(s.fullname, a.fullname, a.student_id) AS student_name, 
    a.student_id,
    DATE(a.date) as date,
    a.day,
    TIME(a.time_in) as time_in,
    CASE 
      WHEN a.day = 'Monday' AND a.time_in IS NOT NULL THEN
        CASE 
          WHEN TIME(a.time_in) <= '07:30:00' THEN 'Present'
          WHEN TIME(a.time_in) <= '08:00:00' THEN 'Late'
          ELSE 'Absent'
        END
      WHEN a.day = 'Friday' AND a.time_in IS NOT NULL THEN
        CASE
          WHEN TIME(a.time_in) <= '17:00:00' AND TIME(a.time_in) >= '16:00:00' THEN 'Present'
          ELSE 'Absent' 
        END
      ELSE 'Absent'
    END as status
  FROM attendance a
  LEFT JOIN students s ON a.student_id = s.student_id 
  WHERE a.day = ?
  AND DATE(a.date) = ?
  ORDER BY a.time_in ASC
";

$stmt = $mysqli->prepare($q);
if ($stmt) {
    $stmt->bind_param('ss', $filterDay, $filterDate);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Query prepare failed: " . htmlspecialchars($mysqli->error));
}

// fetch scholars list for the Scholars tab (include full columns) — supports optional ?q= search
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$students = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmtS = $mysqli->prepare(
      "SELECT student_id, fullname, course, `year`, section, scholarship_type
       FROM students
       WHERE student_id LIKE ? OR fullname LIKE ? OR course LIKE ? OR section LIKE ? OR scholarship_type LIKE ?
       ORDER BY fullname ASC"
    );
    if ($stmtS) {
        $stmtS->bind_param('sssss', $like, $like, $like, $like, $like);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        $students = $resS->fetch_all(MYSQLI_ASSOC);
        $stmtS->close();
    }
} else {
    $studentsResult = $mysqli->query("SELECT student_id, fullname, course, `year`, section, scholarship_type FROM students ORDER BY fullname ASC");
    if ($studentsResult && $studentsResult !== false) {
        $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
        $studentsResult->free();
    }
}

// if editing, fetch the single student to prefill the edit form
$editStudent = null;
if ($tab === 'edit' && !empty($_GET['student_id'])) {
    $sid = $_GET['student_id'];
    $prep = $mysqli->prepare("SELECT student_id, fullname, course, `year`, section, scholarship_type FROM students WHERE student_id = ?");
    if ($prep) {
        $prep->bind_param('s', $sid);
        $prep->execute();
        $res = $prep->get_result();
        $editStudent = $res->fetch_assoc();
        $prep->close();
    }
}

/* ------------------------- EXPORT HANDLERS -------------------------
   Supports:
     - ?export=attendance_csv&ceremony=raising|retreat   -> exports attendance CSV
     - ?export=scholars_csv                              -> exports scholars CSV
------------------------------------------------------------------------ */
if (!empty($_GET['export'])) {
    $export = $_GET['export'];

    if ($export === 'attendance_csv') {
        $exportCeremony = (isset($_GET['ceremony']) && $_GET['ceremony'] === 'retreat') ? 'retreat' : 'raising';
        $exportDay = $exportCeremony === 'retreat' ? 'Friday' : 'Monday';

        $qExport = "
          SELECT a.id, 
           COALESCE(s.fullname, a.fullname, a.student_id) AS student_name, a.student_id, a.date, a.day, a.time_in,
            CASE 
              WHEN a.day = 'Monday' THEN
                CASE 
                  WHEN TIME(a.time_in) <= '07:30:00' THEN 'Present'
                  WHEN TIME(a.time_in) <= '08:00:00' THEN 'Late'
                  ELSE 'Absent'
                END
              WHEN a.day = 'Friday' THEN
                CASE
                  WHEN TIME(a.time_in) <= '17:00:00' THEN 'Present'
                  ELSE 'Absent' 
                END
            END as status
          FROM attendance a
          LEFT JOIN students s ON a.student_id = s.student_id
          WHERE a.day = ?
            AND a.time_in IS NOT NULL
          ORDER BY a.date DESC, a.time_in DESC
        ";
        $sEx = $mysqli->prepare($qExport);
        if ($sEx) {
            $sEx->bind_param('s', $exportDay);
            $sEx->execute();
            $resEx = $sEx->get_result();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=attendance_'.$exportCeremony.'_'.date('Ymd_His').'.csv');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Student Name','Student ID','Date','Day','Time In','Status']);
            while ($r = $resEx->fetch_assoc()) {
                fputcsv($out, [
                    $r['id'],
                    $r['student_name'],
                    $r['student_id'],
                    $r['date'],
                    $r['day'],
                    $r['time_in'],
                    $r['status']
                ]);
            }
            fclose($out);
            exit;
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo "Failed to prepare export query: " . htmlspecialchars($mysqli->error);
            exit;
        }
    }

    if ($export === 'scholars_csv') {
        $sRes = $mysqli->query("SELECT student_id, fullname, course, `year`, section, scholarship_type FROM students ORDER BY fullname ASC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scholars_'.date('Ymd_His').'.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student ID','Fullname','Course','Year','Section','Scholarship']);
        if ($sRes) {
            while ($r = $sRes->fetch_assoc()) {
                fputcsv($out, [
                    $r['student_id'],
                    $r['fullname'],
                    $r['course'],
                    $r['year'],
                    $r['section'],
                    $r['scholarship_type']
                ]);
            }
        }
        fclose($out);
        exit;
    }
}

// Add this after database operations
if ($mysqli->error) {
    error_log("Database error: " . $mysqli->error);
    // Optionally show user-friendly message
    echo '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard — CMC Scholars</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
    <link rel="stylesheet" href="fontawesome/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="" crossorigin="anonymous" media="print" onload="this.media='all'">
    <meta http-equiv="refresh" content="300"> <!-- Refreshes every 5 minutes -->
    <style>
        /* Responsive sidebar + table fixes (replaces previous style block parts) */
        :root{
            --sidebar-width:260px;
            --card-radius:12px;
            --page-pad:18px;
            --content-max:1200px;
        }

        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:'Poppins',system-ui,Segoe UI,Roboto,Arial;
            min-height:100vh;
            background-image: url(images/background_image.jpg);
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
            color:#222;
            position: relative;
            overflow-x: hidden;
        }


        /* Default: MOBILE-FIRST layout (sidebar stacked on top) */
        .sidebar{
            position: fixed;
            left: 0;  /* Change from right: 0 to left: 0 */
            top: 0;
            bottom: 0;
            width: 240px;
            background: rgba(46,57,77,0.95);
            z-index: 1000;
            padding: 1rem;
            overflow-y: auto;
        }
        .sidebar h3 {
            font-family: 'Times New Roman', Times, serif;
            text-align: center;
            color: #fff;
        }
        .sidebar img{width:96px;height:96px;object-fit:cover;margin-bottom:8px}
        .nav-links{list-style:none;padding:8px 0 0 0;margin:0;display:flex;flex-direction:column;gap:8px;width:100%}
        .nav-links a{
            display:block;color:rgba(255,255,255,.96);text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:700;
            background:transparent;text-align:left;
        }
        .nav-links a:hover{background:rgba(255,255,255,0.06);transform:none}
        .nav-links a.active{background:rgba(255,255,255,0.08)}

        /* Main content stacked under sidebar on small screens */
        .main-content{
            margin-left: 240px;  /* Change from margin-right to margin-left */
            padding: 1rem;
            width: calc(100% - 240px);
        }

        /* Card + table visuals */
        .card{border-radius:var(--card-radius);overflow:visible;box-shadow:0 6px 20px rgba(3,28,71,0.08);background: rgba(255,255,255,0.3) !important;border: 1px solid rgba(255,255,255,0.2);backdrop-filter: blur(5px);}
        .card-banner{background:rgba(95,72,243,0.95) !important;color:#fff;padding:14px;border-radius:var(--card-radius) var(--card-radius) 0 0;margin-bottom:.75rem;}
        .card-body{padding:14px}

        /* Add these styles to fix card text overflow */
.card-content {
    text-align: center;
    padding: 15px 10px;
}

.card-content h2 {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0;
    color: #fff;
}

.card-content p {
    color: rgba(255, 255, 255, 0.8);
    margin: 5px 0;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-link {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.8rem;
    position: absolute;
    bottom: 15px;
    left: 15px;
    right: 15px;
    text-align: center;
}

.dashboard-card {
    min-height: 180px;
    padding: 15px;
}

/* Adjust icon size and position */
.card-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 10px;
    font-size: 1.5rem;
}

/* Table styling */
.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.table tr {
    border-bottom: 1px solid #dee2e6;
}

/* Remove striping */
.table tbody tr:nth-of-type(odd) {
    background-color: transparent;
}

/* Keep hover effect */
.table tbody tr:hover {
    background-color: rgba(0,0,0,.075);
}

/* Table responsiveness */
.table-responsive{
    position:relative;
    overflow-y:auto;
    -webkit-overflow-scrolling: touch;
    background: rgba(255,255,255,0.12);
    border-radius:6px;
    padding:0;
    max-height: calc(100vh - 220px); /* space for stacked header/sidebar on mobile */
}

/* Fixed table header styling */
.table thead th{position:sticky;top:0;z-index:2;background:rgba(255,255,255,0.85);backdrop-filter:blur(4px);font-weight:700;border-bottom:1px solid rgba(0,0,0,0.06)}
.table td,.table th{white-space:nowrap;min-width:100px;padding:0.75rem;vertical-align:middle}
.table td:nth-child(2),.table th:nth-child(2){white-space:normal;min-width:160px}

/* Controls */
.controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

/* Small screens improvements */
@media (max-width: 768px){
    .table-responsive{max-height: calc(100vh - 300px);} /* allow more room for stacked sidebar + banner */
    .nav-links a{text-align:center;padding:12px}
    .sidebar{padding:12px}
    .sidebar img{width:84px;height:84px}
    .card-banner .controls{flex-direction:column;gap:8px;align-items:flex-end}
    .card-banner .controls .btn{width:100%}

    /* Adjust card banner controls */
    .card-banner {
        flex-direction: column;
        align-items: stretch !important;
        gap: 10px;
        padding: 12px !important;
    }

    .card-banner .controls {
        display: flex;
        flex-wrap: wrap;
        gap: 4px !important;
        width: 100%;
    }

    /* Make buttons smaller and more compact on mobile */
    .card-banner .btn {
        padding: 4px 8px !important;
        font-size: 12px !important;
        min-height: 28px !important;
        flex: 1;
        white-space: nowrap;
        min-width: auto !important;
    }

    /* Adjust search form in scholars list */
    .card-banner form.d-flex {
        flex-wrap: wrap;
        width: 100%;
        gap: 4px !important;
    }

    .card-banner form.d-flex .form-control {
        height: 28px;
        font-size: 12px;
        padding: 4px 8px;
    }

    /* Ensure buttons stay in line */
    .card-banner .controls .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    /* Fix spacing for banner headings */
    .card-banner h4 {
        font-size: 16px !important;
        margin-bottom: 2px !important;
    }

    .card-banner small {
        font-size: 11px;
        opacity: 0.9;
    }
}

/* Desktop layout: fixed sidebar on the left and content to the right */
@media (min-width: 992px){
    .sidebar{
        position: fixed;
        left: 0;  /* Change from right: 0 to left: 0 */
        top: 0;
        bottom: 0;
        width: 240px;
        background: rgba(46,57,77,0.95);
        z-index: 1000;
        padding: 1rem;
        overflow-y: auto;
    }
    .nav-links a{text-align:left;padding:10px 12px}
    .main-content{
        margin-left: 240px;  /* Change from margin-right to margin-left */
        padding: 1rem;
        width: calc(100% - 240px);
    }

    /* Table should fill available vertical space beside the fixed sidebar */
    .table-responsive{
        max-height: calc(100vh - 200px);
    }
}

/* Extra polish */
.sidebar::-webkit-scrollbar{width:8px;height:8px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(0,0,0,0.2);border-radius:8px}
.table-responsive{scroll-behavior:smooth}

/* Sidebar toggle button (for mobile) */
.sidebar-toggle {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
    background: rgba(46,57,77,0.95);
    border: none;
    border-radius: 4px;
    padding: 8px;
    display: none;
    width: 40px;
    height: 40px;
    cursor: pointer;
}
.sidebar-toggle .navbar-toggler-icon {
    display: inline-block;
    width: 1.5em;
    height: 1.5em;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 100%;
}

/* Mobile styles */
@media (max-width: 768px) {
    .sidebar-toggle {
        display: block;
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1100;
        background: rgba(46,57,77,0.95);
        border: none;
        border-radius: 4px;
        padding: 8px;
        width: 40px;
        height: 40px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        background: rgba(46,57,77,1);
    }

    .sidebar {
        position: fixed;
        left: -240px; /* Hide sidebar by default */
        top: 0;
        bottom: 0;
        width: 240px;
        transition: left 0.3s ease;
        z-index: 1050;
    }

    .sidebar.active {
        left: 0; /* Show sidebar when active */
    }

    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 60px 15px 15px; /* Add top padding for toggle button */
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-active {
        margin-left: 0; /* Don't shift content when sidebar is open */
    }

    /* Ensure sidebar appears above content */
    .sidebar {
        box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    }
}

/* New dashboard card styles */
.dashboard-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    height: 100%;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.card-icon {
    font-size: 2rem;
    margin-bottom: 15px;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-content h2 {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0;
    color: #fff;
}

.card-content p {
    color: rgba(255, 255, 255, 0.8);
    margin: 5px 0;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-link {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.8rem;
    position: absolute;
    bottom: 15px;
    left: 15px;
    right: 15px;
    text-align: center;
}

.dashboard-card {
    min-height: 180px;
    padding: 15px;
}

/* Adjust icon size and position */
.card-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 10px;
    font-size: 1.5rem;
}

/* restore dashboard card colors (only color rules) */
.scholars-card  { background: linear-gradient(135deg,#7b2ff7 0%,#c850c0 100%) !important; }
.raising-card   { background: linear-gradient(135deg,#2ecc71 0%,#27ae60 100%) !important; }
.retreat-card   { background: linear-gradient(135deg,#ff6b6b 0%,#ff8c94 100%) !important; }
.attendance-card { background: linear-gradient(135deg,#0072ff 0%,#00c6ff 100%) !important; }
    </style>
</head>
<body>
    <!-- Add this button at the start of body, before sidebar -->
<button id="sidebarToggle" class="sidebar-toggle">
    <span class="navbar-toggler-icon"></span>
</button>

    <div class="sidebar">
        <img src="images/CMC_Logo.jpg" alt="CMC Logo" class="rounded-circle mb-2 mt-0" style="width:120px;height:120px;border-radius:0;margin-left:50px;margin-right:50px;">
        <h3>CARMEN MUNICIPAL COLLEGE</h3>
        <p style="margin-bottom:12px;color:#fff">Poblacion Sur, Carmen, Bohol</p>

        <ul class="nav-links">
            <li>
                <a href="admin_dashboard.php?tab=dashboard" class="<?php echo $tab==='dashboard' ? 'active':''; ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="attendance_approval.php" class="nav-link" style="color:#fff; background:#dc3545; font-weight:bold;">
                    <i class="fas fa-camera"></i> Photo Attendance Approval
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=attendance&ceremony=raising" class="<?php echo ($tab==='attendance' && $ceremony==='raising') ? 'active':''; ?>">
                    <i class="fas fa-flag-usa"></i> Recent Attendance (Flag Raising)
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=attendance&ceremony=retreat" class="<?php echo ($tab==='attendance' && $ceremony==='retreat') ? 'active':''; ?>">
                    <i class="fas fa-flag"></i> Recent Attendance (Flag Retreat)
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=scholars" class="<?php echo $tab==='scholars' ? 'active':''; ?>">
                    <i class="fas fa-user-graduate"></i> Scholars List
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=add" class="<?php echo $tab==='add' ? 'active':''; ?>">
                    <i class="fas fa-user-plus"></i> Add Scholar
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=reports" class="<?php echo $tab==='reports' ? 'active':''; ?>">
                    <i class="fas fa-chart-bar"></i> Monthly Reports
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?tab=attendance" class="<?php echo $tab==='attendance' ? 'active':''; ?>">
                    <i class="fas fa-arrow-left"></i> Back to Attendance
                </a>
            </li>
            <li>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="container-fluid" style="max-width:var(--content-max)">
            <?php if ($tab === 'dashboard'): ?>
                <div class="row g-3 mb-4">
        <!-- Total Scholars Card -->
        <div class="col-md-3 col-sm-6">
            <div class="dashboard-card scholars-card">
                <div class="card-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="card-content">
                    <h2>
                        <?php
                        $scholarCount = $mysqli->query("SELECT COUNT(*) as total FROM students")->fetch_assoc();
                        echo $scholarCount['total'];
                        ?>
                    </h2>
                    <p>TOTAL SCHOLARS</p>
                    <a href="admin_dashboard.php?tab=scholars" class="card-link">Click to manage</a>
                </div>
            </div>
        </div>

        <!-- Today's Flag Raising Card -->
        <div class="col-md-3 col-sm-6">
            <div class="dashboard-card raising-card">
                <div class="card-icon">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="card-content">
                    <h2>
                        <?php
                        $today = date('Y-m-d');
                        $raisingCount = $mysqli->query("SELECT COUNT(*) as total FROM attendance 
                            WHERE date = '$today' AND day = 'Monday'")->fetch_assoc();
                        echo $raisingCount['total'];
                        ?>
                    </h2>
                    <p>TODAY'S FLAG RAISING</p>
                    <a href="admin_dashboard.php?tab=attendance&ceremony=raising" class="card-link">Click to view details</a>
                </div>
            </div>
        </div>

        <!-- Today's Flag Retreat Card -->
        <div class="col-md-3 col-sm-6">
            <div class="dashboard-card retreat-card">
                <div class="card-icon">
                    <i class="fas fa-flag-usa"></i>
                </div>
                <div class="card-content">
                    <h2>
                        <?php
                        $retreatCount = $mysqli->query("SELECT COUNT(*) as total FROM attendance 
                            WHERE date = '$today' AND day = 'Friday'")->fetch_assoc();
                        echo $retreatCount['total'];
                        ?>
                    </h2>
                    <p>TODAY'S FLAG RETREAT</p>
                    <a href="admin_dashboard.php?tab=attendance&ceremony=retreat" class="card-link">Click to view details</a>
                </div>
            </div>
        </div>

        <!-- Monthly Attendance Card -->
        <div class="col-md-3 col-sm-6">
            <div class="dashboard-card attendance-card">
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="card-content">
                    <h2>
                        <?php
                        $currentMonth = date('m');
                        $currentYear = date('Y');
                        $monthlyQuery = "SELECT 
                            (COUNT(CASE WHEN status = 'Present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as percentage
                            FROM attendance 
                            WHERE MONTH(date) = '$currentMonth' AND YEAR(date) = '$currentYear'";
                        $monthlyAttendance = $mysqli->query($monthlyQuery)->fetch_assoc();
                        echo round($monthlyAttendance['percentage'] ?? 0) . '%';
                        ?>
                    </h2>
                    <p>MONTHLY ATTENDANCE</p>
                    <a href="admin_dashboard.php?tab=reports" class="card-link">Click for reports</a>
                </div>
            </div>
        </div>
    </div>

   
    <?php
    // expected (total scholars)
    $expectedRow = $mysqli->query("SELECT COUNT(*) AS cnt FROM students")->fetch_assoc();
    $expected = (int)($expectedRow['cnt'] ?? 0);

    // helper to get counts by day + day-date
    function get_day_counts($mysqli, $date, $day, $currentDate) {
        // If the ceremony date is in the future, return zeros
        if (strtotime($date) > strtotime($currentDate)) {
            return ['recorded' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
        }

        $res = ['recorded' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
        $q = "SELECT 
                COUNT(*) AS recorded,
                COUNT(CASE WHEN TIME(time_in) <= '07:30:00' THEN 1 END) AS present,
                COUNT(CASE WHEN TIME(time_in) > '07:30:00' AND TIME(time_in) <= '08:00:00' THEN 1 END) AS late
              FROM attendance
              WHERE DATE(date) = ? AND day = ?";
        if ($stmt = $mysqli->prepare($q)) {
            $stmt->bind_param('ss', $date, $day);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $res['recorded'] = (int)($r['recorded'] ?? 0);
            $res['present'] = (int)($r['present'] ?? 0);
            $res['late'] = (int)($r['late'] ?? 0);
            // Absent = recorded entries whose status is Absent (i.e. recorded - present - late)
            $res['absent'] = max(0, $res['recorded'] - $res['present'] - $res['late']);
        }
        return $res;
    }

    $mon_date = $thisWeekMonday;
    $fri_date = $thisWeekFriday;
    $monday = get_day_counts($mysqli, $mon_date, 'Monday', $currentDate);
    $friday = get_day_counts($mysqli, $fri_date, 'Friday', $currentDate);

    $totalRecorded = $monday['recorded'] + $friday['recorded'];
    $totalExpected = $expected * 2; // two ceremonies per week
    $missing = max(0, $totalExpected - $totalRecorded);
    $completion = $totalExpected ? round($totalRecorded / $totalExpected * 100) : 0;
    ?>
    <div class="card mb-3">
        <div class="card-banner d-flex justify-content-between align-items-center">
            <div style="display:flex;gap:1rem;align-items:center;">
                <i class="fas fa-list-ul" style="font-size:1.1rem;margin-right:.4rem"></i>
                <h4 style="margin:0">Weekly Progress Overview</h4>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <small class="badge bg-light text-dark">Week #<?php echo date('W'); ?></small>
                <button class="btn btn-primary btn-sm" onclick="location.reload()">Refresh</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div style="padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.04);background:#fff;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><i class="fas fa-calendar-day text-primary"></i> Monday (Flag Raising):</strong>
                                <div style="font-size:0.9rem;color:#666"><?php echo date('M j, Y', strtotime($mon_date)); ?></div>
                            </div>
                            <div style="font-size:1.25rem;font-weight:700;color:#1e88e5"><?php echo $monday['recorded']; ?></div>
                        </div>
                        <div style="height:8px;background:#eee;border-radius:8px;overflow:hidden;margin-bottom:6px;">
                            <div style="width:<?php echo $expected ? ($monday['recorded']/$expected*100) : 0; ?>%;height:100%;background:linear-gradient(90deg,#66bb6a,#2e7d32)"></div>
                        </div>
                        <small><?php echo $monday['present']; ?> Present, <?php echo $monday['absent']; ?> Absent, <?php echo $monday['late']; ?> Late</small>
                    </div>

                    <div style="height:12px"></div>

                    <div style="padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.04);background:#fff;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><i class="fas fa-flag-checkered text-success"></i> Friday (Flag Retreat):</strong>
                                <div style="font-size:0.9rem;color:#666"><?php echo date('M j, Y', strtotime($fri_date)); ?></div>
                            </div>
                            <div style="font-size:1.25rem;font-weight:700;color:#2e7d32"><?php echo $friday['recorded']; ?></div>
                        </div>
                        <div style="height:8px;background:#eee;border-radius:8px;overflow:hidden;margin-bottom:6px;">
                            <div style="width:<?php echo $expected ? ($friday['recorded']/$expected*100) : 0; ?>%;height:100%;background:linear-gradient(90deg,#42a5f5,#1565c0)"></div>
                        </div>
                        <small><?php echo $friday['present']; ?> Present, <?php echo $friday['absent']; ?> Absent, <?php echo $friday['late']; ?> Late</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div style="padding:12px;border-radius:8px;border:1px solid rgba(0,0,0,0.04);background:#fff;height:100%;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><i class="fas fa-check-circle text-info"></i> Weekly Completion:</strong>
                                <span class="badge bg-info text-white" style="font-size:1rem;"><?php echo $completion; ?>%</span>
                            </div>

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                                <div style="flex:1;min-width:120px;padding:10px;border-radius:8px;background:#f7f9fc;text-align:center;">
                                    <div style="font-size:1.25rem;font-weight:700"><?php echo $totalRecorded; ?></div>
                                    <div style="font-size:0.85rem;color:#666">Total Recorded</div>
                                </div>
                                <div style="flex:1;min-width:120px;padding:10px;border-radius:8px;background:#fff4e6;text-align:center;">
                                    <div style="font-size:1.25rem;font-weight:700;color:#ff9800"><?php echo $missing; ?></div>
                                    <div style="font-size:0.85rem;color:#666">Missing Attendance</div>
                                </div>
                                <div style="flex:1;min-width:120px;padding:10px;border-radius:8px;background:#f1f8ff;text-align:center;">
                                    <div style="font-size:1.25rem;font-weight:700"><?php echo $totalExpected; ?></div>
                                    <div style="font-size:0.85rem;color:#666">Expected Total (week)</div>
                                </div>
                            </div>

                            <div>
                                <canvas id="weeklyChart" height="140"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js (loaded only on dashboard) --> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const data = {
            labels: ['Monday (<?php echo date("M j", strtotime($mon_date)); ?>)', 'Friday (<?php echo date("M j", strtotime($fri_date)); ?>)'],
            datasets: [{
                label: 'Recorded',
                data: [<?php echo $monday['recorded']; ?>, <?php echo $friday['recorded']; ?>],
                backgroundColor: ['#42a5f5','#66bb6a'],
                borderRadius: 6,
                barThickness: 40
            }, {
                label: 'Missing (per day)',
                data: [<?php echo max(0,$expected - $monday['recorded']); ?>, <?php echo max(0,$expected - $friday['recorded']); ?>],
                backgroundColor: ['#ffcc80','#ffcc80'],
                borderRadius: 6,
                barThickness: 20
            }]
        };
        new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision:0 } }
                }
            }
        });
    })();
    </script>
    <?php endif; ?>
     
             <?php if ($tab === 'attendance'): ?>
                <div class="card mb-3">
                        <div class="card-banner d-flex justify-content-between align-items-center">
    <div>
        <h4 style="margin:0">
            <i class="fas fa-history"></i> Recent Attendance (<?php echo ucfirst($ceremony); ?>)
        </h4>
        <small style="opacity:.9">
            <i class="fas fa-calendar-alt"></i> Showing attendance for <?php echo $filterDay; ?>, 
            <?php echo date('M d, Y', strtotime($filterDate)); ?>
        </small>
    </div>
    <div class="controls">
                            <a class="btn btn-success btn-sm" href="admin_dashboard.php?tab=attendance&ceremony=raising">
                                <i class="fas fa-flag-usa"></i> Flag Raising
                            </a>
                            <a class="btn btn-primary btn-sm" href="admin_dashboard.php?tab=attendance&ceremony=retreat">
                                <i class="fas fa-flag"></i> Flag Retreat
                            </a>
                            <a class="btn btn-primary btn-sm fw-bold text-white" href="admin_dashboard.php?tab=scholars">
                                <i class="fas fa-user-graduate"></i> Scholars List
                            </a>
                            <a class="btn btn-success btn-sm btn-export" href="admin_dashboard.php?export=attendance_csv&ceremony=<?php echo $ceremony; ?>">
                                <i class="fas fa-file-export"></i> Export CSV
                            </a>
                            <button class="btn btn-outline-light btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
    <tr>
        <th style="width:48px"><i class="fas fa-hashtag" title="#"></i></th>
        <th><i class="fas fa-user" title="Student"></i> Student</th>
        <th><i class="fas fa-id-badge" title="Student ID"></i> Student ID</th>
        <th><i class="fas fa-calendar" title="Date"></i> Date</th>
        <th><i class="fas fa-calendar-day" title="Day"></i> Day</th>
        <th><i class="fas fa-clock" title="Time In"></i> Time In</th>
        <th><i class="fas fa-clipboard-check" title="Status"></i> Status</th>
    </tr>
</thead>
<tbody>
    <?php if ($result && $result->num_rows > 0): ?>
        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo safe_html($row['student_name'] ?? 'Unknown'); ?></td>
                <td><?php echo safe_html($row['student_id'] ?? ''); ?></td>
                <td><?php echo $row['date'] ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                <td><?php echo safe_html($row['day']); ?></td>
                <td><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : ''; ?></td>
                <td>
                    <?php
                    $status = $row['status'] ?? 'Unknown';
                    $statusClass = match($status) {
                        'Present' => 'text-success',
                        'Late' => 'text-warning',
                        'Absent' => 'text-danger',
                        default => 'text-secondary'
                    };
                    echo "<span class=\"{$statusClass}\">" . safe_html($status) . "</span>";
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="7" class="text-center p-4">No attendance records found.</td></tr>
    <?php endif; ?>
</tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($tab === 'scholars'): ?>
    <div class="card">
        <div class="card-banner d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">
                    <i class="fas fa-user-graduate"></i> Scholars List
                </h4>
                <small><i class="fas fa-info-circle"></i> Manage and view all scholars in the system</small>
            </div>
            <div class="controls">
                <form class="d-flex gap-2" method="get" action="admin_dashboard.php">
                    <input type="hidden" name="tab" value="scholars">
                    <input class="form-control form-control-sm" name="q" type="search" 
                        placeholder="Search scholars..." style="width: 200px;" 
                        value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                    <button class="btn btn-primary btn-sm px-3" style="min-width: 90px;" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (isset($_GET['q']) && !empty($_GET['q'])): ?>
                        <a href="admin_dashboard.php?tab=scholars" class="btn btn-light btn-sm px-3">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                    <a href="admin_dashboard.php?tab=add" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-user-plus"></i> Add Scholar
                    </a>
                    <a class="btn btn-success btn-sm px-3" href="admin_dashboard.php?export=scholars_csv">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:48px"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-user"></i> Fullname</th>
                            <th><i class="fas fa-id-card"></i> Student ID</th>
                            <th><i class="fas fa-book"></i> Course</th>
                            <th><i class="fas fa-graduation-cap"></i> Year</th>
                            <th><i class="fas fa-layer-group"></i> Section</th>
                            <th><i class="fas fa-award"></i> Scholarship</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php $i = 1; foreach ($students as $st): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo safe_html($st['fullname']); ?></td>
                                    <td><?php echo safe_html($st['student_id']); ?></td>
                                    <td><?php echo safe_html($st['course']); ?></td>
                                    <td><?php echo safe_html($st['year']); ?></td>
                                    <td><?php echo safe_html($st['section']); ?></td>
                                    <td><?php echo safe_html($st['scholarship_type']); ?></td>
                                    <td>
                                        <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;">
                                            <a href="admin_dashboard.php?tab=edit&student_id=<?php echo urlencode($st['student_id']); ?>"
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <form method="post" action="admin_dashboard.php" style="display:inline;margin:0;"
                                                  onsubmit="return confirm('Delete this scholar?');" aria-label="Delete scholar">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($st['student_id']); ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center p-4">No scholars found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

            <?php elseif ($tab === 'add'): ?>

                <div class="card">
                    <div class="card-banner d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-user-plus"></i> Add Scholar
                            </h4>
                            <small>
                                <i class="fas fa-pen"></i> Add new scholar to the system
                            </small>
                        </div>
                        <div class="controls">
                            <a href="admin_dashboard.php?tab=scholars" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> Back to Scholars List
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form method="post" action="admin_dashboard.php">
                            <input type="hidden" name="action" value="add_student">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-id-card"></i> Student ID
                                    </label>
                                    <input class="form-control" name="student_id" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Fullname
                                    </label>
                                    <input class="form-control" name="fullname" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-book"></i> Course
                                    </label>
                                    <input class="form-control" name="course">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">
                                        <i class="fas fa-graduation-cap"></i> Year
                                    </label>
                                    <input class="form-control" name="year">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">
                                        <i class="fas fa-layer-group"></i> Section
                                    </label>
                                    <input class="form-control" name="section">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-award"></i> Scholarship Type
                                    </label>
                                    <input class="form-control" name="scholarship_type">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-plus-circle"></i> Add Scholar
                                </button>
                                <a class="btn btn-secondary" href="admin_dashboard.php?tab=scholars">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'edit' && $editStudent): ?>

    <div class="card">
        <div class="card-banner d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">
                    <i class="fas fa-user-edit"></i> Edit Scholar
                </h4>
                <small>
                    <i class="fas fa-id-badge"></i> Student ID: <?php echo safe_html($editStudent['student_id']); ?>
                </small>
            </div>
            <div class="controls">
                <a href="admin_dashboard.php?tab=scholars" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Scholars List
                </a>
            </div>
        </div>

        <div class="card-body">
            <form method="post" action="admin_dashboard.php">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" name="student_id" value="<?php echo safe_html($editStudent['student_id']); ?>">
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><i class="fas fa-id-card"></i> Student ID</label>
                        <input class="form-control" readonly value="<?php echo safe_html($editStudent['student_id']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fas fa-user"></i> Fullname</label>
                        <input class="form-control" name="fullname" value="<?php echo safe_html($editStudent['fullname']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fas fa-book"></i> Course</label>
                        <input class="form-control" name="course" value="<?php echo safe_html($editStudent['course']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-graduation-cap"></i> Year</label>
                        <input class="form-control" name="year" value="<?php echo safe_html($editStudent['year']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Section</label>
                        <input class="form-control" name="section" value="<?php echo safe_html($editStudent['section']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fas fa-award"></i> Scholarship Type</label>
                        <input class="form-control" name="scholarship_type" value="<?php echo safe_html($editStudent['scholarship_type']); ?>">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a class="btn btn-secondary" href="admin_dashboard.php?tab=scholars">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

            <?php elseif ($tab === 'reports'): ?>
    <?php
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    $months = array(
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December'
    );

    $selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
    $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
    ?>

    <div class="card">
        <div class="card-banner d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Monthly Attendance Summary
                </h4>
                <small>
                    <i class="fas fa-file-export"></i> View and export monthly attendance records
                </small>
            </div>
            <div class="controls">
                <button class="btn btn-light btn-sm" onclick="window.location.href='monthly_report.php'">
                    <i class="fas fa-file-alt"></i> View Full Report
                </button>
                <button class="btn btn-dark btn-sm" onclick="window.location.href='admin_dashboard.php?tab=attendance'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>
        </div>

        <div class="card-body">
            <form method="get" action="admin_dashboard.php" class="row g-3 mb-4">
                <input type="hidden" name="tab" value="reports">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Month</label>
                    <select class="form-select" name="month">
                        <?php foreach($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $selectedMonth == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-year"></i> Year</label>
                    <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> View Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-file-export"></i> Export CSV
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-responsive mb-4" style="position: relative;">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="4" class="text-center bg-success text-black">
                                <i class="fas fa-flag-usa"></i> Flag Raising Ceremony (Monday)
                            </th>
                            <th colspan="3" class="text-center bg-primary text-black">
                                <i class="fas fa-flag"></i> Flag Retreat Ceremony (Friday)
                            </th>
                        </tr>
                        <tr>
                            <th><i class="fas fa-check text-success"></i> Present</th>
                            <th><i class="fas fa-clock text-warning"></i> Late</th>
                            <th><i class="fas fa-times text-danger"></i> Absent</th>
                            <th><i class="fas fa-list"></i> Total</th>
                            <th><i class="fas fa-list"></i> Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get attendance summary for selected month/year
                        $summaryQuery = "
                            SELECT 
                                COUNT(CASE WHEN day = 'Monday' AND time_in <= '07:30:00' THEN 1 END) as raising_present,
                                COUNT(CASE WHEN day = 'Monday' AND time_in > '07:30:00' AND time_in <= '08:00:00' THEN 1 END) as raising_late,
                                COUNT(CASE WHEN day = 'Monday' AND (time_in > '08:00:00' OR time_in IS NULL) THEN 1 END) as raising_absent,
                                COUNT(CASE WHEN day = 'Friday' THEN 1 END) as retreat_total
                            FROM attendance 
                            WHERE MONTH(date) = ? AND YEAR(date) = ?
                        ";
                        
                        $stmt = $mysqli->prepare($summaryQuery);
                        $stmt->bind_param('ss', $selectedMonth, $selectedYear);
                        $stmt->execute();
                        $summary = $stmt->get_result()->fetch_assoc();
                        ?>
                        <tr>
                            <td><?php echo $summary['raising_present'] ?? 0; ?></td>
                            <td><?php echo $summary['raising_late'] ?? 0; ?></td>
                            <td><?php echo $summary['raising_absent'] ?? 0; ?></td>
                            <td><?php echo ($summary['raising_present'] ?? 0) + ($summary['raising_late'] ?? 0) + ($summary['raising_absent'] ?? 0); ?></td>
                            <td><?php echo $summary['retreat_total'] ?? 0; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent Records Table -->
            <h5 class="mb-3"><i class="fas fa-history"></i> Recent Attendance Records</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-striped mb-0">
                    <thead class="sticky-top">
                        <tr class="bg-light">
                            <th style="width: 50px"><i class="fas fa-hashtag"></i></th>
                            <th style="min-width: 200px"><i class="fas fa-user"></i> Student</th>
                            <th style="min-width: 120px"><i class="fas fa-calendar"></i> Date</th>
                            <th style="min-width: 100px"><i class="fas fa-clock"></i> Time In</th>
                            <th style="min-width: 100px"><i class="fas fa-clipboard-check"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get recent attendance records for selected month/year
                        $recordsQuery = "SELECT a.*,
                            COALESCE(s.fullname, a.fullname, a.student_id) AS resolved_fullname,
                             DATE_FORMAT(a.time_in, '%h:%i %p') as formatted_time
                             FROM attendance a 
                             LEFT JOIN students s ON a.student_id = s.student_id
                             WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
                             ORDER BY a.date DESC, a.time_in DESC";
                        
                        $stmt = $mysqli->prepare($recordsQuery);
                        $stmt->bind_param('ss', $selectedMonth, $selectedYear);
                        $stmt->execute();
                        $records = $stmt->get_result();
                        
                        if ($records->num_rows > 0):
                            $i = 1;
                            while ($row = $records->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo safe_html($row['resolved_fullname'] ?? $row['student_id'] ?? ''); ?></td>
                                <td><?php echo safe_html($row['date'] ?? ''); ?></td>
                                <td><?php echo safe_html($row['formatted_time']); ?></td>
                                <td><?php echo safe_html($row['status']); ?></td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="text-center">No records found for this month.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function exportReport() {
        const month = document.querySelector('select[name="month"]').value;
        const year = document.querySelector('input[name="year"]').value;
        window.location.href = `admin_dashboard.php?export=monthly_report&month=${month}&year=${year}`;
    }
    </script>
            <?php else: ?>
                <!-- <div class="alert alert-info" style="max-width: 400px">Select a tab to view its content.</div> -->
            <?php endif; ?>
        </div>
    </div>

    <!-- Add this near the top of the visible content area -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo safe_html($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (sidebarToggle && sidebar && mainContent) {
        // Toggle sidebar when menu button is clicked
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) { // Only on mobile
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('sidebar-active');
                }
            }
        });

        // Close sidebar when clicking any nav link (mobile only)
        const navLinks = document.querySelectorAll('.nav-links a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('sidebar-active');
                }
            });
        });

        // Update sidebar state on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('sidebar-active');
            }
        
        });
    }
});
</script>
</body>
</html>