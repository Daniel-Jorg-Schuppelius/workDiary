{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : plugin-health.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Plugin-Zustand" — Daten: PluginHealthWidget.
--}}
<x-card :title="__('Plugin-Zustand')" icon="extension">
    @if ($failing->isEmpty())
        <x-empty-state compact icon="check_circle"
                       :title="__('Alles in Ordnung')"
                       :message="__(':n Plugins geprüft, keine Störung.', ['n' => $total])" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($failing as $state)
                <li class="rounded-box border border-error/40 bg-error/5 px-3 py-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="min-w-0 truncate font-medium">{{ $state->plugin_id }}</span>
                        <x-status-badge size="xs" tone="error">{{ $state->last_health_status }}</x-status-badge>
                    </div>
                    @if ($state->last_health_message)
                        <p class="text-xs text-muted">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($state->last_health_message, 80) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
