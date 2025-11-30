<?php
include('db_connect.php');
date_default_timezone_set('Asia/Manila');
session_start();

// ensure $mysqli exists (keep original variable if defined)
if (!isset($mysqli) && isset($conn) && $conn instanceof mysqli) $mysqli = $conn;

// Simple auth check - if not logged in as admin via session, check basic auth
if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_id'])) {
    // Try to get admin info from session or redirect
    if (empty($_SESSION['admin_id'])) {
        // For now, allow access - in production, enforce login
        // Uncomment the line below to enforce admin login
        // header("Location: index.php");
        // exit;
    }
}

$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($attendance_id && ($action === 'approve' || $action === 'reject')) {
        $approval_status = ($action === 'approve') ? 'approved' : 'rejected';
        $now = date('Y-m-d H:i:s');
        
        $update = $mysqli->prepare("UPDATE attendance SET approval_status = ?, approved_by = ?, approved_at = ? WHERE id = ?");
        if ($update) {
            $update->bind_param('sisi', $approval_status, $admin_id, $now, $attendance_id);
            $update->execute();
            $update->close();
        }
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Improved query: resolve fullname from students table when possible
$sql = "
    SELECT 
      a.id,
      a.student_id,
      COALESCE(s.fullname, a.fullname) AS fullname,
      COALESCE(s.course, a.course) AS course,
      COALESCE(s.`year`, a.`year`) AS `year`,
      a.date,
      a.time_in,
      a.status,
      a.photo_path,
      a.approval_status,
      a.created_at
    FROM attendance a
    LEFT JOIN students s
      ON (
         s.student_id = a.student_id
         OR LOWER(TRIM(s.fullname)) = LOWER(TRIM(a.student_id))
         OR LOWER(TRIM(s.fullname)) = LOWER(TRIM(a.fullname))
      )
    WHERE a.approval_status = ? AND a.date = ?
    ORDER BY a.created_at DESC
";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $mysqli->error);
}

