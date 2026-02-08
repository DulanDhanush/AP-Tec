/* Login Page Interactive Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // Role Selection Logic
    const roleBtns = document.querySelectorAll('.role-option');
    const roleInput = document.getElementById('selected_role');

    roleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Remove active class from all
            roleBtns.forEach(b => b.classList.remove('active'));
            
            // 2. Add active class to clicked
            btn.classList.add('active');
            
            // 3. Update hidden input value (for backend)
            const role = btn.getAttribute('data-role');
            if(roleInput) roleInput.value = role;

            console.log(`Role switched to: ${role}`);
        });
    });

    // Input Focus Animation (Optional Enhancement)
    const inputs = document.querySelectorAll('.login-input');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('focused');
        });
    });
});