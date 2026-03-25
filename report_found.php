<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

if (isset($_POST['report'])) {

    $item_name= mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location = mysqli_real_escape_string($conn, trim($_POST['location']));
    $date_reported = mysqli_real_escape_string($conn, $_POST['date_reported']);
    $report_type = "found";
    $status = "pending";
    $image = "";

    if (!empty($_FILES['image']['name'])) {
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
            echo "<script>alert('Invalid file type! Only JPG, PNG, GIF, WEBP images are allowed.'); window.history.back();</script>";
            exit();
        }

        $image_name = time() . "_" . basename($_FILES['image']['name']);
        $image_tmp = $_FILES['image']['tmp_name'];
        if (move_uploaded_file($image_tmp, "uploads/" . $image_name)) {
            $image = $image_name;
        } else {
            echo "<script>alert('Image upload failed. Please try again.'); window.history.back();</script>";
            exit();
        }
    }

    $get_user = "SELECT id FROM users WHERE username='" . mysqli_real_escape_string($conn, $username) . "'";
    $result = mysqli_query($conn, $get_user);
    $row = mysqli_fetch_assoc($result);
    $user_id = $row['id'];

    $insert = "INSERT INTO items (user_id, item_name, category, description, location, date_reported, report_type, status, image) 
               VALUES ('$user_id', '$item_name', '$category', '$description', '$location', '$date_reported', '$report_type', '$status', '$image')";

    if (mysqli_query($conn, $insert)) {
        echo "<script>alert('Found item reported successfully! Waiting for admin approval.'); window.location.href='user_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to report item!');</script>";
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Found Item - Campus Find</title>
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

        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 16px;
        }

        .container {
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-box {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #000;
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #f1c40f;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #000;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: none;
        }

        .form-group input[type="file"] {
            padding: 8px;
        }

        .required-text {
            font-size: 12px;
            color: #e74c3c;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #f1c40f;
            color: black;
            border: 1px solid #000;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-submit:hover {
            background-color: #d4ac0d;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #159f35;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <h1>Campus-Find</h1>
        <a href="user_dashboard.php">← Back to Dashboard</a>
    </div>

    <div class="container">
        <div class="form-box">
            <h2>Report Found Item</h2>

            <form action="report_found.php" method="post" enctype="multipart/form-data">

                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" placeholder="Enter item name" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="" disabled selected>Select Category</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Accessories">Accessories</option>
                        <option value="Documents">Documents</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Books">Books</option>
                        <option value="ID Card">ID Card</option>
                        <option value="Others">Others</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Describe the item (color, brand, etc.)"></textarea>
                </div>

                <div class="form-group">
                    <label>Location Found</label>
                    <input type="text" name="location" placeholder="Where did you find it?" required>
                </div>

                <div class="form-group">
                    <label>Date Found</label>
                    <input type="date" name="date_reported" required>
                </div>

                <div class="form-group">
                    <label>Upload Image <span class="required-text">(Required)</span></label>
                    <input type="file" name="image" accept="image/*" required id="imgInput"
                        onchange="previewImage(this)">
                    <img id="imgPreview" src="" alt="Preview"
                        style="display:none;margin-top:10px;max-width:100%;max-height:200px;border-radius:8px;border:2px solid #e0e0e0;object-fit:contain;">
                </div>

                <button type="submit" name="report" class="btn-submit">Report Found Item</button>

            </form>

            <div class="back-link">
                <a href="user_dashboard.php">Cancel</a>
            </div>
        </div>
    </div>

    </div>

    <script>
        function previewImage(input) {
            var img = document.getElementById('imgPreview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; img.style.display = 'block'; };
                reader.readAsDataURL(input.files[0]);
            } else { img.style.display = 'none'; }
        }
    </script>
</body>

</html>