<?php
include('db_connect.php');
date_default_timezone_set('Asia/Manila');

$input = mysqli_real_escape_string($conn, $_POST['input_value']);
$current_day = date('l');
$current_time = date('H:i:s');
$current_date = date('Y-m-d');

// Check if student exists
$sql = "SELECT * FROM students WHERE student_id='$input' OR fullname LIKE '%$input%'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $student_id = $row['student_id'];

    // Determine status based on day and time
    $status = "Absent"; // default

    if ($current_day == "Monday") {
        if ($current_time <= "07:30:59") {
            $status = "Present";
        } elseif ($current_time <= "07:46:59") {
            $status = "Late";
        } else {
            $status = "Absent"; // After 7:46:59 → Absent
        }
    } elseif ($current_day == "Friday") {
        if ($current_time <= "16:30:59") {
            $status = "Present";
        } elseif ($current_time <= "16:46:59") {
            $status = "Late";
        } else {
            $status = "Absent"; // After 4:46:59 → Absent
        }
    } else {
        echo "<h3>Today is not a flag ceremony or retreat day.</h3>";
        exit();
    }

    // Check if attendance already recorded for today
    $check = mysqli_query($conn, "SELECT * FROM attendance WHERE student_id='$student_id' AND date='$current_date'");
    if (mysqli_num_rows($check) == 0) {
        // Insert new attendance record
        mysqli_query($conn, "INSERT INTO attendance (student_id, date, day, time_in, status) 
                             VALUES ('$student_id', '$current_date', '$current_day', '$current_time', '$status')");
    }

    // Define message based on status
    if ($status == "Absent") {
        $message = "❌ You have logged in after the allowed time and were marked as Absent.";
    } elseif ($status == "Late") {
        $message = "⚠️ You were marked as Late.";
    } else {
        $message = "✅ Attendance Recorded Successfully.";
    }

    // Redirect with message
    header("Location: success.php?name=" . urlencode($row['fullname']) . "&status=" . urlencode($status) . "&msg=" . urlencode($message));
    exit();

} else {
    echo "<h3>Student not found. Please check your ID or name.</h3>";
}
?>