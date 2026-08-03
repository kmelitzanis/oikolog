<?php

namespace App\Services\RecipeAdapters;

/**
 * A site-specific reader for structured recipe data that schema.org misses.
 *
 * Adapters are an *enrichment* layer, never a replacement. Some sites publish
 * flat JSON-LD (Google only needs a list of steps) while their own page data
 * carries the structure a cook actually needs — the "Για τη βάση" / "Για την
 * κρέμα" grouping, and clean quantity/unit fields instead of "100 γρ.  ζάχαρη"
 * strings that have to be picked apart with a regex.
 *
 * Because an adapter reads another application's internal shape, it *will* break
 * when that site is rebuilt. Every adapter must therefore fail soft: return null
 * rather than throw, so the importer falls back to the standard JSON-LD path and
 * the user still gets a usable recipe.
 */
interface RecipeSiteAdapter
{
    /** Cheap check — does this adapter recognise the page? */
    public function supports(string $url, string $html): bool;

    /**
     * Extra fields for this page, or null when nothing could be read.
     *
     * May return any subset of: ingredients, steps. Anything absent is left to
     * the JSON-LD parser.
     *
     * @return array{
     *     ingredients?: array<int, array{section: ?string, name: string, quantity: float, unit: string}>,
     *     steps?: array<int, array{section: ?string, text: string}>
     * }|null
     */
    public function extract(string $url, string $html): ?array;
}
