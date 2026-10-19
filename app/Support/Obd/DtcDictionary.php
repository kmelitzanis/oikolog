<?php

namespace App\Support\Obd;

/**
 * Plain-language meaning of OBD-II diagnostic trouble codes.
 *
 * Every code decodes to its system from the code itself (letter + digits are
 * standardised by SAE J2012), so even an unknown code says *where* the fault
 * is. The most common generic codes also carry a specific description. Codes
 * whose second character is 1 or 3 (P1xxx, P3xxx…) are manufacturer-specific:
 * their meaning depends on the make, and the app says so rather than guessing.
 */
class DtcDictionary
{
    /** code => [severity, el, en] — severity: high | medium | low */
    private const KNOWN = [
        'P0010' => ['medium', 'Ενεργοποιητής εκκεντροφόρου «A» (τράπεζα 1) — κύκλωμα', 'Camshaft position actuator "A" circuit (bank 1)'],
        'P0011' => ['medium', 'Χρονισμός εκκεντροφόρου «A» υπερβολικά προπορευόμενος (τράπεζα 1)', 'Camshaft position "A" timing over-advanced (bank 1)'],
        'P0016' => ['high', 'Ασυμφωνία θέσης στροφαλοφόρου – εκκεντροφόρου (τράπεζα 1)', 'Crankshaft/camshaft position correlation (bank 1)'],
        'P0087' => ['high', 'Πολύ χαμηλή πίεση ράμπας καυσίμου', 'Fuel rail/system pressure too low'],
        'P0088' => ['high', 'Πολύ υψηλή πίεση ράμπας καυσίμου', 'Fuel rail/system pressure too high'],
        'P0101' => ['medium', 'Αισθητήρας μάζας αέρα (MAF) εκτός εύρους', 'Mass air flow (MAF) sensor range/performance'],
        'P0102' => ['medium', 'Αισθητήρας μάζας αέρα (MAF) — χαμηλό σήμα', 'Mass air flow (MAF) circuit low'],
        'P0113' => ['low', 'Αισθητήρας θερμοκρασίας αέρα εισαγωγής — υψηλό σήμα', 'Intake air temperature sensor circuit high'],
        'P0115' => ['medium', 'Αισθητήρας θερμοκρασίας ψυκτικού — κύκλωμα', 'Engine coolant temperature circuit'],
        'P0118' => ['medium', 'Αισθητήρας θερμοκρασίας ψυκτικού — υψηλό σήμα', 'Engine coolant temperature circuit high'],
        'P0128' => ['low', 'Θερμοστάτης: ο κινητήρας δεν ζεσταίνεται αρκετά', 'Coolant thermostat below regulating temperature'],
        'P0130' => ['low', 'Αισθητήρας οξυγόνου (λ) τράπεζα 1, αισθητήρας 1 — κύκλωμα', 'O2 sensor circuit (bank 1, sensor 1)'],
        'P0133' => ['low', 'Αισθητήρας οξυγόνου (λ) τράπεζα 1, αισθητήρας 1 — αργή απόκριση', 'O2 sensor slow response (bank 1, sensor 1)'],
        'P0135' => ['low', 'Θέρμανση αισθητήρα οξυγόνου (λ) τράπεζα 1, αισθητήρας 1', 'O2 sensor heater circuit (bank 1, sensor 1)'],
        'P0141' => ['low', 'Θέρμανση αισθητήρα οξυγόνου (λ) τράπεζα 1, αισθητήρας 2', 'O2 sensor heater circuit (bank 1, sensor 2)'],
        'P0171' => ['medium', 'Φτωχό μείγμα (τράπεζα 1)', 'System too lean (bank 1)'],
        'P0172' => ['medium', 'Πλούσιο μείγμα (τράπεζα 1)', 'System too rich (bank 1)'],
        'P0174' => ['medium', 'Φτωχό μείγμα (τράπεζα 2)', 'System too lean (bank 2)'],
        'P0217' => ['high', 'Υπερθέρμανση κινητήρα', 'Engine overheat condition'],
        'P0234' => ['high', 'Υπερβολική πίεση turbo', 'Turbocharger overboost condition'],
        'P0299' => ['medium', 'Χαμηλή πίεση turbo', 'Turbocharger underboost'],
        'P0300' => ['high', 'Τυχαίες / πολλαπλές αφλογιστίες', 'Random/multiple cylinder misfire detected'],
        'P0301' => ['high', 'Αφλογιστία στον κύλινδρο 1', 'Cylinder 1 misfire detected'],
        'P0302' => ['high', 'Αφλογιστία στον κύλινδρο 2', 'Cylinder 2 misfire detected'],
        'P0303' => ['high', 'Αφλογιστία στον κύλινδρο 3', 'Cylinder 3 misfire detected'],
        'P0304' => ['high', 'Αφλογιστία στον κύλινδρο 4', 'Cylinder 4 misfire detected'],
        'P0305' => ['high', 'Αφλογιστία στον κύλινδρο 5', 'Cylinder 5 misfire detected'],
        'P0306' => ['high', 'Αφλογιστία στον κύλινδρο 6', 'Cylinder 6 misfire detected'],
        'P0325' => ['medium', 'Αισθητήρας κρουστικής καύσης (knock) — κύκλωμα', 'Knock sensor 1 circuit'],
        'P0335' => ['high', 'Αισθητήρας θέσης στροφαλοφόρου — κύκλωμα', 'Crankshaft position sensor "A" circuit'],
        'P0340' => ['high', 'Αισθητήρας θέσης εκκεντροφόρου — κύκλωμα', 'Camshaft position sensor circuit'],
        'P0380' => ['medium', 'Προθερμαντήρες (πυράκτωσης) — κύκλωμα', 'Glow plug/heater circuit "A"'],
        'P0401' => ['medium', 'Ανεπαρκής ροή ανακυκλοφορίας καυσαερίων (EGR)', 'Exhaust gas recirculation (EGR) flow insufficient'],
        'P0402' => ['medium', 'Υπερβολική ροή ανακυκλοφορίας καυσαερίων (EGR)', 'Exhaust gas recirculation (EGR) flow excessive'],
        'P0420' => ['medium', 'Απόδοση καταλύτη κάτω από το όριο (τράπεζα 1)', 'Catalyst system efficiency below threshold (bank 1)'],
        'P0430' => ['medium', 'Απόδοση καταλύτη κάτω από το όριο (τράπεζα 2)', 'Catalyst system efficiency below threshold (bank 2)'],
        'P0440' => ['low', 'Σύστημα αναθυμιάσεων καυσίμου (EVAP) — δυσλειτουργία', 'Evaporative emission system malfunction'],
        'P0442' => ['low', 'Μικρή διαρροή στο σύστημα αναθυμιάσεων (EVAP)', 'EVAP system small leak detected'],
        'P0455' => ['low', 'Μεγάλη διαρροή στο σύστημα αναθυμιάσεων (EVAP) — συχνά χαλαρή τάπα ρεζερβουάρ', 'EVAP system large leak detected (often a loose fuel cap)'],
        'P0456' => ['low', 'Πολύ μικρή διαρροή στο σύστημα αναθυμιάσεων (EVAP)', 'EVAP system very small leak detected'],
        'P0457' => ['low', 'Διαρροή αναθυμιάσεων — χαλαρή ή χωρίς τάπα ρεζερβουάρ', 'EVAP leak detected (fuel cap loose/off)'],
        'P0500' => ['medium', 'Αισθητήρας ταχύτητας οχήματος', 'Vehicle speed sensor "A"'],
        'P0505' => ['low', 'Σύστημα ελέγχου ρελαντί', 'Idle air control system'],
        'P0507' => ['low', 'Ρελαντί υψηλότερο από το αναμενόμενο', 'Idle control system RPM higher than expected'],
        'P0520' => ['high', 'Αισθητήρας / διακόπτης πίεσης λαδιού — κύκλωμα', 'Engine oil pressure sensor/switch circuit'],
        'P0524' => ['high', 'Πολύ χαμηλή πίεση λαδιού κινητήρα', 'Engine oil pressure too low'],
        'P0562' => ['medium', 'Χαμηλή τάση συστήματος (μπαταρία / δυναμό)', 'System voltage low'],
        'P0563' => ['medium', 'Υψηλή τάση συστήματος', 'System voltage high'],
        'P0571' => ['low', 'Διακόπτης φρένου — κύκλωμα', 'Brake switch "A" circuit'],
        'P0603' => ['high', 'Σφάλμα μνήμης εγκεφάλου (ECU)', 'Internal control module keep-alive memory (KAM) error'],
        'P0606' => ['high', 'Σφάλμα επεξεργαστή εγκεφάλου (ECU)', 'Control module processor fault'],
        'P0700' => ['medium', 'Βλάβη στο σύστημα ελέγχου κιβωτίου — δες τον εγκέφαλο του κιβωτίου', 'Transmission control system malfunction (check TCM codes)'],
        'P0715' => ['medium', 'Αισθητήρας στροφών εισόδου κιβωτίου', 'Input/turbine speed sensor "A" circuit'],
        'P0741' => ['medium', 'Συμπλέκτης μετατροπέα ροπής — απόδοση', 'Torque converter clutch circuit performance or stuck off'],
        'P2002' => ['medium', 'Απόδοση φίλτρου σωματιδίων (DPF) κάτω από το όριο', 'Diesel particulate filter efficiency below threshold (bank 1)'],
        'P2463' => ['medium', 'Συσσώρευση αιθάλης στο φίλτρο σωματιδίων (DPF)', 'Diesel particulate filter restriction — soot accumulation'],
        'P2097' => ['medium', 'Πλούσιο μείγμα μετά τον καταλύτη (τράπεζα 1)', 'Post-catalyst fuel trim system too rich (bank 1)'],
        'U0100' => ['high', 'Χάθηκε η επικοινωνία με τον εγκέφαλο κινητήρα (ECM/PCM)', 'Lost communication with ECM/PCM "A"'],
        'U0101' => ['medium', 'Χάθηκε η επικοινωνία με τον εγκέφαλο κιβωτίου (TCM)', 'Lost communication with TCM'],
        'U0121' => ['medium', 'Χάθηκε η επικοινωνία με τη μονάδα ABS', 'Lost communication with anti-lock brake system (ABS) module'],
        'U0140' => ['low', 'Χάθηκε η επικοινωνία με τη μονάδα αμαξώματος (BCM)', 'Lost communication with body control module'],
    ];

