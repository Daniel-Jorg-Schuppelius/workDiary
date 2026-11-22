{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftragsübersicht des Büros im Aussehen des Altportals (admin/kp_info.php). --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.classic.portal'))

@section('content')
    @php
        $listParams = array_filter([
            'year' => $year === null ? 'all' : (string) $year,
            'customer' => $filters['customer'] ?? null,
            'q' => $search !== '' ? $search : null,
            'status' => $statusFilter !== '' ? $statusFilter : null,
        ]);
        $mailOn = $mailSetting?->isEnabled() ?? false;
        $legend = [
            'new' => (string) \App\Legacy\Models\LegacySupportOrder::STATUS_NEW,
            'seen' => (string) \App\Legacy\Models\LegacySupportOrder::STATUS_SEEN,
            'scheduled' => (string) \App\Legacy\Models\LegacySupportOrder::STATUS_SCHEDULED,
            'overdue' => 'overdue',
            'done' => (string) \App\Legacy\Models\LegacySupportOrder::STATUS_DONE,
            'archived' => (string) \App\Legacy\Models\LegacySupportOrder::STATUS_ARCHIVED,
        ];
        $legendBase = array_filter(['year' => $listParams['year'] ?? null, 'customer' => $listParams['customer'] ?? null, 'sort' => $sort, 'dir' => $dir]);
        $filteredCustomer = $customerId !== null ? $customers->firstWhere('id', $customerId) : null;
    @endphp

    @include('legacy.classic.support._office_menu')

    <h2>{{ __('legacy_support.classic.portal') }}</h2>
    <p>{{ __('legacy_support.classic.logged_in_as', ['name' => (string) (auth()->user()?->name ?? '')]) }}</p>
    @include('legacy.classic._flash')
    @unless ($writeEnabled)
        <p class="kp-msg">{{ __('legacy_support.read_only') }}</p>
    @endunless

    @if ($writeEnabled)
        <form method="POST" action="{{ route('legacy.support.mail.update') }}" class="kp-msg">
            @csrf
            @method('PUT')
            <input type="hidden" name="enabled" value="{{ $mailOn ? '0' : '1' }}">
            <input type="hidden" name="email" value="{{ $mailSetting?->email }}">
            <button type="submit" class="kp-mail kp-mail--{{ $mailOn ? 'on' : 'off' }}"
                    title="{{ $mailOn ? __('legacy_support.classic.mail_switch_off') : __('legacy_support.classic.mail_switch_on') }}">
                <span class="kp-sr-only">{{ $mailOn ? __('legacy_support.classic.mail_switch_off') : __('legacy_support.classic.mail_switch_on') }}</span>
            </button>
            {{ $mailOn ? __('legacy_support.classic.mail_on') : __('legacy_support.classic.mail_off') }}
        </form>
    @endif

    <p class="kp-legend">
        {{ __('legacy_support.classic.legend_label') }}
        @foreach ($legend as $legendKind => $legendStatus)
            <span>
                @include('legacy.classic.support._box', ['kind' => $legendKind])
                <a href="{{ route('legacy.support.orders.index', array_merge($legendBase, ['status' => $legendStatus])) }}" class="{{ $statusFilter === $legendStatus ? '' : 'kp-normal' }}">{{ __('legacy_support.classic.legend.' . $legendKind) }}</a>
            </span>
        @endforeach
        @if ($statusFilter !== '' || $filteredCustomer !== null || $search !== '')
            <a href="{{ route('legacy.support.orders.index', array_filter(['year' => $listParams['year'] ?? null])) }}">{{ __('legacy_support.classic.show_all') }}</a>
        @endif
    </p>
    @if ($filteredCustomer !== null)
        <p class="kp-msg">{{ __('legacy_support.classic.filtered_customer', ['name' => $filteredCustomer->kunde]) }}</p>
    @endif

    @include('legacy.classic.support._year_select', [
        'route' => 'legacy.support.orders.index',
        'keep' => ['sort' => $sort, 'dir' => $dir, 'customer' => $listParams['customer'] ?? null, 'status' => $listParams['status'] ?? null, 'q' => $listParams['q'] ?? null],
    ])

    <div class="kp-scroll">
        <table class="kp-wide kp-list">
            <thead>
                <tr>
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'nr', 'label' => __('legacy_support.classic.col.number')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'datum', 'label' => __('legacy_support.classic.col.date')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'kunde', 'label' => __('legacy_support.classic.col.customer')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'telefon', 'label' => __('legacy_support.classic.col.phone')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'termin', 'label' => __('legacy_support.classic.col.appointment')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'status', 'label' => __('legacy_support.classic.col.status'), 'colspan' => 5])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.orders.index', 'params' => $listParams, 'key' => 'objekt', 'label' => __('legacy_support.classic.col.object_order')])
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->id }}</td>
                        <td>{{ $order->datum?->fdate() }}</td>
                        <td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td>
                        <td>{{ $order->telefon }}</td>
                        <td>{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) }}</td>
                        <td class="kp-icon">
                            <a href="{{ route('legacy.support.orders.show', $order) }}" title="{{ __('legacy_support.classic.action.view') }}" aria-label="{{ __('legacy_support.classic.action.view') }} {{ $order->id }}">@include('legacy.classic.support._box', ['kind' => 'view'])</a>
                        </td>
                        <td class="kp-icon">@include('legacy.classic.support._order_status', ['order' => $order])</td>
                        @if ($writeEnabled)
                            <td class="kp-icon">
                                <a href="{{ route('legacy.support.orders.edit', $order) }}" title="{{ __('legacy_support.classic.action.entry') }}" aria-label="{{ __('legacy_support.classic.action.entry') }} {{ $order->id }}">@include('legacy.classic.support._box', ['kind' => 'edit'])</a>
                            </td>
                            <td class="kp-icon">
                                <a href="{{ route('legacy.support.orders.edit', [$order, 'form' => 'appointment']) }}" title="{{ __('legacy_support.classic.action.appointment') }}" aria-label="{{ __('legacy_support.classic.action.appointment') }} {{ $order->id }}">@include('legacy.classic.support._box', ['kind' => 'appointment'])</a>
                            </td>
                            <td class="kp-icon">
                                <a href="{{ route('legacy.support.orders.edit', [$order, 'form' => 'customer']) }}" title="{{ __('legacy_support.classic.action.customer') }}" aria-label="{{ __('legacy_support.classic.action.customer') }} {{ $order->id }}">@include('legacy.classic.support._box', ['kind' => 'customer'])</a>
                            </td>
                        @else
                            <td></td><td></td><td></td>
                        @endif
                        <td>{{ \CommonToolkit\Helper\Data\StringHelper::truncate($order->objekt . ', ' . $order->auftrag, 55, '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="kp-center">{{ __('legacy_support.orders.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('legacy.classic._pager', ['paginator' => $orders])
@endsection
