<?php
/**
 * login.php
 * ---------------------------------------------------------------------
 * Renders the login form (same markup/design as the original login.html
 * mockup) and, on POST, checks the submitted credentials against the
 * Users table (and Students, so a student can log in with either their
 * account username or their student number).
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config.php';

// Already signed in? Skip straight to the portal.
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errorMsg      = '';
$submittedUser = '';
$submittedRole = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUser = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';
    $submittedRole = $_POST['role'] ?? 'student';

    // The <select>/radio values in the form are lowercase slugs; the
    // Users.role column stores them Capitalized.
    $roleMap = [
        'student'    => 'Student',
        'registrar'  => 'Registrar',
        'cashier'    => 'Cashier',
        'dept_staff' => 'DepartmentStaff',
        'admin'      => 'Admin',
    ];
    $dbRole = $roleMap[$submittedRole] ?? null;

    if ($submittedUser === '' || $password === '') {
        $errorMsg = 'Please enter both a username/student number and a password.';
    } elseif ($dbRole === null) {
        $errorMsg = 'Please choose a valid role.';
    } else {
        // Note: with real (non-emulated) prepared statements, MySQL does not
        // allow the same named placeholder to appear twice, so the value is
        // bound to two distinct parameters instead.
        $stmt = $pdo->prepare(
            'SELECT u.userID, u.username, u.passwordHash, u.role, u.status,
                    s.studentID, s.fullName, s.program, s.yearLevel
             FROM Users u
             LEFT JOIN Students s ON s.userID = u.userID
             WHERE u.username = :login1 OR s.studentNo = :login2
             LIMIT 1'
        );
        $stmt->execute(['login1' => $submittedUser, 'login2' => $submittedUser]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['passwordHash'])) {
            $errorMsg = 'Invalid username or password.';
        } elseif ($user['status'] !== 'Active') {
            $errorMsg = 'This account is ' . strtolower($user['status']) . '. Please contact the Registrar.';
        } elseif ($user['role'] !== $dbRole) {
            $errorMsg = 'That account is not registered under the "' . h($submittedRole) . '" role.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user['userID'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            if ($user['role'] === 'Student') {
                $_SESSION['student_id'] = (int)$user['studentID'];
                $_SESSION['full_name']  = $user['fullName'];
                $_SESSION['program']    = $user['program'];
                $_SESSION['year_level'] = (int)$user['yearLevel'];
            }

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SSIS Login | CuyoTech University</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-dark: #18539B;
      --primary-blue: #2B72C4;
      --primary-light: #5296E0;
      --bg-gradient-start: #EAF4FD;
      --bg-gradient-end: #C5E1F9;
      --text-dark: #123C73;
      --text-muted: #5B708B;
      --card-bg: rgba(255, 255, 255, 0.92);
      --card-border: #B2D5F6;
      --input-bg: #F0F7FF;
      --input-border: #92C2EE;
      --error-color: #E11D48;
      --radius-lg: 16px;
      --radius-md: 10px;
      --shadow-sm: 0 4px 15px rgba(26, 86, 156, 0.08);
      --shadow-lg: 0 10px 30px rgba(26, 86, 156, 0.15);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text-dark);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-gradient-start);
      background-image:
        radial-gradient(circle at 10% 20%, rgba(82, 150, 224, 0.25) 0%, transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(26, 86, 156, 0.2) 0%, transparent 45%),
        radial-gradient(circle at 50% 50%, rgba(215, 236, 251, 0.7) 0%, transparent 100%);
      padding: 30px 20px;
    }
    .main-wrapper { display: grid; grid-template-columns: 1fr 1.15fr; gap: 28px; max-width: 1000px; width: 100%; align-items: start; }
    .left-panel { display: flex; flex-direction: column; gap: 20px; }
    .university-header { display: flex; align-items: center; gap: 16px; }
    .univ-logo { width: 68px; height: 68px; background: #fff; border-radius: 50%; border: 3px solid var(--primary-dark); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: var(--primary-dark); box-shadow: var(--shadow-sm); flex-shrink: 0; }
    .univ-title { font-family: 'Merriweather', serif; font-size: 1.6rem; color: var(--primary-dark); line-height: 1.25; }
    .glass-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); backdrop-filter: blur(8px); }
    .portal-banner-title { font-family: 'Merriweather', serif; font-size: 1.2rem; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: var(--primary-dark); }
    .info-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
    .info-list li { display: flex; align-items: center; gap: 12px; font-weight: 600; color: var(--text-muted); font-size: 0.95rem; }
    .info-list i { color: var(--primary-blue); font-size: 1.1rem; }
    .ui-annotation-box { background: #EFF7FF; border: 1px solid #B8DCFF; border-radius: var(--radius-md); padding: 18px; }
    .ui-annotation-box h4 { font-size: 0.92rem; color: var(--primary-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .ui-annotation-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .ui-annotation-list li { display: flex; align-items: flex-start; gap: 10px; font-size: 0.85rem; color: #334E68; }
    .annotation-num { background: var(--primary-blue); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
    .right-panel { position: relative; }
    .login-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-lg); padding: 36px 32px; box-shadow: var(--shadow-lg); backdrop-filter: blur(10px); position: relative; overflow: hidden; }
    .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 8px; background: linear-gradient(90deg, #18539B 0%, #2A71C4 50%, #478BD9 100%); }
    .system-title-group { text-align: center; margin-bottom: 24px; }
    .system-logo-sm { width: 52px; height: 52px; background: #fff; border-radius: 50%; border: 2px solid var(--primary-blue); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; color: var(--primary-dark); font-size: 1.5rem; box-shadow: var(--shadow-sm); }
    .system-title-group h2 { font-family: 'Merriweather', serif; font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 4px; }
    .system-title-group p { color: var(--text-muted); font-size: 0.88rem; font-weight: 600; }
    .form-group { margin-bottom: 18px; }
    .form-label { display: flex; align-items: center; justify-content: space-between; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px; font-size: 0.88rem; }
    .input-wrapper { position: relative; }
    .form-control { width: 100%; padding: 12px 40px 12px 14px; border-radius: var(--radius-md); border: 1px solid var(--input-border); background-color: var(--input-bg); font-size: 0.95rem; color: #1E293B; outline: none; transition: all 0.2s; }
    .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(43, 114, 196, 0.2); background-color: #fff; }
    .input-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--primary-blue); font-size: 1.1rem; pointer-events: none; }
    .role-section { margin-bottom: 20px; }
    .role-options { display: flex; flex-wrap: wrap; gap: 12px 16px; margin-top: 6px; }
    .role-label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; color: #2D4059; cursor: pointer; }
    .role-label input[type="radio"] { accent-color: var(--primary-blue); width: 16px; height: 16px; }
    .btn-submit { width: 100%; padding: 12px; border-radius: var(--radius-md); background: linear-gradient(180deg, #2B72C4 0%, #1A569C 100%); color: #ffffff; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 10px rgba(26, 86, 156, 0.25); transition: all 0.2s ease; }
    .btn-submit:hover { background: linear-gradient(180deg, #3580D8 0%, #1D5FB0 100%); transform: translateY(-1px); }
    .forgot-link-wrapper { text-align: center; margin-top: 14px; }
    .forgot-link { color: var(--primary-blue); text-decoration: none; font-weight: 700; font-size: 0.88rem; }
    .forgot-link:hover { text-decoration: underline; }
    .error-feedback { margin-top: 18px; color: var(--error-color); font-size: 0.88rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
    .hidden { display: none !important; }
    @media (max-width: 840px) { .main-wrapper { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

  <div class="main-wrapper">

    <div class="left-panel">
      <div class="university-header">
        <div class="univ-logo"><i class="fa-solid fa-graduation-cap"></i></div>
        <h1 class="univ-title">Welcome to CuyoTech<br>University</h1>
      </div>

      <div class="glass-card">
        <div class="portal-banner-title"><i class="fa-solid fa-shield-halved"></i> Student Portal</div>
        <ul class="info-list">
          <li><i class="fa-regular fa-square-check"></i><span>Secure student access</span></li>
          <li><i class="fa-regular fa-file-lines"></i><span>Check your academic services</span></li>
          <li><i class="fa-regular fa-circle-question"></i><span>Need help? Contact Registrar</span></li>
        </ul>
      </div>

      <div class="ui-annotation-box">
        <h4><i class="fa-solid fa-table-cells"></i> Sign-in Notes</h4>
        <ul class="ui-annotation-list">
          <li><span class="annotation-num">1</span> Students may sign in with their account username or their student number.</li>
          <li><span class="annotation-num">2</span> The role you pick must match how your account was registered.</li>
          <li><span class="annotation-num">3</span> Accounts marked Inactive or Suspended by the Registrar cannot sign in.</li>
        </ul>
      </div>
    </div>

    <div class="right-panel">
      <div class="login-card">

        <div class="system-title-group">
          <div class="system-logo-sm"><i class="fa-solid fa-university"></i></div>
          <h2>Student Service<br>Information System</h2>
          <p>Log in to your account</p>
        </div>

        <form id="loginForm" method="post" action="login.php">

          <div class="form-group">
            <label class="form-label" for="usernameInput">Username / Student No.</label>
            <div class="input-wrapper">
              <input type="text" id="usernameInput" name="username" class="form-control" placeholder="e.g. 2026-126" value="<?= h($submittedUser) ?>" required />
              <span class="input-icon"><i class="fa-regular fa-user"></i></span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="passwordInput">Password</label>
            <div class="input-wrapper">
              <input type="password" id="passwordInput" name="password" class="form-control" placeholder="••••••••••••" required />
              <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
            </div>
          </div>

          <div class="role-section">
            <label class="form-label"><span>Log in as:</span><i class="fa-solid fa-users" style="color: var(--primary-blue);"></i></label>
            <div class="role-options">
              <label class="role-label"><input type="radio" name="role" value="student" <?= $submittedRole === 'student' ? 'checked' : '' ?> /> Student</label>
              <label class="role-label"><input type="radio" name="role" value="registrar" <?= $submittedRole === 'registrar' ? 'checked' : '' ?> /> Registrar</label>
              <label class="role-label"><input type="radio" name="role" value="cashier" <?= $submittedRole === 'cashier' ? 'checked' : '' ?> /> Cashier</label>
              <label class="role-label"><input type="radio" name="role" value="dept_staff" <?= $submittedRole === 'dept_staff' ? 'checked' : '' ?> /> Dept. Staff</label>
              <label class="role-label"><input type="radio" name="role" value="admin" <?= $submittedRole === 'admin' ? 'checked' : '' ?> /> Admin</label>
            </div>
          </div>

          <button type="submit" class="btn-submit"><span>LOG IN</span><i class="fa-solid fa-arrow-right"></i></button>

          <div class="forgot-link-wrapper">
            <a href="#" class="forgot-link" onclick="alert('Please visit the Registrar office to reset your credentials.'); return false;">Forgot password?</a>
          </div>

          <div id="errorAlert" class="error-feedback <?= $errorMsg === '' ? 'hidden' : '' ?>">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= h($errorMsg) ?></span>
          </div>

        </form>
      </div>
    </div>

  </div>
</body>
</html>
