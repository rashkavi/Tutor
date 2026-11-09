const dropArea = document.querySelector('#drop-section');
const listSection = document.querySelector('#list-section');
const fileSelector = document.querySelector('#file-selector');
const fileSelectorInput = document.querySelector('#file-selector-input');



// Flag to toggle between single and multiple file modes
var allowMultipleFiles = false;

if ($('#file-selector-input').attr('multiple')) {
    allowMultipleFiles = true;
}

// Upload files with browse button
fileSelector.onclick = () => fileSelectorInput.click();

fileSelectorInput.onchange = () => {
    listSection.innerHTML = '';
    if (allowMultipleFiles) {
        [...fileSelectorInput.files].forEach((file) => {
            if (typeValidation(file.type)) {
                uploadMultipleFile(file);
            }
        });
    } else {
        const file = fileSelectorInput.files[0];
        if (file && typeValidation(file.type)) {
            uploadSingleFile(file);
        }
    }
};

// Handle drag-over effect
dropArea.ondragover = (e) => {
    e.preventDefault();
    dropArea.classList.add('drag-over-effect');
};

// Remove drag-over effect when leaving the drop area
dropArea.ondragleave = () => {
    dropArea.classList.remove('drag-over-effect');
};

// Handle file drop
dropArea.ondrop = (e) => {
    listSection.innerHTML = '';
    e.preventDefault();
    dropArea.classList.remove('drag-over-effect');

    if (allowMultipleFiles) {
        [...e.dataTransfer.files].forEach((file) => {
            if (typeValidation(file.type)) {
                uploadMultipleFile(file);
            }
        });
    } else {
        const file = e.dataTransfer.files[0];
        if (file && typeValidation(file.type)) {
            uploadSingleFile(file);
        }
    }
};

// Validate file type
function typeValidation(type, name) {
    const validImageTypes = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'image/x-png', // Some browsers use this for PNG
        'image/pjpeg' // Some browsers use this for JPEG
    ];
    const validExtensions = ['.pdf', '.png', '.jpg', '.jpeg'];
    
    // Check MIME type first
    if (validImageTypes.includes(type) || type === 'application/pdf') {
        return true;
    }
    
    // Fallback to file extension check
    const extension = name.substring(name.lastIndexOf('.')).toLowerCase();
    return validExtensions.includes(extension);
}


let uploadedFiles = [];

// Upload and display multiple files
function uploadMultipleFile(file) {
    uploadedFiles.push(file);

   
    const template = `
    <div class="col-12 col-md-4 my-3 file-item">
        <div class="p-3 bl-solid">
            <div class="row">
                <div class="col-2">
                    <img src="<?= Project::getAssetsPath() ?>/imgs/icons/${iconSelector(file.type)}" alt="">
                </div>
                <div class="col-8">
                    <div class="file-name">
                        <div class="name">${file.name}</div>
                    </div>
                    <div class="file-size">${(file.size / (1024 * 1024)).toFixed(2)} MB</div>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-file" width="20" height="20" style="color: #49739D" data-file-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendFileTemplate(template, file.name);
}

// Upload and display a single file
function uploadSingleFile(file) {
    // Clear the previously uploaded file
    uploadedFiles = [file];
    listSection.innerHTML = '';

    const template = `
    <div class="col-12 my-3 file-item">
        <div class="p-3 bl-solid">
            <div class="row">
                <div class="col-2">
                    <img src="<?= Project::getAssetsPath() ?>/imgs/icons/${iconSelector(file.type)}" alt="">
                </div>
                <div class="col-8">
                    <div class="file-name">
                        <div class="name">${file.name}</div>
                    </div>
                    <div class="file-size">${(file.size / (1024 * 1024)).toFixed(2)} MB</div>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-file" width="20" height="20" style="color: #49739D" data-file-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendFileTemplate(template, file.name);
}

// Append the file template to the list section
function appendFileTemplate(template, fileName) {
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = template.trim();

    const removeIcon = tempContainer.querySelector('.remove-file');
    if (removeIcon) {
        removeIcon.addEventListener('click', () => removeFile(fileName));
    }

    listSection.appendChild(tempContainer.firstElementChild);
}

// Remove a file
function removeFile(fileName) {
    uploadedFiles = uploadedFiles.filter((file) => file.name !== fileName);

    const fileInput = document.getElementById('file-selector-input');
    const dataTransfer = new DataTransfer();
    uploadedFiles.forEach((file) => dataTransfer.items.add(file));
    fileInput.files = dataTransfer.files;

    const fileItem = listSection.querySelector(`.file-item .remove-file[data-file-name="${fileName}"]`).closest('.file-item');
    if (fileItem) fileItem.remove();
}

// Get the icon for a file
function iconSelector(type) {
    if (type === 'application/pdf') {
        return 'pdf.png';
    }

    if (type.startsWith('image/')) {
        return 'image.png';
    }

    if (type === 'text/csv') {
        return 'csv.png';
    }

    return 'image.png'; // fallback
}
