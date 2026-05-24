@extends('layouts.app')

@section('title', ($asset->name ?: $asset->asset_no) . ' — ' . __('Asset'))
@section('nav-title', $asset->name ?: $asset->asset_no)

@section('content')
    @php
        $assetClassValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
        $assetStatusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
    @endphp

    <x-page-shell>
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <h1 class="text-xl font-semibold">{{ $asset->name }}</h1>
                    <div class="text-sm text-base-content/70">
                        {{ __('Asset-Nr.') }}: <span class="font-mono">{{ $asset->asset_no }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="badge badge-outline">{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</span>
                        <span class="badge badge-outline">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</span>
                        @if ($asset->serial_no)
                            <span class="text-base-content/70">{{ __('Seriennummer') }}: {{ $asset->serial_no }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-icon-btn icon="arrow_back" size="sm" :href="route('assets.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Standort') }}</div>
                    <div class="font-medium">{{ $asset->location_text ?: '—' }}</div>
                </div>
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Kunde') }}</div>
                    <div class="font-medium">{{ $asset->customer?->name ?: '—' }}</div>
                </div>
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Verknüpfungen sichtbar') }}</div>
                    <div class="font-medium">
                        {{ $visibleCounts['diary'] + $visibleCounts['protocols'] + $visibleCounts['material'] + $visibleCounts['attachments'] }}
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Aufträge') }} ({{ $visibleCounts['diary'] }})</h2>
            @if ($diaryEntries->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Aufträge sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Projekt') }}</th>
                                <th>{{ __('Mitarbeiter') }}</th>
                                <th>{{ __('Start') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($diaryEntries as $entry)
                                <tr>
                                    <td>
                                        <a href="{{ route('diary.show', $entry) }}" class="link link-hover">{{ $entry->title ?: ('#' . $entry->id) }}</a>
                                    </td>
                                    <td>{{ $entry->project?->name ?: '—' }}</td>
                                    <td>{{ $entry->user?->name ?: '—' }}</td>
                                    <td>{{ optional($entry->start_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Timeline') }} ({{ $timelineEntries->count() }})</h2>
            @if ($timelineEntries->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine Timeline-Einträge vorhanden.') }}</p>
            @else
                <ul class="divide-y divide-base-300">
                    @foreach ($timelineEntries as $event)
                        <li class="flex items-start justify-between gap-3 py-3">
                            <div class="space-y-1">
                                <div class="text-sm font-semibold">{{ $event['title'] }}</div>
                                <div class="text-sm text-base-content/80">{{ $event['detail'] }}</div>
                            </div>
                            <span class="shrink-0 text-xs text-base-content/60">{{ $event['occurred_at_formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Protokolle') }} ({{ $visibleCounts['protocols'] }})</h2>
            @if ($protocols->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Protokolle sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Typ') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Datum') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocols as $protocol)
                                <tr>
                                    <td>{{ $protocol->title }}</td>
                                    <td>{{ $protocol->type->label() }}</td>
                                    <td>{{ $protocol->status->label() }}</td>
                                    <td>{{ optional($protocol->occurred_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Materialeinsatz') }} ({{ $visibleCounts['material'] }})</h2>
            @if ($materialUsages->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Kein verknüpfter Materialeinsatz sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Beschreibung') }}</th>
                                <th>{{ __('Menge') }}</th>
                                <th>{{ __('Nettobetrag') }}</th>
                                <th>{{ __('Stundenzettel') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materialUsages as $usage)
                                <tr>
                                    <td>{{ $usage->description }}</td>
                                    <td>{{ number_format((float) $usage->quantity, 3, ',', '.') }} {{ $usage->unit }}</td>
                                    <td>{{ number_format((float) $usage->line_total_net, 2, ',', '.') }} €</td>
                                    <td>
                                        {{ optional($usage->timesheet?->work_date)->format('d.m.Y') ?: ($usage->timesheet ? ('#' . $usage->timesheet->id) : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Anhänge') }} ({{ $visibleCounts['attachments'] }})</h2>
            @if ($attachments->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Anhänge sichtbar.') }}</p>
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($attachments as $attachment)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('attachments.download', $attachment) }}" class="link link-hover truncate">{{ $attachment->original_name }}</a>
                            <span class="text-base-content/60">{{ $attachment->humanSize() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </x-page-shell>
@endsection
