<x-filament-widgets::widget>
    <div style="position: relative; overflow: hidden; border-radius: 1rem; padding: 1.75rem 2rem;
                background: linear-gradient(135deg, #043c36 0%, #0a5a50 60%, #37655e 100%); color: #f0ebe1;">

        {{-- زخرفة ذهبية خفيفة --}}
        <div style="position: absolute; inset-inline-start: -60px; top: -60px; width: 220px; height: 220px;
                    border-radius: 9999px; border: 28px solid rgba(167, 149, 112, 0.18);"></div>
        <div style="position: absolute; inset-inline-start: 90px; bottom: -80px; width: 160px; height: 160px;
                    border-radius: 9999px; background: rgba(167, 149, 112, 0.12);"></div>

        <div style="position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <div style="background: #fff; border-radius: 0.9rem; padding: 0.5rem 0.75rem; box-shadow: 0 4px 14px rgba(0,0,0,.15);">
                    <img src="{{ $logo }}" alt="وجاهة" style="height: 3.5rem; width: auto; display: block;">
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #fff;">
                        {{ $greeting }}{{ $name ? '، ' . $name : '' }} 👋
                    </div>
                    <div style="margin-top: .25rem; color: #c4b596; font-size: .95rem;">
                        {{ $date }} — للمكان قيمة .. وللاختيار وجاهة
                    </div>
                    <div style="margin-top: .6rem; font-size: .9rem; color: #f0ebe1;">
                        لديك <strong style="color:#fff">{{ number_format($available) }}</strong> عقار متاح،
                        وأُضيف <strong style="color:#fff">{{ number_format($addedThisMonth) }}</strong> عقار هذا الشهر.
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
                <a href="{{ $createUrl }}"
                   style="display: inline-flex; align-items: center; gap: .4rem; background: #a79570; color: #fff;
                          padding: .65rem 1.2rem; border-radius: .7rem; font-weight: 600; font-size: .9rem; text-decoration: none;">
                    <x-filament::icon icon="heroicon-m-plus" style="width: 1.1rem; height: 1.1rem;" />
                    إضافة عقار
                </a>
                <a href="{{ $availableUrl }}"
                   style="display: inline-flex; align-items: center; gap: .4rem; background: rgba(240,235,225,.12); color: #f0ebe1;
                          border: 1px solid rgba(240,235,225,.35); padding: .65rem 1.2rem; border-radius: .7rem;
                          font-weight: 600; font-size: .9rem; text-decoration: none;">
                    <x-filament::icon icon="heroicon-m-check-circle" style="width: 1.1rem; height: 1.1rem;" />
                    العقارات المتاحة
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
