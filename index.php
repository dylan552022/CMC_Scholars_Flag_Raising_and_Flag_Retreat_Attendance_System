<?php
include('db_connect.php');
// maka login anytime (for testing only)
// date_default_timezone_set('Asia/Manila');
// $dayNum = date('N'); // 1 (Mon) .. 7 (Sun) 
// // Temporarily allow attendance any day for testing
// $isFlagDay = true; // <-- Allow attendance every day for testing
// $todayName = date('l');
date_default_timezone_set('Asia/Manila');
$dayNum = date('N'); // 1 (Mon) .. 7 (Sun)
$isFlagDay = in_array($dayNum, [1, 5]); // maka log in ra og Lunes og Bernes (final)
$todayName = date('l');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>CMC Scholars — Flag Raising & Flag Retreat Attendance</title>
  <link rel="stylesheet" href="bootstrap-5.3.8-dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="fontawesome/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="" crossorigin="anonymous" media="print" onload="this.media='all'">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 0;
      overflow-y: hidden;
      background-image: url(images/background_image.jpg);
      background-repeat: no-repeat;
      background-position: center;
      background-size: cover;
      background-attachment: fixed;
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      color: #fff;
    }

    .container, .card.custom-shadow, .banner { position: relative; z-index:2; }

    .banner { background: linear-gradient(90deg,#0b63d6,#075ad5); color:#fff; padding:18px; border-radius:.5rem; margin-bottom:1rem; }

    .transparent-card {
      background: rgba(46, 57, 77, 0.7) !important; 
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    .form-label, .form-text { color: #dceff0; }
    .form-control {
      background: rgba(240, 241, 243, 0.9);
      color: #050a21;
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(5px);
    }
    .form-select {
      background-color: rgba(240, 241, 243, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-primary { background: linear-gradient(90deg,#6ec1ff,#7b61ff); border: none; color:#061022; }
    .btn-outline-primary {
      color: #fff;
      border-color: rgba(255, 255, 255, 0.4);
      background: rgba(109, 193, 255, 0.1);
    }
    .btn-outline-primary:hover {
      background: rgba(13, 110, 253, 0.9) !important;
      border-color: rgba(13, 110, 253, 0.9) !important;
      color: #fff !important;
    }
    .small-muted {
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.875rem;
    }
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(
          45deg,
          rgba(0, 0, 0, 0.4),
          rgba(0, 0, 0, 0.2)
      );
      z-index: 1;
    }
    .container {
      position: relative;
      z-index: 2;
      max-width: 450px !important;
      margin: auto !important;
      padding: 1.5rem !important;
      height: auto;
    }

    .card.transparent-card {
      border-radius: 15px;
      margin: 0;
    }

    .container.pt-3.pb-3 {
      padding-top: 0 !important;
      padding-bottom: 0 !important;
    }

    .card-body.p-4.p-md-5 {
      padding: 1.5rem !important;
    }

    .card-body img {
      width: 80px !important; 
      height: 80px !important;
      margin-bottom: 1rem !important;
    }

    .card-body h4 {
      font-size: 1.25rem;
      margin-bottom: 0.5rem;
    }

    .mb-3 {
      margin-bottom: 0.75rem !important;
    }

    .form-control, .form-select {
      height: 38px;
    }

    .btn-lg {
      height: 38px;
      line-height: 1;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .mt-4 {
      margin-top: 0.75rem !important;
    }

    .card-footer {
      padding: 1rem;
      font-size: 0.875rem;
    }

    @media (min-width: 992px) {
      .col-lg-5 {
        flex: 0 0 auto;
        width: 450px;
      }
    }

    .input-group-text {
      background: rgba(240,241,243,0.92);
      border: 1px solid rgba(255,255,255,0.12);
      color: #050a21;
      min-width: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .input-group .form-control {
      border-left: 0;
    }
    .input-group .form-control:focus {
      box-shadow: none;
    }
    .btn .btn-icon { margin-right: .6rem; display:inline-flex; align-items:center; }
    .btn .btn-icon i { font-size: 1.05rem; }
    .btn-outline-primary .btn-icon i { color: #fff; }
    .input-group-text { padding: 0 .75rem; }
    .input-group .form-control { border-left: 0; }
    .input-group .form-control:focus { box-shadow: none; }
    .btn:disabled .btn-icon i { opacity: .6; }
    .login-type-wrapper {
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .login-type-icon {
      width: 40px;
      height: 40px;
      min-width: 40px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.14);
      color: #fff;
      font-size: 18px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }
    .login-type-icon.admin { background: linear-gradient(135deg,#556ee6,#6f42c1); color: #fff; }
    .login-type-icon.student { background: linear-gradient(135deg,#06d6a0,#118ab2); color: #fff; }
    .login-type-select { flex: 1 1 auto; }

    /* Center the Admin Login button */
    .btn-lg.w-100 {
      display: block;
      width: 100%;
      max-width: 100%;
      margin: 0; /* align with username/password inputs */
      box-sizing: border-box;
      padding-left: 12px;
      padding-right: 12px;
    }

    /* Center the Quick Info section */
    .text-center {
      text-align: center;
    }
  </style>
</head>
<body>


  <div class="container pt-3 pb-3">
      <div class="row justify-content-center">
        <div class="col-11 col-sm-10 col-md-8 col-lg-5">
          <div class="card transparent-card shadow-sm">
            <div class="card-body p-4 p-md-5">
              <div class="text-center mb-3">
                <img src="images/CMC_Logo.jpg" alt="CMC Logo" class="rounded-circle mb-2 mt-0" style="width: 120px;height: 120px;border-radius:0;">
                <h4 class="mb-1 fw-bold text-white">CMC Scholars — Flag Raising & Flag Retreat Attendance</h4>
                <p class="small-muted mb-0 fw-bold text-white">Competence Meets Character.</p>
              </div>

              <form id="loginForm" action="admin_login.php" method="POST" autocomplete="off" novalidate>
                <div class="mb-3">
                  <label for="loginType" class="form-label">I am logging in as</label>
                  <div class="login-type-wrapper">
                    <div id="loginTypeIcon" class="login-type-icon admin" aria-hidden="true" title="Admin">
                      <i class="fas fa-user-shield"></i>
                    </div>
                    <select id="loginType" name="login_type" class="form-select login-type-select" aria-label="Login type">
                      <option value="admin">Admin / Coordinator</option>
                      <option value="student">Student</option>
                    </select>
                  </div>
                  <div class="form-text small-muted">Choose Admin to access the dashboard, or Student to log attendance.</div>
                </div>

                <div id="adminFields" class="mb-3">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                      <span class="input-group-text" id="username-addon" aria-hidden="true"><i class="fas fa-user"></i></span>
                      <input id="username" name="username" type="text" class="form-control" aria-describedby="username-addon" required>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                      <span class="input-group-text" id="password-addon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                      <input id="password" name="password" type="password" class="form-control" aria-describedby="password-addon" required>
                    </div>
                  </div>
                </div>

                <div id="studentFields" class="mb-3 d-none">
                  <div class="mb-2">
                    <label for="student_input" class="form-label">School ID or Full Name</label>
                    <div class="input-group">
                      <span class="input-group-text" id="student-addon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                      <input id="student_input" name="input_value" type="text" class="form-control" placeholder="" aria-describedby="student-addon">
                    </div>
                  </div>
                  <div class="form-text small-muted">Students may only log attendance on Flag Ceremony (Monday) & Flag Retreat (Friday).</div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label small-muted" for="remember">Remember me</label>
                 
                <div class="col d-grid gap-2 mt-4 mb-2 text-center">
                  <button id="primaryBtn" type="submit" class="btn btn-outline-primary btn-lg w-100" aria-label="Primary action">
                    <span class="btn-icon" aria-hidden="true"><i class="fas fa-sign-in-alt"></i></span>
                    <span class="btn-text">Admin Login</span>
                  </button>
                </div>

                <div class="text-center mt-4">
                  <div class="fw-bold text-white">Quick Info</div>
                  <div class="small-muted mt-1">
                    Today: <strong><?php echo htmlspecialchars($todayName); ?></strong>
                    <?php if (!$isFlagDay): ?>
                      &nbsp;•&nbsp;<span class="text-warning fw-semibold">Attendance closed</span>
                    <?php else: ?>
                      &nbsp;•&nbsp;<span class="text-success fw-semibold pt-2">Flag Day — attendance open</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="text-center small-muted pt-2 mt-4">
              © <span id="yr">2025</span> CMC Scholars. All rights reserved.
            </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <script>
    (function(){
      const loginType = document.getElementById('loginType');
      const loginTypeIcon = document.getElementById('loginTypeIcon');
      const adminFields = document.getElementById('adminFields');
      const studentFields = document.getElementById('studentFields');
      const form = document.getElementById('loginForm');
      const primaryBtn = document.getElementById('primaryBtn');
      const studentInput = document.getElementById('student_input');
      const username = document.getElementById('username');
      const password = document.getElementById('password');
      const btnDemo = document.getElementById('btnDemo');

      const isFlagDay = <?php echo $isFlagDay ? 'true' : 'false'; ?>;

      function updateMode(){
        const mode = loginType.value;
        if(mode === 'admin'){
          adminFields.classList.remove('d-none');
          studentFields.classList.add('d-none');
          form.action = 'admin_login.php';
          const t = primaryBtn.querySelector('.btn-text');
          if(t) t.textContent = 'Admin Login';
          primaryBtn.disabled = false;
          primaryBtn.classList.remove('pulse');
        } else {
          adminFields.classList.add('d-none');
          studentFields.classList.remove('d-none');
          form.action = 'attendance_process.php';
          const t = primaryBtn.querySelector('.btn-text');
          if(t) t.textContent = 'Log Attendance';
          primaryBtn.disabled = !isFlagDay;
          if(isFlagDay) primaryBtn.classList.add('pulse'); else primaryBtn.classList.remove('pulse');
        }
      }

      function refreshLoginTypeIcon(mode){
        if(!loginTypeIcon) return;
        if(mode === 'student'){
          loginTypeIcon.classList.remove('admin');
          loginTypeIcon.classList.add('student');
          loginTypeIcon.innerHTML = '<i class="fas fa-user-graduate" aria-hidden="true"></i>';
          loginTypeIcon.setAttribute('title','Student');
        } else {
          loginTypeIcon.classList.remove('student');
          loginTypeIcon.classList.add('admin');
          loginTypeIcon.innerHTML = '<i class="fas fa-user-shield" aria-hidden="true"></i>';
          loginTypeIcon.setAttribute('title','Admin / Coordinator');
        }
      }

      loginType.addEventListener('change', function(e){
        refreshLoginTypeIcon(e.target.value);
        if(typeof updateMode === 'function') updateMode(); 
      });
      btnDemo.addEventListener('click', function() {
        loginType.value = 'admin';
        username.value = 'demo';
        password.value = 'demo123';
        updateMode();
      });

      document.getElementById('yr').textContent = new Date().getFullYear();

      updateMode();
    })();
  </script>
  <script>
(function(){
  const loginType = document.getElementById('loginType');
  const form = document.getElementById('loginForm');
  const primaryBtn = document.getElementById('primaryBtn');
  const studentInput = document.getElementById('student_input');
  const isFlagDay = <?php echo $isFlagDay ? 'true' : 'false'; ?>;

  // Intercept student login to redirect to photo capture
  form.addEventListener('submit', function(e){
    if(loginType.value === 'student'){
      e.preventDefault();
      if(!isFlagDay){
        alert('Attendance is only open on Flag Ceremony (Monday) & Flag Retreat (Friday).');
        return;
      }
      const inputValue = studentInput.value.trim();
      if(!inputValue){
        alert('Please enter your School ID or Full Name.');
        studentInput.focus();
        return;
      }
      // Directly redirect to capture_photo.php with student info
      const params = new URLSearchParams({
        input_value: inputValue,
        current_day: '<?php echo $todayName; ?>',
        current_time: '<?php echo date('H:i:s'); ?>',
        current_date: '<?php echo date('Y-m-d'); ?>',
        status: 'Present',
        fullname: inputValue,
        student_id: inputValue
      });
      window.location.href = 'capture_photo.php?' + params.toString();
    }
    // else: allow normal admin login
  });
})();
</script>
</body>
</html>