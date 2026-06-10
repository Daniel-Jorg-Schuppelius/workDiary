@extends('customer.layout')

@section('content')
    <div class="max-w-2xl mx-auto mt-8 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined">verified_user</span>
                {{ __('Zwei-Faktor-Authentifizierung') }}
            </h1>
            <x-status-badge :tone="$enabled ? 'success' : ($pending ? 'warning' : 'ghost')" size="sm">
                {{ $enabled ? __('Aktiv') : ($pending ? __('Einrichtung offen') : __('Inaktiv')) }}
            </x-status-badge>
        </div>

        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning text-sm">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Recovery-Codes einmalig nach Bestätigung --}}
        @if (! empty($recoveryCodes))
            <div class="border border-warning/40 bg-warning/10 rounded p-4">
                <p class="font-semibold">{{ __('Recovery-Codes') }}</p>
                <p class="text-sm text-base-content/70">{{ __('Bewahren Sie diese Codes sicher auf. Jeder Code funktioniert einmal, falls Sie keinen Zugriff auf Ihre Authenticator-App haben.') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                    @foreach ($recoveryCodes as $rc)
                        <code class="bg-base-200 rounded px-2 py-1 text-center select-all">{{ $rc }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($enabled)
            {{-- Aktiv: deaktivieren --}}
            <div class="border border-success/30 bg-base-100 rounded p-4 space-y-3">
                <p class="flex items-center gap-2 font-semibold text-success">
                    <span class="material-symbols-outlined">check_circle</span> {{ __('Ihr Zugang ist mit einem zweiten Faktor geschützt.') }}
                </p>
                @unless (auth('customer')->user()->organization?->two_factor_required)
                    <form method="POST" action="{{ route('customer.2fa.disable') }}" class="flex items-end gap-2 border-t border-base-300 pt-3">
                        @csrf @method('DELETE')
                        <div class="grow">
                            <label class="block text-sm mb-1" for="dis-code">{{ __('Deaktivieren (aktueller Code)') }}</label>
                            <input id="dis-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100" required>
                        </div>
                        <button type="submit" class="btn btn-error">{{ __('Deaktivieren') }}</button>
                    </form>
                @else
                    <p class="text-sm text-base-content/60">{{ __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.') }}</p>
                @endunless
            </div>
        @elseif ($pending)
            {{-- Einrichtung: QR scannen + Code bestätigen --}}
            <div class="border border-base-300 bg-base-100 rounded p-4 space-y-4">
                <p class="font-semibold">{{ __('Einrichtung abschließen') }}</p>

                <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-start">
                    <div class="mx-auto w-fit border border-base-300 bg-white p-3 rounded">
                        {!! $qrSvg !!}
                    </div>

                    <div class="space-y-4">
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-base-content/70">
                            <li>{{ __('QR-Code mit einer Authenticator-App scannen (z. B. Google Authenticator, Aegis, 1Password).') }}</li>
                            <li>{{ __('Den dort angezeigten 6-stelligen Code unten eingeben.') }}</li>
                        </ol>

                        <div class="border border-base-300 bg-base-200/40 rounded p-3 text-xs">
                            <span class="uppercase tracking-wider text-base-content/60">{{ __('Manueller Schlüssel') }}</span>
                            <code class="mt-1 block break-all font-mono select-all">{{ $secret }}</code>
                        </div>

                        <form method="POST" action="{{ route('customer.2fa.confirm') }}" class="flex items-end gap-2">
                            @csrf
                            <div class="grow">
                                <label class="block text-sm mb-1" for="confirm-code">{{ __('Code aus der App') }}</label>
                                <input id="confirm-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="w-full border border-base-300 rounded px-3 py-2 bg-base-100 text-center tracking-[0.4em] font-mono" placeholder="000000" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('Bestätigen') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            {{-- Inaktiv: aktivieren --}}
            <div class="border border-base-300 bg-base-100 rounded p-4 space-y-3">
                <p class="text-sm text-base-content/70">{{ __('Schützen Sie Ihren Zugang zusätzlich mit einem Einmalcode aus einer Authenticator-App.') }}</p>
                <form method="POST" action="{{ route('customer.2fa.enable') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Aktivieren') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
