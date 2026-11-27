<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Lookup;
use App\Models\Property;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/** خريطة العقارات: دبوس لكل عقار محدد موقعه */
class PropertiesMap extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'خريطة العقارات';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.properties-map';

    // ===== الفلاتر =====
    #[Url]
    public ?string $availability = null;

    #[Url]
    public ?string $category = null;

    #[Url]
    public ?string $transaction = null;

    #[Url]
    public ?string $city = null;

    /** العقار المطلوب تركيز الخريطة عليه (?property=12) */
    #[Url(as: 'property')]
    public ?int $focus = null;

    #[Locked]
    public array $markers = [];

    #[Locked]
    public int $withoutLocation = 0;

    public function getTitle(): string
    {
        return 'خريطة العقارات';
    }

    public function mount(): void
    {
        $this->loadMarkers();
    }

    public function updatedAvailability(): void
    {
        $this->loadMarkers();
    }

    public function updatedCategory(): void
    {
        $this->loadMarkers();
    }

    public function updatedTransaction(): void
    {
        $this->loadMarkers();
    }

    public function updatedCity(): void
    {
        $this->loadMarkers();
    }

    protected function filtered()
    {
        return Property::query()
            ->when($this->availability === 'available', fn ($q) => $q->where('is_available', true))
            ->when($this->availability === 'unavailable', fn ($q) => $q->where('is_available', false))
            ->when($this->category, fn ($q, $v) => $q->where('category', $v))
            ->when($this->transaction, fn ($q, $v) => $q->where('transaction', $v))
            ->when($this->city, fn ($q, $v) => $q->where('city', $v));
    }

    protected function loadMarkers(): void
    {
        $this->withoutLocation = $this->filtered()->whereNull('latitude')->count();

        $this->markers = $this->filtered()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'available' => (bool) $p->is_available,
                'popup' => $this->popupHtml($p),
            ])
            ->values()
            ->all();
    }

    /** بطاقة الدبوس */
    protected function popupHtml(Property $p): string
    {
        $image = $p->firstImageUrl();
        $url = PropertyResource::getUrl('edit', ['record' => $p]);

        $facts = collect([
            $p->built_area ? number_format((float) $p->built_area) . ' م²' : null,
            $p->bedrooms ? $p->bedrooms . ' غرف' : null,
            $p->bathrooms ? $p->bathrooms . ' حمام' : null,
        ])->filter()->implode(' · ');

        return '<div class="wj-pop">'
            . ($image ? '<img src="' . e($image) . '" alt="">' : '')
            . '<div class="wj-pop-body">'
            . '<div class="wj-pop-tags">'
            . '<span class="wj-tag ' . ($p->is_available ? 'wj-tag-ok' : 'wj-tag-no') . '">' . e($p->availabilityLabel()) . '</span>'
            . '<span class="wj-tag">' . e(Lookup::label('transaction', $p->transaction)) . '</span>'
            . '<span class="wj-tag">' . e($p->subtypeLabel()) . '</span>'
            . '</div>'
            . '<div class="wj-pop-title">' . e($p->title) . '</div>'
            . '<div class="wj-pop-loc">📍 ' . e(trim($p->city . ' - ' . $p->area, ' -')) . ' · ' . e($p->reference) . '</div>'
            . '<div class="wj-pop-price">' . e($p->formattedPrice()) . '</div>'
            . ($facts ? '<div class="wj-pop-facts">' . e($facts) . '</div>' : '')
            . '<a href="' . e($url) . '" class="wj-pop-link">عرض كل التفاصيل ←</a>'
            . '</div></div>';
    }

    protected function getViewData(): array
    {
        return [
            'categories' => Lookup::options('category'),
            'transactions' => Lookup::options('transaction'),
            'cities' => Lookup::options('city'),
            'withoutLocationUrl' => PropertyResource::getUrl('index') . '?' . http_build_query(['filters' => ['has_location' => ['value' => 0]]]),
        ];
    }
}
