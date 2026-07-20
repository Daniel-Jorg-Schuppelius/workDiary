{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche, login-freie Angebots-Annahme (Feature 066, MVP-170):
  token-basiert (nur Hash gespeichert), datensparsam — Positionen, Summen
  und Bindefrist; Annahme/Teilannahme/Ablehnung mit Zeitstempel.
  Variablen: $quote (Quote), $token (string), $decided (bool)
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('Angebot :nr', ['nr' => $quote->number]) }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4 space-y-4">
    <div class="rounded-box bg-base-100 p-4 shadow">
        <div class="mb-1 flex items-center gap-2 text-xs text-base-content/60">
            <span class="badge badge-outline badge-sm">{{ __('Angebot') }}</span>
            <span>{{ $quote->number }} · V{{ $quote->version }}</span>
        </div>
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Angebot :nr', ['nr' => $quote->number]) }}</h1>
        @if ($quote->valid_until)
            <div class="mt-1 text-sm text-base-content/70">{{ __('Gültig bis :date', ['date' => $quote->valid_until->fdate()]) }}</div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-table>
        <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('Beschreibung') }}</th>
                    <th class="text-right">{{ __('Menge') }}</th>
                    <th class="text-right">{{ __('Einzelpreis') }}</th>
                    <th>{{ __('Art') }}</th>
                </tr>
        </x-slot:head>
        <x-slot:foot>
                <tr><td colspan="3" class="text-right font-bold">{{ __('Gesamt (netto zzgl. USt.)') }}</td><td class="text-right font-bold" colspan="2">{{ number_format((float) $quote->subtotal, 2, ',', '.') }} EUR</td></tr>
        </x-slot:foot>
                @foreach ($quote->items as $item)
                    <tr>
                        <td>{{ $item->position }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="text-right">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 2, ',', '.') }} EUR</td>
                        <td>{{ $item->optional ? __('Option') : __('Pflicht') }}</td>
                    </tr>
                @endforeach
    </x-table>

    @if ($quote->terms)
        <div class="rounded-box bg-base-100 p-4 shadow">
            <p class="whitespace-pre-line text-sm">{{ $quote->terms }}</p>
        </div>
    @endif

    @if ($decided)
        <div class="alert alert-info">
            {{ __('Zu diesem Angebot liegt bereits eine Entscheidung vor (:status, :date).', [
                'status' => __('values.' . $quote->status),
                'date' => optional($quote->decided_at)->fdatetime() ?? '—',
            ]) }}
        </div>
    @elseif ($quote->status === 'sent' && ! $quote->isExpired())
        <form method="POST" action="{{ route('quotes.portal.decide', $quote) }}" class="rounded-box bg-base-100 p-4 shadow space-y-3">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <h2 class="text-sm font-semibold">{{ __('Ihre Entscheidung') }}</h2>
            <div class="space-y-1">
                @foreach ($quote->items as $item)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="checkbox checkbox-sm" @checked(! $item->optional)>
                        <span class="label-text">{{ $item->position }}. {{ $item->description }} @if ($item->optional)<span class="text-xs text-base-content/60">({{ __('Option') }})</span>@endif</span>
                    </label>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="decision" value="accept" class="btn btn-primary btn-sm">{{ __('Angebot annehmen') }}</button>
                <button type="submit" name="decision" value="reject" class="btn btn-outline btn-sm">{{ __('Angebot ablehnen') }}</button>
            </div>
            <p class="text-xs text-base-content/60">{{ __('Ihre Auswahl wird mit Zeitstempel dokumentiert. Abgewählte Positionen gelten als nicht beauftragt.') }}</p>
        </form>
    @elseif ($quote->isExpired())
        <div class="alert alert-warning">{{ __('Die Bindefrist dieses Angebots ist abgelaufen — bitte kontaktieren Sie uns für eine neue Version.') }}</div>
    @endif
</main>
</body>
</html>
