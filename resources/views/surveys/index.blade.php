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
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Wiederverwendbare Fragebögen mit Einmal-Links und Ermüdungsschutz — keine Marketing-Automation.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('surveys.create')" show-label>{{ __('Fragebogen anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($surveys->isEmpty())
        <x-empty-state framed icon="reviews"
                       :title="__('Noch keine Fragebögen.')"
                       :message="__('NPS, Projektabschluss-Feedback oder freie Umfragen — angelegt in Minuten, versendet als Einmal-Link.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Fragebogen') }}</th>
                    <th>{{ __('Fragen') }}</th>
                    <th>{{ __('Einladungen') }}</th>
                    <th>{{ __('Eingegangene Antworten') }}</th>
                    <th>{{ __('Eigenschaften') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($surveys as $survey)
                <tr class="hover">
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('surveys.show', $survey) }}">{{ $survey->title }}</a>
                        @unless ($survey->active)
                            <x-status-badge size="xs" class="align-middle">{{ __('inaktiv') }}</x-status-badge>
                        @endunless
                    </td>
                    <td class="text-sm tabular-nums">{{ $survey->questions_count }}</td>
                    <td class="text-sm tabular-nums">{{ $survey->invitations_count }}</td>
                    <td class="text-sm tabular-nums">{{ $survey->responses_count }}</td>
                    <td class="text-sm text-base-content/70">
                        @if ($survey->anonymous)<x-status-badge tone="plain" size="xs" outline>{{ __('anonym') }}</x-status-badge>@endif
                        @if ($survey->trigger_on_ticket_close)<x-status-badge tone="plain" size="xs" outline>{{ __('nach Ticketabschluss') }}</x-status-badge>@endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$surveys" standing />
    @endif
</x-index-page>
@endsection
