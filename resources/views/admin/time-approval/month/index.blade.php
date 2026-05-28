@extends('layouts.app')

@section('title', __('Inbox Monatsfreigaben'))
@section('nav-title', __('Inbox Monatsfreigaben'))

@php
    use App\Enums\TimeApproval\MonthClosureStatus;
@endphp

@section('content')
    <x-index-page :subtitle="__('Eingereichte und entschiedene Monate der Organisation.')">
        <x-filter-bar :action="route('admin.month-approval.index')" :reset="route('admin.month-approval.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0">
                <option value="all" @selected($filters['status'] === 'all')>{{ __('Alle Status') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="user" class="select select-sm select-bordered w-48 shrink-0">
                <option value="">{{ __('Alle Mitarbeitenden') }}</option>
                @foreach ($teamUsers as $u)
                    <option value="{{ $u->sqid }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="number" name="year" min="2000" max="2999" value="{{ $filters['year'] }}"
                   class="input input-sm input-bordered w-24 shrink-0" placeholder="{{ __('Jahr') }}"
                   aria-label="{{ __('Jahr') }}" />
        </x-filter-bar>

        @if ($closures->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
                :title="__('Keine Monate im Filter')"
                :message="__('Passen Sie die Filter an oder warten Sie auf eingereichte Monate.')" />
        @else
            @if (session('error'))
                <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
            @endif
            @if (session('status'))
                <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
            @endif

            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Mitarbeitende:r') }}</th>
                        <th>{{ __('Periode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Tage offen') }}</th>
                        <th class="text-right">{{ __('Warnungen') }}</th>
                        <th>{{ __('Eingereicht') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($closures as $c)
                    <tr>
                        <td class="font-medium">{{ $c->user?->name }}</td>
                        <td class="tabular-nums">{{ $c->periodLabel() }}</td>
                        <td><span class="badge badge-{{ $c->status->tone() }} badge-sm">{{ $c->status->label() }}</span></td>
                        <td class="text-right tabular-nums">{{ $c->days_open }}</td>
                        <td class="text-right tabular-nums">{{ $c->warnings_count }}</td>
                        <td class="text-xs tabular-nums">{{ $c->submitted_at?->format('d.m.Y H:i') }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @can('approve', $c)
                                    <form method="POST" action="{{ route('admin.month-approval.approve', $c) }}">
                                        @csrf
                                        <x-icon-btn icon="check" tone="success" size="sm" type="submit"
                                                    :aria-label="__('Freigeben')" />
                                    </form>
                                @endcan
                                @can('reject', $c)
                                    <button type="button" class="btn btn-sm btn-warning"
                                            onclick="document.getElementById('reject-{{ $c->id }}').showModal()">
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                    <dialog id="reject-{{ $c->id }}" class="modal">
                                        <form method="POST" action="{{ route('admin.month-approval.reject', $c) }}" class="modal-box space-y-3">
                                            @csrf
                                            <h3 class="font-bold">{{ __('Monat ablehnen') }}</h3>
                                            <textarea name="note" required minlength="20" maxlength="2000" rows="4"
                                                      class="textarea textarea-bordered w-full"
                                                      placeholder="{{ __('Begründung (mind. 20 Zeichen)') }}"></textarea>
                                            <div class="modal-action">
                                                <button type="button" class="btn btn-sm btn-ghost"
                                                        onclick="document.getElementById('reject-{{ $c->id }}').close()">{{ __('Abbrechen') }}</button>
                                                <button type="submit" class="btn btn-sm btn-warning">{{ __('Ablehnen') }}</button>
                                            </div>
                                        </form>
                                    </dialog>
                                @endcan
                                @can('reopen', $c)
                                    @if (in_array($c->status, [MonthClosureStatus::Approved, MonthClosureStatus::Locked], true))
                                        <button type="button" class="btn btn-sm btn-ghost"
                                                onclick="document.getElementById('reopen-{{ $c->id }}').showModal()"
                                                aria-label="{{ __('Wieder öffnen') }}">
                                            <span class="material-symbols-outlined text-base">lock_open</span>
                                        </button>
                                        <dialog id="reopen-{{ $c->id }}" class="modal">
                                            <form method="POST" action="{{ route('admin.month-approval.reopen', $c) }}" class="modal-box space-y-3">
                                                @csrf
                                                <h3 class="font-bold">{{ __('Monat wieder öffnen') }}</h3>
                                                <textarea name="note" required minlength="20" maxlength="2000" rows="4"
                                                          class="textarea textarea-bordered w-full"
                                                          placeholder="{{ __('Begründung (mind. 20 Zeichen)') }}"></textarea>
                                                <div class="modal-action">
                                                    <button type="button" class="btn btn-sm btn-ghost"
                                                            onclick="document.getElementById('reopen-{{ $c->id }}').close()">{{ __('Abbrechen') }}</button>
                                                    <button type="submit" class="btn btn-sm btn-warning">{{ __('Wieder öffnen') }}</button>
                                                </div>
                                            </form>
                                        </dialog>
                                    @endif
                                @endcan
                                @can('lock', $c)
                                    <form method="POST" action="{{ route('admin.month-approval.lock', $c) }}">
                                        @csrf
                                        <x-icon-btn icon="lock" tone="ghost" size="sm" type="submit"
                                                    :aria-label="__('Sperren')" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>{{ $closures->links() }}</div>
        @endif
    </x-index-page>
@endsection
