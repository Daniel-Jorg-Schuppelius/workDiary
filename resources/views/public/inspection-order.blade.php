{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : inspection-order.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Link des Prüfdienstleisters (MVP-938). Erwartet: $order, $orgName, $token --}}
@php
    use App\Enums\AssetCompliance\{AssetInspectionOrderStatus as S, AssetInspectionResult};
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('inspection_order.public_title', ['org' => $orgName]) }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="max-w-3xl mx-auto p-4 flex flex-col gap-4">
    <x-card class="flex flex-col gap-1">
        <h1 class="card-title">{{ __('inspection_order.public_title', ['org' => $orgName]) }}</h1>
        <p class="text-sm font-medium">{{ $order->title }}</p>
        <p class="text-sm"><span class="wd-badge badge-ghost">{{ $order->status->label() }}</span></p>
    </x-card>
    @if (session('status'))
        <div role="status" class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif

    <x-card :title="__('inspection_order.field.items')">
        <ul class="text-sm">
            @foreach ($order->items as $item)
                <li>{{ $item->asset?->name }} ({{ $item->asset?->asset_no }})</li>
            @endforeach
        </ul>
    </x-card>

    @if (in_array($order->status, [S::Requested, S::Offered], true))
        <x-card :title="__('inspection_order.public_offer')">
            <form method="POST" action="{{ route('inspection-order.public.offer', $token) }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                <x-input-field name="offer_amount" type="number" step="0.01" min="0" :label="__('inspection_order.field.offer_amount') . ' (EUR, netto)'" :value="old('offer_amount', $order->offer_amount)" required />
                <x-input-field name="offer_planned_on" type="date" :label="__('inspection_order.field.offer_planned_on')" :value="old('offer_planned_on', $order->offer_planned_on?->toDateString())" required />
                <x-textarea-field name="offer_note" :label="__('inspection_order.field.offer_note')" rows="2" span="2">{{ old('offer_note', $order->offer_note) }}</x-textarea-field>
                <div class="md:col-span-2 flex justify-end"><x-button type="submit">{{ __('inspection_order.public_submit_offer') }}</x-button></div>
            </form>
        </x-card>
    @elseif ($order->status === S::Accepted)
        <x-card :title="__('inspection_order.public_report')">
            <form method="POST" action="{{ route('inspection-order.public.report', $token) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                @error('items')<p class="text-sm text-error">{{ $message }}</p>@enderror
                @foreach ($order->items as $item)
                    <fieldset class="rounded-box border border-base-300 p-3">
                        <legend class="px-1 text-sm font-semibold">{{ $item->asset?->name }} ({{ $item->asset?->asset_no }})</legend>
                        <div class="grid gap-2 md:grid-cols-2">
                            <x-select-field :name="'items[' . $item->sqid . '][result]'" :id="'result-' . $item->sqid" :label="__('inspection_order.field.result')" :error="'items.' . $item->sqid . '.result'">
                                <option value="">—</option>
                                @foreach (AssetInspectionResult::cases() as $result)
                                    <option value="{{ $result->value }}">{{ $result->label() }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field :name="'items[' . $item->sqid . '][performed_on]'" :id="'performed-' . $item->sqid" type="date" :label="__('inspection_order.field.performed_on')" />
                            <x-input-field :name="'items[' . $item->sqid . '][valid_until]'" :id="'valid-' . $item->sqid" type="date" :label="__('inspection_order.field.valid_until')" />
                            <x-input-field :name="'items[' . $item->sqid . '][certificate_no]'" :id="'cert-' . $item->sqid" :label="__('inspection_order.field.certificate_no')" />
                            <div class="md:col-span-2">
                                <label class="label" for="file-{{ $item->sqid }}"><span class="label-text">{{ __('inspection_order.field.certificate_file') }}</span></label>
                                <input id="file-{{ $item->sqid }}" type="file" name="certificates[{{ $item->sqid }}]" accept="application/pdf,image/*" class="file-input file-input-bordered file-input-sm w-full">
                            </div>
                        </div>
                    </fieldset>
                @endforeach
                <div class="flex justify-end"><x-button type="submit">{{ __('inspection_order.public_submit_report') }}</x-button></div>
            </form>
        </x-card>
    @elseif ($order->status === S::Reported)
        <div role="status" class="alert alert-info text-sm">{{ __('inspection_order.public_reported') }}</div>
    @endif
</main>
</body>
</html>
