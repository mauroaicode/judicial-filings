<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AiChat\Support;

use Psr\Http\Message\StreamInterface;

/**
 * Keeps nginx / mobile clients alive while rag-api processes long hybrid queries.
 */
final class RagSseProxyHelper
{
    public static function flushKeepAlive(): void
    {
        echo ": ping\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * @return array<string, mixed>
     */
    public static function guzzleOptions(): array
    {
        return [
            'timeout' => (int) config('ia-rag.timeout', 120),
            'connect_timeout' => 10,
        ];
    }

    /**
     * Forwards rag-api SSE to the client, emitting keep-alive pings when the upstream is silent.
     */
    public static function relay(StreamInterface $body, callable $onChunk): void
    {
        $resource = $body->detach();

        if (! is_resource($resource)) {
            while (! $body->eof()) {
                $chunk = $body->read(1024);
                if ($chunk === '') {
                    break;
                }

                self::emitChunk($chunk, $onChunk);
            }

            return;
        }

        stream_set_blocking($resource, true);
        stream_set_timeout($resource, 15);

        while (! feof($resource)) {
            $chunk = fread($resource, 1024);

            if ($chunk === false) {
                break;
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($resource);

                if ($meta['timed_out']) {
                    self::flushKeepAlive();

                    continue;
                }

                break;
            }

            self::emitChunk($chunk, $onChunk);
        }
    }

    private static function emitChunk(string $chunk, callable $onChunk): void
    {
        echo $chunk;
        $onChunk($chunk);

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
