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

const signIn = (btn) => {
    toggleBtnLoader(btn, true);

    const email = $("#email").val().trim();
    const password = $("#password").val().trim();
    const rememberMe = $("#rememberMe").is(":checked");

    fetch("api/signIn.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            email: email,
            password: password,
            rememberMe: rememberMe
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                const validationMap = {
                    email: ["#email", "#email-feedback"],
                    password: ["#password", "#password-feedback"],
                };

                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const forgottenPassword = () => {
    toggleLoader(true);
    const data = {
        email: $("#email").val()
    }

    fetch('api/forgottenPassword.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('email')) {
                if (response.hasOwnProperty('email')) {
                    $("#email").addClass("is-invalid");
                    $("#email-feedback").html(response.email);
                } else {
                    $("#email").removeClass("is-invalid");
                    $("#email-feedback").html("");
                }
            }
        })
        .catch((error) => {

            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const signOut = () => {
    // A normal page load (not fetch), so the server's redirect to the sign-in page always happens.
    try { localStorage.removeItem("welcomeAlertShown"); } catch (e) { /* storage blocked */ }
    window.location.href = "api/signOut.php";
}

const addUser = (btn) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    const validationMap = {
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        title: ["#user-title", "#title-feedback"],
        email: ["#user-email", "#email-feedback"],
        contact: ["#user-contact-number", "#contact-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        role: ["#user-role", "#role-feedback"],
        gender: ["#user-gender", "#gender-feedback"],
        image: ["#user-profile-image-container", "#image-feedback"],
        nic: ["#user-national-id", "#nic-feedback"],
        dob: ["#user-dob", "#dob-feedback"],
        address: ["#address-line-1", "#address-feedback"],
        address2: ["#address-line-2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#pcode-feedback"],
        cv: ["#file-info", "#cv-feedback"]
    };

    fetch("api/addUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                console.log(response);
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Network error. Please try again.");
        });
};
const editUser = (btn, id) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("whatsapp", $("#user-whatsapp-number").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    const validationMap = {
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        title: ["#user-title", "#title-feedback"],
        email: ["#user-email", "#email-feedback"],
        contact: ["#user-contact-number", "#contact-feedback"],
        whatsapp: ["#user-whatsapp-number", "#whatsapp-feedback"],
        role: ["#user-role", "#role-feedback"],
        gender: ["#user-gender", "#gender-feedback"],
        image: ["#user-profile-image-container", "#image-feedback"],
        nic: ["#user-national-id", "#nic-feedback"],
        dob: ["#user-dob", "#dob-feedback"],
        address: ["#address-line-1", "#address-feedback"],
        address2: ["#address-line-2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#pcode-feedback"],
        cv: ["#file-info", "#cv-feedback"]
    };

    fetch("api/editUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Network error. Please try again.");
        });
}
const filterUsers = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        role: $("#role-selector").val() || "",
        sort: $("#sort-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        update: $("#update-selector").val() || "",
        page: selectedPage || 1,
        selectUsers: selectedUsers || [],
        unSelectUsers: unSelectedUsers || [],
    };

    fetch('api/filterUsers.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const applyUsersUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedUsers: selectedUsers,
            uncheckedUsers: unSelectedUsers,
            currentPage: selectedPage,
            text: $("#user-search-text").val(),
            role: $("#role-selector").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.error) {
                    showToast("error", response.error);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    toggleBtnLoader(btn, "done");
                    showToast("success", response.done);
                    filterUsers();
                    setDefault();
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired user status change option.");
    }
}
const applySelectedUserUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedUsers: selectedUsers, uncheckedUsers: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterUsers();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                setTimeout(() => { showToast("error", "Something went wrong"); }, 1000);
            });
    } else {
        showToast("warning", "Please select the desired user status change option.");
    }
}

const updateUserRegistrationRequestStatus = (btn, userId) => {
    toggleBtnLoader(btn, true);
    const data = {
        userId: userId,
        status: $("#status-selector").val(),
        plan: $("#plan-selector").val(),
        note: $("#note").val()
    }

    const validationMap = {
        status: ["#status-selector", "#status-feedback"],
        plan: ["#plan-selector", "#plan-feedback"],
        note: ["#note", "#note-feedback"]
    };


    fetch('api/updateUserRegistrationRequestStatus.php', {
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
                $("#updateRegRequestStatus").modal('hide');
                showToast("success", response.done);
                filterRequestedUsers();
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const filterRequestedUsers = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        sort: $("#sort-selector").val() || "",
        filter: $("#filter-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterRequestedUsers.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const addClass = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecturer", $("#lecturer").val());
    form.append("subject", $("#subject").val());
    form.append("exam_type", $("#exam-type").val());
    form.append("year", $("#year").val());
    form.append("medium", $("#medium").val());
    form.append("name", $("#class-name").val());
    form.append("class_cover", $("#cover-input").prop("files")[0]);

    const validationMap = {
        subject: ["#subject", "#subject-feedback"],
        year: ["#year", "#year-feedback"],
        exam_type: ["#exam-type", "#exam-type-feedback"],
        medium: ["#medium", "#medium-feedback"],
        name: ["#class-name", "#class-name-feedback"],
        class_cover: ["#cover-input", "#cover-feedback"],
    };

    fetch("api/addClass.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#addSubject").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
