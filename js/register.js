document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const fullNameInput = document.getElementById('full_name');
    const emailInput = document.getElementById('email');
    const cityInput = document.getElementById('city');
    const barangayInput = document.getElementById('barangay');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    

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
    

    fullNameInput.addEventListener('blur', function() {
        validateFullName();
    });
    
    emailInput.addEventListener('blur', function() {
        validateEmail();
    });
    
    cityInput.addEventListener('change', function() {
        validateCity();
        updateBarangays();
    });
    
    barangayInput.addEventListener('change', function() {
        validateBarangay();
    });
    
    passwordInput.addEventListener('blur', function() {
        validatePassword();
    });
    
    confirmPasswordInput.addEventListener('blur', function() {
        validateConfirmPassword();
    });
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    

    function updateBarangays() {
        const selectedCity = cityInput.value;
        barangayInput.innerHTML = '<option value="">Select Barangay</option>';
        
        if (selectedCity && barangaysByCity[selectedCity]) {
            barangaysByCity[selectedCity].forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay;
                option.textContent = barangay;
                barangayInput.appendChild(option);
            });
        }
        

        barangayInput.disabled = !selectedCity;
    }
    
    function validateFullName() {
        const fullName = fullNameInput.value.trim();
        const fullNameError = document.querySelector('#full_name + .invalid-feedback');
        
        if (fullName === '') {
            fullNameInput.classList.add('is-invalid');
            fullNameError.textContent = 'Please enter your full name.';
            return false;
        } else {
            fullNameInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateEmail() {
        const email = emailInput.value.trim();
        const emailError = document.querySelector('#email + .invalid-feedback');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email === '') {
            emailInput.classList.add('is-invalid');
            emailError.textContent = 'Please enter an email address.';
            return false;
        } else if (!emailPattern.test(email)) {
            emailInput.classList.add('is-invalid');
            emailError.textContent = 'Please enter a valid email address.';
            return false;
        } else {
            emailInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateCity() {
        const city = cityInput.value;
        const cityError = document.querySelector('#city + .invalid-feedback');
        
        if (city === '') {
            cityInput.classList.add('is-invalid');
            cityError.textContent = 'Please select your city.';
            return false;
        } else {
            cityInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateBarangay() {
        const barangay = barangayInput.value;
        const barangayError = document.querySelector('#barangay + .invalid-feedback');
        
        if (barangay === '') {
            barangayInput.classList.add('is-invalid');
            barangayError.textContent = 'Please select your barangay.';
            return false;
        } else {
            barangayInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validatePassword() {
        const password = passwordInput.value.trim();
        const passwordError = document.querySelector('#password + .invalid-feedback');
        
        if (password === '') {
            passwordInput.classList.add('is-invalid');
            passwordError.textContent = 'Please enter a password.';
            return false;
        } else if (password.length < 6) {
            passwordInput.classList.add('is-invalid');
            passwordError.textContent = 'Password must have at least 6 characters.';
            return false;
        } else {
            passwordInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateConfirmPassword() {
        const password = passwordInput.value.trim();
        const confirmPassword = confirmPasswordInput.value.trim();
        const confirmPasswordError = document.querySelector('#confirm_password + .invalid-feedback');
        
        if (confirmPassword === '') {
            confirmPasswordInput.classList.add('is-invalid');
            confirmPasswordError.textContent = 'Please confirm password.';
            return false;
        } else if (password !== confirmPassword) {
            confirmPasswordInput.classList.add('is-invalid');
            confirmPasswordError.textContent = 'Password did not match.';
            return false;
        } else {
            confirmPasswordInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateForm() {
        const isFullNameValid = validateFullName();
        const isEmailValid = validateEmail();
        const isCityValid = validateCity();
        const isBarangayValid = validateBarangay();
        const isPasswordValid = validatePassword();
        const isConfirmPasswordValid = validateConfirmPassword();
        
        return isFullNameValid && isEmailValid && isCityValid && isBarangayValid && isPasswordValid && isConfirmPasswordValid;
    }
    
  
    updateBarangays();
});