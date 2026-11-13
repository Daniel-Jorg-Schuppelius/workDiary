{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Wünsche an die App (Feature 206, MVP-1658/1659). Variablen: $wishes (Paginator, votes_count), $voted (IDs mit eigener Stimme), $canManage, $filters (kind, status, open), $sort, $dir, $highlight (Sqid), $dailyLimit. --}}

@extends('layouts.app')

@section('title', __('wishes.title'))
@section('nav-title', __('wishes.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('wishes.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('wishes.create') . '?dialog=1'"
                    show-label>{{ __('wishes.create') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('wishes.index')" :reset="route('wishes.index')">
        <select name="kind" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('wishes.field.kind') }}">
            <option value="">{{ __('wishes.filter.all_kinds') }}</option>
            @foreach (\App\Enums\Wishes\AppWishKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('wishes.field.status') }}">
            <option value="">{{ __('wishes.filter.all_statuses') }}</option>
            @foreach (\App\Enums\Wishes\AppWishStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <x-filter-toggle name="open" :label="__('wishes.filter.open')" :checked="$filters['open']" />
    </x-filter-bar>

    @if ($wishes->isEmpty())
        <x-empty-state framed icon="lightbulb" :title="__('wishes.empty.title')" :message="__('wishes.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('wishes.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['kind' => $filters['kind'] ?: null, 'status' => $filters['status'] ?: null, 'open' => $filters['open'] ? 1 : null])">
            <x-slot:head>
                <tr>
                    <th>{{ __('wishes.field.kind') }}</th>
                    <th>{{ __('wishes.field.body') }}</th>
                    <th>{{ __('wishes.field.page') }}</th>
                    <x-table.th sort="votes" align="right">{{ __('wishes.field.votes') }}</x-table.th>
                    <th>{{ __('wishes.field.status') }}</th>
                    <x-table.th sort="created_at">{{ __('wishes.field.created_at') }}</x-table.th>
                    <th class="w-px"></th>
                </tr>
            </x-slot:head>
            @foreach ($wishes as $wish)
                @php
                    $hasVoted = in_array((int) $wish->id, $voted, true);
                    $pageUrl = $wish->pageUrl();
                @endphp
                <tr id="wish-{{ $wish->sqid }}" class="hover align-top {{ $highlight === $wish->sqid ? 'bg-primary/5' : '' }}">
                    <td class="whitespace-nowrap text-sm">
                        <x-icon :name="$wish->kind->icon()" class="align-middle text-muted" /> {{ $wish->kind->label() }}
                    </td>
                    <td class="min-w-60">
                        <div class="whitespace-pre-line">{{ $wish->body }}</div>
                        <div class="mt-1 text-xs text-muted">{{ __('wishes.by', ['name' => $wish->author?->name ?? __('wishes.former_member')]) }}</div>
                        @if ($wish->answer !== null)
                            <div class="mt-2 rounded-box bg-base-200 p-2 text-sm" data-wish-answer="organization">
                                <span class="font-medium">{{ __('wishes.answer.organization') }}:</span>
                                <span class="whitespace-pre-line">{{ $wish->answer }}</span>
                            </div>
                        @endif
                        @if ($wish->platform_answer !== null)
                            <div class="mt-2 rounded-box bg-info/10 p-2 text-sm" data-wish-answer="platform">
                                <span class="font-medium">{{ __('wishes.answer.platform') }}:</span>
                                <span class="whitespace-pre-line">{{ $wish->platform_answer }}</span>
                            </div>
                        @endif
                        @if ($wish->vendor_answer !== null)
                            <div class="mt-2 rounded-box bg-success/10 p-2 text-sm" data-wish-answer="vendor">
                                <span class="font-medium">{{ __('wishes.answer.vendor') }}:</span>
                                <span class="whitespace-pre-line">{{ $wish->vendor_answer }}</span>
                            </div>
                        @endif
                    </td>
                    <td class="text-sm">
                        @if ($wish->route_name === null)
                            <span class="text-muted">—</span>
                        @elseif ($pageUrl !== null)
                            <a href="{{ $pageUrl }}" class="link link-hover font-mono text-xs">{{ $wish->route_name }}</a>
                        @else
                            <span class="font-mono text-xs">{{ $wish->route_name }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <span class="tabular-nums font-medium">{{ $wish->votes_count }}</span>
                        @if ($hasVoted)
                            <x-action-form :action="route('wishes.unvote', $wish)" method="DELETE" class="inline">
                                <x-icon-btn icon="thumb_up" icon-filled tone="primary" type="submit" :label="__('wishes.action.unvote')" />
                            </x-action-form>
                        @elseif (\Illuminate\Support\Facades\Gate::allows('vote', $wish))
                            <x-action-form :action="route('wishes.vote', $wish)" class="inline">
                                <x-icon-btn icon="thumb_up" type="submit" :label="__('wishes.action.vote')" />
                            </x-action-form>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">
                        <x-status-badge size="sm" :tone="$wish->status->tone()">{{ $wish->status->label() }}</x-status-badge>
                        @if ($wish->isForwarded())
                            <div class="mt-1">
                                <x-status-badge size="xs" tone="info" icon="outbox">{{ __('wishes.forwarded_badge', ['status' => ($wish->platform_status ?? \App\Enums\Wishes\AppWishStatus::New)->label()]) }}</x-status-badge>
                            </div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm tabular-nums">{{ $wish->created_at?->fdate() }}</td>
                    <td class="whitespace-nowrap text-right">
                        @can('update', $wish)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('wishes.edit', $wish) . '?dialog=1'" :label="__('wishes.action.edit')" />
                        @endcan
                        @can('answer', $wish)
                            <x-icon-btn icon="reply" data-entry-modal-trigger :href="route('wishes.answer.edit', $wish) . '?dialog=1'" :label="__('wishes.action.answer')" />
                        @endcan
                        @can('forward', $wish)
                            <x-icon-btn icon="outbox" data-entry-modal-trigger :href="route('wishes.forward.create', $wish) . '?dialog=1'" :label="__('wishes.action.forward')" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$wishes" standing />
    @endif
</x-index-page>
@endsection
