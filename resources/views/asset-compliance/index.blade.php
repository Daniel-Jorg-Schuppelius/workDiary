{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Prüfmittel'))
@section('nav-title', __('Prüfmittel'))

@section('content')
<x-index-page :subtitle="__('Fällige, überfällige, gesperrte und eingeschränkt freigegebene Assets — Sperren und Ausnahmen im gemeinsamen Modell (D12).')">
    <x-slot:actions>
        <x-icon-btn icon="checklist" size="sm" :href="route('asset-compliance.profiles.index')" show-label>{{ __('Prüfprofile') }}</x-icon-btn>
        <x-icon-btn icon="event_available" size="sm" :href="route('asset-compliance.schedules.index')" show-label>{{ __('Prüfkalender') }}</x-icon-btn>
        <x-icon-btn icon="query_stats" size="sm" :href="route('asset-compliance.reports.index')" show-label>{{ __('Auditbericht') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Prüfpflichten aktiv')" :value="$assignments->count()" />
        <x-kpi-tile :label="__('Bald fällig')" :value="$dueSoon->count()" />
        <x-kpi-tile :label="__('Überfällig')" :value="$overdue->count()" />
        <x-kpi-tile :label="__('Aktive Sperren')" :value="$activeBlocks->count()" />
    </div>

    <x-card :title="__('Prüfpflichten (nach Fälligkeit)')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Prüfprofil') }}</th>
                    <th>{{ __('Fällig am') }}</th>
                    <th>{{ __('Prüfstatus') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($assignments as $assignment)
                <tr>
                    <td>{{ $assignment->asset->name ?? '—' }}</td>
                    <td>{{ $assignment->profile->name ?? '—' }}
                        <span class="text-xs text-muted">({{ $assignment->profile?->inspection_kind->label() }})</span>
                    </td>
                    <td>
                        @if ($assignment->isOverdue())
                            <span class="text-error font-medium">{{ optional($assignment->next_due_on)->fdate() }}</span>
                        @elseif ($assignment->isDueSoon())
                            <span class="text-warning font-medium">{{ optional($assignment->next_due_on)->fdate() }}</span>
                        @else
                            {{ optional($assignment->next_due_on)->fdate() ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @php($status = $assignment->asset !== null ? $statusByAsset->get($assignment->asset->id) : null)
                        <x-status-badge size="md" outline>{{ $status?->label() ?? '—' }}</x-status-badge>
                    </td>
                    <td>{{ $assignment->responsible->name ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty icon="rule_settings" :colspan="5" :title="__('Keine Prüfpflichten — Prüfprofile zuweisen (Prüfprofile-Seite).')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Aktive Sperren & Ausnahmefreigaben (D12)')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Grund') }}</th>
                    <th>{{ __('Seit / Befristung') }}</th>
                    <th>{{ __('Ausnahmen') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($activeBlocks as $block)
                <tr>
                    <td>{{ $block->asset->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-error badge-outline">{{ $block->reason->label() }}</span>
                        @if ($block->note !== null)
                            <span class="block text-xs text-muted">{{ \Illuminate\Support\Str::limit($block->note, 80) }}</span>
                        @endif
                    </td>
                    <td>{{ $block->blocked_from->fdate() }}{{ $block->blocked_until !== null ? ' – ' . $block->blocked_until->fdate() : '' }}</td>
                    <td>
                        @forelse ($block->exceptions as $exception)
                            @if ($exception->revoked_at === null)
                                <span class="badge badge-warning badge-outline badge-sm" title="{{ $exception->reason_text }}">
                                    {{ $exception->context }} {{ __('bis') }} {{ $exception->valid_until->fdate() }}
                                </span>
                                @can('release', \App\Models\AssetCompliance\AssetComplianceProfile::class)
                                    <form method="POST" action="{{ route('asset-compliance.blocks.exception.revoke', $exception) }}" class="inline">@csrf
                                        <button type="submit" class="btn btn-xs btn-ghost">{{ __('Widerrufen') }}</button>
                                    </form>
                                @endcan
                            @endif
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="text-right">
                        @can('release', \App\Models\AssetCompliance\AssetComplianceProfile::class)
                            <details class="inline-block text-left">
                                <summary class="btn btn-xs">{{ __('Ausnahme') }}</summary>
                                <form method="POST" action="{{ route('asset-compliance.blocks.exception', $block) }}" class="mt-2 grid w-72 gap-2 rounded-box border border-base-300 p-3">
                                    @csrf
                                    <x-select-field name="context" :label="__('Einsatzkontext')" required>
                                        <option value="usage">{{ __('Einsatz') }}</option>
                                        <option value="dispatch">{{ __('Disposition') }}</option>
                                        <option value="rental">{{ __('Verleih') }}</option>
                                    </x-select-field>
                                    {{-- :id — Formular wiederholt sich pro Sperre (doppelte ids, I13) --}}
                                    <x-input-field name="valid_until" type="date" :id="'block-exception-valid-until-' . $block->sqid" :label="__('Befristet bis')" required />
                                    <x-textarea-field name="reason_text" :label="__('Pflichtbegründung (min. 20 Zeichen)')" rows="2" required></x-textarea-field>
                                    <button type="submit" class="btn btn-sm btn-warning">{{ __('Ausnahme erteilen') }}</button>
                                </form>
                            </details>
                        @endcan
                        <details class="inline-block text-left">
                            <summary class="btn btn-xs btn-ghost">{{ __('Aufheben') }}</summary>
                            <form method="POST" action="{{ route('asset-compliance.blocks.release', $block) }}" class="mt-2 flex items-end gap-2 rounded-box border border-base-300 p-3">
                                @csrf
                                <x-input-field name="note" :id="'block-release-note-' . $block->sqid" :label="__('Begründung')" required />
                                <button type="submit" class="btn btn-sm">{{ __('Aufheben') }}</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine aktiven Sperren.')" compact />
            @endforelse
        </x-table>
        @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
            <details class="border-t border-base-300 p-3">
                <summary class="cursor-pointer text-sm font-medium">{{ __('Asset manuell sperren') }}</summary>
                <form method="POST" action="{{ route('asset-compliance.blocks.store') }}" class="mt-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <x-select-field name="asset_id" :label="__('Asset')" required>
                        @foreach ($assignments->pluck('asset')->filter()->unique('id') as $asset)
                            <option value="{{ $asset->sqid }}">{{ $asset->name }}</option>
                        @endforeach
                    </x-select-field>
                    <x-select-field name="reason" :label="__('Grund')" required>
                        @foreach (\App\Enums\Asset\AssetBlockReason::cases() as $reason)
                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="blocked_until" type="date" :label="__('Befristet bis (optional)')" />
                    <x-input-field name="note" id="block-create-note" :label="__('Begründung')" required />
                    <button type="submit" class="btn btn-sm btn-error">{{ __('Sperren') }}</button>
                </form>
            </details>
        @endcan
    </x-card>
</x-index-page>
@endsection
