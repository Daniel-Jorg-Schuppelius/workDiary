{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_visibility_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenakte-Panel „Portal-Sichtbarkeit" (MVP-511) — erwartet: $customer.
     Bereichsfreigabe + Zeit-Detailstufe; Bereichs- und Objektfreigabe wirken
     als doppeltes Gate (documents.customer_visible & Co. bleiben verbindlich). --}}

@php
    // use-Import bewusst VOR dem @can: innerhalb der if-Struktur wäre das
    // Statement in der kompilierten View ungültig (ParseError).
    use App\Enums\CustomerPortal\{PortalCapability, PortalTimeDetail};
    use App\Services\CustomerPortal\PortalVisibility;
@endphp
@can(\App\Enums\User\Permission::CustomerPortalVisibilityManage->value)
    @php
        $portalVisibility = app(PortalVisibility::class);
        $portalEnabled = $portalVisibility->enabled($customer);
        $availableCaps = $portalVisibility->availableCapabilities();
        $unavailableCaps = array_values(array_filter(
            PortalCapability::cases(),
            fn (PortalCapability $c): bool => ! $portalVisibility->capabilityAvailable($c),
        ));
        $grantedCaps = array_values(array_filter(
            PortalCapability::cases(),
            fn (PortalCapability $c): bool => $portalVisibility->allows($customer, $c),
        ));
        $timeDetail = $portalVisibility->timeDetail($customer);
        $timeScope = $portalVisibility->timeScope($customer);
    @endphp
    <x-card :title="__('Portal-Sichtbarkeit')" id="portal-visibility">
        <form method="POST" action="{{ route('customers.portal-visibility.update', $customer) }}" class="space-y-4">
            @csrf @method('PUT')

            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="toggle toggle-primary" @checked($portalEnabled)>
                <span class="font-medium">{{ __('Kundenportal aktiv') }}</span>
            </label>
            <p class="text-xs text-muted">
                {{ __('Ohne aktives Portal sehen Portalzugänge dieses Kunden einen erklärten Leerzustand. Neue Bereiche starten immer „aus" und müssen ausdrücklich freigegeben werden.') }}
            </p>

            <fieldset class="rounded-box border border-base-300 p-3">
                <legend class="px-1 text-xs font-semibold">{{ __('Freigegebene Bereiche') }}</legend>
                <div class="grid gap-1 sm:grid-cols-2">
                    @foreach ($availableCaps as $cap)
                        <label class="label cursor-pointer justify-start gap-2 py-1">
                            <input type="checkbox" name="capabilities[]" value="{{ $cap->value }}"
                                   class="checkbox checkbox-sm"
                                   @checked(in_array($cap, $grantedCaps, true))>
                            <span class="text-sm">{{ $cap->label() }}</span>
                        </label>
                    @endforeach
                    @foreach ($unavailableCaps as $cap)
                        <label class="label justify-start gap-2 py-1 opacity-50" title="{{ __('Modul nicht lizenziert') }}">
                            <input type="checkbox" class="checkbox checkbox-sm" disabled>
                            <span class="text-sm">{{ $cap->label() }} <span class="badge badge-xs badge-ghost">{{ __('nicht lizenziert') }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="fieldset">
                    <label class="fieldset-label" for="portal-time-detail">{{ __('Projektzeiten — Detailstufe') }}</label>
                    <select id="portal-time-detail" name="time_detail" class="select select-bordered select-sm w-full">
                        @foreach (PortalTimeDetail::cases() as $case)
                            <option value="{{ $case->value }}" @selected($timeDetail === $case)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="portal-time-scope">{{ __('Sichtbare Zeiten') }}</label>
                    <select id="portal-time-scope" name="time_scope" class="select select-bordered select-sm w-full">
                        <option value="published" @selected($timeScope === PortalVisibility::TIME_SCOPE_PUBLISHED)>{{ __('Nur veröffentlichte Einträge (empfohlen)') }}</option>
                        <option value="all" @selected($timeScope === PortalVisibility::TIME_SCOPE_ALL)>{{ __('Alle kundenbezogenen Zeiten (Kompatibilitätsoption)') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-muted">{{ __('Einzelne Zeiten veröffentlichst du über die Massenaktion im Projekt-Zeittab; Beschreibungen erscheinen nur für veröffentlichte Einträge.') }}</p>
                </div>
            </div>

            {{-- Vorschau: „Dieser Kunde sieht …" --}}
            <div class="rounded-box bg-base-200/50 px-4 py-3 text-sm">
                <span class="font-medium">{{ __('Dieser Kunde sieht:') }}</span>
                @if (! $portalEnabled || $grantedCaps === [])
                    <span class="text-muted">{{ __('nichts — das Portal zeigt einen erklärten Leerzustand.') }}</span>
                @else
                    <span>{{ collect($grantedCaps)->map(fn (PortalCapability $c): string => $c->label())->implode(', ') }}</span>
                    @if ($timeDetail !== PortalTimeDetail::None)
                        <span class="text-muted">· {{ __('Zeiten: :detail', ['detail' => $timeDetail->label()]) }}</span>
                    @endif
                @endif
            </div>

            <div class="flex justify-end">
                <x-button type="submit" tone="primary" icon="check"><span>{{ __('Speichern') }}</span></x-button>
            </div>
        </form>
    </x-card>
@endcan
