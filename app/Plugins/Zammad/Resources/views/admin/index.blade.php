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
                @if ($connection)
                    <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.webhook_url') }}:
                        <code class="select-all break-all">{{ route('api.webhooks.zammad', ['connection' => $connection->id]) }}</code></span>
                @endif
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
            <label class="form-control">
                <span class="label-text">{{ __('zammad::zammad.field.time_unit') }}</span>
                @php $timeUnit = old('time_unit', $connection->time_unit ?? ''); @endphp
                <select name="time_unit" class="select select-bordered select-sm">
                    <option value="">{{ __('zammad::zammad.field.time_unit_off') }}</option>
                    <option value="minute" @selected($timeUnit === 'minute')>{{ __('zammad::zammad.field.time_unit_minute') }}</option>
                    <option value="hour" @selected($timeUnit === 'hour')>{{ __('zammad::zammad.field.time_unit_hour') }}</option>
                </select>
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.time_unit_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="allow_private_network" value="0">
                    <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                           @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                    <span class="label-text">{{ __('zammad::zammad.field.allow_private_network') }}</span>
                </span>
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.field.allow_private_network_help') }}</span>
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
            <label class="form-control mb-2">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="is_limited_to_mapped_groups" value="0">
                    <input type="checkbox" name="is_limited_to_mapped_groups" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('is_limited_to_mapped_groups', $connection->is_limited_to_mapped_groups ?? false))>
                    <span class="label-text">{{ __('zammad::zammad.queue.limited') }}</span>
                </span>
                <span class="label-text-alt text-muted">{{ __('zammad::zammad.queue.limited_help') }}</span>
            </label>
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

    {{-- Ticketziel: Aufgaben oder Service-Tickets einer Queue --}}
    @if ($connection)
        @php
            $target = $connection->ticket_target ?? 'task';
            $currentQueue = $queues->firstWhere('id', (int) $connection->service_queue_id);
        @endphp
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.zammad.ticket-target') }}"
                data-confirm-dialog
                data-confirm-title="{{ __('zammad::zammad.target.confirm_title') }}"
                data-confirm-message="{{ __('zammad::zammad.target.confirm') }}"
                data-confirm-label="{{ __('zammad::zammad.action.switch_target') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('zammad::zammad.target.heading') }}</h2>
            <p class="text-xs text-muted">{{ __('zammad::zammad.target.help') }}</p>
            <p class="text-sm">
                {{ __('zammad::zammad.target.current') }}:
                <strong>{{ $target === 'service_ticket'
                    ? __('zammad::zammad.target.service_ticket_in', ['queue' => $currentQueue['name'] ?? '—'])
                    : __('zammad::zammad.target.task') }}</strong>
            </p>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('zammad::zammad.target.field') }}</span>
                    <select name="ticket_target" class="select select-bordered select-sm">
                        <option value="task" @selected(old('ticket_target', $target) === 'task')>{{ __('zammad::zammad.target.task') }}</option>
                        @if ($serviceTicketsAvailable)
                            <option value="service_ticket" @selected(old('ticket_target', $target) === 'service_ticket')>{{ __('zammad::zammad.target.service_ticket') }}</option>
                        @endif
                    </select>
                </label>
                @if ($serviceTicketsAvailable && $queues->isNotEmpty())
                    <label class="form-control">
                        <span class="label-text">{{ __('zammad::zammad.target.queue') }}</span>
                        <select name="service_queue" class="select select-bordered select-sm">
                            <option value="">{{ __('zammad::zammad.target.no_queue') }}</option>
                            @foreach ($queues as $queue)
                                <option value="{{ $queue['sqid'] }}" @selected(old('service_queue', $currentQueue['sqid'] ?? null) === $queue['sqid'])>{{ $queue['name'] }}</option>
                            @endforeach
                        </select>
                        <span class="label-text-alt text-muted">{{ __('zammad::zammad.target.queue_help') }}</span>
                    </label>
                @endif
            </div>
            @if (! $serviceTicketsAvailable)
                <p class="text-xs text-muted">{{ __('zammad::zammad.target.helpdesk_hint') }}</p>
            @elseif ($queues->isEmpty())
                <p class="text-xs text-muted">{{ __('zammad::zammad.target.no_queues_hint') }}</p>
            @endif

            <div class="flex justify-end">
                <x-button type="submit" tone="ghost">{{ __('zammad::zammad.action.switch_target') }}</x-button>
            </div>
        </x-card>
    @endif
</x-index-page>
@endsection
