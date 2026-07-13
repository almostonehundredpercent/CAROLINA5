function showLogin() {
    document.getElementById("loginCard").classList.add("active");
    document.getElementById("signupCard").classList.remove("active");
}

function showSignup() {
    document.getElementById("signupCard").classList.add("active");
    document.getElementById("loginCard").classList.remove("active");
}

function continueGuest() {
    window.location.href = "/home";
}