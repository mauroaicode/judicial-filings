<?php

namespace Core\Shared\Domain\Enums;

enum NotificationType: string
{
    case MULTIPLE_INSTANCE = 'multiple_instances';
    case NEW_PROCESS_ACTION = 'new_process_action';
    case AI_WORDS_PROCESS_ACTION = 'ai_words_process_action';
}
