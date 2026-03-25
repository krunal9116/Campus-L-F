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

$admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'"));
if (!$admin_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$admin_id = $admin_data['id'];

$photo_msg = "";
$photo_error = "";

// UPLOAD PROFILE PHOTO
if (isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $file = $_FILES['profile_photo'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed)) {
            $photo_error = "Only JPG, JPEG, PNG, GIF, WEBP files are allowed!";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $photo_error = "File size must be less than 5MB!";
        } else {
            if (!empty($admin_data['profile_photo'])) {
                $old = 'uploads/profile_photos/' . $admin_data['profile_photo'];
                if (file_exists($old))
                    unlink($old);
            }
            $new_name = 'admin_' . $admin_id . '_' . time() . '.' . $file_ext;
            if (move_uploaded_file($file['tmp_name'], 'uploads/profile_photos/' . $new_name)) {
                mysqli_query($conn, "UPDATE users SET profile_photo = '$new_name' WHERE id = '$admin_id'");
                $photo_msg = "Profile photo updated!";
                $admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$admin_id'"));
            } else {
                $photo_error = "Failed to upload file.";
            }
        }
    } else {
        $photo_error = "Please select a photo!";
    }
}

// REMOVE PROFILE PHOTO
if (isset($_POST['remove_photo'])) {
    if (!empty($admin_data['profile_photo'])) {
        $old = 'uploads/profile_photos/' . $admin_data['profile_photo'];
        if (file_exists($old))
            unlink($old);
        mysqli_query($conn, "UPDATE users SET profile_photo = NULL WHERE id = '$admin_id'");
        $photo_msg = "Profile photo removed!";
        $admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$admin_id'"));
    }
}

// Admin stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user'"))['c'];
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items"))['c'];
$total_lost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='lost'"))['c'];
$total_found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='found'"))['c'];
$total_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims"))['c'];
$pending_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='pending'"))['c'];
$col_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='claims' AND COLUMN_NAME='approved_by'"));
$approved_claims = ($col_check['c'] > 0)
    ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='approved' AND approved_by='$admin_id'"))['c']
    : 0;

// Recent activity
$recent_items = mysqli_query($conn, "SELECT i.*, u.username FROM items i JOIN users u ON i.user_id = u.id ORDER BY i.date_reported DESC LIMIT 5");
$recent_claims = mysqli_query($conn, "SELECT cl.*, u.username AS claimer, i.item_name FROM claims cl JOIN users u ON cl.user_id = u.id JOIN items i ON cl.item_id = i.id ORDER BY cl.claim_date DESC LIMIT 5");

