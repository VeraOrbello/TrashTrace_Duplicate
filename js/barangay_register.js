document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.worker-form');
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    const idProofInput = document.getElementById('id_proof');
    const birthdateInput = document.getElementById('birthdate');
    const navLinks = document.querySelectorAll('.nav-link');
    const mainContent = document.querySelector('.register-main');
    const submitBtn = document.querySelector('.btn-submit');
    const cancelBtn = document.querySelector('.btn-cancel');
    
    const barangaysByCity = {
        "CEBU CITY": ["Adlaon", "Agsungot", "Apas", "Babag", "Bacayan", "Banilad", "Binaliw", "Bonbon", "Budlaan", "Buhisan", "Bulacao", "Buot", "Busay", "Cambinocot", "Capitol Site", "Carreta", "Cogon Ramos", "Cogon Pardo", "Duljo Fatima", "Ermita", "Guadalupe", "Guba", "Hipodromo", "Kalubihan", "Kalunasan", "Kamagayan", "Kamputhaw", "Kasambagan", "Kinasang-an", "Labangon", "Lahug", "Lorega San Miguel", "Lusaran", "Mabini", "Mabolo", "Malubog", "Mambaling", "Pahina Central", "Pahina San Nicolas", "Pamutan", "Pardo", "Pari-an", "Paril", "Pasil", "Pit-os", "Pulangbato", "Pung-ol Sibugay", "Quiot", "Sambag I", "Sambag II", "San Antonio", "San Jose", "San Nicolas Central", "San Roque", "Santa Cruz", "Santo Niño", "Sapangdaku", "Sawang Calero", "Sinsin", "Sirao", "Suba", "Sudlon I", "Sudlon II", "T. Padilla", "Tabunan", "Tagbao", "Talamban", "Taptap", "Tejero", "Tinago", "Tisa", "To-ong", "Zapatera"],
        "MANDAUE CITY": ["Alang-alang", "Bakilid", "Banilad", "Basak", "Cabancalan", "Cambaro", "Canduman", "Casili", "Casuntingan", "Centro", "Cubacub", "Guizo", "Ibabao-Estancia", "Jagobiao", "Labogon", "Looc", "Maguikay", "Mantuyong", "Opao", "Paknaan", "Pagsabungan", "Subangdaku", "Tabok", "Tawason", "Tingub", "Tipolo", "Umapad"],
        "LAPU-LAPU CITY": ["Agus", "Babag", "Bankal", "Baring", "Basak", "Buaya", "Calawisan", "Canjulao", "Caw-oy", "Cawhagan", "Gun-ob", "Ibo", "Looc", "Mactan", "Maribago", "Marigondon", "Pajac", "Pajo", "Pangan-an", "Pusok", "Sabang", "Santa Rosa", "Subabasbas", "Talo-ota", "Tungasan", "San Vicente"],
        "TALISAY CITY": ["Biasong", "Bulacao", "Cabatangan", "Camp IV", "Cansojong", "Dumlog", "Jaclupan", "Lagtang", "Lawaan I", "Lawaan II", "Lawaan III", "Linao", "Maghaway", "Moho", "Pooc", "San Isidro", "San Roque", "Tabunoc", "Tangke", "Tapul", "Zone 1", "Zone 2", "Zone 3", "Zone 4", "Zone 5"]
    };
    
    function initCityBarangay() {
        if (citySelect) {
            const currentCity = "<?php echo $user_data['city'] ?? ''; ?>";
            const currentBarangay = "<?php echo $user_data['barangay'] ?? ''; ?>";
            
            if (currentCity) {
                citySelect.value = currentCity;
                updateBarangayOptions();
                
                if (currentBarangay && barangaySelect.querySelector(`option[value="${currentBarangay}"]`)) {
                    barangaySelect.value = currentBarangay;
                }
            }
            
            citySelect.addEventListener('change', updateBarangayOptions);
        }
    }
    
    function updateBarangayOptions() {
        if (!citySelect || !barangaySelect) return;
        
        const selectedCity = citySelect.value;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        
        if (selectedCity && barangaysByCity[selectedCity]) {
            barangaysByCity[selectedCity].forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay;
                option.textContent = barangay;
                barangaySelect.appendChild(option);
            });
        }
        
        barangaySelect.disabled = !selectedCity;
        animateElement(barangaySelect, 'fadeIn');
    }
    
    function validateFileUpload(fileInput) {
        if (!fileInput.files.length) return true;
        
        const file = fileInput.files[0];
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const maxSize = 2 * 1024 * 1024;
        
        if (!validTypes.includes(file.type)) {
            showToast('Please upload only JPG, PNG, or PDF files', 'error');
            fileInput.value = '';
            return false;
        }
        
        if (file.size > maxSize) {
            showToast('File size should not exceed 2MB', 'error');
            fileInput.value = '';
            return false;
        }
        
        return true;
    }
    
    function validateForm() {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                highlightField(field, true);
                isValid = false;
            } else {
                highlightField(field, false);
            }
        });
        
        if (!isValid) {
            showToast('Please fill all required fields', 'error');
        }
        
        return isValid;
    }
    
    function highlightField(field, isError) {
        const formGroup = field.closest('.form-group');
        if (formGroup) {
            if (isError) {
                formGroup.classList.add('field-error');
                animateElement(formGroup, 'shake');
            } else {
                formGroup.classList.remove('field-error');
            }
        }
    }
    
    function initDatePicker() {
        if (birthdateInput) {
            const today = new Date().toISOString().split('T')[0];
            const minDate = new Date();
            minDate.setFullYear(minDate.getFullYear() - 70);
            
            birthdateInput.max = today;
            birthdateInput.min = minDate.toISOString().split('T')[0];
            
            birthdateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const age = new Date().getFullYear() - selectedDate.getFullYear();
                
                if (age < 18) {
                    showToast('You must be at least 18 years old to work', 'warning');
                }
            });
        }
    }
    
    function initFormAnimations() {
        const formGroups = form.querySelectorAll('.form-group');
        formGroups.forEach((group, index) => {
            group.style.animationDelay = `${index * 0.05}s`;
            animateElement(group, 'fadeInUp');
        });
    }
    
    function initBenefitCards() {
        const benefitItems = document.querySelectorAll('.benefit-item');
        benefitItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
            animateElement(item, 'fadeInLeft');
            
            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateX(10px) scale(1.02)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0) scale(1)';
            });
        });
    }
    function initSubmitButton() {
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            
            if (idProofInput && !validateFileUpload(idProofInput)) {
                e.preventDefault();
                return;
            }
            
            const originalText = this.innerHTML;
            const originalDisabled = this.disabled;
            
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            this.disabled = true;
            
            setTimeout(() => {
                if (form && form.checkValidity()) {
                    form.submit();
                } else {
                    this.innerHTML = originalText;
                    this.disabled = originalDisabled;
                    showToast('Please fix form errors before submitting', 'error');
                }
            }, 500);
        });
    }
}
    
    function initCancelButton() {
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (hasUnsavedChanges()) {
                    if (confirm('You have unsaved changes. Are you sure you want to cancel?')) {
                        navigateToUrl(this.href);
                    }
                } else {
                    navigateToUrl(this.href);
                }
            });
        }
    }
    
    function hasUnsavedChanges() {
        const inputs = form.querySelectorAll('input, textarea, select');
        let hasChanges = false;
        
        inputs.forEach(input => {
            if (input.type !== 'submit' && input.type !== 'button') {
                if (input.value.trim() !== '') {
                    hasChanges = true;
                }
            }
        });
        
        return hasChanges;
    }
    
    function navigateToUrl(url) {
        mainContent.style.animation = 'fadeOut 0.3s ease-in forwards';
        
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    }
    
    function initNavigation() {
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    navigateToUrl(this.href);
                }
            });
        });
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        animateElement(toast, 'slideInRight');
        
        setTimeout(() => {
            animateElement(toast, 'slideOutRight', () => {
                toast.remove();
            });
        }, 3000);
    }
    
    function animateElement(element, animationName, callback = null) {
        element.classList.add(`animate-${animationName}`);
        
        if (callback) {
            element.addEventListener('animationend', function handler() {
                element.removeEventListener('animationend', handler);
                callback();
            });
        }
    }
    
    function initCharacterCounters() {
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            const charCount = document.createElement('div');
            charCount.className = 'char-count';
            charCount.textContent = `${textarea.value.length}/${textarea.maxLength || '∞'}`;
            
            textarea.parentNode.appendChild(charCount);
            
            textarea.addEventListener('input', function() {
                const currentLength = this.value.length;
                const maxLength = this.maxLength || Infinity;
                
                charCount.textContent = `${currentLength}/${maxLength === Infinity ? '∞' : maxLength}`;
                
                if (maxLength !== Infinity && currentLength > maxLength * 0.8) {
                    charCount.style.color = '#ff9800';
                } else {
                    charCount.style.color = '#666';
                }
                
                if (maxLength !== Infinity && currentLength >= maxLength) {
                    charCount.style.color = '#f44336';
                    showToast(`Maximum ${maxLength} characters reached`, 'warning');
                }
            });
        });
    }
    
    function initCopyInfo() {
        const copyButtons = document.querySelectorAll('.copy-info');
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const infoId = this.dataset.copy;
                const infoElement = document.getElementById(infoId);
                
                if (infoElement) {
                    navigator.clipboard.writeText(infoElement.textContent)
                        .then(() => showToast('Copied to clipboard', 'success'))
                        .catch(() => showToast('Failed to copy', 'error'));
                }
            });
        });
    }
    
    function initFormStepper() {
        const formSections = document.querySelectorAll('.form-section');
        const stepper = document.createElement('div');
        stepper.className = 'form-stepper';
        
        formSections.forEach((section, index) => {
            const step = document.createElement('div');
            step.className = 'stepper-step';
            step.innerHTML = `
                <div class="step-number">${index + 1}</div>
                <div class="step-title">${section.querySelector('h3').textContent}</div>
            `;
            
            step.addEventListener('click', () => {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                highlightStep(step);
            });
            
            stepper.appendChild(step);
        });
        
        form.insertBefore(stepper, form.firstChild);
    }
    
    function highlightStep(step) {
        document.querySelectorAll('.stepper-step').forEach(s => {
            s.classList.remove('active');
        });
        step.classList.add('active');
    }
    
    function initAutoSave() {
        const autoSaveIndicator = document.createElement('div');
        autoSaveIndicator.className = 'auto-save-indicator';
        autoSaveIndicator.innerHTML = '<i class="fas fa-save"></i> <span>Auto-saved</span>';
        autoSaveIndicator.style.display = 'none';
        
        form.appendChild(autoSaveIndicator);
        
        let saveTimeout;
        form.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            
            autoSaveIndicator.style.display = 'flex';
            autoSaveIndicator.classList.remove('saved');
            
            saveTimeout = setTimeout(() => {
                localStorage.setItem('worker_validation_draft', JSON.stringify({
                    timestamp: new Date().toISOString(),
                    formData: new FormData(form)
                }));
                
                autoSaveIndicator.classList.add('saved');
                setTimeout(() => {
                    autoSaveIndicator.style.display = 'none';
                }, 2000);
            }, 1000);
        });
    }
    
    function loadDraft() {
        const draft = localStorage.getItem('worker_validation_draft');
        if (draft) {
            const data = JSON.parse(draft);
            const draftDate = new Date(data.timestamp);
            const now = new Date();
            const hoursDiff = (now - draftDate) / (1000 * 60 * 60);
            
            if (hoursDiff < 24) {
                if (confirm('We found a saved draft from your last session. Would you like to restore it?')) {
                    showToast('Draft restored', 'success');
                }
            }
        }
    }
    
    function initRedirectTimer() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            let seconds = 3;
            const redirectText = successAlert.querySelector('.notification-info p:last-child');
            
            const countdown = setInterval(() => {
                seconds--;
                redirectText.innerHTML = `<i class="fas fa-clock"></i> Redirecting to home in ${seconds} seconds...`;
                
                if (seconds <= 0) {
                    clearInterval(countdown);
                    window.location.href = 'dashboard.php';
                }
            }, 1000);
        }
    }
    
    initCityBarangay();
    initDatePicker();
    initFormAnimations();
    initBenefitCards();
    initSubmitButton();
    initCancelButton();
    initNavigation();
    initCharacterCounters();
    initCopyInfo();
    initFormStepper();
    initAutoSave();
    loadDraft();
    initRedirectTimer();
    
    if (idProofInput) {
        idProofInput.addEventListener('change', function() {
            validateFileUpload(this);
        });
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }
});