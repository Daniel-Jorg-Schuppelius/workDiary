{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : attendance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meine Anwesenheit (MVP-845): eigene bestätigte Nachweise im Kopfzeilen-Zeitraum. --}}
@extends('layouts.app')
@section('title', __('club.my.title.attendance'))
@section('nav-title', __('club.my.title.attendance'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
@php
    $hours = intdiv($creditedMinutes, 60);
    $minutes = $creditedMinutes % 60;
@endphp
<x-index-page overflow="clip" :subtitle="$subject->member->fullName() . ' · ' . __('club.my.hint.attendance', ['from' => $range['from']->format('d.m.Y'), 'to' => $range['to']->format('d.m.Y')]) . ' · ' . $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ' ' . __('club.my.label.hours')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.my.index')" show-label>{{ __('club.my.action.back') }}</x-icon-btn>
        <x-help-button topic="club.my" />
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.events.field.title') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th class="text-right">{{ __('club.attendance.field.minutes') }}</th>
                <th class="text-right">{{ __('club.attendance.field.credited') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <tr class="hover">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $record->event?->started_at?->orgTz()->format('d.m.Y H:i') }}</td>
                <td class="text-sm">{{ $record->event?->title }}</td>
                <td>
                    <x-status-badge :tone="$record->status->tone()" size="sm" :icon="$record->status->icon()">{{ $record->status->label() }}</x-status-badge>
                    @if ($record->hasUnresolvedOverlap())
                        <x-status-badge tone="warning" size="xs" :label="__('club.attendance.label.overlap')" />
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $record->minutes ?? '–' }}</td>
                <td class="text-right tabular-nums">{{ $record->sheet ? $record->creditableMinutes($record->sheet) : 0 }}</td>
            </tr>
        @empty
            <x-table.empty icon="fact_check" :colspan="5" :title="__('club.my.empty.attendance')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$records" standing />
</x-index-page>
@endsection
