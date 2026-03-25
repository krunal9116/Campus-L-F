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

// Profile photo
$has_photo = !empty($user_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $user_data['profile_photo'] : '';

// ========================
// DELETE REPORT
// ========================
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);

    // Check if item belongs to this user and not claimed
    $check = "SELECT * FROM items WHERE id = '$delete_id' AND user_id = '$user_id'";
    $check_result = mysqli_query($conn, $check);

    if (mysqli_num_rows($check_result) > 0) {
        $item = mysqli_fetch_assoc($check_result);

        // Check if claimed (only block on pending or approved — rejected claims are cleared first)
        $claim_check = "SELECT * FROM claims WHERE item_id = '$delete_id' AND status IN ('pending','approved')";
        $claim_result = mysqli_query($conn, $claim_check);

        if (mysqli_num_rows($claim_result) > 0) {
            $error_msg = "Cannot delete! This item has a pending or approved claim.";
        } else {
            // Remove any rejected claims first (FK safety)
            mysqli_query($conn, "DELETE FROM claims WHERE item_id = '$delete_id'");

            // Delete image file if exists
            if (!empty($item['image']) && file_exists('uploads/' . $item['image'])) {
                unlink('uploads/' . $item['image']);
            }

            $delete = "DELETE FROM items WHERE id = '$delete_id' AND user_id = '$user_id'";
            if (mysqli_query($conn, $delete)) {
                $success_msg = "Report deleted successfully!";
            } else {
                $error_msg = "Failed to delete report.";
            }
        }
    } else {
        $error_msg = "Report not found!";
    }
}
// Success message from edit page
if (isset($_GET['msg']) && $_GET['msg'] == 'updated') {
    $success_msg = "Report updated successfully!";
}

// ========================
// FILTERS
// ========================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Build query
$where = "WHERE user_id = '$user_id'";

if (!empty($search)) {
    $where .= " AND (item_name LIKE '%$search%' OR category LIKE '%$search%' OR location LIKE '%$search%')";
}

if (!empty($filter_type)) {
    $where .= " AND report_type = '$filter_type'";
}

if (!empty($filter_status)) {
    $where .= " AND status = '$filter_status'";
}

// ========================
// PAGINATION
// ========================
$per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $per_page;

// Total count
$count_query = "SELECT COUNT(*) as total FROM items $where";
$count_result = mysqli_query($conn, $count_query);
$total_items = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_items / $per_page);

// Get reports
$reports_query = "SELECT * FROM items $where ORDER BY date_reported DESC LIMIT $offset, $per_page";
$reports_result = mysqli_query($conn, $reports_query);

// Stats
$total_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id'";
$total_all = mysqli_fetch_assoc(mysqli_query($conn, $total_query))['count'];

$lost_count_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id' AND report_type = 'lost'";
$total_lost = mysqli_fetch_assoc(mysqli_query($conn, $lost_count_query))['count'];