$stmt->bind_param('ss', $filter_status, $filter_date);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get summary counts
$summary = [];
$statuses = array('pending', 'approved', 'rejected');
foreach ($statuses as $s) {
    $count_sql = "SELECT COUNT(*) as cnt FROM attendance WHERE approval_status = ? AND date = ?";
    $count_stmt = $mysqli->prepare($count_sql);
    $count_stmt->bind_param('ss', $s, $filter_date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $summary[$s] = $count_row['cnt'];
    $count_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Approval - Admin Dashboard</title>
  <link rel="stylesheet" href="bootstrap-5.3.8-dist/css/bootstrap.min.css">
  <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      background: linear-gradient(180deg, #04121a 0%, #075ad5ff 100%);
      color: #e6f7f2;
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      padding: 20px;
    }

    #constellation {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: linear-gradient(180deg, #04121a 0%, #075ad5ff 100%);
    }

    .container {
      position: relative;
      z-index: 10;
      max-width: 1200px;
      margin: 0 auto;
    }

    .header {
      background: #2e394dff;
      border: 1px solid rgba(255, 255, 255, 0.04);
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 20px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    .header h1 {
      margin: 0;
      font-size: 28px;
      margin-bottom: 15px;
      color: whitesmoke;
    }

    .filter-section {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 20px;
    }

    .filter-section input,
    .filter-section select {
      background: rgba(240, 241, 243, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #050a21;
      padding: 8px 12px;
      border-radius: 6px;
    }

    .filter-section button {
      background: linear-gradient(135deg, #00d4ff, #0099ff);
      color: #fff;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }

    .filter-section button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 212, 255, 0.4);
    }

    .summary-badges {
      display: flex;
      gap: 15px;
      margin-top: 15px;
      flex-wrap: wrap;
    }

    .badge-item {
      background: rgba(0, 0, 0, 0.2);
      padding: 12px 16px;
      border-radius: 8px;
      font-weight: 600;
    }

    .badge-item.pending {
      border-left: 4px solid #fd7e14;
    }

    .badge-item.approved {
      border-left: 4px solid #28a745;
    }

    .badge-item.rejected {
      border-left: 4px solid #dc3545;
    }

    .badge-item .count {
      font-size: 24px;
      font-weight: 700;
    }

    .records-container {
      background: #2e394dff;
      border: 1px solid rgba(255, 255, 255, 0.04);
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    .record-card {
      background: rgba(0, 0, 0, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      display: grid;
      grid-template-columns: 150px 1fr auto;
      gap: 20px;
      align-items: start;
    }

    .photo-preview {
      width: 150px;
      height: 150px;
      border-radius: 8px;
      overflow: hidden;
      background: #000;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .photo-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .record-info h3 {
      margin: 0 0 10px 0;
      font-size: 18px;
      color: #6ec1ff;
    }

    .info-row {
      display: flex;
      gap: 20px;
      margin-bottom: 8px;
      flex-wrap: wrap;
      font-size: 14px;
    }

    .info-row strong {
      color: #00d4ff;
      min-width: 80px;
    }

    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      margin-right: 8px;
    }

    .status-present {
      background: #0d6efd;
      color: #fff;
    }

    .status-late {
      background: #fd7e14;
      color: #fff;
    }

    .status-absent {
      background: #dc3545;
      color: #fff;
    }

    .approval-pending {
      background: rgba(253, 126, 20, 0.2);
      border: 1px solid rgba(253, 126, 20, 0.4);
    }

    .approval-pending::before {
      content: '⏳ PENDING APPROVAL';
      color: #fd7e14;
      font-weight: 700;
      font-size: 12px;
    }

    .actions {
      display: flex;
      gap: 8px;
      flex-direction: column;
      min-width: 120px;
    }

    .btn-action {
      padding: 8px 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-approve {
      background: #28a745;
      color: #fff;
    }

    .btn-approve:hover {
      background: #218838;
      transform: translateY(-2px);
    }

    .btn-reject {
      background: #dc3545;
      color: #fff;
    }

    .btn-reject:hover {
      background: #c82333;
      transform: translateY(-2px);
    }

    .btn-view {
      background: #0d6efd;
      color: #fff;
    }

    .btn-view:hover {
      background: #0b5ed7;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #999;
    }

    .empty-state-icon {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .back-link {
      color: #6ec1ff;
      text-decoration: none;
      margin-bottom: 20px;
      display: inline-block;
    }

    .back-link:hover {
      color: #00d4ff;
    }

    @media (max-width: 768px) {
      .record-card {
        grid-template-columns: 1fr;
      }

      .photo-preview {
        width: 100%;
        height: auto;
        aspect-ratio: 4/3;
      }

      .actions {
        flex-direction: row;
        min-width: auto;
      }

      .filter-section {
        flex-direction: column;
        align-items: stretch;
      }

      .filter-section input,
      .filter-section select,
      .filter-section button {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <canvas id="constellation" aria-hidden="true"></canvas>

  <div class="container">
    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>

    <div class="header">
      <h1>📸 Attendance Photo Approval</h1>
      <p style="margin: 0; color: #aaa; font-size: 14px;">Review and approve student attendance photos</p>

      <div class="filter-section">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; width: 100%;">
          <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <label for="date" style="margin: 0; white-space: nowrap;">Date:</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" />
          </div>

          <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <label for="status" style="margin: 0; white-space: nowrap;">Status:</label>
            <select id="status" name="status">
              <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>

          <button type="submit">🔍 Filter</button>
        </form>
      </div>

      <div class="summary-badges">
        <div class="badge-item pending">
          <div class="count"><?php echo $summary['pending']; ?></div>
          <div style="font-size: 12px; color: #fd7e14;">Pending</div>
        </div>
        <div class="badge-item approved">
          <div class="count"><?php echo $summary['approved']; ?></div>
          <div style="font-size: 12px; color: #28a745;">Approved</div>
        </div>
        <div class="badge-item rejected">
          <div class="count"><?php echo $summary['rejected']; ?></div>
          <div style="font-size: 12px; color: #dc3545;">Rejected</div>
        </div>
      </div>
    </div>

    <div class="records-container">
      <?php if (empty($records)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <h3>No records found</h3>
          <p>There are no <?php echo htmlspecialchars($filter_status); ?> attendance records for <?php echo htmlspecialchars($filter_date); ?></p>
        </div>
      <?php else: ?>
        <?php foreach ($records as $record): ?>
          <div class="record-card <?php echo $record['approval_status'] === 'pending' ? 'approval-pending' : ''; ?>">
            <div class="photo-preview">
              <?php if ($record['photo_path'] && file_exists($record['photo_path'])): ?>
                <img src="<?php echo htmlspecialchars($record['photo_path']); ?>" alt="Student Photo">
              <?php else: ?>
                <div style="text-align: center; color: #666; font-size: 12px;">
                  📷<br>No Photo
                </div>
              <?php endif; ?>
            </div>

            <div class="record-info">
              <h3><strong><?php echo htmlspecialchars($record['fullname'] ??'Details'); ?></strong></h3>
              
              <div class="info-row">
                <strong>ID:</strong>
                <span><?php echo htmlspecialchars($record['student_id'] ?? 'N/A'); ?></span>
              </div>

              <!-- <div class="info-row">
                <strong>Course:</strong>
                <span><?php echo htmlspecialchars($record['course'] ?? 'N/A'); ?></span>
                <strong style="margin-left: 20px;">Year:</strong>
                <span><?php echo htmlspecialchars($record['year'] ?? 'N/A'); ?></span>
              </div> -->

              <div class="info-row">
                <strong>Date/Time:</strong>
                <span><?php echo htmlspecialchars($record['date'] . ' ' . $record['time_in']); ?></span>
              </div>

              <div class="info-row">
                <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                  <?php echo htmlspecialchars($record['status']); ?>
                </span>
                <span class="status-badge" style="background: rgba(0, 212, 255, 0.2); color: #00d4ff;">
                  <?php echo ucfirst($record['approval_status']); ?>
                </span>
              </div>
            </div>

            <?php if ($record['approval_status'] === 'pending'): ?>
              <div class="actions">
                <form method="POST" style="display: contents;">
                  <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                  <button type="submit" name="action" value="approve" class="btn-action btn-approve">✅ Approve</button>
                  <button type="submit" name="action" value="reject" class="btn-action btn-reject">❌ Reject</button>
                </form>
              </div>
            <?php else: ?>
              <div class="actions">
                <span style="padding: 8px 12px; background: rgba(0, 0, 0, 0.3); border-radius: 6px; font-size: 12px; text-align: center;">
                  <?php echo ucfirst($record['approval_status']); ?> ✓
                </span>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Constellation animation
    (function(){
      const canvas = document.getElementById('constellation');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      let DPR = Math.max(1, window.devicePixelRatio || 1);
      let W = 0, H = 0;
      let points = [];
      const MAX_CONN = 140;
      const BASE_SPEED = 0.35;
      const DENSITY_FACTOR = 110000;

      function resize(){
        DPR = Math.max(1, window.devicePixelRatio || 1);
        W = Math.max(300, window.innerWidth);
        H = Math.max(300, window.innerHeight);
        canvas.width = Math.round(W * DPR);
        canvas.height = Math.round(H * DPR);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(DPR,0,0,DPR,0,0);
        initPoints();
      }

      function initPoints(){
        points = [];
        const count = Math.max(24, Math.round((W * H) / DENSITY_FACTOR));
        for(let i=0;i<count;i++){
          points.push({
            x: Math.random()*W,
            y: Math.random()*H,
            vx: (Math.random()-0.5)*BASE_SPEED,
            vy: (Math.random()-0.5)*BASE_SPEED,
            r: 1.2 + Math.random()*2.4
          });
        }
      }

      function step(){
        ctx.clearRect(0,0,W,H);
        const g = ctx.createLinearGradient(0,0,0,H);
        g.addColorStop(0, 'rgba(6,18,24,0.35)');
        g.addColorStop(1, 'rgba(3,9,12,0.6)');
        ctx.fillStyle = g;
        ctx.fillRect(0,0,W,H);

        for(let i=0;i<points.length;i++){
          const a = points[i];
          a.vx *= 0.996;
          a.vy *= 0.996;
          a.x += a.vx; a.y += a.vy;
          if (a.x < -20) a.x = W + 20;
          if (a.x > W + 20) a.x = -20;
          if (a.y < -20) a.y = H + 20;
          if (a.y > H + 20) a.y = -20;

          for(let j=i+1;j<points.length;j++){
            const b = points[j];
            const dx = a.x - b.x, dy = a.y - b.y;
            const d = Math.sqrt(dx*dx + dy*dy);
            if (d < MAX_CONN) {
              const alpha = 0.85 * (1 - d / MAX_CONN);
              ctx.beginPath();
              ctx.strokeStyle = `rgba(0,200,230,${alpha*0.85})`;
              ctx.lineWidth = 1;
              ctx.moveTo(a.x,a.y);
              ctx.lineTo(b.x,b.y);
              ctx.stroke();
            }
          }
        }

        for(const p of points){
          ctx.beginPath();
          ctx.fillStyle = 'rgba(0,200,220,0.95)';
          ctx.shadowColor = 'rgba(0,220,240,0.18)';
          ctx.shadowBlur = 8;
          ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
          ctx.fill();
          ctx.shadowBlur = 0;
        }

        requestAnimationFrame(step);
      }

      window.addEventListener('resize', resize);
      resize();
      requestAnimationFrame(step);
    })();
  </script>
</body>
</html>
