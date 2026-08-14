{{--
  Created on   : Mon Jul 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : identifier-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props(['issues' => []])

@if (! empty($issues))
    <div class="alert alert-warning items-start" role="alert">
        <div class="min-w-0">
            <p class="font-semibold">{{ __('stammdaten.identifier.heading') }}</p>
            <p class="text-sm opacity-80">{{ __('stammdaten.identifier.hint') }}</p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($issues as $issue)
                    <li>
                        @if (! empty($issue['context']))
                            <span class="opacity-70">{{ $issue['context'] }}:</span>
                        @endif
                        <span class="font-mono">{{ __('stammdaten.identifier.field.' . $issue['field']) }}</span>
                        <span class="font-mono opacity-70">{{ $issue['value'] }}</span>
                        — {{ $issue['reason'] }}
                        @if (! empty($issue['suggestion']))
                            <span class="opacity-80">{{ __('stammdaten.identifier.suggestion', ['value' => $issue['suggestion']]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
