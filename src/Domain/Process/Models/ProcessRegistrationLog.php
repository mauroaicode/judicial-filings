<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Src\Domain\Shared\Traits\Uuid;

class ProcessRegistrationLog extends Model
{
    use Uuid;

    protected $table = 'process_registration_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'app_user_id',
        'process_number',
        'status',
        'error',
    ];
}
