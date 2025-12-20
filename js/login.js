document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    usernameInput.addEventListener('blur', function() {
        validateUsername();
    });
    
    passwordInput.addEventListener('blur', function() {
        validatePassword();
    });
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateUsername() {
        const username = usernameInput.value.trim();
        const usernameError = document.querySelector('#username + .invalid-feedback');
        
        if (username === '') {
            usernameInput.classList.add('is-invalid');
            usernameError.textContent = 'Please enter username or email.';
            return false;
        } else {
            usernameInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validatePassword() {
        const password = passwordInput.value.trim();
        const passwordError = document.querySelector('#password + .invalid-feedback');
        
        if (password === '') {
            passwordInput.classList.add('is-invalid');
            passwordError.textContent = 'Please enter your password.';
            return false;
        } else if (password.length < 6) {
            passwordInput.classList.add('is-invalid');
            passwordError.textContent = 'Password must be at least 6 characters.';
            return false;
        } else {
            passwordInput.classList.remove('is-invalid');
            return true;
        }
    }
    
    function validateForm() {
        const isUsernameValid = validateUsername();
        const isPasswordValid = validatePassword();
        
        return isUsernameValid && isPasswordValid;
    }
});