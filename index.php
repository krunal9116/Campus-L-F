<?php
session_start();
require_once __DIR__ . '/config.php';

$error = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Find user by username or email
    $username = mysqli_real_escape_string($conn, $username);
    $check = "SELECT * FROM users WHERE username='$username' OR email='$username'";
    $result = mysqli_query($conn, $check);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Verify hashed password
        if (password_verify($password, $row['password'])) {

            if ($row['role'] === 'pending_admin') {
                $error = "Your admin request is pending approval. You will be notified via email once a decision is made.";
            } else {
                // Login success
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];

                if ($row['role'] == "admin") {
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    header("Location: user_dashboard.php");
                    exit();
                }
            }
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Username or email not found!";
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
    <title>Campus-Find</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Righteous&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-image: url(images/loginscreen.png);
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        #div {
            width: 400px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            text-align: center;
            border: 2px solid black;
        }

        input[type="text"],
        input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid black;
        }

        input[type="submit"] {
            width: 95%;
            padding: 10px;
            background-color: #159f35;
            color: #ffffff;
            border-radius: 8px;
            border: 1px solid black;
            cursor: pointer;
            margin-top: 10px;
        }

        input[type="submit"]:hover {
            background-color: #035815;
        }

        h5 {
            margin-top: 20px;
            color: #333;
        }

        h3 {
            font-family: 'Righteous', cursive;
            font-weight: 400;
            letter-spacing: 1.5px;
        }
        .error-msg {
            color: red;
            font-size: 14px;
            margin: 5px 0;
            background-color: #f8d7da;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <div id="div">
        <form action="" method="post">
            <h3>Campus-Find</h3>

            <?php if ($error != "") { ?>
                <p class="error-msg">❌ <?php echo $error; ?></p>
            <?php } ?>

            <input type="text" name="username" placeholder="Enter Username or Email" required />
            <input type="password" name="password" placeholder="Enter Password" required />
            <input type="submit" value="LOGIN" name="login" />
            <h6>New User? <a href="register.php">Register Here</a> &nbsp;|&nbsp; <a href="forgot_password.php">Forgot Password?</a></h6>
            <h5>Welcome to the Campus Lost & Found Management System</h5>
        </form>
    </div>
</body>

</html>