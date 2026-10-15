{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _setup_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einrichtung „Was möchten Sie erledigen?" (Feature 165, MVP-1189) --}}
@php
    /** @var array{key: string, label: string, description: string, version: int, modules: list<string>, default_active: list<string>, workspaces: list<string>, commercial_workspace: ?string, startpages: array<string, string>}|null $package */
    /** @var bool $teamLicensed */
    /** @var \App\Services\Navigation\NavFocusService $focus */
    /** @var array<string, string> $startPageLabels */
    /** @var array<string, string> $startpages */
    /** @var array{mode: string, key: ?string} $profile */
    $catalog = app(\App\Services\Licensing\ModuleCatalog::class);
    $goals = [
        'orders' => ['icon' => 'assignment', 'always' => true, 'workspaces' => ['compact_orders'], 'modules' => ['module.vertrieb'], 'available' => true],
        'time' => ['icon' => 'today', 'always' => true, 'workspaces' => ['compact_day'], 'modules' => [], 'available' => true],
        'team' => ['icon' => 'groups', 'always' => false, 'workspaces' => ['compact_team'], 'modules' => ['module.planung', 'module.auswertungen_team'], 'available' => $teamLicensed],
        'billing' => ['icon' => 'receipt_long', 'always' => false, 'workspaces' => [$package['commercial_workspace'] ?? 'compact_billing'], 'modules' => [], 'available' => true],
    ];
@endphp
<x-modal
    :title="__('scope.setup.title')"
    :eyebrow="$package !== null ? __('scope.setup.eyebrow', ['package' => __($package['label'])]) : null"
    icon="rocket_launch"
    tone="primary"
    size="wide"
    :action="route('admin.scope.setup.apply')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('scope.setup.submit')"
>
    <p class="text-sm text-base-content/80">{{ __('scope.setup.intro') }}</p>

    <fieldset class="mt-4">
        <legend class="mb-2 text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.setup.question') }}</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($goals as $goalKey => $goal)
                <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 bg-base-200/40 p-3 has-checked:border-primary {{ $goal['available'] ? '' : 'opacity-60' }}">
                    <input type="checkbox" name="goals[]" value="{{ $goalKey }}" class="checkbox checkbox-primary checkbox-sm mt-0.5"
                           @checked($goal['always'] || in_array($goalKey, (array) old('goals', []), true))
                           @disabled(! $goal['available'])>
                    @if ($goal['always'])
                        {{-- Immer enthalten: disabled-Checkboxen posten nicht, deshalb mitsenden. --}}
                        <input type="hidden" name="goals[]" value="{{ $goalKey }}">
                    @endif
                    <span class="min-w-0">
                        <span class="flex items-center gap-1.5 font-semibold"><x-icon :name="$goal['icon']" class="text-base" />{{ __('scope.setup.goal.' . $goalKey) }}</span>
                        <span class="block text-xs text-base-content/70">{{ __('scope.setup.goal.' . $goalKey . '_hint') }}</span>
                        <span class="mt-1 block text-xs">
                            <span class="text-muted">{{ __('scope.setup.effect_workspaces') }}:</span>
                            {{ implode(', ', array_map(static fn (string $k): string => $focus->label(null, $k), $goal['workspaces'])) }}
                            @if ($goal['modules'] !== [])
                                · <span class="text-muted">{{ __('scope.setup.effect_modules') }}:</span>
                                {{ implode(', ', array_map(static fn (string $c): string => __($catalog->label($c)), $goal['modules'])) }}
                            @endif
                        </span>
                        @if (! $goal['available'])
                            <span class="mt-1 block text-xs text-warning">{{ __('scope.setup.team_not_licensed') }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <x-card padding="p-3" class="bg-base-200/40">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-60">{{ __('scope.setup.startpages_heading') }}</h3>
            <ul class="mt-1 space-y-0.5 text-sm">
                @foreach ($startpages as $roleValue => $route)
                    @php $role = \App\Enums\User\UserRole::tryFrom($roleValue); @endphp
                    @if ($role !== null && isset($startPageLabels[$route]))
                        <li class="flex justify-between gap-2"><span>{{ $role->label() }}</span><span class="text-muted">{{ $startPageLabels[$route] }}</span></li>
                    @endif
                @endforeach
            </ul>
        </x-card>
        <x-card padding="p-3" class="bg-base-200/40">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-60">{{ __('scope.mode.heading') }}</h3>
            <p class="mt-1 text-sm">{{ __('scope.mode.limited') }}</p>
            <p class="text-xs text-base-content/70">{{ __('scope.mode.limited_hint') }} {{ __('scope.setup.mode_hint') }}</p>
        </x-card>
    </div>

    <p class="mt-3 text-xs text-muted">{{ __('scope.setup.skip_hint') }}</p>
</x-modal>
