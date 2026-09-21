{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : plugin-health.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gespeicherter Health-Stand eines Plugins (Scheduler bzw. letzter Test) plus
     asynchroner Sofort-Test — die Seite selbst pingt nicht mehr (UI-Fuzz 2026-09-21:
     ein hängender Server blockierte den Seitenaufbau). --}}
@props(['pluginId', 'state' => null, 'detailed' => false])
@php
    $health = \App\Enums\Plugin\PluginHealthStatus::tryFrom((string) $state?->last_health_status);
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-sm']) }}>
    <x-status-badge size="sm" :tone="$health?->tone() ?? 'ghost'">{{ $health?->label() ?? __('Noch nicht geprüft') }}</x-status-badge>
    @if ($detailed && $state?->last_health_message)
        <span class="text-base-content/70">{{ $state->last_health_message }}</span>
    @endif
    @if ($state?->last_health_check_at)
        <span class="text-xs text-muted">{{ __('zuletzt :time geprüft', ['time' => $state->last_health_check_at->diffForHumans()]) }}</span>
    @endif
    <span x-data="pluginHealthCheck({{ \Illuminate\Support\Js::from(route('admin.plugins.health-check', $pluginId)) }}, {{ \Illuminate\Support\Js::from(csrf_token()) }}, {{ \Illuminate\Support\Js::from(__('Verbindung fehlgeschlagen.')) }})">
        <button type="button" class="btn btn-ghost btn-xs" :disabled="testing" @click="run()">
            <span x-show="idle">{{ __('Verbindung testen') }}</span>
            <span x-show="testing" x-cloak>{{ __('Wird geprüft …') }}</span>
        </button>
        <template x-if="result">
            <span class="ml-1" x-text="resultText"></span>
        </template>
    </span>
</div>
