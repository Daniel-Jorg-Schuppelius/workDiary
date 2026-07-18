{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Aufbewahrungs-Review (Restpunkt 66/67): Fristen je Rechtsraum +
     Lösch-Vorschläge mit zweistufiger Bestätigung (approve → purge). --}}

@extends('layouts.app')

@section('title', __('Aufbewahrung & Löschung'))
@section('nav-title', __('Aufbewahrung & Löschung'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Aufbewahrung & Löschung') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Rechtsraum :region — der Scan erzeugt Vorschläge, gelöscht wird erst nach Bestätigung (zweistufig, auditiert).', ['region' => $region]) }}</x-slot:subtitle>
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('dataprotection.retention.scan') }}">
                    @csrf
                    <x-icon-btn icon="radar" tone="primary" size="sm" type="submit"
                                show-label>{{ __('Jetzt scannen') }}</x-icon-btn>
                </form>
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    <x-card :title="__('Fristen je Bereich')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Bereich') }}</th>
                    <th class="text-right">{{ __('Frist (Jahre)') }}</th>
                    <th>{{ __('Rechtsgrundlage') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($areas as $area)
                <tr>
                    <td>{{ $area['label'] }}</td>
                    <td class="text-right tabular-nums">{{ $area['years'] ?? '—' }}</td>
                    <td class="text-sm text-base-content/70">{{ $area['basis'] ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-card :title="__('Lösch-Vorschläge')">
        @if ($proposals->isEmpty())
            <x-empty-state icon="auto_delete" :title="__('Keine offenen Lösch-Vorschläge.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Bereich') }}</th>
                        <th>{{ __('Datensatz') }}</th>
                        <th>{{ __('Frist abgelaufen seit') }}</th>
                        <th>{{ __('Begründung') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($proposals as $proposal)
                    <tr>
                        <td class="text-sm">{{ config('retention.areas.' . $proposal->area . '.label', $proposal->area) }}</td>
                        <td class="text-xs">{{ \App\Support\EntityType::label($proposal->subject_type) }} <span class="font-mono text-base-content/60">#{{ $proposal->subject_id }}</span></td>
                        <td class="tabular-nums text-sm">{{ $proposal->retention_until->format('d.m.Y') }}</td>
                        <td class="max-w-md truncate text-sm text-base-content/70">{{ $proposal->reason }}</td>
                        <td>
                            @if ($proposal->status === \App\Models\Privacy\RetentionProposal::STATUS_PENDING)
                                <x-status-badge tone="warning" size="xs">{{ __('offen') }}</x-status-badge>
                            @else
                                <x-status-badge tone="info" size="xs">{{ __('bestätigt') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($canManage)
                                <div class="flex justify-end gap-1">
                                    @if ($proposal->status === \App\Models\Privacy\RetentionProposal::STATUS_PENDING)
                                        <form method="POST" action="{{ route('dataprotection.retention.decide', $proposal) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="approve">
                                            <x-icon-btn icon="check" tone="ghost" size="xs" type="submit" :label="__('Bestätigen')" />
                                        </form>
                                        <form method="POST" action="{{ route('dataprotection.retention.decide', $proposal) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <x-icon-btn icon="close" tone="ghost" size="xs" type="submit" :label="__('Ablehnen')" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('dataprotection.retention.decide', $proposal) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="purge">
                                            <x-icon-btn icon="delete_forever" tone="error" size="xs" type="submit" :label="__('Endgültig löschen')" />
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>

            @if ($canManage)
                @php
                    $approvedAreas = $proposals->getCollection()
                        ->where('status', \App\Models\Privacy\RetentionProposal::STATUS_APPROVED)
                        ->pluck('area')->unique();
                @endphp
                @if ($approvedAreas->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($approvedAreas as $approvedArea)
                            <form method="POST" action="{{ route('dataprotection.retention.purge-area') }}">
                                @csrf
                                <input type="hidden" name="area" value="{{ $approvedArea }}">
                                <x-icon-btn icon="delete_sweep" tone="error" size="sm" type="submit"
                                            show-label>{{ __('Bestätigte in :area löschen', ['area' => config('retention.areas.' . $approvedArea . '.label', $approvedArea)]) }}</x-icon-btn>
                            </form>
                        @endforeach
                    </div>
                @endif
            @endif
        @endif

        <div class="mt-3">
            <x-pagination :paginator="$proposals" />
        </div>
    </x-card>
</x-page-shell>
@endsection
