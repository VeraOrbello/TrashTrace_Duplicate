document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const emailInput = document.getElementById('email');
    
    emailInput.addEventListener('blur', function() {
        validateEmail();
    });
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateEmail() {
        const email = emailInput.value.trim();
        const emailError = document.querySelector('#email + .invalid-feedback');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email === '') {
            emailInput.classList.add('is-invalid');
            emailError.textContent = 'Please enter your email address.';
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
    
    function validateForm() {
        return validateEmail();
    }
});