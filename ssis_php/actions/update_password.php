<?php
/**
 * actions/update_password.php
 * ---------------------------------------------------------------------
 * Handles the "Change Security Password" form on the Settings tab.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?tab=settings');
    exit;
}

$userId      = $_SESSION['user_id'];
$currentPass = $_POST['current_password'] ?? '';
$newPass     = $_POST['new_password'] ?? '';

$stmt = $pdo->prepare('SELECT passwordHash FROM Users WHERE userID = :id');
$stmt->execute(['id' => $userId]);
$row = $stmt->fetch();

if (!$row || !password_verify($currentPass, $row['passwordHash'])) {
    $_SESSION['flash_error'] = 'Your current password was not entered correctly.';
} elseif (strlen($newPass) < 8) {
    $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
} else {
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE Users SET passwordHash = :hash WHERE userID = :id');
    $stmt->execute(['hash' => $newHash, 'id' => $userId]);
    $_SESSION['flash_success'] = 'Password updated successfully!';
}

header('Location: ../index.php?tab=settings');
exit;
