@php
    // مسار حقلي الإحداثيات بجانب هذا الحقل (data.latitude / data.longitude)
    $base = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
@endphp

<div
    x-data="wajahaMapPicker({
        lat: $wire.$entangle('{{ $base }}.latitude', true),
        lng: $wire.$entangle('{{ $base }}.longitude', true),
    })"
    wire:ignore
    class="wj-picker"
>
    <div class="wj-picker-bar">
        <div class="wj-picker-search">
            <input type="text" x-model="query" x-on:keydown.enter.prevent="search()" placeholder="ابحث عن منطقة أو شارع… (مثال: الجميلية حلب)" class="wj-input">
            <button type="button" x-on:click="search()" class="wj-btn">
                <span x-show="!searching">بحث</span>
                <span x-show="searching">…</span>
            </button>
            <div class="wj-picker-results" x-show="results.length" x-on:click.outside="results = []" x-cloak>
                <template x-for="r in results" :key="r.place_id">
                    <button type="button" x-on:click="choose(r)" x-text="r.display_name"></button>
                </template>
            </div>
        </div>
        <button type="button" x-on:click="locateMe()" class="wj-btn wj-btn-light" title="موقعي الحالي">📍 موقعي</button>
        <button type="button" x-on:click="clear()" class="wj-btn wj-btn-light" x-show="lat && lng">✕ إزالة</button>
    </div>

    <div x-ref="map" class="wj-map wj-map-picker"></div>

    <div class="wj-picker-hint">
        اكبس على الخريطة لتحديد موقع العقار، أو اسحب الدبوس لتعديله.
        <span x-show="lat && lng" x-text="'(' + lat + ', ' + lng + ')'" dir="ltr"></span>
    </div>
</div>
