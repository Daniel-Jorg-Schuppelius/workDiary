{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bulk_confirm_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückfrage der Sammelaktion (Phase 152, MVP-1222): Sammelkontakt oder „Neu aus Belegdaten“, nach Gegenpartei gruppiert. --}}
<x-modal
    :title="$collective ? __('An den Sammelkontakt zuordnen?') : __('Parteien aus Belegdaten anlegen?')"
    icon="rule"
    :tone="$collective ? 'warning' : 'primary'"
    :action="route('finance.incoming-invoices.bulk-assign')"
    :submit-label="$collective ? __('An Sammelkontakt zuordnen') : __('Anlegen und zuordnen')"
>
    @foreach ($ids as $id)
        <input type="hidden" name="ids[]" value="{{ $id }}">
    @endforeach
    <input type="hidden" name="party" value="{{ $party }}">
    <input type="hidden" name="confirmed" value="1">

    <p class="text-sm">
        @if ($collective)
            {{ __('Ein Sammelkontakt ist für Belege ohne Stammsatz gedacht; der Name der Partei bleibt am Beleg. Diese Gruppen gehen an den Sammelkontakt:') }}
        @else
            {{ __('Je Gegenpartei entsteht ein Stammsatz aus dem ersten Beleg der Gruppe; gibt es schon eine Partei mit gleicher USt-IdNr. oder gleichem Namen, wird ihr zugeordnet:') }}
        @endif
    </p>
    @if ($groups === [])
        <x-empty-state compact icon="rule" :message="__('Keiner der gewählten Eingänge lässt sich noch zuordnen.')" />
    @endif
    <ul class="space-y-1 text-sm">
        @foreach ($groups as $group)
            <li>
                <strong>{{ $group['name'] ?? __('Ohne Namen') }}</strong>
                @if ($group['vat_id'])· {{ $group['vat_id'] }}@endif
                · {{ $group['direction']->label() }}
                · {{ trans_choice(':count Eingang|:count Eingänge', count($group['incomings']), ['count' => count($group['incomings'])]) }}
                @if (! $collective)
                    <span class="text-xs text-muted">· {{ $group['existing'] !== null ? __('wird „:name“ zugeordnet', ['name' => $group['existing']->name]) : __('wird angelegt') }}</span>
                @elseif ($group['named_contact'])
                    <span class="text-xs text-warning">· {{ __('braucht einen echten Firmenkontakt (Reverse Charge, EU, Drittland) und wird übersprungen') }}</span>
                @elseif ($group['vat_id'])
                    <span class="text-xs text-warning">· {{ __('nennt eine USt-IdNr. — besser „Neu aus Belegdaten“') }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</x-modal>
