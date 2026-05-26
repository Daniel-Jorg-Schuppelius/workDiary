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
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-base-content/70">{{ __('Periode') }}</dt>
                    <dd class="tabular-nums">{{ $export->periodLabel() }}</dd>
                    <dt class="text-base-content/70">{{ __('Profil') }}</dt>
                    <dd>{{ $export->profile }}</dd>
                    <dt class="text-base-content/70">{{ __('Status') }}</dt>
                    <dd><span class="badge badge-{{ $tone }} badge-sm">{{ $export->status->label() }}</span></dd>
                    <dt class="text-base-content/70">{{ __('Scope') }}</dt>
                    <dd>
                        @if ($export->scope === 'user')
                            {{ __('Person') }} · {{ $export->scopeUser?->name }}
                        @elseif ($export->scope === 'team')
                            {{ __('Team') }} #{{ $export->scope_team_id }}
                        @else
                            {{ __('Gesamte Organisation') }}
                        @endif
                    </dd>
                    <dt class="text-base-content/70">{{ __('Zeilen') }}</dt>
                    <dd class="tabular-nums">{{ $export->rows_count }}</dd>
                    <dt class="text-base-content/70">{{ __('Hash') }}</dt>
                    <dd class="font-mono text-xs break-all">{{ $export->payload_hash }}</dd>
                    <dt class="text-base-content/70">{{ __('Datei') }}</dt>
                    <dd class="font-mono text-xs break-all">{{ $export->file_path ?? '—' }}</dd>
                    <dt class="text-base-content/70">{{ __('Erstellt') }}</dt>
                    <dd>{{ $export->created_at?->format('d.m.Y H:i') }} · {{ $export->creator?->name }}</dd>
                    @if ($export->delivered_at)
                        <dt class="text-base-content/70">{{ __('Übermittelt') }}</dt>
                        <dd>{{ $export->delivered_at->format('d.m.Y H:i') }} · {{ $export->deliveredBy?->name }}</dd>
                    @endif
                    @if ($export->supersededBy)
                        <dt class="text-base-content/70">{{ __('Ersetzt durch') }}</dt>
                        <dd><a class="link link-primary" href="{{ route('exports.show', $export->supersededBy) }}">#{{ $export->supersededBy->id }}</a></dd>
                    @endif
                </dl>
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
                        <button type="submit" class="btn btn-sm btn-success w-full">
                            <span class="material-symbols-outlined text-base">send</span>
                            {{ __('Als übermittelt markieren') }}
                        </button>
                    </form>
                @endcan

                @can('reject', $export)
                    <form method="POST" action="{{ route('exports.reject', $export) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" required minlength="5" maxlength="2000" rows="2"
                                  class="textarea textarea-bordered w-full text-sm"
                                  placeholder="{{ __('Ablehnungsgrund (Pflicht)') }}"></textarea>
                        <button type="submit" class="btn btn-sm btn-warning w-full">
                            <span class="material-symbols-outlined text-base">block</span>
                            {{ __('Export ablehnen') }}
                        </button>
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
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Lohnart') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th>{{ __('Einheit') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($totals as $wageType => $info)
                        <tr>
                            <td>{{ $wageType }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) ($info['quantity'] ?? 0), 4, ',', '.') }}</td>
                            <td>{{ $info['unit'] ?? '' }}</td>
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
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Mitarbeiter:in') }}</th>
                            <th>{{ __('Lohnart') }}</th>
                            <th>{{ __('Kostenstelle') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th>{{ __('Einheit') }}</th>
                            <th>{{ __('Zeitraum') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($export->lines as $line)
                        <tr>
                            <td>{{ $line->user?->name }}</td>
                            <td>{{ $line->wage_type }}</td>
                            <td>{{ $line->cost_center ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) $line->quantity, 4, ',', '.') }}</td>
                            <td>{{ $line->unit }}</td>
                            <td class="text-xs tabular-nums">
                                {{ $line->period_start?->format('d.m.Y') }} – {{ $line->period_end?->format('d.m.Y') }}
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
                                    <span class="tabular-nums">{{ $ev->created_at?->format('d.m.Y H:i') }}</span>
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
