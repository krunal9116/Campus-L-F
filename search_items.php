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

// Handle claim
if (isset($_POST['claim_item'])) {
    $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
    $claim_date = date('Y-m-d H:i:s');
    $countdown_end = date('Y-m-d H:i:s', strtotime('+2 days'));

    $check_claim = "SELECT * FROM claims WHERE item_id = '$item_id' AND user_id = '$user_id'";
    $check_result = mysqli_query($conn, $check_claim);

    if (mysqli_num_rows($check_result) == 0) {
        // Check item is "found" type AND not your own item
        $item_check = "SELECT report_type, user_id FROM items WHERE id = '$item_id'";
        $item_result = mysqli_query($conn, $item_check);
        $item_data = mysqli_fetch_assoc($item_result);

        if (strtolower($item_data['report_type']) == 'found' && $item_data['user_id'] != $user_id) {
            $insert_claim = "INSERT INTO claims (item_id, user_id, claimed_by, claim_date, countdown_end, status) 
                             VALUES ('$item_id', '$user_id', '$username', '$claim_date', '$countdown_end', 'pending')";
            mysqli_query($conn, $insert_claim);
        }
    }
}

// Get all items
$query = "SELECT * FROM items ORDER BY date_reported DESC";
$result = mysqli_query($conn, $query);

