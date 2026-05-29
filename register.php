<?php
/**
 * File: register.php
 * Description: The enrollment application form for new students and their parents. Creates a pending student profile and links it to a new parent account.
 * Importance: Vital for public user engagement and onboarding new learners into the school ecosystem.
 */

session_start();
require_once 'db.php';

$error = '';
$success = '';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentName     = isset($_POST['parentName']) ? trim($_POST['parentName']) : '';
    $parentUsername = isset($_POST['parentUsername']) ? trim($_POST['parentUsername']) : '';
    $parentPassword = isset($_POST['parentPassword']) ? trim($_POST['parentPassword']) : '';
    $studentName    = isset($_POST['studentName']) ? trim($_POST['studentName']) : '';

    if (empty($parentName) || empty($parentUsername) || empty($parentPassword) || empty($studentName)) {
        $error = 'All fields are required to submit an application.';
    } else {
        try {
            $pdo->beginTransaction();

            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$parentUsername]);
            if ($stmt->fetch()) {
                $error = 'Username already in use. Please select a different one.';
                $pdo->rollBack();
            } else {
                // Insert Parent User
                $parentHash = password_hash($parentPassword, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'parent')");
                $uStmt->execute([$parentUsername, $parentHash]);
                $parentId = $pdo->lastInsertId();

                // Generate Student Credentials
                $cleanName = strtolower(preg_replace('/\s+/', '', $studentName));
                $studentUsername = 's_' . $cleanName . rand(100, 999);
                $studentHash = password_hash('temp1234', PASSWORD_BCRYPT); // Starting password

                // Insert Student User
                $uStmt->execute([$studentUsername, $studentHash]);
                $studentId = $pdo->lastInsertId();

                // Insert Student details
                $sStmt = $pdo->prepare("INSERT INTO students (id, name, class_id, fees_paid, parent_id, enrollment_status) VALUES (?, ?, NULL, 0, ?, 'pending')");
                $sStmt->execute([$studentId, $studentName, $parentId]);

                // Log audit action
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([$parentId, "Applied for enrollment of student: " . $studentName, $ip]);

                $pdo->commit();
                $success = "Application submitted successfully! Username assigned: " . htmlspecialchars($parentUsername) . ". Please sign in to track progress.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to submit application. Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Enrollment Application - cportal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script>
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-mode');
    }
  </script>
</head>
<body style="justify-content: center; align-items: center; padding: 40px 20px;">

  <div class="glass-panel" style="width: 100%; max-width: 500px; display: flex; flex-direction: column; gap: 20px;">
    <div>
      <h2>Online Enrollment Application</h2>
      <p style="color: var(--text-secondary); font-size: 13px; margin-top: 5px;">
        Submit details to apply for school enrollment. You will create a parent account to track the approval status.
      </p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <script>setTimeout(function(){ window.location.href = 'login.php'; }, 3500);</script>
    <?php endif; ?>

    <form method="POST" action="register.php" style="display: flex; flex-direction: column; gap: 15px;">
      <div class="form-group">
        <label for="parentName">Parent's Full Name</label>
        <input type="text" name="parentName" id="parentName" required placeholder="e.g. John Ncube" value="<?= htmlspecialchars($_POST['parentName'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="parentUsername">Choose a Parent Username</label>
        <input type="text" name="parentUsername" id="parentUsername" required placeholder="e.g. parent_jncube" value="<?= htmlspecialchars($_POST['parentUsername'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="parentPassword">Create Password</label>
        <input type="password" name="parentPassword" id="parentPassword" required placeholder="••••••••">
      </div>
      
      <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 5px 0;">
      
      <div class="form-group">
        <label for="studentName">Student's Full Name</label>
        <input type="text" name="studentName" id="studentName" required placeholder="e.g. Daphne Ncube" value="<?= htmlspecialchars($_POST['studentName'] ?? '') ?>">
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 12px; margin-top: 10px;">Submit Application</button>
    </form>

    <div style="text-align: center; font-size: 14px; color: var(--text-secondary); margin-top: 10px;">
      Already applied? <a href="login.php" style="font-weight: 600;">Sign In</a>
    </div>
    <div style="text-align: center; font-size: 13px; color: var(--text-muted);">
      <a href="index.php">← Back to Homepage</a>
    </div>
  </div>

  <script src="script.js"></script>
</body>
<!-- Future Improvements: Add real-time field validation and email notification alerts for parents upon enrollment status updates. -->
</html>
