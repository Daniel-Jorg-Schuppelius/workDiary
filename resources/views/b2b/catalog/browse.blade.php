{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : browse.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('b2b.catalog.layout')

@section('title', __('b2b_catalog.public.title'))
@section('subtitle', $access->customer?->name)

@section('content')
    <div class="card">
        <form method="GET" action="{{ route('b2b-punchout.browse', ['org' => $organization->slug]) }}" class="toolbar">
            <input type="hidden" name="t" value="{{ $token }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('b2b_catalog.public.search_placeholder') }}" aria-label="{{ __('b2b_catalog.public.search_placeholder') }}">
            <button type="submit" class="btn secondary">{{ __('b2b_catalog.public.search') }}</button>
        </form>

        @if ($items->isEmpty())
            <p class="muted">{{ __('b2b_catalog.public.empty') }}</p>
        @else
            <form method="POST" action="{{ route('b2b-punchout.transfer', ['org' => $organization->slug]) }}">
                <input type="hidden" name="t" value="{{ $token }}">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('b2b_catalog.public.col_number') }}</th>
                            <th>{{ __('b2b_catalog.public.col_name') }}</th>
                            <th>{{ __('b2b_catalog.public.col_unit') }}</th>
                            <th class="num">{{ __('b2b_catalog.public.col_price') }}</th>
                            <th class="num">{{ __('b2b_catalog.public.col_quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($metalService = app(\App\Services\Procurement\MetalSurchargeService::class))
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->article?->number }}</td>
                                <td>
                                    {{ $item->article?->name }}
                                    @if (trim((string) $item->article?->description) !== '')
                                        <div class="muted">{{ \Illuminate\Support\Str::limit((string) $item->article->description, 120) }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->article?->base_unit }}</td>
                                <td class="num">
                                    {{ $item->effectivePrice()?->format() }}
                                    {{-- MVP-603: Tagespreis-Kupferzuschlag separat ausgewiesen --}}
                                    @php($copper = $item->article !== null ? $metalService->salesSurcharge($item->article) : null)
                                    @if ($copper !== null)
                                        <div class="muted">+ {{ $copper->format() }} {{ __('b2b_catalog.copper_surcharge_label') }}</div>
                                    @endif
                                </td>
                                <td class="num">
                                    <input type="number" name="qty[{{ $item->sqid }}]" value="" min="0" step="any"
                                           aria-label="{{ __('b2b_catalog.public.col_quantity') }} {{ $item->article?->number }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="toolbar" style="margin-top:14px;">
                    <span class="muted">
                        {{ __('b2b_catalog.public.page_of', ['current' => $items->currentPage(), 'last' => $items->lastPage()]) }}
                    </span>
                    <span>
                        @if ($items->previousPageUrl())
                            <a class="btn secondary" href="{{ $items->previousPageUrl() }}">{{ __('b2b_catalog.public.prev') }}</a>
                        @endif
                        @if ($items->nextPageUrl())
                            <a class="btn secondary" href="{{ $items->nextPageUrl() }}">{{ __('b2b_catalog.public.next') }}</a>
                        @endif
                        <button type="submit" class="btn">{{ __('b2b_catalog.public.to_cart') }}</button>
                    </span>
                </div>
            </form>
        @endif
    </div>
@endsection
