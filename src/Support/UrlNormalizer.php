<?php

declare(strict_types=1);

namespace HardImpact\CloudflareCache\Support;

final class UrlNormalizer
{
    /**
     * @param  iterable<int|string, mixed>  $urls
     * @return list<string>
     */
    public static function normalizeMany(iterable $urls): array
    {
        $normalized = [];

        foreach ($urls as $url) {
            if (! is_string($url) && ! (is_object($url) && method_exists($url, '__toString'))) {
                continue;
            }

            $value = self::normalize((string) $url);

            if ($value !== null) {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    public static function normalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Drop fragments — they are never part of a cache key.
        $hashPosition = strpos($url, '#');

        if ($hashPosition !== false) {
            $url = substr($url, 0, $hashPosition);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
