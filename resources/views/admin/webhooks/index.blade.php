@extends('layouts.app')

@section('title', __('integration.webhook.title.index'))
@section('nav-title', __('integration.webhook.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('integration.webhook.title.subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.webhooks.create')"
                        show-label>{{ __('integration.webhook.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if (session('webhook_secret'))
        <div role="alert" class="alert alert-warning alert-soft">
            <x-icon name="key" />
            <div class="min-w-0">
                <h3 class="font-semibold">{{ __('integration.webhook.secret.shown_once') }}</h3>
                <div class="text-sm">{{ __('integration.webhook.secret.shown_once_help') }}</div>
                <code class="mt-2 block w-full overflow-x-auto rounded bg-base-300 px-2 py-1 font-mono text-sm">{{ session('webhook_secret') }}</code>
            </div>
        </div>
    @endif

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('integration.webhook.title.help') }}</h3>
            <div class="text-sm">{{ __('integration.webhook.title.help_text') }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('integration.webhook.field.label') }}</th>
                <th>{{ __('integration.webhook.field.url') }}</th>
                <th>{{ __('integration.webhook.field.events') }}</th>
                <th class="text-center">{{ __('integration.webhook.field.status') }}</th>
                <th>{{ __('integration.webhook.field.last_deliveries') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($endpoints as $endpoint)
            @php /** @var \App\Models\Integration\WebhookEndpoint $endpoint */ @endphp
            <tr>
                <td class="font-medium">{{ $endpoint->label }}</td>
                <td class="max-w-xs truncate font-mono text-xs" title="{{ $endpoint->url }}">{{ $endpoint->url }}</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ((array) $endpoint->events as $eventKey)
                            <x-status-badge size="xs" tone="info">
                                {{ \App\Enums\Integration\WebhookEvent::tryFrom($eventKey)?->label() ?? $eventKey }}
                            </x-status-badge>
                        @endforeach
                    </div>
                </td>
                <td class="text-center">
                    @if ($endpoint->disabled_at !== null)
                        <x-status-badge size="xs" tone="error" title="{{ __('integration.webhook.field.auto_disabled_help') }}">
                            {{ __('integration.webhook.field.auto_disabled') }}
                        </x-status-badge>
                    @elseif ($endpoint->active)
                        <x-status-badge size="xs" tone="success">{{ __('integration.webhook.field.active') }}</x-status-badge>
                    @else
                        <x-status-badge size="xs" tone="ghost">{{ __('integration.webhook.field.inactive') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm">
                    @forelse ($endpoint->deliveries as $delivery)
                        <div class="flex items-center gap-2 tabular-nums">
                            <x-status-badge size="xs" :tone="$delivery->status->tone()">{{ $delivery->status->label() }}</x-status-badge>
                            <span class="text-xs text-base-content/60">
                                {{ $delivery->event }}@if ($delivery->http_status) · HTTP {{ $delivery->http_status }}@endif
                                · {{ $delivery->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <span class="opacity-50">{{ __('integration.webhook.field.no_deliveries') }}</span>
                    @endforelse
                </td>
                <td class="text-right">
                    @if ($canManage ?? false)
                        <div class="flex justify-end gap-1">
                            <form method="POST" action="{{ route('admin.webhooks.test', $endpoint) }}" class="inline">
                                @csrf
                                <x-icon-btn icon="send" type="submit" :label="__('integration.webhook.action.test')" />
                            </form>
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('admin.webhooks.edit', $endpoint)"
                                        :label="__('integration.webhook.action.edit')" />
                            <form method="POST" action="{{ route('admin.webhooks.destroy', $endpoint) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('integration.webhook.action.delete_confirm') }}"
                                  data-confirm-label="{{ __('integration.webhook.action.delete') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('integration.webhook.action.delete')" />
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">webhook</span>' :colspan="6" :title="__('integration.webhook.title.empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
