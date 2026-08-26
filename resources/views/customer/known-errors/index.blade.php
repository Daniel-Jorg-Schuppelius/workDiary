{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('customer.layout')

{{-- Bekannte Fehler (Feature 065, MVP-156): read-only — nur Probleme mit
     status=known_error UND visibility=customer der eigenen Organisation. --}}

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-semibold">{{ __('Bekannte Fehler') }}</h1>
        <p class="text-sm text-muted">{{ __('Bekannte Störungen mit empfohlenem Workaround — an der dauerhaften Lösung wird gearbeitet.') }}</p>
    </div>

    @forelse ($problems as $problem)
        <div class="rounded-box border border-base-300 bg-base-100 p-4 mb-3">
            <h2 class="font-semibold">{{ $problem->title }}</h2>
            @if ($problem->workaround)
                <div class="mt-2 text-sm">
                    <span class="text-xs uppercase text-muted">{{ __('Workaround') }}</span>
                    <p class="whitespace-pre-wrap">{{ $problem->workaround }}</p>
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-sm text-muted">
            {{ __('Derzeit sind keine bekannten Fehler dokumentiert.') }}
        </div>
    @endforelse

    <x-pagination :paginator="$problems" standing />
@endsection
