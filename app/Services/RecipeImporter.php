<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use App\Services\RecipeAdapters\NextDataAdapter;
use App\Services\RecipeAdapters\RecipeSiteAdapter;
use App\Support\Units;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a recipe web page into the fields the recipe form expects.
 *
 * Recipe sites almost universally publish schema.org Recipe as JSON-LD, because
 * Google requires it for recipe rich results. Reading that beats scraping CSS
 * selectors per site: it is a documented contract rather than a guess about
 * someone's markup, and it does not break when they restyle the page.
 *
 * OpenGraph is the fallback so a page with no structured data still yields a
 * title and a picture rather than nothing at all.
 */
class RecipeImporter
{
    /** @var array<int, RecipeSiteAdapter> tried in order, before falling back to JSON-LD alone */
    private array $adapters;

    public function __construct(
        private readonly SafeUrlFetcher $fetcher,
        private readonly RecipeImageStore $images,
    ) {
        $this->adapters = [new NextDataAdapter()];
    }

    /**
     * @return array{
     *     name: ?string, description: ?string, servings: ?int, prep_minutes: ?int,
     *     cook_minutes: ?int, steps: array, ingredients: array, image_path: ?string,
     *     source_url: string, matched: bool
     * }
     */
    public function import(string $url): array
    {
        $page = $this->fetcher->fetch($url);

        if (! str_contains($page['contentType'], 'html') && $page['contentType'] !== '') {
            throw new RuntimeException(__('messages.import_not_html'));
        }

        $dom = $this->parseHtml($page['body']);
        $recipe = $this->findRecipeNode($dom);

        $data = $recipe
            ? $this->fromJsonLd($recipe)
            : $this->fromOpenGraph($dom);

        // Some sites publish flat JSON-LD while their own page data keeps the
        // section structure. Adapters fill that gap; they only ever add, so a
        // broken adapter degrades to the standard result rather than to nothing.
        $enriched = $this->enrich($page['url'], $page['body']);
        foreach (['ingredients', 'steps'] as $field) {
            if (! empty($enriched[$field])) {
                $data[$field] = $enriched[$field];
            }
        }

        $data['source_url'] = $page['url'];
        $data['matched']    = $recipe !== null || ! empty($enriched);

        // Downloading the image goes through the same SSRF guard — the image URL
        // comes from the fetched page, which is exactly as untrusted as the input.
        $data['image_path'] = null;
        if (! empty($data['image_url'])) {
            try {
                $data['image_path'] = $this->images->storeFromUrl($data['image_url']);
            } catch (RuntimeException $e) {
                // A missing picture must not sink an otherwise good import.
            }
        }
        unset($data['image_url']);

        return $data;
    }

    /**
     * Ask each adapter for what the standard markup left out.
     *
     * Adapters read another site's private data shape, so a failure here is
     * expected rather than exceptional: swallow it and let JSON-LD stand.
     *
     * @return array<string, mixed>
     */
    private function enrich(string $url, string $html): array
    {
        foreach ($this->adapters as $adapter) {
            try {
                if (! $adapter->supports($url, $html)) {
                    continue;
                }

                if ($extra = $adapter->extract($url, $html)) {
                    return $extra;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [];
    }

    private function parseHtml(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        // Recipe pages are full of malformed markup; parse errors are expected
        // and must not surface as PHP warnings.
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    /** Locate the schema.org Recipe object, wherever it sits in the JSON-LD graph. */
    private function findRecipeNode(DOMXPath $xpath): ?array
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $json = json_decode(trim($script->textContent), true);
            if (! is_array($json)) {
                continue;
            }

            if ($found = $this->searchForRecipe($json)) {
                return $found;
            }
        }

        return null;
    }

    /** JSON-LD nests Recipe under @graph, arrays, or neither — walk all of it. */
    private function searchForRecipe(array $node): ?array
    {
        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        if (in_array('Recipe', $types, true)) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value) && $found = $this->searchForRecipe($value)) {
                return $found;
            }
        }

