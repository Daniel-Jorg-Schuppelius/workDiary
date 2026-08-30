{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsanfragen zu Kursen (Feature 149, MVP-744). Zweiphasig: die
  Anfrage steht hier, der Zugang entsteht erst mit der Zusage.
--}}
@extends('layouts.app')
@section('title', __('learning.title.bookings'))
@section('nav-title', __('learning.title.bookings'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.bookings')">
    <x-slot:actions>
        <x-help-button topic="learning.overview" />
    </x-slot:actions>

    <x-filter-bar :action="route('learning.bookings.index')" :reset="route('learning.bookings.index')">
        <x-filter-field :label="__('learning.field.open_bookings')" for="flt-open-count">
            <span id="flt-open-count" class="badge badge-ghost badge-sm">{{ $openCount }}</span>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.status')" for="flt-booking-status">
            <select id="flt-booking-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_status') }}</option>
                @foreach (\App\Enums\Learning\LearningBookingStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.course') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.learner') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.status') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.seats') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.requested_at') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($bookings as $booking)
            <tr class="hover">
                <td class="font-medium">{{ $booking->course?->title }}</td>
                <td class="text-sm">{{ $booking->bookerName() }}</td>
                <td class="text-sm">
                    <x-status-badge :tone="$booking->status->tone()" size="sm">{{ $booking->status->label() }}</x-status-badge>
                    @if ($booking->isOpenForBilling())
                        <x-status-badge tone="warning" size="sm" outline>{{ __('learning.field.billable') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-center text-sm">{{ $booking->seats }}</td>
                <td class="text-sm">{{ $booking->requested_at?->translatedFormat('d.m.Y H:i') }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        @if ($booking->status->isOpen())
                            <form method="POST" action="{{ route('learning.bookings.confirm', $booking) }}">
                                @csrf
                                <x-icon-btn icon="check" tone="primary" size="xs" type="submit" :label="__('learning.action.confirm_booking')" />
                            </form>
                            <form method="POST" action="{{ route('learning.bookings.reject', $booking) }}" class="flex items-center gap-1">
                                @csrf
                                {{-- Ein Platzhalter ist keine Beschriftung (WCAG 3.3.2). --}}
                                <label class="sr-only" for="booking-reason-{{ $booking->sqid }}">{{ __('learning.field.reason') }}</label>
                                <input type="text" id="booking-reason-{{ $booking->sqid }}" name="reason"
                                       class="input input-xs input-bordered w-32"
                                       placeholder="{{ __('learning.field.reason') }}" maxlength="500" required>
                                <x-icon-btn icon="close" tone="ghost" size="xs" type="submit" :label="__('learning.action.reject_booking')" />
                            </form>
                        @elseif ($booking->isOpenForBilling())
                            <form method="POST" action="{{ route('learning.bookings.billed', $booking) }}">
                                @csrf
                                <x-icon-btn icon="receipt_long" tone="outline" size="xs" type="submit" :label="__('learning.action.mark_billed')" />
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="event_note" :colspan="6" :title="__('learning.empty.bookings')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$bookings" standing />
</x-index-page>
@endsection
