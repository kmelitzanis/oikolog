<?php

namespace App\Support;

/**
 * The app's unit vocabulary.
 *
 * Units used to be free text: whatever a recipe site wrote ("γρ.", "tbsp",
 * "piece") went straight into the column, so the same thing was stored under
 * several spellings and could not be translated. Everything is now canonicalised
 * to a key on the way in, and rendered through `messages.unit_*` on the way out,
 * so a Greek user sees "κ.σ." where an English one sees "tbsp".
 *
 * Canonical keys are deliberately the English abbreviations — they are stable
 * identifiers, not display text. Never show a raw key to a user; call label().
 */
class Units
{
    /** Canonical keys, in the order they should appear in a picker. */
    public const ALL = [
        'piece', 'g', 'kg', 'ml', 'l',
        'tbsp', 'tsp', 'cup', 'pinch',
        'slice', 'clove', 'bunch', 'pack', 'box', 'can', 'bottle',
    ];

    public const DEFAULT = 'piece';

    /**
     * Spellings seen in the wild → canonical key.
     *
     * Keys here are compared after lowercasing and stripping trailing dots, so
     * "ΓΡ." and "γρ" both land on "gr". Greek entries matter as much as English:
     * Greek recipe sites are the main import source.
     */
    private const ALIASES = [
        // pieces
        'piece' => 'piece', 'pieces' => 'piece', 'pc' => 'piece', 'pcs' => 'piece',
        'τεμ' => 'piece', 'τεμάχιο' => 'piece', 'τεμάχια' => 'piece', 'κομμάτι' => 'piece', 'κομμάτια' => 'piece',

        // mass
        'g' => 'g', 'gr' => 'g', 'gram' => 'g', 'grams' => 'g', 'gramme' => 'g',
        'γρ' => 'g', 'γραμ' => 'g', 'γραμμάριο' => 'g', 'γραμμάρια' => 'g',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
        'κ' => 'kg', 'κιλ' => 'kg', 'κιλό' => 'kg', 'κιλά' => 'kg',

        // volume
        'ml' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml', 'milliliter' => 'ml', 'milliliters' => 'ml',
        'μλ' => 'ml',
        'l' => 'l', 'lt' => 'l', 'litre' => 'l', 'litres' => 'l', 'liter' => 'l', 'liters' => 'l',
        'λ' => 'l', 'λίτρο' => 'l', 'λίτρα' => 'l',

        // spoons & cups
        'tbsp' => 'tbsp', 'tbs' => 'tbsp', 'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp',
        'κσ' => 'tbsp', 'κ.σ' => 'tbsp', 'κουταλιά σούπας' => 'tbsp', 'κουταλιές σούπας' => 'tbsp',
        'κουτ σούπας' => 'tbsp', 'κουταλιά της σούπας' => 'tbsp',
        'tsp' => 'tsp', 'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'κγ' => 'tsp', 'κ.γ' => 'tsp', 'κουταλάκι' => 'tsp', 'κουταλάκια' => 'tsp',
        'κουταλιά γλυκού' => 'tsp', 'κουταλάκι του γλυκού' => 'tsp',
        'cup' => 'cup', 'cups' => 'cup',
        'φλ' => 'cup', 'φλιτζάνι' => 'cup', 'φλιτζάνια' => 'cup', 'κούπα' => 'cup', 'κούπες' => 'cup',

        // small / countable
        'pinch' => 'pinch', 'pinches' => 'pinch', 'πρέζα' => 'pinch', 'πρέζες' => 'pinch',
        'slice' => 'slice', 'slices' => 'slice', 'φέτα' => 'slice', 'φέτες' => 'slice',
        'clove' => 'clove', 'cloves' => 'clove', 'σκελίδα' => 'clove', 'σκελίδες' => 'clove',
        'bunch' => 'bunch', 'bunches' => 'bunch', 'ματσάκι' => 'bunch', 'μάτσο' => 'bunch',

        // packaging
        'pack' => 'pack', 'packet' => 'pack', 'packets' => 'pack', 'πακέτο' => 'pack', 'πακέτα' => 'pack',
        'box' => 'box', 'boxes' => 'box', 'κουτί' => 'box', 'κουτιά' => 'box',
        'can' => 'can', 'cans' => 'can', 'tin' => 'can', 'κονσέρβα' => 'can',
        'bottle' => 'bottle', 'bottles' => 'bottle', 'μπουκάλι' => 'bottle', 'μπουκάλια' => 'bottle',
    ];

    /**
     * Map any spelling onto a canonical key.
     *
     * Returns null when the text isn't a unit at all, which is how the ingredient
     * parser tells "500 g flour" (unit) from "2 lemons" (no unit) — anything
     * unrecognised belongs to the ingredient's name, not its unit.
     */
    public static function canonical(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $key = mb_strtolower(trim($raw));
        // Greek abbreviations are written with dots ("κ.σ.", "γρ."); normalise
        // trailing dots but keep internal ones so "κ.σ" still matches.
        $key = rtrim($key, ". \t");
        $key = preg_replace('/\s+/u', ' ', $key);

        if ($key === '') {
            return null;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Retry without internal dots: "κ.σ" → "κσ".
        $stripped = str_replace('.', '', $key);

        return self::ALIASES[$stripped] ?? null;
    }

    /** Canonical key, falling back to `piece` — for columns that must hold a unit. */
    public static function canonicalOrDefault(?string $raw): string
    {
        return self::canonical($raw) ?? self::DEFAULT;
    }

    /** The localised label to display for a unit key. */
    public static function label(?string $unit): string
    {
        $key = self::canonical($unit);

        if ($key === null) {
            // Something stored before canonicalisation, or hand-typed: show it
            // as-is rather than swallowing it.
            return (string) $unit;
        }

        return __('messages.unit_' . $key);
    }

    /** @return array<string, string> canonical key => localised label, for pickers. */
    public static function options(): array
    {
        $out = [];
        foreach (self::ALL as $key) {
            $out[$key] = __('messages.unit_' . $key);
        }

        return $out;
    }
}
