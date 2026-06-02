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
    <a href="{{ route('install.integrations') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
    <button type="submit" class="btn btn-sm btn-primary">
        <span class="material-symbols-outlined" aria-hidden="true">lock</span>
        {{ __('Installation abschließen') }}
    </button>
</form>
@endsection
