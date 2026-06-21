{{-- Tab: Wiederkehr — erwartet: $project, $recurrenceRules --}}
<div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
    <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
        <div>
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Wiederkehr-Regeln') }}</span>
            <p class="mt-0.5 text-xs text-base-content/60">
                {{ __('Erzeugen automatisch Aufträge im Voraus. Generator läuft per Cron oder via Button „Jetzt erzeugen".') }}
            </p>
        </div>
        @can('update', $project)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.recurrence-rules.create', $project)"
                        show-label>{{ __('Regel') }}</x-icon-btn>
        @endcan
    </header>
    @if ($recurrenceRules->isEmpty())
        <div class="p-4">
            <x-empty-state compact
                icon='<span class="material-symbols-outlined" aria-hidden="true">repeat</span>'
                :title="__('Noch keine Wiederkehr-Regeln angelegt.')" />
        </div>
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($recurrenceRules as $rule)
                <li class="flex flex-wrap items-start gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">{{ $rule->name }}</span>
                            <x-status-badge size="xs" outline>{{ $rule->frequencyLabel() }}</x-status-badge>
                            @if ($rule->interval > 1)
                                <x-status-badge tone="ghost" size="xs">{{ __('alle :n', ['n' => $rule->interval]) }}</x-status-badge>
                            @endif
                            @if ($rule->byweekday)
                                <x-status-badge tone="ghost" size="xs">{{ $rule->byweekday }}</x-status-badge>
                            @endif
                            @if (! $rule->is_active)
                                <x-status-badge tone="warning" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-base-content/60">
                            {{ __('Ab') }} {{ $rule->starts_on?->fdate() }}
                            @if ($rule->ends_on)
                                · {{ __('bis') }} {{ $rule->ends_on->fdate() }}
                            @endif
                            @if ($rule->last_generated_until)
                                · {{ __('erzeugt bis') }} {{ $rule->last_generated_until->fdate() }}
                            @endif
                        </div>
                        @if ($rule->title_template)
                            <div class="mt-1 line-clamp-1 text-xs text-base-content/50">
                                {{ $rule->title_template }}
                            </div>
                        @endif
                    </div>
                    <div class="flex gap-1">
                        @can('update', $project)
                            <x-action-form :action="route('projects.recurrence-rules.run', [$project, $rule])">
                                <button type="submit" class="btn btn-xs btn-ghost" title="{{ __('Jetzt Aufträge erzeugen') }}">
                                    <x-icon name="play_arrow" />
                                </button>
                            </x-action-form>
                            <a href="{{ route('projects.recurrence-rules.edit', [$project, $rule]) }}"
                               data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Edit') }}</a>
                            <x-action-form :action="route('projects.recurrence-rules.destroy', [$project, $rule])" method="DELETE"
                                  :confirm="__('Bereits erzeugte Aufträge bleiben erhalten, die Regel wird entfernt.')"
                                  :confirm-label="__('Löschen')"
                                  data-confirm-title="{{ __('Regel löschen') }}">
                                <button class="btn btn-xs btn-ghost text-error">{{ __('Del') }}</button>
                            </x-action-form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
