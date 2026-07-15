@extends('layouts.app')

@section('title', __('notification.title.center'))
@section('nav-title', __('notification.title.center'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('notification.title.center_subtitle')">
    <x-slot:actions>
        @if (($unreadCount ?? 0) > 0)
            <form method="POST" action="{{ route('notifications.readAll') }}" class="inline">
                @csrf
                <x-icon-btn icon="done_all" tone="primary" size="sm" type="submit" show-label>
                    {{ __('notification.action.mark_all_read') }}
                </x-icon-btn>
            </form>
        @endif
    </x-slot:actions>

    <div class="flex-1 min-h-0 overflow-y-auto">
        <ul class="divide-y divide-base-200 rounded-box border border-base-300 bg-base-100">
            @forelse ($notifications as $notification)
                @php
                    $data = (array) $notification->data;
                    $isUnread = $notification->read_at === null;
                @endphp
                <li class="flex items-start gap-3 px-4 py-3 {{ $isUnread ? 'bg-primary/5' : '' }}">
                    <x-icon :name="$data['icon'] ?? 'notifications'" class="mt-0.5 {{ $isUnread ? 'text-primary' : 'text-base-content/50' }}" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium {{ $isUnread ? '' : 'opacity-70' }}">{{ \App\Support\NotificationText::title($data) }}</span>
                            @if (($data['stage'] ?? '') === 'escalation')
                                <x-status-badge size="xs" tone="error">{{ __('notification.field.escalation') }}</x-status-badge>
                            @endif
                            @if ($isUnread)
                                <span class="badge badge-primary badge-xs">{{ __('notification.field.unread') }}</span>
                            @endif
                        </div>
                        @if (($notificationMessage = \App\Support\NotificationText::message($data)) !== '')
                            <p class="text-sm opacity-70 mt-0.5">{{ $notificationMessage }}</p>
                        @endif
                        <p class="text-xs opacity-50 mt-1">
                            {{ $notification->created_at?->diffForHumans() }}
                            @if (! empty($data['event']))
                                · {{ \App\Enums\Notification\NotificationEvent::tryFrom((string) $data['event'])?->label() ?? $data['event'] }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        @if (! empty($data['url']))
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="inline">
                                @csrf
                                <input type="hidden" name="follow" value="1">
                                <x-icon-btn icon="open_in_new" size="sm" type="submit" :label="__('notification.action.open')" />
                            </form>
                        @endif
                        @if ($isUnread)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="inline">
                                @csrf
                                <x-icon-btn icon="done" size="sm" type="submit" :label="__('notification.action.mark_read')" />
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li>
                    <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_off</span>'
                                   :title="__('notification.title.empty')"
                                   :message="__('notification.title.empty_message')"
                                   compact />
                </li>
            @endforelse
        </ul>
    </div>

    <x-pagination :paginator="$notifications" standing />
</x-index-page>
@endsection
