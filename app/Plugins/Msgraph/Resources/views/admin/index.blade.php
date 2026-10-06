{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('msgraph::msgraph.title'))
@section('nav-title', __('msgraph::msgraph.title'))

@section('content')
<x-index-page :title="__('msgraph::msgraph.calendar_heading')" :subtitle="__('msgraph::msgraph.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            @if (($health['ok'] ?? false))
                <x-status-badge tone="success">{{ __('msgraph::msgraph.health.badge_ok') }}</x-status-badge>
            @else
                <x-status-badge tone="error">{{ __('msgraph::msgraph.health.badge_failing') }}</x-status-badge>
            @endif
        @elseif ($connection)
            <x-status-badge>{{ __('msgraph::msgraph.health.badge_inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @unless ($configured)
            <div role="alert" class="alert alert-warning text-sm">{{ __('msgraph::msgraph.not_configured_hint') }}</div>
        @endunless

        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.msgraph.publish') }}">
                    @csrf
                    <x-button type="submit">{{ __('msgraph::msgraph.action.publish') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.msgraph.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('msgraph::msgraph.action.disconnect') }}</x-button>
                </form>
            </div>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.msgraph.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('msgraph::msgraph.action.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Graph-Mail-Versand (Feature 102) --}}
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph_mail.heading') }}</h2>
            @if ($mailConnection && $mailConnection->isActive())
                <x-status-badge tone="success">{{ __('msgraph::msgraph_mail.badge_connected') }}</x-status-badge>
            @elseif ($mailConnection)
                <x-status-badge>{{ __('msgraph::msgraph_mail.badge_inactive') }}</x-status-badge>
            @endif
        </div>
        <p class="text-sm text-muted">{{ __('msgraph::msgraph_mail.intro') }}</p>

        @unless ($mailerActive)
            <div role="status" class="alert alert-info text-sm">{{ __('msgraph::msgraph_mail.mailer_hint') }}</div>
        @endunless

        @if ($mailConnection && $mailConnection->isActive())
            @if ($mailConnection->account_label)
                <p class="text-sm">{{ __('msgraph::msgraph_mail.account') }}: <span class="font-mono">{{ $mailConnection->account_label }}</span></p>
            @endif
            @if ($mailConnection->last_error)
                <div role="alert" class="alert alert-warning text-sm">
                    <span>{{ $mailConnection->last_error }} <span class="text-muted">({{ $mailConnection->last_error_at?->ftime() }})</span></span>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.msgraph.mail.settings') }}" class="space-y-3">
                @csrf
                <label class="form-control max-w-md">
                    <span class="label-text">{{ __('msgraph::msgraph_mail.from_address') }}</span>
                    <input type="email" name="from_address" maxlength="190"
                           value="{{ old('from_address', $mailConnection->from_address) }}"
                           class="input input-sm input-bordered" placeholder="{{ __('msgraph::msgraph_mail.from_placeholder') }}">
                    <span class="label-text-alt text-muted">{{ __('msgraph::msgraph_mail.from_hint') }}</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="save_to_sent_items" value="1" class="checkbox checkbox-sm"
                           @checked(old('save_to_sent_items', $mailConnection->save_to_sent_items))>
                    {{ __('msgraph::msgraph_mail.save_to_sent') }}
                </label>
                <div class="flex justify-end">
                    <x-button type="submit">{{ __('msgraph::msgraph.action.save') }}</x-button>
                </div>
            </form>

            {{-- Aktionsleiste: Testversand (links) + Trennen (rechts) --}}
            <div class="space-y-2 border-t border-base-300 pt-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <form method="POST" action="{{ route('admin.msgraph.mail.test') }}" class="flex items-end gap-2">
                        @csrf
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('msgraph::msgraph_mail.test.recipient') }}</span>
                            <input type="email" name="test_recipient" maxlength="190"
                                   class="input input-sm input-bordered w-56"
                                   placeholder="{{ __('msgraph::msgraph_mail.test.recipient_placeholder') }}">
                        </label>
                        <x-button type="submit" tone="outline">{{ __('msgraph::msgraph_mail.test.send') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.msgraph.mail.disconnect') }}">
                        @csrf
                        <x-button type="submit" tone="ghost">{{ __('msgraph::msgraph_mail.disconnect') }}</x-button>
                    </form>
                </div>
                <p class="text-xs text-muted">{{ __('msgraph::msgraph_mail.test.hint') }}</p>
            </div>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.msgraph.mail.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('msgraph::msgraph_mail.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Kontakt-Push (Feature 102, Schnitt D) --}}
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph_contacts.heading') }}</h2>
            @if ($contactConnection && $contactConnection->isActive())
                <x-status-badge tone="success">{{ __('msgraph::msgraph_contacts.badge_connected') }}</x-status-badge>
            @elseif ($contactConnection)
                <x-status-badge>{{ __('msgraph::msgraph_contacts.badge_inactive') }}</x-status-badge>
            @endif
        </div>
        <p class="text-sm text-muted">{{ __('msgraph::msgraph_contacts.intro') }}</p>

        @if ($contactConnection && $contactConnection->isActive())
            @if ($contactConnection->account_label)
                <p class="text-sm">{{ __('msgraph::msgraph_contacts.account') }}: <span class="font-mono">{{ $contactConnection->account_label }}</span></p>
            @endif
            @if ($contactConnection->last_error)
                <div role="alert" class="alert alert-warning text-sm">
                    <span>{{ $contactConnection->last_error }} <span class="text-muted">({{ $contactConnection->last_error_at?->ftime() }})</span></span>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.msgraph.contacts.disconnect') }}">
                @csrf
                <x-button type="submit" tone="ghost">{{ __('msgraph::msgraph_contacts.disconnect') }}</x-button>
            </form>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.msgraph.contacts.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('msgraph::msgraph_contacts.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- To-Do-Sync (Feature 102, Schnitt E) --}}
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph_tasks.heading') }}</h2>
            @if ($taskConnection && $taskConnection->isActive())
                <x-status-badge tone="success">{{ __('msgraph::msgraph_tasks.badge_connected') }}</x-status-badge>
            @elseif ($taskConnection)
                <x-status-badge>{{ __('msgraph::msgraph_tasks.badge_inactive') }}</x-status-badge>
            @endif
        </div>
        <p class="text-sm text-muted">{{ __('msgraph::msgraph_tasks.intro') }}</p>

        @if ($taskConnection && $taskConnection->isActive())
            @if ($taskConnection->account_label)
                <p class="text-sm">{{ __('msgraph::msgraph_tasks.account') }}: <span class="font-mono">{{ $taskConnection->account_label }}</span></p>
            @endif

            {{-- Zuordnungen --}}
            @if ($taskLinks->isNotEmpty())
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('msgraph::msgraph_tasks.link.list') }}</th>
                            <th>{{ __('msgraph::msgraph_tasks.link.target') }}</th>
                            <th>{{ __('msgraph::msgraph_tasks.link.mode') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($taskLinks as $link)
                        <tr>
                            <td>{{ $link->todo_list_name ?? $link->todo_list_id }}</td>
                            <td>{{ $link->target_kind === 'project' ? ($link->project?->name ?? '—') : __('msgraph::msgraph_tasks.link.global') }}</td>
                            <td>{{ __('msgraph::msgraph_tasks.mode.' . $link->sync_mode) }}</td>
                            <td class="text-right">
                                <x-action-form :action="route('admin.msgraph.tasks.links.destroy', $link)" method="DELETE"
                                      :confirm="__('msgraph::msgraph_tasks.link.remove_confirm')"
                                      :confirm-label="__('msgraph::msgraph_tasks.link.remove')">
                                    <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :label="__('msgraph::msgraph_tasks.link.remove')" />
                                </x-action-form>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif

            {{-- Neue Zuordnung --}}
            <form method="POST" action="{{ route('admin.msgraph.tasks.links.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('msgraph::msgraph_tasks.link.list') }}</span>
                    <select name="todo_list_id" class="select select-bordered select-sm w-56" required>
                        @foreach ($todoLists as $list)
                            <option value="{{ $list['id'] }}">{{ $list['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('msgraph::msgraph_tasks.link.target') }}</span>
                    <select name="target_kind" class="select select-bordered select-sm">
                        <option value="project">{{ __('msgraph::msgraph_tasks.link.project') }}</option>
                        <option value="global_kanban">{{ __('msgraph::msgraph_tasks.link.global') }}</option>
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('msgraph::msgraph_tasks.link.project') }}</span>
                    <select name="project_id" class="select select-bordered select-sm w-48">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->sqid }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('msgraph::msgraph_tasks.link.mode') }}</span>
                    <select name="sync_mode" class="select select-bordered select-sm">
                        <option value="bidirectional">{{ __('msgraph::msgraph_tasks.mode.bidirectional') }}</option>
                        <option value="todo_to_workdiary">{{ __('msgraph::msgraph_tasks.mode.todo_to_workdiary') }}</option>
                        <option value="workdiary_to_todo">{{ __('msgraph::msgraph_tasks.mode.workdiary_to_todo') }}</option>
                    </select>
                </label>
                <x-icon-btn icon="add_link" tone="primary" size="sm" type="submit" show-label>{{ __('msgraph::msgraph_tasks.link.add') }}</x-icon-btn>
            </form>

            <form method="POST" action="{{ route('admin.msgraph.tasks.disconnect') }}">
                @csrf
                <x-button type="submit" tone="ghost">{{ __('msgraph::msgraph_tasks.disconnect') }}</x-button>
            </form>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.msgraph.tasks.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('msgraph::msgraph_tasks.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- OneNote-Übernahme (Feature 155, MVP-815): nur lesend, erst nach Einschalten verbindbar --}}
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph_onenote.heading') }}</h2>
            @if ($oneNoteConnection && $oneNoteConnection->isActive())
                <x-status-badge tone="success">{{ __('msgraph::msgraph_onenote.badge_connected') }}</x-status-badge>
            @elseif (! $oneNoteEnabled)
                <x-status-badge>{{ __('msgraph::msgraph_onenote.badge_disabled') }}</x-status-badge>
            @endif
        </div>
        <p class="text-sm text-muted">{{ __('msgraph::msgraph_onenote.intro') }}</p>

        @if ($oneNoteConnection && $oneNoteConnection->isActive())
            @if ($oneNoteConnection->account_label)
                <p class="text-sm">{{ __('msgraph::msgraph_onenote.account') }}: <span class="font-mono">{{ $oneNoteConnection->account_label }}</span></p>
            @endif
            <div class="flex flex-wrap items-center gap-2">
                <x-icon-btn icon="hub" tone="primary" size="sm" :href="route('knowledge-hub.index')" show-label>{{ __('msgraph::msgraph_onenote.open_hub') }}</x-icon-btn>
                <form method="POST" action="{{ route('admin.msgraph.onenote.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('msgraph::msgraph_onenote.disconnect') }}</x-button>
                </form>
            </div>
        @elseif (! $oneNoteEnabled)
            <p class="text-sm">{{ __('msgraph::msgraph_onenote.enable_hint') }}</p>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.msgraph.onenote.oauth.start') }}" data-oauth-popup>
                @csrf
                <x-button type="submit">{{ __('msgraph::msgraph_onenote.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Ziel-Kalender --}}
    @if ($connection && $connection->isActive())
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.msgraph.calendar.store') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph.calendar.heading') }}</h2>
            <p class="text-sm text-muted">{{ __('msgraph::msgraph.calendar.help') }}</p>

            <label class="form-control max-w-md">
                <span class="label-text">{{ __('msgraph::msgraph.calendar.target') }}</span>
                <select name="calendar_id" class="select select-bordered select-sm">
                    <option value="">{{ __('msgraph::msgraph.calendar.default') }}</option>
                    @foreach ($calendars as $calendar)
                        <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex items-center gap-2 text-sm" title="{{ __('msgraph::msgraph.calendar.teams_meetings_hint') }}">
                <input type="hidden" name="teams_meetings" value="0">
                <input type="checkbox" name="teams_meetings" value="1" class="checkbox checkbox-sm"
                       @checked(old('teams_meetings', $connection->teams_meetings))>
                {{ __('msgraph::msgraph.calendar.teams_meetings') }}
            </label>

            <label class="flex items-center gap-2 text-sm" title="{{ __('msgraph::msgraph.calendar.two_way_hint') }}">
                <input type="hidden" name="two_way" value="0">
                <input type="checkbox" name="two_way" value="1" class="checkbox checkbox-sm"
                       @checked(old('two_way', $connection->two_way))>
                {{ __('msgraph::msgraph.calendar.two_way') }}
            </label>

            <div class="flex justify-end">
                <x-button type="submit">{{ __('msgraph::msgraph.action.save') }}</x-button>
            </div>
        </x-card>
    @endif

    {{-- Entra-App & tenantweite Freigabe (Admin-Consent) --}}
    <x-card class="space-y-3">
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph::msgraph.entra.heading') }}</h2>
        <p class="text-sm text-muted">{{ __('msgraph::msgraph.entra.intro') }}</p>

        @if ($configured)
            <form method="POST" action="{{ route('admin.msgraph.adminconsent.start') }}">
                @csrf
                <x-button type="submit" tone="outline">{{ __('msgraph::msgraph.entra.consent') }}</x-button>
            </form>
            <p class="text-xs text-muted">{{ __('msgraph::msgraph.entra.consent_hint') }}</p>
        @endif

        <details class="text-sm">
            <summary class="cursor-pointer font-medium">{{ __('msgraph::msgraph.entra.redirects') }}</summary>
            <p class="mt-2 text-muted">{{ __('msgraph::msgraph.entra.redirects_hint') }}</p>
            <ul class="mt-2 space-y-1">
                <li>{{ __('msgraph::msgraph.entra.redirect_calendar') }}: <code class="select-all break-all">{{ route('admin.msgraph.oauth.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_mail') }}: <code class="select-all break-all">{{ route('admin.msgraph.mail.oauth.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_contacts') }}: <code class="select-all break-all">{{ route('admin.msgraph.contacts.oauth.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_tasks') }}: <code class="select-all break-all">{{ route('admin.msgraph.tasks.oauth.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_intake') }}: <code class="select-all break-all">{{ route('admin.cloud-intake.microsoft.oauth.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_adminconsent') }}: <code class="select-all break-all">{{ route('admin.msgraph.adminconsent.callback') }}</code></li>
                <li>{{ __('msgraph::msgraph.entra.redirect_backup') }}: <code class="select-all break-all">{{ route('admin.backup-targets.microsoft.oauth.callback') }}</code></li>
            </ul>
        </details>
    </x-card>
</x-index-page>
@endsection
