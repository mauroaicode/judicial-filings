<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\Models\ProcessExport;

/**
 * In-app + WebSocket alert for admin Users when a process Excel export finishes.
 */
class ProcessExportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProcessExport $export,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->getData();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))->onQueue('notifications');
    }

    public function broadcastType(): string
    {
        return 'ProcessExportFinished';
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        $this->export->loadMissing(['organization']);

        $orgName = $this->export->organization?->name ?: '—';
        $completed = $this->export->status === ProcessExportStatus::Completed;
        $downloadable = $this->export->isDownloadable();

        return [
            'title' => $completed
                ? __('process.export_finished_notification_title')
                : __('process.export_failed_notification_title'),
            'description' => $completed
                ? __('process.export_finished_notification_description', [
                    'organization' => $orgName,
                    'count' => $this->export->row_count,
                    'actions' => (int) ($this->export->action_row_count ?? 0),
                ])
                : __('process.export_failed_notification_description', [
                    'organization' => $orgName,
                    'error' => $this->export->error_message ?: '—',
                ]),
            'type' => 'process-export-finished',
            'export_id' => $this->export->id,
            'organization_id' => $this->export->organization_id,
            'organization_name' => $orgName,
            'status' => $this->export->status->value,
            'row_count' => $this->export->row_count,
            'action_row_count' => (int) ($this->export->action_row_count ?? 0),
            'filters' => $this->export->filters,
            'downloadable' => $downloadable,
            'download_url' => $downloadable
                ? route('admin.organizations.processes.exports.download', [
                    'organization' => $this->export->organization_id,
                    'processExport' => $this->export->id,
                ])
                : null,
            'url' => '/admin/organizations/'.$this->export->organization_id.'/exports',
        ];
    }
}
