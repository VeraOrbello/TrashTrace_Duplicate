document.addEventListener('DOMContentLoaded', function() {
    const progressFill = document.querySelector('.progress-fill');
    const navLinks = document.querySelectorAll('.nav-link');
    const mainContent = document.querySelector('.dashboard-main');
    
    function animateProgressBar() {
        if (progressFill) {
            let width = 0;
            const targetWidth = parseInt(progressFill.getAttribute('data-width') || progressFill.style.width);
            progressFill.style.width = '0%';
            
            const interval = setInterval(function() {
                if (width >= targetWidth) {
                    clearInterval(interval);
                } else {
                    width++;
                    progressFill.style.width = width + '%';
                }
            }, 20);
        }
    }
    
    function handleNavigation(e) {
        if (e.target.getAttribute('href') && !e.target.getAttribute('href').startsWith('#')) {
            e.preventDefault();
            
            mainContent.style.animation = 'fadeOut 0.3s ease-in forwards';
            
            setTimeout(() => {
                window.location.href = e.target.getAttribute('href');
            }, 300);
        }
    }
    
    navLinks.forEach(link => {
        link.addEventListener('click', handleNavigation);
    });
    
    setTimeout(animateProgressBar, 500);
    
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