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

$success_msg = "";
$error_msg = "";

// Check if ID provided
if (!isset($_GET['id'])) {
    header("Location: my_reports.php");
    exit();
}

$item_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get item - must belong to this user
$item_query = "SELECT * FROM items WHERE id = '$item_id' AND user_id = '$user_id'";
$item_result = mysqli_query($conn, $item_query);

if (mysqli_num_rows($item_result) == 0) {
    header("Location: my_reports.php");
    exit();
}

$item = mysqli_fetch_assoc($item_result);

// Check if claimed - can't edit
$claim_check = "SELECT * FROM claims WHERE item_id = '$item_id'";
$is_claimed = (mysqli_num_rows(mysqli_query($conn, $claim_check)) > 0);

if ($is_claimed || strtolower($item['status']) == 'received' || strtolower($item['status']) == 'claimed') {
    header("Location: my_reports.php");
    exit();
}

// ========================
// UPDATE REPORT
// ========================
if (isset($_POST['update_report'])) {
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location = mysqli_real_escape_string($conn, trim($_POST['location']));
    $date_reported = mysqli_real_escape_string($conn, $_POST['date_reported']);
    $report_type = mysqli_real_escape_string($conn, $_POST['report_type']);

    if (empty($item_name) || empty($category) || empty($location) || empty($date_reported) || empty($report_type)) {
        $error_msg = "All required fields must be filled!";
    } else {
        // Handle image upload
        $image_name = $item['image']; // Keep old image by default

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $file = $_FILES['image'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($file_ext, $allowed)) {
                $error_msg = "Only JPG, JPEG, PNG, GIF, WEBP images allowed!";
            } else if ($file['size'] > 5 * 1024 * 1024) {
                $error_msg = "Image must be less than 5MB!";
            } else {
                // Delete old image
                if (!empty($item['image']) && file_exists('uploads/' . $item['image'])) {
                    unlink('uploads/' . $item['image']);
                }

                // Upload new image
                $image_name = 'item_' . $item_id . '_' . time() . '.' . $file_ext;
                move_uploaded_file($file['tmp_name'], 'uploads/' . $image_name);
            }
        }

        // Remove image if user clicked remove
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if (!empty($item['image']) && file_exists('uploads/' . $item['image'])) {
                unlink('uploads/' . $item['image']);
            }
            $image_name = '';
        }

        if (empty($error_msg)) {
            $update = "UPDATE items SET 
                        item_name = '$item_name', 
                        category = '$category', 
                        description = '$description', 
                        location = '$location', 
                        date_reported = '$date_reported', 
                        report_type = '$report_type', 
                        image = '$image_name' 
                        WHERE id = '$item_id' AND user_id = '$user_id'";

            if (mysqli_query($conn, $update)) {
                header("Location: my_reports.php?msg=updated");
                exit();
            } else {
                $error_msg = "Failed to update report.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode.css">
    <script src="dark-mode.js"></script>
    <script src="page-loader.js"></script>

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
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Container */
        .container {
            padding: 30px;
            max-width: 800px;
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

        /* Form Card */
        .form-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
        }

        .form-card-header {
            padding: 18px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .form-card-header h3 {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .form-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #159f35;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Two columns */
        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Current Image */
        .current-image-box {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .current-image-box img {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .current-image-box .img-info {
            flex: 1;
        }

        .current-image-box .img-info p {
            font-size: 13px;
            color: #666;
        }

        .remove-img-btn {
            padding: 6px 14px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .remove-img-btn:hover {
            background-color: #c0392b;
        }

        .no-image-box {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        /* Image preview */
        .image-preview {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #159f35;
            margin-top: 10px;
            display: none;
        }

        .file-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        /* Type selector */
        .type-selector {
            display: flex;
            gap: 15px;
        }

        .type-option {
            flex: 1;
            text-align: center;
        }

        .type-option input[type="radio"] {
            display: none;
        }

        .type-option label {
            display: block;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .type-option label:hover {
            border-color: #159f35;
        }

        .type-option input[type="radio"]:checked+label.lost-label {
            border-color: #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }

        .type-option input[type="radio"]:checked+label.found-label {
            border-color: #007bff;
            background-color: #cce5ff;
            color: #004085;
        }

        /* Buttons */
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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
        }

        .btn-gray:hover {
            background-color: #545b62;
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
                    <a href="my_reports.php">My Reports</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <h1>Edit Report</h1>
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

        <h2 class="page-title">✏️ Edit Report</h2>

        <?php if ($success_msg != "") { ?>
            <div class="msg success">✅ <?php echo $success_msg; ?></div>
        <?php } ?>

        <?php if ($error_msg != "") { ?>
            <div class="msg error">❌ <?php echo $error_msg; ?></div>
        <?php } ?>

        <div class="form-card">
            <div class="form-card-header">
                <h3>📝 Edit Item Details</h3>
            </div>

            <div class="form-body">
                <form method="POST" enctype="multipart/form-data" id="editForm">
                    <input type="hidden" name="remove_image" id="removeImageFlag" value="0">

                    <!-- Report Type -->
                    <div class="form-group">
                        <label>Report Type <span class="required">*</span></label>
                        <div class="type-selector">
                            <div class="type-option">
                                <input type="radio" name="report_type" id="typeLost" value="lost" <?php echo ($item['report_type'] == 'lost') ? 'checked' : ''; ?>>
                                <label for="typeLost" class="lost-label">🔴 Lost Item</label>
                            </div>
                            <div class="type-option">
                                <input type="radio" name="report_type" id="typeFound" value="found" <?php echo ($item['report_type'] == 'found') ? 'checked' : ''; ?>>
                                <label for="typeFound" class="found-label">🔵 Found Item</label>
                            </div>
                        </div>
                    </div>

                    <!-- Item Name & Category -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Item Name <span class="required">*</span></label>
                            <input type="text" name="item_name"
                                value="<?php echo htmlspecialchars($item['item_name']); ?>"
                                placeholder="e.g. Blue Backpack" required>
                        </div>
                        <div class="form-group">
                            <label>Category <span class="required">*</span></label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <?php
                                $categories = ['Electronics', 'Accessories', 'Documents', 'Clothing', 'Books', 'ID Card', 'Others'];
                                foreach ($categories as $cat) {
                                    $selected = ($item['category'] == $cat) ? 'selected' : '';
                                    echo "<option value='$cat' $selected>$cat</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"
                            placeholder="Describe the item in detail..."><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>

                    <!-- Location & Date -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location <span class="required">*</span></label>
                            <input type="text" name="location"
                                value="<?php echo htmlspecialchars($item['location']); ?>"
                                placeholder="e.g. Library 2nd Floor" required>
                        </div>
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="date_reported" value="<?php echo $item['date_reported']; ?>"
                                required>
                        </div>
                    </div>

                    <!-- Current Image -->
                    <div class="form-group">
                        <label>Item Image</label>

                        <?php if (!empty($item['image']) && file_exists('uploads/' . $item['image'])) { ?>
                            <div class="current-image-box" id="currentImageBox">
                                <img src="uploads/<?php echo $item['image']; ?>" alt="Current Image">
                                <div class="img-info">
                                    <p>📷 Current image</p>
                                    <p style="font-size: 12px; color: #999;"><?php echo $item['image']; ?></p>
                                </div>
                                <button type="button" class="remove-img-btn" onclick="removeCurrentImage()">🗑️
                                    Remove</button>
                            </div>
                        <?php } else { ?>
                            <div class="no-image-box">
                                📷 No image uploaded
                            </div>
                        <?php } ?>

                        <input type="file" name="image" id="imageInput" accept="image/*">
                        <p class="file-hint">Allowed: JPG, PNG, GIF, WEBP — Max: 5MB — Leave empty to keep current image
                        </p>
                        <img src="" class="image-preview" id="imagePreview" alt="Preview">
                    </div>

                    <!-- Buttons -->
                    <div class="form-buttons">
                        <button type="submit" name="update_report" class="btn btn-green">✅ Update Report</button>
                        <a href="my_reports.php" class="btn btn-gray">← Back to Reports</a>
                    </div>
                </form>
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

        // Image Preview
        document.getElementById('imageInput').addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    var preview = document.getElementById('imagePreview');
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Remove Current Image
        function removeCurrentImage() {
            var box = document.getElementById('currentImageBox');
            if (box) {
                box.style.display = 'none';
            }
            document.getElementById('removeImageFlag').value = '1';
        }
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>