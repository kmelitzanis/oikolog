/**
 * OBD-II scan over Web Bluetooth.
 *
 * Talks to an ELM327-compatible Bluetooth Low Energy adapter: the browser
 * opens a GATT connection, finds a characteristic it can write commands to
 * and one that notifies replies, and speaks the ELM327 text protocol over
 * them ("010C\r" → "410C1AF8\r>"). Nothing is stored until the user reviews
 * the result and presses save.
 *
 * Works in Chrome/Edge on Android and desktop. Safari and iOS have no Web
 * Bluetooth, and classic-Bluetooth (SPP) adapters are invisible to it.
 */

// GATT services used by common BLE OBD adapters. Web Bluetooth only exposes
// services listed up front, so every candidate has to be named here.
const SERVICES = [
    0xfff0,                                     // Vgate iCar, many ELM327 BLE clones
    0xffe0,                                     // HM-10 style modules
    0x18f0,                                     // Some iOS-oriented adapters
    0xae30,                                     // Generic BLE UART chips
    0xfee7,
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e',     // Nordic UART
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',     // Vlinker / Veepeak / OBDLink-style
    '0000ffe0-0000-1000-8000-00805f9b34fb',
];

const NO_REPLY = /NO DATA|UNABLE TO CONNECT|CAN ERROR|BUS (INIT|ERROR)|STOPPED|^\?$|ERROR/;

class Elm327 {
    constructor(writeChar, notifyChar) {
        this.writeChar = writeChar;
        this.notifyChar = notifyChar;
        this.buffer = '';
        this.pending = null;
        this.decoder = new TextDecoder();
        this.onData = (e) => {
            this.buffer += this.decoder.decode(e.target.value);
            // ">" is the adapter's prompt: the reply is complete.
            const end = this.buffer.indexOf('>');
            if (end !== -1 && this.pending) {
                const reply = this.buffer.slice(0, end);
                this.buffer = this.buffer.slice(end + 1);
                const { resolve, timer } = this.pending;
                this.pending = null;
                clearTimeout(timer);
                resolve(reply);
            }
        };
    }

    async start() {
        await this.notifyChar.startNotifications();
        this.notifyChar.addEventListener('characteristicvaluechanged', this.onData);
    }

    async stop() {
        try { await this.notifyChar.stopNotifications(); } catch (e) {}
        this.notifyChar.removeEventListener('characteristicvaluechanged', this.onData);
    }

    /** Send one command; resolve with its reply as clean, non-empty lines. */
    async send(cmd, timeout = 5000) {
        this.buffer = '';
        const reply = new Promise((resolve, reject) => {
            const timer = setTimeout(() => { this.pending = null; reject(new Error('timeout:' + cmd)); }, timeout);
            this.pending = { resolve, timer };
        });

        // BLE packets are small; long commands go in 20-byte pieces.
        const bytes = new TextEncoder().encode(cmd + '\r');
        const canQuick = this.writeChar.properties.writeWithoutResponse;
        for (let i = 0; i < bytes.length; i += 20) {
            const chunk = bytes.slice(i, i + 20);
            if (canQuick) await this.writeChar.writeValueWithoutResponse(chunk);
            else await this.writeChar.writeValue(chunk);
        }

        const text = await reply;
        return text.split(/[\r\n]+/)
            .map((l) => l.trim())
            .filter((l) => l && l !== cmd && !/^SEARCHING/i.test(l) && !/^BUS INIT/i.test(l));
    }
}

// ── Decoding ──────────────────────────────────────────────────────────────

/** Bytes of the first reply line answering a mode-01 PID, or null. */
function pidBytes(lines, pid) {
    const head = ('41' + pid).toUpperCase();
    for (const raw of lines) {
        const line = raw.replace(/\s/g, '').toUpperCase();
        const at = line.indexOf(head);
        if (at === -1) continue;
        const data = line.slice(at + head.length);
        const out = [];
        for (let i = 0; i + 1 < data.length; i += 2) out.push(parseInt(data.slice(i, i + 2), 16));
        return out.every((b) => !Number.isNaN(b)) ? out : null;
    }
    return null;
}

