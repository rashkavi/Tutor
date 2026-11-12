const downloadTute = (attachment, name) => {
    if (!attachment) return;

    const basePath = '../assets/uploads/lectures-attachments/';
    const fileUrl = basePath + encodeURIComponent(attachment);

    const link = document.createElement('a');
    link.href = fileUrl;

    link.download = name ? name : attachment;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};


const previewTute = (attachment, name) => {
    if (!attachment) return;

    const basePath = '../assets/uploads/lectures-attachments/';
    const fileUrl = basePath + encodeURIComponent(attachment);

    const win = window.open(fileUrl, '_blank', 'noopener,noreferrer');
    if (win && name) {
        win.document.title = name;
    }
};

const viewPaymentSlip = (slipName) => {
    const url = "../assets/uploads/payment-slips/" + slipName;
    window.open(url, '_blank');
}


