<x-filament-panels::page>
    {{-- ===== الفلاتر ===== --}}
    <div class="wj-board-filters">
        <select wire:model.live="availability" class="wj-input">
            <option value="">كل العقارات</option>
            <option value="available">المتاحة فقط</option>
            <option value="unavailable">غير المتاحة فقط</option>
        </select>

        <select wire:model.live="category" class="wj-input">
            <option value="">كل الأنواع</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        <select wire:model.live="transaction" class="wj-input">
            <option value="">بيع وإيجار</option>
            @foreach ($transactions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        <select wire:model.live="city" class="wj-input">
            <option value="">كل المدن</option>
            @foreach ($cities as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        <div class="wj-map-legend">
            <span><i class="wj-dot wj-dot-ok"></i> متاح</span>
            <span><i class="wj-dot wj-dot-no"></i> غير متاح</span>
            <span class="wj-muted">{{ count($this->markers) }} عقار على الخريطة</span>
            <span wire:loading class="wj-loading">جاري التحديث…</span>
        </div>
    </div>

    @if ($this->withoutLocation)
        <div class="wj-map-note">
            ⚠️ يوجد <strong>{{ $this->withoutLocation }}</strong> عقار بدون موقع محدد، فلا يظهر على الخريطة.
            حدّد موقعه من صفحة العقار ← قسم "الموقع على الخريطة".
        </div>
    @endif

    <div
        wire:ignore
        x-data="wajahaPropertiesMap({ focus: @js($this->focus) })"
        class="wj-map-card"
    >
        <div x-ref="map" class="wj-map wj-map-full"></div>
    </div>
</x-filament-panels::page>