/** Group multi-frame CAN replies ("0: …", "1: …") into whole messages. */
function messages(lines) {
    const out = [];
    for (const raw of lines) {
        const line = raw.replace(/\s/g, '').toUpperCase();
        if (/^[0-9A-F]{3}$/.test(line)) continue;          // byte-count header
        const frame = line.match(/^([0-9A-F]):(.*)$/);
        if (frame) {
            if (frame[1] === '0' || !out.length) out.push(frame[2]);
            else out[out.length - 1] += frame[2];
        } else {
            out.push(line);
        }
    }
    return out;
}

/** "0133" → "P0133": the first nibble carries the letter and first digit. */
function decodeDtc(hex) {
    const n = parseInt(hex[0], 16);
    return 'PCBU'[n >> 2] + (n & 3) + hex.slice(1).toUpperCase();
}

/**
 * Trouble codes from a mode 03/07/0A reply (prefix 43/47/4A).
 *
 * On CAN cars (protocols 6–9, A) the reply leads with a count byte and is
 * padded; older protocols send fixed groups of three codes padded with 0000.
 * When the protocol is unknown, an odd byte count is taken to mean "CAN".
 */
export function parseDtcs(lines, prefix, isCan = null) {
    const codes = [];
    for (const msg of messages(lines)) {
        if (!msg.startsWith(prefix) || NO_REPLY.test(msg)) continue;
        let data = msg.slice(2);
        const can = isCan ?? ((data.length / 2) % 2 === 1);
        if (can) {
            const count = parseInt(data.slice(0, 2), 16) || 0;
            data = data.slice(2, 2 + count * 4);
        }
        for (let i = 0; i + 3 < data.length; i += 4) {
            const chunk = data.slice(i, i + 4);
            if (chunk !== '0000') codes.push(decodeDtc(chunk));
        }
    }
    return [...new Set(codes)];
}

function parseVin(lines) {
    let hex = messages(lines).join('').replace(/^4902(01)?/, '');
    hex = hex.replace(/4902\d{2}/g, '');
    let text = '';
    for (let i = 0; i + 1 < hex.length; i += 2) {
        const c = parseInt(hex.slice(i, i + 2), 16);
        if (c >= 48 && c <= 90) text += String.fromCharCode(c);
    }
    const vin = text.slice(-17);
    return /^[A-HJ-NPR-Z0-9]{17}$/.test(vin) ? vin : null;
}

// Mode-01 PIDs read in a scan, and how to turn their bytes into a reading.
const PIDS = [
    ['0105', 'coolant_c',      (b) => b[0] - 40],
    ['010F', 'intake_c',       (b) => b[0] - 40],
    ['010C', 'rpm',            (b) => Math.round((256 * b[0] + b[1]) / 4)],
    ['010D', 'speed_kmh',      (b) => b[0]],
    ['0104', 'load_pct',       (b) => Math.round(b[0] * 100 / 255)],
    ['012F', 'fuel_level_pct', (b) => Math.round(b[0] * 100 / 255)],
    ['0106', 'stft_pct',       (b) => +((b[0] - 128) * 100 / 128).toFixed(1)],
    ['0107', 'ltft_pct',       (b) => +((b[0] - 128) * 100 / 128).toFixed(1)],
    ['0142', 'module_v',       (b) => +((256 * b[0] + b[1]) / 1000).toFixed(2)],
    ['0131', 'km_since_clear', (b) => 256 * b[0] + b[1]],
    ['0121', 'km_with_mil',    (b) => 256 * b[0] + b[1]],
];

// ── Alpine component ─────────────────────────────────────────────────────

