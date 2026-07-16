{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.title.reseller') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.title.reseller'))

@section('content')
<x-index-page :subtitle="__('domain.title.reseller_subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="dns" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('success'))<div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>@endif

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('domain.reseller.user') }}</th>
                    <th>{{ __('domain.reseller.parent') }}</th>
                    <th>{{ __('domain.field.customer') }}</th>
                    <th class="text-right">{{ __('domain.reseller.domains') }}</th>
                    <th class="text-right">{{ __('domain.reseller.balance') }}</th>
                    <th>{{ __('domain.field.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td style="padding-left: {{ 0.5 + $account->depth * 1.25 }}rem">
                            <a href="{{ route('domain-reseller.show', $account) }}" class="link link-hover font-mono">{{ $account->external_user }}</a>
                            @if ($account->user_class)<span class="badge badge-ghost badge-sm">{{ $account->user_class }}</span>@endif
                        </td>
                        <td class="font-mono text-xs text-base-content/60">{{ $account->parent_user ?? '—' }}</td>
                        <td>{{ $account->customer?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $account->domains_count }}</td>
                        <td class="text-right tabular-nums">
                            {{ $account->balance_snapshot !== null ? number_format((float) $account->balance_snapshot, 2, ',', '.') . ' ' . ($account->currency?->value ?? '') : '—' }}
                        </td>
                        <td>
                            <span class="badge badge-{{ $account->active ? 'success' : 'ghost' }} badge-sm">
                                {{ $account->active ? __('domain.reseller.active') : __('domain.reseller.inactive') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-base-content/60 py-8">{{ __('domain.empty.reseller') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-index-page>
@endsection
