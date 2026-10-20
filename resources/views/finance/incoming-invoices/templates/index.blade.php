{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Belegvorlagen des Rechnungseingangs (Phase 155, MVP-1253): Liste, Bearbeiten, Probelauf, Zusammenführen, Löschen. --}}
@extends('layouts.app')
@section('title', __('Belegvorlagen'))
@section('nav-title', __('Belegvorlagen'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" back-route="finance.incoming-invoices.index"
              :subtitle="__('Eine Vorlage erkennt die Belegform eines Lieferanten an Kopf und Fuß und liest Nummer, Daten und Beträge aus ihren Zonen. Zonen mit Anker finden ihren Wert neben der Beschriftung, auf welcher Seite er auch steht.')">
    <x-slot:actions>
        <x-icon-btn icon="rule" size="sm" :href="route('finance.incoming-invoices.rules')" show-label>{{ __('Regeln') }}</x-icon-btn>
    </x-slot:actions>
    <x-table scroll="flex" :pinRows="true" :zebra="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Vorlage') }}</th>
                <th>{{ __('Felder') }}</th>
                <th>{{ __('Quelle') }}</th>
                <th class="text-right">{{ __('Treffer') }}</th>
                <th class="text-right">{{ __('Eingänge') }}</th>
                <th>{{ __('Zuletzt getroffen') }}</th>
                <th>{{ __('Angelegt') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            @php($last = $lastIncoming[$template->id] ?? null)
            <tr class="hover">
                <td class="max-w-80">
                    <div class="truncate font-medium">{{ $template->name }}</div>
                    <div class="text-xs text-muted">{{ $template->supplier?->name ?? __('Kein Lieferant hinterlegt') }}</div>
                </td>
                <td class="max-w-96">
                    <div class="flex flex-wrap gap-1">
                        @forelse ($template->zoneList() as $zone)
                            @php($anchored = trim((string) ($zone['anchor'] ?? '')) !== '')
                            <x-status-badge :tone="$anchored ? 'info' : 'ghost'" :title="$anchored ? __('Anker: :anchor', ['anchor' => $zone['anchor']]) : __('Feste Lage auf Seite :page', ['page' => $zone['page'] ?? 1])">
                                {{ \App\Services\Invoicing\Recognition\ZoneFields::label((string) ($zone['field'] ?? '')) }}@if ($anchored) ⚓@endif
                                @if (($zone['source'] ?? '') === \App\Models\Invoicing\IncomingInvoiceTemplate::SOURCE_LEARNED && ! ($zone['confirmed'] ?? false)) ?@endif
                            </x-status-badge>
                        @empty
                            <span class="text-xs text-muted">{{ __('Keine Zonen') }}</span>
                        @endforelse
                    </div>
                </td>
                <td class="whitespace-nowrap text-sm">{{ $template->source === \App\Models\Invoicing\IncomingInvoiceTemplate::SOURCE_LEARNED ? __('gelernt') : __('gezeichnet') }}</td>
                <td class="whitespace-nowrap text-right tabular-nums text-sm">{{ $template->hits }}</td>
                <td class="whitespace-nowrap text-right tabular-nums text-sm">{{ $linked[$template->id] ?? 0 }}</td>
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $template->last_matched_at?->fdatetime() ?? '—' }}</td>
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $template->created_at?->fdatetime() }}</td>
                <td class="whitespace-nowrap text-right">
                    <x-icon-btn icon="edit" size="xs" :href="route('finance.incoming-invoices.templates.edit', $template)" data-entry-modal-trigger :label="__('Bearbeiten')" />
                    @if ($last !== null)
                        <x-icon-btn icon="crop_free" size="xs" :href="route('finance.incoming-invoices.template.edit', $last->document)" :label="__('Zonen-Editor öffnen')" />
                    @endif
                    <x-action-form :action="route('finance.incoming-invoices.templates.trial', $template)" class="inline">
                        <x-icon-btn icon="science" size="xs" type="submit" :label="__('Probelauf')" />
                    </x-action-form>
                    @if ($templates->total() > 1)
                        <x-icon-btn icon="merge" size="xs" :href="route('finance.incoming-invoices.templates.merge.form', $template)" data-entry-modal-trigger :label="__('Zusammenführen')" />
                    @endif
                    <x-action-form :action="route('finance.incoming-invoices.templates.destroy', $template)" method="DELETE" :confirm="__('Vorlage löschen? Die Eingänge bleiben, nur die Zuordnung zur Vorlage entfällt.')" class="inline">
                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('Vorlage löschen')" />
                    </x-action-form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="8" icon="crop_free" :title="__('Keine Belegvorlagen.')" :message="__('Vorlagen entstehen über „Vorlage“ auf einem PDF-Beleg oder beim Erfassen von Werten.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$templates" standing />
</x-index-page>
@endsection
