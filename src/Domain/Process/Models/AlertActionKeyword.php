<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Src\Domain\Shared\Traits\Uuid;

/**
 * Keyword type for alert filtering (Consulta, Apelación, etc.).
 * Table: alert_actions_keywords.
 * Direct relation with process_actions via pivot process_action_alert_action_keyword (for filtering).
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AlertActionKeyword query()
 */
class AlertActionKeyword extends Model
{
    use Uuid;

    protected $table = 'alert_actions_keywords';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Find the alert action keyword that best matches the detected fragment (e.g. from AI).
     * Used when syncing the pivot process_action_alert_action_keyword and to resolve alert_type in list detail.
     */
    public static function matchFragment(string $fragment): ?self
    {
        $fragment = trim($fragment);
        if ($fragment === '') {
            return null;
        }

        if (! Schema::hasTable('alert_actions_keywords')) {
            return null;
        }

        $norm = self::normalizeForMatch($fragment);

        // "Notificación estado" / "Notificacion esta" etc. se relacionan con Fijación Estado (mismo slug para filtrar).
        if (mb_strpos($norm, 'notificacion') !== false && (mb_strpos($norm, 'estado') !== false || mb_strpos($norm, 'esta') !== false)) {
            return self::query()->where('slug', 'fijacion_estado')->first();
        }

        $keywords = self::query()->orderByRaw('LENGTH(slug) DESC')->get();

        foreach ($keywords as $keyword) {
            $nameNorm = self::normalizeForMatch($keyword->name);
            $slug = $keyword->slug;

            if ($norm === $slug
                || mb_strpos($norm, $nameNorm) !== false
                || mb_strpos($nameNorm, $norm) !== false
                || mb_strpos($norm, str_replace('_', ' ', $slug)) !== false) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Normalize for matching: lowercase, remove accents, collapse spaces to single space.
     */
    private static function normalizeForMatch(string $s): string
    {
        $s = mb_strtolower($s);
        $s = Str::ascii($s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim((string) $s);
    }

    /**
     * Process actions that have this keyword (direct relation for filtering).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Process\Models\ProcessAction, $this>
     */
    public function processActions(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcessAction::class,
            'process_action_alert_action_keyword',
            'alert_action_keyword_id',
            'process_action_id'
        );
    }
}