$joined_date = isset($admin_data['created_at']) ? date('d M Y', strtotime($admin_data['created_at'])) : 'N/A';
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';
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
    <title>Admin Profile - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .navbar {
            background: #1a1a2e;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .navbar h1 {
            font-size: 24px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            background: white;
            color: #1a1a2e;
            padding: 9px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .back-btn:hover {
            background: #dde3f0;
        }

        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            vertical-align: middle;
        }

        .container {
            padding: 30px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .msg {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .msg.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .msg.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .profile-banner {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 50px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-size: 16px;
            border: 2px solid #1a1a2e;
            transition: transform 0.2s;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
        }

        .profile-banner h2 {
            font-size: 26px;
            margin-bottom: 5px;
        }

        .profile-banner p {
            font-size: 14px;
            opacity: 0.85;
        }

        .profile-details {
            padding: 25px 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .detail-item {
            flex: 1;
            min-width: 220px;
            padding-right: 15px;
        }

        .detail-item label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        .detail-item p {
            font-size: 15px;
            color: #333;
            font-weight: 500;
            word-break: break-word;
        }

        /* Stats */
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 3px solid #1a1a2e;
            display: inline-block;
        }

        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #ddd;
            padding: 20px;
            flex: 1;
            min-width: 140px;
            text-align: center;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .stat-card:hover {
            border-color: #1a1a2e;
            box-shadow: 0 3px 10px rgba(26, 26, 46, 0.1);
        }

        .stat-icon {
            font-size: 35px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .stat-label {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
        }

        .stat-card.users .stat-number {
            color: #1a1a2e;
        }

        .stat-card.items .stat-number {
            color: #6f42c1;
        }

        .stat-card.lost .stat-number {
            color: #dc3545;
        }

        .stat-card.found .stat-number {
            color: #007bff;
        }

        .stat-card.claims .stat-number {
            color: #856404;
        }

        .stat-card.pending .stat-number {
            color: #fd7e14;
        }

        .stat-card.approved .stat-number {
            color: #159f35;
        }

        /* Tables */
        .table-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .table-card-header {
            padding: 18px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-card-header h3 {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .view-all {
            font-size: 13px;
            color: #1a1a2e;
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            color: #555;
        }

        .data-table td {
            font-size: 14px;
            color: #333;
        }

        .data-table tr:hover {
            background: #f9f9f9;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 14px;
        }

        .no-data span {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status.lost {
            background: #f8d7da;
            color: #721c24;
        }

        .status.found {
            background: #cce5ff;
            color: #004085;
        }

        .status.received {
            background: #d4edda;
            color: #155724;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.approved {
            background: #d4edda;
            color: #155724;
        }

        .status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        /* Quick Actions */
        .profile-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #2d3148;
            background: #1e2130;
            color: #c0c8ff;
        }

        .action-btn:hover {
            border-color: #4f6ef7;
            background: #252840;
            color: #a8c0ff;
        }

        /* Photo Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal.open {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            max-width: 450px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .photo-preview-area {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #f0f2f5;
            margin: 0 auto 20px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px dashed #ccc;
        }

        .photo-preview-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-area.has-photo {
            border-style: solid;
            border-color: #1a1a2e;
        }

        .photo-preview-placeholder {
            color: #999;
            font-size: 13px;
        }

        .file-input-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-select-btn {
            padding: 12px 25px;
            background: #f0f2f5;
            border: 2px dashed #ccc;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            display: inline-block;
            width: 100%;
            font-family: 'Poppins', sans-serif;
        }

        .file-select-btn:hover {
            border-color: #1a1a2e;
            background: #eef0f8;
            color: #1a1a2e;
        }

        .file-name {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .photo-hint {
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .btn-dark {
            background: #1a1a2e;
            color: white;
        }

        .btn-dark:hover {
            background: #0f3460;
        }

        .btn-red {
            background: #e74c3c;
            color: white;
        }

        .btn-red:hover {
            background: #c0392b;
        }

        .btn-gray {
            background: #6c757d;
            color: white;
        }

        .btn-gray:hover {
            background: #545b62;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>Admin Profile <span class="admin-badge">ADMIN</span></h1>
        </div>
        <div class="nav-avatar">
            <?php if ($has_photo): ?>
                <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="">
            <?php else: ?>
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">

        <?php if ($photo_msg): ?>
            <div class="msg success">✅ <?php echo $photo_msg; ?></div>
        <?php endif; ?>
        <?php if ($photo_error): ?>
            <div class="msg error">❌ <?php echo $photo_error; ?></div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-banner">
                <div class="avatar-wrapper">
                    <div class="profile-avatar">
                        <?php if ($has_photo): ?>
                            <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-edit-btn" onclick="openPhotoModal()">📷</div>
                </div>
                <h2><?php echo htmlspecialchars($username); ?></h2>
                <p>🛡️ System Administrator — Campus Find</p>
            </div>
            <div class="profile-details">
                <div class="detail-item">
                    <label>👤 Username</label>
                    <p><?php echo htmlspecialchars($username); ?></p>
                </div>
                <div class="detail-item">
                    <label>📧 Email</label>
                    <p><?php echo htmlspecialchars($admin_data['email']); ?></p>
                </div>
                <div class="detail-item">
                    <label>🔑 Role</label>
                    <p>Administrator</p>
                </div>
                <div class="detail-item">
                    <label>📅 Joined</label>
                    <p><?php echo $joined_date; ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="profile-actions">
            <a href="admin_claims.php" class="action-btn">✋ Manage Claims</a>
            <a href="admin_manage_items.php" class="action-btn">📦 Manage Items</a>
            <a href="admin_manage_users.php" class="action-btn">👥 Manage Users</a>
            <a href="admin_reports.php" class="action-btn">📊 Reports</a>
        </div>

        <!-- System Stats -->
        <h2 class="section-title">📊 System Overview</h2>
        <div class="stats-container">
            <div class="stat-card users">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card items">
                <div class="stat-icon">📦</div>
                <div class="stat-number"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card lost">
                <div class="stat-icon">🔴</div>
                <div class="stat-number"><?php echo $total_lost; ?></div>
                <div class="stat-label">Lost Reports</div>
            </div>
            <div class="stat-card found">
                <div class="stat-icon">🔵</div>
                <div class="stat-number"><?php echo $total_found; ?></div>
                <div class="stat-label">Found Reports</div>
            </div>
            <div class="stat-card claims">
                <div class="stat-icon">✋</div>
                <div class="stat-number"><?php echo $total_claims; ?></div>
                <div class="stat-label">Total Claims</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $pending_claims; ?></div>
                <div class="stat-label">Pending Claims</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $approved_claims; ?></div>
                <div class="stat-label">Approved Claims</div>
            </div>
        </div>

        <!-- Recent Items -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>📋 Recent Item Reports</h3>
                <a href="admin_manage_items.php" class="view-all">View All →</a>
            </div>
            <?php if (mysqli_num_rows($recent_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Date</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent_items)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td>
                                    <?php
                                    $t = strtolower($row['report_type']);
                                    $labels = ['lost' => '🔴 Lost', 'found' => '🔵 Found', 'received' => '✅ Received'];
                                    echo '<span class="status ' . $t . '">' . ($labels[$t] ?? ucfirst($t)) . '</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data"><span>📭</span>
                    <p>No items reported yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Claims -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>✋ Recent Claims</h3>
                <a href="admin_claims.php" class="view-all">View All →</a>
            </div>
            <?php if (mysqli_num_rows($recent_claims) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Claimed By</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent_claims)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['claimer']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['claim_date'])); ?></td>
                                <td>
                                    <?php
                                    $s = strtolower($row['status']);
                                    $slabels = ['pending' => '🟡 Pending', 'approved' => '🟢 Approved', 'rejected' => '🔴 Rejected'];
                                    echo '<span class="status ' . $s . '">' . ($slabels[$s] ?? ucfirst($s)) . '</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data"><span>📭</span>
                    <p>No claims yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Photo Modal -->
    <div class="modal" id="photoModal">
        <div class="modal-content">
            <h3>📷 Update Profile Photo</h3>
            <div class="photo-preview-area <?php echo $has_photo ? 'has-photo' : ''; ?>" id="previewArea">
                <?php if ($has_photo): ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" id="previewImg" alt="">
                <?php else: ?>
                    <div class="photo-preview-placeholder" id="previewPlaceholder">
                        <div style="font-size:40px;">📷</div>
                        <div>No photo selected</div>
                    </div>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="file-input-wrapper">
                    <label class="file-select-btn" for="photoInput">📁 Choose Photo</label>
                    <input type="file" id="photoInput" name="profile_photo" accept="image/*"
                        onchange="previewPhoto(this)">
                    <div class="file-name" id="fileName">No file chosen</div>
                </div>
                <p class="photo-hint">JPG, PNG, GIF or WEBP · Max 5MB</p>
                <div class="modal-buttons">
                    <button type="submit" name="upload_photo" class="btn btn-dark">💾 Save Photo</button>
                    <?php if ($has_photo): ?>
                        <button type="submit" name="remove_photo" class="btn btn-red"
                            onclick="return confirm('Remove profile photo?')">🗑️ Remove</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-gray" onclick="closePhotoModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPhotoModal() { document.getElementById('photoModal').classList.add('open'); }
        function closePhotoModal() { document.getElementById('photoModal').classList.remove('open'); }
        document.getElementById('photoModal').addEventListener('click', function (e) {
            if (e.target === this) closePhotoModal();
        });
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var area = document.getElementById('previewArea');
                    area.classList.add('has-photo');
                    area.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
                };
                reader.readAsDataURL(input.files[0]);
                document.getElementById('fileName').textContent = input.files[0].name;
            }
        }
    </script>
    <?php mysqli_close($conn); ?>
</body>

</html>