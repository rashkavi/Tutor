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

const sendOTP = (btn) => {
    toggleBtnLoader(btn, true);
    const mobile = $("#mobile").val().trim();

    fetch("api/sendOtp.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            mobile: mobile,
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);

            if (response.done) {
                showToast("success", response.done);
                $("#content-div").html(`
                <p class="text-24 sub-topic-color fw-medium lh-lg">Tutor Login</p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    ${Array.from({ length: 6 }).map((_, i) => `
                        <input type="text" 
                               maxlength="1" 
                               data-index="${i}"
                               class="form-control custom-input otp-digit"
                               style="width: 3rem; 
                                      height: 3rem; 
                                      text-align: center; 
                                      font-size: 18px; 
                                      transition: border 0.2s;"
                               oninput="handleOTPInput(this)"
                               onkeydown="handleOTPKeyDown(event, this)">
                    `).join('')}
                </div>
                <span id="otp-feedback" class="text-dark-red text-12"></span>
                <!-- Hidden input to store complete OTP -->
                <input type="hidden" id="completeOTP">
                
                <div style="text-align: center; font-size: 14px; color: #666; margin-bottom: 16px;">
                    Didn't receive code? 
                    <a id="resendLink" class="text-theme text-decoration-none cursor-pointor" onclick="resendOTP('${mobile}')">Resend</a>
                    <span id="timer" style="margin-left: 4px;"></span>
                </div>
                
                <button class="w-100 custom-btn d-flex align-items-center justify-content-center" id="verifyBtn" onclick="signIn(this,'${mobile}');" disabled>
                    <span class="text-16 fw-semibold pe-3">Sign In</span>
                    <iconify-icon icon="rivet-icons:arrow-right" width="16" height="16" class="text-white"></iconify-icon>
                </button>
            `);

                // Initialize OTP verification logic
                let timeLeft = 120;
                const timer = document.getElementById('timer');
                const resendLink = document.getElementById('resendLink');
                resendLink.style.display = 'none';

                // Start countdown timer
                const countdown = setInterval(() => {
                    timeLeft--;
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;

                    timer.textContent = `(${minutes}:${seconds < 10 ? '0' : ''}${seconds})`;

                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        timer.textContent = '';
                        resendLink.style.display = 'inline';

                    }
                }, 1000);

                // Focus first OTP input
                document.querySelector('input[data-index="0"]').focus();

            } else {
                showToast("error", 'An error occurred. Please try again.');
                $("#mobile").addClass("is-invalid");
                if (response.error) {
                    $("#mobile-feedback").html(response.error);
                } else {
                    $("#mobile-feedback").html(response.mobile);
                }
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", 'An error occurred. Please try again.');
        });
}

// Handle OTP input movement between fields
const handleOTPInput = (input) => {
    // Only allow numbers
    input.value = input.value.replace(/[^0-9]/g, '');

    // Move to next field if a digit was entered
    if (input.value.length === 1) {
        const nextIndex = parseInt(input.dataset.index) + 1;
        const nextInput = document.querySelector(`.otp-digit[data-index="${nextIndex}"]`);
        if (nextInput) {
            nextInput.focus();
        } else {
            // All digits filled, enable verify button
            checkOTPComplete();
        }
    }
    // Update complete OTP value
    updateCompleteOTP();
}

// Handle backspace/delete navigation
const handleOTPKeyDown = (event, input) => {
    if (event.key === 'Backspace' && input.value === '') {
        const prevIndex = parseInt(input.dataset.index) - 1;
        const prevInput = document.querySelector(`.otp-digit[data-index="${prevIndex}"]`);
        if (prevInput) prevInput.focus();
    }

    // Update complete OTP value
    updateCompleteOTP();
}

// Update the complete OTP value
const updateCompleteOTP = () => {
    const otpDigits = document.querySelectorAll('.otp-digit');
    let completeOTP = '';

    otpDigits.forEach(input => {
        completeOTP += input.value;
    });

    document.getElementById('completeOTP').value = completeOTP;
    checkOTPComplete();
}

// Check if all OTP digits are entered
function checkOTPComplete() {
    const completeOTP = document.getElementById('completeOTP').value;
    const verifyBtn = document.getElementById('verifyBtn');

    if (completeOTP.length === 6) {
        verifyBtn.disabled = false;
    } else {
        verifyBtn.disabled = true;
    }
}

