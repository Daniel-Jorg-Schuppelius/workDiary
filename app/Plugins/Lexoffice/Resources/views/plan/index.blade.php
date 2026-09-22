{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Ergänzungen zu Ihrem Lexware-Tarif" (Feature 158, MVP-831): Tarifprofil,
     Funktionsmatrix mit Zuständen, lokale Ergänzungen aktivieren, Vorschau
     des Tarifwechsels. Funktioniert ohne API-Verbindung. --}}
@extends('layouts.app')

@section('title', __('lexware.plan_page.title'))
@section('nav-title', __('lexware.menu'))

@php
    use App\Enums\Lexoffice\LexwarePlan;
    use App\Plugins\Lexoffice\Tariff\LexwareTariffService;
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('lexware.plan_page.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="outbox" size="sm" :href="route('lexoffice.handover.index')" show-label>{{ __('lexware.handover.title') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-validation-errors />

    @if ($profile->isTrialExpired())
        <div role="status" class="alert alert-warning">
            <span>{{ __('lexware.plan_page.trial_expired', ['plan' => $effectivePlan->label()]) }}</span>
        </div>
    @endif
    @unless ($billsLocally)
        <div role="status" class="alert alert-info">
            <span>{{ __('lexware.reason.billing_external') }}</span>
        </div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('lexware.plan_page.profile_title')" icon="tune" class="lg:col-span-1">
            <form method="POST" action="{{ route('lexoffice.plan.update') }}" class="space-y-3">
                @csrf
                <x-select-field name="plan" :label="__('lexware.field.plan')" required :hint="__('lexware.hint.plan')">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->value }}" @selected(old('plan', $profile->plan->value) === $plan->value)>{{ $plan->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="plan_source" :label="__('lexware.field.plan_source')" required>
                    <option value="user" @selected(old('plan_source', $profile->source) === 'user')>{{ __('lexware.source.user') }}</option>
                    <option value="provider" @selected(old('plan_source', $profile->source) === 'provider')>{{ __('lexware.source.provider') }}</option>
                </x-select-field>
                <x-input-field name="plan_confirmed_on" type="date" :label="__('lexware.field.plan_confirmed_on')" :value="old('plan_confirmed_on', $profile->confirmedOn?->toDateString())" />
                <x-input-field name="trial_ends_on" type="date" :label="__('lexware.field.trial_ends_on')" :value="old('trial_ends_on', $profile->trialEndsOn?->toDateString())" :hint="__('lexware.hint.trial')" />
                <x-select-field name="trial_successor_plan" :label="__('lexware.field.trial_successor_plan')">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->value }}" @selected(old('trial_successor_plan', $profile->trialSuccessorPlan->value) === $plan->value)>{{ $plan->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="handover_channel" :label="__('lexware.field.handover_channel')" required :hint="__('lexware.hint.handover_channel')">
                    <option value="{{ LexwareTariffService::CHANNEL_MANUAL }}" @selected(old('handover_channel', $profile->handoverChannel) === LexwareTariffService::CHANNEL_MANUAL)>{{ __('lexware.channel.manual') }}</option>
                    <option value="{{ LexwareTariffService::CHANNEL_API }}" @selected(old('handover_channel', $profile->handoverChannel) === LexwareTariffService::CHANNEL_API)>{{ __('lexware.channel.api') }}</option>
                </x-select-field>

                <fieldset class="wd-fieldset">
                    <span class="fieldset-label">{{ __('lexware.field.local_features') }}</span>
                    <div class="grid gap-1">
                        @foreach ($localFeatures as $feature)
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="checkbox" name="local_features[]" value="{{ $feature->value }}" class="checkbox checkbox-sm"
                                       @checked(in_array($feature->value, old('local_features', array_map(fn ($f) => $f->value, $profile->localFeatures)), true))>
                                <span class="label-text text-sm">{{ $feature->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="text-xs text-muted">{{ __('lexware.hint.local_features') }}</span>
                </fieldset>

                @if ($canEdit)
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('lexware.action.save') }}</button>
                @else
                    <p class="text-xs text-muted">{{ __('lexware.plan_page.read_only') }}</p>
                @endif
            </form>
        </x-card>

        <x-card :title="__('lexware.plan_page.matrix_title')" icon="checklist" class="lg:col-span-2" padding="p-0">
            <div class="px-4 pt-3 text-sm text-muted">
                {{ __('lexware.plan_page.matrix_intro', ['plan' => $effectivePlan->label(), 'version' => $matrix::VERSION]) }}
            </div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('lexware.field.feature') }}</th>
                        <th>{{ __('lexware.field.coverage') }}</th>
                        <th>{{ __('lexware.field.state') }}</th>
                        <th class="text-right">{{ __('lexware.field.action') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($availabilities as $availability)
                    <tr>
                        <td>
                            <span class="font-medium">{{ $availability->feature->label() }}</span>
                            <span class="block text-xs text-muted">{{ $availability->feature->description() }}</span>
                        </td>
                        <td><x-status-badge size="sm" :tone="$availability->coverage->tone()" :label="$availability->coverage->label()" /></td>
                        <td>
                            <x-status-badge size="sm" :tone="$availability->stateTone()" :label="$availability->stateLabel()" />
                            @if ($availability->reason())
                                <span class="block text-xs text-muted">{{ $availability->reason() }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($availability->isActionable() && \Illuminate\Support\Facades\Route::has((string) $availability->actionRoute))
                                <x-icon-btn icon="open_in_new" tone="outline" size="xs" :href="route((string) $availability->actionRoute, $availability->actionParameters)" show-label>{{ __('lexware.action.open') }}</x-icon-btn>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <p class="px-4 py-3 text-xs text-muted">{{ __('lexware.plan_page.matrix_note') }}</p>
        </x-card>
    </div>

    <x-card :title="__('lexware.plan_page.preview_title')" icon="swap_horiz">
        <p class="text-sm">{{ __('lexware.plan_page.preview_intro') }}</p>
        <ul class="mt-2 list-disc pl-5 text-sm">
            <li>{{ trans_choice('lexware.plan_page.preview_schedules', $preview['active_schedules'], ['count' => $preview['active_schedules']]) }}</li>
            <li>{{ trans_choice('lexware.plan_page.preview_handovers', $preview['open_handovers'], ['count' => $preview['open_handovers']]) }}</li>
            <li>{{ $preview['api_allowed'] ? __('lexware.plan_page.preview_api_yes') : __('lexware.plan_page.preview_api_no') }}</li>
        </ul>
        <p class="mt-2 text-xs text-muted">{{ __('lexware.plan_page.preview_note') }}</p>
    </x-card>
</x-page-shell>
@endsection
