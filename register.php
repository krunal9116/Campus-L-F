<?php
session_start();
require_once __DIR__ . '/config.php';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Righteous&display=swap"
        rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-image: url(images/registerscreen.png);
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        #div {
            width: 480px;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            text-align: center;
            border: 2px solid black;
        }

        h3 {
            font-family: 'Righteous', cursive;
            font-weight: 400;
            letter-spacing: 1.5px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid black;
            font-size: 14px;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: #159f35;
        }

        .btn {
            width: 95%;
            padding: 10px;
            background-color: #159f35;
            color: #ffffff;
            border-radius: 8px;
            border: 1px solid black;
            cursor: pointer;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .btn:hover {
            background-color: #035815;
        }

        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        h5 {
            margin-top: 20px;
            color: #333;
        }

        #role {
            width: 95%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid black;
        }

        .error-msg {
            color: red;
            font-size: 14px;
            margin: 5px 0;
        }

        .password-hint {
            font-size: 11px;
            text-align: left;
            margin: 5px 15px;
        }

        .password-hint span {
            display: block;
            margin: 3px 0;
            color: #999;
            transition: color 0.3s;
        }

        .password-hint span.valid {
            color: #159f35;
        }

        .password-hint span.invalid {
            color: #e74c3c;
        }

        /* OTP Section */
        .otp-section {
            display: none;
        }

        .otp-input-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 15px 0;
        }

        .otp-input {
            width: 45px;
            height: 45px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            border: 2px solid #ccc;
            border-radius: 8px;
        }

        .otp-input:focus {
            outline: none;
            border-color: #159f35;
        }

        .timer {
            color: #666;
            font-size: 13px;
            margin-top: 10px;
        }

        .timer span {
            font-weight: 600;
            color: #333;
        }

        .resend-link {
            margin-top: 10px;
            font-size: 13px;
        }

        .resend-link a {
            color: #159f35;
            text-decoration: none;
            font-weight: 500;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        .resend-link a.disabled {
            color: #ccc;
            pointer-events: none;
        }

        .login-link {
            margin-top: 12px;
            font-size: 13px;
        }

        .login-link a {
            color: #159f35;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            margin-top: 10px;
            font-size: 13px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
        }

        .back-link a:hover {
            color: #159f35;
        }

        .email-display {
            color: #159f35;
            font-weight: 600;
            font-size: 14px;
        }

        /* Loading Spinner */
        .loading {
            display: none;
            text-align: center;
            margin: 15px 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #159f35;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading p {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        /* Message Box */
        .message-box {
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 13px;
            display: none;
        }

        .message-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>
    <div id="div">
        <h3>Campus-Find</h3>

        <div class="message-box" id="messageBox"></div>

        <!-- Step 1: Registration Form -->
        <div id="step1">
            <form id="registerForm">
                <input type="text" name="username" id="username" placeholder="Enter Username" required />

                <input type="email" name="email" id="email" placeholder="Enter Email" required />

                <input type="password" name="password" id="password" placeholder="Enter Password" required>
                <div class="password-hint">
                    <span id="length">✗ At least 8 characters</span>
                    <span id="uppercase">✗ 1 uppercase letter (A-Z)</span>
                    <span id="lowercase">✗ 1 lowercase letter (a-z)</span>
                    <span id="number">✗ 1 number (0-9)</span>
                    <span id="special">✗ 1 special character (!@#$%^&*)</span>
                </div>

                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password"
                    required />
                <p class="error-msg" id="errorMsg" style="display: none;">❌ Passwords do not match!</p>

                <select id="role" name="role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>

                <button type="submit" class="btn" id="sendOtpBtn">SEND OTP & REGISTER</button>
            </form>

            <div class="loading" id="loading1">
                <div class="spinner"></div>
                <p>Sending OTP to your email...</p>
            </div>

            <h6>Back to login Page <a href="index.php">Click Here</a></h6>
            <h5>Welcome to the Campus Lost & Found Management System</h5>
        </div>

        <!-- Step 2: OTP Verification -->
        <div id="step2" class="otp-section">
            <p id="otpInstruction" style="color: #666; font-size: 14px; margin-bottom: 5px;">
                Enter the 6-digit OTP sent to
            </p>
            <p class="email-display" id="emailDisplay"></p>

            <form id="otpForm">
                <div class="otp-input-container">
                    <input type="text" class="otp-input" maxlength="1" data-index="0">
                    <input type="text" class="otp-input" maxlength="1" data-index="1">
                    <input type="text" class="otp-input" maxlength="1" data-index="2">
                    <input type="text" class="otp-input" maxlength="1" data-index="3">
                    <input type="text" class="otp-input" maxlength="1" data-index="4">
                    <input type="text" class="otp-input" maxlength="1" data-index="5">
                </div>

                <input type="hidden" id="otpValue">
                <input type="hidden" id="otpEmail">
                <input type="hidden" id="otpUsername">
                <input type="hidden" id="otpPassword">
                <input type="hidden" id="otpRole">

                <button type="submit" class="btn" id="verifyBtn">VERIFY & REGISTER</button>
            </form>

            <div class="loading" id="loading2">
                <div class="spinner"></div>
                <p>Verifying OTP...</p>
            </div>

            <div class="timer" id="timer">
                OTP expires in: <span id="countdown">05:00</span>
            </div>

            <div class="login-link">
              Go to <a href="index.php">Login Page</a>
            </div>

            <div class="resend-link">
                Didn't receive? <a href="#" id="resendBtn" class="disabled">Resend OTP</a>
            </div>

            <div class="back-link">
                <a href="#" id="backBtn">← Change Email</a>
            </div>
        </div>
    </div>

    <script>
        // ========================
        // Elements
        // ========================
        var registerForm = document.getElementById('registerForm');
        var otpForm = document.getElementById('otpForm');
        var step1 = document.getElementById('step1');
        var step2 = document.getElementById('step2');
        var messageBox = document.getElementById('messageBox');
        var otpInputs = document.querySelectorAll('.otp-input');
        var countdownEl = document.getElementById('countdown');
        var resendBtn = document.getElementById('resendBtn');
        var timerInterval;

        var password = document.getElementById('password');
        var confirmPassword = document.getElementById('confirm_password');
        var errorMsg = document.getElementById('errorMsg');

        // ========================
        // Password Match Check
        // ========================
        confirmPassword.addEventListener('input', function () {
            if (password.value !== confirmPassword.value) {
                errorMsg.style.display = 'block';
            } else {
                errorMsg.style.display = 'none';
            }
        });

        password.addEventListener('input', function () {
            if (confirmPassword.value !== '' && password.value !== confirmPassword.value) {
                errorMsg.style.display = 'block';
            } else {
                errorMsg.style.display = 'none';
            }
        });

        // ========================
        // Password Strength Check
        // ========================
        password.addEventListener('input', function () {
            var value = password.value;

            if (value.length >= 8) {
                document.getElementById('length').textContent = '✓ At least 8 characters';
                document.getElementById('length').className = 'valid';
            } else {
                document.getElementById('length').textContent = '✗ At least 8 characters';
                document.getElementById('length').className = 'invalid';
            }

            if (/[A-Z]/.test(value)) {
                document.getElementById('uppercase').textContent = '✓ 1 uppercase letter (A-Z)';
                document.getElementById('uppercase').className = 'valid';
            } else {
                document.getElementById('uppercase').textContent = '✗ 1 uppercase letter (A-Z)';
                document.getElementById('uppercase').className = 'invalid';
            }

            if (/[a-z]/.test(value)) {
                document.getElementById('lowercase').textContent = '✓ 1 lowercase letter (a-z)';
                document.getElementById('lowercase').className = 'valid';
            } else {
                document.getElementById('lowercase').textContent = '✗ 1 lowercase letter (a-z)';
                document.getElementById('lowercase').className = 'invalid';
            }

            if (/[0-9]/.test(value)) {
                document.getElementById('number').textContent = '✓ 1 number (0-9)';
                document.getElementById('number').className = 'valid';
            } else {
                document.getElementById('number').textContent = '✗ 1 number (0-9)';
                document.getElementById('number').className = 'invalid';
            }

            if (/[!@#$%^&*(),.?":{}|<>]/.test(value)) {
                document.getElementById('special').textContent = '✓ 1 special character (!@#$%^&*)';
                document.getElementById('special').className = 'valid';
            } else {
                document.getElementById('special').textContent = '✗ 1 special character (!@#$%^&*)';
                document.getElementById('special').className = 'invalid';
            }
        });

        // ========================
        // Send OTP
        // ========================
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var username = document.getElementById('username').value.trim();
            var email = document.getElementById('email').value.trim();
            var pass = password.value;
            var confirmPass = confirmPassword.value;
            var role = document.getElementById('role').value;

            // Validations
            if (username === '' || email === '' || pass === '' || confirmPass === '' || role === '') {
                showMessage('All fields are required!', 'error');
                return;
            }

            if (pass !== confirmPass) {
                showMessage('Passwords do not match!', 'error');
                return;
            }

            if (pass.length < 8) {
                showMessage('Password must be at least 8 characters!', 'error');
                return;
            }

            if (!/[A-Z]/.test(pass)) {
                showMessage('Password must contain at least 1 uppercase letter!', 'error');
                return;
            }

            if (!/[a-z]/.test(pass)) {
                showMessage('Password must contain at least 1 lowercase letter!', 'error');
                return;
            }

            if (!/[0-9]/.test(pass)) {
                showMessage('Password must contain at least 1 number!', 'error');
                return;
            }

            if (!/[!@#$%^&*(),.?":{}|<>]/.test(pass)) {
                showMessage('Password must contain at least 1 special character!', 'error');
                return;
            }

            sendOTP(username, email);
        });

        function sendOTP(username, email) {
            // Show loading
            document.getElementById('loading1').style.display = 'block';
            document.getElementById('sendOtpBtn').style.display = 'none';
            messageBox.style.display = 'none';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'send_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                document.getElementById('loading1').style.display = 'none';
                document.getElementById('sendOtpBtn').style.display = 'block';

                try {
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        // Store data in hidden fields
                        document.getElementById('otpEmail').value = email;
                        document.getElementById('otpUsername').value = username;
                        document.getElementById('otpPassword').value = password.value;
                        document.getElementById('otpRole').value = document.getElementById('role').value;
                        document.getElementById('emailDisplay').textContent = email;

                        // Switch to OTP step
                        step1.style.display = 'none';
                        step2.style.display = 'block';
                        messageBox.style.display = 'none';

                        // Start countdown
                        startCountdown(300);

                        // Focus first OTP input
                        otpInputs[0].focus();
                    } else {
                        showMessage(response.message, 'error');
                    }
                } catch (e) {
                    showMessage('Something went wrong. Please try again.', 'error');
                }
            };

            xhr.onerror = function () {
                document.getElementById('loading1').style.display = 'none';
                document.getElementById('sendOtpBtn').style.display = 'block';
                showMessage('Network error. Please check your connection.', 'error');
            };

            xhr.send('username=' + encodeURIComponent(username) + '&email=' + encodeURIComponent(email));
        }

        // ========================
        // OTP Input Handling
        // ========================
        otpInputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');

                if (this.value.length === 1 && index < 5) {
                    otpInputs[index + 1].focus();
                }
                updateOTPValue();
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            // Paste support
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var paste = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                for (var i = 0; i < paste.length; i++) {
                    if (otpInputs[i]) {
                        otpInputs[i].value = paste[i];
                    }
                }
                if (paste.length > 0) {
                    otpInputs[Math.min(paste.length, 5)].focus();
                }
                updateOTPValue();
            });
        });

        function updateOTPValue() {
            var otp = '';
            otpInputs.forEach(function (input) {
                otp += input.value;
            });
            document.getElementById('otpValue').value = otp;
        }

        // ========================
        // Verify OTP
        // ========================
        otpForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var otp = document.getElementById('otpValue').value;

            if (otp.length !== 6) {
                showMessage('Please enter complete 6-digit OTP!', 'error');
                return;
            }

            // Show loading
            document.getElementById('loading2').style.display = 'block';
            document.getElementById('verifyBtn').style.display = 'none';
            messageBox.style.display = 'none';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'verify_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                document.getElementById('loading2').style.display = 'none';
                document.getElementById('verifyBtn').style.display = 'block';

                try {
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        showMessage(response.message, 'success');
                        if (response.pending) {
                            // Admin pending approval - hide OTP form, timer, resend, change email, OTP text
                            document.getElementById('otpForm').style.display = 'none';
                            document.getElementById('timer').style.display = 'none';
                            document.getElementById('emailDisplay').style.display = 'none';
                            document.getElementById('otpInstruction').style.display = 'none';
                            document.querySelector('.resend-link').style.display = 'none';
                            document.querySelector('.back-link').style.display = 'none';
                            clearInterval(timerInterval);
                        } else {
                            setTimeout(function () {
                                window.location.href = response.redirect || 'user_dashboard.php';
                            }, 1500);
                        }
                    } else {
                        showMessage(response.message, 'error');
                        // Clear OTP inputs
                        otpInputs.forEach(function (input) {
                            input.value = '';
                        });
                        otpInputs[0].focus();
                    }
                } catch (e) {
                    showMessage('Something went wrong. Please try again.', 'error');
                }
            };

            xhr.onerror = function () {
                document.getElementById('loading2').style.display = 'none';
                document.getElementById('verifyBtn').style.display = 'block';
                showMessage('Network error. Please check your connection.', 'error');
            };

            var data = 'otp=' + otp +
                '&email=' + encodeURIComponent(document.getElementById('otpEmail').value) +
                '&username=' + encodeURIComponent(document.getElementById('otpUsername').value) +
                '&password=' + encodeURIComponent(document.getElementById('otpPassword').value) +
                '&role=' + encodeURIComponent(document.getElementById('otpRole').value);

            xhr.send(data);
        });

        // ========================
        // Countdown Timer
        // ========================
        function startCountdown(seconds) {
            clearInterval(timerInterval);
            var remaining = seconds;

            resendBtn.className = 'disabled';

            timerInterval = setInterval(function () {
                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                countdownEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

                remaining--;

                if (remaining < 0) {
                    clearInterval(timerInterval);
                    countdownEl.textContent = 'Expired';
                    countdownEl.style.color = 'red';
                    resendBtn.className = '';
                }
            }, 1000);
        }

        // ========================
        // Resend OTP
        // ========================
        resendBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (this.classList.contains('disabled')) return;

            var username = document.getElementById('otpUsername').value;
            var email = document.getElementById('otpEmail').value;

            // Clear old OTP
            otpInputs.forEach(function (input) {
                input.value = '';
            });
            countdownEl.style.color = '#333';

            // Show loading on step2
            document.getElementById('loading2').style.display = 'block';
            document.getElementById('verifyBtn').style.display = 'none';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'send_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                document.getElementById('loading2').style.display = 'none';
                document.getElementById('verifyBtn').style.display = 'block';

                try {
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        showMessage('New OTP sent to ' + email, 'success');
                        startCountdown(300);
                        otpInputs[0].focus();
                    } else {
                        showMessage(response.message, 'error');
                    }
                } catch (e) {
                    showMessage('Failed to resend OTP.', 'error');
                }
            };

            xhr.send('username=' + encodeURIComponent(username) + '&email=' + encodeURIComponent(email));
        });

        // ========================
        // Back Button
        // ========================
        document.getElementById('backBtn').addEventListener('click', function (e) {
            e.preventDefault();
            step2.style.display = 'none';
            step1.style.display = 'block';
            clearInterval(timerInterval);
            messageBox.style.display = 'none';

            // Clear OTP inputs
            otpInputs.forEach(function (input) {
                input.value = '';
            });
        });

        // ========================
        // Show Message
        // ========================
        function showMessage(text, type) {
            messageBox.textContent = (type === 'error' ? '❌ ' : '✅ ') + text;
            messageBox.className = 'message-box ' + type;
            messageBox.style.display = 'block';
        }
    </script>
</body>

</html>