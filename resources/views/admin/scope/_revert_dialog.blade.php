{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _revert_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rücknahme des kompakten Einstiegs (Feature 165, MVP-1196) --}}
@php
    /** @var array{available: bool, taken_at: ?string, conflicts: list<string>, changes: array<string, array{from: mixed, to: mixed}>} $preview */
    /** @var \Closure(string): string $moduleLabel */
    /** @var \Closure(string): string $workspaceLabel */
    /** @var array<string, string> $startPageLabels */
    $format = function (string $dimension, mixed $value) use ($moduleLabel, $workspaceLabel, $startPageLabels): string {
        if ($value === null) {
            return $dimension === 'workspaces' ? __('scope.revert.value_all') : __('scope.revert.value_none');
        }
        if (! is_array($value)) {
            return $dimension === 'default' ? $workspaceLabel((string) $value) : (string) $value;
        }
        if ($value === []) {
            return __('scope.revert.value_none');
        }
        return match ($dimension) {
            'modules' => implode(', ', array_map($moduleLabel, array_map('strval', $value))),
            'workspaces' => implode(', ', array_map($workspaceLabel, array_map('strval', $value))),
            'roles' => implode(', ', array_map(static fn (string $role, string $key) => (\App\Enums\User\UserRole::tryFrom($role)?->label() ?? $role) . ' → ' . $workspaceLabel($key), array_keys($value), array_values(array_map('strval', $value)))),
            'startpages' => implode(', ', array_map(static fn (string $role, string $route) => (\App\Enums\User\UserRole::tryFrom($role)?->label() ?? $role) . ' → ' . ($startPageLabels[$route] ?? $route), array_keys($value), array_values(array_map('strval', $value)))),
            default => implode(', ', array_map('strval', $value)),
        };
    };
@endphp
<x-modal
    :title="__('scope.revert.title')"
    :eyebrow="$preview['taken_at'] !== null ? __('scope.revert.taken_at', ['date' => \Carbon\CarbonImmutable::parse($preview['taken_at'])->fdatetime()]) : null"
    icon="undo"
    tone="warning"
    size="wide"
    :action="route('admin.scope.revert.apply')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('scope.revert.submit')"
>
    <p class="text-sm text-base-content/80">{{ __('scope.revert.subtitle') }}</p>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wider opacity-60">{{ __('scope.revert.changes_heading') }}</h3>
    @if ($preview['changes'] === [])
        <p class="mt-1 text-sm text-muted">{{ __('scope.revert.no_changes') }}</p>
    @else
        <x-detail-grid layout="cells" small-labels class="mt-1">
            @foreach ($preview['changes'] as $dimension => $change)
                <x-detail-grid.row :label="__('scope.revert.dimension.' . $dimension)" full>
                    <span class="line-through decoration-base-content/40">{{ $format($dimension, $change['from']) }}</span>
                    <span class="mx-1 text-muted">&rarr;</span>
                    <span>{{ $format($dimension, $change['to']) }}</span>
                </x-detail-grid.row>
            @endforeach
        </x-detail-grid>
    @endif

    @if ($preview['conflicts'] !== [])
        <div class="mt-4 rounded-box border border-warning/40 bg-warning/10 p-3 text-sm">
            <h3 class="font-semibold">{{ __('scope.revert.conflicts_heading') }}</h3>
            <p class="mt-1 text-xs text-base-content/80">{{ __('scope.revert.conflicts_hint') }}</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach ($preview['conflicts'] as $dimension)
                    <li>{{ __('scope.revert.dimension.' . $dimension) }}</li>
                @endforeach
            </ul>
            <x-checkbox-field name="override_conflicts" class="mt-2" :label="__('scope.revert.override')" :checked="(bool) old('override_conflicts', false)" />
        </div>
    @endif
</x-modal>
