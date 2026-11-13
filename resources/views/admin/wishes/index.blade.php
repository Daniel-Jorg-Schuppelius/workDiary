{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Plattformsicht der Wünsche (Feature 206, MVP-1659). Ohne Personenbezug: bereinigter Text, Art, Seite, Stimmen, Organisation.
     Variablen: $wishes (Paginator führender Wünsche, votes_count), $bundled (Lead-ID → gebündelte), $organizations (Wunsch-ID → Name), $filters, $sort, $dir, $vendorEnabled. --}}

@extends('layouts.app')

@section('title', __('wishes.platform.title'))
@section('nav-title', __('wishes.platform.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('wishes.platform.subtitle')">
    <x-slot:badges>
        <x-status-badge :tone="$vendorEnabled ? 'success' : 'ghost'" icon="forward_to_inbox">{{ $vendorEnabled ? __('wishes.vendor.enabled') : __('wishes.vendor.disabled') }}</x-status-badge>
    </x-slot:badges>

    <x-filter-bar :action="route('admin.wishes.index')" :reset="route('admin.wishes.index')">
        <select name="kind" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('wishes.field.kind') }}">
            <option value="">{{ __('wishes.filter.all_kinds') }}</option>
            @foreach (\App\Enums\Wishes\AppWishKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('wishes.platform.status') }}">
            <option value="">{{ __('wishes.filter.all_statuses') }}</option>
            @foreach (\App\Enums\Wishes\AppWishStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($wishes->isEmpty())
        <x-empty-state framed icon="outbox" :title="__('wishes.platform.empty.title')" :message="__('wishes.platform.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('admin.wishes.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['kind' => $filters['kind'] ?: null, 'status' => $filters['status'] ?: null])">
            <x-slot:head>
                <tr>
                    <th>{{ __('wishes.field.kind') }}</th>
                    <th>{{ __('wishes.field.forwarded_body') }}</th>
                    <th>{{ __('wishes.platform.organization') }}</th>
                    <x-table.th sort="votes" align="right">{{ __('wishes.field.votes') }}</x-table.th>
                    <th>{{ __('wishes.platform.status') }}</th>
                    <x-table.th sort="forwarded_at">{{ __('wishes.field.forwarded_at') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($wishes as $wish)
                @php
                    $members = $bundled->get($wish->id, collect());
                    $votes = (int) $wish->votes_count + (int) $members->sum('votes_count');
                @endphp
                <tr class="hover align-top">
                    <td class="whitespace-nowrap text-sm"><x-icon :name="$wish->kind->icon()" class="align-middle text-muted" /> {{ $wish->kind->label() }}</td>
                    <td class="min-w-60">
                        <a href="{{ route('admin.wishes.show', $wish->sqid) }}" class="link link-hover whitespace-pre-line">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($wish->forwarded_body, 240) }}</a>
                        <div class="mt-1 text-xs text-muted font-mono">{{ $wish->route_name ?? '—' }}</div>
                        @if ($members->isNotEmpty())
                            <div class="mt-1 text-xs text-muted">{{ trans_choice('wishes.platform.bundled_count', $members->count(), ['count' => $members->count()]) }}</div>
                        @endif
                    </td>
                    <td class="text-sm">
                        {{ $organizations[(int) $wish->id] ?? '—' }}
                        @foreach ($members as $member)
                            <div class="text-xs text-muted">+ {{ $organizations[(int) $member->id] ?? '—' }}</div>
                        @endforeach
                    </td>
                    <td class="text-right tabular-nums font-medium">{{ $votes }}</td>
                    <td class="whitespace-nowrap">
                        <x-status-badge size="sm" :tone="($wish->platform_status ?? \App\Enums\Wishes\AppWishStatus::New)->tone()">{{ ($wish->platform_status ?? \App\Enums\Wishes\AppWishStatus::New)->label() }}</x-status-badge>
                        @if ($wish->isSentToVendor())
                            <div class="mt-1"><x-status-badge size="xs" tone="info" icon="forward_to_inbox">{{ __('wishes.vendor.sent_badge') }}</x-status-badge></div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm tabular-nums">{{ $wish->forwarded_at?->fdate() }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$wishes" standing />
    @endif
</x-index-page>
@endsection
