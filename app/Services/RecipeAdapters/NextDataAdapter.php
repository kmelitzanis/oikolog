<?php

namespace App\Services\RecipeAdapters;

use App\Support\Units;
use Illuminate\Support\Str;

/**
 * Reads recipe sections out of a Next.js page's `__NEXT_DATA__` blob.
 *
 * Written for akispetretzikis.com, whose JSON-LD flattens a recipe into one
 * unbroken list of steps while the page's own data keeps the structure:
 *
 *     props.pageProps.ssRecipe.data
 *         ingredient_sections[] → { title, ingredients[] → { title, quantity, unit, info } }
 *         method[]              → { section, steps[] → { step } }
 *
 * Two things make this worth reading over the JSON-LD: the section headings
 * exist at all, and quantity/unit arrive as separate fields, so no amount has to
 * be recovered by pattern-matching "100 γρ.  ζάχαρη".
 *
 * The lookup is shape-based rather than keyed to a hostname, so any Next.js site
 * exposing the same structure benefits — but it is still another application's
 * private shape, so every step here tolerates absence and gives up quietly.
 */
class NextDataAdapter implements RecipeSiteAdapter
{
    public function supports(string $url, string $html): bool
    {
        return str_contains($html, '__NEXT_DATA__');
    }

    public function extract(string $url, string $html): ?array
    {
        $data = $this->recipeNode($html);
        if ($data === null) {
            return null;
        }

        $out = [];

        if ($ingredients = $this->ingredients($data)) {
            $out['ingredients'] = $ingredients;
        }
        if ($steps = $this->steps($data)) {
            $out['steps'] = $steps;
        }

        return $out ?: null;
    }

    /** The recipe payload inside the Next.js props tree, or null. */
    private function recipeNode(string $html): ?array
    {
        if (! preg_match('#<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
            return null;
        }

        $json = json_decode(trim($m[1]), true);
        if (! is_array($json)) {
            return null;
        }

        // Search by shape instead of a fixed path: the wrapper key differs per
        // site and per release, but a node holding these arrays is the recipe.
        return $this->findBySignature($json);
    }

    private function findBySignature(mixed $node, int $depth = 0): ?array
    {
        if ($depth > 12 || ! is_array($node)) {
            return null;
        }

        $hasIngredients = isset($node['ingredient_sections']) && is_array($node['ingredient_sections']);
        $hasMethod      = isset($node['method']) && is_array($node['method']);

        if ($hasIngredients || $hasMethod) {
            return $node;
        }

        foreach ($node as $child) {
            if (is_array($child) && $found = $this->findBySignature($child, $depth + 1)) {
                return $found;
            }
        }

        return null;
    }

    /** @return array<int, array{section: ?string, name: string, quantity: float, unit: string}> */
    private function ingredients(array $data): array
    {
        $out = [];

        foreach ($data['ingredient_sections'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }

            $section = $this->clean($group['title'] ?? null);

            foreach ($group['ingredients'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = $this->clean($item['title'] ?? null);
                if ($name === null) {
                    continue;
                }

                // `info` carries a supplementary amount ("425 γρ." for one sheet
                // of pastry). It isn't the quantity, so it rides along in the
                // name rather than overwriting a real measurement.
                if ($info = $this->clean($item['info'] ?? null)) {
                    $name .= " ({$info})";
                }

                $quantity = $this->toNumber($item['quantity'] ?? null);

                $out[] = [
                    'section'  => $section,
                    'name'     => Str::limit($name, 255, ''),
                    'quantity' => $quantity ?? 1.0,
                    'unit'     => Units::canonicalOrDefault($item['unit'] ?? null),
                ];
            }
        }

        return $out;
    }

    /** @return array<int, array{section: ?string, text: string}> */
    private function steps(array $data): array
    {
        $out = [];

        foreach ($data['method'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }

            $section = $this->clean($group['section'] ?? null);

            foreach ($group['steps'] ?? [] as $step) {
                $text = is_array($step)
                    ? $this->clean($step['step'] ?? $step['text'] ?? null)
                    : $this->clean($step);

                if ($text !== null) {
                    $out[] = ['section' => $section, 'text' => $text];
                }
            }
        }

        return $out;
    }

    /** Decode entities, strip markup, collapse whitespace; '' becomes null. */
    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        // Steps arrive with entities intact ("190&deg;C").
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $text !== '' ? $text : null;
    }

    private function toNumber(mixed $raw): ?float
    {
        if (! is_scalar($raw)) {
            return null;
        }

        $text = trim(str_replace(',', '.', (string) $raw));
        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        return round((float) $text, 2);
    }
}
