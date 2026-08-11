@extends('layouts.app')
@section('title', __('msgraph.title'))
@section('nav-title', __('msgraph.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Status + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('msgraph.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('msgraph.health.badge_ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('msgraph.health.badge_failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph.health.badge_inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-base-content/60">{{ __('msgraph.intro') }}</p>

            @unless ($configured)
                <div class="alert alert-warning text-sm">{{ __('msgraph.not_configured_hint') }}</div>
            @endunless

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.msgraph.publish') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.publish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.msgraph.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph.action.disconnect') }}</button>
                    </form>
                </div>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.oauth.start') }}" data-oauth-popup>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Graph-Mail-Versand (Feature 102) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph_mail.heading') }}</h2>
                @if ($mailConnection && $mailConnection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('msgraph_mail.badge_connected') }}</span>
                @elseif ($mailConnection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph_mail.badge_inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-base-content/60">{{ __('msgraph_mail.intro') }}</p>

            @unless ($mailerActive)
                <div class="alert alert-info text-sm">{{ __('msgraph_mail.mailer_hint') }}</div>
            @endunless

            @if ($mailConnection && $mailConnection->isActive())
                @if ($mailConnection->account_label)
                    <p class="text-sm">{{ __('msgraph_mail.account') }}: <span class="font-mono">{{ $mailConnection->account_label }}</span></p>
                @endif
                @if ($mailConnection->last_error)
                    <div role="alert" class="alert alert-warning text-sm">
                        <span>{{ $mailConnection->last_error }} <span class="text-base-content/60">({{ $mailConnection->last_error_at?->ftime() }})</span></span>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.msgraph.mail.settings') }}" class="space-y-3">
                    @csrf
                    <label class="form-control max-w-md">
                        <span class="label-text">{{ __('msgraph_mail.from_address') }}</span>
                        <input type="email" name="from_address" maxlength="190"
                               value="{{ old('from_address', $mailConnection->from_address) }}"
                               class="input input-sm input-bordered" placeholder="{{ __('msgraph_mail.from_placeholder') }}">
                        <span class="label-text-alt text-base-content/60">{{ __('msgraph_mail.from_hint') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="save_to_sent_items" value="1" class="checkbox checkbox-sm"
                               @checked(old('save_to_sent_items', $mailConnection->save_to_sent_items))>
                        {{ __('msgraph_mail.save_to_sent') }}
                    </label>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.save') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.msgraph.mail.test') }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 pt-3">
                    @csrf
                    <label class="form-control max-w-xs grow">
                        <span class="label-text">{{ __('msgraph_mail.test.recipient') }}</span>
                        <input type="email" name="test_recipient" maxlength="190"
                               class="input input-sm input-bordered"
                               placeholder="{{ __('msgraph_mail.test.recipient_placeholder') }}">
                        <span class="label-text-alt text-base-content/60">{{ __('msgraph_mail.test.hint') }}</span>
                    </label>
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('msgraph_mail.test.send') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.msgraph.mail.disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph_mail.disconnect') }}</button>
                </form>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.mail.oauth.start') }}" data-oauth-popup>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph_mail.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Kontakt-Push (Feature 102, Schnitt D) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph_contacts.heading') }}</h2>
                @if ($contactConnection && $contactConnection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('msgraph_contacts.badge_connected') }}</span>
                @elseif ($contactConnection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph_contacts.badge_inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-base-content/60">{{ __('msgraph_contacts.intro') }}</p>

            @if ($contactConnection && $contactConnection->isActive())
                @if ($contactConnection->account_label)
                    <p class="text-sm">{{ __('msgraph_contacts.account') }}: <span class="font-mono">{{ $contactConnection->account_label }}</span></p>
                @endif
                @if ($contactConnection->last_error)
                    <div role="alert" class="alert alert-warning text-sm">
                        <span>{{ $contactConnection->last_error }} <span class="text-base-content/60">({{ $contactConnection->last_error_at?->ftime() }})</span></span>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.msgraph.contacts.disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph_contacts.disconnect') }}</button>
                </form>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.contacts.oauth.start') }}" data-oauth-popup>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph_contacts.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- To-Do-Sync (Feature 102, Schnitt E) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph_tasks.heading') }}</h2>
                @if ($taskConnection && $taskConnection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('msgraph_tasks.badge_connected') }}</span>
                @elseif ($taskConnection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph_tasks.badge_inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-base-content/60">{{ __('msgraph_tasks.intro') }}</p>

            @if ($taskConnection && $taskConnection->isActive())
                @if ($taskConnection->account_label)
                    <p class="text-sm">{{ __('msgraph_tasks.account') }}: <span class="font-mono">{{ $taskConnection->account_label }}</span></p>
                @endif

                {{-- Zuordnungen --}}
                @if ($taskLinks->isNotEmpty())
                    <x-table>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('msgraph_tasks.link.list') }}</th>
                                <th>{{ __('msgraph_tasks.link.target') }}</th>
                                <th>{{ __('msgraph_tasks.link.mode') }}</th>
                                <th></th>
                            </tr>
                        </x-slot:head>
                        @foreach ($taskLinks as $link)
                            <tr>
                                <td>{{ $link->todo_list_name ?? $link->todo_list_id }}</td>
                                <td>{{ $link->target_kind === 'project' ? ($link->project?->name ?? '—') : __('msgraph_tasks.link.global') }}</td>
                                <td>{{ __('msgraph_tasks.mode.' . $link->sync_mode) }}</td>
                                <td class="text-right">
                                    <x-action-form :action="route('admin.msgraph.tasks.links.destroy', $link)" method="DELETE"
                                          :confirm="__('msgraph_tasks.link.remove_confirm')"
                                          :confirm-label="__('msgraph_tasks.link.remove')">
                                        <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :label="__('msgraph_tasks.link.remove')" />
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
                        <span class="label-text">{{ __('msgraph_tasks.link.list') }}</span>
                        <select name="todo_list_id" class="select select-bordered select-sm w-56" required>
                            @foreach ($todoLists as $list)
                                <option value="{{ $list['id'] }}">{{ $list['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('msgraph_tasks.link.target') }}</span>
                        <select name="target_kind" class="select select-bordered select-sm">
                            <option value="project">{{ __('msgraph_tasks.link.project') }}</option>
                            <option value="global_kanban">{{ __('msgraph_tasks.link.global') }}</option>
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('msgraph_tasks.link.project') }}</span>
                        <select name="project_id" class="select select-bordered select-sm w-48">
                            <option value="">—</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('msgraph_tasks.link.mode') }}</span>
                        <select name="sync_mode" class="select select-bordered select-sm">
                            <option value="bidirectional">{{ __('msgraph_tasks.mode.bidirectional') }}</option>
                            <option value="todo_to_workdiary">{{ __('msgraph_tasks.mode.todo_to_workdiary') }}</option>
                            <option value="workdiary_to_todo">{{ __('msgraph_tasks.mode.workdiary_to_todo') }}</option>
                        </select>
                    </label>
                    <x-icon-btn icon="add_link" tone="primary" size="sm" type="submit" show-label>{{ __('msgraph_tasks.link.add') }}</x-icon-btn>
                </form>

                <form method="POST" action="{{ route('admin.msgraph.tasks.disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph_tasks.disconnect') }}</button>
                </form>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.tasks.oauth.start') }}" data-oauth-popup>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph_tasks.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Ziel-Kalender --}}
        @if ($connection && $connection->isActive())
            <form method="POST" action="{{ route('admin.msgraph.calendar.store') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph.calendar.heading') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('msgraph.calendar.help') }}</p>

                <label class="form-control max-w-md">
                    <span class="label-text">{{ __('msgraph.calendar.target') }}</span>
                    <select name="calendar_id" class="select select-bordered select-sm">
                        <option value="">{{ __('msgraph.calendar.default') }}</option>
                        @foreach ($calendars as $calendar)
                            <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2 text-sm" title="{{ __('msgraph.calendar.teams_meetings_hint') }}">
                    <input type="hidden" name="teams_meetings" value="0">
                    <input type="checkbox" name="teams_meetings" value="1" class="checkbox checkbox-sm"
                           @checked(old('teams_meetings', $connection->teams_meetings))>
                    {{ __('msgraph.calendar.teams_meetings') }}
                </label>

                <label class="flex items-center gap-2 text-sm" title="{{ __('msgraph.calendar.two_way_hint') }}">
                    <input type="hidden" name="two_way" value="0">
                    <input type="checkbox" name="two_way" value="1" class="checkbox checkbox-sm"
                           @checked(old('two_way', $connection->two_way))>
                    {{ __('msgraph.calendar.two_way') }}
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.save') }}</button>
                </div>
            </form>
        @endif

        {{-- Entra-App & tenantweite Freigabe (Admin-Consent) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph.entra.heading') }}</h2>
            <p class="text-sm text-base-content/60">{{ __('msgraph.entra.intro') }}</p>

            @if ($configured)
                <form method="POST" action="{{ route('admin.msgraph.adminconsent.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('msgraph.entra.consent') }}</button>
                </form>
                <p class="text-xs text-base-content/60">{{ __('msgraph.entra.consent_hint') }}</p>
            @endif

            <details class="text-sm">
                <summary class="cursor-pointer font-medium">{{ __('msgraph.entra.redirects') }}</summary>
                <p class="mt-2 text-base-content/60">{{ __('msgraph.entra.redirects_hint') }}</p>
                <ul class="mt-2 space-y-1">
                    <li>{{ __('msgraph.entra.redirect_calendar') }}: <code class="select-all break-all">{{ route('admin.msgraph.oauth.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_mail') }}: <code class="select-all break-all">{{ route('admin.msgraph.mail.oauth.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_contacts') }}: <code class="select-all break-all">{{ route('admin.msgraph.contacts.oauth.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_tasks') }}: <code class="select-all break-all">{{ route('admin.msgraph.tasks.oauth.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_intake') }}: <code class="select-all break-all">{{ route('admin.cloud-intake.microsoft.oauth.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_adminconsent') }}: <code class="select-all break-all">{{ route('admin.msgraph.adminconsent.callback') }}</code></li>
                    <li>{{ __('msgraph.entra.redirect_backup') }}: <code class="select-all break-all">{{ route('admin.backup-targets.microsoft.oauth.callback') }}</code></li>
                </ul>
            </details>
        </div>
    </div>
</x-page-shell>
@endsection
