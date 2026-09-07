// Camera barcode scanner.
//
// Two engines, picked at runtime. `BarcodeDetector` is built into Chrome and
// Android WebView: no download, hardware-accelerated, and already installed on
// the phones most likely to be scanning a shopping list in a supermarket. Safari
// and Firefox do not implement it, so those fall back to ZXing, imported
// dynamically — a few hundred KB that never reaches the phones that don't need
// it, and never at all until someone actually opens the scanner.
//
// Emits a `barcode-detected` window event carrying the code, so a page wires
// itself up by listening rather than by holding a reference to this component.

const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf'];

window.barcodeScanner = function () {
    return {
        open: false,
        starting: false,
        error: null,
        stream: null,
        detector: null,
        zxingControls: null,
        rafId: null,

        /** Whether a camera can be reached at all — no button without one. */
        get supported() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        async start() {
            this.open = true;
            this.starting = true;
            this.error = null;

            await this.$nextTick();

            try {
                // `environment` asks for the rear camera; on a laptop there is
                // only one and the constraint is ignored rather than failing.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });

                const video = this.$refs.video;
                video.srcObject = this.stream;
                video.setAttribute('playsinline', true); // iOS: don't go fullscreen
                await video.play();

                if ('BarcodeDetector' in window) {
                    await this.runNative(video);
                } else {
                    await this.runZxing(video);
                }
            } catch (e) {
                // A refused permission is the common case and is not a fault:
                // say so plainly rather than dumping a DOMException name.
                this.error = e && e.name === 'NotAllowedError'
                    ? (window.__scannerText?.denied || 'Camera permission denied')
                    : (window.__scannerText?.failed || 'Could not start the camera');
                this.starting = false;
            }
        },

        /** Chrome/Android: poll the native detector once per animation frame. */
        async runNative(video) {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            this.detector = new window.BarcodeDetector({
                formats: FORMATS.filter((f) => supported.includes(f)),
            });
            this.starting = false;

            const tick = async () => {
                if (!this.open) return;
                try {
                    const codes = await this.detector.detect(video);
                    if (codes.length && codes[0].rawValue) {
                        this.found(codes[0].rawValue);
                        return;
                    }
                } catch { /* a dropped frame is not worth aborting the scan */ }
                this.rafId = requestAnimationFrame(tick);
            };
            this.rafId = requestAnimationFrame(tick);
        },

        /** Safari/Firefox: ZXing drives its own decode loop off the stream. */
        async runZxing(video) {
            const { BrowserMultiFormatReader } = await import('@zxing/browser');
            const reader = new BrowserMultiFormatReader();
            this.starting = false;

            this.zxingControls = await reader.decodeFromVideoElement(video, (result) => {
                if (result) this.found(result.getText());
            });
        },

        found(code) {
            // A scan is a physical act with feedback in the real world; without
            // a buzz it is unclear whether the camera saw anything.
            if (navigator.vibrate) navigator.vibrate(60);
            window.dispatchEvent(new CustomEvent('barcode-detected', { detail: { code } }));
            this.stop();
        },

        stop() {
            this.open = false;
            this.starting = false;

            if (this.rafId) { cancelAnimationFrame(this.rafId); this.rafId = null; }
            if (this.zxingControls) { this.zxingControls.stop(); this.zxingControls = null; }

            // Releasing every track is what turns the camera light off. Leaving
            // it on reads as the app still watching.
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            this.detector = null;
        },

        // Leaving the page mid-scan must not strand the camera.
        destroy() { this.stop(); },
    };
};
