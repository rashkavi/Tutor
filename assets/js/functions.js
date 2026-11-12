const applyValidation = (response, validationMap, showMsg = true) => {
    if (showMsg) {
        showToast('error', 'Something went wrong.Check data fields and try again');
    }
    $('.contentBodyArea').animate({ scrollTop: 0 }, 'fast');
    Object.entries(validationMap).forEach(([key, selectors]) => {
        const [input, feedback] = selectors;

        if (response[key]) {
            $(input).addClass("is-invalid");
            $(feedback).text(response[key]);
        } else {
            $(input).removeClass("is-invalid");
            $(feedback).text("");
        }
    });
}

// The tutor sign-up form is assets/landing/signup.js (POST /api/v1/public/tutor-signup).