$items = array();
while ($row = mysqli_fetch_assoc($result)) {
    $claim_check = "SELECT * FROM claims WHERE item_id = '" . $row['id'] . "' AND user_id = '$user_id'";
    $claim_result = mysqli_query($conn, $claim_check);

    if (mysqli_num_rows($claim_result) > 0) {
        $claim_data = mysqli_fetch_assoc($claim_result);
        $row['user_claimed'] = true;
        $row['countdown_end'] = $claim_data['countdown_end'];
        $row['claim_status'] = $claim_data['status'];
    } else {
        $row['user_claimed'] = false;
        $row['countdown_end'] = null;
        $row['claim_status'] = null;
    }

    $total_claims = "SELECT COUNT(*) as count FROM claims WHERE item_id = '" . $row['id'] . "'";
    $total_result = mysqli_query($conn, $total_claims);
    $total_data = mysqli_fetch_assoc($total_result);
    $row['total_claims'] = $total_data['count'];

    // Check if item belongs to this user
    $row['is_own_item'] = ($row['user_id'] == $user_id);

    $items[] = $row;
}
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
    <title>Search Items - Campus Find</title>
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

        .search-section {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #000;
        }

        .search-section h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            border-bottom: 3px solid #159f35;
            padding-bottom: 10px;
            display: inline-block;
        }

        .search-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
        }

        .search-input:focus {
            outline: none;
            border-color: #159f35;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table th,
        .results-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .results-table tr:hover {
            background-color: #f5f5f5;
        }

        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            min-width: 110px;
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

        .no-results {
            text-align: center;
            color: gray;
            padding: 40px;
            font-size: 16px;
        }

        .result-count {
            color: #666;
            font-size: 14px;
            margin-top: 15px;
        }

        .start-search {
            text-align: center;
            color: #999;
            padding: 50px;
            font-size: 16px;
        }

        .start-search span {
            font-size: 50px;
            display: block;
            margin-bottom: 15px;
        }

        .claim-btn {
            padding: 8px 15px;
            background-color: #159f35;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .claim-btn:hover {
            background-color: #035815;
        }

        .chat-btn {
            padding: 6px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }

        .chat-btn:hover {
            background-color: #0056b3;
        }

        .own-item-tag {
            font-size: 11px;
            color: #6f42c1;
            font-weight: 600;
            background-color: #f0e6ff;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 5px;
        }

        .claimed-badge {
            color: #27ae60;
            font-size: 13px;
            font-weight: 600;
        }

        .countdown {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        .countdown-time {
            color: #333;
            font-weight: 600;
        }

        .countdown-awaiting {
            color: #159f35;
            font-weight: 600;
            font-size: 12px;
            margin-top: 5px;
        }

        .claim-count {
            font-size: 11px;
            color: #856404;
            margin-top: 5px;
            background-color: #fff3cd;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .no-claim-text {
            color: #999;
            font-size: 12px;
        }

        .action-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

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
            margin-bottom: 15px;
            color: #333;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-buttons button {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .confirm-btn {
            background-color: #159f35;
            color: white;
        }

        .confirm-btn:hover {
            background-color: #035815;
        }

        .cancel-btn {
            background-color: #e74c3c;
            color: white;
        }

        .cancel-btn:hover {
            background-color: #c0392b;
        }

        .success-modal .modal-content {
            border: 2px solid #159f35;
        }

        .success-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <h1>Campus-Find</h1>
        <a href="user_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="container">
        <div class="search-section">
            <h2>Search Items</h2>

            <input type="text" id="searchInput" class="search-input"
                placeholder="🔍 Type to search by item name, location, category...">

            <!-- Filter Row -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                <select id="filterType"
                    style="padding:9px 14px; border:2px solid #ddd; border-radius:10px; font-size:13px; font-family:'Poppins',sans-serif; outline:none; cursor:pointer;">
                    <option value="">📦 All Types</option>
                    <option value="lost">🔴 Lost</option>
                    <option value="found">🔵 Found</option>
                </select>
                <select id="filterCategory"
                    style="padding:9px 14px; border:2px solid #ddd; border-radius:10px; font-size:13px; font-family:'Poppins',sans-serif; outline:none; cursor:pointer;">
                    <option value="">📁 All Categories</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Documents">Documents</option>
                    <option value="Clothing">Clothing</option>
                    <option value="Books">Books</option>
                    <option value="ID Card">ID Card</option>
                    <option value="Others">Others</option>
                </select>
                <select id="filterDate" style="display:none;"></select>
                <button onclick="resetFilters()"
                    style="padding:9px 18px; background:white; border:2px solid #ddd; border-radius:10px; font-size:13px; font-family:'Poppins',sans-serif; cursor:pointer; color:#555;">✕
                    Reset</button>
            </div>

            <div class="start-search" id="startSearch">
                <span>🔍</span>
                <p>Start typing to search items</p>
            </div>

            <p class="result-count" id="resultCount" style="display: none;"></p>

            <table class="results-table" id="resultsTable" style="display: none;">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>

            <div class="no-results" id="noResults" style="display: none;">
                <p>Opps!!! No items found.</p>
                <p>Try searching with different keywords.</p>
            </div>
            <div id="paginationDiv" style="display:none;text-align:center;margin:16px 0;"></div>
        </div>
    </div>

    <!-- Claim Confirmation Modal -->
    <div class="modal" id="claimModal">
        <div class="modal-content">
            <h3>🖐️ Claim This Item?</h3>
            <p id="claimItemName"></p>
            <p style="font-size: 12px; color: #999;">
                A 2-day countdown will start. Other users can also claim during this period.
                After countdown, admin will verify and contact both parties.
            </p>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button class="confirm-btn" onclick="confirmClaim()">Yes, Claim It</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal success-modal" id="successModal">
        <div class="modal-content">
            <div class="success-icon">✅</div>
            <h3>Item Claimed Successfully!</h3>
            <p>A 2-day countdown has started. Admin will contact you after verification.</p>
            <div class="modal-buttons">
                <button class="confirm-btn" onclick="closeSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <form id="claimForm" method="POST" style="display: none;">
        <input type="hidden" name="item_id" id="claimItemId">
        <input type="hidden" name="claim_item" value="1">
    </form>

    <script>
        var items = <?php echo json_encode($items); ?>;
        var currentUserId = <?php echo $user_id; ?>;

        var searchInput = document.getElementById('searchInput');
        var tableBody = document.getElementById('tableBody');
        var noResults = document.getElementById('noResults');
        var resultsTable = document.getElementById('resultsTable');
        var resultCount = document.getElementById('resultCount');
        var startSearch = document.getElementById('startSearch');
        var filterType = document.getElementById('filterType');
        var filterCategory = document.getElementById('filterCategory');
        var filterDate = document.getElementById('filterDate');
        var selectedItemId = null;
        var ITEMS_PER_PAGE = 10;
        var currentPage = 1;
        var currentFilteredItems = [];

        function applyFilters() {
            var searchTerm = searchInput.value.trim().toLowerCase();
            var type = filterType.value.toLowerCase();
            var category = filterCategory.value.toLowerCase();
            var dateRange = filterDate.value;

            var hasFilter = searchTerm !== '' || type !== '' || category !== '' || dateRange !== '';

            if (!hasFilter) {
                startSearch.style.display = 'block';
                resultsTable.style.display = 'none';
                noResults.style.display = 'none';
                resultCount.style.display = 'none';
                return;
            }

            startSearch.style.display = 'none';

            var now = new Date();
            var filteredItems = items.filter(function (item) {
                // Text search
                if (searchTerm !== '' && !(
                    item.item_name.toLowerCase().includes(searchTerm) ||
                    item.location.toLowerCase().includes(searchTerm) ||
                    item.category.toLowerCase().includes(searchTerm) ||
                    (item.description && item.description.toLowerCase().includes(searchTerm))
                )) return false;

                // Type filter
                if (type !== '' && item.report_type.toLowerCase() !== type) return false;

                // Category filter
                if (category !== '' && item.category.toLowerCase() !== category) return false;

                // Date filter
                if (dateRange !== '') {
                    var itemDate = new Date(item.date_reported);
                    if (dateRange === 'today') {
                        if (itemDate.toDateString() !== now.toDateString()) return false;
                    } else if (dateRange === 'week') {
                        var weekAgo = new Date(now); weekAgo.setDate(now.getDate() - 7);
                        if (itemDate < weekAgo) return false;
                    } else if (dateRange === 'month') {
                        var monthAgo = new Date(now); monthAgo.setMonth(now.getMonth() - 1);
                        if (itemDate < monthAgo) return false;
                    }
                }
                return true;
            });

            currentPage = 1;
            currentFilteredItems = filteredItems;
            displayItems(filteredItems);
        }

        function resetFilters() {
            searchInput.value = '';
            filterType.value = '';
            filterCategory.value = '';
            filterDate.value = '';
            startSearch.style.display = 'block';
            resultsTable.style.display = 'none';
            noResults.style.display = 'none';
            resultCount.style.display = 'none';
        }

        searchInput.addEventListener('input', applyFilters);
        filterType.addEventListener('change', applyFilters);
        filterCategory.addEventListener('change', applyFilters);
        filterDate.addEventListener('change', applyFilters);

        function displayItems(itemsToShow) {
            tableBody.innerHTML = '';
            var paginationDiv = document.getElementById('paginationDiv');

            if (itemsToShow.length > 0) {
                var totalPages = Math.ceil(itemsToShow.length / ITEMS_PER_PAGE);
                if (currentPage > totalPages) currentPage = totalPages;
                var start = (currentPage - 1) * ITEMS_PER_PAGE;
                var pageItems = itemsToShow.slice(start, start + ITEMS_PER_PAGE);

                resultsTable.style.display = 'table';
                noResults.style.display = 'none';
                resultCount.style.display = 'block';
                resultCount.textContent = 'Found ' + itemsToShow.length + ' item(s)' + (totalPages > 1 ? ' — Page ' + currentPage + ' of ' + totalPages : '');

                pageItems.forEach(function (item) {
                    var row = document.createElement('tr');
                    var reportType = item.report_type.toLowerCase();

                    var date = new Date(item.date_reported);
                    var formattedDate = date.toLocaleDateString('en-GB', {
                        day: '2-digit', month: 'short', year: 'numeric'
                    });

                    // STATUS
                    var statusHTML = '';
                    if (reportType === 'received') {
                        statusHTML = '<span class="status received">🟢 Received</span>';
                    } else if (item.user_claimed) {
                        statusHTML = '<span class="status claimed">🟡 Claimed</span>';
                    } else if (reportType === 'lost') {
                        statusHTML = '<span class="status lost">🔴 Lost</span>';
                    } else if (reportType === 'found') {
                        statusHTML = '<span class="status found">🔵 Found</span>';
                    }

                    if (item.total_claims > 0) {
                        statusHTML += ' <span class="claim-count">👥 ' + item.total_claims + '</span>';
                    }

                    // ACTION COLUMN
                    var actionHTML = '<div class="action-group">';

                    if (reportType === 'received') {
                        // Returned - no action
                        actionHTML += '<span style="color: #155724; font-size: 12px;">🟢 Returned</span>';

                    } else if (item.is_own_item) {
                        // Your own item - no claim, no chat
                        actionHTML += '<span class="own-item-tag">📌 Your Item</span>';

                    } else if (reportType === 'found') {
                        // Found items - claim only, no chat
                        if (item.user_claimed) {
                            var countdownEnd = new Date(item.countdown_end);
                            var now = new Date();
                            var diff = countdownEnd - now;

                            if (diff > 0) {
                                var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                                var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

                                actionHTML += '<span class="claimed-badge">✓ You Claimed</span>' +
                                    '<div class="countdown" data-end="' + item.countdown_end + '">' +
                                    '⏱️ <span class="countdown-time">' + days + 'd ' + hours + 'h ' + minutes + 'm</span> left</div>';
                            } else {
                                actionHTML += '<span class="claimed-badge">✓ You Claimed</span>' +
                                    '<div class="countdown-awaiting">⏱️ Awaiting Admin</div>';
                            }
                        } else {
                            actionHTML += '<button class="claim-btn" onclick="openClaimModal(' + item.id + ', \'' + item.item_name.replace(/'/g, "\\'") + '\')">✋ Claim</button>';
                        }

                    } else if (reportType === 'lost') {
                        // Lost items - chat only, no claim
                        actionHTML += '<a href="messages.php?item_chat=' + item.id + '" class="chat-btn">💬 Chat with Reporter</a>';

                    } else {
                        actionHTML += '<span class="no-claim-text">—</span>';
                    }

                    actionHTML += '</div>';

                    row.innerHTML =
                        '<td><div style="display:flex;align-items:center;gap:8px;">' +
                        (item.image ? '<img src="uploads/' + item.image + '" onclick="zoomImage(\'uploads/' + item.image + '\')" style="width:45px;height:45px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd;" onerror="this.style.display=\'none\'">' : '<span style="font-size:22px;">📦</span>') +
                        '<span>' + item.item_name + '</span></div></td>' +
                        '<td>' + item.category + '</td>' +
                        '<td>' + item.location + '</td>' +
                        '<td>' + (item.description || '-') + '</td>' +
                        '<td>' + formattedDate + '</td>' +
                        '<td>' + statusHTML + '</td>' +
                        '<td>' + actionHTML + '</td>';

                    tableBody.appendChild(row);
                });

                startCountdowns();

                // Pagination controls
                if (totalPages > 1) {
                    paginationDiv.style.display = 'block';
                    var html = '';
                    html += '<button onclick="goPage(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + ' style="margin:0 4px;padding:6px 14px;border-radius:6px;border:1px solid #ccc;cursor:pointer;background:' + (currentPage === 1 ? '#f5f5f5' : 'white') + ';">‹ Prev</button>';
                    for (var p = 1; p <= totalPages; p++) {
                        html += '<button onclick="goPage(' + p + ')" style="margin:0 3px;padding:6px 12px;border-radius:6px;border:1px solid ' + (p === currentPage ? '#159f35' : '#ccc') + ';background:' + (p === currentPage ? '#159f35' : 'white') + ';color:' + (p === currentPage ? 'white' : '#333') + ';font-weight:' + (p === currentPage ? '600' : '400') + ';cursor:pointer;">' + p + '</button>';
                    }
                    html += '<button onclick="goPage(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + ' style="margin:0 4px;padding:6px 14px;border-radius:6px;border:1px solid #ccc;cursor:pointer;background:' + (currentPage === totalPages ? '#f5f5f5' : 'white') + ';">Next ›</button>';
                    paginationDiv.innerHTML = html;
                } else {
                    paginationDiv.style.display = 'none';
                }
            } else {
                resultsTable.style.display = 'none';
                noResults.style.display = 'block';
                resultCount.style.display = 'none';
                paginationDiv.style.display = 'none';
            }
        }

        function goPage(page) {
            var totalPages = Math.ceil(currentFilteredItems.length / ITEMS_PER_PAGE);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            displayItems(currentFilteredItems);
            document.getElementById('resultsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function openClaimModal(itemId, itemName) {
            selectedItemId = itemId;
            document.getElementById('claimItemName').textContent = 'Item: "' + itemName + '"';
            document.getElementById('claimModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('claimModal').style.display = 'none';
            selectedItemId = null;
        }

        function confirmClaim() {
            document.getElementById('claimItemId').value = selectedItemId;
            document.getElementById('claimForm').submit();
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        function startCountdowns() {
            setInterval(function () {
                var countdowns = document.querySelectorAll('.countdown[data-end]');
                countdowns.forEach(function (el) {
                    var end = new Date(el.getAttribute('data-end'));
                    var now = new Date();
                    var diff = end - now;

                    if (diff > 0) {
                        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        el.innerHTML = '⏱️ <span class="countdown-time">' + days + 'd ' + hours + 'h ' + minutes + 'm</span> left';
                    } else {
                        el.className = 'countdown-awaiting';
                        el.innerHTML = '⏱️ Awaiting Admin';
                        el.removeAttribute('data-end');
                    }
                });
            }, 60000);
        }

        <?php if (isset($_POST['claim_item'])) { ?>
            document.getElementById('successModal').style.display = 'flex';
        <?php } ?>

        // Image zoom
        function zoomImage(src) {
            document.getElementById('zoomOverlay').style.display = 'flex';
            document.getElementById('zoomImg').src = src;
        }
        document.getElementById('zoomOverlay').addEventListener('click', function (e) {
            if (e.target !== document.getElementById('zoomImg')) this.style.display = 'none';
        });
    </script>

    <!-- Image Zoom Overlay -->
    <div id="zoomOverlay"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;justify-content:center;align-items:center;">
        <button onclick="document.getElementById('zoomOverlay').style.display='none'"
            style="position:absolute;top:18px;right:24px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;width:44px;height:44px;border-radius:50%;cursor:pointer;line-height:1;">✕</button>
        <img id="zoomImg" src=""
            style="max-width:90%;max-height:90%;border-radius:10px;box-shadow:0 0 40px rgba(0,0,0,0.5);cursor:default;"
            onclick="event.stopPropagation()">
    </div>

    <?php mysqli_close($conn); ?>
</body>

</html>