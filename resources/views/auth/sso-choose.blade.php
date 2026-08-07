{{-- Anbieterauswahl (Feature 057-Ausbau): mehrere aktive SSO-Verbindungen einer
     Organisation. Jede Verbindung startet den Flow präzise per Sqid. --}}
@extends('layouts.guest')

@section('title', __('Anmeldeanbieter wählen'))
@section('headline', __('Anmeldeanbieter wählen'))
@section('intro', __('sso.choose.hint', ['org' => $organization->name]))

@section('content')
    <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
        <ul class="space-y-3">
            @foreach ($connections as $connection)
                <li>
                    <a href="{{ route('sso.start', ['slug' => $organization->slug, 'connection' => $connection->sqid]) }}"
                       class="flex items-center gap-3 rounded-2xl border border-base-content/15 bg-base-200/60 px-4 py-3 text-base-content transition hover:border-primary/60 hover:bg-base-200">
                        <x-icon name="login" class="text-primary" />
                        <span class="font-medium">{{ $connection->label }}</span>
                        <span class="ms-auto text-xs text-base-content/60">{{ $connection->provider_type->label() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/70">
        <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">← {{ __('sso.discover.back_to_login') }}</a>
    </p>
@endsection
