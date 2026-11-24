{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duties.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Verwaltung von Bereitschaft und Notdienst wie im Altportal (bereit.php/notdnst.php). Erwartet: $tab, $oncallItems, $notdienstItems, $canManageDuties, $sort, $dir --}}
@php
    $kind = $tab === 'bereitschaft' ? 'oncall' : 'notdienst';
    $items = $kind === 'oncall' ? $oncallItems : $notdienstItems;
    $cell = $kind === 'oncall' ? 'bereit' : 'notdnst';
    $canEdit = $canManageDuties && (bool) config('app.legacy_write_enabled');
    $sortParams = ['route' => 'legacy.diary.index', 'routeParams' => ['tab' => $tab], 'sort' => $sort, 'dir' => $dir];
@endphp
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.duty.' . $kind . '.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.duty.' . $kind . '.title') }}</h1>

    @if ($canEdit)
        <table class="kp-msg">
            <tr><td class="{{ $cell }}">&raquo; <a href="{{ route('legacy.' . $kind . '.create') }}">{{ __('legacy_classic.duty.' . $kind . '.new') }}</a> &laquo;</td></tr>
        </table>
    @endif
    <p>{{ __('legacy_classic.duty.' . $kind . '.existing') }}</p>
    <div class="kp-scroll">
        <table>
            <tr>
                <th>{{ __('legacy_classic.col.id') }}</th>
                @include('legacy.classic._sort', [...$sortParams, 'key' => 'mitarbeiter', 'label' => __('legacy_classic.col.employee')])
                @include('legacy.classic._sort', [...$sortParams, 'key' => 'von', 'label' => __('legacy_classic.col.from_date')])
                @include('legacy.classic._sort', [...$sortParams, 'key' => 'bis', 'label' => __('legacy_classic.col.until_date')])
                @if ($canEdit)
                    <th colspan="2">{{ __('legacy_classic.col.option') }}</th>
                @endif
            </tr>
            @forelse ($items as $item)
                <tr>
                    <td class="mitte">{{ $item->id }}</td>
                    <td>{{ $item->mitarbeiter?->uname }}</td>
                    <td>{{ $item->von?->format(\App\Support\Formats::date()) }}</td>
                    <td>{{ $item->bis?->format(\App\Support\Formats::date()) }}</td>
                    @if ($canEdit)
                        <td class="option-edit"><a href="{{ route('legacy.' . $kind . '.edit', $item) }}">{{ __('legacy_classic.action.edit') }}</a></td>
                        <td class="option-delete"><a href="{{ route('legacy.' . $kind . '.edit', [$item, 'delete' => 1]) }}">{{ __('legacy_classic.action.delete') }}</a></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canEdit ? 6 : 4 }}" class="mitte">{{ __('legacy_classic.none') }}</td></tr>
            @endforelse
        </table>
    </div>
    @include('legacy.classic._pager', ['paginator' => $items])
@endsection
