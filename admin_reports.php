<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

// Get admin data
$admin_query = "SELECT * FROM users WHERE username = '$username'";
$admin_result = mysqli_query($conn, $admin_query);
$admin_data = mysqli_fetch_assoc($admin_result);
if (!$admin_data) { session_destroy(); header("Location: index.php"); exit(); }
$admin_id = $admin_data['id'];

$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

// Unread chat count
$unread_q = "SELECT COUNT(*) as count FROM messages m 
            JOIN conversations c ON m.conversation_id = c.id 
            WHERE (c.user1_id = '$admin_id' OR c.user2_id = '$admin_id') 
            AND m.sender_id != '$admin_id' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_q);
$unread_count = $unread_result ? mysqli_fetch_assoc($unread_result)['count'] : 0;

// Date filter
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';

// Items date condition
$date_condition = "";
if (!empty($date_from) && !empty($date_to)) {
    $date_condition = " AND DATE(i.date_reported) BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $date_condition = " AND DATE(i.date_reported) >= '$date_from'";
} elseif (!empty($date_to)) {
    $date_condition = " AND DATE(i.date_reported) <= '$date_to'";
}

// Claims date condition
$claims_date_condition = "";
if (!empty($date_from) && !empty($date_to)) {
    $claims_date_condition = " AND DATE(claim_date) BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $claims_date_condition = " AND DATE(claim_date) >= '$date_from'";
} elseif (!empty($date_to)) {
    $claims_date_condition = " AND DATE(claim_date) <= '$date_to'";
}

