<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------------
 * Session bootstrapping + a small guard function. Include this before
 * config.php on any page that needs to know who is logged in.
 * ---------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Stops the page and bounces the visitor to the login screen if nobody
 * is signed in. Call this at the top of every protected page.
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * True when a session exists AND belongs to one of the given roles.
 * Usage: require_role(['Student']);
 */
function require_role(array $allowedRoles): void
{
    require_login();
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}
