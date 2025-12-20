document.addEventListener('DOMContentLoaded', function() {
    const notificationsList = document.getElementById('notifications-list');
    const markAllReadBtn = document.getElementById('mark-all-read');
    const refreshBtn = document.getElementById('refresh-notifications');
    const loadMoreBtn = document.getElementById('load-more');
    const navLinks = document.querySelectorAll('.nav-link');
    const mainContent = document.querySelector('.notifications-main');
    
    let currentPage = 1;
    let isLoading = false;
    
    function handleNavigation(e) {
        if (e.target.getAttribute('href') && !e.target.getAttribute('href').startsWith('#')) {
            e.preventDefault();
            
            mainContent.style.animation = 'fadeOut 0.3s ease-in forwards';
            
            setTimeout(() => {
                window.location.href = e.target.getAttribute('href');
            }, 300);
        }
    }
    
    function markAsRead(notificationId) {
        fetch('php/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('unread');
                    const unreadDot = notificationItem.querySelector('.unread-dot');
                    if (unreadDot) {
                        unreadDot.remove();
                    }
                    updateUnreadCount();
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    function markAllAsRead() {
        fetch('php/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const unreadDot = item.querySelector('.unread-dot');
                    if (unreadDot) {
                        unreadDot.remove();
                    }
                });
                updateUnreadCount();
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    function updateUnreadCount() {
        const unreadCount = document.querySelectorAll('.notification-item.unread').length;
        document.querySelectorAll('.stat-number').forEach(stat => {
            if (stat.textContent === document.querySelector('.stat-card:nth-child(2) .stat-number').textContent) {
                stat.textContent = unreadCount;
            }
            if (stat.textContent === document.querySelector('.stat-card:nth-child(3) .stat-number').textContent) {
                stat.textContent = document.querySelectorAll('.notification-item').length - unreadCount;
            }
        });
    }
    
    function loadMoreNotifications() {
        if (isLoading) return;
        
        isLoading = true;
        currentPage++;
        
        fetch(`php/get_notifications.php?page=${currentPage}&user_id=${userId}&barangay=${userBarangay}`)
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notification => {
                        const notificationItem = createNotificationElement(notification);
                        notificationsList.appendChild(notificationItem);
                    });
                    
                    if (data.notifications.length < 10) {
                        loadMoreBtn.style.display = 'none';
                    }
                } else {
                    loadMoreBtn.style.display = 'none';
                }
                isLoading = false;
            })
            .catch(error => {
                console.error('Error loading more notifications:', error);
                isLoading = false;
            });
    }
    
    function createNotificationElement(notification) {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
        item.dataset.id = notification.id;
        
        const icon = getNotificationIcon(notification.type);
        
        item.innerHTML = `
            <div class="notification-icon">${icon}</div>
            <div class="notification-content">
                <h4>${escapeHtml(notification.title)}</h4>
                <p>${escapeHtml(notification.message)}</p>
                <span class="notification-time">${formatDate(notification.created_at)}</span>
            </div>
            <div class="notification-actions">
                ${!notification.is_read ? '<span class="unread-dot"></span>' : ''}
                <button class="mark-read-btn" title="Mark as read">✓</button>
            </div>
        `;
        
        const markReadBtn = item.querySelector('.mark-read-btn');
        markReadBtn.addEventListener('click', function() {
            markAsRead(notification.id);
        });
        
        return item;
    }
    
    function getNotificationIcon(type) {
        const icons = {
            'pickup_scheduled': '📅',
            'pickup_completed': '✅',
            'pickup_delayed': '⚠️',
            'pickup_cancelled': '❌',
            'emergency': '🚨',
            'default': '📢'
        };
        return icons[type] || icons.default;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }
    
    function checkForNewNotifications() {
        fetch(`php/check_new_notifications.php?user_id=${userId}&barangay=${userBarangay}`)
            .then(response => response.json())
            .then(data => {
                if (data.new_notifications && data.new_notifications.length > 0) {
                    data.new_notifications.forEach(notification => {
                        const notificationItem = createNotificationElement(notification);
                        notificationItem.classList.add('new-notification');
                        notificationsList.insertBefore(notificationItem, notificationsList.firstChild);
                    });
                    updateUnreadCount();
                }
            })
            .catch(error => {
                console.error('Error checking for new notifications:', error);
            });
    }
    
    markAllReadBtn.addEventListener('click', markAllAsRead);
    
    refreshBtn.addEventListener('click', function() {
        location.reload();
    });
    
    loadMoreBtn.addEventListener('click', loadMoreNotifications);
    
    document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const notificationItem = this.closest('.notification-item');
            const notificationId = notificationItem.dataset.id;
            markAsRead(notificationId);
        });
    });
    
    navLinks.forEach(link => {
        link.addEventListener('click', handleNavigation);
    });
    
    setInterval(checkForNewNotifications, 30000);
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
    `;
    document.head.appendChild(style);
});