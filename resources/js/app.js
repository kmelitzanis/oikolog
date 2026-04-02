import '../css/app.css';
import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// ── FilePond ──────────────────────────────────────────────────────────────
import * as FilePond from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';

FilePond.registerPlugin(FilePondPluginImagePreview);
window.FilePond = FilePond;

document.addEventListener('DOMContentLoaded', () => {
    // Avatar upload (settings page)
    const avatarInput = document.querySelector('input[data-filepond="avatar"]');
    if (avatarInput) {
        FilePond.create(avatarInput, {
            allowMultiple: false,
            acceptedFileTypes: ['image/*'],
            labelIdle: '<span class="material-icons-round text-2xl text-gray-400 block mb-1">photo_camera</span><span class="text-sm text-gray-500">Drag & drop or <span class="text-indigo-600 font-semibold">browse</span></span>',
            stylePanelLayout: 'compact',
            styleButtonRemoveItemPosition: 'left',
            styleLoadIndicatorPosition: 'right',
        });
    }

    // Receipt uploads (bill form)
    const receiptInput = document.querySelector('input[data-filepond="receipts"]');
    if (receiptInput) {
        FilePond.create(receiptInput, {
            allowMultiple: true,
            acceptedFileTypes: ['image/*', 'application/pdf'],
            labelIdle: '<span class="material-icons-round text-2xl text-gray-400 block mb-1">upload_file</span><span class="text-sm text-gray-500">Drag & drop receipts or <span class="text-indigo-600 font-semibold">browse</span></span>',
            allowReorder: true,
        });
    }
});
