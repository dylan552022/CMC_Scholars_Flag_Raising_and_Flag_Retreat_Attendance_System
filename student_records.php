<?php
include('db_connect.php');
// ensure we have a mysqli connection variable available
if (!isset($mysqli)) {
    if (isset($conn)) {
        $mysqli = $conn;
    } elseif (isset($link)) {
        $mysqli = $link;
    } elseif (isset($db)) {
        $mysqli = $db;
    } else {
        die("Database connection not found. Please make sure db_connect.php creates a mysqli connection in \$mysqli (or \$conn / \$link / \$db).");
    }
}

/*
 This project DB layout (as you reported):
 - students(student_id PK VARCHAR, fullname, ...)
 - attendance(id, student_id VARCHAR, date DATE, day VARCHAR, time_in TIME, status VARCHAR)
 The page accepts:
  - ?student_id=23-1001   OR
  - ?q=Full+Name
*/

$q = '';
$student_id = '';
if (!empty($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);
} elseif (!empty($_GET['q'])) {
    $q = trim($_GET['q']);
}

if (!$student_id && !$q) {
    echo "No student specified. <a href='index.php'>Back</a>";
    exit;
}

// Build and run query joining attendance <> students
if ($student_id) {
    $sql = "SELECT a.id, a.date, a.day, a.time_in, a.status, a.student_id, s.fullname
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.student_id
            WHERE a.student_id = ?
            ORDER BY a.date DESC, a.time_in DESC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) { die("Prepare failed: " . $mysqli->error); }
    $stmt->bind_param('s', $student_id);
} else {
    $like = "%{$q}%";
    $sql = "SELECT a.id, a.date, a.day, a.time_in, a.status, a.student_id, s.fullname
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.student_id
            WHERE s.fullname LIKE ? OR a.student_id LIKE ?
            ORDER BY a.date DESC, a.time_in DESC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) { die("Prepare failed: " . $mysqli->error); }
    $stmt->bind_param('ss', $like, $like);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

// summary counters
$summary = [
    'total' => 0,
    'Present' => 0,
    'Late' => 0,
    'Absent' => 0
];

foreach ($rows as $r) {
    $summary['total']++;
    $st = $r['status'] ?? 'Unknown';
    if (!isset($summary[$st])) $summary[$st] = 0;
    $summary[$st]++;
}

// display name resolution
$displayName = '';
if (!empty($rows) && !empty($rows[0]['fullname'])) $displayName = $rows[0]['fullname'];
elseif ($q) $displayName = $q;
elseif ($student_id) $displayName = $student_id;

