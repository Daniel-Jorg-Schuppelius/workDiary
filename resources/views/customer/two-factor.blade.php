@extends('customer.layout')

@section('content')
    <div class="max-w-2xl mx-auto mt-8 space-y-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <span class="material-symbols-outlined">verified_user</span>
            {{ __('Zwei-Faktor-Authentifizierung') }}
        </h1>

        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning text-sm">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        @if (! empty($recoveryCodes))
            <div class="border border-warning/40 bg-warning/10 rounded p-4">
                <p class="font-semibold">{{ __('Recovery-Codes') }}</p>
                <p class="text-sm">{{ __('Bewahren Sie diese Codes sicher auf. Jeder Code funktioniert einmal.') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($recoveryCodes as $rc)
                        <code class="bg-base-200 rounded px-2 py-1">{{ $rc }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($enabled)
            <div class="border border-success/30 bg-base-100 rounded p-4">
                <p class="flex items-center gap-2 font-semibold text-success">
                    <span class="material-symbols-outlined">check_circle</span> {{ __('Aktiv') }}
                </p>
                @unless (auth('customer')->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('customer.2fa.disable') }}" class="mt-3 flex items-end gap-2">
                        @csrf @method('DELETE')
                        <div class="grow">
                            <label class="block text-sm mb-1" for="dis-code">{{ __('Deaktivieren (aktueller Code)') }}</label>
                            <input id="dis-code" name="code" type="text" inputmode="numeric" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100" required>
                        </div>
                        <button type="submit" class="btn btn-error">{{ __('Deaktivieren') }}</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-base-content/60">{{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.') }}</p>
                @endunless
            </div>
        @elseif ($pending)
            <div class="border border-base-300 bg-base-100 rounded p-4">
                <p class="font-semibold">{{ __('Einrichtung abschließen') }}</p>
                <p class="text-sm text-base-content/70">{{ __('Scannen Sie den QR-Code mit Ihrer Authenticator-App und geben Sie den Code ein.') }}</p>
                <div class="mt-4 flex flex-col items-center gap-3">
                    <div class="bg-white p-3 rounded">{!! $qrSvg !!}</div>
                    <p class="text-xs text-base-content/60">{{ __('Schlüssel') }}: <code class="select-all">{{ $secret }}</code></p>
                </div>
                <form method="POST" action="{{ route('customer.2fa.confirm') }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    <div class="grow">
                        <label class="block text-sm mb-1" for="confirm-code">{{ __('Code aus der App') }}</label>
                        <input id="confirm-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100" placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('Bestätigen') }}</button>
                </form>
            </div>
        @else
            <div class="border border-base-300 bg-base-100 rounded p-4">
                <p class="text-sm text-base-content/70 mb-3">{{ __('Schützen Sie Ihren Zugang zusätzlich mit einem Einmalcode aus einer Authenticator-App.') }}</p>
                <form method="POST" action="{{ route('customer.2fa.enable') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Aktivieren') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
