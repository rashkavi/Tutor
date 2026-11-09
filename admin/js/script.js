const initializeUsers = () => {
    document.getElementById("copy-button").addEventListener("click", function () {
        const linkText = document.getElementById("generated-link").innerText;

        navigator.clipboard.writeText(linkText).then(() => {
            showToast('success', 'Link copied to clipboard.');
            $("#shareLink").modal("hide");
        }).catch(err => {
            showToast('error', 'Failed to copy link.');
        });
    });
}

const openStatusUpdateModal = (userId, statusId) => {

    $("#update-reg-request-status").off("click").on("click", function () {
        updateUserRegistrationRequestStatus(this, userId);
    });

    if (statusId != 1) {
        $("#status-selector").val(statusId);
    }

    $("#updateRegRequestStatus").modal("show");
}

const handleUsersPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterUsers();
}


const handleRequestedUsersPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterRequestedUsers();
}

const changePaymentPlan = (value) => {
    if (value == 2) {
        $("#payment-plan-area").hasClass('d-none') ? $("#payment-plan-area").removeClass('d-none') : '';
    } else {
        $("#payment-plan-area").hasClass('d-none') ? '' : $("#payment-plan-area").addClass('d-none');
    }
}