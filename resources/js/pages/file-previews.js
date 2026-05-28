// Generic file drop & preview handlers for avatar/logo/receipts
function bindFileDrop(options) {
    const {dropAreaId, inputId, previewId, dropTextId, multiple = false, previewType = 'single'} = options;
    const dropArea = document.getElementById(dropAreaId);
    if (!dropArea) return;
    const input = document.getElementById(inputId);
    const preview = previewId ? document.getElementById(previewId) : null;
    const dropText = dropTextId ? document.getElementById(dropTextId) : null;

    dropArea.addEventListener('click', () => input.click());
    dropArea.addEventListener('dragover', e => {
        e.preventDefault();
        dropArea.classList.add('border-indigo-400');
    });
    dropArea.addEventListener('dragleave', e => {
        e.preventDefault();
        dropArea.classList.remove('border-indigo-400');
    });
    dropArea.addEventListener('drop', e => {
        e.preventDefault();
        dropArea.classList.remove('border-indigo-400');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showPreview();
        }
    });
    input.addEventListener('change', showPreview);

    function showPreview() {
        if (!input.files || input.files.length === 0) {
            if (dropText) dropText.classList.remove('hidden');
            if (preview && previewType === 'single') preview.classList.add('hidden');
            return;
        }

        if (dropText) dropText.classList.add('hidden');

        if (previewType === 'single' && preview) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
            return;
        }

        // multiple preview container
        if (preview) {
            preview.innerHTML = '';
            Array.from(input.files).forEach(file => {
                const wrapper = document.createElement('div');
                wrapper.className = 'w-20 h-20 overflow-hidden rounded-lg border border-gray-100 dark:border-slate-600 flex items-center justify-center bg-white dark:bg-slate-700';
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.className = 'w-full h-full object-cover';
                    img.alt = file.name;
                    const reader = new FileReader();
                    reader.onload = e => img.src = e.target.result;
                    reader.readAsDataURL(file);
                    wrapper.appendChild(img);
                } else if (file.type === 'application/pdf') {
                    const icon = document.createElement('span');
                    icon.className = 'material-icons-round text-4xl text-gray-400';
                    icon.textContent = 'picture_as_pdf';
                    wrapper.appendChild(icon);
                } else {
                    wrapper.textContent = file.name;
                }
                preview.appendChild(wrapper);
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    bindFileDrop({
        dropAreaId: 'avatar-drop-area',
        inputId: 'avatar-input',
        previewId: 'avatar-preview',
        dropTextId: 'avatar-drop-text',
        multiple: false,
        previewType: 'single'
    });
    bindFileDrop({
        dropAreaId: 'logo-drop-area',
        inputId: 'logo-input',
        previewId: 'logo-preview',
        dropTextId: 'logo-drop-text',
        multiple: false,
        previewType: 'single'
    });
    bindFileDrop({
        dropAreaId: 'receipts-drop-area',
        inputId: 'receipts-input',
        previewId: 'receipts-preview',
        dropTextId: 'receipts-drop-text',
        multiple: true,
        previewType: 'multiple'
    });
});

