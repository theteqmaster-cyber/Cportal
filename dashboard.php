<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$userId = $currentUser['id'];
$userRole = $currentUser['role'];

// Global messaging
$error = '';
$success = '';

// Check if user account is locked
try {
    $lockCheck = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $lockCheck->execute([$userId]);
    $uStatus = $lockCheck->fetchColumn();
    if ($uStatus === 'locked') {
        session_unset();
        session_destroy();
        header('Location: login.php?error=Account is locked.');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Failed to verify account lock state.';
}

// Audit logger helper
function logAction($pdo, $userId, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $ip]);
    } catch (PDOException $e) {
        error_log("Failed to log action: " . $e->getMessage());
    }
}

// --- POST HANDLING FOR COMMON UTILITIES ---

// 1. Password Change
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = isset($_POST['currentPassword']) ? trim($_POST['currentPassword']) : '';
    $newPassword     = isset($_POST['newPassword']) ? trim($_POST['newPassword']) : '';

    if (empty($currentPassword) || empty($newPassword)) {
        $error = 'Both password fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();

            if (password_verify($currentPassword, $currentHash)) {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$newHash, $userId]);

                logAction($pdo, $userId, "Changed login password");
                $success = 'Password changed successfully.';
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to change password: ' . $e->getMessage();
        }
    }
}

// 2. Submit Help Ticket
if (isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    $msg = isset($_POST['message']) ? trim($_POST['message']) : '';
    if (empty($msg)) {
        $error = 'Ticket description cannot be empty.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO help_tickets (user_id, message, status) VALUES (?, ?, 'open')");
            $stmt->execute([$userId, $msg]);
            logAction($pdo, $userId, "Submitted a help ticket");
            $success = 'Your support request has been logged. IT Helpdesk will review it soon.';
        } catch (PDOException $e) {
            $error = 'Failed to create help ticket: ' . $e->getMessage();
        }
    }
}

// --- ROLE SPECIFIC POST ACTIONS ---

// Bursar: Update Fee Status
if ($userRole === 'bursar' && isset($_POST['action']) && $_POST['action'] === 'update_fees') {
    $studentId  = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $feesPaid   = isset($_POST['fees_paid']) ? (int)$_POST['fees_paid'] : 0;

    try {
        $stmt = $pdo->prepare("UPDATE students SET fees_paid = ? WHERE id = ?");
        $stmt->execute([$feesPaid, $studentId]);
        
        $sName = $pdo->prepare("SELECT name FROM students WHERE id = ?");
        $sName->execute([$studentId]);
        $nameStr = $sName->fetchColumn();

        logAction($pdo, $userId, "Updated fees status for $nameStr to " . ($feesPaid ? 'Paid' : 'Unpaid'));
        $success = "Fee status updated for $nameStr.";
    } catch (PDOException $e) {
        $error = "Failed to update fees: " . $e->getMessage();
    }
}

// Teacher: Input Grade
if ($userRole === 'teacher' && isset($_POST['action']) && $_POST['action'] === 'input_grade') {
    $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $subject   = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $marks     = isset($_POST['marks']) ? $_POST['marks'] : '';
    $term      = isset($_POST['term']) ? trim($_POST['term']) : 'Term 1';

    if ($marks === '') {
        $error = 'Marks field is empty.';
    } else {
        $numericMarks = (int)$marks;
        if ($numericMarks < 0 || $numericMarks > 100) {
            $error = 'Marks must be between 0 and 100.';
        } else {
            try {
                // Check if grade exists
                $check = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject = ? AND term = ?");
                $check->execute([$studentId, $subject, $term]);
                $gradeId = $check->fetchColumn();

                if ($gradeId) {
                    $stmt = $pdo->prepare("UPDATE grades SET marks = ?, teacher_id = ? WHERE id = ?");
                    $stmt->execute([$numericMarks, $userId, $gradeId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$studentId, $subject, $numericMarks, $term, $userId]);
                }

                logAction($pdo, $userId, "Updated grade for student ID $studentId in $subject ($numericMarks%)");
                $success = 'Grade saved successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to save grade: ' . $e->getMessage();
            }
        }
    }
}

