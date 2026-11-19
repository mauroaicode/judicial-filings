<?php

declare(strict_types=1);

namespace Src\Application\Shared\Pdf;

final class PdfLogoDataUri
{
    public static function resolve(): ?string
    {
        $path = public_path('images/logo-notijudicial-grande.png');

        if (! is_file($path)) {
            $path = public_path('images/logo-notijudicial.png');
        }

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
