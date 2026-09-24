{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : journal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'entries',
    'emptyText'   => null,
    'payloadKeys' => ['note', 'reason', 'failure_reason'],
    'icon'        => 'history',
    'newestFirst' => false,
])

{{--
    <x-journal :entries="$model->journal"> — Verlauf eines Journal-Trägers (MVP-864).

    Zeigt je Eintrag Zeitpunkt (Org-Zeitzone), Label des Ereignisses
    (JournalEntry::label()), Akteur (oder „System") und eine Kurzform der
    Payload: die ersten Treffer aus `payloadKeys` sowie die Zusatzspalte
    `note`. `$slot` rendert nach jedem Eintrag mit `$entry` als Variable —
    für modul-eigene Zeilendetails.
--}}
@php
    $list = collect($entries);
    if ($newestFirst) {
        $list = $list->reverse()->values();
    }
@endphp
@if ($list->isEmpty())
    <p class="text-sm text-muted">{{ $emptyText ?? __('Noch keine Ereignisse.') }}</p>
@else
    <ul {{ $attributes->merge(['class' => 'timeline timeline-vertical timeline-compact']) }}>
        @foreach ($list as $entry)
            @php
                $payload = $entry->payloadData();
                $extras = [];
                foreach ($payloadKeys as $key) {
                    $value = data_get($payload, $key);
                    if (is_scalar($value) && (string) $value !== '') {
                        $extras[] = (string) $value;
                    }
                }
                $note = $entry->getAttribute('note');
                if (is_string($note) && $note !== '') {
                    $extras[] = $note;
                }
            @endphp
            <li>
                <div class="timeline-start text-xs tabular-nums text-muted">{{ $entry->occurredAt()?->fdatetime() }}</div>
                <div class="timeline-middle"><x-icon :name="$icon" class="text-base" /></div>
                <div class="timeline-end timeline-box text-sm">
                    <div class="font-medium">{{ $entry->label() }}</div>
                    <div class="text-xs text-muted">{{ $entry->actor?->name ?? __('System') }}</div>
                    @foreach ($extras as $extra)
                        <div class="mt-1 text-xs">{{ $extra }}</div>
                    @endforeach
                    {{ $slot }}
                </div>
                @if (! $loop->last)
                    <hr />
                @endif
            </li>
        @endforeach
    </ul>
@endif
