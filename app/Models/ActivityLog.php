<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Activity
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->ip_address = request()->ip();
            $model->user_agent = request()->userAgent();
        });
    }

    public function scopeDateBegin(Builder $query, $value): Builder
    {
        return $query->where('activity_log.created_at', '>=', $value.' 00:00:00');
    }

    public function scopeDateEnd(Builder $query, $value): Builder
    {
        return $query->where('activity_log.created_at', '<=', $value.' 23:59:59');
    }
}
