{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : onboarding.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Persönlicher Einstieg je Rolle (MVP-911). --}}
@extends('layouts.app')

@section('title', __('onboarding.personal.title'))
@section('nav-title', __('onboarding.personal.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('onboarding.personal.title')" :subtitle="__('onboarding.personal.progress', ['done' => $checklist['done'], 'total' => $checklist['total']])">
            <x-slot:actions>
                @unless ($checklist['dismissed'])
                    <x-action-form :action="route('me.onboarding.dismiss')">
                        <x-icon-btn icon="visibility_off" size="sm" tone="ghost" type="submit" show-label>{{ __('onboarding.personal.dismiss') }}</x-icon-btn>
                    </x-action-form>
                @endunless
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <p class="mb-3 text-sm text-muted">{{ __('onboarding.personal.description') }}</p>
        <ul class="divide-y divide-base-300">
            @foreach ($checklist['steps'] as $step)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div class="flex items-start gap-2">
                        <x-icon :name="$step['done'] ? 'check_circle' : 'radio_button_unchecked'" @class(['text-success' => $step['done'], 'text-muted' => ! $step['done']]) />
                        <div>
                            <div class="font-medium">{{ __('onboarding.personal.step.' . $step['code'] . '.title') }}</div>
                            <div class="text-xs text-muted">{{ __('onboarding.personal.step.' . $step['code'] . '.hint') }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <x-icon-btn icon="arrow_forward" size="sm" :href="$step['url']" show-label>{{ __('onboarding.personal.go') }}</x-icon-btn>
                        @if (! $step['done'] && $step['manual'])
                            <x-action-form :action="route('me.onboarding.done', ['step' => $step['code']])">
                                <x-icon-btn icon="done" size="sm" tone="ghost" type="submit" show-label>{{ __('onboarding.personal.mark_done') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>
</x-page-shell>
@endsection