// Admin Actions
if ($userRole === 'admin' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // 1. Hire Teacher
    if ($act === 'hire_teacher') {
        $name     = isset($_POST['name']) ? trim($_POST['name']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $subjects = isset($_POST['subjects']) ? trim($_POST['subjects']) : '';

        if (empty($name) || empty($username) || empty($password)) {
            $error = 'All fields are required to hire a teacher.';
        } else {
            try {
                $pdo->beginTransaction();
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = 'Username already in use.';
                    $pdo->rollBack();
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'teacher')")
                        ->execute([$username, $hash]);
                    $teacherId = $pdo->lastInsertId();

                    $subjectsArray = array_filter(array_map('trim', explode(',', $subjects)));
                    $pdo->prepare("INSERT INTO teachers (id, name, subjects) VALUES (?, ?, ?)")
                        ->execute([$teacherId, $name, json_encode($subjectsArray)]);

                    logAction($pdo, $userId, "Hired teacher $name (username: $username)");
                    $success = "Teacher $name added successfully.";
                    $pdo->commit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Hire failed: ' . $e->getMessage();
            }
        }
    }

    // 2. Create Class
    if ($act === 'create_class') {
        $name      = isset($_POST['name']) ? trim($_POST['name']) : '';
        $teacherId = isset($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
        if (empty($teacherId)) $teacherId = null;

        if (empty($name)) {
            $error = 'Class name is required.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
                $check->execute([$name]);
                if ($check->fetch()) {
                    $error = 'Class already exists.';
                } else {
                    $pdo->prepare("INSERT INTO classes (name, teacher_id) VALUES (?, ?)")
                        ->execute([$name, $teacherId]);
                    logAction($pdo, $userId, "Created class $name");
                    $success = "Class $name created.";
                }
            } catch (PDOException $e) {
                $error = 'Class creation failed: ' . $e->getMessage();
            }
        }
    }

    // 3. Post Event
    if ($act === 'post_event') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $desc  = isset($_POST['description']) ? trim($_POST['description']) : '';
        $date  = isset($_POST['event_date']) ? $_POST['event_date'] : '';

        if (empty($title) || empty($date)) {
            $error = 'Title and event date are required.';
        } else {
            try {
                $pdo->prepare("INSERT INTO events (title, description, event_date, created_by) VALUES (?, ?, ?, ?)")
                    ->execute([$title, $desc, $date, $userId]);
                logAction($pdo, $userId, "Created school event: $title");
                $success = "Event posted.";
            } catch (PDOException $e) {
                $error = "Failed to post event: " . $e->getMessage();
            }
        }
    }

    // 4. Enroll Student Manually
    if ($act === 'enroll_student_manual') {
        $name     = isset($_POST['name']) ? trim($_POST['name']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $classId  = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;

        if (empty($name) || empty($username) || empty($password) || !$classId) {
            $error = 'All fields are required.';
        } else {
            try {
                $pdo->beginTransaction();
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = 'Username already in use.';
                    $pdo->rollBack();
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'student')")
                        ->execute([$username, $hash]);
                    $studentId = $pdo->lastInsertId();

                    $pdo->prepare("INSERT INTO students (id, name, class_id, fees_paid, parent_id, enrollment_status) VALUES (?, ?, ?, 0, NULL, 'approved')")
                        ->execute([$studentId, $name, $classId]);

                    logAction($pdo, $userId, "Manually enrolled student $name");
                    $success = "Student enrolled successfully.";
                    $pdo->commit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Manual enrollment failed: ' . $e->getMessage();
            }
        }
    }

    // 5. Approve/Reject Application
    if ($act === 'enrollment_action') {
        $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $decision  = isset($_POST['decision']) ? $_POST['decision'] : ''; // approve or reject
        $classId   = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;

        try {
            $sName = $pdo->prepare("SELECT name FROM students WHERE id = ?");
            $sName->execute([$studentId]);
            $nameStr = $sName->fetchColumn();

            if ($decision === 'approve') {
                if (!$classId) {
                    $error = 'Please assign a class to approve the student.';
                } else {
                    $pdo->prepare("UPDATE students SET enrollment_status = 'approved', class_id = ? WHERE id = ?")
                        ->execute([$classId, $studentId]);
                    logAction($pdo, $userId, "Approved enrollment for $nameStr, assigned to class ID $classId");
                    $success = "Enrollment approved for $nameStr.";
                }
            } else if ($decision === 'reject') {
                $pdo->prepare("UPDATE students SET enrollment_status = 'rejected' WHERE id = ?")
                    ->execute([$studentId]);
                logAction($pdo, $userId, "Rejected enrollment for $nameStr");
                $success = "Enrollment application rejected.";
            }
        } catch (PDOException $e) {
            $error = 'Enrollment action failed: ' . $e->getMessage();
        }
    }
}

