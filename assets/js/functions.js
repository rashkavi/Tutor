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

const submitTutorRegistrationApplication = (btn) => {
    toggleBtnLoader(btn, true);

    const data = {
        title: $("#title").val(),
        nic: $("#nic").val(),
        fname: $("#firstName").val(),
        lname: $("#lastName").val(),
        email: $("#email").val(),
        mobile: $("#mobile").val(),
        whatsapp: $("#whatsapp").val(),
    }

    const validationMap = {
        title: ["#title", "#title-feedback"],
        nic: ["#nic", "#nic-feedback"],
        fname: ["#firstName", "#fname-feedback"],
        lname: ["#lastName", "#lname-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
    }

    fetch('api/submitTutorRegistrationApplication.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(res => res.json())
        .then(response => {
            toggleBtnLoader(btn, false);

            if (response.done) {
                showToast('success', response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast('error', response.error);
            } else {
                applyValidation(response, validationMap);
            }
        });
}