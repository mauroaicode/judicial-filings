<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\Domain\Process\Models\Process;

class ProcessResource extends JsonResource
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Process $process */
        $process = $this->resource;

        return [
            'id' => $process->id,
            'process_number' => $process->process_number,
            'court' => $process->court,
            'speaker' => $process->speaker,
            'status' => $process->status,
            'created_at' => $process->created_at->toDateTimeString(),
        ];
    }
}