// IT Helpdesk Actions
if ($userRole === 'helpdesk' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // 1. Lock/Unlock User
    if ($act === 'toggle_lock') {
        $targetId  = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
        $newStatus = isset($_POST['status']) ? $_POST['status'] : 'active';

        try {
            $usernameStr = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $usernameStr->execute([$targetId]);
            $uName = $usernameStr->fetchColumn();

            if ($uName === 'helpdesk') {
                $error = 'Cannot lock the primary IT Helpdesk account.';
            } else {
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $targetId]);
                logAction($pdo, $userId, ($newStatus === 'locked' ? 'Locked' : 'Unlocked') . " account for $uName");
                $success = "User status updated to $newStatus.";
            }
        } catch (PDOException $e) {
            $error = "Failed to update lock status: " . $e->getMessage();
        }
    }

    // 2. Reset Password
    if ($act === 'reset_password') {
        $targetId    = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
        $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

        if (strlen($newPassword) < 4) {
            $error = 'Password must be at least 4 characters long.';
        } else {
            try {
                $usernameStr = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $usernameStr->execute([$targetId]);
                $uName = $usernameStr->fetchColumn();

                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $targetId]);
                logAction($pdo, $userId, "Reset password credentials for user $uName");
                $success = "Password for user $uName reset successfully.";
            } catch (PDOException $e) {
                $error = "Password reset failed: " . $e->getMessage();
            }
        }
    }

    // 3. Resolve/Escalate Ticket
    if ($act === 'resolve_ticket') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $status   = isset($_POST['status']) ? $_POST['status'] : 'resolved';
        $response = isset($_POST['response']) ? trim($_POST['response']) : '';

        try {
            $pdo->prepare("UPDATE help_tickets SET status = ?, response = ? WHERE id = ?")
                ->execute([$status, $response, $ticketId]);
            logAction($pdo, $userId, "Updated ticket ID $ticketId to status $status");
            $success = "Ticket status updated to $status.";
        } catch (PDOException $e) {
            $error = "Failed to update ticket: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - cportal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Navigation Header -->
  <header>
    <div class="header-container">
      <div style="display: flex; align-items: center; gap: 10px;" onclick="window.location.href='index.php'" style="cursor: pointer;">
        <div style="width: 40px; height: 40px; background-color: var(--primary); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 20px; box-shadow: 0 0 10px var(--primary-glow)">C</div>
        <div style="margin-left: 10px;">
          <h2 style="font-size: 18px; line-height: 1.1;">cportal</h2>
          <span style="font-size: 12px; color: var(--text-secondary);">Tswayi High School</span>
        </div>
      </div>
      <nav style="display: flex; gap: 15px; align-items: center;">
        <span style="font-size: 14px; color: var(--text-secondary);">
          Logged in as <strong style="color: var(--text-primary);"><?= htmlspecialchars($currentUser['username']) ?></strong>
        </span>
        <a href="logout.php" class="btn btn-danger" style="padding: 6px 14px;">Logout</a>
      </nav>
    </div>
  </header>

  <!-- Main Container -->
  <main>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <h1>Dashboard Overview</h1>
      <span style="font-size: 13px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 20px; text-transform: uppercase;">
        Role: <strong><?= $userRole ?></strong>
      </span>
    </div>

    <!-- Feedback Banners -->
    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><strong>Success:</strong> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ---------------------------------------------------- -->
    <!-- 1. STUDENT VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php if ($userRole === 'student'): ?>
      <?php
        $stmt = $pdo->prepare("SELECT s.name, s.fees_paid, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$userId]);
        $student = $stmt->fetch();
      ?>
      <?php if ($student): ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
          <div class="glass-panel" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
              <h2>Welcome back, <?= htmlspecialchars($student['name']) ?></h2>
              <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Class: <strong><?= htmlspecialchars($student['class_name'] ?? 'Not allocated') ?></strong></p>
            </div>
            <div>
              <?php if ($student['fees_paid']): ?>
                <span class="badge badge-success" style="padding: 8px 16px; font-size: 12px;">Fees Status: Cleared</span>
              <?php else: ?>
                <span class="badge badge-error" style="padding: 8px 16px; font-size: 12px;">Fees Status: Unpaid</span>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!$student['fees_paid']): ?>
            <!-- Lock Screen Wall -->
            <div class="glass-panel" style="padding: 50px 30px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 20px; border-left: 5px solid var(--error);">
              <div style="font-size: 40px;">🔒</div>
              <h2 style="color: var(--error);">Access Restricted</h2>
              <p style="max-width: 600px; color: var(--text-secondary); line-height: 1.6;">
                Your school fees are currently unpaid. Please complete fee payments in USD or ZiG at the Bursar to unlock access to subjects, grades, and teacher logs.
              </p>
              <div style="background: var(--bg-input); padding: 15px 25px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <p style="font-size: 14px;">Payments must be handled at the Bursar counter. Once checked, the Bursar will mark your account as paid.</p>
              </div>
              <button onclick="toggleAiChat()" class="btn btn-primary">Ask School AI for Help</button>
            </div>
          <?php else: ?>
            <!-- Paid student Dashboard: Grades list -->
            <div class="glass-panel" style="padding: 30px;">
              <h3 style="margin-bottom: 20px;">Your Academic Report (Current Term)</h3>
              <div class="grid-3">
                <?php
                  $gStmt = $pdo->prepare("SELECT g.subject, g.marks, g.term, t.name as teacher_name FROM grades g LEFT JOIN teachers t ON g.teacher_id = t.id WHERE g.student_id = ?");
                  $gStmt->execute([$userId]);
                  $grades = $gStmt->fetchAll();
                ?>
                <?php if (empty($grades)): ?>
                  <p style="color: var(--text-muted); grid-column: span 3;">No marks recorded by teachers yet.</p>
                <?php else: ?>
                  <?php foreach ($grades as $g): ?>
                    <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; flex-direction: column; gap: 8px;">
                      <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($g['term']) ?> • Teacher: <?= htmlspecialchars($g['teacher_name'] ?? 'N/A') ?></span>
                      <h4 style="font-size: 18px;"><?= htmlspecialchars($g['subject']) ?></h4>
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                        <span style="font-size: 24px; font-weight: 800; color: <?= $g['marks'] >= 50 ? 'var(--success)' : 'var(--error)' ?>;">
                          <?= $g['marks'] ?>%
                        </span>
                        <span style="font-size: 11px; background: <?= $g['marks'] >= 50 ? 'var(--success-glow)' : 'var(--error-glow)' ?>; padding: 4px 10px; border-radius: 10px;">
                          <?= $g['marks'] >= 75 ? 'Excellent' : ($g['marks'] >= 50 ? 'Pass' : 'Fail') ?>
                        </span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <!-- ---------------------------------------------------- -->
    <!-- 2. PARENT VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php elseif ($userRole === 'parent'): ?>
      <?php
        $stmt = $pdo->prepare("SELECT s.id, s.name, s.enrollment_status, s.fees_paid, c.name as class_name, u.username as student_username FROM students s LEFT JOIN classes c ON s.class_id = c.id JOIN users u ON s.id = u.id WHERE s.parent_id = ?");
        $stmt->execute([$userId]);
        $children = $stmt->fetchAll();
      ?>
      <div class="glass-panel" style="padding: 30px;">
        <h2>Registered Student Statuses</h2>
        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Track enrollment approval states and see default login details.</p>
        
        <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 25px;">
          <?php if (empty($children)): ?>
            <p style="color: var(--text-muted);">No student accounts found linked to your parent account.</p>
          <?php else: ?>
            <?php foreach ($children as $c): ?>
              <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                  <h3 style="font-size: 18px;"><?= htmlspecialchars($c['name']) ?></h3>
                  <p style="font-size: 13px; color: var(--text-muted); margin-top: 5px;">Class Assigned: <strong><?= htmlspecialchars($c['class_name'] ?? 'Not yet allocated') ?></strong></p>
                </div>
                
                <div style="display: flex; gap: 15px; align-items: center;">
                  <span class="badge <?= $c['enrollment_status'] === 'approved' ? 'badge-success' : ($c['enrollment_status'] === 'pending' ? 'badge-warning' : 'badge-error') ?>" style="padding: 5px 12px;">
                    <?= $c['enrollment_status'] ?>
                  </span>

                  <?php if ($c['enrollment_status'] === 'approved'): ?>
                    <div style="background: rgba(255,255,255,0.03); padding: 10px; border: 1px dashed var(--border-color); border-radius: 8px; font-size: 12px;">
                      <p style="color: var(--text-secondary)">Login Details:</p>
                      <strong>Username:</strong> <code style="color: var(--primary-hover);"><?= htmlspecialchars($c['student_username']) ?></code><br/>
                      <strong>Default Password:</strong> <code>temp1234</code>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    <!-- ---------------------------------------------------- -->
    <!-- 3. TEACHER VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php elseif ($userRole === 'teacher'): ?>
      <?php
        // Fetch teacher's classes
        $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE teacher_id = ?");
        $stmt->execute([$userId]);
        $myClasses = $stmt->fetchAll();

        // Selected class
        $selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
      ?>
      <div class="grid-sidebar">
        <!-- Classes select -->
        <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
          <h3>Your Classes</h3>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php if (empty($myClasses)): ?>
              <p style="color: var(--text-muted); font-size: 14px;">No classes assigned by admin yet.</p>
            <?php else: ?>
              <?php foreach ($myClasses as $c): ?>
                <a href="dashboard.php?class_id=<?= $c['id'] ?>" class="btn <?= $selectedClassId === $c['id'] ? 'btn-primary' : 'btn-outline' ?>" style="text-align: left; justify-content: flex-start; padding: 12px 15px; width: 100%;">
                  <?= htmlspecialchars($c['name']) ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Student Grade Manager -->
        <div class="glass-panel">
          <?php if (!$selectedClassId): ?>
            <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
              Please select a class from the list to enter student grades.
            </div>
          <?php else: ?>
            <?php
              // Fetch teacher subjects
              $tStmt = $pdo->prepare("SELECT subjects FROM teachers WHERE id = ?");
              $tStmt->execute([$userId]);
              $subjects = json_decode($tStmt->fetchColumn() ?: '[]', true);

              // Fetch students in this class
              $sStmt = $pdo->prepare("SELECT s.id, s.name, s.fees_paid FROM students s WHERE s.class_id = ? AND s.enrollment_status = 'approved'");
              $sStmt->execute([$selectedClassId]);
              $students = $sStmt->fetchAll();

              $selectedSubject = isset($_GET['subject']) ? $_GET['subject'] : ($subjects[0] ?? '');
            ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h3>Class List & Grades</h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                  <label for="sub-select">Subject:</label>
                  <select id="sub-select" onchange="window.location.href='dashboard.php?class_id=<?= $selectedClassId ?>&subject=' + this.value">
                    <?php foreach ($subjects as $sub): ?>
                      <option value="<?= htmlspecialchars($sub) ?>" <?= $selectedSubject === $sub ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <?php if (empty($students)): ?>
                <p style="color: var(--text-muted); padding: 30px 0;">No active students in this class.</p>
              <?php else: ?>
                <div style="overflow-x: auto;">
                  <table>
                    <thead>
                      <tr>
                        <th>Student Name</th>
                        <th>Fee Status</th>
                        <th>Marks (Term 1)</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students as $student): ?>
                        <?php
                          // Fetch current grade
                          $gStmt = $pdo->prepare("SELECT marks FROM grades WHERE student_id = ? AND subject = ? AND term = 'Term 1'");
                          $gStmt->execute([$student['id'], $selectedSubject]);
                          $currentMarks = $gStmt->fetchColumn();
                        ?>
                        <tr>
                          <td style="font-weight: 500;"><?= htmlspecialchars($student['name']) ?></td>
                          <td>
                            <span class="badge <?= $student['fees_paid'] ? 'badge-success' : 'badge-error' ?>">
                              <?= $student['fees_paid'] ? 'Paid' : 'Unpaid' ?>
                            </span>
                          </td>
                          <form method="POST" action="dashboard.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>">
                            <input type="hidden" name="action" value="input_grade">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <input type="hidden" name="subject" value="<?= htmlspecialchars($selectedSubject) ?>">
                            <input type="hidden" name="term" value="Term 1">
                            <td>
                              <input type="number" min="0" max="100" name="marks" value="<?= $currentMarks !== false ? $currentMarks : '' ?>" placeholder="--" style="width: 75px; padding: 6px; text-align: center; font-weight: bold;">
                              <span style="margin-left: 5px; font-size: 13px; color: var(--text-secondary);">%</span>
                            </td>
                            <td>
                              <button type="submit" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">Save</button>
                            </td>
                          </form>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    <!-- ---------------------------------------------------- -->
    <!-- 4. BURSAR VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php elseif ($userRole === 'bursar'): ?>
      <?php
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        if ($search !== '') {
            $stmt = $pdo->prepare("SELECT s.id, s.name, s.fees_paid, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.enrollment_status = 'approved' AND s.name LIKE ?");
            $stmt->execute(['%' . $search . '%']);
        } else {
            $stmt = $pdo->query("SELECT s.id, s.name, s.fees_paid, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.enrollment_status = 'approved' ORDER BY c.name, s.name");
        }
        $students = $stmt->fetchAll();
      ?>
      <div class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
          <div>
            <h2>Student Fee Status Dashboard</h2>
            <p style="color: var(--text-secondary); font-size: 14px;">Toggle paid/unpaid status for student class fees.</p>
          </div>
          <form method="GET" action="dashboard.php" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search student name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
          </form>
        </div>

        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Class</th>
                <th>Fee Status</th>
                <th style="text-align: center;">Mark Payment Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr><td colSpan="4" style="text-align: center; color: var(--text-muted);">No student records found.</td></tr>
              <?php else: ?>
                <?php foreach ($students as $student): ?>
                  <tr>
                    <td style="font-weight: 500;"><?= htmlspecialchars($student['name']) ?></td>
                    <td><?= htmlspecialchars($student['class_name'] ?? 'Unassigned') ?></td>
                    <td>
                      <span class="badge <?= $student['fees_paid'] ? 'badge-success' : 'badge-error' ?>">
                        <?= $student['fees_paid'] ? 'Cleared' : 'Outstanding' ?>
                      </span>
                    </td>
                    <td style="text-align: center;">
                      <form method="POST" action="dashboard.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">
                        <input type="hidden" name="action" value="update_fees">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <?php if ($student['fees_paid']): ?>
                          <input type="hidden" name="fees_paid" value="0">
                          <button type="submit" class="btn btn-danger" style="padding: 5px 12px; font-size: 12px;">Mark as Unpaid</button>
                        <?php else: ?>
                          <input type="hidden" name="fees_paid" value="1">
                          <button type="submit" class="btn btn-success" style="padding: 5px 12px; font-size: 12px;">Mark as Paid (USD/ZiG)</button>
                        <?php endif; ?>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ---------------------------------------------------- -->
    <!-- 5. ADMIN VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php elseif ($userRole === 'admin'): ?>
      <?php
        // Fetch counters
        $numStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE enrollment_status = 'approved'")->fetchColumn();
        $numTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
        $numPending  = $pdo->query("SELECT COUNT(*) FROM students WHERE enrollment_status = 'pending'")->fetchColumn();
        $numClasses  = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

        // Fetch teachers list
        $teachers = $pdo->query("SELECT u.id, t.name FROM users u JOIN teachers t ON u.id = t.id WHERE u.role = 'teacher'")->fetchAll();

        // Fetch classes list
        $classes = $pdo->query("SELECT c.id, c.name, t.name as teacher_name FROM classes c LEFT JOIN teachers t ON c.teacher_id = t.id")->fetchAll();

        // Fetch pending enrollments
        $pending = $pdo->query("SELECT s.id, s.name, p.username as parent_username FROM students s JOIN users p ON s.parent_id = p.id WHERE s.enrollment_status = 'pending'")->fetchAll();
      ?>
      <div style="display: flex; flex-direction: column; gap: 30px;">
        
        <!-- Metrics -->
        <div class="grid-3" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
          <div class="glass-panel" style="text-align: center; padding: 20px;">
            <span style="font-size: 14px; color: var(--text-secondary);">Active Students</span>
            <h2 style="font-size: 32px; color: var(--primary-hover); margin-top: 5px;"><?= $numStudents ?></h2>
          </div>
          <div class="glass-panel" style="text-align: center; padding: 20px;">
            <span style="font-size: 14px; color: var(--text-secondary);">Hired Teachers</span>
            <h2 style="font-size: 32px; color: var(--accent); margin-top: 5px;"><?= $numTeachers ?></h2>
          </div>
          <div class="glass-panel" style="text-align: center; padding: 20px;">
            <span style="font-size: 14px; color: var(--text-secondary);">Classes Configured</span>
            <h2 style="font-size: 32px; color: var(--success); margin-top: 5px;"><?= $numClasses ?></h2>
          </div>
          <div class="glass-panel" style="text-align: center; padding: 20px;">
            <span style="font-size: 14px; color: var(--text-secondary);">Pending Enrollments</span>
            <h2 style="font-size: 32px; color: var(--error); margin-top: 5px;"><?= $numPending ?></h2>
          </div>
        </div>

        <!-- Enrollment applications list -->
        <div class="glass-panel">
          <h3>Pending Enrollment Applications</h3>
          <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
            <?php if (empty($pending)): ?>
              <p style="color: var(--text-muted); padding: 10px 0;">No pending applications.</p>
            <?php else: ?>
              <?php foreach ($pending as $app): ?>
                <div style="padding: 15px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                  <div>
                    <strong>Student: <?= htmlspecialchars($app['name']) ?></strong><br/>
                    <span style="font-size: 12px; color: var(--text-secondary);">Parent Account: <?= htmlspecialchars($app['parent_username']) ?></span>
                  </div>
                  <form method="POST" action="dashboard.php" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="action" value="enrollment_action">
                    <input type="hidden" name="student_id" value="<?= $app['id'] ?>">
                    
                    <label for="cls-<?= $app['id'] ?>">Assign Class:</label>
                    <select name="class_id" id="cls-<?= $app['id'] ?>" required style="padding: 6px;">
                      <option value="">-- Select Class --</option>
                      <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <button type="submit" name="decision" value="approve" class="btn btn-success" style="padding: 6px 12px;">Approve</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-danger" style="padding: 6px 12px;">Reject</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Actions Forms -->
        <div class="grid-2">
          
          <!-- Create Class -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Create Class</h3>
            <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 12px;">
              <input type="hidden" name="action" value="create_class">
              <div class="form-group">
                <label for="cname">Class Name</label>
                <input type="text" name="name" id="cname" required placeholder="e.g. Form 4B">
              </div>
              <div class="form-group">
                <label for="cteacher">Assign Class Teacher</label>
                <select name="teacher_id" id="cteacher">
                  <option value="">-- Select Teacher (Optional) --</option>
                  <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Create Class</button>
            </form>
          </div>

          <!-- Hire Teacher -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Hire Teacher</h3>
            <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 12px;">
              <input type="hidden" name="action" value="hire_teacher">
              <div class="form-group">
                <label for="tname">Teacher Full Name</label>
                <input type="text" name="name" id="tname" required placeholder="e.g. Melissa Dube">
              </div>
              <div class="form-group">
                <label for="tusername">Create Username</label>
                <input type="text" name="username" id="tusername" required placeholder="e.g. mdube">
              </div>
              <div class="form-group">
                <label for="tpassword">Create Password</label>
                <input type="password" name="password" id="tpassword" required placeholder="••••••••">
              </div>
              <div class="form-group">
                <label for="tsubjects">Subjects Taught (comma separated)</label>
                <input type="text" name="subjects" id="tsubjects" placeholder="Mathematics, Biology">
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Hire Teacher</button>
            </form>
          </div>

          <!-- Post Event -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Post School Event</h3>
            <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 12px;">
              <input type="hidden" name="action" value="post_event">
              <div class="form-group">
                <label for="etitle">Event Title</label>
                <input type="text" name="title" id="etitle" required placeholder="e.g. Orientation Day">
              </div>
              <div class="form-group">
                <label for="edesc">Description</label>
                <textarea name="description" id="edesc" placeholder="Details of the event..." style="min-height: 60px; resize: vertical;"></textarea>
              </div>
              <div class="form-group">
                <label for="edate">Event Date</label>
                <input type="date" name="event_date" id="edate" required>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Post Event</button>
            </form>
          </div>

          <!-- Manual Student Enroller -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Enroll Student Manually</h3>
            <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 12px;">
              <input type="hidden" name="action" value="enroll_student_manual">
              <div class="form-group">
                <label for="sname">Student Name</label>
                <input type="text" name="name" id="sname" required placeholder="e.g. John Smith">
              </div>
              <div class="form-group">
                <label for="susername">Username</label>
                <input type="text" name="username" id="susername" required placeholder="e.g. jsmith">
              </div>
              <div class="form-group">
                <label for="spassword">Password</label>
                <input type="password" name="password" id="spassword" required placeholder="••••••••">
              </div>
              <div class="form-group">
                <label for="sclass">Assign Class</label>
                <select name="class_id" id="sclass" required>
                  <option value="">-- Select Class --</option>
                  <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Enroll Student</button>
            </form>
          </div>

        </div>

      </div>

    <!-- ---------------------------------------------------- -->
    <!-- 6. IT HELPDESK VIEW -->
    <!-- ---------------------------------------------------- -->
    <?php elseif ($userRole === 'helpdesk'): ?>
      <?php
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        if ($search !== '') {
            $stmt = $pdo->prepare("SELECT id, username, role, status FROM users WHERE username LIKE ? OR role LIKE ?");
            $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        } else {
            $stmt = $pdo->query("SELECT id, username, role, status FROM users ORDER BY role, username");
        }
        $users = $stmt->fetchAll();

        // Fetch logs
        $logs = $pdo->query("SELECT a.id, u.username, u.role, a.action, a.timestamp, a.ip_address FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.timestamp DESC LIMIT 100")->fetchAll();

        // Fetch tickets
        $tickets = $pdo->query("SELECT t.id, t.message, t.status, t.response, t.created_at, u.username, u.role FROM help_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();
      ?>
      <div style="display: flex; flex-direction: column; gap: 30px;">
        
        <!-- User account list -->
        <div class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
              <h3>Manage User Access Credentials</h3>
              <p style="color: var(--text-secondary); font-size: 13px; margin-top: 5px;">Reset passwords, lock accounts, or update database credentials.</p>
            </div>
            <form method="GET" action="dashboard.php" style="display: flex; gap: 10px;">
              <input type="text" name="search" placeholder="Search username/role..." value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </form>
          </div>

          <div style="overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Lock Action</th>
                  <th>Reset Password</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td style="font-weight: 500;"><?= htmlspecialchars($u['username']) ?></td>
                    <td style="text-transform: capitalize;"><?= htmlspecialchars($u['role']) ?></td>
                    <td>
                      <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-error' ?>">
                        <?= htmlspecialchars($u['status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($u['username'] !== 'helpdesk'): ?>
                        <form method="POST" action="dashboard.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">
                          <input type="hidden" name="action" value="toggle_lock">
                          <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                          <?php if ($u['status'] === 'active'): ?>
                            <input type="hidden" name="status" value="locked">
                            <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Lock Account</button>
                          <?php else: ?>
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Unlock</button>
                          <?php endif; ?>
                        </form>
                      <?php else: ?>
                        --
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="POST" action="dashboard.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>" style="display: flex; gap: 5px;">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                        <input type="text" name="new_password" placeholder="New Password" required style="padding: 4px 8px; font-size: 12px; width: 120px;">
                        <button type="submit" class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;">Reset</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tickets & Audit Logs -->
        <div class="grid-2">
          
          <!-- Tickets Queue -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Escalated Support Requests</h3>
            <div style="display: flex; flex-direction: column; gap: 15px; max-height: 400px; overflow-y: auto; padding-right: 5px;">
              <?php if (empty($tickets)): ?>
                <p style="color: var(--text-muted);">No support requests logged.</p>
              <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                  <div style="padding: 15px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary);">
                      <span>From: <strong><?= htmlspecialchars($t['username']) ?></strong> (<?= htmlspecialchars($t['role']) ?>)</span>
                      <span class="badge <?= $t['status'] === 'open' ? 'badge-warning' : ($t['status'] === 'resolved' ? 'badge-success' : 'badge-error') ?>" style="font-size: 9px; padding: 2px 6px;">
                        <?= $t['status'] ?>
                      </span>
                    </div>
                    <p style="font-size: 14px;">"<?= htmlspecialchars($t['message']) ?>"</p>
                    
                    <?php if ($t['response']): ?>
                      <p style="font-size: 13px; border-top: 1px solid rgba(255,255,255,0.03); padding-top: 8px; color: var(--text-muted);">
                        <strong>Reply:</strong> <?= htmlspecialchars($t['response']) ?>
                      </p>
                    <?php endif; ?>

                    <?php if ($t['status'] === 'open'): ?>
                      <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
                        <input type="hidden" name="action" value="resolve_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                        <input type="text" name="response" placeholder="Response text..." required style="padding: 6px; font-size: 12px;">
                        <div style="display: flex; gap: 5px;">
                          <button type="submit" name="status" value="resolved" class="btn btn-success" style="padding: 5px; font-size: 12px; flex: 1;">Resolve</button>
                          <button type="submit" name="status" value="escalated" class="btn btn-danger" style="padding: 5px; font-size: 12px; flex: 1;">Escalate to Prof IT Co.</button>
                        </div>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Transaction Audit Logs -->
          <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>Security Audit Log (Recent 100 Transactions)</h3>
            <div style="display: flex; flex-direction: column; gap: 10px; max-height: 400px; overflow-y: auto; padding-right: 5px;">
              <?php foreach ($logs as $log): ?>
                <div style="padding: 10px 15px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                  <div>
                    <strong style="color: var(--primary-hover);"><?= htmlspecialchars($log['username'] ?? 'System') ?></strong> (<?= htmlspecialchars($log['role'] ?? 'Guest') ?>)<br/>
                    <span style="color: var(--text-secondary);"><?= htmlspecialchars($log['action']) ?></span>
                  </div>
                  <div style="text-align: right; font-size: 11px; color: var(--text-muted);">
                    <?= date('H:i:s', strtotime($log['timestamp'])) ?><br/>
                    <?= htmlspecialchars($log['ip_address']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>

      </div>
    <?php endif; ?>

    <!-- ---------------------------------------------------- -->
    <!-- COMMON UTILITIES PANELS (FOR ALL LOGGED-IN USERS) -->
    <!-- ---------------------------------------------------- -->
    <div class="glass-panel" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; align-items: start; margin-top: 10px;">
      
      <!-- Change Password -->
      <div>
        <h3>Update Account Settings</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-top: 5px;">Change your personal account credentials at any time.</p>
        <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
          <input type="hidden" name="action" value="change_password">
          <input type="password" name="currentPassword" placeholder="Current Password" required>
          <input type="password" name="newPassword" placeholder="New Password" required>
          <button type="submit" class="btn btn-primary" style="padding: 10px;">Change Password</button>
        </form>
      </div>

      <!-- Submit IT Ticket -->
      <div style="border-left: 1px solid var(--border-color); padding-left: 20px;">
        <h3>Escalate Issue to IT Helpdesk</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-top: 5px;">If the AI assistant was unable to resolve your issue, write a ticket to the Helpdesk.</p>
        <form method="POST" action="dashboard.php" style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
          <input type="hidden" name="action" value="submit_ticket">
          <input type="text" name="message" placeholder="Describe your issue details here..." required>
          <button type="submit" class="btn btn-outline" style="padding: 10px;">Submit Ticket</button>
        </form>
      </div>

    </div>

  </main>

  <!-- Footer -->
  <footer>
    <div class="footer-container">
      © 2026 Tswayi High School • Built by NUST Computer Science (Mphathisi Ndlovu, Melissa Dube, Daphne Ncube)
    </div>
  </footer>

  <!-- Floating AI Assistant Widget -->
  <button class="ai-bubble" onclick="toggleAiChat()">💬</button>

  <div class="ai-panel" id="ai-chat-panel" style="display: none;">
    <div class="ai-header">
      <div>
        <h3 style="font-size: 15px; display: flex; align-items: center; gap: 6px;">
          <span style="width: 8px; height: 8px; background-color: var(--success); border-radius: 50%; display: inline-block;"></span>
          cportal AI Assistant
        </h3>
        <span style="font-size: 10px; color: var(--text-secondary);">Powered by Groq Cloud APIs</span>
      </div>
      <button onclick="toggleAiChat()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px;">✕</button>
    </div>
    
    <div class="ai-body" id="ai-messages-container">
      <div class="ai-msg ai-msg-assistant">
        Hello! I am the cportal AI. How can I help you today in your dashboard?
      </div>
    </div>

    <form class="ai-input-form" onsubmit="sendAiMessage(event)">
      <input type="text" id="ai-chat-input" placeholder="Ask AI for clarification..." required autocomplete="off">
      <button type="submit" class="ai-send-btn">➔</button>
    </form>
  </div>

  <script>
    function toggleAiChat() {
      const panel = document.getElementById('ai-chat-panel');
      panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
      scrollToBottom();
    }

    function scrollToBottom() {
      const container = document.getElementById('ai-messages-container');
      container.scrollTop = container.scrollHeight;
    }

    function sendAiMessage(e) {
      e.preventDefault();
      const input = document.getElementById('ai-chat-input');
      const text = input.value.trim();
      if (!text) return;

      input.value = '';
      appendMessage('user', text);

      // Append typing indicator
      const container = document.getElementById('ai-messages-container');
      const typingDiv = document.createElement('div');
      typingDiv.className = 'ai-msg ai-msg-assistant typing-indicator';
      typingDiv.innerText = 'Thinking...';
      container.appendChild(typingDiv);
      scrollToBottom();

      fetch('ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      })
      .then(res => res.json())
      .then(data => {
        container.removeChild(typingDiv);
        appendMessage('assistant', data.reply);
      })
      .catch(err => {
        container.removeChild(typingDiv);
        appendMessage('assistant', 'Sorry, I encountered an error communicating with the server.');
      });
    }

    function appendMessage(sender, text) {
      const container = document.getElementById('ai-messages-container');
      const msgDiv = document.createElement('div');
      msgDiv.className = `ai-msg ai-msg-${sender}`;
      msgDiv.innerText = text;
      container.appendChild(msgDiv);
      scrollToBottom();
    }
  </script>

</body>
</html>
