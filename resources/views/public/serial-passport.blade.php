<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('inventory.serial.verify.title') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<div class="max-w-md mx-auto p-4 flex flex-col gap-4">
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h1 class="card-title">{{ __('inventory.serial.verify.title') }}</h1>
            <p class="text-sm opacity-70">{{ $orgName }}</p>
            <form method="GET" action="{{ route('serials.public-passport', $token) }}" class="flex items-end gap-2 mt-2">
                <input name="serial" value="{{ $query }}" autofocus
                       placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                       class="input input-bordered w-full font-mono">
                <button type="submit" class="btn btn-primary">{{ __('inventory.serial.action.search') }}</button>
            </form>
        </div>
    </div>

    @if ($searched)
        @if ($serial === null)
            <div class="alert alert-error">{{ __('inventory.serial.verify.not_found') }}</div>
        @else
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center gap-2">
                        <span class="font-mono">{{ $serial->serial_no }}</span>
                        <span class="badge">{{ $serial->status->label() }}</span>
                    </div>
                    {{-- Bewusst ohne personenbezogene Daten (kein Kunde). --}}
                    <dl class="grid grid-cols-2 gap-y-1 text-sm mt-2">
                        <dt class="opacity-60">{{ __('inventory.serial.field.article') }}</dt>
                        <dd>{{ $serial->article?->name }}</dd>
                        <dt class="opacity-60">{{ __('inventory.serial.field.source') }}</dt>
                        <dd>{{ $serial->source->label() }}</dd>
                    </dl>
                </div>
            </div>
        @endif
    @endif
</div>
</body>
</html>
