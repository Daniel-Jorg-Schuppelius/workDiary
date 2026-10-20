{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Rechnungseingang (Feature 163, MVP-1110/1111): Arbeitsliste nach Bearbeitungsstand.
     Zuzuordnen, Zu prüfen und offene Übergaben vollständig, „Alle“ im globalen Zeitraum. --}}

@extends('layouts.app')

@section('title', __('Rechnungseingang'))
@section('nav-title', __('Rechnungseingang'))
@include('partials.page-fill')

@php
    $queryBase = array_filter([
        'direction' => request('direction'),
        'recognition' => request('recognition'),
        'status' => request('status'),
        'q' => request('q'),
    ], static fn ($value) => $value !== null && $value !== '');
    $tabItems = [
        ['label' => __('Zuzuordnen'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'assign'] + $queryBase, 'active' => $tab === 'assign', 'count' => $assignCount, 'icon' => 'rule'],
        ['label' => __('Zu prüfen'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'review'] + $queryBase, 'active' => $tab === 'review', 'count' => $reviewCount, 'icon' => 'fact_check'],
        ...($transferCount > 0 || $tab === 'transfer' ? [['label' => __('Übergabe offen'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'transfer'] + $queryBase, 'active' => $tab === 'transfer', 'count' => $transferCount, 'icon' => 'outbox']] : []),
        ...($failedCount > 0 || $tab === 'failed' ? [['label' => __('Übergabe fehlgeschlagen'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'failed'] + $queryBase, 'active' => $tab === 'failed', 'count' => $failedCount, 'icon' => 'sync_problem']] : []),
        ...($duplicateCount > 0 || $tab === 'duplicates' ? [['label' => __('Möglicher Doppel-Eingang'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'duplicates'] + $queryBase, 'active' => $tab === 'duplicates', 'count' => $duplicateCount, 'icon' => 'content_copy']] : []),
        ...($rejectedCount > 0 || $tab === 'rejected' ? [['label' => __('Abgelehnt'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'rejected'] + $queryBase, 'active' => $tab === 'rejected', 'count' => $rejectedCount, 'icon' => 'block']] : []),
        ['label' => __('Alle'), 'route' => 'finance.incoming-invoices.index', 'params' => ['tab' => 'all'] + $queryBase, 'active' => $tab === 'all', 'icon' => 'list'],
    ];
    $bulk = ($bulkParties !== null || $bulkDiscard) && $incomings->isNotEmpty();
    $colspan = $bulk ? 9 : 8;
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Rechnungen aus Postfach, Upload, Peppol und Cloud: zuordnen, prüfen, an die Buchhaltung übergeben.')">
    <x-slot:actions>
        @if ($canUpload)
            <form method="POST" action="{{ route('finance.incoming-invoices.store') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                {{-- MVP-1066: auch PDF ohne E-Rechnungsdaten und Fotos, mehrere Dateien auf einmal. --}}
                <input type="file" name="files[]" multiple accept=".xml,.pdf,.jpg,.jpeg,.png,.tif,.tiff,application/xml,text/xml,application/pdf,image/jpeg,image/png,image/tiff"
                       class="file-input file-input-bordered file-input-sm max-w-64" required
                       aria-label="{{ __('Rechnungen (XML, PDF oder Foto)') }}">
                <x-icon-btn icon="upload_file" tone="primary" size="sm" type="submit"
                            show-label>{{ __('Rechnungen hochladen') }}</x-icon-btn>
            </form>
        @endif
        @if ($canManage)
            <x-icon-btn icon="rule_settings" size="sm" placement="menu" :href="route('finance.incoming-invoices.rules')" show-label>{{ __('Regeln') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-tab-nav :items="$tabItems" />

    <x-filter-bar :action="route('finance.incoming-invoices.index')" :reset="route('finance.incoming-invoices.index', ['tab' => $tab])">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input input-sm input-bordered w-56 shrink-0"
               placeholder="{{ __('Nummer, Partei oder Absender') }}" aria-label="{{ __('Nummer, Partei oder Absender') }}">
        <select name="direction" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Richtung') }}">
            <option value="">{{ __('Alle Richtungen') }}</option>
            @foreach ([\App\Enums\Billing\DocumentDirection::Incoming, \App\Enums\Billing\DocumentDirection::Outgoing] as $d)
                <option value="{{ $d->value }}" @selected(request('direction') === $d->value)>{{ $d->label() }}</option>
            @endforeach
        </select>
        <select name="recognition" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Erkennung') }}">
            <option value="">{{ __('Jede Erkennung') }}</option>
            @foreach (\App\Enums\Invoicing\IncomingInvoiceRecognition::cases() as $r)
                <option value="{{ $r->value }}" @selected(request('recognition') === $r->value)>{{ $r->label() }}</option>
            @endforeach
        </select>
        @if ($tab === 'all')
            <select name="status" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Jeder Status') }}</option>
                @foreach (\App\Enums\Invoicing\IncomingEInvoiceStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        @endif
    </x-filter-bar>

    @if ($bulk && $bulkDiscard)
        {{-- Abgelehnt (MVP-1212): endgültig löschen — Beleg, Dokument, Dateien; es bleibt der Audit-Eintrag. --}}
        <form method="POST" action="{{ route('finance.incoming-invoices.bulk-discard') }}" data-bulk-form class="contents"
              data-confirm-dialog data-confirm-message="{{ __('Ausgewählte Eingänge endgültig löschen? Beleg, Dokument und Dateien werden entfernt; es bleibt der Audit-Eintrag.') }}"
              data-confirm-tone="error" data-confirm-icon="delete_forever" data-confirm-label="{{ __('Endgültig löschen') }}">
            @csrf
            <x-bulk-toolbar :label="__(':n Eingänge ausgewählt')" tone="error">
                <x-slot:actions>
                    <x-button type="submit" tone="error" size="sm" icon="delete_forever">{{ __('Endgültig löschen') }}</x-button>
                </x-slot:actions>
            </x-bulk-toolbar>
    @elseif ($bulk)
        <form method="POST" action="{{ route('finance.incoming-invoices.bulk-assign') }}" data-bulk-form class="contents">
            @csrf
            <x-bulk-toolbar :label="__(':n Eingänge ausgewählt')">
                <x-slot:actions>
                    {{-- MVP-1222: kein vorausgewählter Sammelkontakt; Sammelkontakt und „Neu“ fragen mit den Gruppen der Auswahl zurück. --}}
                    <select name="party" class="select select-sm select-bordered w-64" aria-label="{{ __('Zuordnen zu') }}" required>
                        <option value="" selected disabled>{{ __('Partei wählen …') }}</option>
                        <option value="new">{{ __('Neu aus Belegdaten (je Gegenpartei)') }}</option>
                        <optgroup label="{{ __('Lieferanten') }}">
                            @foreach ($bulkParties['suppliers'] as $supplier)
                                <option value="supplier:{{ $supplier->sqid }}">{{ $supplier->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="{{ __('Kunden') }}">
                            @foreach ($bulkParties['customers'] as $customer)
                                <option value="customer:{{ $customer->sqid }}">{{ $customer->name }}</option>
                            @endforeach
                        </optgroup>
                        <option value="unassign">{{ __('Zuordnung aufheben') }}</option>
                        <option value="collective">{{ __('Sammelkontakt (mit Rückfrage)') }}</option>
                    </select>
                    <x-button type="submit" tone="primary" size="sm" icon="rule">{{ __('Zuordnen') }}</x-button>
                    {{-- Nacherkennung (MVP-1207): Klärfälle und aus PDF bzw. Bild gelesene Eingänge ohne erfasste Werte, Übergabe oder Ablehnung werden neu gelesen.
                         formnovalidate: das Pflichtfeld „Partei“ gehört nur zum Zuordnen, sonst blockt der Browser den Knopf (Befund 2026-10-20). --}}
                    <x-button type="submit" size="sm" icon="document_scanner" formaction="{{ route('finance.incoming-invoices.bulk-reanalyze') }}" formnovalidate>{{ __('Erneut erkennen') }}</x-button>
                    {{-- Neuvalidierung (MVP-1248): Schema- und KoSIT-Prüfung strukturierter E-Rechnungen am Original wiederholen; Werte und Zuordnung bleiben. --}}
                    <x-button type="submit" size="sm" icon="verified" formaction="{{ route('finance.incoming-invoices.bulk-revalidate') }}" formnovalidate>{{ __('Erneut prüfen') }}</x-button>
                    @if ($tab !== 'assign')
                        {{-- Sammelfreigabe (MVP-1249): nur Eingänge mit Partei, zulässigem Übergang und ohne Hindernis am Übergabe-Tor; der Rest bleibt mit Grund stehen. --}}
                        <x-button type="submit" tone="success" size="sm" icon="gavel" formaction="{{ route('finance.incoming-invoices.bulk-approve') }}" formnovalidate
                                  data-confirm-dialog data-confirm-message="{{ __('Ausgewählte Eingänge fachlich freigeben? Eingänge mit Hindernissen am Übergabe-Tor bleiben stehen.') }}"
                                  data-confirm-icon="gavel" data-confirm-label="{{ __('Fachlich freigeben') }}">{{ __('Fachlich freigeben') }}</x-button>
                    @endif
                </x-slot:actions>
            </x-bulk-toolbar>
    @endif

    <x-table scroll="flex" :pinRows="true" :zebra="true"
             table-sort="server"
             :route="route('finance.incoming-invoices.index')"
             :current-sort="$sort"
             :current-dir="$dir"
             :sort-params="['tab' => $tab] + $queryBase">
        <x-slot:head>
            <tr>
                @if ($bulk)
                    <th class="w-8"><input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all aria-label="{{ __('Alle auswählen') }}"></th>
                @endif
                <x-table.th sort="received_at" default>{{ __('Eingang') }}</x-table.th>
                <th>{{ __('Partei') }}</th>
                <x-table.th sort="number">{{ __('Nummer') }}</x-table.th>
                <x-table.th sort="issue_date">{{ __('Rechnungsdatum') }}</x-table.th>
                <x-table.th sort="amount" align="right">{{ __('Brutto') }}</x-table.th>
                <th>{{ __('Erkennung') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($incomings as $incoming)
            @php($party = $incoming->counterparty())
            @php($isOutgoing = $incoming->direction === \App\Enums\Billing\DocumentDirection::Outgoing)
            <tr>
                @if ($bulk)
                    <td>
                        @if ($bulkDiscard || ($incoming->transferred_at === null && $incoming->status !== \App\Enums\Invoicing\IncomingEInvoiceStatus::Rejected))
                            <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="ids[]" value="{{ $incoming->sqid }}" aria-label="{{ __('Auswählen') }}">
                        @endif
                    </td>
                @endif
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $incoming->received_at?->fdatetime() }}</td>
                <td class="max-w-72">
                    @if ($party !== null)
                        <span class="truncate">{{ $party->name }}</span>
                        @if ($party->is_collective)
                            <span class="text-xs text-muted">· {{ $isOutgoing ? $incoming->buyer_name : $incoming->seller_name }}</span>
                        @endif
                    @else
                        <span class="truncate">{{ ($isOutgoing ? $incoming->buyer_name : $incoming->seller_name) ?? $incoming->sender_email ?? '—' }}</span>
                        <x-status-badge tone="warning">{{ __('Noch nicht zugeordnet') }}</x-status-badge>
                    @endif
                    @if ($isOutgoing)
                        <x-status-badge tone="ghost">{{ $incoming->direction->label() }}</x-status-badge>
                    @endif
                </td>
                <td class="font-mono text-sm">
                    @if ($incoming->document !== null)
                        <a class="link link-hover" href="{{ route('finance.incoming-invoices.show', $incoming->document) }}">{{ $incoming->invoice_number ?? '—' }}</a>
                    @else
                        {{ $incoming->invoice_number ?? '—' }}
                    @endif
                </td>
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $incoming->issue_date?->fdate() ?? '—' }}</td>
                <td class="whitespace-nowrap text-right tabular-nums text-sm">
                    @if ($incoming->amount_gross !== null){{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($incoming->amount_gross->toFloat(), 2, withThousandsSeparator: true) }} {{ $incoming->currency?->value }}@else—@endif
                </td>
                <td>
                    <x-status-badge :tone="$incoming->recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::None ? 'warning' : 'ghost'">{{ $incoming->recognition->label() }}</x-status-badge>
                    @if (($hintLabel = $kindHints->label($incoming->kindHint())) !== null)
                        <x-status-badge tone="ghost" :title="__('Sieht aus wie')">{{ $hintLabel }}</x-status-badge>
                    @endif
                </td>
                <td>
                    <x-status-badge outline>{{ $incoming->status->label() }}</x-status-badge>
                    @if ($incoming->isPossibleDuplicate())
                        <x-status-badge tone="warning">{{ __('Möglicher Doppel-Eingang') }}</x-status-badge>
                    @endif
                    @if ($incoming->relationLoaded('transfers'))
                        @foreach ($incoming->transfers as $journal)
                            <x-status-badge :tone="$journal->status->tone()" :title="$journal->error">{{ $journal->status->label() }}</x-status-badge>
                        @endforeach
                    @endif
                </td>
                <td class="whitespace-nowrap text-right">
                    @if ($canManage && $incoming->transferred_at === null && $incoming->status !== \App\Enums\Invoicing\IncomingEInvoiceStatus::Rejected)
                        @if ($incoming->recognition !== \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured)
                            <x-icon-btn icon="edit_note" :href="route('finance.incoming-invoices.values.form', $incoming)" data-entry-modal-trigger :label="__('Werte erfassen')" />
                        @endif
                        <x-icon-btn icon="rule" :href="route('finance.incoming-invoices.assign.form', $incoming)" data-entry-modal-trigger :label="__('Zuordnen')" />
                    @endif
                    @if ($incoming->document !== null)
                        <x-icon-btn icon="visibility" :href="route('finance.incoming-invoices.show', $incoming->document)" :label="__('Anzeigen')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="$colspan" icon="receipt_long" :title="match ($tab) { 'assign' => __('Nichts zuzuordnen.'), 'review' => __('Nichts zu prüfen.'), 'transfer', 'failed' => __('Keine offenen Übergaben.'), 'duplicates' => __('Kein möglicher Doppel-Eingang offen.'), default => __('Keine Eingänge im Zeitraum.') }" />
        @endforelse
    </x-table>

    @if ($bulk)
        </form>
    @endif

    <x-slot:footer>
        <x-pagination :paginator="$incomings" standing />
    </x-slot:footer>
</x-index-page>
@endsection
