{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rechnungsdaten und Tarifwechsel-Anfrage (MVP-957). Erwartet: $organization, $contact, $plans, $requests --}}
@extends('layouts.app')

@section('title', __('platform_usage.billing_profile.title'))
@section('nav-title', __('platform_usage.billing_profile.title'))

@section('content')
<x-index-page :subtitle="__('platform_usage.billing_profile.subtitle')">
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('platform_usage.billing_profile.contact')">
            <form method="POST" action="{{ route('admin.billing-profile.update') }}" class="grid gap-2 sm:grid-cols-2" data-entry-form>
                @csrf
                @method('PUT')
                <x-input-field name="name" :label="__('platform_usage.billing_profile.field.name')" :value="old('name', $contact['name'] ?? $organization->name)" required />
                <x-input-field name="email" type="email" :label="__('platform_usage.billing_profile.field.email')" :value="old('email', $contact['email'] ?? '')" required />
                <x-input-field name="street" :label="__('platform_usage.billing_profile.field.street')" :value="old('street', $contact['street'] ?? '')" />
                <x-input-field name="zip" :label="__('platform_usage.billing_profile.field.zip')" :value="old('zip', $contact['zip'] ?? '')" />
                <x-input-field name="city" :label="__('platform_usage.billing_profile.field.city')" :value="old('city', $contact['city'] ?? '')" />
                <x-input-field name="country" maxlength="2" :label="__('platform_usage.billing_profile.field.country')" :value="old('country', $contact['country'] ?? 'DE')" />
                <x-input-field name="vat_id" :label="__('platform_usage.billing_profile.field.vat_id')" :value="old('vat_id', $contact['vat_id'] ?? '')" />
                <x-input-field name="reference" :label="__('platform_usage.billing_profile.field.reference')" :value="old('reference', $contact['reference'] ?? '')" :hint="__('platform_usage.billing_profile.hint.reference')" />
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('platform_usage.billing_profile.save') }}</button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('platform_usage.plan_request.title')">
            <p class="mb-2 text-sm">{{ __('platform_usage.plan_request.current', ['plan' => __('platform_usage.plan.' . ($organization->plan ?? 'free'))]) }}</p>
            <form method="POST" action="{{ route('admin.billing-profile.plan-request') }}" class="grid gap-2" data-entry-form>
                @csrf
                <x-select-field name="requested_plan" :label="__('platform_usage.plan_request.field.plan')" required>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan }}">{{ __('platform_usage.plan.' . $plan) }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="requested_addons" :label="__('platform_usage.plan_request.field.addons')" :hint="__('platform_usage.plan_request.hint.addons')" />
                <x-textarea-field name="note" rows="3" :label="__('platform_usage.plan_request.field.note')">{{ old('note') }}</x-textarea-field>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm">{{ __('platform_usage.plan_request.send') }}</button>
                </div>
            </form>
        </x-card>
    </div>

    <x-card :title="__('platform_usage.plan_request.history')" class="mt-4" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('platform_usage.plan_request.field.plan') }}</th>
                    <th>{{ __('platform_usage.plan_request.field.requester') }}</th>
                    <th>{{ __('platform_usage.plan_request.field.created_at') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($requests as $planRequest)
                <tr>
                    <td>{{ __('platform_usage.plan.' . $planRequest->requested_plan) }}@if (($planRequest->requested_addons ?? []) !== []) <span class="text-xs text-muted">+ {{ implode(', ', $planRequest->requested_addons) }}</span>@endif</td>
                    <td>{{ $planRequest->requester->name ?? '—' }}</td>
                    <td>{{ $planRequest->created_at?->orgTz()->isoFormat('L') }}</td>
                    <td><span class="wd-badge badge-{{ $planRequest->status->tone() }}">{{ $planRequest->status->label() }}</span>@if ($planRequest->decision_note) <span class="text-xs text-muted">{{ $planRequest->decision_note }}</span>@endif</td>
                    <td class="text-right">
                        @if ($planRequest->status === \App\Enums\Platform\TenantPlanRequestStatus::Open)
                            <form method="POST" action="{{ route('admin.billing-profile.withdraw', $planRequest) }}">
                                @csrf
                                <x-icon-btn icon="undo" size="xs" type="submit" :title="__('platform_usage.plan_request.withdraw')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="swap_vert" :colspan="5" :title="__('platform_usage.plan_request.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
