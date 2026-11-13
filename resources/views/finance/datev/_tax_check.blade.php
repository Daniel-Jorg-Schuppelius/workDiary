{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tax_check.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661): Befunde mit
  Sprung zur Buchung bzw. zum Beleg und — solange Fehler offen sind — die
  begründete Freigabe. Gemeinsam für die Journal-Übergabe und den
  belegbasierten Stapel.

  Erwartet: $check (DatevTaxCheckResult), $canRelease (bool),
  $releaseAction (URL), $releaseFields (verborgene Felder des Formulars).
--}}
@php
    /** @var \App\Services\Finance\Datev\TaxCheck\DatevTaxCheckResult $check */
    $releaseFields ??= [];
    $openErrors = $check->openErrors();
@endphp
<x-card :title="__('accounting.datev_check.title')" icon="fact_check" :subtitle="__('accounting.datev_check.hint')">
    <x-slot:actions>
        <x-status-badge :tone="$openErrors === [] ? 'success' : 'error'">
            {{ __('accounting.datev_check.counts', ['errors' => count($check->errors()), 'hints' => count($check->hints())]) }}
        </x-status-badge>
    </x-slot:actions>

    @if ($check->isEmpty())
        <p class="text-sm text-success">{{ __('accounting.datev_check.clean') }}</p>
    @else
        @if ($openErrors !== [])
            <div role="alert" class="alert alert-error text-sm mb-3">
                {{ __('accounting.datev_check.blocked', ['count' => count($openErrors)]) }}
            </div>
        @endif

        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.datev_check.column.severity') }}</th>
                    <th>{{ __('accounting.datev_check.column.finding') }}</th>
                    <th>{{ __('accounting.datev_check.column.reference') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($check->findings as $finding)
                <tr class="hover">
                    <td class="whitespace-nowrap">
                        <x-status-badge :tone="$finding->blocking ? 'error' : 'warning'">
                            {{ __('accounting.datev_check.severity.' . ($finding->blocking ? 'error' : 'hint')) }}
                        </x-status-badge>
                        @if ($check->isReleased($finding))
                            <x-status-badge tone="neutral" outline>{{ __('accounting.datev_check.released_badge') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-sm">
                        <div class="font-medium">{{ $finding->kind->label() }}</div>
                        <div class="text-base-content/70">{{ $finding->message }}</div>
                    </td>
                    <td class="text-sm whitespace-nowrap">
                        @if ($finding->url)
                            <a href="{{ $finding->url }}" class="link link-primary">{{ $finding->reference }}</a>
                        @else
                            {{ $finding->reference }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        @if ($openErrors !== [])
            <div class="mt-4 border-t border-base-200 pt-3">
                <div class="font-medium text-sm">{{ __('accounting.datev_check.release.title') }}</div>
                <p class="text-xs text-muted mb-2">{{ __('accounting.datev_check.release.hint') }}</p>
                @if ($canRelease)
                    <form method="POST" action="{{ $releaseAction }}" class="grid gap-2">
                        @csrf
                        @foreach ($releaseFields as $field => $value)
                            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                        @endforeach
                        <x-textarea-field name="reason" required minlength="10" maxlength="500" rows="2"
                                          :label="__('accounting.datev_check.release.reason')" :value="old('reason')" />
                        <div class="flex justify-end">
                            <x-button type="submit" tone="warning" size="sm">{{ __('accounting.datev_check.action.release') }}</x-button>
                        </div>
                    </form>
                @else
                    <p class="text-xs text-muted">{{ __('accounting.datev_check.release.no_permission') }}</p>
                @endif
            </div>
        @endif
    @endif
</x-card>
