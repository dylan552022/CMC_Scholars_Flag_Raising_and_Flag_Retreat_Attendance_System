<!DOCTYPE html>
<html>
<head>
	<title>Forgot Password</title>
	<link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
</head>
<style>
body {
	min-height: 100vh;
	margin: 0;
	padding: 0;
	background: #0f2027;
	background: linear-gradient(135deg, #232526 0%, #0f2027 100%);
	font-family: 'Segoe UI', Arial, sans-serif;
	overflow-x: hidden;
}
#particles-js {
	position: fixed;
	width: 100vw;
	height: 100vh;
	top: 0;
	left: 0;
	z-index: 0;
}
.forgot-container {
	position: relative;
	z-index: 2;
	width: 430px;
	max-width: 95vw;
	margin: 60px auto;
	background: rgba(20, 30, 48, 0.85);
	border-radius: 20px;
	box-shadow: 0 0 30px 2px #00fff7, 0 0 0 4px #0ff2ff33;
	border: 2px solid #00fff7;
	padding: 38px 32px 32px 32px;
	color: #fff;
	text-align: center;
	backdrop-filter: blur(2px);
}
.forgot-container h2 {
	font-size: 2.2rem;
	color: #00fff7;
	margin-bottom: 10px;
	letter-spacing: 1px;
}
.forgot-table {
	width: 100%;
	margin: 0 auto 10px auto;
	border-collapse: separate;
	border-spacing: 0 10px;
}
.forgot-table td {
	text-align: left;
	padding: 10px 0 10px 0;
	font-size: 1.1rem;
	color: #b2f7ef;
}
.input {
	font-size: 1rem;
	width: 100%;
	padding: 10px 12px;
	margin-top: 4px;
	margin-bottom: 2px;
	border-radius: 8px;
	border: 1px solid #00fff7;
	background: rgba(255,255,255,0.08);
	color: #fff;
	outline: none;
	box-sizing: border-box;
	transition: border 0.2s, box-shadow 0.2s;
}
.input:focus {
	border: 1.5px solid #00fff7;
	box-shadow: 0 0 8px #00fff7;
	background: rgba(0,255,247,0.08);
}
.button {
	width: 100%;
	padding: 12px 0;
	background: #00fff7;
	color: #232526;
	border: none;
	border-radius: 8px;
	font-size: 1.2rem;
	font-weight: bold;
	margin-top: 18px;
	margin-bottom: 10px;
	cursor: pointer;
	box-shadow: 0 0 10px #00fff7;
	transition: background 0.2s, color 0.2s;
}
.button:hover {
	background: #232526;
	color: #00fff7;
	box-shadow: 0 0 18px #00fff7;
}
</style>
<body>
	<div id="particles-js"></div>
	<div class="forgot-container">
		<h2>Forgot Password</h2>
		<form action="forgot_password1.php" method="post">
			<table class="forgot-table">
				<tr>
					<td>Enter Student ID or Fullname:</td>
					<td><input class="input" type="text" name="student_id" placeholder="Enter Student ID or Fullname" required></td>
				</tr>
				<tr>
					<td>Enter Scholar Type:</td>
					<td><input class="input" type="text" name="scholar_type" placeholder="Enter Scholar Type" required></td>
				</tr>
				<tr>
					<td colspan="2"><input class="button" type="submit" value="Recover Password"></td>
				</tr>
			</table>
		</form>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
	<script>
	particlesJS('particles-js', {
	  "particles": {
		"number": {"value": 60, "density": {"enable": true, "value_area": 800}},
		"color": {"value": "#00fff7"},
		"shape": {"type": "polygon", "stroke": {"width": 0, "color": "#000"}, "polygon": {"nb_sides": 6}},
		"opacity": {"value": 0.3, "random": true},
		"size": {"value": 4, "random": true},
		"line_linked": {"enable": true, "distance": 150, "color": "#00fff7", "opacity": 0.2, "width": 1},
		"move": {"enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false}
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