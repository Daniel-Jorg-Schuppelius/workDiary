{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Bestellseite (Feature 065, MVP-154): 032-Formular aus der Vorlage des
     Katalogeintrags; Upload-/Signatur-Felder werden im Portal ausgelassen. --}}

@section('content')
    <div class="mb-4">
        <a class="link link-hover text-sm" href="{{ route('customer.catalog.index') }}">← {{ __('Zurück zum Servicekatalog') }}</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $item->name }}</h1>
        @if ($item->description)
            <p class="mt-1 text-base-content/70">{{ $item->description }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('customer.catalog.order', $item) }}"
          class="rounded-box border border-base-300 bg-base-100 p-4 space-y-3">
        @csrf

        @forelse ($fields as $field)
            <x-field-input :field="$field" :wide="false" />
        @empty
            <p class="text-sm text-muted">{{ __('Für diese Leistung sind keine weiteren Angaben nötig.') }}</p>
        @endforelse

        <button type="submit" class="btn btn-primary">{{ __('Bestellung absenden') }}</button>
    </form>
@endsection
