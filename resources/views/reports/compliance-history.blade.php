{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : compliance-history.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verstoß-Historie (Feature 006, Welle D): die persistierten ArbZG-Verstöße
  mit Bearbeitungsstand und Acknowledge-Workflow (quittieren / akzeptieren
  mit Pflicht-Begründung). Ergänzt die on-the-fly-Report-Ansicht um die
  revisionssichere Sicht — Verstöße werden nie gelöscht, nur umgestatust.
--}}

@extends('layouts.app')
@section('title', __('compliance.history.title'))
@section('nav-title', __('compliance.history.nav'))

@section('content')
@php
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $sevTone = fn (string $sev) => $sev === \App\Services\Compliance\AttendanceComplianceFinding::SEVERITY_ERROR ? 'error' : 'warning';
    $ackStatus = \App\Enums\Compliance\ComplianceFindingStatus::Acknowledged->value;
    $accStatus = \App\Enums\Compliance\ComplianceFindingStatus::Accepted->value;
    $statusOptions = [];
    foreach ($statuses as $status) {
        $statusOptions[$status] = __('enums.compliance.finding-status.' . $status) . ' (' . ($counts[$status] ?? 0) . ')';
    }
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('compliance.history.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.arbzg-compliance')" show-label>{{ __('compliance.history.to_report') }}</x-icon-btn>
                <x-icon-btn icon="insights" tone="outline" size="sm"
                            :href="route('reports.compliance.dashboard')" show-label>{{ __('compliance.history.to_dashboard') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.compliance.history')" :reset="route('reports.compliance.history')">
        @include('reports._standard_filters', [
            'idPrefix' => 'hist',
            'statusOptions' => $statusOptions,
            'statusLabel' => __('compliance.history.filter.status'),
        ])
        <x-filter-field :label="__('compliance.history.filter.category')" for="hist-category">
            <select id="hist-category" name="category" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('compliance.report.filter.all') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($categoryFilter === $category)>{{ __('compliance.history.category.' . $category) }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-charts.bar :title="__('Neue vs. quittierte Befunde je Monat')" :unit="__('Befunde')"
                  :series="$ackSeries" :x-label="__('Monat')" :y-label="__('Neu')"
                  :y2-label="__('Quittiert')" />

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        @foreach ($statuses as $status)
            @php $case = \App\Enums\Compliance\ComplianceFindingStatus::from($status); @endphp
            <x-kpi-tile :label="$case->label()" :value="$counts[$status] ?? 0"
                        :tone="($counts[$status] ?? 0) > 0 ? $case->tone() : 'neutral'" format="int"
                        :href="route('reports.compliance.history', ['status' => $status])" />
        @endforeach
    </div>

    <x-card>
        @if ($findings->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">verified</span>'
                           :title="__('compliance.history.empty')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('compliance.history.col.employee') }}</x-table.th>
                        <x-table.th>{{ __('compliance.report.col.date') }}</x-table.th>
                        <x-table.th>{{ __('compliance.report.col.kind') }}</x-table.th>
                        <x-table.th align="right">{{ __('compliance.report.col.value') }}</x-table.th>
                        <x-table.th align="right">{{ __('compliance.report.col.threshold') }}</x-table.th>
                        <x-table.th>{{ __('compliance.report.col.severity') }}</x-table.th>
                        <x-table.th>{{ __('compliance.history.col.status') }}</x-table.th>
                        @if ($canManage)<x-table.th></x-table.th>@endif
                    </tr>
                </x-slot:head>
                @foreach ($findings as $f)
                    <tr>
                        <td>{{ $f->subject?->name ?? '—' }}</td>
                        <td class="tabular-nums">{{ $f->scope_date->fdate() }}</td>
                        <td>{{ __('compliance.report.kind.' . $f->rule_code) }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ $fmtMin((int) $f->detected_value) }}</td>
                        <td class="text-right tabular-nums text-base-content/60">{{ $fmtMin((int) $f->threshold_value) }}</td>
                        <td><x-status-badge :tone="$sevTone($f->severity)" size="sm">{{ __('compliance.report.severity.' . $f->severity) }}</x-status-badge></td>
                        <td>
                            <x-status-badge :tone="$f->status->tone()" size="sm">{{ $f->status->label() }}</x-status-badge>
                            @if ($f->acknowledged_at)
                                <div class="text-xs text-base-content/60 mt-0.5">
                                    {{ $f->acknowledgedByUser?->name ?? '—' }} · {{ $f->acknowledged_at->fdate() }}
                                    @if ($f->acknowledge_note)
                                        <span class="italic">„{{ \Illuminate\Support\Str::limit($f->acknowledge_note, 80) }}"</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-right">
                                @if ($f->status->isAcknowledgeable())
                                    <div class="flex items-center gap-1 justify-end flex-wrap">
                                        @if ($f->category === \App\Services\Compliance\AttendancePlausibilityScanService::CATEGORY)
                                            {{-- MVP-519: 1-Klick-Klärung — Korrekturantrag für den Befund-Tag vorbefüllen. --}}
                                            <x-icon-btn icon="edit_calendar" tone="outline" size="xs"
                                                        :href="route('corrections.create', ['date' => $f->scope_date->toDateString()])"
                                                        data-entry-modal-trigger
                                                        :title="__('compliance.history.btn.correction')" />
                                        @endif
                                        <form method="POST" action="{{ route('reports.compliance.acknowledge', $f) }}"
                                              class="flex items-center gap-1 justify-end flex-wrap">
                                            @csrf
                                            <input type="text" name="note" maxlength="5000"
                                                   class="input input-xs input-bordered w-40"
                                                   placeholder="{{ __('compliance.history.note_placeholder') }}">
                                            <button class="btn btn-xs" type="submit" name="status" value="{{ $ackStatus }}">
                                                {{ __('compliance.history.btn.acknowledge') }}
                                            </button>
                                            <button class="btn btn-xs btn-warning" type="submit" name="status" value="{{ $accStatus }}">
                                                {{ __('compliance.history.btn.accept') }}
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-table>

            <x-pagination :paginator="$findings" standing />
        @endif
    </x-card>
</x-page-shell>
@endsection
