{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Nachhaltigkeit & ESG'))
@section('nav-title', __('Nachhaltigkeit'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Zeitraum: :from – :to · erklärbare Kennzahlen, keine Konformitätszusage.', ['from' => $from, 'to' => $to])">
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="route('sustainability.index', ['export' => 'csv', 'from' => $from, 'to' => $to])" show-label>{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" :href="route('sustainability.index', ['export' => 'xlsx', 'from' => $from, 'to' => $to])" show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" :href="route('sustainability.index', ['export' => 'pdf', 'from' => $from, 'to' => $to])" show-label>{{ __('PDF') }}</x-icon-btn>
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
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success rounded-2xl px-5 py-3 text-sm shadow-xs">{{ session('status') }}</div>
    @endif

    {{-- Kennzahlen --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-tile :label="__('CO₂e gesamt (kg)')" :value="$aggregate['co2e_total_kg']" format="int" />
        <x-kpi-tile :label="__('Kritische Bewertungen (rot)')" :value="$critical" :tone="$critical > 0 ? 'error' : 'neutral'" />
        <x-kpi-tile :label="__('Offene Maßnahmen')" :value="$openMeasures" :tone="$openMeasures > 0 ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('Anteil Schätzwerte')" :value="$estimatedShare . ' %'" :tone="$estimatedShare > 50 ? 'warning' : 'info'"
                    :hint="__('gemessen/berechnet vor geschätzt')" />
    </div>

    @if ($aggregate['missing_factors'] !== [])
        <div class="alert alert-warning rounded-2xl px-5 py-3 text-sm shadow-xs">
            <x-icon name="warning" class="text-base" />
            <span>{{ __('Für folgende Aktivitäten fehlt ein gültiger Emissionsfaktor (keine stille 0): :codes', ['codes' => implode(', ', $aggregate['missing_factors'])]) }}</span>
        </div>
    @endif

    {{-- Emissionen & Ziele --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card padding="p-0" :title="__('Emissionen nach Aktivität')" icon="eco">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="activity-create">{{ __('Erfassen') }}</x-button>
                </x-slot:actions>
            @endif
            @if (empty($aggregate['activities']))
                <div class="p-4"><x-empty-state icon="eco" :title="__('Noch keine Aktivitätsdaten im Zeitraum.')" compact /></div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Aktivität') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th class="text-right">{{ __('CO₂e kg') }}</th>
                            <th>{{ __('Faktorquelle') }}</th>
                        </tr>
                    </x-slot:head>
                    <x-slot:foot>
                        @foreach ($aggregate['co2e_by_scope'] as $scope => $value)
                            <tr>
                                <td colspan="2" class="text-right text-xs uppercase tracking-wide text-muted">{{ __('Scope :scope', ['scope' => $scope]) }}</td>
                                <td class="text-right tabular-nums font-medium">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 1, withThousandsSeparator: true) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    </x-slot:foot>
                    @foreach ($aggregate['activities'] as $code => $activity)
                        <tr>
                            <td>{{ __("values.$code") }}</td>
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($activity['amount'], 1, withThousandsSeparator: true) }} {{ $activity['unit'] }}</td>
                            <td class="text-right tabular-nums">{{ $activity['co2e_kg'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($activity['co2e_kg'], 1, withThousandsSeparator: true) : '—' }}</td>
                            <td class="text-xs text-muted">{{ $activity['factor_source'] ?? __('Faktor fehlt') }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card padding="p-0" :title="__('Ziele & Zielpfade')" icon="flag">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="target-create">{{ __('Ziel anlegen') }}</x-button>
                </x-slot:actions>
            @endif
            @if ($targets->isEmpty())
                <div class="p-4"><x-empty-state icon="flag" :title="__('Keine Ziele definiert.')" compact /></div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Ziel') }}</th>
                            <th class="text-right">{{ __('Soll (Pfad :year)', ['year' => now()->format('Y')]) }}</th>
                            <th class="text-right">{{ __('Ist (Zeitraum)') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($targets as $row)
                        <tr @class(['text-error' => $row['actual'] !== null && $row['actual'] > $row['expected']])>
                            <td>{{ $row['target']->label }} <span class="text-xs text-muted">({{ $row['target']->baseline_year }} → {{ $row['target']->target_year }})</span></td>
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['expected'], 1, withThousandsSeparator: true) }} {{ $row['target']->unit }}</td>
                            <td class="text-right tabular-nums">{{ $row['actual'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['actual'], 1, withThousandsSeparator: true) . ' ' . $row['target']->unit : '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    {{-- Bewertungen, Kriterien, Maßnahmen --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Bewertungen (versioniert)')" icon="grade" :count="$assessments->count()">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="assessment-create"
                              :disabled="$criteria->where('active', true)->isEmpty()">{{ __('Bewertung starten') }}</x-button>
                </x-slot:actions>
            @endif
            @if ($canManage && $criteria->where('active', true)->isEmpty())
                <p class="mb-3 text-xs text-warning">{{ __('Blockiert: erst Kriterien anlegen (rechts).') }}</p>
            @endif
            @if ($assessments->isEmpty())
                <x-empty-state icon="grade" :title="__('Noch keine Bewertungen.')" compact />
            @else
                <ul class="space-y-1.5 text-sm">
                    @foreach ($assessments as $assessment)
                        <li class="flex flex-wrap items-center gap-2">
                            <a class="link link-hover font-medium" href="{{ route('sustainability.assessments.show', $assessment) }}">{{ $assessment->subject_label }} <span class="text-xs text-muted">V{{ $assessment->version }}</span></a>
                            <x-status-badge size="xs" outline>{{ __("values.{$assessment->status}") }}</x-status-badge>
                            @if ($assessment->rating)
                                <x-status-badge size="xs" :tone="$assessment->rating === 'green' ? 'success' : ($assessment->rating === 'yellow' ? 'warning' : 'error')">{{ $assessment->total_score }}</x-status-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('Kriterienkatalog (E/S/G)')" icon="checklist" :count="$criteria->count()">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="criterion-create">{{ __('Kriterium anlegen') }}</x-button>
                </x-slot:actions>
            @endif
            @if ($criteria->isEmpty())
                <x-empty-state icon="checklist" :title="__('Noch keine Kriterien.')" compact />
            @else
                <div class="flex flex-wrap gap-1 text-xs">
                    @foreach ($criteria as $criterion)
                        <span class="badge badge-outline badge-sm">{{ __("values.{$criterion->dimension}") }}: {{ $criterion->label }} (×{{ $criterion->weight }})</span>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card :title="__('Maßnahmenregister')" icon="task_alt" :count="$measures->count()">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="measure-create">{{ __('Erfassen') }}</x-button>
                </x-slot:actions>
            @endif
            @if ($measures->isEmpty())
                <x-empty-state icon="task_alt" :title="__('Keine Maßnahmen.')" compact />
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($measures as $measure)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" outline>{{ __("values.{$measure->status}") }}</x-status-badge>
                            <span class="min-w-0 flex-1">{{ $measure->title }}</span>
                            @if ($measure->responsible)<span class="text-xs text-muted">{{ $measure->responsible->name }}</span>@endif
                            @if ($measure->due_on)<span class="text-xs text-muted">{{ $measure->due_on->fdate() }}</span>@endif
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

    {{-- Methodik: Faktoren & VSME-Referenz --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Faktorenbibliothek (versioniert, Org-Override)')" icon="functions">
            @if ($canManage)
                <x-slot:actions>
                    <x-button size="sm" tone="ghost" icon="add" data-open-dialog="factor-create">{{ __('Override anlegen') }}</x-button>
                </x-slot:actions>
            @endif
            @forelse ($factorSets as $set)
                <div class="mb-3 last:mb-0">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="font-medium">{{ $set->name }} {{ $set->year }}</span>
                        <span class="text-xs text-muted">{{ $set->source ?? '—' }}</span>
                        @if ($set->organization_id !== null)
                            <span class="badge badge-info badge-xs">{{ __('Org-Override') }}</span>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1 text-xs">
                        @foreach ($set->factors as $factor)
                            <span class="badge badge-outline badge-sm" title="{{ $factor->source_note }} · gültig ab {{ $factor->valid_from->fdate() }}">
                                {{ __("values.{$factor->activity_code}") }}: {{ rtrim(rtrim((string) $factor->factor, '0'), '.') }} ({{ $factor->unit_code }}, S{{ $factor->scope }})
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty-state icon="functions" :title="__('Keine Faktor-Sets.')" compact />
            @endforelse
        </x-card>

        <x-card padding="p-0" :title="__('VSME-Referenzmatrix')" icon="table_view"
                :subtitle="__('vsme-1.0 · ohne Konformitätszusage')">
            <x-table bare scroll="none" class="max-h-72 overflow-y-auto">
                <x-slot:head>
                    <tr><th>{{ __('Abschnitt') }}</th><th>{{ __('Datenquelle in WorkDiary') }}</th></tr>
                </x-slot:head>
                @foreach ($mappings as $mapping)
                    <tr>
                        <td class="whitespace-nowrap">{{ $mapping->section_code }} — {{ $mapping->section_label }}</td>
                        <td class="text-xs text-base-content/70">{{ $mapping->mapping_note }}</td>
                    </tr>
                @endforeach
            </x-table>
            <p class="border-t border-base-300 px-4 py-3 text-xs text-muted">{{ __('esrs-2.0 / iso14001-2026 folgen als weitere Matrixversionen nach den Watchlist-Checks (W4/W6).') }}</p>
        </x-card>
    </div>

    {{-- ── Formular-Dialoge (Anlegen/Erfassen) — nur mit Verwaltungsrecht ── --}}
    @if ($canManage)
        <x-modal id="activity-create" :embedded="false" size="lg" tone="primary" icon="eco"
                 :title="__('Aktivität erfassen')" :action="route('sustainability.activities.store')" :submitLabel="__('Erfassen')">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Aktivität') }}</span>
                    <select name="activity_code" class="select select-bordered select-sm w-full">
                        @foreach (\App\Models\Sustainability\SustainabilityActivityRecord::ACTIVITY_CODES as $code)
                            <option value="{{ $code }}">{{ __("values.$code") }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Menge') }}</span>
                    <input name="amount" type="number" step="0.001" min="0" required class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Einheit (kWh/l/km/kg/m3)') }}</span>
                    <input name="unit" required maxlength="20" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Datenqualität') }}</span>
                    <select name="data_quality" class="select select-bordered select-sm w-full">
                        <option value="measured">{{ __('values.measured') }}</option>
                        <option value="calculated">{{ __('values.calculated') }}</option>
                        <option value="estimated">{{ __('values.estimated') }}</option>
                    </select>
                </label>
                <div class="sm:col-span-2">
                    <x-date-range layout="join" from-name="period_start" to-name="period_end" type="date" required
                                  :label="__('Zeitraum')"
                                  :from="now()->startOfMonth()->toDateString()" :to="now()->toDateString()" />
                </div>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Quelle (Zähler/Rechnung/Schätzung)') }}</span>
                    <input name="source_note" maxlength="300" class="input input-bordered input-sm w-full">
                </label>
            </div>
        </x-modal>

        <x-modal id="target-create" :embedded="false" size="lg" tone="primary" icon="flag"
                 :title="__('Ziel anlegen')" :action="route('sustainability.targets.store')" :submitLabel="__('Ziel anlegen')">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Kennzahl') }}</span>
                    <select name="metric" class="select select-bordered select-sm w-full">
                        <option value="co2e_total">{{ __('CO₂e gesamt') }}</option>
                        <option value="energy_kwh">{{ __('Energie (kWh)') }}</option>
                        <option value="waste_kg">{{ __('Abfall (kg)') }}</option>
                        <option value="repair_quota">{{ __('Reparaturquote') }}</option>
                        <option value="sustainable_procurement_share">{{ __('Nachhaltige Beschaffung') }}</option>
                        <option value="custom">{{ __('Eigene Kennzahl') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Einheit') }}</span>
                    <input name="unit" required maxlength="20" class="input input-bordered input-sm w-full">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Bezeichnung') }}</span>
                    <input name="label" required maxlength="200" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Basiswert') }}</span>
                    <input name="baseline_value" type="number" step="0.001" required class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Basisjahr') }}</span>
                    <input name="baseline_year" type="number" required value="{{ now()->format('Y') }}" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Zielwert') }}</span>
                    <input name="target_value" type="number" step="0.001" required class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Zieljahr') }}</span>
                    <input name="target_year" type="number" required value="{{ (int) now()->format('Y') + 4 }}" class="input input-bordered input-sm w-full">
                </label>
            </div>
        </x-modal>

        <x-modal id="assessment-create" :embedded="false" tone="primary" icon="grade"
                 :title="__('Bewertung starten')" :action="route('sustainability.assessments.store')" :submitLabel="__('Bewertung starten')">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Gerät/Prozess/Dienstleistung/Lieferant …') }}</span>
                <input name="subject_label" required maxlength="200" class="input input-bordered input-sm w-full">
            </label>
            <p class="mt-2 text-xs text-muted">{{ __(':count aktive Kriterien im Katalog.', ['count' => $criteria->where('active', true)->count()]) }}</p>
        </x-modal>

        <x-modal id="criterion-create" :embedded="false" tone="primary" icon="checklist"
                 :title="__('Kriterium anlegen')" :action="route('sustainability.criteria.store')" :submitLabel="__('Anlegen')">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Dimension') }}</span>
                    <select name="dimension" class="select select-bordered select-sm w-full">
                        <option value="environment">{{ __('values.environment') }}</option>
                        <option value="social">{{ __('values.social') }}</option>
                        <option value="governance">{{ __('values.governance') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Gewicht') }}</span>
                    <input name="weight" type="number" min="1" max="10" value="1" required class="input input-bordered input-sm w-full">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Kriterium (z. B. Reparierbarkeit)') }}</span>
                    <input name="label" required maxlength="200" class="input input-bordered input-sm w-full">
                </label>
            </div>
        </x-modal>

        <x-modal id="measure-create" :embedded="false" size="lg" tone="primary" icon="task_alt"
                 :title="__('Maßnahme erfassen')" :action="route('sustainability.measures.store')" :submitLabel="__('Erfassen')">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Maßnahme (z. B. Umstellung auf LED)') }}</span>
                    <input name="title" required maxlength="300" class="input input-bordered input-sm w-full">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Erwartete Wirkung') }}</span>
                    <input name="expected_impact" maxlength="500" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Aufwand') }}</span>
                    <select name="effort" class="select select-bordered select-sm w-full">
                        <option value="low">{{ __('values.low') }}</option>
                        <option value="medium" selected>{{ __('values.medium') }}</option>
                        <option value="high">{{ __('values.high') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Kosten €') }}</span>
                    <input name="cost_estimate" type="number" step="0.01" min="0" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Verantwortlich …') }}</span>
                    <select name="responsible_user_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Verantwortlich …') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Fällig am') }}</span>
                    <input name="due_on" type="date" class="input input-bordered input-sm w-full">
                </label>
            </div>
        </x-modal>

        <x-modal id="factor-create" :embedded="false" size="lg" tone="primary" icon="functions"
                 :title="__('Faktor-Override anlegen')" :action="route('sustainability.factors.store')" :submitLabel="__('Override anlegen')">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Aktivität') }}</span>
                    <select name="activity_code" class="select select-bordered select-sm w-full">
                        @foreach (\App\Models\Sustainability\SustainabilityActivityRecord::ACTIVITY_CODES as $code)
                            <option value="{{ $code }}">{{ __("values.$code") }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Bezeichnung') }}</span>
                    <input name="label" required maxlength="200" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Faktor (kg CO₂e/Einheit)') }}</span>
                    <input name="factor" type="number" step="0.000001" min="0" required class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Einheitencode (z. B. kg_co2e_per_kwh)') }}</span>
                    <input name="unit_code" required maxlength="40" class="input input-bordered input-sm w-full">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Scope') }}</span>
                    <select name="scope" class="select select-bordered select-sm w-full">
                        <option value="1">Scope 1</option>
                        <option value="2">Scope 2</option>
                        <option value="3">Scope 3</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Gültig ab') }}</span>
                    <input name="valid_from" type="date" required value="{{ now()->toDateString() }}" class="input input-bordered input-sm w-full">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-base-content/70">{{ __('Quelle') }}</span>
                    <input name="source_note" maxlength="300" class="input input-bordered input-sm w-full">
                </label>
            </div>
        </x-modal>
    @endif
</x-page-shell>
@endsection
