<?php
// Start session and include database connection
session_start();
require_once('db_connect.php');

// Check admin login
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Ensure we have a mysqli connection
if (!isset($mysqli)) {
    if (isset($conn)) $mysqli = $conn;
    elseif (isset($link)) $mysqli = $link;
    elseif (isset($db)) $mysqli = $db;
    else {
        die("Database connection not found. Check db_connect.php");
    }
}

// Get month and year from query params or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get monthly attendance data
$query = "
    SELECT 
        s.student_id,
        s.fullname,
        -- Monday (Flag Raising)
        COUNT(CASE WHEN a.day = 'Monday' THEN 1 END) as mon_total,
        COUNT(CASE WHEN a.day = 'Monday' AND TIME(a.time_in) <= '07:30:00' THEN 1 END) as mon_present,
        COUNT(CASE WHEN a.day = 'Monday' AND TIME(a.time_in) > '07:30:00' AND TIME(a.time_in) <= '08:00:00' THEN 1 END) as mon_late,
        COUNT(CASE WHEN a.day = 'Monday' AND (a.time_in IS NULL OR TIME(a.time_in) > '08:00:00') THEN 1 END) as mon_absent,
        -- Friday (Flag Retreat)
        COUNT(CASE WHEN a.day = 'Friday' THEN 1 END) as fri_total,
        COUNT(CASE WHEN a.day = 'Friday' AND TIME(a.time_in) >= '16:00:00' AND TIME(a.time_in) <= '17:00:00' THEN 1 END) as fri_present,
        COUNT(CASE WHEN a.day = 'Friday' AND (a.time_in IS NULL OR TIME(a.time_in) < '16:00:00' OR TIME(a.time_in) > '17:00:00') THEN 1 END) as fri_absent,
        -- capture last login as combined datetime so we can split date/time reliably
        MAX(CONCAT(a.date, ' ', IFNULL(a.time_in, '00:00:00'))) as last_login_dt
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id 
        AND MONTH(a.date) = ?
        AND YEAR(a.date) = ?
    GROUP BY s.student_id, s.fullname
    ORDER BY s.fullname ASC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$result = $stmt->get_result();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d_His') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers (split Last Login into Date and Time)
    fputcsv($output, array(
        '#',
        'Student ID',
        'Fullname',
        'Flag Raising - Present',
        'Flag Raising - Late',
        'Flag Raising - Absent',
        'Flag Raising - Total',
        'Flag Retreat - Present',
        'Flag Retreat - Absent',
        'Flag Retreat - Total',
        'Total Absences',
        'Last Login Date',
        'Last Login Time'
    ));
    
    // Add data rows
    if ($result && $result->num_rows > 0) {
        $i = 1;
        while ($row = $result->fetch_assoc()) {
            $last_dt = $row['last_login_dt'] ? strtotime($row['last_login_dt']) : false;
            $last_date = $last_dt ? date('Y-m-d', $last_dt) : '-';
            $last_time = $last_dt ? date('h:i A', $last_dt) : '-';
            fputcsv($output, array(
                $i++,
                $row['student_id'],
                $row['fullname'],
                $row['mon_present'],
                $row['mon_late'],
                $row['mon_absent'],
                $row['mon_total'],
                $row['fri_present'],
                $row['fri_absent'],
                $row['fri_total'],
                ($row['mon_absent'] + $row['fri_absent']),
                $last_date,
                $last_time
            ));
        }
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Attendance Summary</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
    <link rel="stylesheet" href="fontawesome/css/all.min.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="" crossorigin="anonymous" media="print" onload="this.media='all'">
    <style>
        body { 
            background: linear-gradient(180deg, #ffffff 0%,  #ffffff 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Poppins', system-ui, sans-serif;
            background-image: url(images/background_image.jpg);
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(90deg,#0b63d6 0%,#075ad5 100%);
            color: #fff;
            padding: 20px;
            border: none;
        }
        .flag-raising { background: #198754 !important; }
        .flag-retreat { background: #0d6efd !important; }
        .table { margin-bottom: 0; }
        .btn-toolbar { gap: 8px; }
        
        /* Header buttons */
.header-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}

.header-controls .btn,
.header-controls .form-select,
.header-controls .form-control {
    height: 31px;
    padding: 4px 12px;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 4px;
}

.header-controls .form-select {
    width: 120px;
}

.header-controls .form-control[type="number"] {
    width: 80px;
}

.header-controls .btn-primary,
.header-controls .btn-success,
.header-controls .btn-dark {
    min-width: 70px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.header-controls .btn:hover {
    transform: translateY(-1px);
    transition: transform 0.2s;
}

/* Common table styles */
.table {
    width: 100%;
    margin-bottom: 1rem;    
    color: #212529;
}

.table th {
    text-align: inherit;
}

/* Table hover and striped styles */
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Responsive table styles */
.table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
}

/* Page specific styles */
.card {
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.card-title {
    margin-bottom: 0.5rem;
}

.card-subtitle {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: #6c757d;
}

/* Slightly speed up or slow animations if needed */
.fa-beat    { --fa-animation-duration: 1.1s; }
.fa-bounce  { --fa-animation-duration: 1.3s; }
.fa-fade    { --fa-animation-duration: 1.8s; }
.fa-spin    { --fa-animation-duration: 1.6s; }

/* ensure icons in headers align nicely */
th i { margin-right: .45rem; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">
                        <!-- animated banner icon -->
                        <i class="fas fa-chart-bar fa-beat fa-lg" aria-hidden="true"></i>
                        Monthly Attendance Summary
                    </h4>
                    <small>
                        <i class="fas fa-calendar-alt fa-fade" aria-hidden="true"></i>
                        Summary for month: <?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?>
                    </small>
                </div>
                <div class="header-controls">
                    <form class="d-flex gap-2" method="get">
                        <!-- month selector (icon outside select) -->
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <i class="fas fa-calendar-month fa-beat" aria-hidden="true"></i>
                            <select name="month" class="form-select form-select-sm">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>>
                                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div style="display:flex;align-items:center;gap:.5rem">
                            <i class="fas fa-calendar fa-fade" aria-hidden="true"></i>
                            <input type="number" name="year" class="form-control form-control-sm" style="width:100px" value="<?php echo $year; ?>">
                        </div>

                        <button type="submit" class="btn btn-light btn-sm">
                            <i class="fas fa-search fa-flip fa-fw" aria-hidden="true"></i> View
                        </button>

                        <a href="?export=csv&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-file-export fa-bounce fa-fw" aria-hidden="true"></i> Export CSV
                        </a>

                        <a href="admin_dashboard.php?tab=attendance" class="btn btn-dark btn-sm">
                            <i class="fas fa-arrow-left fa-beat" aria-hidden="true"></i> Back to Dashboard
                        </a>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <!-- ensure every <th> has an animated icon -->
                            <th rowspan="2"><i class="fas fa-hashtag fa-beat" aria-hidden="true"></i></th>
                            <th rowspan="2"><i class="fas fa-id-card fa-fade" aria-hidden="true"></i> Student ID</th>
                            <th rowspan="2"><i class="fas fa-user fa-beat" aria-hidden="true"></i> Fullname</th>

                            <th colspan="4" class="text-center flag-raising">
                                <i class="fas fa-flag-usa fa-beat fa-lg" aria-hidden="true"></i> Flag Raising (Mon)
                            </th>

                            <th colspan="4" class="text-center flag-retreat">
                                <i class="fas fa-flag fa-beat fa-lg" aria-hidden="true"></i> Flag Retreat (Fri)
                            </th>

                            <th rowspan="2"><i class="fas fa-calendar-check fa-beat" aria-hidden="true"></i> Last Login Date</th>
                            <th rowspan="2"><i class="fas fa-clock fa-spin" aria-hidden="true"></i> Last Login Time</th>
                        </tr>
                        <tr>
                            <th class="text-center">
                                <i class="fas fa-check-circle text-success fa-bounce" aria-hidden="true"></i> Present
                            </th>
                            <th class="text-center">
                                <i class="fas fa-clock text-warning fa-spin" aria-hidden="true"></i> Late
                            </th>
                            <th class="text-center">
                                <i class="fas fa-times-circle text-danger fa-shake" aria-hidden="true"></i> Absent
                            </th>
                            <th class="text-center">
                                <i class="fas fa-list fa-beat" aria-hidden="true"></i> Total
                            </th>

                            <th class="text-center">
                                <i class="fas fa-check-circle text-success fa-bounce" aria-hidden="true"></i> Present
                            </th>
                            <th class="text-center">
                                <i class="fas fa-times-circle text-danger fa-shake" aria-hidden="true"></i> Absent
                            </th>
                            <th class="text-center">
                                <i class="fas fa-list fa-beat" aria-hidden="true"></i> Total
                            </th>
                            <th class="text-center">
                                <i class="fas fa-exclamation-circle text-danger fa-fade" aria-hidden="true"></i> Total Absences
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $i = 1; while($row = $result->fetch_assoc()): ?>
                                <?php
                                    $last_dt = $row['last_login_dt'] ? strtotime($row['last_login_dt']) : false;
                                    $last_date = $last_dt ? date('Y-m-d', $last_dt) : '-';
                                    $last_time = $last_dt ? date('h:i A', $last_dt) : '-';
                                ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td class="text-center"><?php echo $row['mon_present']; ?></td>
                                    <td class="text-center"><?php echo $row['mon_late']; ?></td>
                                    <td class="text-center"><?php echo $row['mon_absent']; ?></td>
                                    <td class="text-center"><?php echo $row['mon_total']; ?></td>
                                    <td class="text-center"><?php echo $row['fri_present']; ?></td>
                                    <td class="text-center"><?php echo $row['fri_absent']; ?></td>
                                    <td class="text-center"><?php echo $row['fri_total']; ?></td>
                                    <td class="text-center"><?php echo $row['mon_absent'] + $row['fri_absent']; ?></td>
                                    <td><?php echo $last_date; ?></td>
                                    <td><?php echo $last_time; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center p-3">No records found for this month.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>