@props(['issues' => []])

@if (! empty($issues))
    <div class="alert alert-warning items-start" role="alert">
        <div class="min-w-0">
            <p class="font-semibold">{{ __('stammdaten.identifier.heading') }}</p>
            <p class="text-sm opacity-80">{{ __('stammdaten.identifier.hint') }}</p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($issues as $issue)
                    <li>
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
