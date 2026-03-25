<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

$user_query = "SELECT id FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$user_id = $user_data['id'];

// ── CANCEL CLAIM ──────────────────────────────────────────
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    // Only allow cancel if it belongs to this user, is pending, and timer hasn't expired
    $php_now = date('Y-m-d H:i:s');
    $check = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM claims WHERE id = $cancel_id AND user_id = '$user_id' AND status = 'pending' AND countdown_end > '$php_now'"
    ));
    if ($check) {
        mysqli_query($conn, "DELETE FROM claims WHERE id = $cancel_id");
    }
    header("Location: claim_item.php");
    exit();
}

// Get all claims by THIS user with item details
$query = "SELECT claims.*, items.item_name, items.category, items.location, 
          items.description, items.date_reported, items.report_type, items.image
          FROM claims 
          JOIN items ON claims.item_id = items.id 
          WHERE claims.user_id = '$user_id' 
          ORDER BY claims.claim_date DESC";
$result = mysqli_query($conn, $query);
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
    <title>My Claims - Campus Find</title>
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

        .navbar {
            background-color: #159f35;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .navbar h1 {
            font-size: 30px;
        }

        .back-btn {
            background-color: white;
            color: #159f35;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .back-btn:hover {
            background-color: #e6f8e6;
        }

        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            border-bottom: 3px solid #159f35;
            padding-bottom: 10px;
            display: inline-block;
        }

        .claims-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .claim-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        .claim-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #159f35;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .claim-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .claim-status.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .claim-status.approved {
            background-color: #d4edda;
            color: #155724;
        }

        .claim-status.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .card-body {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            width: 130px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .detail-value {
            flex: 1;
            color: #333;
            font-size: 14px;
        }

        .item-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .item-status.lost {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .item-status.found {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .total-claims-badge {
            font-size: 11px;
            color: #856404;
            background-color: #fff3cd;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }

        .countdown-section {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }

        .countdown-title {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }

        .countdown-timer {
            display: flex;
            gap: 15px;
        }

        .countdown-box {
            text-align: center;
        }

        .countdown-number {
            font-size: 24px;
            font-weight: 700;
            color: #159f35;
        }

        .countdown-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .countdown-awaiting {
            color: #159f35;
            font-weight: 600;
            font-size: 14px;
        }

        .claim-count-info {
            background-color: #fff3cd;
            padding: 10px 20px;
            font-size: 12px;
            color: #856404;
            border-top: 1px solid #eee;
        }

        .no-claims {
            background-color: white;
            padding: 50px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .no-claims span {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
        }

        .no-claims p {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .no-claims a {
            color: #159f35;
            text-decoration: none;
            font-weight: 500;
        }

        .no-claims a:hover {
            text-decoration: underline;
        }

        /* Item image in claim card */
        .claim-item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .claim-image-placeholder {
            width: 100%;
            height: 120px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #ccc;
        }

        /* Meeting countdown */
        .meeting-section {
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            padding: 15px 20px;
            border-top: 1px solid #c8e6c9;
        }

        .meeting-section .meeting-title {
            font-size: 13px;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 8px;
        }

        .meeting-datetime-display {
            font-size: 15px;
            font-weight: 700;
            color: #1b5e20;
            background: white;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #a5d6a7;
            display: inline-block;
            margin-bottom: 10px;
        }

        .meeting-countdown-label {
            font-size: 12px;
            color: #555;
            margin-bottom: 6px;
        }

        .meeting-countdown-timer {
            display: flex;
            gap: 12px;
        }

        .meeting-countdown-box {
            text-align: center;
        }

        .meeting-countdown-number {
            font-size: 22px;
            font-weight: 700;
            color: #159f35;
        }

        .meeting-countdown-unit {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
        }

        .meeting-passed {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        /* Print receipt button */
        .receipt-btn {
            display: inline-block;
            margin: 12px 20px;
            padding: 9px 18px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .receipt-btn:hover {
            background: #0f3460;
        }

        /* Print styles */
        @media print {

            .navbar,
            .back-btn,
            .cancel-section,
            .receipt-btn,
            .countdown-section {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .claim-card {
                border: 1px solid #999 !important;
                page-break-inside: avoid;
            }

            .print-only {
                display: block !important;
            }
        }

        .print-only {
            display: none;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <h1>Campus-Find</h1>
        <a href="user_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="container">

        <h2 class="page-title">Items You Have Claimed</h2>

        <?php if (mysqli_num_rows($result) > 0) { ?>
            <div class="claims-container">
                <?php while ($row = mysqli_fetch_assoc($result)) {
                    // Get total claims for this item (from ALL users)
                    $total_claims_query = "SELECT COUNT(*) as count FROM claims WHERE item_id = '" . $row['item_id'] . "'";
                    $total_claims_result = mysqli_query($conn, $total_claims_query);
                    $total_claims_data = mysqli_fetch_assoc($total_claims_result);
                    $total_claims = $total_claims_data['count'];

                    // Countdown
                    $countdown_end = strtotime($row['countdown_end']);
                    $now = time();
                    $diff = $countdown_end - $now;
                    $countdown_active = $diff > 0;

                    $item_type = strtolower($row['report_type']);
                    ?>
                    <div class="claim-card">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['item_name']); ?></h3>
                            <span class="claim-status <?php echo $row['status']; ?>">
                                <?php
                                if ($row['status'] == 'pending')
                                    echo '🟡 Pending';
                                else if ($row['status'] == 'approved')
                                    echo '🟢 Approved';
                                else if ($row['status'] == 'rejected')
                                    echo '🔴 Rejected';
                                else
                                    echo ucfirst($row['status']);
                                ?>
                            </span>
                        </div>

                        <?php if (!empty($row['image']) && file_exists('uploads/' . $row['image'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" class="claim-item-image"
                                alt="Item Photo">
                        <?php else: ?>
                            <div class="claim-image-placeholder">📷</div>
                        <?php endif; ?>

                        <div class="card-body">
                            <div class="detail-row">
                                <span class="detail-label">Category</span>
                                <span class="detail-value"><?php echo htmlspecialchars($row['category']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($row['location']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Description</span>
                                <span class="detail-value"><?php echo htmlspecialchars($row['description'] ?: '-'); ?></span>
                            </div>


                            <div class="detail-row">
                                <span class="detail-label">Date Reported</span>
                                <span class="detail-value"><?php echo date('d M Y', strtotime($row['date_reported'])); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Your Claim Date</span>
                                <span
                                    class="detail-value"><?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Total Claims</span>
                                <span class="detail-value">
                                    <span class="total-claims-badge">👥 <?php echo $total_claims; ?> user(s) claimed</span>
                                </span>
                            </div>
                        </div>

                        <?php if ($row['status'] == 'pending') { ?>
                            <div class="countdown-section">
                                <p class="countdown-title">⏱️ Verification Countdown</p>
                                <?php if ($countdown_active) {
                                    $days = floor($diff / (60 * 60 * 24));
                                    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                                    $minutes = floor(($diff % (60 * 60)) / 60);
                                    $seconds = $diff % 60;
                                    ?>
                                    <div
                                        style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                                        <div class="countdown-timer" data-end="<?php echo $row['countdown_end']; ?>">
                                            <div class="countdown-box">
                                                <div class="countdown-number days"><?php echo $days; ?></div>
                                                <div class="countdown-label">Days</div>
                                            </div>
                                            <div class="countdown-box">
                                                <div class="countdown-number hours"><?php echo $hours; ?></div>
                                                <div class="countdown-label">Hours</div>
                                            </div>
                                            <div class="countdown-box">
                                                <div class="countdown-number minutes"><?php echo $minutes; ?></div>
                                                <div class="countdown-label">Minutes</div>
                                            </div>
                                            <div class="countdown-box">
                                                <div class="countdown-number seconds"><?php echo $seconds; ?></div>
                                                <div class="countdown-label">Seconds</div>
                                            </div>
                                        </div>
                                        <!-- Cancel button inline with timer -->
                                        <a href="?cancel_id=<?php echo $row['id']; ?>"
                                            style="background:#dc2626; color:white; padding:7px 16px; border-radius:8px; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap;"
                                            onclick="return confirm('Cancel this claim?')">
                                            ✖ Cancel Claim
                                        </a>
                                    </div>
                                <?php } else { ?>
                                    <p class="countdown-awaiting">✅ Countdown ended - Waiting For Admin Verification</p>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($total_claims > 1) { ?>
                            <div class="claim-count-info">
                                👥 <?php echo $total_claims; ?> users have claimed this item. Admin will verify the rightful owner.
                            </div>
                        <?php } ?>

                        <?php if ($row['status'] == 'approved'): ?>
                            <?php
                            $has_meeting = !empty($row['meeting_datetime']);
                            $meeting_ts = $has_meeting ? strtotime($row['meeting_datetime']) : 0;
                            $meeting_diff = $meeting_ts - time();
                            ?>
                            <div class="meeting-section">
                                <p class="meeting-title">📅 Meeting Details</p>
                                <?php if ($has_meeting): ?>
                                    <div class="meeting-datetime-display">
                                        📅 <?php echo date('d M Y \a\t h:i A', $meeting_ts); ?>
                                    </div>
                                    <?php if ($meeting_diff > 0): ?>
                                        <p class="meeting-countdown-label">⏳ Time until meeting:</p>
                                        <div class="meeting-countdown-timer" data-meeting="<?php echo $row['meeting_datetime']; ?>">
                                            <?php
                                            $md = floor($meeting_diff / 86400);
                                            $mh = floor(($meeting_diff % 86400) / 3600);
                                            $mm = floor(($meeting_diff % 3600) / 60);
                                            $ms = $meeting_diff % 60;
                                            ?>
                                            <div class="meeting-countdown-box">
                                                <div class="meeting-countdown-number m-days"><?php echo $md; ?></div>
                                                <div class="meeting-countdown-unit">Days</div>
                                            </div>
                                            <div class="meeting-countdown-box">
                                                <div class="meeting-countdown-number m-hours"><?php echo $mh; ?></div>
                                                <div class="meeting-countdown-unit">Hrs</div>
                                            </div>
                                            <div class="meeting-countdown-box">
                                                <div class="meeting-countdown-number m-mins"><?php echo $mm; ?></div>
                                                <div class="meeting-countdown-unit">Min</div>
                                            </div>
                                            <div class="meeting-countdown-box">
                                                <div class="meeting-countdown-number m-secs"><?php echo $ms; ?></div>
                                                <div class="meeting-countdown-unit">Sec</div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="meeting-passed">✅ Meeting time has passed. Please contact admin if you haven't collected
                                            your item.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="meeting-passed">📍 Please visit the admin office to collect your item.</p>
                                <?php endif; ?>
                            </div>
                            <!-- Claim Receipt -->
                            <div style="padding: 12px 20px; border-top: 1px solid #eee; background: #f9fafb;">
                                <button class="receipt-btn"
                                    onclick="printReceipt(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['item_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['category'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['location'], ENT_QUOTES); ?>', '<?php echo date('d M Y, h:i A', strtotime($row['claim_date'])); ?>', '<?php echo $has_meeting ? date('d M Y, h:i A', $meeting_ts) : 'Visit admin office'; ?>')">
                                    🖨️ Print Receipt
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="no-claims">
                <span>📭</span>
                <p>You haven't claimed any items yet.</p>
                <p><a href="search_items.php">Search items</a> to find and claim your lost belongings.</p>
            </div>
        <?php } ?>

    </div>

    <script>
        function updateCountdowns() {
            var timers = document.querySelectorAll('.countdown-timer[data-end]');

            timers.forEach(function (timer) {
                var end = new Date(timer.getAttribute('data-end')).getTime();
                var now = new Date().getTime();
                var diff = end - now;

                if (diff > 0) {
                    var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    var seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    timer.querySelector('.days').textContent = days;
                    timer.querySelector('.hours').textContent = hours;
                    timer.querySelector('.minutes').textContent = minutes;
                    timer.querySelector('.seconds').textContent = seconds;
                } else {
                    timer.parentElement.innerHTML = '<p class="countdown-awaiting">✅ Countdown ended - Awaiting Admin Verification</p>';
                }
            });
        }

        setInterval(updateCountdowns, 1000);

        // Meeting countdown
        function updateMeetingCountdowns() {
            document.querySelectorAll('.meeting-countdown-timer[data-meeting]').forEach(function (timer) {
                var end = new Date(timer.getAttribute('data-meeting').replace(' ', 'T')).getTime();
                var diff = end - Date.now();
                if (diff > 0) {
                    timer.querySelector('.m-days').textContent = Math.floor(diff / 86400000);
                    timer.querySelector('.m-hours').textContent = Math.floor((diff % 86400000) / 3600000);
                    timer.querySelector('.m-mins').textContent = Math.floor((diff % 3600000) / 60000);
                    timer.querySelector('.m-secs').textContent = Math.floor((diff % 60000) / 1000);
                } else {
                    timer.parentElement.innerHTML = '<p class="meeting-passed">✅ Meeting time has passed.</p>';
                }
            });
        }
        setInterval(updateMeetingCountdowns, 1000);

        // Print receipt
        function printReceipt(id, itemName, category, location, claimDate, meetingDate) {
            var win = window.open('', '_blank', 'width=600,height=700');
            win.document.write(`
                <html><head><title>Claim Receipt - Campus Find</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
                    .logo { text-align: center; margin-bottom: 20px; }
                    .logo h1 { color: #159f35; font-size: 28px; margin: 0; }
                    .logo p { color: #666; font-size: 13px; margin: 4px 0 0; }
                    .divider { border: 2px solid #159f35; margin: 20px 0; }
                    .receipt-title { text-align: center; font-size: 20px; font-weight: bold; margin: 15px 0; color: #1a1a2e; }
                    .approved-badge { text-align: center; background: #d4edda; color: #155724; padding: 8px; border-radius: 8px; font-weight: bold; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    td { padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 14px; }
                    td:first-child { font-weight: bold; width: 40%; color: #555; }
                    .footer { text-align: center; margin-top: 30px; color: #999; font-size: 12px; }
                    @media print { button { display: none; } }
                </style></head>
                <body>
                    <div class="logo">
                        <h1>Campus-Find</h1>
                        <p>Campus Lost & Found Management System</p>
                    </div>
                    <hr class="divider">
                    <div class="receipt-title">🧾 Claim Receipt</div>
                    <div class="approved-badge">✅ CLAIM APPROVED</div>
                    <table>
                        <tr><td>Receipt No.</td><td>#CLM-${id}</td></tr>
                        <tr><td>Item Name</td><td>${itemName}</td></tr>
                        <tr><td>Category</td><td>${category}</td></tr>
                        <tr><td>Location Found</td><td>${location}</td></tr>
                        <tr><td>Claim Date</td><td>${claimDate}</td></tr>
                        <tr><td>Meeting / Pickup</td><td>${meetingDate}</td></tr>
                        <tr><td>Status</td><td>✅ Approved</td></tr>
                    </table>
                    <p style="margin-top:20px; font-size:13px; color:#555;">
                        Please bring this receipt and a valid ID when you visit the admin office to collect your item.
                    </p>
                    <div class="footer">
                        Printed on ${new Date().toLocaleString()} — Campus Find
                    </div>
                    <br><button onclick="window.print()" style="padding:10px 24px;background:#159f35;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;">🖨️ Print</button>
                </body></html>
            `);
            win.document.close();
        }
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>