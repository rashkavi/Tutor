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
                <p class="text-24 sub-topic-color fw-medium lh-lg">Parent Login</p>

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

const savePayment = (id) => {
    const form = new FormData();
    form.append("payment_id", id);
    form.append("method", $("#payment-method").val());

    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("slips[]", files[i]);
    }

    fetch("api/savePayment.php", {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                if (response.hasOwnProperty('method')) {
                    $("#payment-method").addClass("is-invalid");
                    $("#payment-method-feedback").text(response.method);
                } else {
                    $("#payment-method").removeClass("is-invalid");
                    $("#payment-method-feedback").text("");
                }
                if (response.hasOwnProperty('ref-id')) {
                    $("#ref-id").addClass("is-invalid");
                    $("#ref-id-feedback").text(response.ref - id);
                } else {
                    $("#ref-id").removeClass("is-invalid");
                    $("#ref-id-feedback").text("");
                }
                if (response.hasOwnProperty('slip')) {
                    const slipErrorContainer = $("#file-feedback");
                    if (Array.isArray(response.slip)) {
                        const slipArray = response.slip; // Get the actual array
                        $("#file-feedback").html("");
                        slipErrorContainer.append('<p class="text-14 text-danger fw-bold">Upload Payment Slip Error Messages : </p>');
                        slipArray.forEach(function (item) {
                            slipErrorContainer.append('<p class="text-12 text-danger">*' + item + '</p>');
                        });
                    } else {
                        slipErrorContainer.append('<p class="text-12 text-danger">*' + response.slip + '</p>');
                    }
                } else {
                    $("#file-feedback").html("");
                }
            }
        })
        .catch((error) => {
            //console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