window.obdScanner = function ({ url, csrf, labels }) {
    return {
        supported: typeof navigator !== 'undefined' && !!navigator.bluetooth,
        phase: 'idle',        // idle | connecting | scanning | review | saving | error
        step: '',
        error: '',
        result: null,
        device: null,

        async scan() {
            this.error = '';
            this.result = null;
            this.phase = 'connecting';
            this.step = labels.choose;
            let elm = null;

            try {
                this.device = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: SERVICES,
                });
                this.step = labels.connecting;
                const server = await this.device.gatt.connect();

                // Find a write and a notify characteristic in whichever known
                // service the adapter exposes.
                let writeChar = null, notifyChar = null;
                for (const service of await server.getPrimaryServices()) {
                    for (const ch of await service.getCharacteristics()) {
                        if (!notifyChar && (ch.properties.notify || ch.properties.indicate)) notifyChar = ch;
                        if (!writeChar && (ch.properties.write || ch.properties.writeWithoutResponse)) writeChar = ch;
                    }
                    if (writeChar && notifyChar) break;
                }
                if (!writeChar || !notifyChar) throw new Error('unsupported');

                elm = new Elm327(writeChar, notifyChar);
                await elm.start();

                this.phase = 'scanning';
                this.step = labels.init;
                await elm.send('ATZ', 4000).catch(() => {});
                for (const cmd of ['ATE0', 'ATL0', 'ATS0', 'ATH0', 'ATSP0']) await elm.send(cmd, 3000).catch(() => {});

                // The first request triggers the protocol search, which is slow.
                this.step = labels.protocol;
                const status = await elm.send('0101', 15000);
                if (status.some((l) => /UNABLE TO CONNECT|NO DATA|CAN ERROR/.test(l))) throw new Error('ignition');

                const result = { codes: [], readings: {}, mil_on: null, vin: null, odometer_km: null, adapter: this.device.name || null };
                // Which protocol the car answered on decides how codes are packed.
                const proto = (await elm.send('ATDPN', 3000).catch(() => [])).join('').replace(/^A(?=[0-9A-C]$)/, '');
                const isCan = /^[6-9A]$/i.test(proto) ? true : (/^[1-5]$/.test(proto) ? false : null);
                const s = pidBytes(status, '01');
                if (s) result.mil_on = (s[0] & 0x80) !== 0;

                this.step = labels.codes;
                const add = (list, st) => list.forEach((code) => result.codes.push({ code, status: st }));
                add(parseDtcs(await elm.send('03', 8000).catch(() => []), '43', isCan), 'stored');
                add(parseDtcs(await elm.send('07', 8000).catch(() => []), '47', isCan), 'pending');
                add(parseDtcs(await elm.send('0A', 8000).catch(() => []), '4A', isCan), 'permanent');

                this.step = labels.readings;
                const volt = (await elm.send('ATRV', 3000).catch(() => [])).join('').match(/(\d+(\.\d+)?)\s*V/i);
                if (volt) result.readings.battery_v = +volt[1];
                for (const [pid, key, fn] of PIDS) {
                    const lines = await elm.send(pid, 4000).catch(() => []);
                    const b = pidBytes(lines, pid.slice(2));
                    if (b && b.length) {
                        const v = fn(b);
                        if (Number.isFinite(v)) result.readings[key] = v;
                    }
                }
                const odo = pidBytes(await elm.send('01A6', 4000).catch(() => []), 'A6');
                if (odo && odo.length >= 4) result.odometer_km = Math.round(((odo[0] * 2 ** 24) + (odo[1] << 16) + (odo[2] << 8) + odo[3]) / 10);
                result.vin = parseVin(await elm.send('0902', 8000).catch(() => []));

                this.result = result;
                this.phase = 'review';
            } catch (e) {
                this.phase = 'error';
                const msg = String(e && (e.message || e.name) || e);
                this.error = e && e.name === 'NotFoundError' ? labels.cancelled
                    : msg === 'unsupported' ? labels.unsupported
                    : msg === 'ignition' ? labels.ignition
                    : msg.startsWith('timeout') ? labels.timeout
                    : labels.failed + ' (' + msg + ')';
            } finally {
                if (elm) await elm.stop();
                try { this.device && this.device.gatt.connected && this.device.gatt.disconnect(); } catch (e) {}
            }
        },

        async save() {
            this.phase = 'saving';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ ...this.result, source: 'bluetooth' }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                window.location.reload();
            } catch (e) {
                this.phase = 'review';
                this.error = labels.saveFailed;
            }
        },

        reset() {
            this.phase = 'idle';
            this.result = null;
            this.error = '';
        },
    };
};
