{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Zammad'))
@section('nav-title', __('Zammad'))

@section('content')
<x-index-page :title="__('zammad::zammad.title')" :subtitle="__('zammad::zammad.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            <x-plugin-health :plugin-id="\App\Plugins\Zammad\ZammadPlugin::ID" :state="$healthState" />
        @elseif ($connection)
            <x-status-badge>{{ __('zammad::zammad.health.inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.zammad.sync') }}">
                    @csrf
                    <x-button type="submit">{{ __('zammad::zammad.action.sync') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.zammad.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('zammad::zammad.action.disconnect') }}</x-button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Anbindung --}}
    <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.zammad.connection.store') }}">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('zammad::zammad.connection.heading') }}</h2>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.name') }}</span>
                <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                       class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.base_url') }}</span>
                <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                       placeholder="https://support.example.com" class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.api_token') }}</span>
                <input type="password" name="api_token" autocomplete="new-password"
                       placeholder="{{ $connection ? __('zammad::zammad.field.token_keep') : '' }}"
                       class="input input-bordered input-sm" @required(! $connection)>
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.token_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.webhook_secret') }}</span>
                <input type="password" name="webhook_secret" autocomplete="new-password"
                       placeholder="{{ $connection && $connection->webhook_secret ? __('zammad::zammad.field.token_keep') : '' }}"
                       class="input input-bordered input-sm">
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.webhook_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.default_project') }}</span>
                <select name="default_project" class="select select-bordered select-sm">
                    <option value="">{{ __('zammad::zammad.field.no_project') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project['sqid'] }}" @selected(($defaultProjectSqid ?? null) === $project['sqid'])>{{ $project['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.resolved_state') }}</span>
                <input type="text" name="resolved_state" value="{{ old('resolved_state', $connection->resolved_state ?? '') }}"
                       placeholder="closed" class="input input-bordered input-sm">
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.resolved_state_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('active', $connection->active ?? true))>
                    <span class="label-text">{{ __('zammad::zammad.field.active') }}</span>
                </span>
            </label>
        </div>

        {{-- Queue → Projekt --}}
        <div>
            <h3 class="mb-1 text-sm font-semibold">{{ __('zammad::zammad.queue.heading') }}</h3>
            <p class="mb-2 text-xs text-muted">{{ __('zammad::zammad.queue.help') }}</p>
            <div class="space-y-2">
                @php $rows = array_merge($queueRows, array_fill(0, 3, ['group_id' => '', 'project_sqid' => ''])); @endphp
                @foreach ($rows as $row)
                    <div class="flex flex-wrap items-center gap-2">
                        <input aria-label="{{ __('zammad::zammad.queue.group_id') }}" type="number" name="queue_group[]" min="1" value="{{ $row['group_id'] }}"
                               placeholder="{{ __('zammad::zammad.queue.group_id') }}" class="input input-bordered input-sm w-40">
                        <span class="text-muted">→</span>
                        <select name="queue_project[]" class="select select-bordered select-sm">
                            <option value="">{{ __('zammad::zammad.field.no_project') }}</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project['sqid'] }}" @selected($row['project_sqid'] === $project['sqid'])>{{ $project['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('zammad::zammad.action.save') }}</x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
