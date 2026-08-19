{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Umfragen'))
@section('nav-title', __('Umfragen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Wiederverwendbare Fragebögen mit Einmal-Links und Ermüdungsschutz — keine Marketing-Automation.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('surveys.create')" show-label>{{ __('Fragebogen anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($surveys->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">reviews</span>'
                       :title="__('Noch keine Fragebögen.')"
                       :message="__('NPS, Projektabschluss-Feedback oder freie Umfragen — angelegt in Minuten, versendet als Einmal-Link.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Fragebogen') }}</th>
                    <th>{{ __('Fragen') }}</th>
                    <th>{{ __('Einladungen') }}</th>
                    <th>{{ __('Antworten') }}</th>
                    <th>{{ __('Eigenschaften') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($surveys as $survey)
                <tr class="hover">
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('surveys.show', $survey) }}">{{ $survey->title }}</a>
                        @unless ($survey->active)
                            <span class="badge badge-ghost badge-xs align-middle">{{ __('inaktiv') }}</span>
                        @endunless
                    </td>
                    <td class="text-sm tabular-nums">{{ $survey->questions_count }}</td>
                    <td class="text-sm tabular-nums">{{ $survey->invitations_count }}</td>
                    <td class="text-sm tabular-nums">{{ $survey->responses_count }}</td>
                    <td class="text-sm text-base-content/70">
                        @if ($survey->anonymous)<span class="badge badge-outline badge-xs">{{ __('anonym') }}</span>@endif
                        @if ($survey->trigger_on_ticket_close)<span class="badge badge-outline badge-xs">{{ __('nach Ticketabschluss') }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$surveys" standing />
    @endif
</x-index-page>
@endsection
