const videoDropZone = document.querySelector('#video-drop-zone');
const videoPreviewList = document.querySelector('#video-preview-list');
const videoBrowseBtn = document.querySelector('#video-browse-btn');
const videoInputField = document.querySelector('#video-input-field');

// Detect multiple mode
let allowMultipleVideos = videoInputField.hasAttribute('multiple');

let selectedVideos = [];

// Open file dialog
videoBrowseBtn.onclick = () => videoInputField.click();

// File select via browse
videoInputField.onchange = () => {
    videoPreviewList.innerHTML = '';

    if (allowMultipleVideos) {
        [...videoInputField.files].forEach(processVideoFile);
    } else {
        const file = videoInputField.files[0];
        if (file) processSingleVideo(file);
    }
};

// Drag over
videoDropZone.ondragover = (e) => {
    e.preventDefault();
    videoDropZone.classList.add('drag-active');
};

// Drag leave
videoDropZone.ondragleave = () => {
    videoDropZone.classList.remove('drag-active');
};

// Drop videos
videoDropZone.ondrop = (e) => {
    e.preventDefault();
    videoDropZone.classList.remove('drag-active');
    videoPreviewList.innerHTML = '';

    if (allowMultipleVideos) {
        [...e.dataTransfer.files].forEach(processVideoFile);
    } else {
        const file = e.dataTransfer.files[0];
        if (file) processSingleVideo(file);
    }
};

// Validate video type
function isValidVideo(type) {
    return type.startsWith('video/');
}

// Handle multiple videos
function processVideoFile(file) {
    if (!isValidVideo(file.type)) return;

    selectedVideos.push(file);
    renderVideoItem(file);
}

// Handle single video
function processSingleVideo(file) {
    if (!isValidVideo(file.type)) return;

    selectedVideos = [file];
    renderVideoItem(file);
}

// Render video preview item
function renderVideoItem(file) {
    const template = `
    <div class="col-12 col-md-4 my-3 video-item">
        <div class="p-3 bl-solid">
            <div class="row align-items-center">
                <div class="col-3">
                    <video src="${URL.createObjectURL(file)}" width="100%" height="70" muted></video>
                </div>
                <div class="col-7">
                    <div class="fw-medium">${file.name}</div>
                    <small>${(file.size / (1024 * 1024)).toFixed(2)} MB</small>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-video" width="20" height="20" data-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendVideoTemplate(template, file.name);
}

// Append template
function appendVideoTemplate(template, name) {
    const temp = document.createElement('div');
    temp.innerHTML = template.trim();

    temp.querySelector('.remove-video')
        .addEventListener('click', () => deleteVideo(name));

    videoPreviewList.appendChild(temp.firstElementChild);
}

// Remove video
function deleteVideo(name) {
    selectedVideos = selectedVideos.filter(v => v.name !== name);

    const dt = new DataTransfer();
    selectedVideos.forEach(v => dt.items.add(v));
    videoInputField.files = dt.files;

    const item = videoPreviewList.querySelector(`.remove-video[data-name="${name}"]`)
        ?.closest('.video-item');

    if (item) item.remove();
}
