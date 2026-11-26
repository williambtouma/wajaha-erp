<x-filament-panels::page>
    {{-- ===== الفلاتر ===== --}}
    <div class="wj-board-filters">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="بحث بالاسم أو الهاتف أو الرقم..." class="wj-input">

        <select wire:model.live="type" class="wj-input">
            <option value="">كل الأنواع</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        @if (count($agents))
            <select wire:model.live="agent" class="wj-input">
                <option value="">كل الوكلاء</option>
                @foreach ($agents as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        @endif

        <div wire:loading class="wj-loading">جاري التحديث…</div>
    </div>

    {{-- ===== الأعمدة ===== --}}
    <div class="wj-board">
        @foreach ($columns as $column)
            <div
                wire:key="col-{{ $column['key'] }}"
                class="wj-col {{ $column['closed'] ? 'wj-col-closed' : '' }}"
                x-data="{ over: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave="over = false"
                x-on:drop.prevent="over = false; $wire.moveLead(parseInt($event.dataTransfer.getData('lead')), '{{ $column['key'] }}')"
                x-bind:class="{ 'wj-col-over': over }"
            >
                <div class="wj-col-head wj-color-{{ $column['color'] }}">
                    <span class="wj-col-title">{{ $column['label'] }}</span>
                    <span class="wj-col-count">{{ $column['leads']->count() }}</span>
                </div>
                @if ($column['closed'])
                    <div class="wj-col-hint">آخر 30 يوماً</div>
                @endif

                <div class="wj-col-body">
                    @forelse ($column['leads'] as $lead)
                        @php
                            $overdue = ! $lead->isClosed() && $lead->next_follow_up_at?->isPast();
                        @endphp
                        <div
                            wire:key="lead-{{ $lead->id }}"
                            class="wj-card {{ $overdue ? 'wj-card-overdue' : '' }}"
                            draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('lead', '{{ $lead->id }}'); $el.classList.add('wj-dragging')"
                            x-on:dragend="$el.classList.remove('wj-dragging')"
                        >
                            <div class="wj-card-top">
                                <a href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('edit', ['record' => $lead]) }}" class="wj-card-name">
                                    {{ $lead->name }}
                                </a>
                                @if ($lead->priority)
                                    <x-filament::badge size="sm" :color="\App\Models\Lookup::color('lead_priority', $lead->priority)">
                                        {{ \App\Models\Lookup::label('lead_priority', $lead->priority) }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            <div class="wj-card-meta">
                                <x-filament::badge size="sm" :color="\App\Models\Lookup::color('lead_type', $lead->type)">
                                    {{ \App\Models\Lookup::label('lead_type', $lead->type) }}
                                </x-filament::badge>
                                <span class="wj-muted">{{ $lead->reference }}</span>
                            </div>

                            @if ($summary = $lead->interestSummary())
                                <div class="wj-card-summary">{{ $summary }}</div>
                            @endif

                            @if ($lead->stage === 'lost' && $lead->lost_reason)
                                <div class="wj-card-lost">{{ \App\Models\Lookup::label('lost_reason', $lead->lost_reason) }}</div>
                            @endif

                            <div class="wj-card-foot">
                                @if ($lead->next_follow_up_at && ! $lead->isClosed())
                                    <span class="{{ $overdue ? 'wj-danger' : 'wj-muted' }}" title="{{ $lead->next_follow_up_at->format('Y/m/d H:i') }}">
                                        ⏰ {{ $lead->next_follow_up_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span></span>
                                @endif

                                <span class="wj-card-actions">
                                    @if ($wa = $lead->whatsappUrl())
                                        <a href="{{ $wa }}" target="_blank" title="واتساب" class="wj-wa">
                                            <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="wj-icon" />
                                        </a>
                                    @endif
                                    @if ($lead->assignee)
                                        <span class="wj-avatar" title="{{ $lead->assignee->name }}">{{ mb_substr($lead->assignee->name, 0, 1) }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="wj-empty">اسحب عميلاً إلى هنا</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