const resendOTP = (mobile) => {
    toggleLoader(true);
    fetch("api/sendOtp.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            mobile: mobile,
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);

            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);

                // Initialize OTP verification logic
                let timeLeft = 120;
                const timer = document.getElementById('timer');
                const resendLink = document.getElementById('resendLink');
                resendLink.style.display = 'none';

                // Start countdown timer
                const countdown = setInterval(() => {
                    timeLeft--;
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;

                    timer.textContent = `(${minutes}:${seconds < 10 ? '0' : ''}${seconds})`;

                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        timer.textContent = '';
                        resendLink.style.display = 'inline';

                    }
                }, 1000);
                // Focus first OTP input
                document.querySelector('input[data-index="0"]').focus();

            } else {
                setTimeout(() => { showToast("error", 'An error occurred. Please try again.'); }, 1000);
                if (response.error) {
                    $("#otp-feedback").html(response.error);
                } else {
                    $("#otp-feedback").html(response.mobile);
                }
            }
        })
        .catch((error) => {
            showToast("error", 'An error occurred. Please try again.');
        });
}

// Function to verify OTP (called when button is clicked or auto-submit)
const signIn = (btn, mobile) => {
    toggleBtnLoader(btn, true);
    const completeOTP = document.getElementById('completeOTP').value;

    if (completeOTP.length !== 6) {
        showToast("error", "Please enter complete 6-digit OTP");
        return;
    }
    fetch("api/signIn.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            mobile: mobile,
            otp: completeOTP
        }),
    })
        .then(res => res.json())
        .then(response => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                showToast("error", response.mobile);
                if (response.hasOwnProperty('otp')) {
                    $("#otp-feedback").html(response.otp);
                } else {
                    $("#otp-feedback").html("");
                }
            }
        })
        .catch(error => {
            toggleLoader(false);
            showToast("error", "An error occurred. Please try again.");
        });
}
const signOut = () => {
    fetch("api/signOut.php", {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            if (data.error) {
                localStorage.removeItem('theme');
                localStorage.removeItem("welcomeAlertShown");
                showToast("error", data.error);
            } else {
                window.location.reload();
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadDistrict = () => {
    const id = $("#province").val();
    fetch(`api/loadDistrict.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#district").html(data);
                $('#district').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadCities = () => {
    const id = $("#district").val();
    fetch(`api/loadCities.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#city").html(data);
                $('#city').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const addUser = (btn) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
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
                const validationMap = {
                    fname: ["#first-name", "#fname-feedback"],
                    lname: ["#last-name", "#lname-feedback"],
                    title: ["#user-title", "#title-feedback"],
                    email: ["#user-email", "#email-feedback"],
                    contact: ["#user-contact-number", "#contact-feedback"],
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

    fetch("api/editUser.php",
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
                const validationMap = {
                    fname: ["#first-name", "#fname-feedback"],
                    lname: ["#last-name", "#lname-feedback"],
                    title: ["#user-title", "#title-feedback"],
                    email: ["#user-email", "#email-feedback"],
                    contact: ["#user-contact-number", "#contact-feedback"],
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

const addStudentToClass = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("class_id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("gender", $("#gender-selector").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);

    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
    };

    fetch("api/addStudentToClass.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
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
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("gender", $("#gender-selector").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
    };

    fetch("api/editStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
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
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const uploadStudentBulk = async (btn, classId) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("class_id", classId);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            $("#csv-file-feedback").text('');
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                $("#csv-file-feedback").text('');
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.file);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                showToast("error", "An error occured.Please check error log and try again.");
                setTimeout(() => toggleBtnLoader(btn, false), 4000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 3000);
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 4000);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterStudents = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        class : $("#class-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const applyStudentsUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    filterStudents();
                    setDefault();
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    showToast("error", response.error);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired student status change option.");
    }
}

const applySelectedStudentUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedStudents: selectedUsers, uncheckedStudents: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterStudents();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addClass = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
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

const editClass = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("class", id);
    form.append("subject", $("#edit-subject").val());
    form.append("exam_type", $("#edit-exam-type").val());
    form.append("year", $("#edit-year").val());
    form.append("medium", $("#edit-medium").val());
    form.append("name", $("#edit-class-name").val());
    form.append("class_cover", $("#edit-cover-input").prop("files")[0]);

    const validationMap = {
        subject: ["#edit-subject", "#edit-subject-feedback"],
        year: ["#edit-year", "#edit-year-feedback"],
        exam_type: ["#edit-exam-type", "#edit-exam-type-feedback"],
        medium: ["#edit-medium", "#edit-medium-feedback"],
        name: ["#edit-class-name", "#edit-class-name-feedback"],
        class_cover: ["#edit-cover-input", "#edit-cover-feedback"],
    };

    fetch("api/editClass.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#editClass").modal("hide");
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

const editSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("subject_name", $("#edit-subject-name").val());
    form.append("subject_cover", $("#edit-cover-input").prop("files")[0]);

    fetch("api/editSubject.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editSubject").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                const validationMap = {
                    name: ["#edit-subject-name", "#ef-name-feedback"],
                    cover: ["#edit-subject-cover", "#ef-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addBatch = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("batch_name", $("#batch-name").val());
    form.append("batch_medium", $("#batch-medium").val());
    form.append("batch_cover", $("#cover-input").prop("files")[0]);

    fetch("api/addBatch.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.text())
        .then((response) => {
            console.log(response);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#addBatch").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#subject-name", "#as-name-feedback"],
                    cover: ["#subject-cover", "#as-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterClassStudents = (id) => {
    const data = {
        class_id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterClassStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const scheduleLectureToClass = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("class", $("#class-selector").val());
    form.append("lecture-date", $("#lecture-date").val());
    form.append("title", $("#title").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("one_time", $("#oneTimeLecture").is(":checked") ? true : false);
    form.append("recurring", $("#recurringLecture").is(":checked") ? true : false);
    form.append("start_date", $("#start-date").val());
    form.append("end_date", $("#end-date").val());
    if ($("#recurringLecture").is(":checked")) {
        form.append("start_time", $("#r-start-time").val());
        form.append("end_time", $("#r-end-time").val());
    } else {
        form.append("start_time", $("#start-time").val());
        form.append("end_time", $("#end-time").val());
    }

    form.append("days", selectedDays);
    form.append("skip_poya_days", $("#skip-poya").is(":checked") ? true : false);
    form.append("skip_holidays", $("#skip-holidays").is(":checked") ? true : false);
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        date: ["#lecture-date", "#date-feedback"],
        start_time: ["#start-time", "#start-time-feedback"],
        end_time: ["#end-time", "#end-time-feedback"],
        attachment_name: ["#attachment-name", "#attachment-name-feedback"],
        link: ["#link-input", "#link-feedback"],
        title: ["#title", "#title-feedback"],
        tutes: ["#file-selector-input", "#tutes-feedback"],
        start_date: ["#start-date", "#start-date-feedback"],
        end_date: ["#end-date", "#end-date-feedback"],
        days: ["#days", "#days-feedback"],
    };

    if ($("#recurringLecture").is(":checked")) {
        validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
        validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
    }

    fetch("api/scheduleLectureToClass.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else {
                applyValidation(response, validationMap);
                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }
        })
        .catch((error) => {
            showToast("error", "Something went wrong.Please try again later");
        });
}

const editLecture = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("title", $("#title").val());
    form.append("lecture_date", $("#lecture-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append("status", $("#status-selector").val());
    form.append("physical", $("#physical-checkbox").is(":checked"));
    form.append("online", $("#online-checkbox").is(":checked"));
    form.append("recording", $("#recording-checkbox").is(":checked"));
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    date: ["#lecture-date", "#date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                    lecturer: ["#lecturer-input", "#lecturer-feedback"],
                    title: ["#title", "#title-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }

        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}


const filterClassLectureAttendance = (lectureId) => {
    const data = {
        lecture_id: lectureId,
        text: $("#search-text")?.val(),
        sort: $("#sort-selector")?.val(),
        sortByName: sortCondition1,
        sortById: sortCondition2,
        resultPerPage: $("#show-result-selector")?.val(),
        page: selectedPage,
    }

    fetch('api/filterClassLectureAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#attendance-table-container").html(data);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const addPaymentPlan = (btn, id) => {
    toggleBtnLoader(btn, true);

    const data = {
        class: id,
        amount: $("#amount").val(),
        discountType: $("#discount-type").val(),
        discount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        dueDate: $("#due-date").val(),
        isEnablePanelty: $("#enable-panelty").is(":checked"),
        penaltyOption: $("#panelty-option").val(),
        penalty: collectPenaltyData(),
    }

    const validationMap = {
        amount: ["#amount", "#amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discount: ["#discount", "#discount-feedback"],
        from: ["#from-date", "#from-date-feedback"],
        to: ["#to-date", "#to-date-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
    };

    fetch('api/addPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
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

const editPaymentPlan = (btn, id) => {
    toggleBtnLoader(btn, true);

    const data = {
        class: id,
        amount: $("#amount").val(),
        discountType: $("#discount-type").val(),
        discount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        dueDate: $("#due-date").val(),
        isEnablePanelty: $("#enable-panelty").is(":checked"),
        penaltyOption: $("#panelty-option").val(),
        penalty: collectPenaltyData(),
        note: $("#note").val()
    }

    const validationMap = {
        amount: ["#amount", "#amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discount: ["#discount", "#discount-feedback"],
        from: ["#from-date", "#from-date-feedback"],
        to: ["#to-date", "#to-date-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
        note: ["#note", "#note-feedback"],
    };

    fetch('api/editPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
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

const updatePaymentStatus = (btn, paymentId) => {
    toggleBtnLoader(btn, true);
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updatePaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const addStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("class_id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("gender", $("#gender-selector").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append('class_id', $("#class-selector").val());

    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        gwhatsapp: ["#gwhatsapp", "#gwhatsapp-feedback"],
    };

    fetch("api/addStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
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
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntegration = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        name: $("#integration-name").val(),
        desc: $("#integration-desc").val(),
        type: $("#integration-type").val(),
    }

    const validationMap = {
        name: ["#integration-name", "#integration-name-feedback"],
        desc: ["#integration-desc", "#integration-desc-feedback"],
        type: ["#integration-type", "#integration-type-feedback"],
    };

    fetch("api/addIntegration.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
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
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

function connectZoom(id) {
    window.location.href = "zoom-connect.php?id=" + id;
}

const addVideo = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("title", $("#video-title").val());
    form.append("desc", $("#video-desc").val());
    form.append("video", $("#video-file").prop("files")[0]);

    const validationMap = {
        subject: ["#subject", "#subject-feedback"],
        year: ["#year", "#year-feedback"],
        exam_type: ["#exam-type", "#exam-type-feedback"],
        medium: ["#medium", "#medium-feedback"],
        name: ["#class-name", "#class-name-feedback"],
        class_cover: ["#cover-input", "#cover-feedback"],
    };

    fetch("api/addVideo.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
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

const addPreRecording = (btn) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("class", $("#class-selector").val());
    form.append("title", $("#title").val());
    form.append("description", $("#description").val());
    form.append("duration", $("#duration").val());
    form.append("rec_cover", $("#rec-cover-input").prop("files")[0]);
    form.append("youtube", $("#youtube-check-box").is(":checked"));
    form.append("video", $("#video-check-box").is(":checked"));
    form.append("link", $("#link").val());
    const videoEl = $("#video-input-field")[0];
    if (videoEl && videoEl.files.length > 0) {
        form.append("video_file", videoEl.files[0]);
    }

    const files = $("#file-selector-input")[0].files;
    for (let i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        title: ["#title", "#title-feedback"],
        description: ["#description", "#description-feedback"],
        duration: ["#duration", "#duration-feedback"],
        rec_cover: ["#rec-cover-input", "#rec-cover-feedback"],
        youtube: ["#youtube-check-box", "#youtube-feedback"],
        video: ["#video-check-box", "#video-feedback"],
        link: ["#link", "#link-feedback"],
        video_file: ["#video-input-field", "#video-feedback"],
    }



    const xhr = new XMLHttpRequest();

    $("#upload-container").removeClass("d-none");

    xhr.upload.addEventListener("progress", function (e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            $("#upload-progress-bar").css("width", percent + "%");
            $("#upload-progress-text").text(percent + "%");
        }
    });

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            toggleBtnLoader(btn, false);

            if (xhr.status === 200) {
                try {
                    console.log(xhr.responseText);
                    const response = JSON.parse(xhr.responseText);
                    if (response.done) {
                        $("#upload-progress-bar").css("width", "100%");
                        $("#upload-progress-text").text("Completed ✓");
                        toggleBtnLoader(btn, "done");
                        showToast("success", response.done);
                        setTimeout(() => window.location.reload(), 3000);
                    } else if (response.error) {
                        showToast("error", response.error);
                    } else {
                        applyValidation(response, validationMap);
                    }
                } catch (err) {
                    console.log(err);
                    showToast("error", "Invalid server response.");
                }
            } else {
                showToast("error", "Upload failed.");
            }
        }
    };

    xhr.open("POST", "api/addPreRecording.php", true);
    xhr.send(form);
};

const editPreRecording = (btn, preRecordingId) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("recording_id", preRecordingId);
    form.append("title", $("#title").val());
    form.append("description", $("#description").val());
    form.append("duration", $("#duration").val());
    form.append("rec_cover", $("#rec-cover-input").prop("files")[0]);
    form.append("youtube", $("#youtube-check-box").is(":checked"));
    form.append("video", $("#video-check-box").is(":checked"));
    form.append("link", $("#link").val());
    const videoEl = $("#video-input-field")[0];
    if (videoEl && videoEl.files.length > 0) {
        form.append("video_file", videoEl.files[0]);
    }

    const files = $("#file-selector-input")[0].files;
    for (let i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        title: ["#title", "#title-feedback"],
        description: ["#description", "#description-feedback"],
        duration: ["#duration", "#duration-feedback"],
        rec_cover: ["#rec-cover-input", "#rec-cover-feedback"],
        youtube: ["#youtube-check-box", "#youtube-feedback"],
        video: ["#video-check-box", "#video-feedback"],
        link: ["#link", "#link-feedback"],
        video_file: ["#video-input-field", "#video-feedback"],
    }

    const xhr = new XMLHttpRequest();

    $("#upload-container").removeClass("d-none");

    xhr.upload.addEventListener("progress", function (e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            $("#upload-progress-bar").css("width", percent + "%");
            $("#upload-progress-text").text(percent + "%");
        }
    });

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            toggleBtnLoader(btn, false);

            if (xhr.status === 200) {
                try {
                    console.log(xhr.responseText);
                    const response = JSON.parse(xhr.responseText);
                    if (response.done) {
                        $("#upload-progress-bar").css("width", "100%");
                        $("#upload-progress-text").text("Completed ✓");
                        toggleBtnLoader(btn, "done");
                        showToast("success", response.done);
                        setTimeout(() => window.location.reload(), 3000);
                    } else if (response.error) {
                        showToast("error", response.error);
                    } else {
                        applyValidation(response, validationMap);
                    }
                } catch (err) {
                    console.log(err);
                    showToast("error", "Invalid server response.");
                }
            } else {
                showToast("error", "Upload failed.");
            }
        }
    };

    xhr.open("POST", "api/editPreRecording.php", true);
    xhr.send(form);
};


const searchGuardian = (btn) => {
    toggleBtnLoader(btn, true);
    const validationMap = {
        whatsapp: ["#guardian-whatsapp", "#guardian-whatsapp-feedback"],

    };
    fetch("api/searchGuardian.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ whatsapp: $("#guardian-whatsapp").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#gurdian-details").hasClass("d-none") ? $("#gurdian-details").removeClass("d-none") : "";
                if (response.guardian != null) {
                    $("#gurdian-sub-topic").text('Guardian already added');
                    $("#grelationship-selector").val(response.guardian.guardian_relation_guardian_relation_id).prop("disabled", true);
                    $("#gtitle-selector").val(response.guardian.title_title_id).prop("disabled", true);
                    $("#guardian-fname").val(response.guardian.parent_fname).prop("disabled", true);
                    $("#guardian-lname").val(response.guardian.parent_lname).prop("disabled", true);
                    $("#guardian-email").val(response.guardian.email).prop("disabled", true);
                    $("#guardian-mobile").val(response.guardian.mobile).prop("disabled", true);

                } else {
                    $("#gurdian-sub-topic").text('Please enter guardian details');
                    $("#grelationship-selector").val('').prop("disabled", false);
                    $("#gtitle-selector").val('').prop("disabled", false);
                    $("#guardian-fname").val('').prop("disabled", false);
                    $("#guardian-lname").val('').prop("disabled", false);
                    $("#guardian-email").val('').prop("disabled", false);
                    $("#guardian-mobile").val('').prop("disabled", false);
                }
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

const uploadLectureRecording = (btn, lectureId) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", lectureId);
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("expire_date", $("#expire-date").val());
    form.append("recording_link", $("#recording-link").val());

    const validationMap = {
        cover: ["", "#cover-feedback"],
        expire_date: ["#expire-date", "#expire-date-feedback"],
        lecture: ["#lecture-selector", "#lecture-feedback"],
        recording_link: ["#recording-link", "#link-feedback"]
    };

    fetch('api/uploadLectureRecording.php', {
        method: 'POST',
        body: form
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
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const genarateClassStudentsReport = (btn, classId, className) => {
    toggleBtnLoader(btn, true);

    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        class_id: classId
    };

    fetch('api/genarateClassStudentsReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `${className} - Students Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            showToast("error", error.message);
        });
};

const applyClassStudentsUpdate = (btn, classId) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
            classId: classId
        }
        fetch('api/updateClassStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                console.log(response);
                if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    filterClassStudents(classId);
                    setDefault();
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    showToast("error", response.error);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired student status change option.");
    }
}

const applySelectedClassStudentUpdate = (update, classId) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateClassStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedStudents: selectedUsers, uncheckedStudents: unSelectedUsers, update: update, classId: classId }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterClassStudents(classId);
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addRecording = (btn, classId) => {
    console.log("addRecording");
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("class", classId);
    form.append('time_table_id', $("#class").val());
    form.append("title", $("#title").val());
    form.append("description", $("#description").val());
    form.append("duration", $("#duration").val());
    form.append('expire_date', $("#expire-date").val());
    form.append("rec_cover", $("#rec-cover-input").prop("files")[0]);
    form.append("youtube", $("#youtube-check-box").is(":checked"));
    form.append("video", $("#video-check-box").is(":checked"));
    form.append("link", $("#link").val());
    const videoEl = $("#video-input-field")[0];
    if (videoEl && videoEl.files.length > 0) {
        form.append("video_file", videoEl.files[0]);
    }

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        time_table: ["#class", "#class-feedback"],
        title: ["#title", "#title-feedback"],
        description: ["#description", "#description-feedback"],
        duration: ["#duration", "#duration-feedback"],
        expire_date: ["#expire-date", "#expire-date-feedback"],
        rec_cover: ["#rec-cover-input", "#rec-cover-feedback"],
        youtube: ["#youtube-check-box", "#youtube-feedback"],
        video: ["#video-check-box", "#video-feedback"],
        link: ["#link", "#link-feedback"],
        video_file: ["#video-input-field", "#video-feedback"],
    }


    const xhr = new XMLHttpRequest();

    $("#upload-container").removeClass("d-none");

    xhr.upload.addEventListener("progress", function (e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            $("#upload-progress-bar").css("width", percent + "%");
            $("#upload-progress-text").text(percent + "%");
        }
    });

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            toggleBtnLoader(btn, false);

            if (xhr.status === 200) {
                try {
                    console.log(xhr.responseText);
                    const response = JSON.parse(xhr.responseText);
                    if (response.done) {
                        $("#upload-progress-bar").css("width", "100%");
                        $("#upload-progress-text").text("Completed ✓");
                        toggleBtnLoader(btn, "done");
                        showToast("success", response.done);
                        setTimeout(() => window.location.reload(), 3000);
                    } else if (response.error) {
                        showToast("error", response.error);
                    } else {
                        applyValidation(response, validationMap);
                    }
                } catch (err) {
                    console.log(err);
                    showToast("error", "Invalid server response.");
                }
            } else {
                showToast("error", "Upload failed.");
            }
        }
    };

    xhr.open("POST", "api/addRecording.php", true);
    xhr.send(form);
};

const editRecording = (btn, recordingId) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append('recording_id', recordingId);
    form.append('time_table_id', $("#class").val());
    form.append("title", $("#title").val());
    form.append("description", $("#description").val());
    form.append("duration", $("#duration").val());
    form.append('expire_date', $("#expire-date").val());
    form.append("rec_cover", $("#rec-cover-input").prop("files")[0]);
    form.append("youtube", $("#youtube-check-box").is(":checked"));
    form.append("video", $("#video-check-box").is(":checked"));
    form.append("link", $("#link").val());
    const videoEl = $("#video-input-field")[0];
    if (videoEl && videoEl.files.length > 0) {
        form.append("video_file", videoEl.files[0]);
    }

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        time_table: ["#class", "#class-feedback"],
        title: ["#title", "#title-feedback"],
        description: ["#description", "#description-feedback"],
        duration: ["#duration", "#duration-feedback"],
        expire_date: ["#expire-date", "#expire-date-feedback"],
        rec_cover: ["#rec-cover-input", "#rec-cover-feedback"],
        youtube: ["#youtube-check-box", "#youtube-feedback"],
        video: ["#video-check-box", "#video-feedback"],
        link: ["#link", "#link-feedback"],
        video_file: ["#video-input-field", "#video-feedback"],
    }


    const xhr = new XMLHttpRequest();

    $("#upload-container").removeClass("d-none");

    xhr.upload.addEventListener("progress", function (e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            $("#upload-progress-bar").css("width", percent + "%");
            $("#upload-progress-text").text(percent + "%");
        }
    });

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            toggleBtnLoader(btn, false);

            if (xhr.status === 200) {
                try {
                    console.log(xhr.responseText);
                    const response = JSON.parse(xhr.responseText);
                    if (response.done) {
                        $("#upload-progress-bar").css("width", "100%");
                        $("#upload-progress-text").text("Completed ✓");
                        toggleBtnLoader(btn, "done");
                        showToast("success", response.done);
                        setTimeout(() => window.location.reload(), 3000);
                    } else if (response.error) {
                        showToast("error", response.error);
                    } else {
                        applyValidation(response, validationMap);
                    }
                } catch (err) {
                    console.log(err);
                    showToast("error", "Invalid server response.");
                }
            } else {
                showToast("error", "Upload failed.");
            }
        }
    };

    xhr.open("POST", "api/editRecording.php", true);
    xhr.send(form);
};

const removeRecording = (recordingId) => {
    toggleLoader(true);
    fetch('api/removeRecording.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ recording_id: recordingId }),
    })
        .then(res => res.json())
        .then(response => {
            toggleLoader(false);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
                $("#removeConfirmModal").modal("hide");
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                showToast("warning", response.warning);
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "Something went wrong.Please try again later");
        });
}

const removeRecordingAttachment = (id) => {
    const data = {
        id: id,
    }
    fetch('api/removeRecordingAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                showToast("success", response.done);
                const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                if (attachmentDiv) {
                    attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                    attachmentDiv.style.opacity = 0;
                    attachmentDiv.style.transform = "scale(0.95)";
                    setTimeout(() => attachmentDiv.remove(), 500);
                }
            } else if (response.error) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const removePreRecording = (recordingId) => {
    toggleLoader(true);
    fetch('api/removePreRecording.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ recording_id: recordingId }),
    })
        .then(res => res.json())
        .then(response => {
            toggleLoader(false);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
                $("#removeConfirmModal").modal("hide");
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                showToast("warning", response.warning);
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "Something went wrong.Please try again later");
        });
}

const filterClassAttendanceData = (time_table_id, classId) => {
    const data = {
        time_table_id: time_table_id,
        class_id: classId
    }
    fetch('api/filterClassAttendanceData.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(time_table_id);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#attendance-container").html(data);

            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const downloadClassAttendanceReport = (time_table_id) => {
    const modal = new bootstrap.Modal($('#csvDownload')[0]);
    modal.show();

    const waitingMessage = $('#waitingMessage');
    const successMessage = $('#successMessage');

    waitingMessage.show();
    successMessage.hide();

    const data = {
        time_table_id: time_table_id,
    }

    fetch('api/downloadClassAttendanceReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })

        .then((response) => {
            console.log(response);
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("text/csv")) {
                return response.blob();

            } else {
                return response.json();
            }
        })
        .then((data) => {
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'Lecture Attendance Report.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                waitingMessage.hide();
                successMessage.show();
            } else {

                if (data.error) {
                    showToast("error", data.error);
                } else {
                    showToast("error", "An unknown error occurred.");
                }
                modal.hide();
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again later.");
            modal.hide(); // Hide modal on error
        });
}

const addTute = (btn, classId) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("class", classId);
    form.append("title", $("#title").val());
    form.append("description", $("#description").val());
    form.append("enabled_payment_checked", $("#payment-check").is(":checked") ? true : false);
    form.append("payment_month", $("#payment-month").val());
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("courier", $("#courier-check-box").is(":checked") ? true : false);

    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    const titles = [];

    document.querySelectorAll('input[name="title[]"]').forEach(input => {
        if (input.value.trim() !== "") {
            titles.push(input.value.trim());
        }
    });

    form.append("tute_titles", JSON.stringify(titles));

    const validationMap = {
        class: ["#class-selector", "#class-feedback"],
        date: ["#lecture-date", "#date-feedback"],
        start_time: ["#start-time", "#start-time-feedback"],
        end_time: ["#end-time", "#end-time-feedback"],
        attachment_name: ["#attachment-name", "#attachment-name-feedback"],
        link: ["#link-input", "#link-feedback"],
        title: ["#title", "#title-feedback"],
        tutes: ["#file-selector-input", "#tutes-feedback"],
        start_date: ["#start-date", "#start-date-feedback"],
        end_date: ["#end-date", "#end-date-feedback"],
        days: ["#days", "#days-feedback"],
    };

    if ($("#recurringLecture").is(":checked")) {
        validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
        validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
    }

    fetch("api/addTute.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location = "class-tutes?class-id=" + classId + ""; }, 3000);
            } else {
                applyValidation(response, validationMap);
                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }
        })
        .catch((error) => {
            showToast("error", "Something went wrong.Please try again later");
        });
}

const sendMessages = (btn) => {
    toggleBtnLoader(btn, true);
    console.log($("#class-selector").val());
    const data = {
        classes: $("#class-selector").val(),
        send_students: $("#audienceStudents").is(":checked") ? true : false,
        send_parents: $("#audienceParents").is(":checked") ? true : false,
        message: messageContent.getHTMLCode(),
        send_sourse: $("#message-source").val(),
    }

    const validationMap = {
        classes: ["#class-selector", "#class-feedback"],
        send_students: ["#audienceStudents", "#audienceStudents-feedback"],
        send_parents: ["#audienceParents", "#audienceParents-feedback"],
        message: ["#message-content", "#message-content-feedback"],
        send_sourse: ["#message-source", "#message-source-feedback"],
    };
    fetch('api/sendMessages.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                applyValidation(response, validationMap);

            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeLectureAttachment = (id) => {
    const data = {
        id: id,
    }
    fetch('api/removeLectureAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                showToast("success", response.done);
                const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                if (attachmentDiv) {
                    attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                    attachmentDiv.style.opacity = 0;
                    attachmentDiv.style.transform = "scale(0.95)";
                    setTimeout(() => attachmentDiv.remove(), 500);
                }
            } else if (response.error) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsClassPayments = (classId) => {
    const data = {
        classId: classId,
        text: $("#search-text").val(),
        paymentStatus: $("#status-selector").val(),
        paymentMethod: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        sortByName: sortCondition1,
        sortById: sortCondition2,
        sortByDate: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsClassPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const filterStudentsPayments = () => {
    const data = {
        text: $("#search-text").val(),
        paymentStatus: $("#status-selector").val(),
        paymentMethod: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        sortByName: sortCondition1,
        sortById: sortCondition2,
        sortByDate: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#payments-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const filterAttendance = () => {
    const data = {
        text: $("#search-text").val(),
        sortByName: sortCondition1,
        sortByActive: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    fetch('api/filterAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const filterDownloadableTuteAccessStudents = (id) => {
    const data = {
        tute_id: id,
        text: $("#search-text").val(),
        sortByName: sortCondition1,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterDownloadableTuteAccessStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const markAttendance = (id, lectureId, status) => {
    const data = {
        studentId: id,
        lectureId: lectureId,
        attendStatus: status
    }

    fetch('api/markAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateAttendance = (id, lectureId) => {
    const data = {
        attendanceId: id
    }

    fetch('api/updateAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const confirmSubscriptionRenewal = (btn, id) => {
    toggleBtnLoader(btn, true);

    const data = {
        plan_id: id,
        period: $("#renewPeriod").val()
    }

    fetch('api/confirmSubscriptionRenewal.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#renewSubscriptionModal").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location = "pay-subscription-renewal?renewal-id=" + response.renew_id }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const submitSubscriptionRenewalPayment = (btn, id) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("payment_id", id);
    form.append("method", $("#paymentMethod").val());
    const files = $("#file-selector-input")[0].files;
    for (let i = 0; i < files.length; i++) {
        form.append("slips[]", files[i]);
    }

    fetch('api/submitSubscriptionRenewalPayment.php', {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const signInWithEmail = (btn) => {
    toggleBtnLoader(btn, true);

    const email = $("#email").val().trim();
    const password = $("#password").val().trim();
    const rememberMe = $("#rememberMe").is(":checked");

    fetch("api/signInWithEmail.php", {
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

function disconnectIntegration(btn) {
    toggleBtnLoader(btn, true);
    const integrationId = document.getElementById("disconnectIntegrationId").value;

    if (!integrationId) {
        alert("Invalid integration selected.");
        return;
    }

    fetch("api/disconnectIntegration.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "integration_id=" + encodeURIComponent(integrationId)
    })
        .then(response => response.text())
        .then(response => {
            toggleBtnLoader(btn, false);
            response = response.trim();

            if (response === "success") {
                $("#disconnectIntegrationModal").modal("hide");
                showToast("success", "Integration disconnected successfully.");
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                showToast("error", response);
            }
        })
        .catch(() => {
            alert("Something went wrong. Please try again.");
        });
}