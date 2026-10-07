{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dialog-page.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog-Fragment als Seite (RenderDialogFragmentAsPage). Formulare senden
     hier als normaler POST; Abbrechen/Schließen führt zurück (app.js). --}}
@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full max-w-3xl" data-dialog-page data-dialog-page-fallback="{{ url('/') }}">
        <div class="overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">
            {{ $fragment }}
        </div>
    </div>
@endsection
