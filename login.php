<?php
session_start();
require_once 'db.php';

$error = '';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['status'] === 'locked') {
                    $error = 'Your account is locked. Please contact the IT Helpdesk.';
                } else if (password_verify($password, $user['password_hash'])) {
                    // Start Session
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ];

                    // Write Audit Log
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], "Logged in successfully as " . $user['role'], $ip]);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - cportal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body style="justify-content: center; align-items: center; padding: 20px;">

  <div class="glass-panel" style="width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 20px;">
    <div style="text-align: center;">
      <div style="width: 50px; height: 50px; background-color: var(--primary); border-radius: 50%; display: inline-flex; justify-content: center; align-items: center; font-weight: bold; font-size: 24px; box-shadow: 0 0 10px var(--primary-glow); margin-bottom: 15px;">C</div>
      <h2>cportal Login</h2>
      <p style="color: var(--text-secondary); font-size: 13px; margin-top: 5px;">Enter your credentials to access your dashboard.</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" style="display: flex; flex-direction: column; gap: 15px;">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" required placeholder="e.g. dncube" autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required placeholder="••••••••" autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="padding: 12px; margin-top: 10px;">Sign In</button>
    </form>

    <div style="text-align: center; font-size: 14px; color: var(--text-secondary); margin-top: 10px;">
      Don't have an account? <a href="register.php" style="font-weight: 600;">Apply here</a>
    </div>
    <div style="text-align: center; font-size: 13px; color: var(--text-muted);">
      <a href="index.php">← Back to Homepage</a>
    </div>
  </div>

</body>
</html>
