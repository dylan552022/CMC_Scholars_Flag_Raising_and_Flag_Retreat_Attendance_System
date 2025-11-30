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

// attendance query (filtered by day for the selected ceremony)
$q = "
  SELECT
    a.id,
    COALESCE(s.fullname, a.student_id) AS student_name, 
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
  ORDER BY a.date DESC, a.time_in DESC
  LIMIT 200
";

$stmt = $mysqli->prepare($q);
if ($stmt) {
    $stmt->bind_param('s', $filterDay);
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
          SELECT a.id, COALESCE(s.fullname, a.student_id) AS student_name, a.student_id, a.date, a.day, a.time_in,
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
        .controls .btn{min-height:34px}

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
            <li><a href="admin_dashboard.php?tab=attendance&ceremony=raising" class="<?php echo ($tab==='attendance' && $ceremony==='raising') ? 'active':''; ?>">Recent Attendance (Flag Raising)</a></li>
            <li><a href="admin_dashboard.php?tab=attendance&ceremony=retreat" class="<?php echo ($tab==='attendance' && $ceremony==='retreat') ? 'active':''; ?>">Recent Attendance (Flag Retreat)</a></li>
            <li><a href="admin_dashboard.php?tab=scholars" class="<?php echo $tab==='scholars' ? 'active':''; ?>">Scholars List</a></li>
            <li><a href="admin_dashboard.php?tab=add" class="<?php echo $tab==='add' ? 'active':''; ?>">Add Scholar</a></li>
            <li><a href="admin_dashboard.php?tab=reports" class="<?php echo $tab==='reports' ? 'active':''; ?>">Monthly Reports</a></li>
            <li><a href="admin_dashboard.php?tab=attendance" class="<?php echo $tab==='attendance' ? 'active':''; ?>">Back to Attendance</a></li>
            <li><a href="logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="container-fluid" style="max-width:var(--content-max)">
            <?php if ($tab === 'attendance'): ?>
                <div class="card mb-3">
                    <div class="card-banner d-flex justify-content-between align-items-center">
                        <div>
                            <h4 style="margin:0">Recent Attendance (<?php echo ucfirst($ceremony); ?>)</h4>
                            <small style="opacity:.9">Showing recent <?php echo htmlspecialchars($ceremony); ?> attendance (filtered by <?php echo htmlspecialchars($filterDay); ?>)</small>
                        </div>
                        <div class="controls">
                            <a class="btn btn-success btn-sm" href="admin_dashboard.php?tab=attendance&ceremony=raising">Flag Raising</a>
                            <a class="btn btn-primary btn-sm" href="admin_dashboard.php?tab=attendance&ceremony=retreat">Flag Retreat</a>
                            <a class="btn btn-primary btn-sm fw-bold text-white" href="admin_dashboard.php?tab=scholars">Scholars List</a>

                            <!-- Export / Print -->
                            <a class="btn btn-success btn-sm btn-export" href="admin_dashboard.php?export=attendance_csv&ceremony=<?php echo $ceremony; ?>">Export CSV</a>
                            <button class="btn btn-outline-light btn-sm" onclick="window.print()">Print</button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:48px">#</th>
                                        <th>Student</th>
                                        <th>Student ID</th>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Time In</th>
                                        <th>Status</th>
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
                                                <td><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : ''; ?></td>
                                                <td>
                                                    <?php
                                                        $status = $row['status'] ?? 'Unknown';
                                                        $cls = match($status) {
                                                            'Present' => 'text-success',
                                                            'Late' => 'text-warning',
                                                            'Absent' => 'text-danger',
                                                            default => 'text-secondary'
                                                        };
                                                    ?>
                                                    <span class="<?php echo $cls; ?>"><?php echo safe_html($status); ?></span>
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
                <h4 class="mb-0">Scholars List</h4>
                <small>Manage and view all scholars in the system</small>
            </div>
            <div class="controls">
                <form class="d-flex gap-2" method="get" action="admin_dashboard.php">
                    <input type="hidden" name="tab" value="scholars">
                    <input class="form-control form-control-sm" name="q" type="search" 
                        placeholder="Search scholars..." style="width: 200px;" 
                        value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                    <button class="btn btn-primary btn-sm px-3" style="min-width: 90px;" type="submit">Search</button>
                    <?php if (isset($_GET['q']) && !empty($_GET['q'])): ?>
                        <a href="admin_dashboard.php?tab=scholars" class="btn btn-light btn-sm px-3">Clear</a>
                    <?php endif; ?>
                    <a href="admin_dashboard.php?tab=add" class="btn btn-primary btn-sm px-3">Add Scholar</a>
                    <a class="btn btn-success btn-sm px-3" href="admin_dashboard.php?export=scholars_csv">Export CSV</a>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:48px">#</th>
                            <th>Fullname</th>
                            <th>Student ID</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Section</th>
                            <th>Scholarship</th>
                            <th>Actions</th>
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
                                        <a class="btn btn-sm btn-outline-primary" href="admin_dashboard.php?tab=edit&student_id=<?php echo urlencode($st['student_id']); ?>">Edit</a>
                                        <form method="post" action="admin_dashboard.php" style="display:inline" onsubmit="return confirm('Delete this scholar?');">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($st['student_id']); ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
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
                            <h4 class="mb-0">Add Scholar</h4>
                            <small>Add new scholar to the system</small>
                        </div>
                        <div class="controls">
                            <a href="admin_dashboard.php?tab=scholars" class="btn btn-light btn-sm">Back to Scholars List</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form method="post" action="admin_dashboard.php">
                            <input type="hidden" name="action" value="add_student">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Student ID</label>
                                    <input class="form-control" name="student_id" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Fullname</label>
                                    <input class="form-control" name="fullname" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Course</label>
                                    <input class="form-control" name="course">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Year</label>
                                    <input class="form-control" name="year">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Section</label>
                                    <input class="form-control" name="section">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Scholarship Type</label>
                                    <input class="form-control" name="scholarship_type">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" type="submit">Add Scholar</button>
                                <a class="btn btn-secondary" href="admin_dashboard.php?tab=scholars">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'edit' && $editStudent): ?>

                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-3">Edit Scholar — <?php echo htmlspecialchars($editStudent['student_id']); ?></h4>
                        <form method="post" action="admin_dashboard.php">
                            <input type="hidden" name="action" value="edit_student">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($editStudent['student_id']); ?>">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label">Fullname</label>
                                    <input class="form-control" name="fullname" value="<?php echo safe_html($editStudent['fullname']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Course</label>
                                    <input class="form-control" name="course" value="<?php echo safe_html($editStudent['course']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Year</label>
                                    <input class="form-control" name="year" value="<?php echo safe_html($editStudent['year']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Section</label>
                                    <input class="form-control" name="section" value="<?php echo safe_html($editStudent['section']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Scholarship Type</label>
                                    <input class="form-control" name="scholarship_type" value="<?php echo safe_html($editStudent['scholarship_type']); ?>">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" type="submit">Save Changes</button>
                                <a class="btn btn-secondary" href="admin_dashboard.php?tab=scholars">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'reports'): ?>
    <?php
    // Get current month and year
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    // Array of months
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

    // Get selected month/year or use current
    $selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
    $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
    ?>

    <div class="card">
        <div class="card-banner d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Monthly Attendance Summary</h4>
                <small>View and export monthly attendance records</small>
            </div>
            <div class="controls">
                <button class="btn btn-light btn-sm" onclick="window.location.href='monthly_report.php'">View Full Report</button>
                <button class="btn btn-dark btn-sm" onclick="window.location.href='admin_dashboard.php?tab=attendance'">Back to Dashboard</button>
            </div>
        </div>

        <div class="card-body">
            <!-- Month/Year Selection Form -->
            <form method="get" action="admin_dashboard.php" class="row g-3 mb-4">
                <input type="hidden" name="tab" value="reports">
                <div class="col-md-4">
                    <label class="form-label">Month</label>
                    <select class="form-select" name="month">
                        <?php foreach($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $selectedMonth == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Year</label>
                    <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">View Report</button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">Export CSV</button>
                    </div>
                </div>
            </form>

            <!-- Summary Table -->
            <div class="table-responsive mb-4" style="position: relative;">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="4" class="text-center bg-success text-black">Flag Raising Ceremony (Monday)</th>
                            <th colspan="3" class="text-center bg-primary text-black">Flag Retreat Ceremony (Friday)</th>
                        </tr>
                        <tr>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                            <th>Total</th>
                            <th>Total</th>
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
            <h5 class="mb-3">Recent Attendance Records</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-striped mb-0">
                    <thead class="sticky-top">
                        <tr class="bg-light">
                            <th style="width: 50px">#</th>
                            <th style="min-width: 200px">Student</th>
                            <th style="min-width: 120px">Date</th>
                            <th style="min-width: 100px">Time In</th>
                            <th style="min-width: 100px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get recent attendance records for selected month/year
                        $recordsQuery = "SELECT a.*, s.fullname,
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
                                <td><?php echo safe_html($row['fullname'] ?? ''); ?></td>
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
                <div class="alert alert-info">Tab not found.</div>
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