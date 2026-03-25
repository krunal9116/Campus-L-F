<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

$admin_query = "SELECT * FROM users WHERE username = '$username'";
$admin_result = mysqli_query($conn, $admin_query);
$admin_data = mysqli_fetch_assoc($admin_result);
if (!$admin_data) { session_destroy(); header("Location: index.php"); exit(); }
$admin_id = $admin_data['id'];
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $claim_id = intval($_POST['claim_id']);
    $action = $_POST['action'];
    $item_id = intval($_POST['item_id']);

    require_once 'msg_ajax/create_admin_msg.php';
    require_once 'msg_ajax/send_claim_email.php';
    require_once 'msg_ajax/log_activity.php';

    // Fetch claim + item + user email info (claimant + founder)
    $claim_info = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT cl.user_id, i.item_name, i.user_id AS founder_id,
                u.username AS claimer_name, u.email AS claimer_email,
                f.username AS founder_name, f.email AS founder_email
         FROM claims cl 
         JOIN items i ON cl.item_id = i.id 
         JOIN users u ON cl.user_id = u.id
         LEFT JOIN users f ON i.user_id = f.id
         WHERE cl.id = $claim_id LIMIT 1"
    ));

    $meeting_dt = isset($_POST['meeting_datetime']) ? trim($_POST['meeting_datetime']) : '';
    $meeting_label = '';
    if ($meeting_dt) {
        $meeting_label = date('d M Y \a\t h:i A', strtotime($meeting_dt));
    }

    if ($action === 'approve') {
        $meeting_dt_safe = $meeting_dt ? mysqli_real_escape_string($conn, $meeting_dt) : null;
        mysqli_query($conn, "UPDATE claims SET status = 'approved', approved_by = '$admin_id'" . ($meeting_dt_safe ? ", meeting_datetime = '$meeting_dt_safe'" : "") . " WHERE id = $claim_id");
        mysqli_query($conn, "UPDATE claims SET status = 'rejected' WHERE item_id = $item_id AND id != $claim_id");
        mysqli_query($conn, "UPDATE items SET report_type = 'received' WHERE id = $item_id");

        if ($claim_info) {
            $meeting_line = $meeting_label ? " Please come to the admin office on <strong>$meeting_label</strong>." : " Please visit the admin office to collect your item.";
            $meeting_plain = $meeting_label ? " Please come to the admin office on $meeting_label." : " Please visit the admin office.";

            // --- Message + email to CLAIMANT ---
            $msg_claimer = "✅ Your claim for \"{$claim_info['item_name']}\" has been APPROVED by the admin.{$meeting_plain}";
            createAdminChat($conn, $admin_id, $claim_info['user_id'], $item_id, $msg_claimer);

            $emailBodyClaimer = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:12px;">
                <h2 style="color:#15803d;">Claim Approved - Campus Find</h2>
                <p>Hello <strong>' . htmlspecialchars($claim_info['claimer_name']) . '</strong>,</p>
                <p>Great news! Your claim for the item <strong>"' . htmlspecialchars($claim_info['item_name']) . '"</strong> has been <span style="color:#15803d;font-weight:bold;">APPROVED</span> by the admin.</p>
                ' . ($meeting_label ? '<div style="background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;">You are requested to visit the <strong>admin office</strong> on <strong>' . $meeting_label . '</strong> with valid identification to collect your item.</div>' : '<div style="background:#d4edda;padding:15px;border-radius:8px;margin:20px 0;">Please visit the <strong>admin office</strong> with valid identification to collect your item.</div>') . '
                <p style="color:#999;font-size:12px;">Campus Find — Lost &amp; Found Management System</p>
                <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>';
            sendClaimEmail($claim_info['claimer_email'], $claim_info['claimer_name'], 'Your Claim Has Been Approved - Campus Find', $emailBodyClaimer);

            // --- Message + email to FOUNDER ---
            if (!empty($claim_info['founder_id']) && $claim_info['founder_id'] != $claim_info['user_id']) {
                $msg_founder = "📦 The item \"{$claim_info['item_name']}\" you reported found has been claimed and approved by the admin.{$meeting_plain} Please bring the item to the admin office at the specified time.";
                createAdminChat($conn, $admin_id, $claim_info['founder_id'], $item_id, $msg_founder);

                if (!empty($claim_info['founder_email'])) {
                    $emailBodyFounder = '
                    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:12px;">
                        <h2 style="color:#1d4ed8;">Item Claim Approved - Action Required</h2>
                        <p>Hello <strong>' . htmlspecialchars($claim_info['founder_name']) . '</strong>,</p>
                        <p>The item <strong>"' . htmlspecialchars($claim_info['item_name']) . '"</strong> you reported found has been <strong>claimed and approved</strong> by the admin.</p>
                        ' . ($meeting_label ? '<div style="background:#dbeafe;padding:15px;border-radius:8px;margin:20px 0;">Please bring the item to the <strong>admin office</strong> on <strong>' . $meeting_label . '</strong> so it can be handed over to the claimant.</div>' : '<div style="background:#dbeafe;padding:15px;border-radius:8px;margin:20px 0;">Please bring the item to the <strong>admin office</strong> as soon as possible.</div>') . '
                        <p style="color:#999;font-size:12px;">Campus Find — Lost &amp; Found Management System</p>
                        <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
                    </div>';
                    sendClaimEmail($claim_info['founder_email'], $claim_info['founder_name'], 'Item Claim Approved - Please Bring Item to Admin Office', $emailBodyFounder);
                }
            }
        }

        // Notify rejected claimants
        $rejected_claimants = mysqli_query(
            $conn,
            "SELECT cl.user_id, u.username, u.email FROM claims cl 
             JOIN users u ON cl.user_id = u.id
             WHERE cl.item_id = $item_id AND cl.id != $claim_id AND cl.status = 'rejected'"
        );
        while ($rc = mysqli_fetch_assoc($rejected_claimants)) {
            $msg_r = "❌ Your claim for \"" . $claim_info['item_name'] . "\" has been REJECTED. The item was awarded to another claimant after verification.";
            createAdminChat($conn, $admin_id, $rc['user_id'], $item_id, $msg_r);

            $emailBodyR = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:12px;">
                <h2 style="color:#dc2626;">Claim Rejected - Campus Find</h2>
                <p>Hello <strong>' . htmlspecialchars($rc['username']) . '</strong>,</p>
                <p>We regret to inform you that your claim for <strong>"' . htmlspecialchars($claim_info['item_name']) . '"</strong> has been <span style="color:#dc2626;font-weight:bold;">REJECTED</span>.</p>
                <p>The item was awarded to another claimant after admin verification.</p>
                <p style="color:#999;font-size:12px;">Campus Find — Lost &amp; Found Management System</p>
                <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>';
            sendClaimEmail($rc['email'], $rc['username'], 'Your Claim Has Been Rejected - Campus Find', $emailBodyR);
        }

        // Log activity
        logActivity(
            $conn,
            $admin_id,
            $username,
            'Approve Claim',
            "Approved claim #$claim_id for item \"{$claim_info['item_name']}\" (claimant: {$claim_info['claimer_name']})" . ($meeting_label ? ", meeting: $meeting_label" : "")
        );

    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE claims SET status = 'rejected' WHERE id = $claim_id");

        if ($claim_info) {
            $msg = "❌ Your claim for \"" . $claim_info['item_name'] . "\" has been REJECTED. If you believe this is a mistake, please contact the admin.";
            createAdminChat($conn, $admin_id, $claim_info['user_id'], $item_id, $msg);

            $emailBody = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:12px;">
                <h2 style="color:#dc2626;">Claim Rejected - Campus Find</h2>
                <p>Hello <strong>' . htmlspecialchars($claim_info['claimer_name']) . '</strong>,</p>
                <p>Your claim for <strong>"' . htmlspecialchars($claim_info['item_name']) . '"</strong> has been <span style="color:#dc2626;font-weight:bold;">REJECTED</span>.</p>
                <p>If you believe this is a mistake, please contact the admin through the chat.</p>
                <p style="color:#999;font-size:12px;">Campus Find — Lost &amp; Found Management System</p>
                <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>';
            sendClaimEmail($claim_info['claimer_email'], $claim_info['claimer_name'], 'Your Claim Has Been Rejected - Campus Find', $emailBody);
        }
        logActivity(
            $conn,
            $admin_id,
            $username,
            'Reject Claim',
            "Rejected claim #$claim_id for item \"" . ($claim_info['item_name'] ?? 'Unknown') . "\" (claimant: " . ($claim_info['claimer_name'] ?? 'Unknown') . ")"
        );
    }
    header("Location: admin_claims.php");
    exit();
}

