{{--
    Gemeinsame Tab-Leiste „Schichtplan ↔ Schichttausch" (zusammengelegt).
    Aktiver Tab über routeIs. Der Schichttausch-Tab wird nur bei entsprechender
    Berechtigung gezeigt (ShiftExchange::viewAny).
--}}
<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('schedule.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('schedule.index') || request()->routeIs('schedule.shifts.*') || request()->routeIs('schedule.types.*') || request()->routeIs('schedule.import.*')])
       @if (request()->routeIs('schedule.index')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">schedule</span>
        {{ __('Schichtplan') }}
    </a>
    @can('viewAny', \App\Models\ShiftExchange::class)
        <a role="tab"
           href="{{ route('schedule.exchanges.index') }}"
           @class(['tab gap-1', 'tab-active' => request()->routeIs('schedule.exchanges.*')])
           @if (request()->routeIs('schedule.exchanges.*')) aria-current="page" @endif>
            <span class="material-symbols-outlined text-base" aria-hidden="true">swap_horiz</span>
            {{ __('schedule.exchange.title') }}
        </a>
    @endcan
</div>