$found_count_query = "SELECT COUNT(*) as count FROM items WHERE user_id = '$user_id' AND report_type = 'found'";
$total_found = mysqli_fetch_assoc(mysqli_query($conn, $found_count_query))['count'];
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
    <title>My Reports - Campus Find</title>
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

        /* Navbar */
        .navbar {
            background-color: #159f35;
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

        .navbar h1 {
            font-size: 28px;
        }

        .menu-container {
            position: relative;
            display: inline-block;
        }

        .hamburger {
            background-color: transparent;
            border: none;
            cursor: pointer;
            padding: 10px;
        }

        .hamburger div {
            width: 25px;
            height: 3px;
            background-color: white;
            margin: 5px 0;
            border-radius: 2px;
        }

        .hamburger:hover div {
            background-color: #e6f8e6;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            left: 0;
            top: 45px;
            background-color: white;
            width: 180px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 15px;
            color: #271b1b;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .dropdown-menu a:hover {
            background-color: #f5f5f5;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
            color: #ff1900;
        }

        .dropdown-menu a:last-child:hover {
            background-color: #ffeaea;
        }

        .nav-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: white;
            color: #159f35;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .nav-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Container */
        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #159f35;
            display: inline-block;
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

        /* Stats */
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            flex: 1;
            min-width: 150px;
            background-color: white;
            border-radius: 12px;
            border: 1px solid #000000;
            padding: 20px;
            text-align: center;
        }

        .stat-box:hover {
            border-color: #159f35;
            box-shadow: 0 3px 10px rgba(21, 159, 53, 0.1);
        }

        .stat-box .icon {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .stat-box .num {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-box .lbl {
            font-size: 13px;
            color: #888;
            margin-top: 3px;
        }

        .stat-box.total .num {
            color: #6f42c1;
        }

        .stat-box.lost .num {
            color: #dc3545;
        }

        .stat-box.found .num {
            color: #007bff;
        }

        /* Filters */
        .filters-card {
            background-color: white;
            border-radius: 12px;
            border: 1px solid #000000;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .filters-row input,
        .filters-row select {
            padding: 10px 15px;
            border: 1px solid #0c0b0b;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .filters-row input {
            flex: 1;
            min-width: 200px;
        }

        .filters-row input:focus,
        .filters-row select:focus {
            outline: none;
            border-color: #00ff33;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-gray {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-gray:hover {
            background-color: #545b62;
        }

        /* Table */
        .table-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #000000;
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

        .table-card-header span {
            font-size: 13px;
            color: #888;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 18px;
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

        .status.claimed {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status.received {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Item image thumbnail */
        .item-thumb {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .no-thumb {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid #ddd;
        }

        /* Action Buttons */
        .action-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }

        .action-btn.edit {
            background-color: #007bff;
            color: white;
        }

        .action-btn.edit:hover {
            background-color: #0056b3;
        }

        .action-btn.delete {
            background-color: #e74c3c;
            color: white;
        }

        .action-btn.delete:hover {
            background-color: #c0392b;
        }

        .action-btn.disabled {
            background-color: #ccc;
            color: #888;
            cursor: not-allowed;
            pointer-events: none;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }

        .no-data span {
            font-size: 50px;
            display: block;
            margin-bottom: 10px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #ddd;
        }

        .pagination a {
            background-color: white;
            color: #333;
        }

        .pagination a:hover {
            background-color: #e6f8e6;
            border-color: #159f35;
            color: #159f35;
        }

        .pagination span.active {
            background-color: #159f35;
            color: white;
            border-color: #159f35;
        }

        .pagination span.disabled {
            background-color: #f0f0f0;
            color: #ccc;
        }

        /* Modal */
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
            max-width: 400px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-buttons button,
        .modal-buttons a {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <div class="menu-container">
                <button class="hamburger" id="hamburgerBtn">
                    <div></div>
                    <div></div>
                    <div></div>
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="user_dashboard.php">Dashboard</a>
                    <a href="settings.php">Settings</a>
                    <a href="#" onclick="logoutNow()">Logout</a>
                </div>
            </div>
            <h1>Campus-Find</h1>
        </div>

        <a href="profile.php" class="nav-avatar" title="My Profile">
            <?php if ($has_photo) { ?>
                <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
            <?php } else { ?>
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            <?php } ?>
        </a>
    </div>

    <div class="container">

        <h2 class="page-title">📋 My Reports</h2>

        <?php if (isset($success_msg)) { ?>
            <div class="msg success">✅ <?php echo $success_msg; ?></div>
        <?php } ?>

        <?php if (isset($error_msg)) { ?>
            <div class="msg error">❌ <?php echo $error_msg; ?></div>
        <?php } ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box total">
                <div class="icon">📋</div>
                <div class="num"><?php echo $total_all; ?></div>
                <div class="lbl">Total Reports</div>
            </div>
            <div class="stat-box lost">
                <div class="icon">🔴</div>
                <div class="num"><?php echo $total_lost; ?></div>
                <div class="lbl">Lost Items</div>
            </div>
            <div class="stat-box found">
                <div class="icon">🔵</div>
                <div class="num"><?php echo $total_found; ?></div>
                <div class="lbl">Found Items</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET">
                <div class="filters-row">
                    <input type="text" name="search" placeholder="🔍 Search by name, category, location..."
                        value="<?php echo htmlspecialchars($search); ?>">

                    <select name="type">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo ($filter_type == 'lost') ? 'selected' : ''; ?>>🔴 Lost</option>
                        <option value="found" <?php echo ($filter_type == 'found') ? 'selected' : ''; ?>>🔵 Found</option>
                    </select>

                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>⏳ Pending
                        </option>
                        <option value="claimed" <?php echo ($filter_status == 'claimed') ? 'selected' : ''; ?>>🟡 Claimed
                        </option>
                        <option value="received" <?php echo ($filter_status == 'received') ? 'selected' : ''; ?>>🟢
                            Received</option>
                    </select>

                    <button type="submit" class="btn btn-green">🔍 Search</button>
                    <a href="my_reports.php" class="btn btn-gray">✖ Clear</a>
                </div>
            </form>
        </div>

        <!-- Reports Table -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>📋 Your Reports</h3>
                <span>Showing <?php echo $total_items; ?> report(s)</span>
            </div>

            <?php if (mysqli_num_rows($reports_result) > 0) { ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Image</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $serial = $offset + 1;
                        while ($row = mysqli_fetch_assoc($reports_result)) {
                            $type = strtolower($row['report_type']);
                            $status = strtolower($row['status']);

                            // Check if claimed (only pending/approved block the delete button)
                            $claim_check = "SELECT * FROM claims WHERE item_id = '" . $row['id'] . "' AND status IN ('pending','approved')";
                            $is_claimed = (mysqli_num_rows(mysqli_query($conn, $claim_check)) > 0);
                            ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td>
                                    <?php if (!empty($row['image']) && file_exists('uploads/' . $row['image'])) { ?>
                                        <img src="uploads/<?php echo $row['image']; ?>" class="item-thumb" alt="Item">
                                    <?php } else { ?>
                                        <div class="no-thumb">📷</div>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td>
                                    <?php
                                    if ($type == 'lost') {
                                        echo '<span class="status lost">🔴 Lost</span>';
                                    } else {
                                        echo '<span class="status found">🔵 Found</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($status == 'received') {
                                        echo '<span class="status received">🟢 Received</span>';
                                    } else if ($is_claimed || $status == 'claimed') {
                                        echo '<span class="status claimed">🟡 Claimed</span>';
                                    } else {
                                        echo '<span class="status pending">⏳ Pending</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$is_claimed && $status != 'received' && $status != 'claimed') { ?>
                                        <a href="edit_report.php?id=<?php echo $row['id']; ?>" class="action-btn edit">✏️ Edit</a>
                                        <button class="action-btn delete"
                                            onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['item_name'], ENT_QUOTES); ?>')">🗑️
                                            Delete</button>
                                    <?php } else { ?>
                                        <span class="action-btn disabled">✏️ Edit</span>
                                        <span class="action-btn disabled">🗑️ Delete</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1) { ?>
                    <div class="pagination">
                        <?php
                        // Build query string for pagination
                        $params = [];
                        if (!empty($search))
                            $params[] = "search=" . urlencode($search);
                        if (!empty($filter_type))
                            $params[] = "type=" . urlencode($filter_type);
                        if (!empty($filter_status))
                            $params[] = "status=" . urlencode($filter_status);
                        $query_string = !empty($params) ? '&' . implode('&', $params) : '';

                        // Previous
                        if ($page > 1) {
                            echo '<a href="?page=' . ($page - 1) . $query_string . '">← Prev</a>';
                        } else {
                            echo '<span class="disabled">← Prev</span>';
                        }

                        // Page numbers
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == $page) {
                                echo '<span class="active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . $query_string . '">' . $i . '</a>';
                            }
                        }

                        // Next
                        if ($page < $total_pages) {
                            echo '<a href="?page=' . ($page + 1) . $query_string . '">Next →</a>';
                        } else {
                            echo '<span class="disabled">Next →</span>';
                        }
                        ?>
                    </div>
                <?php } ?>

            <?php } else { ?>
                <div class="no-data">
                    <span>📭</span>
                    <p>No reports found.</p>
                    <?php if (!empty($search) || !empty($filter_type) || !empty($filter_status)) { ?>
                        <a href="my_reports.php" class="btn btn-green" style="margin-top: 10px; display: inline-block;">Clear
                            Filters</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="border: 2px solid #e74c3c;">
            <h3>🗑️ Delete Report?</h3>
            <p>Are you sure you want to delete "<strong id="deleteItemName"></strong>"? This cannot be undone.</p>
            <div class="modal-buttons">
                <button class="btn btn-gray" onclick="closeDeleteModal()"
                    style="background-color: #6c757d; color: white;">Cancel</button>
                <a href="#" id="deleteLink" class="btn" style="background-color: #e74c3c; color: white;">Yes, Delete</a>
            </div>
        </div>
    </div>

    <script>
        // Hamburger
        var hamburgerBtn = document.getElementById('hamburgerBtn');
        var dropdownMenu = document.getElementById('dropdownMenu');

        hamburgerBtn.onclick = function () {
            dropdownMenu.style.display = (dropdownMenu.style.display === 'block') ? 'none' : 'block';
        };

        document.onclick = function (e) {
            if (e.target !== hamburgerBtn && !hamburgerBtn.contains(e.target)) {
                dropdownMenu.style.display = 'none';
            }
        };

        // Delete Modal
        function confirmDelete(id, name) {
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteLink').href = 'my_reports.php?delete_id=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });

        // Logout
        function logoutNow() {
            window.location.href = "logout.php";
        }
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>