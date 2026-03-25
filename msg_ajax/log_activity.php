<?php
/**
 * Log an admin action to the activity_log table.
 * Usage: require_once 'msg_ajax/log_activity.php'; logActivity($conn, $admin_id, $admin_name, 'Approve Claim', 'Approved claim for "Wallet"');
 */
function logActivity($conn, $admin_id, $admin_name, $action, $details = '')
{
    $admin_id = intval($admin_id);
    $admin_name = mysqli_real_escape_string($conn, $admin_name);
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $now = date('Y-m-d H:i:s');
    mysqli_query($conn, "INSERT INTO activity_log (admin_id, admin_name, action, details, created_at)
                         VALUES ($admin_id, '$admin_name', '$action', '$details', '$now')");
}
?>