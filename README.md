# Campus-L-F
Campus Lost &amp; Found Management System. (CLFMS)



# 🔍 Campus-Find (CLFMS)

**Campus Lost & Found Management System** — A full-stack web application that helps college students and staff report, search, and claim lost & found items on campus.

> ⚠️ **This project will NOT work via GitHub Pages.** It requires a PHP server with MySQL database.
>
> 🌐 **Live Website:** [clfms.infinityfreeapp.com](http://clfms.infinityfreeapp.com)

---

## 📋 Description

Campus-Find is a complete campus-level Lost & Found Management System where users can report lost or found items, search through listings, claim items, and communicate with admins — all through a clean, responsive web interface. The system features role-based access (User, Admin, Boss) with OTP-based email verification for secure registration.

---

## ✨ Features

### 🔐 Authentication & Security
- **OTP-based Registration** — 6-digit OTP sent via email (Brevo SMTP) for verification
- **Role-based Access** — Three roles: User, Admin, and Boss (super admin)
- **Admin Approval System** — Admin registrations require Boss-level approval via email notification
- **Forgot Password** — OTP-based password reset flow
- **CSRF Protection** — Token-based protection on sensitive forms
- **Secure Credentials** — Environment variables via `.env` file (not exposed in code)

### 📦 Item Management
- **Report Lost Items** — Users can report items they've lost with details (name, category, location, description, image)
- **Report Found Items** — Users can report items they've found
- **Search & Filter** — Search items by name, category, location, or status
- **Claim Items** — Users can claim found items; admin verifies the rightful owner
- **Item Status Tracking** — Lost → Found → Claimed → Received

### 👤 User Features
- **User Dashboard** — Overview of recent items with quick action cards
- **My Reports** — View and manage your own reported items
- **Edit Reports** — Modify item details after reporting
- **Profile Page** — View profile with profile photo upload
- **Settings** — Change password, update email (with OTP verification)
- **Notifications** — Real-time bell notifications for claim status updates (approved/rejected)
- **Dark/Light Mode** — Toggle dark/light theme across all pages

### 💬 Messaging System
- **Real-time Chat** — In-app messaging between users and admins
- **Unread Badges** — Chat badge with unread message count on navbar
- **Message Actions** — Read receipts and message management

### 🛡️ Admin Panel
- **Admin Dashboard** — Overview with statistics and recent activity
- **Manage Users** — View and manage all registered users
- **Manage Items** — Oversee all reported lost & found items
- **Claims Management** — Approve or reject item claims
- **Activity Log** — Track admin actions and system events
- **Reports** — Generate and view system reports
- **Admin Messaging** — Communicate with users regarding their items

### 👑 Boss Panel
- **Super Admin Dashboard** — Sleek dark-themed panel with glassmorphism UI
- **Admin Approval** — Approve or reject admin registration requests
- **Email Notifications** — Automatic emails sent on approval/rejection
- **System Statistics** — View total users, admins, and pending requests

---

## 🛠️ Tech Stack

| Technology | Purpose |
|---|---|
| **PHP** | Backend logic & server-side processing |
| **MySQL** | Database (hosted on InfinityFree) |
| **HTML/CSS** | Frontend structure & styling |
| **JavaScript** | Client-side interactivity & AJAX requests |
| **PHPMailer** | Email sending (OTP, notifications) |
| **Brevo SMTP** | Email delivery service |
| **Google Fonts** | Typography (Poppins, Righteous) |

---

## 📁 Project Structure

```
CLFMS/
├── index.php                  # Login page
├── register.php               # Registration with OTP verification
├── forgot_password.php        # Forgot password (send OTP)
├── reset_password.php         # Reset password (verify OTP)
├── config.php                 # Configuration (reads from .env)
├── .htaccess                  # Security rules
│
├── user_dashboard.php         # User home page
├── report_lost.php            # Report a lost item
├── report_found.php           # Report a found item
├── search_items.php           # Search lost & found items
├── claim_item.php             # Claim an item
├── my_reports.php             # User's own reports
├── edit_report.php            # Edit a reported item
├── profile.php                # User profile page
├── settings.php               # User settings (password, email change)
├── messages.php               # Messaging system
│
├── admin_dashboard.php        # Admin home page
├── admin_manage_users.php     # Manage users
├── admin_manage_items.php     # Manage items
├── admin_claims.php           # Manage claims
├── admin_reports.php          # View reports
├── admin_activity_log.php     # Activity log
├── admin_messages.php         # Admin messaging
├── admin_settings.php         # Admin settings
├── admin_profile.php          # Admin profile
│
├── boss.php                   # Boss panel (super admin)
│
├── dark-mode.css / .js        # Dark mode theme
├── responsive.css             # Responsive design
├── images/                    # UI images & backgrounds
├── animations/                # Loading animations
├── uploads/                   # User uploaded files
├── email_change/              # Email change OTP flow
├── msg_ajax/                  # AJAX endpoints for messaging
└── PHPMailer-7.0.2/           # PHPMailer library
```

---

## 🚀 How It Works

1. **Register** → User creates an account with OTP email verification
2. **Login** → Users go to User Dashboard; Admins go to Admin Dashboard
3. **Report** → Users report lost or found items with details and images
4. **Search** → Anyone can search and browse reported items
5. **Claim** → Users claim items they believe are theirs
6. **Admin Verify** → Admin reviews claims and approves/rejects
7. **Receive** → Once verified, item status changes to "Received"
8. **Chat** → Users and admins can communicate via in-app messaging

### Admin Registration Flow
1. User selects "Admin" role during registration
2. OTP is verified via email
3. Request is sent to **Boss** for approval
4. Boss approves/rejects from the Boss Panel
5. User receives email notification with the decision

---


## 📄 License

This project is developed for educational purposes as part of a campus management initiative.
