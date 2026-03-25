        var lastMessageId = 0;
        var pollInterval = null;
        var isFirstLoad = true;

        function loadMessages() {
            if (convoId == 0) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'msg_ajax/get_messages.php?convo_id=' + convoId + '&last_id=' + lastMessageId, true);
            xhr.timeout = 10000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
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
                                    var readHtml = '';
                                    if (msg.sender_id == userId) {
                                        readHtml = msg.is_read == 1
                                            ? ' <span class="read-status">✓✓</span>'
                                            : ' <span class="unread-status">✓</span>';
                                    }
                                    div.innerHTML = '<div class="msg-text">' + escapeHtml(msg.message) + '</div>'
                                        + '<div class="time">' + formatTime(msg.created_at) + readHtml + '</div>';
                                }
                                area.appendChild(div);
                                lastMessageId = msg.id;
                            });
                            if (shouldScroll || isFirstLoad) area.scrollTop = area.scrollHeight;
                            isFirstLoad = false;
                        }
                        if (isFirstLoad && data.messages && data.messages.length === 0) {
                            var area = document.getElementById('messagesArea');
                            if (area.children.length === 0) {
                                area.innerHTML = '<div style="text-align:center;color:#999;padding:40px;font-size:14px;"><span style="font-size:50px;display:block;margin-bottom:10px;">💬</span>No messages yet.<br>Send the first message!</div>';
                            }
                            isFirstLoad = false;
                        }
                    } catch (e) { console.error('Admin messages AJAX parse error. Raw response:', xhr.responseText.substring(0, 300)); }
                }
            };
            xhr.send();
        }

        function sendMessage() {
            var input = document.getElementById('messageInput');
            var message = input.value.trim();
            if (message === '' || convoId == 0) return;
            input.value = '';
            var area = document.getElementById('messagesArea');
            var hint = area.querySelector('div[style]');
            if (hint && area.children.length === 1) area.innerHTML = '';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'msg_ajax/send_message.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) loadMessages();
                        else console.error('Send failed:', data.message);
                    } catch (e) { console.error('Send AJAX parse error. InfinityFree may be blocking POST. Response:', xhr.responseText.substring(0, 300)); }
                }
            };
            xhr.send('convo_id=' + convoId + '&message=' + encodeURIComponent(message));
        }

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
                                var el = document.getElementById('msg-' + id);
                                if (el) {
                                    var s = el.querySelector('.unread-status');
                                    if (s) { s.className = 'read-status'; s.textContent = '✓✓'; }
                                }
                            });
                        }
                    } catch (e) { }
                }
            };
            xhr.send();
        }

        function formatTime(datetime) {
            var d = new Date(datetime);
            var h = d.getHours(), m = d.getMinutes();
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

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
                    if (action === 'delete') {
                        window.location.href = 'admin_messages.php';
                    } else {
                        window.location.reload();
                    }
                } else { alert('Failed: ' + (r.msg || '')); }
            };
            xhr.send('action=' + action + '&convo_id=' + convoId);
        }

        function handleSearch(val) {
            var search = val.toLowerCase().trim();
            var userResults = document.getElementById('userSearchResults');
            var convoItems = document.querySelectorAll('.convo-item');

            if (search === '') {
                // Show all conversations, hide user search
                userResults.style.display = 'none';
                convoItems.forEach(function (item) { item.style.display = 'flex'; });
                return;
            }

            // Filter existing conversations
            convoItems.forEach(function (item) {
                var name = item.getAttribute('data-name') || '';
                item.style.display = name.includes(search) ? 'flex' : 'none';
            });

            // Show user search results
            userResults.style.display = 'block';
            document.querySelectorAll('.user-search-item').forEach(function (item) {
                var uname = item.getAttribute('data-username') || '';
                item.style.display = uname.includes(search) ? 'flex' : 'none';
            });
        }

        if (convoId > 0) {
            loadMessages();
            pollInterval = setInterval(loadMessages, 6000);
            setInterval(updateReadStatus, 15000);
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            } else {
                if (convoId > 0 && !pollInterval) {
                    loadMessages();
                    pollInterval = setInterval(loadMessages, 6000);
                }
            }
        });
