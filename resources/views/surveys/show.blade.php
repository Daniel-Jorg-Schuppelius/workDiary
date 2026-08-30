{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Umfrage: :title', ['title' => $survey->title]))
@section('nav-title', __('Umfrage'))

@php /** @var \App\Models\Survey\Survey $survey */ @endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $survey->title }}</span>
                @if ($survey->anonymous)<span class="badge badge-outline badge-sm">{{ __('anonym') }}</span>@endif
                @unless ($survey->active)<span class="badge badge-ghost badge-sm">{{ __('inaktiv') }}</span>@endunless
            </div>
            <x-slot:actions>
                @if ($canManage)
                    <x-action-form :action="route('surveys.toggle-active', $survey)">
                        <x-icon-btn :icon="$survey->active ? 'pause' : 'play_arrow'" size="sm" type="submit" show-label>
                            {{ $survey->active ? __('Deaktivieren') : __('Aktivieren') }}
                        </x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="arrow_back" size="sm" :href="route('surveys.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Fragen')">
                @if ($survey->questions->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Fragen — unten hinzufügen.') }}</p>
                @else
                    <ol class="space-y-2 text-sm">
                        @foreach ($survey->questions as $question)
                            <li class="flex items-start justify-between gap-3 rounded-lg border border-base-300 p-3">
                                <div class="min-w-0">
                                    <span class="badge badge-outline badge-xs mr-2 font-mono">{{ $question->type }}</span>
                                    {{ $question->label }}
                                    @if ($question->options)
                                        <span class="block text-xs text-muted">{{ implode(' · ', $question->options) }}</span>
                                    @endif
                                </div>
                                @if ($canManage)
                                    <x-action-form :action="route('surveys.questions.remove', [$survey, $question])" method="DELETE">
                                        <x-icon-btn icon="delete" size="sm" tone="error" type="submit" :title="__('Frage entfernen')" />
                                    </x-action-form>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if ($canManage)
                    <form method="POST" action="{{ route('surveys.questions.add', $survey) }}" class="mt-4 grid gap-2 sm:grid-cols-4">
                        @csrf
                        <select name="type" class="select select-sm select-bordered" aria-label="{{ __('Fragetyp') }}">
                            <option value="nps">{{ __('NPS (0–10)') }}</option>
                            <option value="scale">{{ __('Skala (1–5)') }}</option>
                            <option value="choice">{{ __('Auswahl') }}</option>
                            <option value="text">{{ __('Freitext') }}</option>
                        </select>
                        <input aria-label="{{ __('Fragetext') }}" type="text" name="label" required maxlength="500" class="input input-sm input-bordered sm:col-span-2" placeholder="{{ __('Fragetext') }}">
                        <input aria-label="{{ __('Auswahl: A, B, C') }}" type="text" name="options" class="input input-sm input-bordered" placeholder="{{ __('Auswahl: A, B, C') }}">
                        <button type="submit" class="btn btn-primary btn-sm sm:col-span-4">{{ __('Frage hinzufügen') }}</button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Letzte Freitext-Antworten')">
                @if ($textAnswers->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Freitext-Antworten.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($textAnswers as $answer)
                            <li class="rounded-lg bg-base-200/60 p-3">{{ $answer->value_text }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Auswertung')">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('NPS-Score') }}</dt>
                        {{-- null heißt „nichts zu rechnen", nicht 0. --}}
                        <dd class="font-medium tabular-nums">{{ $nps ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Antworten') }}</dt><dd class="tabular-nums">{{ $responseCount }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Einladungen') }}</dt><dd class="tabular-nums">{{ $invitations->count() }}</dd></div>
                </dl>
            </x-card>

            @if ($canManage && $survey->active)
                <x-card :title="__('Einladen')">
                    <form method="POST" action="{{ route('surveys.invite', $survey) }}" class="space-y-3">
                        @csrf
                        <x-select-field name="customer" :label="__('Kunde')" required>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->sqid }}">{{ $customer->name }}</option>
                            @endforeach
                        </x-select-field>
                        <p class="text-xs text-muted">{{ __('Der Ermüdungsschutz lehnt Adressen ab, die kürzlich eingeladen wurden — über alle Fragebögen hinweg.') }}</p>
                        <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('Einladung senden') }}</button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('Letzte Einladungen')">
                @if ($invitations->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Einladungen.') }}</p>
                @else
                    <ul class="space-y-1 text-xs">
                        @foreach ($invitations->take(15) as $invitation)
                            <li class="flex justify-between gap-2">
                                <span class="min-w-0 truncate">{{ $invitation->customer?->name ?? $invitation->email }}</span>
                                <span class="shrink-0 text-muted">{{ [
                                    'created' => __('erstellt'),
                                    'sent' => __('versendet'),
                                    'responded' => __('beantwortet'),
                                ][$invitation->status] ?? $invitation->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
