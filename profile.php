<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

// Get user data
$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$user_id = $user_data['id'];

$photo_msg = "";
$photo_error = "";

// ========================
// UPLOAD / CHANGE PROFILE PHOTO
// ========================
if (isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $file = $_FILES['profile_photo'];
        $file_name = $file['name'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $mime = mime_content_type($file_tmp);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file_ext, $allowed) || !in_array($mime, $allowed_mime)) {
            $photo_error = "Only JPG, JPEG, PNG, GIF, WEBP files are allowed!";
        } else if ($file_size > 5 * 1024 * 1024) {
            $photo_error = "File size must be less than 5MB!";
        } else {
            // Delete old photo if exists
            if (!empty($user_data['profile_photo'])) {
                $old_photo = 'uploads/profile_photos/' . $user_data['profile_photo'];
                if (file_exists($old_photo)) {
                    unlink($old_photo);
                }
            }

            // Generate unique name
            $new_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = 'uploads/profile_photos/' . $new_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $update = "UPDATE users SET profile_photo = '$new_name' WHERE id = '$user_id'";
                if (mysqli_query($conn, $update)) {
                    $photo_msg = "Profile photo updated!";
                    // Refresh user data
                    $user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
                    $user_data = mysqli_fetch_assoc($user_result);
                } else {
                    $photo_error = "Failed to update database.";
                }
            } else {
                $photo_error = "Failed to upload file.";
            }
        }
    } else {
        $photo_error = "Please select a photo!";
    }
}

// ========================
// REMOVE PROFILE PHOTO
// ========================
if (isset($_POST['remove_photo'])) {
    if (!empty($user_data['profile_photo'])) {
        $old_photo = 'uploads/profile_photos/' . $user_data['profile_photo'];
        if (file_exists($old_photo)) {
            unlink($old_photo);
        }

        $update = "UPDATE users SET profile_photo = NULL WHERE id = '$user_id'";
        if (mysqli_query($conn, $update)) {
            $photo_msg = "Profile photo removed!";
            $user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
            $user_data = mysqli_fetch_assoc($user_result);
        }
    }
}

// Stats
$reported_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id'";
$reported_result = mysqli_query($conn, $reported_query);
$total_reported = mysqli_fetch_assoc($reported_result)['count'];

$lost_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id' AND report_type = 'lost'";
$lost_result = mysqli_query($conn, $lost_query);
$total_lost = mysqli_fetch_assoc($lost_result)['count'];

$found_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id' AND report_type = 'found'";
$found_result = mysqli_query($conn, $found_query);
$total_found = mysqli_fetch_assoc($found_result)['count'];

$claims_query = "SELECT COUNT(*) as count FROM claims WHERE user_id = '$user_id'";
$claims_result = mysqli_query($conn, $claims_query);
$total_claims = mysqli_fetch_assoc($claims_result)['count'];

$approved_query = "SELECT COUNT(*) as count FROM claims WHERE user_id = '$user_id' AND status = 'approved'";
$approved_result = mysqli_query($conn, $approved_query);
$total_approved = mysqli_fetch_assoc($approved_result)['count'];

$pending_query = "SELECT COUNT(*) as count FROM claims WHERE user_id = '$user_id' AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$total_pending = mysqli_fetch_assoc($pending_result)['count'];

$reports_query = "SELECT * FROM items WHERE user_id = '$user_id' ORDER BY date_reported DESC LIMIT 5";
$reports_result = mysqli_query($conn, $reports_query);

$my_claims_query = "SELECT claims.*, items.item_name, items.category, items.location, items.report_type 
                    FROM claims 
                    JOIN items ON claims.item_id = items.id 
                    WHERE claims.user_id = '$user_id' 
                    ORDER BY claims.claim_date DESC LIMIT 5";
$my_claims_result = mysqli_query($conn, $my_claims_query);

$joined_date = isset($user_data['created_at']) ? date('d M Y', strtotime($user_data['created_at'])) : 'N/A';

