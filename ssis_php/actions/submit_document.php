<?php
/**
 * actions/submit_document.php
 * ---------------------------------------------------------------------
 * Handles the "Request a Document" form from the Document tab.
 * Inserts one row into DocumentRequests, then redirects back to the
 * portal with a flash message and the Document tab pre-selected.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';
require_role(['Student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?tab=document');
    exit;
}

$studentId = $_SESSION['student_id'];
$docType   = $_POST['docType'] ?? '';
$purpose   = trim($_POST['purpose'] ?? '');
$copies    = (int)($_POST['copies'] ?? 1);
$delivery  = $_POST['delivery'] ?? 'pickup';

$validTypes = ['TOR', 'COR', 'Certification'];
// The form's "COE" (Certificate of Enrollment) option maps onto the
// schema's generic 'Certification' docType, with the specific kind kept
// in the purpose/remarks text.
if ($docType === 'COE') {
    $docType = 'Certification';
    $purpose = 'Certificate of Enrollment - ' . $purpose;
}

if (!in_array($docType, $validTypes, true) || $purpose === '' || $copies < 1) {
    $_SESSION['flash_error'] = 'Please fill in all required fields before submitting a document request.';
    header('Location: ../index.php?tab=document');
    exit;
}

// Copies and delivery method aren't columns in the current schema, so we
// fold them into the purpose text rather than silently dropping them.
$purposeWithDetails = $purpose . ' (Copies: ' . $copies . ', Delivery: ' . ($delivery === 'email' ? 'Email' : 'Pick-up') . ')';

$stmt = $pdo->prepare(
    'INSERT INTO DocumentRequests (studentID, docType, purpose, status)
     VALUES (:studentID, :docType, :purpose, :status)'
);
$stmt->execute([
    'studentID' => $studentId,
    'docType'   => $docType,
    'purpose'   => substr($purposeWithDetails, 0, 60),
    'status'    => 'Pending Payment',
]);

$_SESSION['flash_success'] = 'Document request submitted. Please settle the fee at the Cashier.';
header('Location: ../index.php?tab=document');
exit;
