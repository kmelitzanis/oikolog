{{--
    Camera barcode scanner: a button plus the full-screen viewfinder it opens.

    Drop it anywhere a barcode is wanted and listen for the `barcode-detected`
    window event; the component knows nothing about what the code is then used
    for. It renders nothing at all when the browser has no camera API, so no
    button is offered that could not work.

        <x-barcode-scanner />
        window.addEventListener('barcode-detected', e => e.detail.code)
--}}

{{-- The two failure messages live in JS, so hand them over once per page
     however many scanners the page renders. --}}
@once
    <script>
        window.__scannerText = {
            denied: @json(__('messages.barcode_camera_denied')),
            failed: @json(__('messages.barcode_camera_failed')),
        };
    </script>
@endonce

<div x-data="barcodeScanner()" x-cloak @keydown.escape.window="open && stop()">

    {{-- Trigger. `x-show` on `supported` rather than a server-side check: only
         the browser knows whether it has a camera. --}}
    <button type="button" x-show="supported" @click="start()"
            title="{{ __('messages.barcode_scan_camera') }}"
            class="w-10 h-10 shrink-0 flex items-center justify-center rounded-xl
                   bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400
                   hover:bg-amber-100 dark:hover:bg-amber-900/40 transition">
        <span class="material-icons-round">photo_camera</span>
    </button>

    {{-- Viewfinder. Full-screen because framing a barcode one-handed in a shop
         needs the whole display, not a dialog in the middle of it. --}}
    <template x-if="open">
        <div class="fixed inset-0 z-[60] bg-black flex flex-col">

            <div class="flex items-center justify-between p-4 text-white shrink-0">
                <span class="font-semibold">{{ __('messages.barcode_scan_camera') }}</span>
                <button type="button" @click="stop()" class="p-2 -mr-2" aria-label="{{ __('messages.close') }}">
                    <span class="material-icons-round">close</span>
                </button>
            </div>

            <div class="relative flex-1 min-h-0">
                <video x-ref="video" class="absolute inset-0 w-full h-full object-cover"
                       muted playsinline autoplay></video>

                {{-- Aiming frame. Purely a hint: the detector reads the whole
                     frame, so a code outside the box still scans. --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-4/5 max-w-sm aspect-[5/3] rounded-2xl border-2 border-white/80 shadow-[0_0_0_100vmax_rgba(0,0,0,0.45)]"></div>
                </div>

                <div x-show="starting" class="absolute inset-0 flex items-center justify-center text-white/80 text-sm">
                    {{ __('messages.barcode_starting_camera') }}
                </div>
            </div>

            <div class="p-5 text-center text-sm shrink-0">
                <p x-show="!error" class="text-white/70">{{ __('messages.barcode_aim_hint') }}</p>
                <p x-show="error" x-text="error" class="text-red-300"></p>
            </div>
        </div>
    </template>
</div>
