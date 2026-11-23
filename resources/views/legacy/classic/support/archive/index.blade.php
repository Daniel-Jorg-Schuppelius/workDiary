{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archiv des Support-Portals im Aussehen des Altportals (admin/kp_archiv.php). --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_support.classic.archive_title'))

@section('content')
    @php
        $listParams = array_filter([
            'year' => $year === null ? 'all' : (string) $year,
            'customer' => $filters['customer'] ?? null,
            'q' => $search !== '' ? $search : null,
        ]);
    @endphp

    @include('legacy.classic.support._office_menu')

    <h2>{{ __('legacy_support.classic.archive_title') }}</h2>
    <p>{{ __('legacy_support.classic.logged_in_as', ['name' => (string) (auth()->user()?->name ?? '')]) }}</p>
    @include('legacy.classic._flash')

    @include('legacy.classic.support._year_select', [
        'route' => 'legacy.support.archive.index',
        'keep' => ['sort' => $sort, 'dir' => $dir, 'customer' => $listParams['customer'] ?? null, 'q' => $listParams['q'] ?? null],
    ])

    <div class="kp-scroll">
        <table class="kp-wide kp-list">
            <thead>
                <tr>
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'nr', 'label' => __('legacy_support.classic.col.number')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'datum', 'label' => __('legacy_support.classic.col.date')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'kunde', 'label' => __('legacy_support.classic.col.customer')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'telefon', 'label' => __('legacy_support.classic.col.phone')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'termin', 'label' => __('legacy_support.classic.col.appointment')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'status', 'label' => __('legacy_support.classic.col.status'), 'colspan' => 2])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.archive.index', 'params' => $listParams, 'key' => 'objekt', 'label' => __('legacy_support.classic.col.object_order')])
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
                            <a href="{{ route('legacy.support.archive.show', $order) }}" title="{{ __('legacy_support.classic.action.view') }}" aria-label="{{ __('legacy_support.classic.action.view') }} {{ $order->id }}">@include('legacy.classic.support._box', ['kind' => 'view'])</a>
                        </td>
                        <td class="kp-icon">@include('legacy.classic.support._order_status', ['order' => $order])</td>
                        <td>{{ \CommonToolkit\Helper\Data\StringHelper::truncate($order->objekt . ', ' . $order->auftrag, 55, '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="kp-center">{{ __('legacy_support.archive.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('legacy.classic._pager', ['paginator' => $orders])
@endsection
