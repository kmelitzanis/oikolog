<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Stores recipe photos on the `public` disk, normalised to one size and format.
 *
 * Both entry points — a file the user picked and a URL pulled off an imported
 * page — funnel through the same re-encode. That matters for more than tidiness:
 * decoding an image and writing out fresh JPEG bytes drops any EXIF payload and
 * guarantees the stored file really is an image, so a `.jpg` that is actually a
 * PHP script cannot survive the round trip.
 */
class RecipeImageStore
{
    private const DIRECTORY = 'recipes';

    /**
     * The exact shape `store()` produces: `recipes/<32 hex>.jpg`.
     *
     * Paths arrive from the client (the form posts back the `image_path` it was
     * given), so they are untrusted input and are matched against this rather
     * than merely prefix-checked. A prefix check accepts `recipes/../avatars/x`,
     * which normalises back inside the disk root and would let one user delete
     * another's files.
     */
    private const PATH_PATTERN = '#^recipes/[0-9a-f]{32}\\.jpg$#';
    private const MAX_EDGE = 1200;
    private const QUALITY = 82;

    /** ~50 MP: comfortably above any real photo, far below a decompression bomb. */
    private const MAX_PIXELS = 50_000_000;

    /** Formats we are willing to decode; anything else is rejected outright. */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private readonly SafeUrlFetcher $fetcher)
    {
    }

    /** Store an uploaded file, returning its path on the public disk. */
    public function storeUpload(UploadedFile $file): string
    {
        return $this->store(file_get_contents($file->getRealPath()));
    }

    /**
     * Download an image from a URL and store it.
     *
     * The URL comes from a page we just scraped, so it is untrusted input and
     * goes through the same SSRF guard as the page fetch itself.
     */
    public function storeFromUrl(string $url): string
    {
        $response = $this->fetcher->fetch($url, ['image/*']);

        if (! str_starts_with($response['contentType'], 'image/')) {
            throw new RuntimeException(__('messages.import_image_failed'));
        }

        return $this->store($response['body']);
    }

    /** True when a path is one this class could have produced. */
    public static function isManagedPath(?string $path): bool
    {
        return $path !== null && (bool) preg_match(self::PATH_PATTERN, $path);
    }

    /** Remove a previously stored image; missing files are not an error. */
    public function delete(?string $path): void
    {
        if (self::isManagedPath($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function store(string $bytes): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException(__('messages.image_invalid'));
        }

        // Check the declared dimensions from the header *before* decoding.
        // A decoder allocates roughly width × height × 4 bytes, so a highly
        // compressible 30000×30000 PNG is a few hundred KB on the wire and
        // several gigabytes in memory — a file-size limit alone doesn't stop it.
        $size = @getimagesizefromstring($bytes);
        if ($size === false) {
            throw new RuntimeException(__('messages.image_invalid'));
        }
        if (($size[0] * $size[1]) > self::MAX_PIXELS) {
            throw new RuntimeException(__('messages.image_too_large'));
        }

        try {
            $image = (new ImageManager(new Driver()))->read($bytes);
            // scaleDown never enlarges, so small photos keep their own resolution.
            $encoded = (string) $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE)
                ->toJpeg(self::QUALITY);
        } catch (\Throwable $e) {
            throw new RuntimeException(__('messages.image_invalid'));
        }

        $path = self::DIRECTORY . '/' . bin2hex(random_bytes(16)) . '.jpg';
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }
}