    /** Second character of a P-code after "P0"/"P2" => [el, en] system. */
    private const P_SYSTEMS = [
        '0' => ['Μέτρηση καυσίμου / αέρα και εκπομπές', 'Fuel/air metering and emissions'],
        '1' => ['Μέτρηση καυσίμου και αέρα', 'Fuel and air metering'],
        '2' => ['Μέτρηση καυσίμου και αέρα (μπεκ)', 'Fuel and air metering (injector circuit)'],
        '3' => ['Ανάφλεξη / αφλογιστίες', 'Ignition system or misfire'],
        '4' => ['Βοηθητικά συστήματα εκπομπών', 'Auxiliary emission controls'],
        '5' => ['Ταχύτητα οχήματος, ρελαντί και βοηθητικές είσοδοι', 'Vehicle speed, idle control and auxiliary inputs'],
        '6' => ['Εγκέφαλος και έξοδοι του', 'Computer and output circuits'],
        '7' => ['Κιβώτιο ταχυτήτων', 'Transmission'],
        '8' => ['Κιβώτιο ταχυτήτων', 'Transmission'],
        '9' => ['Κιβώτιο ταχυτήτων', 'Transmission'],
        'A' => ['Υβριδικό σύστημα', 'Hybrid propulsion'],
        'B' => ['Υβριδικό σύστημα', 'Hybrid propulsion'],
        'C' => ['Υβριδικό σύστημα', 'Hybrid propulsion'],
    ];

