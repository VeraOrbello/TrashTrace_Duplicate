document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            if(!id) return;
            fetch('php/mark_notification_read.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ notification_id: id })
            }).then(r=>r.json()).then(data=>{
                if(data && data.success){
                    const el = this.closest('.notif-item');
                    if(el) el.classList.remove('unread');
                    this.remove();
                } else {
                    alert('Failed to mark read');
                }
            }).catch(()=>alert('Network error'));
        });
    });

    const markAllBtn = document.getElementById('mark-all-read-b');
    const refreshBtn = document.getElementById('refresh-notifs-b');
    if(markAllBtn){
        markAllBtn.addEventListener('click', function(){
            fetch('php/mark_all_notifications_read.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ barangay: window.userBarangay || '' })
            }).then(r=>r.json()).then(data=>{
                if(data && data.success){
                    document.querySelectorAll('.notif-item.unread').forEach(it=> it.classList.remove('unread'));
                }
            }).catch(()=>alert('Network error'));
        });
    }
    if(refreshBtn){
        refreshBtn.addEventListener('click', ()=> location.reload());
    }
});
