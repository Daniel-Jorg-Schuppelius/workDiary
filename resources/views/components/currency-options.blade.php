{{--
    Options-Liste für Währungs-Selects (Toolkit-Enum CommonToolkit\Enums\CurrencyCode):
    gängige Währungen zuerst, dann alle aktiven ISO-4217-Codes alphabetisch.
    Verwendung: <select name="currency"><x-currency-options :selected="$model->currency?->value" /></select>
--}}
@props(['selected' => null, 'nullable' => false, 'nullLabel' => null])

@php($preferred = [\CommonToolkit\Enums\CurrencyCode::Euro, \CommonToolkit\Enums\CurrencyCode::SwissFranc, \CommonToolkit\Enums\CurrencyCode::USDollar, \CommonToolkit\Enums\CurrencyCode::BritishPound])
@php($rest = collect(\CommonToolkit\Enums\CurrencyCode::cases())->reject(fn($c) => $c->isHistorical() || in_array($c, $preferred, true))->sortBy(fn($c) => $c->value))

@if ($nullable)
    <option value="">{{ $nullLabel ?? __('— Standard —') }}</option>
@endif
@foreach ($preferred as $code)
    <option value="{{ $code->value }}" @selected($selected === $code->value)>{{ $code->value }} — {{ $code->getLabel() }}</option>
@endforeach
<option disabled>──────────</option>
@foreach ($rest as $code)
    <option value="{{ $code->value }}" @selected($selected === $code->value)>{{ $code->value }} — {{ $code->getLabel() }}</option>
@endforeach
