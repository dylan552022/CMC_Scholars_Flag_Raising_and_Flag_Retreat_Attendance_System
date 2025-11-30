<?php
include('db_connect.php');
date_default_timezone_set('Asia/Manila');

// 🧩 Get student input (from form)
$student_input = isset($_POST['input_value']) ? trim($_POST['input_value']) : '';

if ($student_input === '') {
    header("Location: index.php?error=empty_id");
    exit;
}

// 🕒 Get current day and time
$current_day = date('l');          // Monday, Tuesday, etc.
$current_time = date('H:i:s');     // e.g. 16:25:45
$current_date = date('Y-m-d');     // e.g. 2025-10-31

// 🧠 Check if student exists
$sql = "SELECT * FROM students WHERE student_id = ? OR fullname LIKE ?";
$stmt = $conn->prepare($sql);
$like = "%$student_input%";
$stmt->bind_param("ss", $student_input, $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php?error=notfound");
    exit;
}

$student = $result->fetch_assoc();
$student_id = $student['student_id'];
$fullname = $student['fullname'];

// Get student details
$student_query = $conn->prepare("SELECT fullname, course, year FROM students WHERE student_id = ?");
$student_query->bind_param('s', $student_id);
$student_query->execute();
$student_query->bind_result($fullname, $course, $year);
$student_query->fetch();
$student_query->close();

// Insert attendance record with all details
$insert = $conn->prepare("INSERT INTO attendance (student_id, fullname, course, year, date, day, time_in, status, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insert->bind_param("sssssssss", $student_id, $fullname, $course, $year, $current_date, $current_day, $current_time, $status, $photo_path);
$insert->execute();
$insert->close();

// 💬 Message for success page
if ($status === "Present") {
    $message = "✅ Attendance recorded successfully. You are marked as Present.";
} elseif ($status === "Late") {
    $message = "⚠️ You are marked as Late.";
} else {
    $message = "❌ You have logged in after the allowed time and are marked as Absent.";
}

// 🚀 Redirect to success page
header("Location: success.php?name=" . urlencode($fullname) . "&status=" . urlencode($status) . "&msg=" . urlencode($message));
exit;
?>