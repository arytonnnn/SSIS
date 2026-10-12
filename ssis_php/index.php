<?php
/**
 * index.php
 * ---------------------------------------------------------------------
 * The student portal. Same tabbed layout as the original index.html
 * mockup, but every table/stat is now pulled live from ssis_db instead
 * of being hard-coded.
 *
 * Non-student roles (Registrar/Cashier/DepartmentStaff/Admin) aren't
 * part of the original design, so they get a short landing notice
 * instead of an invented dashboard.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config.php';
require_login();

$role = $_SESSION['role'];

if ($role !== 'Student') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <title>SSIS | CuyoTech University</title>
      <link rel="stylesheet" href="style.css">
    </head>
    <body style="align-items:center; justify-content:center;">
      <div class="glass-card" style="max-width:480px; margin:80px auto; text-align:center;">
        <h2 style="font-family:'Merriweather',serif; color:#123C73; margin-bottom:12px;">
          Signed in as <?= h($role) ?>
        </h2>
        <p style="color:var(--text-muted); margin-bottom:20px;">
          The <?= h($role) ?> workspace isn't part of this build — the original
          design only covers the Student portal. You're authenticated
          against the database, though.
        </p>
        <a href="logout.php" class="btn btn-primary">Log out</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$studentId = (int)$_SESSION['student_id'];

// -- Student profile -----------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM Students WHERE studentID = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

// -- Most recent school year/term on file, used as "current" -------------
$stmt = $pdo->prepare(
    'SELECT schoolYear, term FROM Enrollments
     WHERE studentID = :id
     ORDER BY schoolYear DESC, enrollmentID DESC LIMIT 1'
);
$stmt->execute(['id' => $studentId]);
$latestTerm = $stmt->fetch();
$curYear = $latestTerm['schoolYear'] ?? null;
$curTerm = $latestTerm['term'] ?? null;

// -- Subjects enrolled in the current term (+ grades) ---------------------
$stmt = $pdo->prepare(
    'SELECT e.enrollmentID, e.status AS enrollStatus, e.schoolYear, e.term,
            c.courseCode, c.title, c.units, c.semester,
            g.midterm, g.finalGrade, g.remarks
     FROM Enrollments e
     JOIN Courses c ON c.courseID = e.courseID
     LEFT JOIN Grades g ON g.enrollmentID = e.enrollmentID
     WHERE e.studentID = :id AND e.schoolYear = :sy AND e.term = :term
     ORDER BY c.courseCode'
);
$stmt->execute(['id' => $studentId, 'sy' => $curYear, 'term' => $curTerm]);
$currentSubjects = $stmt->fetchAll();
$unitsEnrolled   = array_sum(array_column($currentSubjects, 'units'));

// -- Full grade history (all terms on file) -------------------------------
$stmt = $pdo->prepare(
    'SELECT e.schoolYear, e.term, c.courseCode, c.title, c.units,
            g.midterm, g.finalGrade, g.remarks
     FROM Enrollments e
     JOIN Courses c ON c.courseID = e.courseID
     LEFT JOIN Grades g ON g.enrollmentID = e.enrollmentID
     WHERE e.studentID = :id
     ORDER BY e.schoolYear DESC, c.courseCode'
);
$stmt->execute(['id' => $studentId]);
$gradeHistory = $stmt->fetchAll();

// -- Payments --------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT p.*, r.receiptNo
     FROM Payments p
     LEFT JOIN Receipts r ON r.paymentID = p.paymentID
     WHERE p.studentID = :id
     ORDER BY p.paymentDate DESC'
);
$stmt->execute(['id' => $studentId]);
$payments  = $stmt->fetchAll();
$totalPaid = array_sum(array_column($payments, 'amount'));

// -- Clearance ---------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT cr.*, d.deptName, d.deptHead
     FROM ClearanceRecords cr
     JOIN Departments d ON d.deptID = cr.deptID
     WHERE cr.studentID = :id
     ORDER BY d.deptName'
);
$stmt->execute(['id' => $studentId]);
$clearance       = $stmt->fetchAll();
$clearanceSigned = count(array_filter($clearance, fn($c) => $c['status'] === 'Signed'));
$clearanceTotal  = count($clearance);

// -- Document requests -------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM DocumentRequests WHERE studentID = :id ORDER BY requestDate DESC');
$stmt->execute(['id' => $studentId]);
$docRequests = $stmt->fetchAll();

$activeTab    = $_GET['tab'] ?? 'dashboard';
$validTabs    = ['dashboard', 'grades', 'subjects', 'enrollment', 'payment', 'document', 'clearance', 'settings'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'dashboard';
}
$flashSuccess = flash('flash_success');
$flashError   = flash('flash_error');

$feeSchedule = ['TOR' => 250.00, 'COR' => 100.00, 'COE' => 150.00];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SSIS - Student Portal | CuyoTech University</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header class="navbar">
    <div class="nav-brand">
      <img src="image/logo.png" alt="" srcset="">
      <h1>SSIS &mdash; Student Portal</h1>
    </div>
    <div class="nav-user">
      <div class="user-profile-menu">
        <svg style="width:20px;height:20px;fill:white" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
        <span style="font-weight: 600;"><?= h($student['fullName'] ?? $_SESSION['username']) ?></span>
      </div>
      <span style="opacity: 0.5;">|</span>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <div class="app-container">

    <aside class="sidebar">
      <a class="nav-item<?= $activeTab === 'dashboard' ? ' active' : '' ?>" onclick="switchTab('dashboard', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></span>
        Dashboard
      </a>
      <a class="nav-item<?= $activeTab === 'grades' ? ' active' : '' ?>" onclick="switchTab('grades', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
        Grades
      </a>
      <a class="nav-item<?= $activeTab === 'subjects' ? ' active' : '' ?>" onclick="switchTab('subjects', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg></span>
        Subjects
      </a>
      <a class="nav-item<?= $activeTab === 'enrollment' ? ' active' : '' ?>" onclick="switchTab('enrollment', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg></span>
        Enrollment
      </a>
      <a class="nav-item<?= $activeTab === 'payment' ? ' active' : '' ?>" onclick="switchTab('payment', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg></span>
        Payment
      </a>
      <a class="nav-item<?= $activeTab === 'document' ? ' active' : '' ?>" onclick="switchTab('document', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></span>
        Document
      </a>
      <a class="nav-item<?= $activeTab === 'clearance' ? ' active' : '' ?>" onclick="switchTab('clearance', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg></span>
        Clearance
      </a>
      <a class="nav-item<?= $activeTab === 'settings' ? ' active' : '' ?>" onclick="switchTab('settings', this)">
        <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg></span>
        Settings
      </a>
    </aside>

    <main class="content-area">
      <div id="breadcrumb" class="breadcrumb">Home / <?= h(ucfirst($activeTab)) ?></div>

      <?php if ($flashSuccess): ?>
        <div class="glass-card" style="border-color:#86EFAC; background:#F0FDF4; color:#15803D; font-weight:700; padding:14px 20px; margin-bottom:20px;">
          <?= h($flashSuccess) ?>
        </div>
      <?php endif; ?>
      <?php if ($flashError): ?>
        <div class="glass-card" style="border-color:#FDE047; background:#FEFCE8; color:#A16207; font-weight:700; padding:14px 20px; margin-bottom:20px;">
          <?= h($flashError) ?>
        </div>
      <?php endif; ?>

      <!-- VIEW 1: DASHBOARD -->
      <div id="view-dashboard" class="tab-content<?= $activeTab === 'dashboard' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Welcome back, <?= h(explode(' ', trim($student['fullName'] ?? ''))[0] ?? '') ?>!</h2>
          <p><?= h($student['program'] ?? '') ?> - Year <?= h((string)($student['yearLevel'] ?? '')) ?></p>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-info">
              <h4>Units Enrolled<?= $curTerm ? ' (' . h($curTerm) . ')' : '' ?></h4>
              <div class="stat-value"><?= (int)$unitsEnrolled ?></div>
            </div>
            <div class="stat-icon-wrapper"><svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg></div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <h4>Total Paid</h4>
              <div class="stat-value"><?= peso($totalPaid) ?></div>
            </div>
            <div class="stat-icon-wrapper"><svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg></div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <h4>Clearance Status</h4>
              <div class="stat-value"><?= (int)$clearanceSigned ?> / <?= (int)$clearanceTotal ?> Signed</div>
            </div>
            <div class="stat-icon-wrapper"><svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg></div>
          </div>
        </div>

        <div class="glass-card">
          <h3 style="font-family:'Merriweather',serif; color:#123C73; margin-bottom:16px;">Current Grades<?= $curTerm ? ' &mdash; ' . h($curTerm) . ' ' . h((string)$curYear) : '' ?></h3>
          <div class="table-container">
            <table>
              <thead><tr><th>Course</th><th>Units</th><th>Midterm</th><th>Final</th></tr></thead>
              <tbody>
                <?php if (empty($currentSubjects)): ?>
                  <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No enrollment on file yet.</td></tr>
                <?php else: foreach ($currentSubjects as $s): ?>
                  <tr>
                    <td><strong><?= h($s['courseCode']) ?></strong> - <?= h($s['title']) ?></td>
                    <td><?= (int)$s['units'] ?></td>
                    <td><?= $s['midterm'] !== null ? h(number_format((float)$s['midterm'], 2)) : '--' ?></td>
                    <td><?= $s['finalGrade'] !== null ? h(number_format((float)$s['finalGrade'], 2)) : '--' ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div style="display:flex; gap:16px; margin-top:20px;">
            <button class="btn btn-primary" onclick="switchTab('document', document.querySelectorAll('.nav-item')[5])">Request Document</button>
            <button class="btn btn-secondary" onclick="switchTab('payment', document.querySelectorAll('.nav-item')[4])">View Payments</button>
          </div>
        </div>
      </div>

      <!-- VIEW 2: GRADES -->
      <div id="view-grades" class="tab-content<?= $activeTab === 'grades' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Academic Grades Summary</h2>
          <p>Full grade history on file</p>
        </div>
        <div class="glass-card">
          <div class="table-container">
            <table>
              <thead><tr><th>Course Code</th><th>Description</th><th>Units</th><th>Midterm</th><th>Final Grade</th><th>Remarks</th></tr></thead>
              <tbody>
                <?php if (empty($gradeHistory)): ?>
                  <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No grade records found.</td></tr>
                <?php else: foreach ($gradeHistory as $g):
                  $remarkLabel = $g['remarks'] ?? 'Ongoing';
                ?>
                  <tr>
                    <td><?= h($g['courseCode']) ?></td>
                    <td><?= h($g['title']) ?></td>
                    <td><?= (int)$g['units'] ?></td>
                    <td><?= $g['midterm'] !== null ? h(number_format((float)$g['midterm'], 2)) : '--' ?></td>
                    <td><?= $g['finalGrade'] !== null ? h(number_format((float)$g['finalGrade'], 2)) : '--' ?></td>
                    <td><span class="badge <?= status_badge_class($remarkLabel) ?>"><?= h($remarkLabel) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- VIEW 3: SUBJECTS -->
      <div id="view-subjects" class="tab-content<?= $activeTab === 'subjects' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Course Subjects</h2>
          <p>Subjects enrolled for <?= $curTerm ? h($curTerm) . ' ' . h((string)$curYear) : 'the current term' ?></p>
        </div>

        <div class="glass-card">
          <div style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <input type="text" id="subjectSearchInput" class="form-control" placeholder="Search code or course title..." style="flex:1; min-width:240px;" onkeyup="filterSubjectsTable()">
          </div>

          <div class="table-container">
            <table id="subjectsTable">
              <thead><tr><th>Code</th><th>Subject Description</th><th>Units</th><th>Semester</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (empty($currentSubjects)): ?>
                  <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">You are not enrolled in any subjects yet.</td></tr>
                <?php else: foreach ($currentSubjects as $s): ?>
                  <tr>
                    <td><strong><?= h($s['courseCode']) ?></strong></td>
                    <td><?= h($s['title']) ?></td>
                    <td><?= (int)$s['units'] ?> Units</td>
                    <td><?= h($s['semester']) ?></td>
                    <td><span class="badge <?= status_badge_class($s['enrollStatus']) ?>"><?= h($s['enrollStatus']) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <p style="margin-top:12px; font-size:0.85rem; color:var(--text-muted);">
            Instructor, room, and schedule details aren't tracked in the current database schema, so they aren't shown here.
          </p>
        </div>
      </div>

      <!-- VIEW 4: ENROLLMENT -->
      <div id="view-enrollment" class="tab-content<?= $activeTab === 'enrollment' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Online Course Enrollment</h2>
          <p>Enrollment record on file</p>
        </div>
        <div class="glass-card">
          <h3>Enrollment Status</h3>
          <?php if ($curTerm): ?>
            <p style="margin-top:10px; color:var(--text-muted);">
              You are currently enrolled for <strong><?= h($curTerm) ?>, <?= h((string)$curYear) ?></strong>
              with a status of <strong><?= h($currentSubjects[0]['enrollStatus'] ?? 'Pending') ?></strong> across
              <?= count($currentSubjects) ?> subject(s).
            </p>
          <?php else: ?>
            <p style="margin-top:10px; color:var(--text-muted);">No enrollment record found yet. Please coordinate with the Registrar.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- VIEW 5: PAYMENT -->
      <div id="view-payment" class="tab-content<?= $activeTab === 'payment' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Tuition &amp; Fee Payments</h2>
          <p>Payment history on file</p>
        </div>
        <div class="glass-card">
          <h3>Account Summary</h3>
          <p style="font-size:1.4rem; color:#123C73; font-weight:700; margin:12px 0;">Total Paid to Date: <?= peso($totalPaid) ?></p>
          <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
            Tuition assessment isn't part of the current schema, so an outstanding balance can't be computed here &mdash; only recorded payments are shown.
          </p>
          <div class="table-container">
            <table>
              <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Receipt No.</th></tr></thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No payments recorded yet.</td></tr>
                <?php else: foreach ($payments as $p): ?>
                  <tr>
                    <td><?= h(friendly_date($p['paymentDate'], 'M j, Y g:i A')) ?></td>
                    <td><?= peso((float)$p['amount']) ?></td>
                    <td><?= h($p['method']) ?></td>
                    <td><?= h($p['receiptNo'] ?? '--') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- VIEW 6: DOCUMENT REQUEST -->
      <div id="view-document" class="tab-content<?= $activeTab === 'document' ? '' : ' hidden' ?>">
        <div class="grid-2col">

          <div class="glass-card">
            <h3 style="font-family:'Merriweather',serif; color:#123C73; margin-bottom:6px;">Request a Document</h3>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:20px;">Fields marked * are required</p>

            <form action="actions/submit_document.php" method="post">
              <div class="form-group">
                <label class="form-label">Document Type *</label>
                <select id="docType" name="docType" class="form-select" required onchange="updateEstimatedFee()">
                  <option value="" disabled selected>Select: TOR / COR / Certification</option>
                  <option value="COR">Certificate of Registration (COR)</option>
                  <option value="TOR">Transcript of Records (TOR)</option>
                  <option value="COE">Certificate of Enrollment</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Purpose *</label>
                <input type="text" name="purpose" class="form-control" placeholder="e.g. Employment, Transfer" required>
              </div>

              <div class="form-group">
                <label class="form-label">Number of Copies *</label>
                <input type="number" id="copiesInput" name="copies" class="form-control" value="1" min="1" max="10" required oninput="updateEstimatedFee()" onchange="updateEstimatedFee()">
              </div>

              <div class="form-group">
                <label class="form-label">Delivery Method *</label>
                <div class="radio-group">
                  <label class="radio-label"><input type="radio" name="delivery" value="pickup" checked> Pick-up at Registrar</label>
                  <label class="radio-label"><input type="radio" name="delivery" value="email"> Email (PDF)</label>
                </div>
              </div>

              <div style="background:#E0F2FE; border:1px solid #7DD3FC; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:0.9rem; color:#0369A1; font-weight:600;">
                Estimated Fee: <span id="feeAmount">₱100.00</span> (payable at Cashier)
              </div>

              <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Submit Request</button>
                <button type="button" class="btn btn-secondary" onclick="switchTab('dashboard', document.querySelectorAll('.nav-item')[0])">Cancel</button>
              </div>
            </form>
          </div>

          <div>
            <div class="glass-card">
              <h3 style="font-family:'Merriweather',serif; color:#123C73; margin-bottom:16px;">Request Status Tracker</h3>
              <div id="requestList">
                <?php if (empty($docRequests)): ?>
                  <p style="color:var(--text-muted);">No document requests yet.</p>
                <?php else: foreach ($docRequests as $r): ?>
                  <div class="request-card">
                    <div class="request-info">
                      <div class="request-icon">PDF</div>
                      <div class="request-details">
                        <h5><?= h($r['docType']) ?> - Req#<?= (int)$r['requestID'] ?></h5>
                        <p><?= h($r['purpose']) ?> &middot; <?= h(friendly_date($r['requestDate'])) ?></p>
                      </div>
                    </div>
                    <span class="badge <?= status_badge_class($r['status']) ?>"><?= h($r['status']) ?></span>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- VIEW 7: CLEARANCE -->
      <div id="view-clearance" class="tab-content<?= $activeTab === 'clearance' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Student Clearance Status</h2>
          <p>Signatures required for graduation and semestral release.</p>
        </div>
        <div class="glass-card">
          <div class="table-container">
            <table>
              <thead><tr><th>Department / Office</th><th>Signatory</th><th>Status</th><th>Remarks</th></tr></thead>
              <tbody>
                <?php if (empty($clearance)): ?>
                  <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No clearance records on file.</td></tr>
                <?php else: foreach ($clearance as $c): ?>
                  <tr>
                    <td><?= h($c['deptName']) ?></td>
                    <td><?= h($c['deptHead'] ?? '--') ?></td>
                    <td><span class="badge <?= status_badge_class($c['status']) ?>"><?= h($c['status']) ?></span></td>
                    <td><?= h($c['remarks'] ?? '--') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- VIEW 8: SETTINGS -->
      <div id="view-settings" class="tab-content<?= $activeTab === 'settings' ? '' : ' hidden' ?>">
        <div class="page-title-banner">
          <h2>Account Settings</h2>
          <p>Manage security and student profile info.</p>
        </div>
        <div class="glass-card">
          <h3>Change Security Password</h3>
          <form style="margin-top:16px; max-width:400px;" action="actions/update_password.php" method="post">
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" minlength="8" required>
            </div>
            <button class="btn btn-primary" type="submit">Update Password</button>
          </form>
        </div>
      </div>

    </main>
  </div>

  <script>
    const FEE_SCHEDULE = <?= json_encode($feeSchedule) ?>;

    function switchTab(viewId, element) {
      const contents = document.querySelectorAll('.tab-content');
      contents.forEach(el => el.classList.add('hidden'));

      const navItems = document.querySelectorAll('.nav-item');
      navItems.forEach(item => item.classList.remove('active'));

      const targetView = document.getElementById('view-' + viewId);
      if (targetView) {
        targetView.classList.remove('hidden');
      }

      if (element) {
        element.classList.add('active');
      }

      const breadcrumb = document.getElementById('breadcrumb');
      const titleName = viewId.charAt(0).toUpperCase() + viewId.slice(1);
      breadcrumb.innerText = 'Home / ' + titleName;

      // Keep the URL in sync so a reload / form redirect lands on the same tab.
      const url = new URL(window.location);
      url.searchParams.set('tab', viewId);
      window.history.replaceState({}, '', url);
    }

    function updateEstimatedFee() {
      const type = document.getElementById('docType').value;
      const feeSpan = document.getElementById('feeAmount');
      const copiesInput = document.getElementById('copiesInput');
      // Guard against blank/invalid input while the user is still typing.
      let copies = parseInt(copiesInput.value, 10);
      if (!Number.isInteger(copies) || copies < 1) {
        copies = 1;
      }
      if (FEE_SCHEDULE[type] !== undefined) {
        const total = FEE_SCHEDULE[type] * copies;
        feeSpan.innerText = '₱' + total.toFixed(2);
      }
    }

    function filterSubjectsTable() {
      const input = document.getElementById('subjectSearchInput').value.toLowerCase();
      const rows = document.querySelectorAll('#subjectsTable tbody tr');
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
      });
    }
  </script>
</body>
</html>