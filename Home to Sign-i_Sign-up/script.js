document.addEventListener('DOMContentLoaded', () => {
    const signupContainer = document.getElementById('signup');
    const signinContainer = document.getElementById('signIn');
    const forgotPasswordContainer = document.getElementById('forgotPassword');
    
    const signUpButton = document.getElementById('signUpButton');
    const signInButton = document.getElementById('signInButton');
    const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
    const backToLoginBtn = document.getElementById('backToLoginBtn');

    // Check URL parameters to determine which form to show
    const urlParams = new URLSearchParams(window.location.search);
    const formType = urlParams.get('form');

    if (formType === 'signup') {
        signinContainer.style.display = 'none';
        signupContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    } else if (formType === 'signin') {
        signupContainer.style.display = 'none';
        signinContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    } else {
        // Default to sign-in if no parameter
        signupContainer.style.display = 'none';
        signinContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    }

    // Rest of your existing event listeners...
    signUpButton.addEventListener('click', () => {
        signinContainer.style.display = 'none';
        signupContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    });

    signInButton.addEventListener('click', () => {
        signupContainer.style.display = 'none';
        signinContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    });

    forgotPasswordBtn.addEventListener('click', () => {
        signupContainer.style.display = 'none';
        signinContainer.style.display = 'none';
        forgotPasswordContainer.style.display = 'block';
    });

    backToLoginBtn.addEventListener('click', () => {
        signupContainer.style.display = 'none';
        signinContainer.style.display = 'block';
        forgotPasswordContainer.style.display = 'none';
    });
});