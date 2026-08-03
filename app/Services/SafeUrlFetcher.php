<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches a user-supplied URL with SSRF protection.
 *
 * Recipe import hands the server a URL typed by whoever is using the app and asks
 * it to go fetch that address. Unguarded, that turns the app into a proxy into
 * its own network: `http://127.0.0.1:3306`, a Docker-internal hostname, or the
 * cloud metadata endpoint at 169.254.169.254 would all be fetched by the server
 * and the response handed back to the caller.
 *
 * The guard therefore:
 *   - allows only http / https,
 *   - resolves the hostname and rejects any address in a private, loopback,
 *     link-local or otherwise reserved range,
 *   - follows redirects manually so each hop is re-validated (a public host is
 *     free to 302 you to 127.0.0.1, so validating only the first URL is useless),
 *   - caps body size and wall-clock time.
 */
class SafeUrlFetcher
{
    public const MAX_REDIRECTS = 3;
    public const TIMEOUT_SECONDS = 10;
    public const MAX_BYTES = 3_145_728; // 3 MB

    /** Browser-ish UA: several recipe sites serve a stub or 403 to unknown agents. */
    private const USER_AGENT = 'Mozilla/5.0 (compatible; OikologRecipeImporter/1.0; +https://github.com/kmelitzanis/oikolog)';

    /**
     * Fetch a URL, following redirects with every hop validated.
     *
     * @return array{body: string, url: string, contentType: string}
     * @throws RuntimeException when the URL is unsafe, unreachable, or too large
     */
    public function fetch(string $url, array $acceptTypes = []): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            // assertSafe returns the address it approved so the request can be
            // pinned to it. Resolving again inside the HTTP client would open a
            // DNS-rebinding hole: an attacker who controls the zone can answer
            // the check with a public IP and the connection with 127.0.0.1.
            $ip = $this->assertSafe($current);

            $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => $acceptTypes ? implode(',', $acceptTypes) : 'text/html,application/xhtml+xml',
                ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->withoutRedirecting()
                ->withOptions([
                    'stream' => true,
                    'curl'   => [CURLOPT_RESOLVE => [$this->resolveEntry($current, $ip)]],
                ])
                ->get($current);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new RuntimeException(__('messages.import_unreachable'));
                }
                // Relative Location headers are legal and common.
                $current = $this->resolveRelative($current, $location);
                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException(__('messages.import_unreachable'));
            }

            return [
                'body'        => $this->readCapped($response),
                'url'         => $current,
                'contentType' => strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])),
            ];
        }

        throw new RuntimeException(__('messages.import_unreachable'));
    }

    /**
     * Read at most MAX_BYTES, streaming.
     *
     * Buffering the whole body and measuring it afterwards enforces nothing —
     * by then a multi-gigabyte response is already in memory. Reading in chunks
     * lets the transfer be abandoned as soon as it goes over.
     */
    private function readCapped(\Illuminate\Http\Client\Response $response): string
    {
        // A truthful Content-Length lets us refuse before reading anything.
        $declared = (int) $response->header('Content-Length');
        if ($declared > self::MAX_BYTES) {
            throw new RuntimeException(__('messages.import_too_large'));
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(65536);

            if (strlen($body) > self::MAX_BYTES) {
                $stream->close();
                throw new RuntimeException(__('messages.import_too_large'));
            }
        }

        return $body;
    }

    /** A `host:port:ip` entry pinning this URL's hostname to the vetted address. */
    private function resolveEntry(string $url, string $ip): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443);

        // CURLOPT_RESOLVE wants a bare address; parse_url keeps IPv6 in brackets.
        return trim($host, '[]') . ':' . $port . ':' . $ip;
    }

    /**
     * Reject anything that isn't a plain public http(s) address.
     *
     * @return string the vetted address the caller must connect to
     * @throws RuntimeException
     */
    public function assertSafe(string $url): string
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            throw new RuntimeException(__('messages.import_invalid_url'));
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(__('messages.import_invalid_url'));
        }

        $host = $parts['host'];

        // Every address the hostname resolves to must be public — a name with one
        // public and one private A record is still a way in.
        $addresses = $this->resolve($host);

        foreach ($addresses as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new RuntimeException(__('messages.import_blocked_host'));
            }
        }

        return $addresses[0];
    }

    /**
     * Every IP a hostname maps to.
     *
     * Protected rather than private so tests can substitute a resolver: the guard
     * must do real DNS in production, which would otherwise make it untestable
     * without live network lookups against domains that may not exist.
     *
     * @return string[]
     */
    protected function resolve(string $host): array
    {
        // A literal IP in the URL never reaches DNS.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (! empty($record['ip']))   $ips[] = $record['ip'];
            if (! empty($record['ipv6'])) $ips[] = $record['ipv6'];
        }

        if (! $ips) {
            // dns_get_record can come back empty where gethostbyname still works.
            $resolved = gethostbyname($host);
            if ($resolved !== $host) $ips[] = $resolved;
        }

        if (! $ips) {
            throw new RuntimeException(__('messages.import_unreachable'));
        }

        return $ips;
    }

    /**
     * A public, routable address — this is the whole SSRF guard.
     *
     * FILTER_FLAG_NO_PRIV_RANGE covers 10/8, 172.16/12, 192.168/16 and fc00::/7;
     * NO_RES_RANGE covers loopback, link-local (including the 169.254.169.254
     * metadata address), 0.0.0.0/8 and the other reserved blocks.
     */
    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /** Resolve a possibly-relative Location header against the URL it came from. */
    private function resolveRelative(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = $parts['path'] ?? '/';

        return $origin . rtrim(substr($path, 0, strrpos($path, '/') + 1), '/') . '/' . $location;
    }
}
