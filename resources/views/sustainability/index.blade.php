@extends('layouts.app')

@section('title', __('Nachhaltigkeit & ESG'))
@section('nav-title', __('Nachhaltigkeit'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-page-toolbar :title="__('Nachhaltigkeit & ESG')">
        <div class="text-sm text-base-content/70">{{ __('Zeitraum: :from – :to · erklärbare Kennzahlen, keine Konformitätszusage.', ['from' => $from, 'to' => $to]) }}</div>
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" :href="route('sustainability.index', ['export' => 'csv', 'from' => $from, 'to' => $to])" show-label>{{ __('CSV') }}</x-icon-btn>
            @if ($canManage)
                <x-action-form :action="route('sustainability.snapshot.store')">
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <x-icon-btn icon="photo_camera" size="sm" type="submit" show-label
                                :title="__('Kennzahlen + Methodik als Managementbewertungs-Snapshot einfrieren')">{{ __('Snapshot') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-kpi-tile :label="__('CO₂e gesamt (kg)')" :value="number_format($aggregate['co2e_total_kg'], 0, ',', '.')" />
        <x-kpi-tile :label="__('Kritische Bewertungen (rot)')" :value="$critical" />
        <x-kpi-tile :label="__('Offene Maßnahmen')" :value="$openMeasures" />
        <x-kpi-tile :label="__('Anteil Schätzwerte')" :value="$estimatedShare . ' %'" />
    </div>

    @if ($aggregate['missing_factors'] !== [])
        <div class="alert alert-warning text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">warning</span>
            {{ __('Für folgende Aktivitäten fehlt ein gültiger Emissionsfaktor (keine stille 0): :codes', ['codes' => implode(', ', $aggregate['missing_factors'])]) }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Emissionen nach Aktivität')">
            @if (empty($aggregate['activities']))
                <x-empty-state icon="eco" :title="__('Noch keine Aktivitätsdaten im Zeitraum.')" compact />
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Aktivität') }}</th><th class="text-right">{{ __('Menge') }}</th><th class="text-right">{{ __('CO₂e kg') }}</th><th>{{ __('Faktorquelle') }}</th></tr></thead>
                        <tbody>
                            @foreach ($aggregate['activities'] as $code => $activity)
                                <tr>
                                    <td>{{ __("values.$code") }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($activity['amount'], 1, ',', '.') }} {{ $activity['unit'] }}</td>
                                    <td class="text-right tabular-nums">{{ $activity['co2e_kg'] !== null ? number_format($activity['co2e_kg'], 1, ',', '.') : '—' }}</td>
                                    <td class="text-xs text-base-content/60">{{ $activity['factor_source'] ?? __('Faktor fehlt') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @foreach ($aggregate['co2e_by_scope'] as $scope => $value)
                                <tr><td colspan="2" class="text-right">{{ __('Scope :scope', ['scope' => $scope]) }}</td><td class="text-right tabular-nums">{{ number_format($value, 1, ',', '.') }}</td><td></td></tr>
                            @endforeach
                        </tfoot>
                    </table>
                </div>
            @endif
            @if ($canManage)
                <form method="POST" action="{{ route('sustainability.activities.store') }}" class="mt-3 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <select name="activity_code" class="select select-sm select-bordered">
                        @foreach (\App\Models\Sustainability\SustainabilityActivityRecord::ACTIVITY_CODES as $code)
                            <option value="{{ $code }}">{{ __("values.$code") }}</option>
                        @endforeach
                    </select>
                    <input name="amount" type="number" step="0.001" min="0" required class="input input-sm input-bordered" placeholder="{{ __('Menge') }}">
                    <input name="unit" required maxlength="20" class="input input-sm input-bordered" placeholder="{{ __('Einheit (kWh/l/km/kg/m3)') }}">
                    <input name="period_start" type="date" required class="input input-sm input-bordered" value="{{ now()->startOfMonth()->toDateString() }}">
                    <input name="period_end" type="date" required class="input input-sm input-bordered" value="{{ now()->toDateString() }}">
                    <select name="data_quality" class="select select-sm select-bordered">
                        <option value="measured">{{ __('values.measured') }}</option>
                        <option value="calculated">{{ __('values.calculated') }}</option>
                        <option value="estimated">{{ __('values.estimated') }}</option>
                    </select>
                    <input name="source_note" maxlength="300" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Quelle (Zähler/Rechnung/Schätzung)') }}">
                    <div><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn></div>
                </form>
            @endif
        </x-card>

        <x-card :title="__('Ziele & Zielpfade')">
            @if ($targets->isEmpty())
                <x-empty-state icon="flag" :title="__('Keine Ziele definiert.')" compact />
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Ziel') }}</th><th class="text-right">{{ __('Soll (Pfad :year)', ['year' => now()->format('Y')]) }}</th><th class="text-right">{{ __('Ist (Zeitraum)') }}</th></tr></thead>
                        <tbody>
                            @foreach ($targets as $row)
                                <tr @class(['text-error' => $row['actual'] !== null && $row['actual'] > $row['expected']])>
                                    <td>{{ $row['target']->label }} <span class="text-xs text-base-content/60">({{ $row['target']->baseline_year }} → {{ $row['target']->target_year }})</span></td>
                                    <td class="text-right tabular-nums">{{ number_format($row['expected'], 1, ',', '.') }} {{ $row['target']->unit }}</td>
                                    <td class="text-right tabular-nums">{{ $row['actual'] !== null ? number_format($row['actual'], 1, ',', '.') . ' ' . $row['target']->unit : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if ($canManage)
                <form method="POST" action="{{ route('sustainability.targets.store') }}" class="mt-3 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <select name="metric" class="select select-sm select-bordered">
                        <option value="co2e_total">{{ __('CO₂e gesamt') }}</option>
                        <option value="energy_kwh">{{ __('Energie (kWh)') }}</option>
                        <option value="waste_kg">{{ __('Abfall (kg)') }}</option>
                        <option value="repair_quota">{{ __('Reparaturquote') }}</option>
                        <option value="sustainable_procurement_share">{{ __('Nachhaltige Beschaffung') }}</option>
                        <option value="custom">{{ __('Eigene Kennzahl') }}</option>
                    </select>
                    <input name="label" required maxlength="200" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Bezeichnung') }}">
                    <input name="baseline_value" type="number" step="0.001" required class="input input-sm input-bordered" placeholder="{{ __('Basiswert') }}">
                    <input name="baseline_year" type="number" required class="input input-sm input-bordered" value="{{ now()->format('Y') }}">
                    <input name="unit" required maxlength="20" class="input input-sm input-bordered" placeholder="{{ __('Einheit') }}">
                    <input name="target_value" type="number" step="0.001" required class="input input-sm input-bordered" placeholder="{{ __('Zielwert') }}">
                    <input name="target_year" type="number" required class="input input-sm input-bordered" value="{{ (int) now()->format('Y') + 4 }}">
                    <div><x-icon-btn icon="flag" tone="primary" size="sm" type="submit" show-label>{{ __('Ziel anlegen') }}</x-icon-btn></div>
                </form>
            @endif
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Bewertungen (versioniert)')">
            @if ($canManage)
                <form method="POST" action="{{ route('sustainability.assessments.store') }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="subject_label" required maxlength="200" class="input input-sm input-bordered flex-1" placeholder="{{ __('Gerät/Prozess/Dienstleistung/Lieferant …') }}">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Bewertung starten') }}</x-icon-btn>
                </form>
                <p class="mb-2 text-xs text-base-content/60">{{ $criteria->isEmpty() ? __('Blockiert: erst Kriterien anlegen (unten).') : __(':count aktive Kriterien im Katalog.', ['count' => $criteria->where('active', true)->count()]) }}</p>
            @endif
            @if ($assessments->isEmpty())
                <x-empty-state icon="grade" :title="__('Noch keine Bewertungen.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($assessments as $assessment)
                        <li class="flex flex-wrap items-center gap-2">
                            <a class="link" href="{{ route('sustainability.assessments.show', $assessment) }}">{{ $assessment->subject_label }} (V{{ $assessment->version }})</a>
                            <x-status-badge size="xs" outline>{{ __("values.{$assessment->status}") }}</x-status-badge>
                            @if ($assessment->rating)
                                <x-status-badge size="xs" :tone="$assessment->rating === 'green' ? 'success' : ($assessment->rating === 'yellow' ? 'warning' : 'error')">{{ $assessment->total_score }}</x-status-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canManage)
                <h4 class="mt-4 text-sm font-semibold">{{ __('Kriterienkatalog (E/S/G)') }}</h4>
                <form method="POST" action="{{ route('sustainability.criteria.store') }}" class="my-1 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="dimension" class="select select-sm select-bordered">
                        <option value="environment">{{ __('values.environment') }}</option>
                        <option value="social">{{ __('values.social') }}</option>
                        <option value="governance">{{ __('values.governance') }}</option>
                    </select>
                    <input name="label" required maxlength="200" class="input input-sm input-bordered flex-1" placeholder="{{ __('Kriterium (z. B. Reparierbarkeit)') }}">
                    <input name="weight" type="number" min="1" max="10" value="1" required class="input input-sm input-bordered w-20" aria-label="{{ __('Gewicht') }}">
                    <x-icon-btn icon="add" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
                </form>
                <div class="flex flex-wrap gap-1 text-xs">
                    @foreach ($criteria as $criterion)
                        <span class="badge badge-outline badge-sm">{{ __("values.{$criterion->dimension}") }}: {{ $criterion->label }} (×{{ $criterion->weight }})</span>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card :title="__('Maßnahmenregister')">
            @if ($canManage)
                <form method="POST" action="{{ route('sustainability.measures.store') }}" class="mb-3 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <input name="title" required maxlength="300" class="input input-sm input-bordered sm:col-span-3" placeholder="{{ __('Maßnahme (z. B. Umstellung auf LED)') }}">
                    <input name="expected_impact" maxlength="500" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Erwartete Wirkung') }}">
                    <select name="effort" class="select select-sm select-bordered">
                        <option value="low">{{ __('values.low') }}</option>
                        <option value="medium" selected>{{ __('values.medium') }}</option>
                        <option value="high">{{ __('values.high') }}</option>
                    </select>
                    <input name="cost_estimate" type="number" step="0.01" min="0" class="input input-sm input-bordered" placeholder="{{ __('Kosten €') }}">
                    <select name="responsible_user_id" class="select select-sm select-bordered">
                        <option value="">{{ __('Verantwortlich …') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <input name="due_on" type="date" class="input input-sm input-bordered">
                    <div><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Erfassen') }}</x-icon-btn></div>
                </form>
            @endif
            @if ($measures->isEmpty())
                <x-empty-state icon="checklist" :title="__('Keine Maßnahmen.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($measures as $measure)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" outline>{{ __("values.{$measure->status}") }}</x-status-badge>
                            <span>{{ $measure->title }}</span>
                            @if ($measure->responsible)<span class="text-xs text-base-content/60">{{ $measure->responsible->name }}</span>@endif
                            @if ($measure->due_on)<span class="text-xs text-base-content/60">{{ $measure->due_on->fdate() }}</span>@endif
                            @if ($measure->effectiveness)
                                <x-status-badge size="xs" :tone="$measure->effectiveness === 'effective' ? 'success' : 'warning'">{{ __("values.{$measure->effectiveness}") }}</x-status-badge>
                            @endif
                            @if ($canManage)
                                <form method="POST" action="{{ route('sustainability.measures.update', $measure) }}" class="ml-auto flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="status" class="select select-xs select-bordered">
                                        @foreach (\App\Models\Sustainability\SustainabilityMeasure::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($measure->status === $status)>{{ __("values.$status") }}</option>
                                        @endforeach
                                    </select>
                                    @if ($measure->status === 'done' && $measure->effectiveness === null)
                                        <select name="effectiveness" class="select select-xs select-bordered">
                                            <option value="">{{ __('Wirksamkeit …') }}</option>
                                            <option value="effective">{{ __('values.effective') }}</option>
                                            <option value="partly">{{ __('values.partly') }}</option>
                                            <option value="ineffective">{{ __('values.ineffective') }}</option>
                                        </select>
                                    @endif
                                    <button type="submit" class="btn btn-xs">{{ __('OK') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Faktorenbibliothek (versioniert, Org-Override)')">
            @foreach ($factorSets as $set)
                <div class="mb-2">
                    <span class="font-medium">{{ $set->name }} {{ $set->year }}</span>
                    <span class="text-xs text-base-content/60">({{ $set->source ?? '—' }}@if ($set->organization_id !== null) · {{ __('Org-Override') }}@endif)</span>
                    <div class="mt-1 flex flex-wrap gap-1 text-xs">
                        @foreach ($set->factors as $factor)
                            <span class="badge badge-outline badge-sm" title="{{ $factor->source_note }} · gültig ab {{ $factor->valid_from->fdate() }}">
                                {{ __("values.{$factor->activity_code}") }}: {{ rtrim(rtrim((string) $factor->factor, '0'), '.') }} ({{ $factor->unit_code }}, S{{ $factor->scope }})
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if ($canManage)
                <form method="POST" action="{{ route('sustainability.factors.store') }}" class="mt-2 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <select name="activity_code" class="select select-sm select-bordered">
                        @foreach (\App\Models\Sustainability\SustainabilityActivityRecord::ACTIVITY_CODES as $code)
                            <option value="{{ $code }}">{{ __("values.$code") }}</option>
                        @endforeach
                    </select>
                    <input name="label" required maxlength="200" class="input input-sm input-bordered" placeholder="{{ __('Bezeichnung') }}">
                    <input name="factor" type="number" step="0.000001" min="0" required class="input input-sm input-bordered" placeholder="kg CO₂e/Einheit">
                    <input name="unit_code" required maxlength="40" class="input input-sm input-bordered" placeholder="kg_co2e_per_kwh">
                    <select name="scope" class="select select-sm select-bordered">
                        <option value="1">Scope 1</option><option value="2">Scope 2</option><option value="3">Scope 3</option>
                    </select>
                    <input name="valid_from" type="date" required class="input input-sm input-bordered" value="{{ now()->toDateString() }}">
                    <input name="source_note" maxlength="300" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Quelle') }}">
                    <div><x-icon-btn icon="add" size="sm" type="submit" show-label>{{ __('Override anlegen') }}</x-icon-btn></div>
                </form>
            @endif
        </x-card>

        <x-card :title="__('VSME-Referenzmatrix (vsme-1.0, ohne Konformitätszusage)')">
            <div class="max-h-72 overflow-y-auto">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Abschnitt') }}</th><th>{{ __('Datenquelle in WorkDiary') }}</th></tr></thead>
                    <tbody>
                        @foreach ($mappings as $mapping)
                            <tr>
                                <td class="whitespace-nowrap">{{ $mapping->section_code }} — {{ $mapping->section_label }}</td>
                                <td class="text-xs text-base-content/70">{{ $mapping->mapping_note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-base-content/60">{{ __('esrs-2.0 / iso14001-2026 folgen als weitere Matrixversionen nach den Watchlist-Checks (W4/W6).') }}</p>
        </x-card>
    </div>
</x-page-shell>
@endsection
