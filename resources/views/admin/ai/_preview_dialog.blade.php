{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Datenfluss-/Prompt-Vorschau je Capability (Feature 025, Leitprinzip 3;
     Feature 016: konfigurierbare externe Dienste mit klarer
     Datenfluss-Dokumentation). Rein lesend — es wird kein Provider gerufen. --}}
<x-modal
    :title="__('ai.preview.title', ['capability' => $definition->label()])"
    icon="visibility"
    tone="info"
>
    <div class="space-y-4 text-sm">
        <div>
            <h4 class="font-semibold">{{ __('ai.preview.data_classes') }}</h4>
            <p class="text-xs text-muted">{{ __('ai.preview.data_classes_help') }}</p>
            <div class="mt-1 flex flex-wrap gap-1">
                @forelse ($definition->dataClasses as $dataClass)
                    <span class="badge badge-outline badge-sm font-mono">{{ $dataClass }}</span>
                @empty
                    <span class="text-muted italic">—</span>
                @endforelse
            </div>
        </div>

        <div>
            <h4 class="font-semibold">{{ __('ai.preview.memory_scopes') }}</h4>
            <div class="mt-1 flex flex-wrap gap-1">
                @forelse ($definition->memoryScopes as $scope)
                    <span class="badge badge-outline badge-sm">{{ __('ai.field.scope_' . $scope) }}</span>
                @empty
                    <span class="text-muted italic">{{ __('ai.preview.no_memory') }}</span>
                @endforelse
            </div>
            <p class="mt-1 text-xs text-muted">
                {{ __('ai.preview.memory_counts', [
                    'glossary' => $memoryCounts['glossary'],
                    'rules' => $memoryCounts['style_rules'],
                    'examples' => $memoryCounts['examples'],
                ]) }}
            </p>
        </div>

        <div>
            <h4 class="font-semibold">{{ __('ai.preview.routing') }}</h4>
            <p class="text-xs {{ $cloudAllowed ? 'text-muted' : 'text-warning' }}">
                {{ $cloudAllowed ? __('ai.preview.cloud_allowed') : __('ai.preview.cloud_blocked') }}
            </p>
            @if ($unavailableReason !== null)
                <div class="alert alert-warning mt-2 text-xs">
                    <span>{{ __('ai.preview.unavailable_' . $unavailableReason) }}</span>
                </div>
            @else
                <ol class="mt-1 list-decimal pl-5">
                    @foreach ($candidates as $candidate)
                        <li>
                            {{ $candidate->name }}
                            <span class="badge badge-{{ $candidate->is_local ? 'success' : 'warning' }} badge-xs">
                                {{ $candidate->is_local ? __('ai.field.local') : __('ai.field.cloud') }}
                            </span>
                            @if ($loop->first)
                                <span class="badge badge-info badge-xs">{{ __('ai.preview.primary') }}</span>
                            @else
                                <span class="badge badge-ghost badge-xs">{{ __('ai.preview.fallback') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <p class="text-xs text-muted">
            {{ __('ai.preview.prompt_version') }}: v{{ $definition->promptVersion }} —
            {{ __('ai.preview.no_call_hint') }}
        </p>
    </div>
</x-modal>
