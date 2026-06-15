{{--
    Gemeinsame Tab-Leiste „Dienstpläne ↔ Verfügbarkeit & Wunschdienste"
    (zusammengelegt). Aktiver Tab über routeIs. Der Verfügbarkeits-Tab wird
    nur bei entsprechender Berechtigung gezeigt (AvailabilityWindow::viewAny).
--}}
<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('duty-plans.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('duty-plans.*')])
       @if (request()->routeIs('duty-plans.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">event_available</span>
        {{ __('Dienstpläne') }}
    </a>
    @can('viewAny', \App\Models\AvailabilityWindow::class)
        <a role="tab"
           href="{{ route('schedule.availability.index') }}"
           @class(['tab gap-1', 'tab-active' => request()->routeIs('schedule.availability.*')])
           @if (request()->routeIs('schedule.availability.*')) aria-current="page" @endif>
            <span class="material-symbols-outlined text-base" aria-hidden="true">fact_check</span>
            {{ __('schedule.availability.title') }}
        </a>
    @endcan
</div>
