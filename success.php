<?php
include('db_connect.php');

// read parameters (as your app currently does)
$name   = isset($_GET['name']) ? $_GET['name'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$msg    = isset($_GET['msg']) ? $_GET['msg'] : '';

// prepare safe values for output / url
$displayName = htmlspecialchars($name);
$displayStatus = htmlspecialchars($status);
$displayMsg = htmlspecialchars($msg);
$encodedName = rawurlencode($name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Recorded</title>
  <link rel="stylesheet" href="bootstrap-5.3.8-dist/css/bootstrap.min.css">
  <link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
  <style>
    /* ensure page content sits above the particles */
    .container-fluid,
    .card.custom-shadow,
    .banner {
        position: relative;
        z-index: 2;
    }
/* particles container - behind content but above body gradient */
        #particles-js {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100vh;
            top:0;
            left:0;
            z-index: 0;              /* 1 = behind .container (which is z-index:2/3 below) */
            pointer-events: click;    /* allow clicks through */
            background: transparent;
            display: block;
        }
    body { font-family: Arial, sans-serif; background: linear-gradient(180deg,#04121a 0%, #075ad5ff 100%); text-align:center; padding:60px 20px; }
    .card {  background: #2e394dff; border: 1px solid rgba(255,255,255,0.04); max-width:720px; margin:0 auto; padding:36px 44px; border-radius:12px; box-shadow:0 8px 28px rgba(0,0,0,0.08); }
    h1 { margin:0 0 8px; font-size:26px;color:whitesmoke; }
    .meta { margin:14px 0; font-size:18px;color:whitesmoke;}
    .status { font-weight:700; color:#c62828; }
    .msg { margin-top:18px; color:#666; font-style:italic; }
    .actions { margin-top:26px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
    .btn { display:inline-block; padding:10px 18px; border-radius:8px; text-decoration:none; color:#fff; background:#0b69d6; }
    .btn.secondary { background:#0b69d6; }

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
  <div id="particles-js"></div>
  <div class="card">
    <h1>🎓 Attendance Recorded</h1>

    <div class="meta"><strong>Name:</strong> <?php echo $displayName; ?></div>
    <div class="meta"><strong>Status:</strong> <span class="status" style="color: red;"><?php echo $displayStatus; ?></span></div>

    <?php if ($displayMsg): ?>
      <div class="msg" style="color: white;"><?php echo $displayMsg; ?></div>
    <?php endif; ?>

    <div class="actions">
      <!-- Back to login -->
      <a class="btn btn-primary secondary" href="index.php">← Back to Login</a>

      <!-- View My Records: pass the student's name (or use school_id if you have one) to student_records.php -->
      <form action="student_records.php" method="GET" style="display:inline-block; margin:0;">
        <input type="hidden" name="q" value="<?php echo htmlspecialchars($name); ?>">
        <button type="submit" class="btn btn-primary">View My Records</button>
      </form>
    </div>
  </div>
  <!-- load official particles.js (use CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
    /* particles config: higher opacity/amount so it's visible over the blue gradient */
    particlesJS('particles-js', {
      "particles": {
        "number": {"value": 80, "density": {"enable": true, "value_area": 900}},
        "color": {"value": "#00f4ee"},
        "shape": {"type": "polygon", "stroke": {"width": 0, "color": "#000"}, "polygon": {"nb_sides": 6}},
        "opacity": {"value": 0.45, "random": true, "anim": {"enable": false}},
        "size": {"value": 3.5, "random": true},
        "line_linked": {"enable": true, "distance": 140, "color": "#00f4ee", "opacity": 0.22, "width": 1},
        "move": {"enable": true, "speed": 1.6, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false}
      },
      "interactivity": {
        "detect_on": "canvas",
        "events": {"onhover": {"enable": true, "mode": "repulse"}, "onclick": {"enable": true, "mode": "push"}, "resize": true},
        "modes": {"repulse": {"distance": 100, "duration": 0.4}, "push": {"particles_nb": 4}}
      },
      "retina_detect": true
    });
    </script>
</body>
</html>