<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------
 * Small helpers reused across pages. Nothing here touches the database.
 * ---------------------------------------------------------------------
 */

/** Escape a value for safe HTML output. */
function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Format a number as Philippine peso, e.g. peso(1500) -> "₱1,500.00". */
function peso(?float $amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

/** Format a DATETIME/ DATE column into something readable. */
function friendly_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) {
        return '--';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '--';
}

/**
 * Reads a one-time flash message out of the session (used to show a
 * status banner right after a redirect, e.g. after submitting a form).
 */
function flash(string $key): ?string
{
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/** Maps a status string to one of the two badge CSS classes already in style.css. */
function status_badge_class(string $status): string
{
    $positive = ['Approved', 'Signed', 'Passed', 'Ready', 'Completed', 'Paid', 'Active'];
    return in_array($status, $positive, true) ? 'badge-success' : 'badge-warning';
}