        return null;
    }

    private function fromJsonLd(array $r): array
    {
        return [
            'name'         => $this->text($r['name'] ?? null),
            'description'  => Str::limit((string) $this->text($r['description'] ?? null), 2000, ''),
            'servings'     => $this->parseYield($r['recipeYield'] ?? null),
            'prep_minutes' => $this->parseDuration($r['prepTime'] ?? null),
            'cook_minutes' => $this->parseDuration($r['cookTime'] ?? $r['performTime'] ?? null),
            'steps'        => $this->parseInstructions($r['recipeInstructions'] ?? null),
            'ingredients'  => $this->parseIngredients($r['recipeIngredient'] ?? $r['ingredients'] ?? []),
            'image_url'    => $this->parseImage($r['image'] ?? null),
        ];
    }

    private function fromOpenGraph(DOMXPath $xpath): array
    {
        $meta = function (string $property) use ($xpath): ?string {
            $node = $xpath->query("//meta[@property='{$property}' or @name='{$property}']")?->item(0);

            return $node?->getAttribute('content') ?: null;
        };

        $title = $meta('og:title')
            ?? $xpath->query('//title')?->item(0)?->textContent;

        return [
            'name'         => $this->text($title),
            'description'  => Str::limit((string) $meta('og:description'), 2000, '') ?: null,
            'servings'     => null,
            'prep_minutes' => null,
            'cook_minutes' => null,
            'steps'        => [],
            'ingredients'  => [],
            'image_url'    => $meta('og:image'),
        ];
    }

    // ── Field parsers ────────────────────────────────────────────────────────

    /** JSON-LD values are routinely a string, a list, or a {@value: …} object. */
    private function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['@value'] ?? $value['name'] ?? ($value[0] ?? null);
            if (is_array($value)) {
                $value = $value['@value'] ?? $value['name'] ?? null;
            }
        }

        if (! is_scalar($value)) {
            return null;
        }

        $clean = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $clean = preg_replace('/\s+/u', ' ', $clean);

        return $clean !== '' ? $clean : null;
    }

    /** "4 servings", "Serves 4", "4-6" → 4. */
    private function parseYield(mixed $value): ?int
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/\d+/', $text, $m)) {
            $n = (int) $m[0];

            return ($n >= 1 && $n <= 50) ? $n : null;
        }

        return null;
    }

    /** ISO 8601 duration ("PT1H30M") → whole minutes. */
    private function parseDuration(mixed $value): ?int
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/i', $text, $m)) {
            return null;
        }

        $minutes = ((int) ($m[1] ?? 0)) * 1440 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);

        return ($minutes > 0 && $minutes <= 1440) ? $minutes : null;
    }

    /**
     * Method steps, keeping the headings a recipe is written under.
     *
     * schema.org expresses "Για τη βάση … Για τη σαντιγί" as HowToSection nodes,
     * each with a name and its own itemListElement. The importer used to walk
     * straight through those into a flat list, which threw the headings away —
     * exactly the structure that makes a multi-part recipe readable.
     *
     * @return array<int, array{section: ?string, text: string}>
     */
    private function parseInstructions(mixed $value): array
    {
        $steps = [];

        $walk = function (mixed $node, ?string $section) use (&$walk, &$steps): void {
            if (is_string($node)) {
                foreach (preg_split('/\r\n|\r|\n/', $node) as $line) {
                    if ($text = $this->cleanStep($line)) {
                        $steps[] = ['section' => $section, 'text' => $text];
                    }
                }

                return;
            }

            if (! is_array($node)) {
                return;
            }

            $types = (array) ($node['@type'] ?? null);

            // A section renames the heading for everything nested inside it.
            if (in_array('HowToSection', $types, true) || isset($node['itemListElement'])) {
                $name = $this->text($node['name'] ?? null);
                $walk($node['itemListElement'] ?? [], $name ?: $section);

                return;
            }

            if (isset($node['text']) || isset($node['name'])) {
                if ($text = $this->cleanStep($node['text'] ?? $node['name'])) {
                    $steps[] = ['section' => $section, 'text' => $text];
                }

                return;
            }

            foreach ($node as $child) {
                $walk($child, $section);
            }
        };

        $walk($value, null);

        return $steps;
    }

    /** Normalise one step, dropping the site's own numbering — the UI renumbers. */
    private function cleanStep(mixed $raw): ?string
    {
        $text = $this->text($raw);
        if ($text === null) {
            return null;
        }

        $text = preg_replace('/^\s*(?:step\s*|βήμα\s*)?\d+[\.\):]\s*/iu', '', $text);

        return trim($text) !== '' ? trim($text) : null;
    }

    /**
     * Ingredients, grouped by any headings the list carries.
     *
     * schema.org has no field for ingredient groups, so sites that write in parts
     * smuggle the headings in as list entries — "Για τη βάση:" followed by that
     * part's ingredients. An entry is treated as a heading only when it carries no
     * quantity and looks like one (trailing colon, or "Για …" / "For …"), which
     * keeps a genuine ingredient like "salt" from being mistaken for a title.
     *
     * Quantity parsing stays conservative: anything that can't be split
     * confidently keeps the whole string as the name, so nothing is dropped
     * silently. The user reviews the result before saving.
     *
     * @return array<int, array{section: ?string, name: string, quantity: float, unit: string}>
     */
    private function parseIngredients(mixed $value): array
    {
        $lines = is_array($value) ? $value : [$value];
        $out = [];
        $section = null;

        foreach ($lines as $line) {
            $text = $this->text($line);
            if ($text === null) {
                continue;
            }

            if ($heading = $this->asSectionHeading($text)) {
                $section = $heading;
                continue;
            }

            $out[] = $this->parseIngredientLine($text) + ['section' => $section];
        }

        return $out;
    }

    /** A heading, or null when the line is an ordinary ingredient. */
    private function asSectionHeading(string $text): ?string
    {
        // Anything starting with a number is a quantity, never a heading.
        if (preg_match('/^\s*\d/u', $text)) {
            return null;
        }

        $trimmed = trim($text);

        $looksLikeHeading = str_ends_with($trimmed, ':')
            || preg_match('/^(?:για\s+τ|για\s+το|για\s+τη|για\s+την|για\s+τα|for\s+the)\b/iu', $trimmed);

        if (! $looksLikeHeading) {
            return null;
        }

        $heading = rtrim($trimmed, ": \t");

        // A bare colon-terminated word is more likely a heading than an
        // ingredient, but an over-long line is prose — leave it alone.
        return mb_strlen($heading) > 0 && mb_strlen($heading) <= 120 ? $heading : null;
    }

    /** "500 γρ. αλεύρι" → quantity 500, unit g, name "αλεύρι". */
    private function parseIngredientLine(string $text): array
    {
        $quantity = 1.0;
        $unit = Units::DEFAULT;
        $name = $text;

        // Leading amount: "1", "1.5", "1,5", "1/2", "1 1/2".
        $pattern = '/^(?<qty>\d+\s+\d+\/\d+|\d+\/\d+|\d+(?:[.,]\d+)?)\s*(?<rest>.*)$/u';
        if (preg_match($pattern, $text, $m)) {
            $quantity = $this->toNumber($m['qty']);
            $rest = trim($m['rest']);

            // Take the first token as a candidate unit and let the vocabulary
            // decide. Greek sites write "100 γρ.  ζάχαρη" — note the trailing
            // dot and the double space.
            if (preg_match('/^(?<candidate>[^\s]+)\s+(?<name>.+)$/u', $rest, $um)) {
                if ($canonical = Units::canonical($um['candidate'])) {
                    $unit = $canonical;
                    $name = trim($um['name']);
                } else {
                    $name = $rest;
                }
            } elseif ($rest !== '') {
                $name = $rest;
            }
        }

        return [
            'name'     => Str::limit(preg_replace('/\s+/u', ' ', trim($name)), 255, ''),
            'quantity' => round($quantity, 2),
            'unit'     => $unit,
        ];
    }

    private function toNumber(string $raw): float
    {
        $raw = trim($raw);

        // "1 1/2"
        if (preg_match('#^(\d+)\s+(\d+)/(\d+)$#', $raw, $m)) {
            return (float) $m[1] + ((float) $m[2] / max(1, (float) $m[3]));
        }
        // "1/2"
        if (preg_match('#^(\d+)/(\d+)$#', $raw, $m)) {
            return (float) $m[1] / max(1, (float) $m[2]);
        }

        return (float) str_replace(',', '.', $raw);
    }

    /** `image` may be a string, a list, or an ImageObject. */
    private function parseImage(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return $value['url'];
            }
            foreach ($value as $item) {
                if ($url = $this->parseImage($item)) {
                    return $url;
                }
            }
        }

        return null;
    }
}
