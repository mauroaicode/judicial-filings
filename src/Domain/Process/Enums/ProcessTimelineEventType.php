<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

enum ProcessTimelineEventType: string
{
    case PROCESS_BECAME_PRIVATE = 'process_became_private';
    case PROCESS_SOURCE_CHANGED = 'process_source_changed';
    case TASK_CREATED = 'task_created';
    case TASK_UPDATED = 'task_updated';
    case TASK_STATUS_CHANGED = 'task_status_changed';
    case TASK_DELETED = 'task_deleted';
    case TASK_RESTORED = 'task_restored';
    case PROCESS_SUSPENDED = 'process_suspended';
    case PROCESS_RESUMED = 'process_resumed';
    case TRACKING_ACTIVATED = 'tracking_activated';
    case TRACKING_DEACTIVATED = 'tracking_deactivated';
    case TRACKING_TRASHED = 'tracking_trashed';
    case SEMAPHORE_CHANGED = 'semaphore_changed';
    case SPEAKER_CHANGED = 'speaker_changed';
}