// Filter + Search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed))
    $filter = 'pending';

// Use PHP time for countdown comparison to match how countdown_end was stored (PHP timezone)
$php_now = date('Y-m-d H:i:s');

if ($filter === 'pending') {
    $base_where = "cl.status = 'pending' AND cl.countdown_end <= '$php_now'";
} elseif ($filter === 'all') {
    $base_where = "(cl.status != 'pending' OR cl.countdown_end <= '$php_now')";
} else {
    $base_where = "cl.status = '$filter'";
}

$search_where = '';
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $search_where = " AND (i.item_name LIKE '%$s%' OR u.username LIKE '%$s%' OR i.category LIKE '%$s%' OR i.location LIKE '%$s%')";
}

$where = "WHERE $base_where$search_where";

// Pagination
$per_page = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$total_rows = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as c FROM claims cl JOIN items i ON cl.item_id=i.id JOIN users u ON cl.user_id=u.id $where"
))['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $per_page;

$claims_query = "SELECT cl.*, 
                        i.item_name, i.category, i.location, i.description, i.image,
                        i.report_type, i.date_reported,
                        u.username AS claimer_name, u.email AS claimer_email,
                        reporter.username AS reporter_name,
                        (SELECT COUNT(*) FROM claims WHERE item_id = cl.item_id) AS total_claims
                 FROM claims cl
                 JOIN items i ON cl.item_id = i.id
                 JOIN users u ON cl.user_id = u.id
                 LEFT JOIN users reporter ON i.user_id = reporter.id
                 $where
                 ORDER BY cl.claim_date DESC
                 LIMIT $offset, $per_page";
$claims_result = mysqli_query($conn, $claims_query);

// Counts for tabs — pending only counts timer-expired ones
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='pending' AND countdown_end <= '$php_now'"))['c'];
$count_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='approved'"))['c'];
$count_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='rejected'"))['c'];
$count_all = $count_pending + $count_approved + $count_rejected;

// Unread chat count
$unread_q = "SELECT COUNT(*) as count FROM messages m 
             JOIN conversations c ON m.conversation_id = c.id 
             WHERE (c.user1_id = '$admin_id' OR c.user2_id = '$admin_id') 
             AND m.sender_id != '$admin_id' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_q);
