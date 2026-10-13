<?php

declare(strict_types=1);

namespace Src\Domain\Media\PathGenerators;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionMediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'responsive/';
    }

    private function basePath(Media $media): string
    {
        $model = $media->model;
        $processId = $model instanceof ProcessAction ? $model->process_id : 'unknown';

        return "action-documents/{$processId}/{$media->model_id}/{$media->getKey()}/";
    }
}
