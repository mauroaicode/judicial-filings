<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Src\Domain\Shared\Traits\Uuid;

/**
 * Email address that already passed notification-channel confirmation.
 * Kept even after the channel row changes, so the same address is not asked again.
 *
 * @property-read string $id
 * @property-read string $email
 * @property-read Carbon $validated_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class ValidatedNotificationEmail extends Model
{
    use Uuid;

    protected $table = 'validated_notification_emails';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'validated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public static function remember(string $email, Carbon|string|null $validatedAt = null): void
    {
        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        static::query()->updateOrCreate(
            ['email' => $email],
            ['validated_at' => $validatedAt ?? now()],
        );
    }

    public static function contains(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        return static::query()->where('email', $email)->exists();
    }
}
