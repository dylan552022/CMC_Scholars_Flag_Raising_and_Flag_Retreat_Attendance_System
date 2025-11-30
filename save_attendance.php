<?php
include('db_connect.php');
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

// Ensure mysqli connection
if (!isset($mysqli)) {
    if (isset($conn)) {
        $mysqli = $conn;
    } else {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['student_id']) || !isset($data['photo'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$student_id = $data['student_id'];
$fullname = $data['fullname'];
$status = $data['status'];
$current_date = $data['current_date'];
$current_day = $data['current_day'];
$current_time = $data['current_time'];
$photo = $data['photo'];

// Duplicate-name login check: if another account with the same fullname has already logged in today, block this login
try {
    if (!empty($fullname)) {
        // Find other student_ids that have the same fullname (case/space insensitive) and are NOT the current student
        $stmt = $mysqli->prepare("SELECT student_id FROM students WHERE LOWER(TRIM(fullname)) = LOWER(TRIM(?)) AND student_id != ?");
        if ($stmt !== false) {
            $stmt->bind_param('ss', $fullname, $student_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $sameIds = [];
            while ($row = $res->fetch_assoc()) {
                $sameIds[] = $row['student_id'];
            }
            $stmt->close();

            // Check if any of those student_ids already has attendance for today
            foreach ($sameIds as $sid) {
                $checkDup = $mysqli->prepare("SELECT 1 FROM attendance WHERE student_id = ? AND date = ? LIMIT 1");
                if ($checkDup !== false) {
                    $checkDup->bind_param('ss', $sid, $current_date);
                    $checkDup->execute();
                    $dupRes = $checkDup->get_result();
                    if ($dupRes && $dupRes->num_rows > 0) {
                        // Duplicate found for another account with same fullname
                        echo json_encode(['success' => false, 'error' => 'You have already Logged in']);
                        exit;
                    }
                    $checkDup->close();
                }
            }
        }
    }
} catch (Exception $e) {
    // If this check fails for any reason, do not block the login flow — continue silently.
}

// --- NEW: Check existing attendance BEFORE processing/saving the photo ---
// Check if attendance already recorded for today for this student
$check = $mysqli->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
if ($check === false) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
    exit;
}
$check->bind_param('ss', $student_id, $current_date);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Block duplicate submission for same student/date
    echo json_encode([
        'success' => false,
        'error' => 'You have already logged in',
        'message' => 'Attendance already recorded for today.'
    ]);
    $check->close();
    exit;
}
$check->close();

// Create attendance directory if it doesn't exist
$attendanceDir = 'attendance_photos/' . date('Y-m-d');
if (!is_dir($attendanceDir)) {
    mkdir($attendanceDir, 0755, true);
}

// Process and save photo
try {
    // Decode base64 image
    if (preg_match('/^data:image\/(\w+);base64,/', $photo, $type)) {
        $photo = substr($photo, strpos($photo, ',') + 1);
        $type = strtolower($type[1]);
        
        if (!in_array($type, array('jpeg', 'jpg', 'png', 'gif'))) {
            throw new Exception('Invalid image type');
        }
        
        $photo = base64_decode($photo);
        
        if ($photo === false) {
            throw new Exception('Failed to decode image');
        }
    } else {
        throw new Exception('Invalid image format');
    }

    // Generate unique filename
    $filename = $student_id . '_' . time() . '.jpg';
    $filepath = $attendanceDir . '/' . $filename;
    
    // Save image
    if (!file_put_contents($filepath, $photo)) {
        throw new Exception('Failed to save image');
    }

    // Insert new record - approval_status is hardcoded as 'pending'
    $approval_status = 'pending';
    $insert = $mysqli->prepare("INSERT INTO attendance (student_id, date, day, time_in, status, photo_path, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($insert === false) {
        throw new Exception('Database error: ' . $mysqli->error);
    }
    $insert->bind_param('sssssss', $student_id, $current_date, $current_day, $current_time, $status, $filepath, $approval_status);
    $insert->execute();
    $insert->close();

    $message = "📸 Photo submitted for verification. Admin will approve your attendance shortly.";

    echo json_encode(array(
        'success' => true,
        'fullname' => $fullname,
        'status' => $status,
        'message' => $message
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>
