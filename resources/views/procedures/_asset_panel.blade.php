{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _asset_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Cockpit-Panel „Prozeduren“ der Asset-Akte (MVP-1571): Läufe am Asset und
  Vorlagen, die Kategorie, Produkt oder SLA dem Asset zuordnen.
  Variablen: $asset, $runs, $suggested
--}}
<x-card as="section" id="cockpit-procedures" :title="__('procedure.title.panel')" icon="rule" :count="$runs->count()">
    @if ($runs->isNotEmpty())
        <ul class="mb-3 space-y-2">
            @foreach ($runs as $run)
                <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200/40 px-3 py-2">
                    <div>
                        <span class="font-medium">{{ $run->templateVersion?->template?->displayName() ?? '—' }}</span>
                        <span class="ml-1 text-xs text-muted">v{{ $run->templateVersion?->version }}</span>
                        <x-status-badge :tone="$run->status === \App\Enums\Procedure\ProcedureRunStatus::Completed ? 'success' : ($run->status === \App\Enums\Procedure\ProcedureRunStatus::Aborted ? 'neutral' : 'warning')" class="ml-2">{{ $run->status->label() }}</x-status-badge>
                    </div>
                    <div class="flex items-center gap-1">
                        <x-icon-btn :icon="$run->status->isActive() ? 'play_arrow' : 'visibility'" :tone="$run->status->isActive() ? 'primary' : 'ghost'" size="xs"
                                    :href="route('procedure-runs.show', $run)" :label="__('procedure.run.open')" />
                        <x-icon-btn icon="print" tone="outline" size="xs" :href="route('procedure-runs.print', $run)" target="_blank" :label="__('procedure.action.print')" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($suggested->isNotEmpty())
        <p class="mb-2 text-xs text-muted">{{ __('procedure.panel.suggestedAsset') }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($suggested as $template)
                <form method="POST" action="{{ route('procedure-runs.start-asset', [$asset, $template]) }}">
                    @csrf
                    <x-icon-btn icon="play_arrow" tone="primary" size="xs" type="submit" show-label>{{ $template->displayName() }}</x-icon-btn>
                </form>
            @endforeach
        </div>
    @endif
</x-card>