$unread_count = $unread_result ? mysqli_fetch_assoc($unread_result)['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="dark-mode.css">
    <script src="dark-mode.js"></script>
    <script src="page-loader.js"></script>
    <title>Manage Claims - Campus Find Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        /* ---- NAVBAR ---- */
        .navbar {
            background-color: #1a1a2e;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .navbar h1 {
            font-size: 22px;
        }

        .admin-badge {
            background-color: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            vertical-align: middle;
        }

        .back-btn {
            background-color: white;
            color: #1a1a2e;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background-color: #dde3f0;
        }

        .chat-nav-icon {
            position: relative;
            font-size: 24px;
            text-decoration: none;
            cursor: pointer;
        }

        .chat-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: white;
            color: #1a1a2e;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ---- CONTAINER ---- */
        .container {
            padding: 30px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a1a2e;
        }

        /* ---- FILTER TABS ---- */
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .tab-btn {
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #ddd;
            background: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #555;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        .tab-btn.active {
            background-color: #1a1a2e;
            color: white;
            border-color: #1a1a2e;
        }

        .tab-count {
            background-color: rgba(255, 255, 255, 0.25);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
        }

        .tab-btn:not(.active) .tab-count {
            background-color: #eee;
            color: #444;
        }

        .tab-btn.pending-tab.active {
            background-color: #d97706;
            border-color: #d97706;
        }

        .tab-btn.approved-tab.active {
            background-color: #15803d;
            border-color: #15803d;
        }

        .tab-btn.rejected-tab.active {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        /* ---- CLAIMS TABLE ---- */
        .table-wrap {
            background: white;
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #1a1a2e;
            color: white;
        }

        th {
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: #f9fafb;
        }

        /* Item image */
        .item-img {
            width: 55px;
            height: 55px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .item-img-placeholder {
            width: 55px;
            height: 55px;
            border-radius: 8px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #aaa;
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #28a745;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .badge-lost {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-found {
            background: #cce5ff;
            color: #004085;
        }

        .badge-received {
            background: #d4edda;
            color: #155724;
        }

        .multi-claim-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-top: 4px;
        }

        /* Action buttons */
        .btn-approve,
        .btn-reject {
            padding: 7px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-right: 5px;
        }

        .btn-approve {
            background-color: #15803d;
            color: white;
        }

        .btn-approve:hover {
            opacity: 0.85;
        }

        .btn-reject {
            background-color: #dc2626;
            color: white;
        }

        .btn-reject:hover {
            opacity: 0.85;
        }

        .btn-receipt {
            background-color: #0369a1;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .btn-receipt:hover {
            opacity: 0.85;
        }

        .action-done {
            font-size: 13px;
            color: #888;
            font-style: italic;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }

        .pagination a,
        .pagination span {
            padding: 7px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #ddd;
        }

        .pagination a {
            background: white;
            color: #333;
        }

        .pagination a:hover {
            background: #f0f2f5;
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        .pagination span.active {
            background: #1a1a2e;
            color: white;
            border-color: #1a1a2e;
        }

        .pagination span.disabled {
            background: #f0f0f0;
            color: #ccc;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .empty-state span {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a2e;
            color: white;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            z-index: 9999;
            display: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            th,
            td {
                padding: 10px 10px;
            }

            .btn-approve,
            .btn-reject {
                padding: 6px 10px;
                margin-bottom: 4px;
            }
        }

        /* ── Approve Modal ── */
        .approve-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 5000;
            justify-content: center;
            align-items: center;
        }
        .approve-modal-box {
            background: white;
            border-radius: 16px;
            padding: 32px;
            width: 360px;
            max-width: 95%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .approve-modal-title {
            margin: 0 0 6px;
            color: #159f35;
            font-size: 20px;
        }
        .approve-modal-desc {
            margin: 0 0 20px;
            color: #555;
            font-size: 14px;
        }
        .approve-modal-label {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 6px;
        }
        .approve-modal-input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
            margin-bottom: 20px;
            background: white;
            color: #333;
        }
        .approve-modal-cancel {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            color: #333;
        }
        .approve-modal-confirm {
            padding: 10px 20px;
            background: #159f35;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        /* Dark mode overrides */
        html.dark-mode .approve-modal-box {
            background: #1e2130;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        }
        html.dark-mode .approve-modal-title { color: #4ade80; }
        html.dark-mode .approve-modal-desc  { color: #9ca3af; }
        html.dark-mode .approve-modal-label { color: #d0d8ff; }
        html.dark-mode .approve-modal-input {
            background: #252840;
            color: #e0e0ff;
            border-color: #3d4166;
            color-scheme: dark;
        }
        html.dark-mode .approve-modal-cancel {
            background: #252840;
            color: #d0d0e0;
            border-color: #3d4166;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>Campus-Find <span class="admin-badge">ADMIN</span></h1>
        </div>
        <div class="nav-right">
            <a href="admin_messages.php" class="chat-nav-icon" title="Chat">
                💬
                <?php if ($unread_count > 0) { ?>
                    <span class="chat-badge"><?php echo $unread_count; ?></span>
                <?php } ?>
            </a>
            <a href="admin_profile.php" class="nav-avatar" title="My Profile">
                <?php if ($has_photo) { ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php } else { ?>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                <?php } ?>
            </a>
        </div>
    </div>

    <div class="container">

        <div class="page-header">
            <h2>Manage Claims</h2>
            <span style="color:#888; font-size:13px;">Total: <?php echo $count_all; ?> claim(s)</span>
        </div>

        <!-- Search -->
        <form method="GET" style="display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap;">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <input type="text" name="search" placeholder="🔍 Search by item, claimant, category, location..."
                value="<?php echo htmlspecialchars($search); ?>"
                style="flex:1; min-width:220px; padding:10px 16px; border:2px solid #ddd; border-radius:10px; font-size:14px; font-family:'Poppins',sans-serif; outline:none;">
            <button type="submit"
                style="padding:10px 22px; background:#1a1a2e; color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">Search</button>
            <?php if ($search): ?>
                <a href="?filter=<?php echo $filter; ?>"
                    style="padding:10px 18px; background:white; color:#555; border:2px solid #ddd; border-radius:10px; font-size:14px; text-decoration:none;">✕
                    Clear</a>
            <?php endif; ?>
        </form>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all&search=<?php echo urlencode($search); ?>"
                class="tab-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📋 All <span class="tab-count"><?php echo $count_all; ?></span>
            </a>
            <a href="?filter=pending&search=<?php echo urlencode($search); ?>"
                class="tab-btn pending-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                🟡 Pending <span class="tab-count"><?php echo $count_pending; ?></span>
            </a>
            <a href="?filter=approved&search=<?php echo urlencode($search); ?>"
                class="tab-btn approved-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                🟢 Approved <span class="tab-count"><?php echo $count_approved; ?></span>
            </a>
            <a href="?filter=rejected&search=<?php echo urlencode($search); ?>"
                class="tab-btn rejected-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                🔴 Rejected <span class="tab-count"><?php echo $count_rejected; ?></span>
            </a>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <?php if (mysqli_num_rows($claims_result) > 0) { ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Item Info</th>
                            <th>Claimant</th>
                            <th>Reported By</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($claims_result)):
                            $status = $row['status'];
                            $item_type = strtolower($row['report_type']);
                            $img_path = !empty($row['image']) ? 'uploads/' . $row['image'] : '';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>

                                <!-- Item image -->
                                <td>
                                    <?php if ($img_path && file_exists($img_path)): ?>
                                        <img src="<?php echo $img_path; ?>" class="item-img" alt="Item"
                                            onclick="zoomImage(this.src)" style="cursor:zoom-in;">
                                    <?php else: ?>
                                        <div class="item-img-placeholder">📦</div>
                                    <?php endif; ?>
                                </td>

                                <!-- Item details -->
                                <td>
                                    <strong><?php echo htmlspecialchars($row['item_name']); ?></strong><br>
                                    <span style="color:#888; font-size:12px;">
                                        📁 <?php echo htmlspecialchars($row['category']); ?> &nbsp;|&nbsp;
                                        📍 <?php echo htmlspecialchars($row['location']); ?>
                                    </span><br>
                                    <span class="status-badge badge-<?php echo $item_type; ?>">
                                        <?php echo ucfirst($item_type); ?>
                                    </span>
                                    <?php if ($row['total_claims'] > 1): ?>
                                        <span class="multi-claim-badge">👥 <?php echo $row['total_claims']; ?> claims</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Claimant -->
                                <td>
                                    <strong><?php echo htmlspecialchars($row['claimer_name']); ?></strong><br>
                                    <span
                                        style="color:#888; font-size:12px;"><?php echo htmlspecialchars($row['claimer_email']); ?></span>
                                </td>

                                <!-- Reporter -->
                                <td><?php echo htmlspecialchars($row['reporter_name'] ?? '—'); ?></td>

                                <!-- Claim date -->
                                <td style="white-space:nowrap;">
                                    <?php echo date('d M Y', strtotime($row['claim_date'])); ?><br>
                                    <span
                                        style="color:#888; font-size:11px;"><?php echo date('h:i A', strtotime($row['claim_date'])); ?></span>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge badge-<?php echo $status; ?>">
                                        <?php
                                        if ($status === 'pending')
                                            echo '🟡 Pending';
                                        elseif ($status === 'approved')
                                            echo '🟢 Approved';
                                        elseif ($status === 'rejected')
                                            echo '🔴 Rejected';
                                        else
                                            echo ucfirst($status);
                                        ?>
                                    </span>
                                </td>

                                <!-- Action -->
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <button type="button" class="btn-approve"
                                            onclick="openApproveModal(<?php echo $row['id']; ?>, <?php echo $row['item_id']; ?>)">✔
                                            Approve</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmAction(this, 'reject')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="claim_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-reject">✘ Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <?php if ($status === 'approved'): ?>
                                            <button type="button" class="btn-receipt" onclick="printAdminReceipt(
                                        <?php echo $row['id']; ?>,
                                        '<?php echo htmlspecialchars($row['item_name'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($row['category'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($row['location'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($row['claimer_name'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($row['claimer_email'], ENT_QUOTES); ?>',
                                        '<?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?>',
                                        '<?php echo !empty($row['meeting_datetime']) ? date('d M Y, h:i A', strtotime($row['meeting_datetime'])) : 'Not set'; ?>'
                                    )">Receipt</button>
                                        <?php else: ?>
                                            <span class="action-done">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-state">
                    <span>📭</span>
                    <p>No <?php echo $filter === 'all' ? '' : $filter; ?> claims found.</p>
                </div>
            <?php } ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $qs = '&filter=' . urlencode($filter) . ($search ? '&search=' . urlencode($search) : '');
                echo $page > 1 ? "<a href='?page=" . ($page - 1) . $qs . "'>← Prev</a>" : "<span class='disabled'>← Prev</span>";
                for ($pi = max(1, $page - 2); $pi <= min($total_pages, $page + 2); $pi++) {
                    echo $pi == $page ? "<span class='active'>$pi</span>" : "<a href='?page=$pi$qs'>$pi</a>";
                }
                echo $page < $total_pages ? "<a href='?page=" . ($page + 1) . $qs . "'>Next →</a>" : "<span class='disabled'>Next →</span>";
                ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="toast" id="toast"></div>

    <script>
        function printAdminReceipt(id, itemName, category, location, claimerName, claimerEmail, claimDate, meetingDate) {
            var win = window.open('', '_blank', 'width=620,height=720');
            win.document.write(`
                <html><head><title>Claim Receipt #CLM-${id} — Campus Find Admin</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
                    .logo { text-align: center; margin-bottom: 20px; }
                    .logo h1 { color: #1a1a2e; font-size: 26px; margin: 0; }
                    .logo p { color: #666; font-size: 12px; margin: 4px 0 0; }
                    .divider { border: 2px solid #1a1a2e; margin: 18px 0; }
                    .receipt-title { text-align: center; font-size: 18px; font-weight: bold; margin: 12px 0; }
                    .badge { text-align: center; background: #d4edda; color: #155724; padding: 8px; border-radius: 8px; font-weight: bold; margin-bottom: 18px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                    td { padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 13px; }
                    td:first-child { font-weight: bold; width: 38%; color: #555; }
                    .admin-note { background: #fff3cd; border-left: 4px solid #f59e0b; padding: 12px 16px; margin-top: 20px; font-size: 12px; color: #7c5400; border-radius: 6px; }
                    .footer { text-align: center; margin-top: 24px; color: #999; font-size: 11px; }
                    @media print { button { display: none; } }
                </style></head>
                <body>
                    <div class="logo">
                        <h1>Campus-Find</h1>
                        <p>Campus Lost &amp; Found Management System — Admin Copy</p>
                    </div>
                    <hr class="divider">
                    <div class="receipt-title">🧾 Claim Receipt — Admin Copy</div>
                    <div class="badge">✅ CLAIM APPROVED</div>
                    <table>
                        <tr><td>Receipt No.</td><td>#CLM-${id}</td></tr>
                        <tr><td>Item Name</td><td>${itemName}</td></tr>
                        <tr><td>Category</td><td>${category}</td></tr>
                        <tr><td>Location Found</td><td>${location}</td></tr>
                        <tr><td>Claimant Name</td><td>${claimerName}</td></tr>
                        <tr><td>Claimant Email</td><td>${claimerEmail}</td></tr>
                        <tr><td>Claim Date</td><td>${claimDate}</td></tr>
                        <tr><td>Meeting / Pickup</td><td>${meetingDate}</td></tr>
                        <tr><td>Status</td><td>✅ Approved</td></tr>
                    </table>
                    <div class="admin-note">
                        📋 <strong>Admin Record:</strong> Keep this receipt as proof of item handover. Verify claimant identity before releasing the item.
                    </div>
                    <div class="footer">Printed on ${new Date().toLocaleString()} — Campus Find Admin</div>
                    <br><button onclick="window.print()" style="padding:10px 24px;background:#1a1a2e;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;">🖨️ Print</button>
                </body></html>
            `);
            win.document.close();
        }

        function confirmAction(form, action) {
            var msg = action === 'approve'
                ? 'Approve this claim? All other claims for this item will be rejected.'
                : 'Reject this claim?';
            return confirm(msg);
        }

        // Show success toast if redirected after action
        <?php if (isset($_GET['done'])): ?>
        (function () {
            var t = document.getElementById('toast');
            t.textContent = '✔ Action completed successfully!';
            t.style.display = 'block';
            setTimeout(function () { t.style.display = 'none'; }, 3000);
        })();
        <?php endif; ?>
    </script>

    <?php mysqli_close($conn); ?>
    <!-- Approve Modal: pick meeting date/time -->
    <div id="approveModal" class="approve-modal-overlay">
        <div class="approve-modal-box">
            <h3 class="approve-modal-title">✔ Approve Claim</h3>
            <p class="approve-modal-desc">Select a meeting date &amp; time for the claimant and
                founder to visit the admin office.</p>
            <label class="approve-modal-label">📅 Meeting Date &amp; Time</label>
            <input type="datetime-local" id="meetingDatetime" class="approve-modal-input">
            <form method="POST" id="approveForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="claim_id" id="modalClaimId">
                <input type="hidden" name="item_id" id="modalItemId">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="meeting_datetime" id="modalMeetingDt">
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeApproveModal()" class="approve-modal-cancel">Cancel</button>
                    <button type="submit" class="approve-modal-confirm">✔ Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Zoom Overlay -->
    <div id="zoomOverlay"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;justify-content:center;align-items:center;"
        onclick="if(event.target===this)this.style.display='none'">
        <button onclick="document.getElementById('zoomOverlay').style.display='none'"
            style="position:absolute;top:18px;right:24px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;width:44px;height:44px;border-radius:50%;cursor:pointer;line-height:1;">✕</button>
        <img id="zoomImg" src=""
            style="max-width:90%;max-height:90%;border-radius:10px;box-shadow:0 0 40px rgba(0,0,0,0.5);">
    </div>
    <script>
            function zoomImage(src) { var o = document.getElementById('zoomOverlay'); document.getElementById('zoomImg').src = src; o.style.display = 'flex'; }

        function openApproveModal(claimId, itemId) {
            document.getElementById('modalClaimId').value = claimId;
            document.getElementById('modalItemId').value = itemId;
            // Default to tomorrow at 10:00 AM
            var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0);
            var pad = function (n) { return String(n).padStart(2, '0'); };
            document.getElementById('meetingDatetime').value =
                d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T10:00';
            document.getElementById('approveModal').style.display = 'flex';
        }
        function closeApproveModal() {
            document.getElementById('approveModal').style.display = 'none';
        }
        document.getElementById('approveForm').addEventListener('submit', function (e) {
            var dt = document.getElementById('meetingDatetime').value;
            if (!dt) { alert('Please select a meeting date and time.'); e.preventDefault(); return; }
            document.getElementById('modalMeetingDt').value = dt;
        });
        // Close modal on backdrop click
        document.getElementById('approveModal').addEventListener('click', function (e) {
            if (e.target === this) closeApproveModal();
        });
    </script>
</body>

</html>