// Profile photo
$has_photo = !empty($user_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $user_data['profile_photo'] : '';
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
    <title>My Profile - Campus Find</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Messages */
        .msg {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Card */
        .profile-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .profile-banner {
            background: linear-gradient(135deg, #159f35, #0d7a28);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        /* Avatar */
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
            background-color: white;
            color: #159f35;
            font-size: 50px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-size: 16px;
            border: 2px solid #159f35;
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
            opacity: 0.9;
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
            overflow-wrap: break-word;
        }

        /* Stats */
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 3px solid #159f35;
            display: inline-block;
        }

        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background-color: white;
            border-radius: 12px;
            border: 1px solid #ddd;
            padding: 20px;
            flex: 1;
            min-width: 150px;
            text-align: center;
        }

        .stat-card:hover {
            border-color: #159f35;
            box-shadow: 0 3px 10px rgba(21, 159, 53, 0.1);
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

        .stat-card.lost .stat-number {
            color: #dc3545;
        }

        .stat-card.found .stat-number {
            color: #007bff;
        }

        .stat-card.claimed .stat-number {
            color: #856404;
        }

        .stat-card.approved .stat-number {
            color: #159f35;
        }

        .stat-card.pending .stat-number {
            color: #fd7e14;
        }

        .stat-card.total .stat-number {
            color: #6f42c1;
        }

        /* Tables */
        .table-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .table-card-header {
            padding: 18px 25px;
            background-color: #f8f9fa;
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
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            color: #555;
        }

        .data-table td {
            font-size: 14px;
            color: #333;
        }

        .data-table tr:hover {
            background-color: #f9f9f9;
        }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            min-width: 100px;
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

        /* Action Buttons */
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
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
        }

        .action-btn:hover {
            border-color: #159f35;
            background-color: #e6f8e6;
            color: #159f35;
        }

        .action-btn .icon {
            font-size: 18px;
        }

        /* Photo Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
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
            background-color: #f0f2f5;
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
            border-color: #159f35;
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
            background-color: #f0f2f5;
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
            border-color: #159f35;
            background-color: #e6f8e6;
            color: #159f35;
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

        .btn-green {
            background-color: #159f35;
            color: white;
        }

        .btn-green:hover {
            background-color: #035815;
        }

        .btn-red {
            background-color: #e74c3c;
            color: white;
        }

        .btn-red:hover {
            background-color: #c0392b;
        }

        .btn-gray {
            background-color: #6c757d;
            color: white;
        }

        .btn-gray:hover {
            background-color: #545b62;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <h1>My Profile</h1>
        <a href="user_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="container">

        <?php if ($photo_msg != "") { ?>
            <div class="msg success">✅ <?php echo $photo_msg; ?></div>
        <?php } ?>

        <?php if ($photo_error != "") { ?>
            <div class="msg error">❌ <?php echo $photo_error; ?></div>
        <?php } ?>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-banner">
                <div class="avatar-wrapper">
                    <div class="profile-avatar">
                        <?php if ($has_photo) { ?>
                            <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile Photo">
                        <?php } else { ?>
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        <?php } ?>
                    </div>
                    <div class="avatar-edit-btn" onclick="openPhotoModal()">📷</div>
                </div>
                <h2><?php echo htmlspecialchars($username); ?></h2>
                <p>Campus Find Member</p>
            </div>

            <div class="profile-details">
                <div class="detail-item">
                    <label>👤 Username</label>
                    <p><?php echo htmlspecialchars($username); ?></p>
                </div>
                <div class="detail-item">
                    <label>📧 Email</label>
                    <p><?php echo htmlspecialchars($user_data['email']); ?></p>
                </div>
                <div class="detail-item">
                    <label>🔑 Role</label>
                    <p><?php echo ucfirst($user_data['role']); ?></p>
                </div>
                <div class="detail-item">
                    <label>📅 Joined</label>
                    <p><?php echo $joined_date; ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="profile-actions">
            <a href="settings.php" class="action-btn">
                <span class="icon">⚙️</span> Edit Profile
            </a>
            <a href="report_lost.php" class="action-btn">
                <span class="icon">📢</span> Report Lost Item
            </a>
            <a href="report_found.php" class="action-btn">
                <span class="icon">🕵️</span> Report Found Item
            </a>
            <a href="search_items.php" class="action-btn">
                <span class="icon">🔎</span> Search Items
            </a>
        </div>

        <!-- Activity Stats -->
        <h2 class="section-title">📊 Activity Stats</h2>

        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?php echo $total_reported; ?></div>
                <div class="stat-label">Total Reported</div>
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
            <div class="stat-card claimed">
                <div class="stat-icon">✋</div>
                <div class="stat-number"><?php echo $total_claims; ?></div>
                <div class="stat-label">Total Claims</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $total_approved; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $total_pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- My Reports Table -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>📋 My Recent Reports</h3>
            </div>

            <?php if (mysqli_num_rows($reports_result) > 0) { ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($reports_result)) {
                            $type = strtolower($row['report_type']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td>
                                    <?php
                                    $type = strtolower($row['report_type'] ?? '');
                                    if ($type == 'lost') {
                                        echo '<span class="status lost">🔴 Lost</span>';
                                    } else if ($type == 'found') {
                                        echo '<span class="status found">🔵 Found</span>';
                                    } else if ($type == 'received') {
                                        echo '<span class="status approved">🟢 Received</span>';
                                    } else {
                                        echo '<span class="status pending">Unknown</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="no-data">
                    <span>📭</span>
                    <p>You haven't reported any items yet.</p>
                </div>
            <?php } ?>
        </div>

        <!-- My Claims Table -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>✋ My Recent Claims</h3>
            </div>

            <?php if (mysqli_num_rows($my_claims_result) > 0) { ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($my_claims_result)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['claim_date'])); ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($row['status']);
                                    if ($status == 'pending') {
                                        echo '<span class="status pending">🟡 Pending</span>';
                                    } else if ($status == 'approved') {
                                        echo '<span class="status approved">🟢 Approved</span>';
                                    } else if ($status == 'rejected') {
                                        echo '<span class="status rejected">🔴 Rejected</span>';
                                    } else {
                                        echo '<span class="status pending">' . ucfirst($status) . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="no-data">
                    <span>📭</span>
                    <p>You haven't claimed any items yet.</p>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- Photo Upload Modal -->
    <div class="modal" id="photoModal">
        <div class="modal-content">
            <h3>📷 Change Profile Photo</h3>

            <div class="photo-preview-area <?php echo $has_photo ? 'has-photo' : ''; ?>" id="previewArea">
                <?php if ($has_photo) { ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" id="previewImg" alt="Preview">
                <?php } else { ?>
                    <span class="photo-preview-placeholder" id="previewPlaceholder">No photo selected</span>
                    <img src="" id="previewImg" alt="Preview" style="display: none;">
                <?php } ?>
            </div>

            <form method="POST" enctype="multipart/form-data" id="photoForm">
                <div class="file-input-wrapper">
                    <label class="file-select-btn" for="photoInput" id="fileLabel">
                        📁 Click to select a photo
                    </label>
                    <input type="file" name="profile_photo" id="photoInput" accept="image/*">
                    <p class="file-name" id="fileName"></p>
                </div>

                <p class="photo-hint">Allowed: JPG, PNG, GIF, WEBP — Max: 5MB</p>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-gray" onclick="closePhotoModal()">Cancel</button>
                    <?php if ($has_photo) { ?>
                        <button type="submit" name="remove_photo" class="btn btn-red">🗑️ Remove</button>
                    <?php } ?>
                    <button type="submit" name="upload_photo" class="btn btn-green">✅ Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Photo Modal
        function openPhotoModal() {
            document.getElementById('photoModal').style.display = 'flex';
        }

        function closePhotoModal() {
            document.getElementById('photoModal').style.display = 'none';
        }

        // File Preview
        document.getElementById('photoInput').addEventListener('change', function (e) {
            var file = e.target.files[0];

            if (file) {
                // Show file name
                document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                document.getElementById('fileLabel').textContent = '📁 ' + file.name;

                // Show preview
                var reader = new FileReader();
                reader.onload = function (event) {
                    var previewImg = document.getElementById('previewImg');
                    previewImg.src = event.target.result;
                    previewImg.style.display = 'block';

                    var placeholder = document.getElementById('previewPlaceholder');
                    if (placeholder) placeholder.style.display = 'none';

                    document.getElementById('previewArea').classList.add('has-photo');
                };
                reader.readAsDataURL(file);
            }
        });

        // Close modal on outside click
        document.getElementById('photoModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closePhotoModal();
            }
        });
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>