{{--
  Created on   : Sat Oct 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- EBICS-Bankzugang eines Bankkontos (MVP-124). Erwartet: $account, $connection --}}
@extends('layouts.app')

@section('title', __('ebics.title'))
@section('nav-title', __('ebics.title'))

@section('content')
@php($status = $connection?->status ?? \App\Enums\Finance\EbicsConnectionStatus::Draft)
<x-index-page
    :subtitle="$account->label . ($account->iban ? ' · ' . $account->iban : '')"
    :badge="$status->label()"
    :badge-tone="$status->tone()">

    <x-slot:actions>
        @if ($connection?->isActive())
            @can(\App\Enums\User\Permission::FinancePaymentImport->value)
                <x-action-form :action="route('finance.bank-accounts.ebics.fetch', $account->sqid)">
                    <x-button type="submit" placement="bar">{{ __('ebics.action.fetch') }}</x-button>
                </x-action-form>
            @endcan
            <x-action-form :action="route('finance.bank-accounts.ebics.suspend', $account->sqid)" :confirm="__('ebics.confirm.suspend')">
                <x-button type="submit" tone="ghost" placement="danger">{{ __('ebics.action.suspend') }}</x-button>
            </x-action-form>
        @endif
    </x-slot:actions>

    @if ($connection?->last_error)
        <div role="alert" class="alert alert-error text-sm">{{ __('ebics.last_error', ['error' => $connection->last_error]) }}</div>
    @endif

    <x-card :title="__('ebics.section.access')">
        @if ($status === \App\Enums\Finance\EbicsConnectionStatus::Draft)
            <form method="POST" action="{{ route('finance.bank-accounts.ebics.update', $account->sqid) }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                <x-input-field name="host_url" type="url" required :label="__('ebics.field.host_url')" :value="old('host_url', $connection?->host_url)" :hint="__('ebics.hint.host_url')" />
                <x-input-field name="ebics_host" required maxlength="35" :label="__('ebics.field.ebics_host')" :value="old('ebics_host', $connection?->ebics_host)" />
                <x-input-field name="ebics_partner" required maxlength="35" :label="__('ebics.field.ebics_partner')" :value="old('ebics_partner', $connection?->ebics_partner)" />
                <x-input-field name="ebics_user" required maxlength="35" :label="__('ebics.field.ebics_user')" :value="old('ebics_user', $connection?->ebics_user)" />
                <div class="md:col-span-2">
                    <x-button type="submit">{{ __('ebics.action.save') }}</x-button>
                </div>
            </form>
        @else
            <x-detail-grid layout="cells">
                <x-detail-grid.row :label="__('ebics.field.host_url')" class="break-all">{{ $connection->host_url }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('ebics.field.ebics_host')">{{ $connection->ebics_host }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('ebics.field.ebics_partner')">{{ $connection->ebics_partner }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('ebics.field.ebics_user')">{{ $connection->ebics_user }}</x-detail-grid.row>
            </x-detail-grid>
        @endif
    </x-card>

    @if ($connection !== null)
        <x-card :title="__('ebics.section.steps')" class="mt-4">
            <ol class="list-decimal space-y-3 pl-5 text-sm">
                <li>
                    {{ __('ebics.step.keys') }}
                    @if (in_array($status, [\App\Enums\Finance\EbicsConnectionStatus::Draft, \App\Enums\Finance\EbicsConnectionStatus::Suspended], true))
                        <x-action-form :action="route('finance.bank-accounts.ebics.keys', $account->sqid)" class="mt-1">
                            <x-button type="submit" size="sm">{{ __('ebics.action.keys') }}</x-button>
                        </x-action-form>
                    @elseif ($connection->keys_created_at)
                        <span class="text-muted">— {{ $connection->keys_created_at->fdatetime() }}</span>
                    @endif
                </li>
                <li>
                    {{ __('ebics.step.initialize') }}
                    @if ($status === \App\Enums\Finance\EbicsConnectionStatus::KeysCreated)
                        <x-action-form :action="route('finance.bank-accounts.ebics.initialize', $account->sqid)" class="mt-1">
                            <x-button type="submit" size="sm">{{ __('ebics.action.initialize') }}</x-button>
                        </x-action-form>
                    @elseif ($connection->initialized_at)
                        <span class="text-muted">— {{ $connection->initialized_at->fdatetime() }}</span>
                    @endif
                </li>
                <li>
                    {{ __('ebics.step.letter') }}
                    @if (in_array($status, [\App\Enums\Finance\EbicsConnectionStatus::Initialized, \App\Enums\Finance\EbicsConnectionStatus::Active], true))
                        <x-button :href="route('finance.bank-accounts.ebics.letter', $account->sqid)" tone="ghost" class="mt-1">{{ __('ebics.action.letter') }}</x-button>
                    @endif
                </li>
                <li>
                    {{ __('ebics.step.activate') }}
                    @if ($status === \App\Enums\Finance\EbicsConnectionStatus::Initialized)
                        <x-action-form :action="route('finance.bank-accounts.ebics.activate', $account->sqid)" class="mt-1">
                            <x-button type="submit" size="sm">{{ __('ebics.action.activate') }}</x-button>
                        </x-action-form>
                    @elseif ($connection->activated_at)
                        <span class="text-muted">— {{ $connection->activated_at->fdatetime() }}</span>
                    @endif
                </li>
            </ol>
            @if ($connection->isActive())
                <p class="mt-3 text-sm text-muted">{{ __('ebics.hint.active', ['date' => $connection->statements_until?->fdate() ?? '—']) }}</p>
            @endif
        </x-card>

        <x-card :title="__('ebics.section.journal')" class="mt-4">
            <x-journal :entries="$connection->journal" />
        </x-card>
    @endif
</x-index-page>
@endsection
