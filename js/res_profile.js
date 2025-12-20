document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const mainContent = document.querySelector('.profile-main');
    const forms = document.querySelectorAll('form');
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    
    const barangaysByCity = {
        "CEBU CITY": [
            "Adlaon", "Agsungot", "Apas", "Babag", "Bacayan", "Banilad", "Binaliw", "Bonbon", "Budlaan", "Buhisan",
            "Bulacao", "Buot", "Busay", "Cambinocot", "Capitol Site", "Carreta", "Cogon Ramos", "Cogon Pardo", 
            "Duljo Fatima", "Ermita", "Guadalupe", "Guba", "Hipodromo", "Kalubihan", "Kalunasan", "Kamagayan",
            "Kamputhaw", "Kasambagan", "Kinasang-an", "Labangon", "Lahug", "Lorega San Miguel", "Lusaran",
            "Mabini", "Mabolo", "Malubog", "Mambaling", "Pahina Central", "Pahina San Nicolas", "Pamutan",
            "Pardo", "Pari-an", "Paril", "Pasil", "Pit-os", "Pulangbato", "Pung-ol Sibugay", "Quiot",
            "Sambag I", "Sambag II", "San Antonio", "San Jose", "San Nicolas Central", "San Roque",
            "Santa Cruz", "Santo Niño", "Sapangdaku", "Sawang Calero", "Sinsin", "Sirao", "Suba", 
            "Sudlon I", "Sudlon II", "T. Padilla", "Tabunan", "Tagbao", "Talamban", "Taptap", "Tejero",
            "Tinago", "Tisa", "To-ong", "Zapatera"
        ],
        "MANDAUE CITY": [
            "Alang-alang", "Bakilid", "Banilad", "Basak", "Cabancalan", "Cambaro", "Canduman", "Casili", 
            "Casuntingan", "Centro", "Cubacub", "Guizo", "Ibabao-Estancia", "Jagobiao", "Labogon", "Looc",
            "Maguikay", "Mantuyong", "Opao", "Paknaan", "Pagsabungan", "Subangdaku", "Tabok", "Tawason",
            "Tingub", "Tipolo", "Umapad"
        ],
        "LAPU-LAPU CITY": [
            "Agus", "Babag", "Bankal", "Baring", "Basak", "Buaya", "Calawisan", "Canjulao", "Caw-oy", 
            "Cawhagan", "Gun-ob", "Ibo", "Looc", "Mactan", "Maribago", "Marigondon", "Pajac", "Pajo",
            "Pangan-an", "Pusok", "Sabang", "Santa Rosa", "Subabasbas", "Talo-ota", "Tungasan", "San Vicente"
        ],
        "TALISAY CITY": [
            "Biasong", "Bulacao", "Cabatangan", "Camp IV", "Cansojong", "Dumlog", "Jaclupan", "Lagtang",
            "Lawaan I", "Lawaan II", "Lawaan III", "Linao", "Maghaway", "Moho", "Pooc", "San Isidro",
            "San Roque", "Tabunoc", "Tangke", "Tapul", "Zone 1", "Zone 2", "Zone 3", "Zone 4", "Zone 5"
        ]
    };
    
    function handleNavigation(e) {
        if (e.target.getAttribute('href') && !e.target.getAttribute('href').startsWith('#')) {
            e.preventDefault();
            
            mainContent.style.animation = 'fadeOut 0.3s ease-in forwards';
            
            setTimeout(() => {
                window.location.href = e.target.getAttribute('href');
            }, 300);
        }
    }
    
    function updateBarangays() {
        const selectedCity = citySelect.value;
        const currentBarangay = barangaySelect.value;
        
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        
        if (selectedCity && barangaysByCity[selectedCity]) {
            barangaysByCity[selectedCity].forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay;
                option.textContent = barangay;
                if (barangay === currentBarangay) {
                    option.selected = true;
                }
                barangaySelect.appendChild(option);
            });
        }
        
        barangaySelect.disabled = !selectedCity;
    }
    
    function enhanceForms() {
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const passwordForm = this.querySelector('input[name="change_password"]');
                
                if (passwordForm) {
                    const currentPassword = document.getElementById('current_password');
                    const newPassword = document.getElementById('new_password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (newPassword.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('New password and confirmation do not match.');
                        confirmPassword.focus();
                        return;
                    }
                    
                    if (newPassword.value.length < 6) {
                        e.preventDefault();
                        alert('Password should be at least 6 characters long.');
                        newPassword.focus();
                        return;
                    }
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = 'Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    }
    
    function addInputAnimations() {
        const inputs = document.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            if (input.value !== '') {
                input.parentElement.classList.add('focused');
            }
        });
    }
    
    if (citySelect) {
        citySelect.addEventListener('change', updateBarangays);
    }
    
    navLinks.forEach(link => {
        link.addEventListener('click', handleNavigation);
    });
    
    enhanceForms();
    addInputAnimations();
    
    updateBarangays();
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});