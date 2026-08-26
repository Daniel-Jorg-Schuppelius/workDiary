{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _procedure_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prozedur-Panel der Auftrags-Detailseite (Feature 026): laufende/abge-
  schlossene Läufe (mit Druck-Link) sowie automatisch vorgeschlagene,
  anwendbare Vorlagen (ProcedureApplicabilityResolver) zum Starten.
  Variablen: $diary, $procedureRuns, $suggestedProcedures
--}}
@if ($procedureRuns->isNotEmpty() || $suggestedProcedures->isNotEmpty())
    <x-card>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted">
                <x-icon name="rule" class="text-muted" />
                <x-term glossary="prozedur">{{ __('procedure.title.panel') }}</x-term>
            </h2>
            <x-help-button topic="procedures.run" :label="__('Hilfe zu Prozedur')" />
        </div>

        @if ($procedureRuns->isNotEmpty())
            <ul class="mb-3 space-y-2">
                @foreach ($procedureRuns as $run)
                    <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200/40 px-3 py-2">
                        <div>
                            <span class="font-medium">{{ $run->templateVersion?->template?->name ?? '—' }}</span>
                            <span class="ml-1 text-xs text-muted">v{{ $run->templateVersion?->version }}</span>
                            <x-status-badge :tone="$run->status->value === 'completed' ? 'success' : ($run->status->value === 'aborted' ? 'neutral' : 'warning')" class="ml-2">{{ $run->status->label() }}</x-status-badge>
                        </div>
                        <div class="flex items-center gap-1">
                            @if (in_array($run->status->value, ['open', 'inProgress', 'blocked'], true))
                                <x-icon-btn icon="play_arrow" tone="primary" size="xs" show-label
                                            :href="route('procedure-runs.show', $run)">{{ __('procedure.run.open') }}</x-icon-btn>
                            @else
                                <x-icon-btn icon="visibility" tone="ghost" size="xs"
                                            :href="route('procedure-runs.show', $run)"
                                            :label="__('procedure.run.open')" />
                            @endif
                            <x-icon-btn icon="print" tone="outline" size="xs"
                                        :href="route('procedure-runs.print', $run)"
                                        target="_blank"
                                        :label="__('procedure.action.print')" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($suggestedProcedures->isNotEmpty())
            <p class="mb-2 text-xs text-muted">{{ __('procedure.panel.suggested') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($suggestedProcedures as $tpl)
                    <form method="POST" action="{{ route('procedure-runs.start', [$diary, $tpl]) }}">
                        @csrf
                        <x-icon-btn icon="play_arrow" tone="primary" size="xs" type="submit" show-label>{{ $tpl->name }}</x-icon-btn>
                    </form>
                @endforeach
            </div>
        @endif
    </x-card>
@endif
