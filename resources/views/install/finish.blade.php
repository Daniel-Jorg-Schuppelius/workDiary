{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : finish.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.install')

@section('install-content')
<div class="text-center">
    <span class="material-symbols-outlined text-success" style="font-size: 3rem;" aria-hidden="true">task_alt</span>
    <h2 class="card-title mt-2 justify-center">{{ __('Bereit zum Abschluss') }}</h2>
    <p class="mt-2 text-sm text-base-content/70">
        {{ __('Alle Schritte sind abgeschlossen. Mit dem Abschluss wird die Installation gesperrt und kann nicht erneut ausgeführt werden.') }}
    </p>
</div>

<form method="POST" action="{{ route('install.complete') }}" class="mt-6 flex justify-between">
    @csrf
    <x-button href="{{ route('install.integrations') }}" tone="ghost" size="sm">{{ __('Zurück') }}</x-button>
    <x-button type="submit" tone="primary" size="sm" icon="lock">{{ __('Installation abschließen') }}</x-button>
</form>
@endsection
