<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\Shared\Services\AiRagService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Notifications\ProcessAiSummaryReadyNotification;
use Src\Domain\Process\Models\Process;

class GenerateProcessAiSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;

    public function __construct(
        public Process $process,
        public string $organizationId,
        public AppUser $appUser,
        public ?string $prompt = null
    ) {}

    /**
     * @throws Exception
     */
    public function handle(AiRagService $aiRagService): void
    {
        // 1. Markdown Concatenation
        $markdown = $this->buildMarkdown();

        // 2. Upload to RAG
        $aiRagService->uploadMarkdown(
            $this->organizationId,
            $this->process->process_number,
            $markdown
        );

        // 3. Query Summary using custom prompt if provided
        $summary = $aiRagService->querySummary($this->organizationId, $this->prompt);

        // 4. Save to DB
        $this->process->update([
            'ai_summary' => $summary,
        ]);

        // 5. Notify User via WebSocket
        $this->appUser->notify(new ProcessAiSummaryReadyNotification($this->process, $summary));
    }

    private function buildMarkdown(): string
    {
        $markdown = $this->buildHeader();
        $markdown .= $this->buildGeneralInformation();
        $markdown .= $this->buildSubjectsSection();
        $markdown .= $this->buildActionsSection();

        return $markdown.$this->buildAiNotice();
    }

    private function buildHeader(): string
    {
        return "# Proceso Judicial: {$this->process->process_number}\n\n";
    }

    private function buildGeneralInformation(): string
    {
        $markdown = "## Información General\n";
        $markdown .= "- **Despacho:** {$this->process->court}\n";
        $markdown .= "- **Departamento:** {$this->process->department}\n";
        $markdown .= "- **Tipo de Proceso:** {$this->process->process_type}\n";

        return $markdown."- **Clase de Proceso:** {$this->process->process_class}\n\n";
    }

    private function buildSubjectsSection(): string
    {
        $subjects = $this->process->subjects;
        $markdown = "## Sujetos Procesales\n";

        if ($subjects->isEmpty()) {
            return $markdown."*No hay sujetos procesales registrados.*\n\n";
        }

        foreach ($subjects as $subject) {
            $markdown .= "- **{$subject->subject_type}:** {$subject->name_or_business_name} ({$subject->identification})\n";
        }

        return $markdown."\n";
    }

    private function buildActionsSection(): string
    {
        $actions = $this->process->actions()->orderBy('cons_action', 'desc')->get();
        $markdown = "## Actuaciones\n";

        if ($actions->isEmpty()) {
            return $markdown."*No hay actuaciones registradas.*\n\n";
        }

        foreach ($actions as $action) {
            $markdown .= "### {$action->action_date->toDateString()} - {$action->action}\n";
            $markdown .= "{$action->annotation}\n\n";
        }

        return $markdown;
    }

    private function buildAiNotice(): string
    {
        $hasNoData = $this->process->subjects->isEmpty() &&
            $this->process->actions()->count() === 0;

        if (! $hasNoData) {
            return '';
        }

        return "\n---\n**AVISO PARA IA:** Este proceso es muy reciente o no tiene datos públicos de actuaciones/sujetos aún. Por favor, genera un resumen indicando que solo se dispone de la información básica de radicación.";
    }
}
