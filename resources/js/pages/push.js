// Web Push opt-in. Exposed as an Alpine component for the settings page toggle.
//
// Browsers only grant Notification permission from a user gesture, so there is
// deliberately no auto-prompt on page load: the user flips the switch.

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

/** VAPID keys travel as base64url; PushManager wants raw bytes. */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

window.pushToggle = function () {
    return {
        supported: 'serviceWorker' in navigator && 'PushManager' in window,
        // 'unknown' until we've looked; then 'on' | 'off' | 'blocked' | 'unavailable'
        state: 'unknown',
        busy: false,
        publicKey: null,

        get blocked() {
            return this.state === 'blocked';
        },
        get enabled() {
            return this.state === 'on';
        },

        async init() {
            if (!this.supported) {
                this.state = 'unavailable';
                return;
            }

            const cfg = await fetch('/push/config', {headers: {Accept: 'application/json'}})
                .then((r) => r.json())
                .catch(() => null);

            if (!cfg?.enabled || !cfg.public_key) {
                // Server has no VAPID keys configured — nothing to subscribe to.
                this.state = 'unavailable';
                return;
            }
            this.publicKey = cfg.public_key;

            if (Notification.permission === 'denied') {
                this.state = 'blocked';
                return;
            }

            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            this.state = sub ? 'on' : 'off';
        },

        async toggle() {
            if (this.busy || this.blocked || this.state === 'unavailable') return;
            this.busy = true;
            try {
                await (this.enabled ? this.disable() : this.enable());
            } finally {
                this.busy = false;
            }
        },

        async enable() {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                this.state = permission === 'denied' ? 'blocked' : 'off';
                return;
            }

            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(this.publicKey),
            });

            const json = sub.toJSON();
            const res = await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    keys: json.keys,
                    content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                }),
            });

            if (!res.ok) {
                // Don't leave a browser subscribed to a server that lost the row.
                await sub.unsubscribe();
                this.state = 'off';
                return;
            }

            this.state = 'on';
        },

        async disable() {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();

            if (sub) {
                await fetch('/push/subscribe', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({endpoint: sub.endpoint}),
                }).catch(() => {});
                await sub.unsubscribe();
            }

            this.state = 'off';
        },
    };
};
