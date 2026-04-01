<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Domain\Notification\Models\NotificationDigest;

class NotificationDigestResource extends Resource
{
    public function __construct(
        public string $id,
        public array $data,
        public string $created_at,
        public ?string $email_sent_at = null,
        public ?string $whatsapp_sent_at = null,
        public ?string $sms_sent_at = null,
    ) {}

    public static function fromModel(NotificationDigest $digest, ?NotificationDigestFilterData $filters = null): self
    {
        // Cargar datos vinculados con Eager Loading optimizado para evitar N+1
        $digest->load([
            'notifications.notifiable.alertHighlights',
            'notifications.notifiable.process',
        ]);
        
        // Crear un mapa de búsqueda O(1) para asociar el JSON con las notificaciones reales
        $notifLookup = [];
        foreach ($digest->notifications as $notif) {
            $action = $notif->notifiable;
            if ($action instanceof \Src\Domain\Process\Models\ProcessAction) {
                // Usamos una combinación única de radicado y texto de actuación
                $key = "{$action->process->process_number}|{$action->action}";
                $notifLookup[$key] = $notif;
            }
        }

        $formattedData = array_map(function (array $item) use ($notifLookup, $digest, $filters) {
            // --- LÓGICA DE FILTRADO INTERNA ---
            if ($filters) {
                // Filtro por número de proceso
                if ($filters->process_number && isset($item['process_number'])) {
                    if (!str_contains($item['process_number'], $filters->process_number)) {
                        return null;
                    }
                }

                // Filtro por fecha de registro
                if ($filters->registration_date_from || $filters->registration_date_to) {
                    if (!isset($item['registration_date']) || empty($item['registration_date'])) {
                        return null;
                    }
                    try {
                        $regDate = str_contains((string)$item['registration_date'], '/')
                            ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', (string)$item['registration_date'])
                            : \Illuminate\Support\Facades\Date::parse((string)$item['registration_date']);
                        
                        if ($filters->registration_date_from && $regDate->lt(\Illuminate\Support\Carbon::parse($filters->registration_date_from)->startOfDay())) {
                            return null;
                        }
                        if ($filters->registration_date_to && $regDate->gt(\Illuminate\Support\Carbon::parse($filters->registration_date_to)->endOfDay())) {
                            return null;
                        }
                    } catch (\Exception) {
                        return null;
                    }
                }

                // Filtro por fecha de actuación
                if ($filters->action_date_from || $filters->action_date_to) {
                    if (!isset($item['action_date']) || empty($item['action_date'])) {
                        return null;
                    }
                    try {
                        $actDate = str_contains((string)$item['action_date'], '/')
                            ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', (string)$item['action_date'])
                            : \Illuminate\Support\Facades\Date::parse((string)$item['action_date']);
                        
                        if ($filters->action_date_from && $actDate->lt(\Illuminate\Support\Carbon::parse($filters->action_date_from)->startOfDay())) {
                            return null;
                        }
                        if ($filters->action_date_to && $actDate->gt(\Illuminate\Support\Carbon::parse($filters->action_date_to)->endOfDay())) {
                            return null;
                        }
                    } catch (\Exception) {
                        return null;
                    }
                }

                // Filtro por fecha de inicio de término
                if ($filters->term_start_date_from || $filters->term_start_date_to) {
                    $val = $item['term_start_date'] ?? $item['start_date'] ?? null;
                    if (!$val) {
                        return null;
                    }
                    try {
                        $tsDate = str_contains((string)$val, '/')
                            ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', (string)$val)
                            : \Illuminate\Support\Facades\Date::parse((string)$val);
                        
                        if ($filters->term_start_date_from && $tsDate->lt(\Illuminate\Support\Carbon::parse($filters->term_start_date_from)->startOfDay())) {
                            return null;
                        }
                        if ($filters->term_start_date_to && $tsDate->gt(\Illuminate\Support\Carbon::parse($filters->term_start_date_to)->endOfDay())) {
                            return null;
                        }
                    } catch (\Exception) {
                        return null;
                    }
                }

                // Filtro por fecha de fin de término
                if ($filters->term_end_date_from || $filters->term_end_date_to) {
                    $val = $item['term_end_date'] ?? $item['end_date'] ?? null;
                    if (!$val) {
                        return null;
                    }
                    try {
                        $teDate = str_contains((string)$val, '/')
                            ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', (string)$val)
                            : \Illuminate\Support\Facades\Date::parse((string)$val);
                        
                        if ($filters->term_end_date_from && $teDate->lt(\Illuminate\Support\Carbon::parse($filters->term_end_date_from)->startOfDay())) {
                            return null;
                        }
                        if ($filters->term_end_date_to && $teDate->gt(\Illuminate\Support\Carbon::parse($filters->term_end_date_to)->endOfDay())) {
                            return null;
                        }
                    } catch (\Exception) {
                        return null;
                    }
                }
            }
            // --- FIN LÓGICA DE FILTRADO ---

            // Guardar o parsear fecha original para ordenamiento posterior (sin formatear aún)
            $rawRegDate = $item['registration_date'] ?? null;
            $sortTimestamp = 0;
            
            if ($rawRegDate) {
                try {
                    $sortTimestamp = str_contains((string)$rawRegDate, '/')
                        ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', (string)$rawRegDate)->timestamp
                        : \Illuminate\Support\Facades\Date::parse((string)$rawRegDate)->timestamp;
                } catch (\Exception) {
                    $sortTimestamp = 0;
                }
            }
            $item['_sort_timestamp'] = $sortTimestamp;

            // Mapear llaves de español a inglés
            $mappings = [
                'demandante' => 'plaintiff',
                'demandado' => 'defendant',
                'start_date' => 'term_start_date',
                'end_date' => 'term_end_date',
            ];

            foreach ($mappings as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $item)) {
                    $item[$newKey] = $item[$oldKey];
                    unset($item[$oldKey]);
                }
            }

            // Recuperar alert_highlights usando el mapa de búsqueda (O(1))
            if (! isset($item['alert_highlights']) || empty($item['alert_highlights'])) {
                $lookupKey = ($item['process_number'] ?? '').'|'.($item['action_text'] ?? '');
                $matchedNotif = $notifLookup[$lookupKey] ?? null;

                if ($matchedNotif && $matchedNotif->notifiable->alertHighlights->isNotEmpty()) {
                    $item['alert_highlights'] = $matchedNotif->notifiable->alertHighlights
                        ->unique(fn($h) => "{$h->start}-{$h->end}-{$h->detected_text}-{$h->source}")
                        ->map(fn($h) => [
                            'start' => $h->start,
                            'end' => $h->end,
                            'text' => $h->detected_text,
                            'source' => $h->source,
                        ])->values()->toArray();
                } else {
                    $item['alert_highlights'] = $item['alert_highlights'] ?? [];
                }
            }

            // Limpiar duplicados de resaltado
            if (isset($item['alert_highlights']) && is_array($item['alert_highlights']) && ! empty($item['alert_highlights'])) {
                $item['alert_highlights'] = collect($item['alert_highlights'])
                    ->unique(fn($h) => ($h['start'] ?? '').'-'.($h['end'] ?? '').'-'.($h['text'] ?? '').'-'.($h['source'] ?? ''))
                    ->values()
                    ->toArray();
            }

            // Formatear sujetos
            $subjects = ['plaintiff' => 'plaintiffs', 'defendant' => 'defendants'];
            foreach ($subjects as $singular => $plural) {
                if (isset($item[$singular]) && is_string($item[$singular])) {
                    $list = array_filter(array_map('trim', explode(',', $item[$singular])));
                    if (!empty($list)) {
                        $list = array_map(fn($name) => StrParseHelper::toTitleCase($name), $list);
                        $item[$plural] = $list;
                        $count = count($list);
                        $item[$singular] = $count > 1 ? "{$list[0]} (+".($count - 1).")" : $list[0];
                    } else {
                        $item[$plural] = [];
                    }
                } else {
                    $item[$plural] = $item[$plural] ?? [];
                }
            }

            // Title Case para campos de texto
            foreach (['court', 'process_class', 'subclass_process'] as $field) {
                if (isset($item[$field]) && is_string($item[$field])) {
                    $item[$field] = StrParseHelper::toTitleCase($item[$field]);
                }
            }

            // Formatear fechas para el usuario final (legible)
            foreach (['registration_date', 'action_date', 'term_start_date', 'term_end_date'] as $field) {
                if (isset($item[$field]) && is_string($item[$field]) && ! empty($item[$field])) {
                    try {
                        $dateObj = str_contains($item[$field], '/')
                            ? \Illuminate\Support\Facades\Date::createFromFormat('d/m/Y', $item[$field])
                            : $item[$field];
                        $item[$field] = DateFormatHelper::formatDate($dateObj);
                    } catch (\Exception) {}
                }
            }

            return $item;
        }, $digest->data);

        // Limpiar nulos (actuaciones filtradas) y resetear índices
        $formattedData = array_values(array_filter($formattedData));

        // Ordenar por el timestamp pre-calculado (Descendente)
        usort($formattedData, fn($a, $b) => ($b['_sort_timestamp'] ?? 0) <=> ($a['_sort_timestamp'] ?? 0));

        // Limpiar metadatos internos antes de retornar
        $finalData = array_map(function($item) {
            unset($item['_sort_timestamp']);
            return $item;
        }, $formattedData);

        return new self(
            id: $digest->id,
            data: $finalData,
            created_at: DateFormatHelper::formatDate($digest->created_at),
            email_sent_at: $digest->email_sent_at ? DateFormatHelper::formatDate($digest->email_sent_at) : null,
            whatsapp_sent_at: $digest->whatsapp_sent_at ? DateFormatHelper::formatDate($digest->whatsapp_sent_at) : null,
            sms_sent_at: $digest->sms_sent_at ? DateFormatHelper::formatDate($digest->sms_sent_at) : null,
        );
    }
}
