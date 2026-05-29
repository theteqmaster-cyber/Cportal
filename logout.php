<?php
/**
 * File: logout.php
 * Description: Clears user session data and logs the user out.
 * Importance: Essential for user security and session termination.
 */

session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;

// Future Improvements: Implement CSRF tokens on logout to prevent malicious logout attempts.
?>
