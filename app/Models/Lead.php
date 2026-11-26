<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $guarded = ['id', 'reference'];

    protected $casts = [
        'details' => 'array',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'next_follow_up_at' => 'datetime',
        'stage_changed_at' => 'datetime',
    ];

    // قيم يعتمد عليها النظام
    public const BUYER = 'buyer';
    public const OWNER = 'owner';
    public const INVESTOR = 'investor';
    public const DEVELOPER = 'developer';

    public const CLOSED_STAGES = ['won', 'lost'];

    // ===== العلاقات =====

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function interestedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'interested_property_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('id');
    }

    // ===== نطاقات =====

    /** من لديه صلاحية "رؤية كل العملاء" يرى الجميع، وغيره يرى عملاءه فقط */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $user?->canSeeAllLeads() ? $query : $query->where('assigned_to', $user?->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', self::CLOSED_STAGES);
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay());
    }

    // ===== المرحلة =====

    public function isClosed(): bool
    {
        return in_array($this->stage, self::CLOSED_STAGES);
    }

    public function changeStage(string $stage, ?string $note = null, ?string $lostReason = null, $nextFollowUp = null): void
    {
        $from = Lookup::label('lead_stage', $this->stage);
        $to = Lookup::label('lead_stage', $stage);

        $this->forceFill([
            'stage' => $stage,
            'lost_reason' => $stage === 'lost' ? $lostReason : null,
            'stage_changed_at' => now(),
            'next_follow_up_at' => in_array($stage, self::CLOSED_STAGES) ? null : ($nextFollowUp ?? $this->next_follow_up_at),
        ])->save();

        $body = "{$from} ← {$to}";
        if ($stage === 'lost' && $lostReason) {
            $body .= ' (' . Lookup::label('lost_reason', $lostReason) . ')';
        }
        if (filled($note)) {
            $body .= "\n" . $note;
        }

        $this->activities()->create(['type' => 'stage_change', 'body' => $body]);
    }

    // ===== العرض =====

    /** ملخص ما يطلبه العميل: "فيلا للبيع - حلب - حتى $500,000" */
    public function interestSummary(): ?string
    {
        $budget = $this->budget_max ?: $this->budget_min;
        $symbol = Lookup::code('currency', $this->currency) ?? $this->currency;

        return collect([
            $this->subtype ? Lookup::label('subtype', $this->subtype, $this->category) : Lookup::label('category', $this->category),
            Lookup::label('transaction', $this->transaction),
            $this->area ?: $this->city,
            $budget ? ($this->type === self::BUYER ? 'حتى ' : '') . "\u{2066}" . ($symbol === '$' ? '$' . number_format((float) $budget) : number_format((float) $budget) . ' ' . $symbol) . "\u{2069}" : null,
        ])->filter()->implode(' - ') ?: null;
    }

    public function whatsappUrl(): ?string
    {
        $number = preg_replace('/\D+/', '', $this->phone2 ?: $this->phone);

        if (blank($number)) {
            return null;
        }
        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '963' . substr($number, 1); // رقم سوري محلي
        }

        return "https://wa.me/{$number}";
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->assigned_to ??= auth()->id();
            $lead->stage ??= 'new';
            $lead->stage_changed_at ??= now();
        });

        static::created(function (Lead $lead) {
            $lead->forceFill(['reference' => 'L-' . $lead->id])->saveQuietly();
            $lead->activities()->create(['type' => 'note', 'body' => 'تم إنشاء العميل']);
        });
    }
}
