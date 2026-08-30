{{--
  Created on   : Sat Aug 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-approvals.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Freigabe von Lernzeit außerhalb der Arbeitszeit (Feature 149, MVP-749).
  Bei der Zeitpolitik „Freigabe nötig" entsteht die Anwesenheitsspanne erst
  mit der Zusage — vorher zu buchen und später zurückzunehmen wäre ein
  Eingriff in die Zeitkonten für etwas, das noch niemand entschieden hat.
--}}
@extends('layouts.app')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('title', __('learning.title.time_approvals'))
@section('nav-title', __('learning.title.time_approvals'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.subtitle.time_approvals')" />
    </x-slot:toolbar>

    <x-table scroll="flex" hover :caption="__('learning.title.time_approvals')">
        <x-slot:head>
            <tr>
                <th>{{ __('learning.field.person') }}</th>
                <th>{{ __('learning.field.course') }}</th>
                <th>{{ __('learning.field.period') }}</th>
                <th class="text-right">{{ __('learning.field.minutes_short') }}</th>
                <th class="text-right">{{ __('learning.field.actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($sessions as $session)
            <tr>
                <td>{{ $session->user?->name ?? '—' }}</td>
                <td>{{ $session->enrollment?->course?->title ?? '—' }}</td>
                <td>
                    {{ $session->started_at?->translatedFormat('d.m.Y H:i') }}
                    @if ($session->ended_at) – {{ $session->ended_at->translatedFormat('H:i') }} @endif
                </td>
                <td class="text-right font-mono">{{ intdiv($session->active_seconds, 60) }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <form method="POST" action="{{ route('learning.time-approvals.approve', $session->sqid) }}">
                            @csrf
                            <x-icon-btn icon="check_circle" tone="success" size="xs" type="submit"
                                        :label="__('learning.action.approve_time')" />
                        </form>
                        {{-- Eine Ablehnung braucht eine Begründung: sie kürzt
                             jemandem eine geleistete Stunde. --}}
                        <form method="POST" action="{{ route('learning.time-approvals.reject', $session->sqid) }}"
                              class="flex items-center gap-1">
                            @csrf
                            {{-- Ein Platzhalter ist keine Beschriftung: er
                                 verschwindet beim Tippen (WCAG 3.3.2). --}}
                            <label class="sr-only" for="reason-{{ $session->sqid }}">{{ __('learning.field.reason') }}</label>
                            <input type="text" id="reason-{{ $session->sqid }}" name="reason" required minlength="3" maxlength="255"
                                   placeholder="{{ __('learning.field.reason') }}"
                                   class="input input-bordered input-xs w-40">
                            <x-icon-btn icon="cancel" tone="error" size="xs" type="submit"
                                        :label="__('learning.action.reject_time')" />
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="schedule" :message="__('learning.empty.time_approvals')" colspan="5" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$sessions" standing />
</x-page-shell>
@endsection
