<?php
include('db_connect.php');

date_default_timezone_set('Asia/Manila');

// ensure we have a mysqli connection available (work with $mysqli or $conn)
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
elseif (isset($link) && $link instanceof mysqli) $db = $link;
if (!$db) {
    die("Database connection not found.");
}

// Get parameters from attendance_process
$input = isset($_GET['input_value']) ? trim($_GET['input_value']) : '';
$provided_student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$provided_fullname = isset($_GET['fullname']) ? trim($_GET['fullname']) : '';
$current_day = isset($_GET['current_day']) ? $_GET['current_day'] : date('l');
$current_time = isset($_GET['current_time']) ? $_GET['current_time'] : date('H:i:s');
$current_date = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : 'Absent';

// helper: try to find a student by id OR fullname (case-insensitive)
function findScholarByIdentifier($db, $val) {
    $val = trim($val);
    if ($val === '') return null;

    // Try student_id first
    $sql = "SELECT student_id, fullname, course, `year` FROM students WHERE student_id = ? LIMIT 1";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('s', $val);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($row) return $row;
    }

    // Try case-insensitive fullname
    $sql = "SELECT student_id, fullname, course, `year` FROM students WHERE LOWER(TRIM(fullname)) = LOWER(TRIM(?)) LIMIT 1";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('s', $val);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($row) return $row;
    }

    return null;
}

// Add a shared, identical status calculation used for display
function determine_status($day, $time) {
    $time = trim($time ?? '');
    if ($time === '') return 'Absent';
    $t = date('H:i:s', strtotime($time));

    if (strcasecmp($day, 'Monday') === 0) {
        if ($t <= '07:30:00') return 'Present';
        if ($t > '07:30:00' && $t <= '08:00:00') return 'Late';
        return 'Absent';
    }

    if (strcasecmp($day, 'Friday') === 0) {
        if ($t >= '16:00:00' && $t <= '17:00:00') return 'Present';
        return 'Absent';
    }

    return 'Absent';
}

// Resolve input to a student record (prefer provided student_id, then input as id, then input as fullname)
$student = null;
if (!empty($provided_student_id)) {
    $student = findScholarByIdentifier($db, $provided_student_id);
}

if (!$student && !empty($input)) {
    $student = findScholarByIdentifier($db, $input);
}

// If found, use canonical values; otherwise fall back to provided values
if ($student) {
    $student_id = $student['student_id'];
    $fullname = $student['fullname'];
    $course = $student['course'] ?? '';
    $year = $student['year'] ?? '';
} else {
    $student_id = $provided_student_id !== '' ? $provided_student_id : $input;
    $fullname = $provided_fullname !== '' ? $provided_fullname : $input;
    $course = '';
    $year = '';
}

// compute server-side status (ignore any client-supplied status/query param)
$status = determine_status($current_day, $current_time);

// basic validation: require at least an input or student id to continue
if (empty($input) && empty($student_id)) {
    die("Invalid request. <a href='index.php'>Back to Login</a>");
}

