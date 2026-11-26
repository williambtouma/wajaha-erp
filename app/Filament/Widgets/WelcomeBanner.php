<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Property;
use Filament\Widgets\Widget;

/** بانر الترحيب أعلى لوحة التحكم */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    protected function getViewData(): array
    {
        $now = now('Asia/Damascus')->locale('ar');

        return [
            'greeting' => $now->hour < 12 ? 'صباح الخير' : 'مساء الخير',
            'name' => auth()->user()?->name,
            'date' => $now->translatedFormat('l، j F Y'),
            'available' => Property::where('is_available', true)->count(),
            'addedThisMonth' => Property::where('created_at', '>=', now()->startOfMonth())->count(),
            'createUrl' => PropertyResource::getUrl('create'),
            'availableUrl' => PropertyResource::getUrl('index', ['availability' => 'available']),
            'logo' => asset('images/logo.png'),
        ];
    }
}
