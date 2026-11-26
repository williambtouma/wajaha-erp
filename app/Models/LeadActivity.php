<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (LeadActivity $activity) {
            $activity->user_id ??= auth()->id();
        });

        // موعد جديد (اجتماع / معاينة) يصبح موعد المتابعة القادم للعميل
        static::created(function (LeadActivity $activity) {
            if ($activity->scheduled_at?->isFuture()) {
                $activity->lead()->update(['next_follow_up_at' => $activity->scheduled_at]);
            }
        });
    }
}
