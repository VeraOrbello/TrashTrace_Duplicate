document.addEventListener('DOMContentLoaded', function(){
    const list = document.getElementById('reports-list');

    let reportsCache = [];

    function renderReports(items){
        if(!items || items.length === 0){
            list.innerHTML = '<div class="empty-state"><p>No reports yet.</p></div>';
            return;
        }
        list.innerHTML = '';
        items.forEach(r => {
            const el = document.createElement('div');
            el.className = 'report-item';
            const name = r.user_name ? r.user_name : 'Anonymous';
            const initials = name.split(' ').map(s=>s[0]).slice(0,2).join('').toUpperCase();
            el.innerHTML = `
                <div class="report-row">
                    <div class="report-avatar">${escapeHtml(initials)}</div>
                    <div class="report-content">
                        <div class="meta">${escapeHtml(name)} • ${r.created_at}</div>
                        <div class="title">${escapeHtml(r.category || r.type || 'Report')}</div>
                        <div class="desc">${escapeHtml(r.description || '')}</div>
                        <div class="report-badges">
                            <span class="badge">Address: ${escapeHtml(r.address || 'N/A')}</span>
                            ${r.location ? '<span class="badge">Location: ' + escapeHtml(r.location) + '</span>' : ''}
                            <span class="badge">Barangay: ${escapeHtml(r.user_barangay || 'N/A')}</span>
                            <span class="badge">City: ${escapeHtml(r.user_city || 'N/A')}</span>
                        </div>
                        <div class="report-actions">
                            <button class="btn btn-outline open-report">Open</button>
                        </div>
                        <div class="report-panel" style="display:none;">
                            <div style="margin-top:8px;">
                                <label>Message to user</label>
                                <textarea class="reply-message" rows="3"></textarea>
                            </div>
                            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                                <button class="btn btn-primary send-reply">Send Reply</button>
                                <button class="btn btn-outline mark-resolved">Mark Resolved</button>
                                <span class="reply-feedback" style="margin-left:8px;color:green;display:none;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.appendChild(el);
            const panel = el.querySelector('.report-panel');
            
            const openBtn = el.querySelector('.open-report');
            openBtn.addEventListener('click', function(){
                const p = el.querySelector('.report-panel');
                p.style.display = p.style.display === 'none' ? 'block' : 'none';
            });
            
            const sendBtn = el.querySelector('.send-reply');
            sendBtn.addEventListener('click', function(){
                const msg = el.querySelector('.reply-message').value.trim();
                const cat = r.category || r.type || '';
                if(!msg){
                    const fb = el.querySelector('.reply-feedback'); fb.style.display='inline'; fb.style.color='red'; fb.textContent='Enter a message';
                    setTimeout(()=>{ fb.style.display='none'; },2000);
                    return;
                }
                sendReply(r.id, msg, cat, false, el);
            });
            const markBtn = el.querySelector('.mark-resolved');
            markBtn.addEventListener('click', function(){
                const cat = r.category || r.type || '';
                sendReply(r.id, 'Marked resolved by barangay worker.', cat, true, el);
            });
        });
    }

    function escapeHtml(text){
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function fetchReports(){
        fetch('php/get_feedback.php')
            .then(r=>r.json())
            .then(data=>{
                if(data.success){
                    reportsCache = data.feedbacks || [];
                    applyFilters();
                } else {
                    list.innerHTML = '<div class="empty-state"><p>Failed to load reports.</p></div>';
                }
            }).catch(()=>{
                list.innerHTML = '<div class="empty-state"><p>Network error.</p></div>';
            });
    }

    function applyFilters(){
        const q = (document.getElementById('report-search')?.value || '').trim().toLowerCase();
        const cat = (document.getElementById('report-filter-category')?.value || '').trim();
        let results = reportsCache.slice();
        if(cat) results = results.filter(r => (r.category || r.type || '').toLowerCase() === cat.toLowerCase());
        if(q){
            results = results.filter(r => {
                const hay = ((r.user_name||'') + ' ' + (r.description||'') + ' ' + (r.address||'') + ' ' + (r.location||'')).toLowerCase();
                return hay.indexOf(q) !== -1;
            });
        }
        renderReports(results);
    }

    function sendReply(feedbackId, message, category, markResolved, containerEl){
        fetch('php/send_feedback_reply.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ feedback_id: feedbackId, message: message, category: category, mark_resolved: markResolved })
        }).then(r=>r.json()).then(data=>{
            const fb = containerEl.querySelector('.reply-feedback');
            if(data && data.success){
                fb.style.display='inline'; fb.style.color='green'; fb.textContent='Reply sent';
                setTimeout(()=>{ fb.style.display='none'; },1500);
                const p = containerEl.querySelector('.report-panel'); if(p) p.style.display='none';
                
                
                setTimeout(fetchReports, 800);
            } else {
                fb.style.display='inline'; fb.style.color='red'; fb.textContent=(data.error||'Failed');
            }
        }).catch(()=>{
            const fb = containerEl.querySelector('.reply-feedback'); fb.style.display='inline'; fb.style.color='red'; fb.textContent='Network error';
        });
    }

    
    const searchInput = document.getElementById('report-search');
    const filterSelect = document.getElementById('report-filter-category');
    const refreshBtn = document.getElementById('refresh-reports');
    if(searchInput) searchInput.addEventListener('input', applyFilters);
    if(filterSelect) filterSelect.addEventListener('change', applyFilters);
    if(refreshBtn) refreshBtn.addEventListener('click', fetchReports);

    fetchReports();
    setInterval(fetchReports, 30000);
});
