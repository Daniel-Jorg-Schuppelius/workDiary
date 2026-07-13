{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Problem-Detail (Feature 065, MVP-156): Ursachenanalyse, Statuswechsel
     aus der Service-Matrix, Wirksamkeitsblock, Known-Error-Veröffentlichung
     (idempotent) und verknüpfte Incidents (Pivot). --}}

@extends('layouts.app')
@section('title', __('Problem') . ': ' . $problem->title)
@section('nav-title', __('Problem'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$problem->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('servicedesk.problems.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @if ($canManage)
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('servicedesk.problems.edit', $problem)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge size="md" outline>{{ $statusLabels[$problem->status] ?? $problem->status }}</x-status-badge>
            <x-status-badge tone="ghost" size="md">{{ $problem->visibility === 'customer' ? __('Kundenportal') : __('Intern') }}</x-status-badge>
            <span class="ml-auto text-sm text-base-content/60">
                {{ __('Verantwortlich') }}: {{ $problem->owner?->name ?? '—' }}
            </span>
        </div>

        @if ($problem->description)
            <div class="mt-4">
                <div class="text-xs uppercase text-base-content/60 mb-1">{{ __('Beschreibung') }}</div>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $problem->description }}</div>
            </div>
        @endif
    </x-card>

    <x-card :title="__('Ursachenanalyse')" icon="troubleshoot">
        <dl class="grid grid-cols-1 gap-y-3 text-sm">
            <div><dt class="text-base-content/60">{{ __('Ursache') }}</dt><dd class="whitespace-pre-wrap">{{ $problem->root_cause ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Evidenz') }}</dt><dd class="whitespace-pre-wrap">{{ $problem->evidence ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Workaround') }}</dt><dd class="whitespace-pre-wrap">{{ $problem->workaround ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Dauerhafte Lösung') }}</dt><dd class="whitespace-pre-wrap">{{ $problem->permanent_fix ?: '—' }}</dd></div>
        </dl>
    </x-card>

    @if ($canManage && $transitions !== [])
        @include('helpdesk.problems._transition_dialog')
    @endif

    <x-card :title="__('Wirksamkeitsprüfung')" icon="fact_check">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-base-content/60">{{ __('Fällig am') }}</dt><dd>{{ $problem->effectiveness_check_due_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Geprüft am') }}</dt><dd>{{ $problem->effectiveness_checked_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
        </dl>
        @if ($problem->effectiveness_result)
            <div class="mt-3">
                <div class="text-xs uppercase text-base-content/60 mb-1">{{ __('Ergebnis') }}</div>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $problem->effectiveness_result }}</div>
            </div>
        @endif

        @if ($canManage && $problem->effectiveness_check_due_at !== null && $problem->effectiveness_checked_at === null)
            <form method="POST" action="{{ route('servicedesk.problems.effectiveness', $problem) }}" class="mt-3">
                @csrf
                <label class="fieldset-label" for="effectiveness-result">{{ __('Ergebnis der Prüfung') }}</label>
                <textarea id="effectiveness-result" name="result" rows="2" required minlength="3" maxlength="2000"
                          class="textarea textarea-bordered w-full"
                          placeholder="{{ __('Greift die Lösung? Neue Incidents seit dem Fix?') }}"></textarea>
                <div class="mt-2">
                    <x-icon-btn icon="fact_check" tone="primary" size="sm" type="submit" show-label>{{ __('Prüfung dokumentieren') }}</x-icon-btn>
                </div>
                @error('result')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </form>
        @endif
    </x-card>

    <x-card :title="__('Known Error & Wissensbasis')" icon="school">
        @if ($article !== null)
            <p class="text-sm">
                <span class="text-base-content/60">{{ __('Wissensartikel') }}:</span>
                <a class="link link-hover font-medium" href="{{ route('knowledge.show', $article) }}">{{ $article->title }}</a>
            </p>
        @else
            <p class="text-sm text-base-content/60">{{ __('Noch kein Wissensartikel veröffentlicht.') }}</p>
        @endif

        @if ($canManage)
            <x-action-form :action="route('servicedesk.problems.publish', $problem)"
                           method="POST" class="mt-3">
                <x-icon-btn icon="publish" tone="primary" size="sm" type="submit"
                            show-label>{{ __('Known Error veröffentlichen') }}</x-icon-btn>
            </x-action-form>
            <p class="text-xs text-base-content/60 mt-1">{{ __('Idempotent — ein erneutes Veröffentlichen verweist auf den bestehenden Artikel.') }}</p>
        @endif
    </x-card>

    @if ($problem->changes->isNotEmpty())
        {{-- Changes zu diesem Problem (MVP-157, changes.problem_id) — rein informativ. --}}
        <x-card :title="__('Zugeordnete Changes')" icon="published_with_changes">
            <ul class="space-y-1 text-sm">
                @foreach ($problem->changes as $change)
                    <li class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('servicedesk.changes.show', $change) }}" class="link link-hover">{{ $change->title }}</a>
                        <x-status-badge tone="ghost" size="xs">{{ \App\Http\Controllers\Helpdesk\ChangeController::typeLabels()[$change->change_type] ?? $change->change_type }}</x-status-badge>
                        <x-status-badge size="xs" outline>{{ \App\Http\Controllers\Helpdesk\ChangeController::statusLabels()[$change->status] ?? $change->status }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :title="__('Verknüpfte Incidents')" icon="link">
        @if ($problem->tickets->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Keine Incidents verknüpft.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($problem->tickets as $ticket)
                    <li>
                        <a href="{{ route('service-tickets.show', $ticket) }}" class="link link-hover">
                            <span class="font-mono text-xs">{{ $ticket->ticket_no }}</span>
                            {{ $ticket->title }}
                        </a>
                        <x-status-badge size="xs" outline>{{ $ticket->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-page-shell>
@endsection
