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
        <div class="px-4 py-8 text-center text-sm text-base-content/60">
            {{ __('Noch keine Wiederkehr-Regeln angelegt.') }}
        </div>
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($recurrenceRules as $rule)
                <li class="flex flex-wrap items-start gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">{{ $rule->name }}</span>
                            <span class="badge badge-xs badge-outline">{{ $rule->frequencyLabel() }}</span>
                            @if ($rule->interval > 1)
                                <span class="badge badge-xs badge-ghost">{{ __('alle :n', ['n' => $rule->interval]) }}</span>
                            @endif
                            @if ($rule->byweekday)
                                <span class="badge badge-xs badge-ghost">{{ $rule->byweekday }}</span>
                            @endif
                            @if (! $rule->is_active)
                                <span class="badge badge-xs badge-warning">{{ __('Inaktiv') }}</span>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-base-content/60">
                            {{ __('Ab') }} {{ $rule->starts_on?->format('d.m.Y') }}
                            @if ($rule->ends_on)
                                · {{ __('bis') }} {{ $rule->ends_on->format('d.m.Y') }}
                            @endif
                            @if ($rule->last_generated_until)
                                · {{ __('erzeugt bis') }} {{ $rule->last_generated_until->format('d.m.Y') }}
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
                            <form method="POST" action="{{ route('projects.recurrence-rules.run', [$project, $rule]) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-ghost" title="{{ __('Jetzt Aufträge erzeugen') }}">
                                    <x-icon name="play_arrow" />
                                </button>
                            </form>
                            <a href="{{ route('projects.recurrence-rules.edit', [$project, $rule]) }}"
                               data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('projects.recurrence-rules.destroy', [$project, $rule]) }}"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Regel löschen') }}"
                                  data-confirm-message="{{ __('Bereits erzeugte Aufträge bleiben erhalten, die Regel wird entfernt.') }}"
                                  data-confirm-label="{{ __('Löschen') }}"
                                  class="inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-ghost text-error">{{ __('Del') }}</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
