@props([
    'organization' => null,
])

@php
    /** @var \App\Models\Organization|null $organization */
    $show = $organization !== null && $organization->inMaintenance()
        && auth()->user() instanceof \App\Models\User && auth()->user()->isAdmin();
    $until = $show ? $organization->maintenanceSettings()['until'] : null;
@endphp

@if ($show)
    <div role="status"
         class="border-b border-warning/40 bg-warning/15 px-4 py-2 text-xs text-warning-content"
         data-maintenance-banner>
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center gap-2 font-medium">
                <x-icon name="engineering" class="text-base" />
                {{ __('Wartungsmodus aktiv — Nicht-Administratoren sehen derzeit eine Wartungsseite.') }}
            </span>
            <span class="inline-flex items-center gap-3">
                @if ($until instanceof \Carbon\CarbonInterface)
                    <span class="opacity-70">{{ __('Bis: :at', ['at' => $until->translatedFormat('d.m.Y H:i')]) }}</span>
                @endif
                @can('update', $organization)
                    <a href="{{ route('admin.organizations.edit', $organization) }}" class="link font-medium">
                        {{ __('Einstellungen') }}
                    </a>
                @endcan
            </span>
        </div>
    </div>
@endif
