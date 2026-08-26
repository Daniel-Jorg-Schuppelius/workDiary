{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('notification.title.rules'))
@section('nav-title', __('notification.title.rules'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('notification.title.rules_subtitle')">
    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('notification.title.rules_help') }}</h3>
            <div class="text-sm">{{ __('notification.title.rules_help_text') }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('notification.field.event') }}</th>
                <th class="text-center">{{ __('notification.field.enabled') }}</th>
                <th>{{ __('notification.field.channels') }}</th>
                <th>{{ __('notification.field.recipients') }}</th>
                <th>{{ __('notification.field.escalation') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @foreach ($rules as $rule)
            @php /** @var \App\Models\Notification\NotificationRule $rule */ @endphp
            <tr>
                <td class="font-medium">
                    <span class="inline-flex items-center gap-2">
                        <x-icon :name="$rule->event->icon()" class="text-primary" />
                        {{ $rule->event->label() }}
                    </span>
                    <div class="text-xs font-mono text-muted">{{ $rule->event->value }}</div>
                    @unless ($rule->exists)
                        <div class="text-xs text-muted">{{ __('notification.field.default_rule') }}</div>
                    @endunless
                </td>
                <td class="text-center">
                    @if ($rule->enabled)
                        <x-status-badge tone="success">{{ __('notification.field.yes') }}</x-status-badge>
                    @else
                        <x-status-badge tone="error">{{ __('notification.field.no') }}</x-status-badge>
                    @endif
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ((array) $rule->channels as $channel)
                            <x-status-badge size="xs" tone="info">
                                {{ \App\Enums\Notification\NotificationChannel::tryFrom($channel)?->label() ?? $channel }}
                            </x-status-badge>
                        @endforeach
                    </div>
                </td>
                <td class="text-sm">
                    <div class="flex flex-wrap gap-1">
                        @if ($rule->notify_affected)
                            <x-status-badge size="xs" tone="ghost">{{ __('notification.field.affected_user') }}</x-status-badge>
                        @endif
                        @foreach ((array) $rule->recipient_roles as $role)
                            <x-status-badge size="xs" tone="ghost">{{ \App\Enums\User\UserRole::tryFrom($role)?->label() ?? $role }}</x-status-badge>
                        @endforeach
                        @if (count((array) $rule->recipient_user_ids) > 0)
                            <x-status-badge size="xs" tone="ghost">+{{ count((array) $rule->recipient_user_ids) }} {{ __('notification.field.fixed_users') }}</x-status-badge>
                        @endif
                    </div>
                </td>
                <td class="text-sm">
                    @if ($rule->escalation_enabled && $rule->escalation_role)
                        <div class="flex flex-wrap gap-1">
                            <x-status-badge size="xs" tone="warning">
                                {{ __('notification.field.escalation_summary', [
                                    'hours' => (int) $rule->escalate_after_hours,
                                    'role' => \App\Enums\User\UserRole::tryFrom((string) $rule->escalation_role)?->label() ?? $rule->escalation_role,
                                ]) }}
                            </x-status-badge>
                            {{-- Eskalationsleiter (MVP-331): konfigurierte Stufen 2/3 kompakt anzeigen. --}}
                            @foreach ([2, 3] as $level)
                                @if ($rule->escalationLevelConfigured($level))
                                    <x-status-badge size="xs" tone="warning">
                                        {{ __('notification.field.escalation_level_summary', [
                                            'level' => $level,
                                            'hours' => (int) $rule->escalationAfterHoursFor($level),
                                        ]) }}
                                    </x-status-badge>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <span class="opacity-50">–</span>
                    @endif
                </td>
                <td class="text-right">
                    @if ($canUpdate ?? false)
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.notification-rules.edit', ['event' => $rule->event->value])"
                                    :label="__('notification.action.edit')" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
