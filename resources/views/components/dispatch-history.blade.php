{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dispatch-history.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Versandhistorie je Beleg (MVP-876) aus dem Zustellnachweis-Log
  document_dispatches: Zeitpunkt, Belegart, Kanal, Empfänger, Status.
  `bare` rendert nur die Tabelle (in einer bestehenden Karte).
--}}
@props([
    'kinds',                  // list<\App\Enums\DocumentDesign\RenderDocumentKind>
    'ids',                    // list<int> Beleg-IDs
    'labels' => [],           // array<int, string> Beschriftung je Beleg-ID (mehrere Belege)
    'bare' => false,
    'empty' => null,          // eigener Leertext (z. B. Zugangsnachweis bei VOB-Schreiben)
])

@php
    $kindValues = array_map(static fn (\App\Enums\DocumentDesign\RenderDocumentKind $kind): string => $kind->value, $kinds);
    $dispatches = $ids === [] ? collect() : \App\Models\Document\DocumentDispatch::query()
        ->whereIn('document_kind', $kindValues)
        ->whereIn('document_id', $ids)
        ->orderByDesc('created_at')->orderByDesc('id')
        ->limit(50)
        ->get();
    $showKind = count($kinds) > 1;
    $showLabel = $labels !== [];
    $colspan = 4 + ($showKind ? 1 : 0) + ($showLabel ? 1 : 0);
@endphp

@unless ($bare)
<x-card :title="__('document.dispatch.history')" icon="outgoing_mail" :count="$dispatches->count()">
@endunless
    <x-table bare size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('document.dispatch.at') }}</th>
                @if ($showLabel)<th>{{ __('document.dispatch.document') }}</th>@endif
                @if ($showKind)<th>{{ __('document.dispatch.kind') }}</th>@endif
                <th>{{ __('document.dispatch.channel') }}</th>
                <th>{{ __('document.dispatch.recipient') }}</th>
                <th>{{ __('document.dispatch.status') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($dispatches as $dispatch)
            <tr>
                <td class="tabular-nums whitespace-nowrap">{{ $dispatch->created_at?->fdatetime() }}</td>
                @if ($showLabel)<td>{{ $labels[$dispatch->document_id] ?? '—' }}</td>@endif
                @if ($showKind)<td>{{ \App\Enums\DocumentDesign\RenderDocumentKind::tryFrom((string) $dispatch->document_kind)?->label() ?? '—' }}</td>@endif
                <td>{{ __('values.' . $dispatch->channel) }}</td>
                <td class="break-all">{{ $dispatch->recipient ?? '—' }}</td>
                <td><x-status-badge :tone="$dispatch->status === 'sent' ? 'success' : ($dispatch->status === 'failed' ? 'error' : 'ghost')" size="sm">{{ __('values.' . $dispatch->status) }}</x-status-badge></td>
            </tr>
        @empty
            <x-table.empty :colspan="$colspan" :title="$empty ?? __('document.dispatch.none')" compact />
        @endforelse
    </x-table>
@unless ($bare)
</x-card>
@endunless
