{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : damage-cases-card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schadensfälle an einer Akte (MVP-920); nur mit Recht damage.viewAny sichtbar. --}}
@props(['subject'])
@can('viewAny', \App\Models\Damage\DamageCase::class)
    @php($cases = $subject->damageCases()->get())
    <x-card :title="__('damage.card.title')" icon="car_crash" :count="$cases->count()">
        @if ($cases->isEmpty())
            <p class="text-sm text-muted">{{ __('damage.card.none') }}</p>
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($cases as $case)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a class="link font-mono" href="{{ route('damage-cases.show', $case) }}">{{ $case->number }}</a>
                        <span class="min-w-0 flex-1 truncate">{{ $case->title }}</span>
                        <x-status-badge size="sm" outline :tone="$case->status->tone()">{{ $case->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
        @can('create', \App\Models\Damage\DamageCase::class)
            <div class="mt-3 flex justify-end">
                <x-icon-btn icon="add" size="xs" data-entry-modal-trigger
                            :href="route('damage-cases.create', ['subject_type' => $subject->getMorphClass(), 'subject' => $subject->sqid])"
                            show-label>{{ __('damage.action.report') }}</x-icon-btn>
            </div>
        @endcan
    </x-card>
@endcan