    private const AREAS = [
        'P' => ['Κινητήρας & κιβώτιο', 'Powertrain'],
        'C' => ['Πλαίσιο (φρένα, ανάρτηση, τιμόνι)', 'Chassis (brakes, suspension, steering)'],
        'B' => ['Αμάξωμα (αερόσακοι, κλιματισμός, φώτα)', 'Body (airbags, climate, lighting)'],
        'U' => ['Δίκτυο επικοινωνίας μονάδων', 'Network communication'],
    ];

    /** True for a syntactically valid OBD-II code: P0420, U0100, B1234… */
    public static function isValid(string $code): bool
    {
        return (bool) preg_match('/^[PCBU][0-3][0-9A-F]{3}$/', strtoupper(trim($code)));
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @return array{area: string, system: ?string, description: ?string, severity: string, manufacturer: bool}
     */
    public static function describe(string $code): array
    {
        $code = self::normalize($code);
        $i    = app()->getLocale() === 'el' ? 1 : 2;
        $sys  = $i - 1;

        $letter = $code[0] ?? 'P';
        $type   = $code[1] ?? '0';
        // P0/P2/C0/B0/U0 are generic; 1 and 3 are the manufacturer's own.
        $manufacturer = in_array($type, ['1', '3'], true);

        $known = self::KNOWN[$code] ?? null;

        $system = null;
        if ($letter === 'P' && ! $manufacturer) {
            $system = self::P_SYSTEMS[$code[2] ?? ''][$sys] ?? null;
        }

        return [
            'area'         => self::AREAS[$letter][$sys] ?? $letter,
            'system'       => $system,
            'description'  => $known[$i] ?? null,
            'severity'     => $known[0] ?? ($letter === 'P' && ($code[2] ?? '') === '3' ? 'high' : 'medium'),
            'manufacturer' => $manufacturer,
        ];
    }
}
