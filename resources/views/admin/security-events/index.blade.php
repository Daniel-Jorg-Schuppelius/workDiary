{{--
  Created on   : Tue Jul 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Angriffserkennung'))
@section('nav-title', __('Angriffserkennung'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Sicherheitsereignisse der letzten 24 Stunden, Schwellwert-Alarme und Verlauf.')">
    <div class="grid gap-3 lg:grid-cols-3 mb-3 shrink-0">
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60 mb-1">{{ __('Ereignisse (24 h)') }}</div>
            @forelse ($counts as $row)
                <div class="flex justify-between text-xs py-0.5">
                    <code>{{ $row['event'] }}</code>
                    <span class="font-medium">{{ $row['count'] }}</span>
                </div>
            @empty
                <div class="text-sm text-base-content/60">{{ __('Keine Ereignisse') }}</div>
            @endforelse
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60 mb-1">{{ __('Auffällige IPs (24 h)') }}</div>
            @forelse ($topIps as $row)
                <div class="flex justify-between text-xs py-0.5">
                    <code>{{ $row['ip'] }}</code>
                    <span class="font-medium">{{ $row['count'] }}</span>
                </div>
            @empty
                <div class="text-sm text-base-content/60">{{ __('Keine Ereignisse') }}</div>
            @endforelse
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60 mb-1">{{ __('Schwellwert-Regeln') }}</div>
            @foreach ($alarms as $rule)
                <div class="flex justify-between items-center text-xs py-0.5">
                    <span><code>{{ $rule['event'] }}</code> <span class="text-base-content/60">({{ $rule['scope'] }}, {{ $rule['limit'] }}/{{ $rule['window_minutes'] }} min)</span></span>
                    <x-status-badge :tone="$rule['active'] ? 'error' : 'success'" size="sm">
                        {{ $rule['active'] ? __('Alarm') : __('ruhig') }}
                    </x-status-badge>
                </div>
            @endforeach
        </div>
    </div>

    @if ($events->count() === 0)
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">gpp_good</span>'
            :title="__('Keine Sicherheitsereignisse')"
            :message="__('Aktuell sind keine sicherheitsrelevanten Fehlversuche verzeichnet.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th>{{ __('Ereignis') }}</x-table.th>
                    <x-table.th>{{ __('IP') }}</x-table.th>
                    <x-table.th>{{ __('Details') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($events as $event)
                <tr>
                    <td class="text-xs text-base-content/70 whitespace-nowrap">{{ $event->occurred_at->format('d.m.Y H:i:s') }}</td>
                    <td><code class="text-xs">{{ $event->getRawOriginal('event') }}</code></td>
                    <td><code class="text-xs">{{ $event->ip?->getValue() ?? '—' }}</code></td>
                    <td class="text-xs max-w-md truncate" title="{{ collect($event->meta ?? [])->map(fn($v, $k) => $k . '=' . $v)->implode(' ') }}">
                        {{ collect($event->meta ?? [])->map(fn($v, $k) => $k . '=' . $v)->implode(' ') ?: '—' }}
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$events" standing />
    @endif
</x-index-page>
@endsection