// NEW: determine display student id (use provided student_id or the one returned by DB)
$displayStudentId = '';
if (!empty($student_id)) {
    $displayStudentId = $student_id;
} elseif (!empty($rows) && !empty($rows[0]['student_id'])) {
    $displayStudentId = $rows[0]['student_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Records - <?php echo htmlspecialchars($displayName); ?></title>
  <link rel="stylesheet" href="bootstrap-5.3.8-dist/css/bootstrap.min.css">
  <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
  <style>
    body{ font-family: Arial, sans-serif; background:#f4f4f4; padding:40px; }
    .card{ max-width:900px; margin:0 auto; background:#fff; padding:22px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.08); }
    table{ width:100%; border-collapse:collapse; margin-top:12px; }
    th,td{ padding:8px 10px; border:1px solid #8b8686d8; text-align:left; }
    th{ background:#fafafa; }
    .summary{ display:flex; gap:12px; flex-wrap:wrap; margin-top:12px; }
    .badge{ background:#007BFF; color:#fff; padding:8px 12px; border-radius:8px; font-weight:600; }
    .badge.warn{ background:#f0ad4e; }
    .badge.danger{ background:#d9534f; }
    .actions{ margin-top:16px; }
    /* status colors */
    .status-absent { color: #dc3545; font-weight: 700; }
    .status-present { color: #0d6efd; font-weight: 700; }
    .status-late { color: #fd7e14; font-weight: 700; }
    .actions{ margin-top:16px; }
    body{
      margin:0;
      min-height:100vh;
      background: linear-gradient(180deg,#04121a 0%, #075ad5ff 100%);
      color:#e6f7f2;
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }

    /* keep canvas behind content */
    #constellation{ position:fixed; inset:0; z-index:0; pointer-events:none; }

    /* --- polygon background canvas --- */
    #constellation {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: linear-gradient(180deg,#04121a 0%, #075ad5ff 100%);
    }
     /* Respect reduced motion */

    @media (prefers-reduced-motion: reduce){
      #constellation { display: none; }
    }

    /* small responsive tweak so content doesn't overlap browser UI */
    @media (max-width: 640px){
      body { padding: 12px; }
      .top, .card { max-width: 100%; }
    }
  </style>
</head>
<body>
     <!-- animated polygon background canvas -->
  <canvas id="constellation" aria-hidden="true"></canvas>
  <div class="card">
    <h2>Attendance Records for <?php echo htmlspecialchars($displayName ?: '—'); ?></h2>
    <!-- changed: show $displayStudentId (falls back to '—') -->
    <p>Student ID: <?php echo htmlspecialchars($displayStudentId ?: '—'); ?></p>

    <div class="summary">
      <div class="badge">Total: <?php echo $summary['total']; ?></div>
      <div class="badge">Present: <?php echo ($summary['Present'] ?? 0); ?></div>
      <div class="badge warn">Late: <?php echo ($summary['Late'] ?? 0); ?></div>
      <div class="badge danger">Absent: <?php echo ($summary['Absent'] ?? 0); ?></div>
    </div>

    <h3 style="margin-top:18px;">Recent Records</h3>
    <table>
      <tr><th>#</th><th>Date</th><th>Day</th><th>Time In</th><th>Status</th></tr>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5">No records found.</td></tr>
      <?php else: ?>
        <?php $i=0; foreach ($rows as $r): $i++; ?>
          <tr>
            <td><?php echo $i; ?></td>
            <td><?php echo htmlspecialchars($r['date']); ?></td>
            <td><?php echo htmlspecialchars($r['day']); ?></td>
            <td><?php echo htmlspecialchars($r['time_in']); ?></td>
            <?php
              $st = $r['status'] ?? '';
              $st_class = 'status-' . strtolower($st);
              $st_class = preg_replace('/[^a-z0-9\-_]/', '-', $st_class);
            ?>
            <td class="<?php echo htmlspecialchars($st_class); ?>">
              <?php echo htmlspecialchars($st); ?>
            </td>
            
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>

    <div class="actions">
      <a href="index.php" style="display:inline-block;margin-top:12px;padding:8px 12px;background:#6c757d;color:#fff;border-radius:6px;text-decoration:none;margin-right:8px;">Back</a>
      <form action="student_logout.php" method="POST" style="display:inline-block; margin:0;">
        <button type="submit" style="margin-top:12px;padding:8px 12px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;">Logout</button>
      </form>
    </div>
  </div>
  <!-- polygon background script -->
  <script>
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

    const mouse = { x: -9999, y: -9999, down: false, active: false };

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

      // subtle background vignette
      const g = ctx.createLinearGradient(0,0,0,H);
      g.addColorStop(0, 'rgba(6,18,24,0.35)');
      g.addColorStop(1, 'rgba(3,9,12,0.6)');
      ctx.fillStyle = g;
      ctx.fillRect(0,0,W,H);

      // draw connections
      for(let i=0;i<points.length;i++){
        const a = points[i];
        // damping
        a.vx *= 0.996;
        a.vy *= 0.996;

        // mouse interactivity: attract when moving, repel on click
        const dxm = mouse.x - a.x, dym = mouse.y - a.y;
        const dm = Math.sqrt(dxm*dxm + dym*dym);
        if (mouse.down) {
          if (dm < 260) {
            const f = (260 - dm)/260;
            a.vx += -dxm * 0.002 * f;
            a.vy += -dym * 0.002 * f;
          }
        } else if (mouse.active) {
          if (dm < 140) {
            const f = (140 - dm)/140;
            a.vx += dxm * 0.0009 * f;
            a.vy += dym * 0.0009 * f;
          }
        }

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

      // draw points
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

    function burst(cx,cy){
      for(const p of points){
        const dx = p.x - cx, dy = p.y - cy;
        const d = Math.sqrt(dx*dx + dy*dy) || 1;
        if (d < 260){
          const f = (260 - d)/260;
          p.vx += (dx/d) * (0.6 + Math.random()*1.2) * f;
          p.vy += (dy/d) * (0.6 + Math.random()*1.2) * f;
        }
      }
    }

    window.addEventListener('mousemove', (e)=>{
      mouse.x = e.clientX;
      mouse.y = e.clientY;
      mouse.active = true;
    });
    window.addEventListener('mouseleave', ()=>{
      mouse.x = -9999; mouse.y = -9999; mouse.active = false;
    });
    window.addEventListener('mousedown', (e)=>{
      mouse.down = true;
      burst(e.clientX, e.clientY);
    });
    window.addEventListener('mouseup', ()=>{ mouse.down = false; });
    window.addEventListener('resize', resize);

    // init
    resize();
    requestAnimationFrame(step);

  })();
  </script>
  <script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>