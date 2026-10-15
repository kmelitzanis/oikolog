/**
 * Every <input type="date"> shows DD/MM/YYYY.
 *
 * A native date input is drawn by the browser in the operating system's
 * locale, so on a Mac set to English (US) it reads MM/DD/YYYY whatever the app
 * does. flatpickr replaces what the user sees with a DD/MM/YYYY field while the
 * original input — now hidden — keeps submitting ISO YYYY-MM-DD, so no server
 * code changes. Inputs added later (modals, inline forms) are picked up by a
 * MutationObserver.
 */
import flatpickr from 'flatpickr';
import { Greek } from 'flatpickr/dist/l10n/gr.js';
import 'flatpickr/dist/flatpickr.min.css';

const isGreek = () => (document.documentElement.lang || '').toLowerCase().startsWith('el');

function enhance(input) {
    if (input._flatpickr || input.dataset.nativeDate !== undefined) return;

    flatpickr(input, {
        dateFormat: 'Y-m-d',   // what the form submits
        altInput: true,
        altFormat: 'd/m/Y',    // what the user sees
        allowInput: false,
        // The native picker on phones would bring the OS format straight back.
        disableMobile: true,
        locale: isGreek() ? Greek : 'default',
        minDate: input.min || null,
        maxDate: input.max || null,
        onReady(_, __, fp) {
            fp.altInput.required = input.required;
            if (input.id) {
                // Labels point at the id; give it to the field the user clicks.
                fp.altInput.id = input.id;
                input.removeAttribute('id');
            }
            fp.altInput.setAttribute('placeholder', isGreek() ? 'ΗΗ/ΜΜ/ΕΕΕΕ' : 'DD/MM/YYYY');
        },
        onChange(_, __, fp) {
            // Alpine's x-model listens for "input"; flatpickr only fires "change".
            fp.input.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });
}

function enhanceAll(root = document) {
    root.querySelectorAll?.('input[type="date"]').forEach(enhance);
}

enhanceAll();

new MutationObserver((mutations) => {
    for (const m of mutations) {
        m.addedNodes.forEach((node) => {
            if (node.nodeType !== 1) return;
            if (node.matches?.('input[type="date"]')) enhance(node);
            else enhanceAll(node);
        });
    }
}).observe(document.body, { childList: true, subtree: true });
