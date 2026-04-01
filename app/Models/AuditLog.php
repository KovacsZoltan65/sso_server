<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string|null $log_name
 * @property string|null $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property array<array-key, mixed>|null $properties
 * @property string|null $batch_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $subject
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $causer
 * @mixin \Eloquent
 */
class AuditLog extends Activity
{
    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