// (optional) fetch course/year if still empty and student_id exists
if (($course === '' || $year === '') && !empty($student_id)) {
    if ($stmt = $db->prepare("SELECT course, `year` FROM students WHERE student_id = ? LIMIT 1")) {
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $stmt->bind_result($c, $y);
        if ($stmt->fetch()) {
            if ($course === '') $course = $c;
            if ($year === '') $year = $y;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Capture Your Photo - Attendance Verification</title>
  <link rel="stylesheet" href="bootstrap-5.3.8-dist/css/bootstrap.min.css">
  <!-- Local FontAwesome (fallback to CDN kept for online devices) -->
  <link rel="stylesheet" href="fontawesome/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="" crossorigin="anonymous" media="print" onload="this.media='all'">
  <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      background: linear-gradient(180deg, #04121a 0%, #075ad5ff 100%);
      color: #e6f7f2;
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #constellation {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: linear-gradient(180deg, #04121a 0%, #075ad5ff 100%);
    }

    .card {
      position: relative;
      z-index: 10;
      max-width: 600px;
      margin: 0 auto;
      background: #2e394dff;
      border: 1px solid rgba(255, 255, 255, 0.04);
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    .card h1 {
      font-size: 24px;
      margin-bottom: 10px;
      color: whitesmoke;
      text-align: center;
    }

    .card p {
      color: #ccc;
      text-align: center;
      font-size: 14px;
    }

    .info-section {
      background: rgba(0, 0, 0, 0.2);
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      text-align: center;
    }

    .info-section div {
      color: white;
      font-weight: bold;
    }

    .info-section strong {
      color: #6ec1ff;
    }

    .camera-container {
      position: relative;
      margin: 20px 0;
      border-radius: 8px;
      overflow: hidden;
      background: #000;
    }

    #video {
      width: 100%;
      height: auto;
      display: block;
    }

    #canvas {
      display: none;
    }

    .preview-container {
      position: relative;
      margin: 20px 0;
      border-radius: 8px;
      overflow: hidden;
      display: none;
      background: #000;
    }

    #photoPreview {
      width: 100%;
      height: auto;
      display: block;
    }

    .button-group {
      display: flex;
      gap: 10px;
      margin: 20px 0;
      flex-wrap: wrap;
      justify-content: center;
    }

    .btn-action {
      flex: 1;
      min-width: 120px;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .btn-capture {
      background: linear-gradient(135deg, #00d4ff, #0099ff);
      color: #fff;
    }

    .btn-capture:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 212, 255, 0.4);
    }

    .btn-retake {
      background: #fd7e14;
      color: #fff;
      display: none;
    }

    .btn-retake:hover {
      background: #e06c0a;
    }

    .btn-submit {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: #fff;
      display: none;
      flex-basis: 100%;
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
    }

    .btn-cancel {
      background: #6c757d;
      color: #fff;
    }

    .btn-cancel:hover {
      background: #5a6268;
    }

    .status-badge {
      display: inline-block;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      margin-top: 8px;
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

    .loader {
      display: none;
      text-align: center;
      margin: 20px 0;
    }

    .spinner {
      display: inline-block;
      width: 30px;
      height: 30px;
      border: 4px solid rgba(255, 255, 255, 0.3);
      border-top: 4px solid #fff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .error-message {
      background: #dc3545;
      color: #fff;
      padding: 12px;
      border-radius: 6px;
      margin: 10px 0;
      display: none;
      text-align: center;
    }

    .success-message {
      background: #28a745;
      color: #fff;
      padding: 12px;
      border-radius: 6px;
      margin: 10px 0;
      display: none;
      text-align: center;
    }
  </style>
</head>
<body>
  <canvas id="constellation" aria-hidden="true"></canvas>
  <div class="card">
    <h1>📸 Capture Your Photo</h1>
    <p>Take a selfie to verify your attendance</p>

    <div class="info-section">
      <div>Fullname: <strong><?php echo htmlspecialchars($fullname); ?></strong></div>
      <div>Date: <strong><?php echo htmlspecialchars($current_date); ?></strong></div>
      <span class="status-badge status-<?php echo strtolower($status); ?>">
        <?php echo htmlspecialchars($status); ?>
      </span>
    </div>

    <div class="error-message" id="errorMessage"></div>
    <div class="success-message" id="successMessage"></div>

    <div class="camera-container">
      <video id="video" autoplay playsinline muted></video>
    </div>

    <div class="preview-container" id="previewContainer">
      <img id="photoPreview" src="" alt="Photo Preview">
    </div>

    <!-- Hidden file input fallback for mobile (opens native camera on many devices) -->
    <input type="file" id="fileInput" accept="image/*" capture="environment" style="display:none">

    <div class="button-group">
      <button class="btn-action btn-capture" id="captureBtn">📷 Take Photo</button>
      <button class="btn-action btn-retake" id="retakeBtn">🔄 Retake</button>
      <button class="btn-action btn-submit" id="submitBtn">✅ Submit Attendance</button>
      <button class="btn-action btn-cancel" onclick="window.history.back();">❌ Cancel</button>
    </div>

    <div class="loader" id="loader">
      <div class="spinner"></div>
      <p style="margin-top: 10px; color: #ccc;">Processing your photo...</p>
    </div>

    <canvas id="canvas" style="display:none"></canvas>
    <input type="hidden" id="photoData" value="">
  </div>

  <script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const submitBtn = document.getElementById('submitBtn');
    const previewContainer = document.getElementById('previewContainer');
    const photoPreview = document.getElementById('photoPreview');
    const photoData = document.getElementById('photoData');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    const loader = document.getElementById('loader');
    const fileInput = document.getElementById('fileInput');

    let photoCaptured = false;
    let usingStream = false;
    let streamRef = null;

    // Start camera with simpler constraints and wait for metadata
    async function startCamera() {
      // Many mobile browsers block getUserMedia on insecure origins (HTTP).
      // If permission or secure-context fails, fallback to file input below.
      try {
        const constraints = {
          video: {
            facingMode: { ideal: 'environment' }
          },
          audio: false
        };
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        streamRef = stream;
        video.srcObject = stream;
        usingStream = true;
        // wait for metadata to get natural size
        await new Promise((resolve) => {
          if (video.readyState >= 2) return resolve();
          video.onloadedmetadata = () => resolve();
        });
        // ensure muted & playsinline for iOS / mobile
        video.muted = true;
        video.play().catch(()=>{});
        hideError();
      } catch (err) {
        console.warn('getUserMedia failed:', err);
        // Keep UI clean: do not show the red error box when camera access fails.
        hideError();
        usingStream = false;
        // Try to open the native camera/file picker automatically on touch devices.
        // Some browsers will block this if not initiated by a user gesture — it's safe to ignore failures.
        try {
          if ('ontouchstart' in window) {
            fileInput.click();
          }
        } catch (e) {
          console.warn('file input open blocked', e);
        }
      }
    }

    // Utility: stop camera if active
    function stopCamera() {
      if (streamRef) {
        streamRef.getTracks().forEach(t => t.stop());
        streamRef = null;
      }
      usingStream = false;
    }

    // Draw current video frame to canvas safely (handle 0-dimensions)
    function captureFromVideo() {
      const vW = video.videoWidth || video.clientWidth || 1280;
      const vH = video.videoHeight || video.clientHeight || Math.round(vW * 0.75);
      canvas.width = vW;
      canvas.height = vH;
      try {
        ctx.drawImage(video, 0, 0, vW, vH);
      } catch (e) {
        console.error('drawImage error', e);
        showError('Unable to capture from camera. Please use the file upload fallback.');
        return null;
      }
      return canvas.toDataURL('image/jpeg', 0.9);
    }

    // Capture photo (either from live stream or open file picker)
    captureBtn.addEventListener('click', () => {
      hideError();
      if (usingStream && streamRef) {
        // ensure we have loaded metadata
        if ((video.videoWidth || video.clientWidth) === 0) {
          // wait a little for dimensions
          setTimeout(() => {
            const dataUrl = captureFromVideo();
            if (dataUrl) setCapturedPhoto(dataUrl);
          }, 250);
        } else {
          const dataUrl = captureFromVideo();
          if (dataUrl) setCapturedPhoto(dataUrl);
        }
      } else {
        // fallback: open native camera / gallery via file input
        fileInput.click();
      }
    });

    // File input handler - mobile native camera fallback
    fileInput.addEventListener('change', (e) => {
      hideError();
      const file = e.target.files && e.target.files[0];
      if (!file) {
        showError('No file selected.');
        return;
      }
      if (!file.type.startsWith('image/')) {
        showError('Invalid image format. Please select an image.');
        return;
      }

      const reader = new FileReader();
      reader.onload = function(evt) {
        const dataUrl = evt.target.result;
        // create image to normalize orientation/size
        const img = new Image();
        img.onload = function() {
          // draw into canvas to normalize size
          const maxW = 1280;
          const ratio = Math.min(1, maxW / img.width);
          canvas.width = Math.round(img.width * ratio);
          canvas.height = Math.round(img.height * ratio);
          ctx.clearRect(0,0,canvas.width,canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          const normalized = canvas.toDataURL('image/jpeg', 0.9);
          setCapturedPhoto(normalized);
        };
        img.onerror = function() {
          showError('Invalid image file. Please try again.');
        };
        img.src = dataUrl;
      };
      reader.onerror = function() {
        showError('Failed to read selected file.');
      };
      reader.readAsDataURL(file);
    });

    function setCapturedPhoto(dataUrl) {
      // validate dataUrl format
      if (!/^data:image\/(jpeg|jpg|png|webp);base64,/.test(dataUrl)) {
        // allow jpeg fallback if canvas produces other mime
        if (!dataUrl.startsWith('data:image/')) {
          showError('Invalid image format');
          return;
        }
      }
      photoData.value = dataUrl;
      photoPreview.src = dataUrl;
      previewContainer.style.display = 'block';
      video.style.display = 'none';
      captureBtn.style.display = 'none';
      retakeBtn.style.display = 'block';
      submitBtn.style.display = 'block';
      photoCaptured = true;
      stopCamera(); // stop live stream to free camera
    }

    // Retake photo - reset UI and attempt camera again
    retakeBtn.addEventListener('click', async () => {
      photoData.value = '';
      photoPreview.src = '';
      previewContainer.style.display = 'none';
      captureBtn.style.display = 'block';
      retakeBtn.style.display = 'none';
      submitBtn.style.display = 'none';
      photoCaptured = false;
      fileInput.value = ''; // reset file input
      // try to restart camera (if available)
      await startCamera();
      if (usingStream) {
        video.style.display = 'block';
      } else {
        // show video area hidden but user can use Capture to open file picker
        video.style.display = 'none';
      }
      hideError();
    });

    // Submit photo
    submitBtn.addEventListener('click', async () => {
      if (!photoCaptured || !photoData.value) {
        showError('No photo captured. Please take a photo first.');
        return;
      }

      loader.style.display = 'block';
      submitBtn.disabled = true;

      try {
        const response = await fetch('save_attendance.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            student_id: '<?php echo htmlspecialchars($student_id); ?>',
            fullname: '<?php echo htmlspecialchars($fullname); ?>',
            status: '<?php echo htmlspecialchars($status); ?>',
            current_date: '<?php echo htmlspecialchars($current_date); ?>',
            current_day: '<?php echo htmlspecialchars($current_day); ?>',
            current_time: '<?php echo htmlspecialchars($current_time); ?>',
            photo: photoData.value
          })
        });

        const result = await response.json();

        if (result.success) {
          successMessage.textContent = '✅ Photo submitted successfully! Redirecting...';
          successMessage.style.display = 'block';

          setTimeout(() => {
            window.location.href = 'success.php?name=' + encodeURIComponent(result.fullname) +
                                  '&status=' + encodeURIComponent(result.status) +
                                  '&msg=' + encodeURIComponent(result.message);
          }, 1500);
        } else {
          showError(result.error || 'Failed to save attendance');
          loader.style.display = 'none';
          submitBtn.disabled = false;
        }
      } catch (error) {
        showError('Error uploading photo: ' + (error.message || error));
        console.error('Upload error:', error);
        loader.style.display = 'none';
        submitBtn.disabled = false;
      }
    });

    function showError(message) {
      errorMessage.textContent = '⚠️ ' + message;
      errorMessage.style.display = 'block';
    }
    function hideError() {
      errorMessage.textContent = '';
      errorMessage.style.display = 'none';
    }

    // Initialize
    (async function init(){
      // Try camera first, but handle when getUserMedia is disallowed (insecure origin / permissions).
      await startCamera();
      if (!usingStream) {
        // Hide video area for clarity on devices where stream isn't available
        video.style.display = 'none';
      } else {
        video.style.display = 'block';
      }
    })();

    // Polygon background animation (constellation)
    (function(){
      const canvas_bg = document.getElementById('constellation');
      if (!canvas_bg) return;
      const ctx = canvas_bg.getContext('2d');
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
        canvas_bg.width = Math.round(W * DPR);
        canvas_bg.height = Math.round(H * DPR);
        canvas_bg.style.width = W + 'px';
        canvas_bg.style.height = H + 'px';
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