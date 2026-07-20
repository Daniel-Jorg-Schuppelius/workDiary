{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Export :id', ['id' => $export->id]))
@section('nav-title', __('Export :period', ['period' => $export->periodLabel()]))

@php
    use App\Enums\TimeExport\TimeExportStatus;
    $tone = match ($export->status) {
        TimeExportStatus::Preparing => 'info',
        TimeExportStatus::Ready => 'primary',
        TimeExportStatus::Delivered => 'success',
        TimeExportStatus::Rejected => 'warning',
        TimeExportStatus::Superseded => 'ghost',
    };
@endphp

@section('content')
<x-index-page :subtitle="__('Profil :profile · Status :status', ['profile' => $export->profile, 'status' => $export->status->label()])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                    :href="route('exports.index')"
                    show-label>{{ __('Zurück') }}</x-icon-btn>
        @can('download', $export)
            <x-icon-btn icon="download" tone="primary" size="sm"
                        :href="route('exports.download', $export)"
                        show-label>{{ __('Datei laden') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @if (session('status'))
        <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
            <div class="card-body space-y-2">
                <h3 class="card-title text-base">{{ __('Stammdaten') }}</h3>
                <x-detail-grid class="grid-cols-2">
                    <x-detail-grid.row :label="__('Periode')" :value="$export->periodLabel()" class="tabular-nums" />
                    <x-detail-grid.row :label="__('Profil')" :value="$export->profile" />
                    <x-detail-grid.row :label="__('Status')"><x-status-badge :tone="$tone" size="sm">{{ $export->status->label() }}</x-status-badge></x-detail-grid.row>
                    <x-detail-grid.row :label="__('Scope')">
                        @if ($export->scope === 'user')
                            {{ __('Person') }} · {{ $export->scopeUser?->name }}
                        @elseif ($export->scope === 'team')
                            {{ __('Team') }} #{{ $export->scope_team_id }}
                        @else
                            {{ __('Gesamte Organisation') }}
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('Zeilen')" :value="$export->rows_count" class="tabular-nums" />
                    <x-detail-grid.row :label="__('Hash')" :value="$export->payload_hash" class="font-mono text-xs break-all" />
                    <x-detail-grid.row :label="__('Datei')" :value="$export->file_path ?? '—'" class="font-mono text-xs break-all" />
                    <x-detail-grid.row :label="__('Erstellt')">{{ $export->created_at?->fdatetime() }} · {{ $export->creator?->name }}</x-detail-grid.row>
                    @if ($export->delivered_at)
                        <x-detail-grid.row :label="__('Übermittelt')">{{ $export->delivered_at->fdatetime() }} · {{ $export->deliveredBy?->name ?? __('System') }}</x-detail-grid.row>
                    @endif
                    {{-- Liefernachweis der automatischen Lieferung (A21): wann/wohin je Kanal. --}}
                    @php $autoDelivery = $export->auto_delivery ?? []; @endphp
                    @if (! empty($autoDelivery))
                        <x-detail-grid.row :label="__('wage_types.delivery.title_evidence')">
                            <ul class="space-y-1 text-sm">
                                @if (isset($autoDelivery['mail']))
                                    <li>
                                        {{ __('wage_types.delivery.evidence_mail', ['to' => implode(', ', (array) ($autoDelivery['mail']['to'] ?? []))]) }}
                                        <span class="text-xs text-base-content/60 tabular-nums">· {{ \Carbon\CarbonImmutable::parse((string) $autoDelivery['mail']['at'])->fdatetime() }}</span>
                                    </li>
                                @endif
                                @if (isset($autoDelivery['sftp']))
                                    <li>
                                        {{ __('wage_types.delivery.evidence_sftp', ['target' => (string) ($autoDelivery['sftp']['target'] ?? '')]) }}
                                        <span class="text-xs text-base-content/60 tabular-nums">· {{ \Carbon\CarbonImmutable::parse((string) $autoDelivery['sftp']['at'])->fdatetime() }}</span>
                                    </li>
                                @endif
                            </ul>
                        </x-detail-grid.row>
                    @endif
                    @if ($export->supersededBy)
                        <x-detail-grid.row :label="__('Ersetzt durch')"><a class="link link-primary" href="{{ route('exports.show', $export->supersededBy) }}">#{{ $export->supersededBy->id }}</a></x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-3">
                <h3 class="card-title text-base">{{ __('Aktionen') }}</h3>

                @can('deliver', $export)
                    <form method="POST" action="{{ route('exports.deliver', $export) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" maxlength="2000" rows="2"
                                  class="textarea textarea-bordered w-full text-sm"
                                  placeholder="{{ __('Vermerk (optional)') }}"></textarea>
                        <x-button type="submit" tone="success" class="w-full" icon="send">{{ __('Als übermittelt markieren') }}</x-button>
                    </form>
                @endcan

                @can('reject', $export)
                    <form method="POST" action="{{ route('exports.reject', $export) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" required minlength="5" maxlength="2000" rows="2"
                                  class="textarea textarea-bordered w-full text-sm"
                                  placeholder="{{ __('Ablehnungsgrund (Pflicht)') }}"></textarea>
                        <x-button type="submit" tone="warning" class="w-full" icon="block">{{ __('Export ablehnen') }}</x-button>
                    </form>
                @endcan

                @can('delete', $export)
                    {{-- Vollaudit 2026-07 (N6): Löschung mit Pflicht-Begründung, Spur im Audit-Protokoll. --}}
                    <form method="POST" action="{{ route('exports.destroy', $export) }}" class="space-y-2"
                          data-confirm="{{ __('Export endgültig löschen? Die Begründung wird auditiert.') }}">
                        @csrf
                        @method('DELETE')
                        <textarea name="note" required minlength="5" maxlength="2000" rows="2"
                                  class="textarea textarea-bordered w-full text-sm"
                                  placeholder="{{ __('Löschbegründung (Pflicht)') }}"></textarea>
                        <x-button type="submit" tone="error" class="w-full" icon="delete">{{ __('Export löschen') }}</x-button>
                    </form>
                @endcan

                @cannot('deliver', $export)
                    @cannot('reject', $export)
                        <p class="text-sm text-base-content/60">{{ __('Keine weiteren Aktionen verfügbar.') }}</p>
                    @endcannot
                @endcannot
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Summen pro Lohnart') }}</h3>
            @php $totals = $export->totals ?? []; @endphp
            @if (empty($totals))
                <p class="text-sm text-base-content/60">{{ __('Keine Summen verfügbar.') }}</p>
            @else
                <x-table table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string" default="asc">{{ __('Lohnart') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($totals as $wageType => $info)
                        <tr>
                            <td>{{ $wageType }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $info['quantity'] ?? 0 }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($info['quantity'] ?? 0), 4, withThousandsSeparator: true) }}</td>
                            <td>{{ $info['unit'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>

    {{-- Prüfansicht (Feature 005): Summen je Mitarbeiter:in und Lohnart/Zuschlagsart --}}
    @php
        $userWageSummary = $export->lines
            ->groupBy(fn($l) => $l->user_id . '|' . $l->wage_type)
            ->map(fn($group) => [
                'user' => $group->first()->user,
                'wage_type' => $group->first()->wage_type,
                'wage_type_code' => $group->first()->wage_type_code,
                'percentage' => $group->first()->percentage,
                'rule_label' => $group->first()->surchargeRule?->label,
                'hours' => $group->sum(fn($l) => (float) $l->quantity),
            ])
            ->sortBy([['user.name', 'asc'], ['wage_type', 'asc']])
            ->values();
    @endphp
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('surcharge.title.export_summary') }}</h3>
            @if ($userWageSummary->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine Summen verfügbar.') }}</p>
            @else
                <x-table table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string" default="asc">{{ __('Mitarbeiter:in') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Lohnart') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('surcharge.field.wage_type_code') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('surcharge.field.percentage') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('surcharge.field.hours') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($userWageSummary as $row)
                        <tr>
                            <td>{{ $row['user']?->name }}</td>
                            <td>
                                <span class="font-mono text-sm">{{ $row['wage_type'] }}</span>
                                @if ($row['rule_label'])
                                    <div class="text-xs text-base-content/60">{{ $row['rule_label'] }}</div>
                                @endif
                            </td>
                            <td class="font-mono text-sm">{{ $row['wage_type_code'] ?? '—' }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $row['percentage'] ?? 0 }}">
                                {{ $row['percentage'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['percentage'], 2, withThousandsSeparator: true) . ' %' : '—' }}
                            </td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $row['hours'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['hours'], 4, withThousandsSeparator: true) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Zeilen') }}</h3>
            @if ($export->lines->isEmpty())
                <x-table.empty :colspan="6" icon="receipt_long" />
            @else
                <x-table table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string" default="asc">{{ __('Mitarbeiter:in') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Lohnart') }}</x-table.th>
                            <x-table.th sort type="string"><x-term glossary="kostenstelle">{{ __('Kostenstelle') }}</x-term></x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                            <x-table.th sort type="date">{{ __('Zeitraum') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @php
                        $canEditLines = $export->status === \App\Enums\TimeExport\TimeExportStatus::Ready
                            && \Illuminate\Support\Facades\Gate::allows('deliver', $export);
                    @endphp
                    @foreach ($export->lines as $line)
                        <tr>
                            <td>{{ $line->user?->name }}</td>
                            <td>{{ $line->wage_type }}</td>
                            <td>
                                @if ($canEditLines)
                                    {{-- Kostenstellen-Override (Rang 35): korrigiert die Zeile und rendert die Datei neu. --}}
                                    <form method="POST" action="{{ route('exports.lines.update', [$export, $line]) }}" class="flex items-center gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="cost_center" value="{{ $line->cost_center }}" maxlength="32"
                                               class="input input-bordered input-xs w-24 font-mono"
                                               aria-label="{{ __('Kostenstelle') }}">
                                        <x-icon-btn icon="save" tone="ghost" size="xs" type="submit" :label="__('Speichern')" />
                                    </form>
                                @else
                                    {{ $line->cost_center ?? '—' }}
                                @endif
                            </td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $line->quantity }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line->quantity, 4, withThousandsSeparator: true) }}</td>
                            <td>{{ $line->unit }}</td>
                            <td class="text-xs tabular-nums" data-sort-value="{{ $line->period_start?->format('Y-m-d') ?? '' }}">
                                {{ $line->period_start?->fdate() }} – {{ $line->period_end?->fdate() }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Audit-Verlauf') }}</h3>
            @if ($export->events->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine Ereignisse vorhanden.') }}</p>
            @else
                <ul class="timeline timeline-vertical timeline-compact">
                    @foreach ($export->events as $ev)
                        <li>
                            <div class="timeline-middle">
                                <span class="material-symbols-outlined text-base">history</span>
                            </div>
                            <div class="timeline-end timeline-box">
                                <div class="flex items-center gap-2 text-xs text-base-content/70">
                                    <span class="tabular-nums">{{ $ev->created_at?->fdatetime() }}</span>
                                    <span>·</span>
                                    <span>{{ $ev->actor?->name ?? __('System') }}</span>
                                </div>
                                <div class="font-medium">{{ $ev->event }}</div>
                                @if ($ev->note)
                                    <p class="text-sm">{{ $ev->note }}</p>
                                @endif
                            </div>
                            <hr />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-index-page>
@endsection
