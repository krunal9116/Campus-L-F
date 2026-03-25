        var lastMessageId = 0;
        var pollInterval = null;
        var isFirstLoad = true;

        // Tabs
        function switchTab(tab) {
            document.querySelectorAll('.sidebar-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.convo-tab-content').forEach(function (c) { c.classList.remove('active'); });

            if (tab === 'all') {
                document.getElementById('tabAll').classList.add('active');
                document.getElementById('contentAll').classList.add('active');
            } else if (tab === 'admin') {
                document.getElementById('tabAdmin').classList.add('active');
                document.getElementById('contentAdmin').classList.add('active');
            } else {
                document.getElementById('tabUsers').classList.add('active');
                document.getElementById('contentUsers').classList.add('active');
            }
        }

        // Load messages
        function loadMessages() {
            if (convoId == 0) return;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'msg_ajax/get_messages.php?convo_id=' + convoId + '&last_id=' + lastMessageId, true);
            xhr.timeout = 10000;

            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.messages && data.messages.length > 0) {
                                var area = document.getElementById('messagesArea');
                                var shouldScroll = (area.scrollTop + area.clientHeight >= area.scrollHeight - 100);

                                data.messages.forEach(function (msg) {
                                    if (document.getElementById('msg-' + msg.id)) return;

                                    var div = document.createElement('div');

                                    if (msg.is_system == 1) {
                                        div.className = 'message system';
                                        div.id = 'msg-' + msg.id;
                                        div.innerHTML = '<div class="msg-text">' + escapeHtml(msg.message) + '</div>';
                                    } else {
                                        div.className = 'message ' + (msg.sender_id == userId ? 'sent' : 'received');
                                        div.id = 'msg-' + msg.id;

                                        var timeStr = formatTime(msg.created_at);
                                        var readHtml = '';
                                        if (msg.sender_id == userId) {
                                            readHtml = msg.is_read == 1
                                                ? ' <span class="read-status">✓✓</span>'
                                                : ' <span class="unread-status">✓</span>';
                                        }

                                        div.innerHTML = '<div class="msg-text">' + escapeHtml(msg.message) + '</div><div class="time">' + timeStr + readHtml + '</div>';
                                    }

                                    area.appendChild(div);
                                    lastMessageId = msg.id;
                                });

                                if (shouldScroll || isFirstLoad) {
                                    area.scrollTop = area.scrollHeight;
                                }

                                isFirstLoad = false;
                            }

                            // Show "no messages" hint on first load if empty
                            if (isFirstLoad && data.messages && data.messages.length === 0) {
                                var area = document.getElementById('messagesArea');
                                if (area.children.length === 0) {
                                    area.innerHTML = '<div style="text-align:center; color:#999; padding:40px; font-size:14px;"><span style="font-size:50px; display:block; margin-bottom:10px;">💬</span>No messages yet.<br>Send the first message!</div>';
                                }
                                isFirstLoad = false;
                            }

                        } catch (e) {
                            console.error('Messages AJAX parse error. InfinityFree may be blocking the request. Raw response:', xhr.responseText.substring(0, 300));
                        }
                    } else {
                        console.error('Messages AJAX failed with status:', xhr.status);
                    }
                }
            };

            xhr.onerror = function () {
                console.error('Messages AJAX request failed (network error)');
            };

            xhr.send();
        }

        // Send message
        function sendMessage() {
            var input = document.getElementById('messageInput');
            var message = input.value.trim();
            if (message === '' || convoId == 0) return;

            input.value = '';

            // Remove "no messages" hint if present
            var area = document.getElementById('messagesArea');
            var hint = area.querySelector('div[style]');
            if (hint && area.children.length === 1) {
                area.innerHTML = '';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'msg_ajax/send_message.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            loadMessages();
                        } else {
                            console.error('Send failed:', data.message);
                        }
                    } catch (e) {
                        console.error('Send AJAX parse error. InfinityFree may be blocking POST requests. Response:', xhr.responseText.substring(0, 300));
                    }
                }
            };
            xhr.send('convo_id=' + convoId + '&message=' + encodeURIComponent(message));
        }

        // Check read status (less frequent)
        function updateReadStatus() {
            if (convoId == 0) return;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'msg_ajax/check_read.php?convo_id=' + convoId, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.read_ids) {
                            data.read_ids.forEach(function (id) {
                                var msgEl = document.getElementById('msg-' + id);
                                if (msgEl) {
                                    var statusEl = msgEl.querySelector('.unread-status');
                                    if (statusEl) {
                                        statusEl.className = 'read-status';
                                        statusEl.textContent = '✓✓';
                                    }
                                }
                            });
                        }
                    } catch (e) { }
                }
            };
            xhr.send();
        }

        function formatTime(datetime) {
            var date = new Date(datetime);
            var hours = date.getHours();
            var minutes = date.getMinutes();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            return hours + ':' + minutes + ' ' + ampm;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // Convo action menu
        var _menuJustOpened = false;
        function toggleConvoMenu(e, id) {
            e.stopPropagation();
            e.preventDefault();
            document.querySelectorAll('.convo-actions-menu').forEach(function (m) { m.classList.remove('open'); });
            document.querySelectorAll('.convo-actions').forEach(function (a) { a.classList.remove('menu-open'); });
            var menu = document.getElementById('cmenu-' + id);
            menu.classList.add('open');
            menu.closest('.convo-actions').classList.add('menu-open');
            _menuJustOpened = true;
        }
        document.addEventListener('click', function () {
            if (_menuJustOpened) { _menuJustOpened = false; return; }
            document.querySelectorAll('.convo-actions-menu').forEach(function (m) { m.classList.remove('open'); });
            document.querySelectorAll('.convo-actions').forEach(function (a) { a.classList.remove('menu-open'); });
        });
        function chatAction(action, convoId) {
            var msg = action === 'clear' ? 'Clear all messages in this chat?' : 'Delete this entire conversation?';
            if (!confirm(msg)) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'msg_ajax/msg_action.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                var r = JSON.parse(this.responseText);
                if (r.success) {
                    window.location.href = action === 'delete' ? 'messages.php' : window.location.href;
                } else { alert('Failed: ' + (r.msg || '')); }
            };
            xhr.send('action=' + action + '&convo_id=' + convoId);
        }

        // Search
        function filterConversations() {
            var search = document.getElementById('searchChat').value.toLowerCase();
            document.querySelectorAll('.convo-item').forEach(function (item) {
                var name = item.getAttribute('data-name') || '';
                item.style.display = name.includes(search) ? 'flex' : 'none';
            });
        }

        // Modal
        function openNewChatModal() { document.getElementById('newChatModal').style.display = 'flex'; }
        function closeNewChatModal() { document.getElementById('newChatModal').style.display = 'none'; }
        document.getElementById('newChatModal').addEventListener('click', function (e) {
            if (e.target === this) closeNewChatModal();
        });

        // Start polling - 3 seconds for messages, 10 seconds for read status
        if (convoId > 0) {
            loadMessages();
            pollInterval = setInterval(loadMessages, 6000);
            setInterval(updateReadStatus, 15000);
        }

        // Stop polling when tab is hidden (saves resources)
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            } else {
                if (convoId > 0 && !pollInterval) {
                    loadMessages();
                    pollInterval = setInterval(loadMessages, 6000);
                }
            }
        });