// ========================
// EXPORT HANDLER
// ========================
if (isset($_GET['export'])) {
    $format = $_GET['export']; // 'csv' or 'pdf'

    // Fetch all items
    $exp_items = mysqli_query($conn, "SELECT i.item_name, i.category, i.location, i.report_type, i.date_reported, u.username as reporter
        FROM items i JOIN users u ON i.user_id = u.id WHERE 1=1 $date_condition ORDER BY i.date_reported DESC");

    // Fetch all claims
    $exp_claims = mysqli_query($conn, "SELECT i.item_name, u.username as claimer, u.email as claimer_email, cl.status, cl.claim_date
        FROM claims cl JOIN users u ON cl.user_id = u.id JOIN items i ON cl.item_id = i.id WHERE 1=1 $claims_date_condition ORDER BY cl.claim_date DESC");

    $date_label = (!empty($date_from) || !empty($date_to)) ? " ({$date_from} to {$date_to})" : "";

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="campus_find_report' . date('_Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['=== CAMPUS FIND - REPORT' . $date_label . ' ===']);
        fputcsv($out, []);

        // Items section
        fputcsv($out, ['ITEMS REPORT']);
        fputcsv($out, ['Item Name', 'Category', 'Location', 'Type', 'Date Reported', 'Reported By']);
        while ($row = mysqli_fetch_assoc($exp_items)) {
            fputcsv($out, [$row['item_name'], $row['category'], $row['location'], ucfirst($row['report_type']), date('d M Y', strtotime($row['date_reported'])), $row['reporter']]);
        }

        fputcsv($out, []);

        // Claims section
        fputcsv($out, ['CLAIMS REPORT']);
        fputcsv($out, ['Item Name', 'Claimed By', 'Email', 'Status', 'Claim Date']);
        while ($row = mysqli_fetch_assoc($exp_claims)) {
            fputcsv($out, [$row['item_name'], $row['claimer'], $row['claimer_email'], ucfirst($row['status']), date('d M Y', strtotime($row['claim_date']))]);
        }

        fclose($out);
        exit();
    }

    if ($format === 'pdf') {
        // Build HTML for PDF (browser print-to-PDF)
        $items_html = '';
        while ($row = mysqli_fetch_assoc($exp_items)) {
            $items_html .= '<tr>
                <td>' . htmlspecialchars($row['item_name']) . '</td>
                <td>' . htmlspecialchars($row['category']) . '</td>
                <td>' . htmlspecialchars($row['location']) . '</td>
                <td>' . ucfirst($row['report_type']) . '</td>
                <td>' . date('d M Y', strtotime($row['date_reported'])) . '</td>
                <td>' . htmlspecialchars($row['reporter']) . '</td>
            </tr>';
        }
        $claims_html = '';
        while ($row = mysqli_fetch_assoc($exp_claims)) {
            $claims_html .= '<tr>
                <td>' . htmlspecialchars($row['item_name']) . '</td>
                <td>' . htmlspecialchars($row['claimer']) . '</td>
                <td>' . htmlspecialchars($row['claimer_email']) . '</td>
                <td>' . ucfirst($row['status']) . '</td>
                <td>' . date('d M Y', strtotime($row['claim_date'])) . '</td>
            </tr>';
        }
        mysqli_close($conn);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Campus Find Report</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 30px; color: #333; }
            h1 { color: #1a1a2e; font-size: 22px; margin-bottom: 5px; }
            .sub { color: #666; font-size: 13px; margin-bottom: 25px; }
            h2 { font-size: 16px; color: #1a1a2e; border-bottom: 2px solid #1a1a2e; padding-bottom: 5px; margin: 25px 0 12px; }
            table { width: 100%; border-collapse: collapse; font-size: 13px; }
            th { background: #1a1a2e; color: white; padding: 9px 12px; text-align: left; }
            td { padding: 8px 12px; border-bottom: 1px solid #eee; }
            tr:nth-child(even) td { background: #f8f9fa; }
            .footer { margin-top: 30px; font-size: 11px; color: #999; text-align: center; }
            @media print { button { display:none; } }
        </style></head><body>
        <h1>📊 Campus Find — Report</h1>
        <div class="sub">Generated on ' . date('d M Y \a\t h:i A') . ($date_label ? ' · Filter: ' . htmlspecialchars($date_label) : '') . '</div>
        <button onclick="window.print()" style="padding:10px 20px;background:#1a1a2e;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;margin-bottom:20px;">🖨️ Print / Save as PDF</button>
        <h2>📦 Items Report</h2>
        <table><thead><tr><th>Item Name</th><th>Category</th><th>Location</th><th>Type</th><th>Date</th><th>Reporter</th></tr></thead>
        <tbody>' . ($items_html ?: '<tr><td colspan="6" style="text-align:center;color:#999;">No items found</td></tr>') . '</tbody></table>
        <h2>✋ Claims Report</h2>
        <table><thead><tr><th>Item Name</th><th>Claimed By</th><th>Email</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>' . ($claims_html ?: '<tr><td colspan="5" style="text-align:center;color:#999;">No claims found</td></tr>') . '</tbody></table>
        <div class="footer">Campus Find — Lost & Found Management System</div>
        </body></html>';
        exit();
    }
}
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items i WHERE 1=1 $date_condition"))['c'];
$total_lost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items i WHERE report_type = 'lost' $date_condition"))['c'];
$total_found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items i WHERE report_type = 'found' $date_condition"))['c'];
$total_received = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items i WHERE report_type = 'received' $date_condition"))['c'];

$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status = 'pending' $claims_date_condition"))['c'];
$total_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status = 'approved' $claims_date_condition"))['c'];
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status = 'rejected' $claims_date_condition"))['c'];
$total_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE 1=1 $claims_date_condition"))['c'];

// ========================
// TABS (NOT FILTERABLE - always show all)
// ========================

// Approved items (received)
$approved_query = "SELECT i.*, u.username as reporter FROM items i 
                   LEFT JOIN users u ON i.user_id = u.id 
                   WHERE i.report_type = 'received' 
                   ORDER BY i.date_reported DESC";
$approved_result = mysqli_query($conn, $approved_query);

// Pending claims
$pending_query = "SELECT cl.*, i.item_name, i.category, i.location, u.username as claimer, u2.username as reporter
                  FROM claims cl
                  LEFT JOIN items i ON cl.item_id = i.id
                  LEFT JOIN users u ON cl.user_id = u.id
                  LEFT JOIN users u2 ON i.user_id = u2.id
                  WHERE cl.status = 'pending'
                  ORDER BY cl.claim_date DESC";
$pending_result = mysqli_query($conn, $pending_query);

// Rejected claims
$rejected_query = "SELECT cl.*, i.item_name, i.category, i.location, u.username as claimer, u2.username as reporter
                   FROM claims cl
                   LEFT JOIN items i ON cl.item_id = i.id
                   LEFT JOIN users u ON cl.user_id = u.id
                   LEFT JOIN users u2 ON i.user_id = u2.id
                   WHERE cl.status = 'rejected'
                   ORDER BY cl.claim_date DESC";
$rejected_result = mysqli_query($conn, $rejected_query);

// User activity
$activity_query = "SELECT u.username, u.id,
                   SUM(CASE WHEN i.report_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
                   SUM(CASE WHEN i.report_type = 'found' THEN 1 ELSE 0 END) as found_count,
                   SUM(CASE WHEN i.report_type = 'received' THEN 1 ELSE 0 END) as received_count,
                   COUNT(i.id) as total_items
                   FROM users u 
                   LEFT JOIN items i ON u.id = i.user_id
                   WHERE u.role = 'user'
                   GROUP BY u.id, u.username
                   ORDER BY total_items DESC";
$activity_result = mysqli_query($conn, $activity_query);

// Approved claims list
$approved_claims_query = "SELECT cl.*, i.item_name, i.category, i.location, u.username as claimer, u2.username as reporter
                          FROM claims cl
                          LEFT JOIN items i ON cl.item_id = i.id
                          LEFT JOIN users u ON cl.user_id = u.id
                          LEFT JOIN users u2 ON i.user_id = u2.id
                          WHERE cl.status = 'approved'
                          ORDER BY cl.claim_date DESC";
$approved_claims_result = mysqli_query($conn, $approved_claims_query);
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
    <title>Reports - Campus Find</title>
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

        /* NAVBAR */
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
            font-size: 26px;
        }

        .admin-badge {
            background-color: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 10px;
            vertical-align: middle;
        }

        .back-btn {
            background-color: white;
            color: #1a1a2e;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .back-btn:hover {
            background-color: #e6e6e6;
        }

        .chat-nav-icon {
            position: relative;
            font-size: 24px;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .chat-nav-icon:hover {
            transform: scale(1.15);
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
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: white;
            color: #1a1a2e;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .nav-avatar:hover {
            transform: scale(1.1);
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* CONTAINER */
        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 25px;
        }

        /* DATE FILTER */
        .filter-box {
            background-color: white;
            padding: 20px 25px;
            border-radius: 15px;
            border: 1px solid #000;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-box label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-box input[type="date"] {
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .filter-box input[type="date"]:focus {
            outline: none;
            border-color: #1a1a2e;
        }

        .filter-btn {
            padding: 10px 20px;
            background-color: #1a1a2e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .filter-btn:hover {
            background-color: #16213e;
        }

        .clear-btn {
            padding: 10px 20px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .clear-btn:hover {
            background-color: #c0392b;
        }

        .print-btn {
            padding: 10px 20px;
            background-color: #1a1a2e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn:hover {
            background-color: #0f3460;
        }

        .export-wrap {
            position: relative;
            margin-left: auto;
        }

        .export-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 110%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 100;
            overflow: hidden;
        }

        .export-dropdown.open {
            display: block;
        }

        .export-option {
            display: block;
            padding: 11px 18px;
            font-size: 13px;
            color: #333;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .export-option:hover {
            background: #f0f2f5;
        }

        /* STATS */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            flex: 1;
            min-width: 130px;
            border: 1px solid #000;
        }

        .stat-icon {
            font-size: 35px;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 3px;
        }

        /* SECTION */
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 3px solid #1a1a2e;
            display: inline-block;
        }

        /* TABS */
        .tab-container {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #000;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tab-header {
            display: flex;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .tab-btn {
            padding: 15px 25px;
            background-color: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            font-family: 'Poppins', sans-serif;
            position: relative;
            flex: 1;
            text-align: center;
        }

        .tab-btn:hover {
            color: #333;
            background-color: #eef2ff;
        }

        .tab-btn.active {
            color: #1a1a2e;
            background-color: white;
            border-bottom: 3px solid #1a1a2e;
        }

        .tab-badge {
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 50%;
            margin-left: 5px;
        }

        .tab-badge.green {
            background-color: #159f35;
        }

        .tab-badge.gray {
            background-color: #999;
        }

        .tab-badge.yellow {
            background-color: #f39c12;
        }

        .tab-content {
            display: none;
            padding: 20px;
        }

        .tab-content.active {
            display: block;
        }

        /* TABLES */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .report-table tr:hover {
            background-color: #f5f5f5;
        }

        .status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        .status.lost {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status.found {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .status.received {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status.approved {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .reporter-tag {
            font-size: 11px;
            color: #6f42c1;
            background-color: #f0e6ff;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .claimer-tag {
            font-size: 11px;
            color: #007bff;
            background-color: #e6f0ff;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .no-data {
            text-align: center;
            color: #999;
            padding: 30px;
            font-size: 14px;
        }

        .no-data span {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }

        /* USER ACTIVITY */
        .activity-bar {
            display: flex;
            gap: 5px;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            background-color: #eee;
            min-width: 100px;
        }

        .bar-lost {
            background-color: #dc3545;
            height: 100%;
        }

        .bar-found {
            background-color: #007bff;
            height: 100%;
        }

        .bar-received {
            background-color: #28a745;
            height: 100%;
        }

        .activity-legend {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            font-size: 12px;
            color: #666;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            vertical-align: middle;
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

        <h1 class="page-title">Reports & Analytics</h1>

        <!-- DATE FILTER -->
        <form class="filter-box" method="GET">
            <label>From:</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            <label>To:</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="admin_reports.php" class="clear-btn">✕ Clear</a>
            <div class="export-wrap">
                <button type="button" class="print-btn" onclick="toggleExportMenu()">Export ▾</button>
                <div class="export-dropdown" id="exportDropdown">
                    <a class="export-option"
                        href="admin_reports.php?export=csv<?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">Download
                        as Excel(CSV)</a>
                    <a class="export-option"
                        href="admin_reports.php?export=pdf<?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>"
                        target="_blank">Export as PDF</a>
                </div>
            </div>
        </form>

        <?php if (!empty($date_from) || !empty($date_to)) { ?>
            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                📅 Filtered:
                <?php echo !empty($date_from) ? 'From ' . date('d M Y', strtotime($date_from)) : ''; ?>
                <?php echo !empty($date_to) ? ' To ' . date('d M Y', strtotime($date_to)) : ''; ?>
            </p>
        <?php } ?>

        <!-- STATS -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-number"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔴</div>
                <div class="stat-number"><?php echo $total_lost; ?></div>
                <div class="stat-label">Lost</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔵</div>
                <div class="stat-number"><?php echo $total_found; ?></div>
                <div class="stat-label">Found</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🟢</div>
                <div class="stat-number"><?php echo $total_received; ?></div>
                <div class="stat-label">Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🟡</div>
                <div class="stat-number"><?php echo $total_pending; ?></div>
                <div class="stat-label">Pending Claims</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $total_approved; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-number"><?php echo $total_rejected; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $total_claims; ?></div>
                <div class="stat-label">Total Claims</div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tab-container">
            <div class="tab-header">
                <button class="tab-btn active" onclick="switchTab('approved')">
                    ✅ Approved Items <span
                        class="tab-badge green"><?php echo mysqli_num_rows($approved_result); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('pending')">
                    🟡 Pending Claims <span
                        class="tab-badge yellow"><?php echo mysqli_num_rows($pending_result); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('rejected')">
                    ❌ Rejected Claims <span
                        class="tab-badge gray"><?php echo mysqli_num_rows($rejected_result); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('approved_claims')">
                    🏆 Approved Claims <span
                        class="tab-badge green"><?php echo mysqli_num_rows($approved_claims_result); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('activity')">
                    👥 User Activity
                </button>
            </div>

            <!-- TAB 1: Approved Items (Received) -->
            <div class="tab-content active" id="tab-approved">
                <?php if (mysqli_num_rows($approved_result) > 0) { ?>
                    <table class="report-table">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($approved_result)) { ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['reporter']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td><span class="status received">🟢 Received</span></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-data">
                        <span>📭</span>
                        <p>No approved items found.</p>
                    </div>
                <?php } ?>
            </div>

            <!-- TAB 2: Pending Claims -->
            <div class="tab-content" id="tab-pending">
                <?php if (mysqli_num_rows($pending_result) > 0) { ?>
                    <table class="report-table">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Claimed By</th>
                            <th>Reporter</th>
                            <th>Claim Date</th>
                            <th>Countdown End</th>
                            <th>Status</th>
                        </tr>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($pending_result)) { ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><span class="claimer-tag">🙋 <?php echo htmlspecialchars($row['claimer']); ?></span></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['reporter']); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['countdown_end'])); ?></td>
                                <td><span class="status pending">🟡 Pending</span></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-data">
                        <p>No pending claims! All clear.</p>
                    </div>
                <?php } ?>
            </div>

            <!-- TAB 3: Rejected Claims -->
            <div class="tab-content" id="tab-rejected">
                <?php if (mysqli_num_rows($rejected_result) > 0) { ?>
                    <table class="report-table">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Claimed By</th>
                            <th>Reporter</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                        </tr>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($rejected_result)) { ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><span class="claimer-tag">🙋 <?php echo htmlspecialchars($row['claimer']); ?></span></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['reporter']); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?></td>
                                <td><span class="status rejected">❌ Rejected</span></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-data">
                        <span>📭</span>
                        <p>No rejected claims.</p>
                    </div>
                <?php } ?>
            </div>

            <!-- TAB 4: Approved Claims -->
            <div class="tab-content" id="tab-approved_claims">
                <?php if (mysqli_num_rows($approved_claims_result) > 0) { ?>
                    <table class="report-table">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Claimed By</th>
                            <th>Reporter</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                        </tr>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($approved_claims_result)) { ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><span class="claimer-tag">🙋 <?php echo htmlspecialchars($row['claimer']); ?></span></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['reporter']); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?></td>
                                <td><span class="status approved">✅ Approved</span></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-data">
                        <span>📭</span>
                        <p>No approved claims yet.</p>
                    </div>
                <?php } ?>
            </div>

            <!-- TAB 5: User Activity -->
            <div class="tab-content" id="tab-activity">
                <?php if (mysqli_num_rows($activity_result) > 0) { ?>
                    <div class="activity-legend">
                        <span><span class="legend-dot" style="background-color:#dc3545;"></span> Lost</span>
                        <span><span class="legend-dot" style="background-color:#007bff;"></span> Found</span>
                        <span><span class="legend-dot" style="background-color:#28a745;"></span> Received</span>
                    </div>
                    <br>
                    <table class="report-table">
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Lost</th>
                            <th>Found</th>
                            <th>Received</th>
                            <th>Total</th>
                            <th>Activity</th>
                        </tr>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($activity_result)) {
                            $max = max($row['total_items'], 1);
                            $lost_pct = ($row['lost_count'] / $max) * 100;
                            $found_pct = ($row['found_count'] / $max) * 100;
                            $received_pct = ($row['received_count'] / $max) * 100;
                            ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['username']); ?></span></td>
                                <td><?php echo $row['lost_count']; ?></td>
                                <td><?php echo $row['found_count']; ?></td>
                                <td><?php echo $row['received_count']; ?></td>
                                <td><strong><?php echo $row['total_items']; ?></strong></td>
                                <td>
                                    <div class="activity-bar">
                                        <?php if ($row['lost_count'] > 0) { ?>
                                            <div class="bar-lost" style="width: <?php echo $lost_pct; ?>%"></div>
                                        <?php } ?>
                                        <?php if ($row['found_count'] > 0) { ?>
                                            <div class="bar-found" style="width: <?php echo $found_pct; ?>%"></div>
                                        <?php } ?>
                                        <?php if ($row['received_count'] > 0) { ?>
                                            <div class="bar-received" style="width: <?php echo $received_pct; ?>%"></div>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-data">
                        <span>👥</span>
                        <p>No user activity yet.</p>
                    </div>
                <?php } ?>
            </div>

        </div>

    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(function (btn) { btn.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function (content) { content.classList.remove('active'); });
            document.getElementById('tab-' + tab).classList.add('active');
            var btns = document.querySelectorAll('.tab-btn');
            var tabMap = ['approved', 'pending', 'rejected', 'approved_claims', 'activity'];
            var index = tabMap.indexOf(tab);
            if (index >= 0) btns[index].classList.add('active');
        }

        var _exportJustOpened = false;
        function toggleExportMenu() {
            document.getElementById('exportDropdown').classList.toggle('open');
            _exportJustOpened = true;
        }
        document.addEventListener('click', function () {
            if (_exportJustOpened) { _exportJustOpened = false; return; }
            document.getElementById('exportDropdown').classList.remove('open');
        });
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>