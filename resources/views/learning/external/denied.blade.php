{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : denied.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Neutrale Antwort auf einen ungültigen Lernzugang (Feature 149, MVP-742).
  Ob der Link unbekannt, abgelaufen oder widerrufen ist, steht hier
  bewusst NICHT — das wäre eine Auskunft an Unbefugte.
--}}
@extends('layouts.guest')
@section('title', __('learning.external.link_invalid_title'))
@section('content')
<div class="w-full">
    <div class="alert alert-warning text-sm">
        <x-icon name="link_off" />
        <span>{{ __('learning.external.link_invalid') }}</span>
    </div>
    <p class="mt-3 text-sm text-base-content/70">{{ __('learning.external.link_invalid_hint') }}</p>
</div>
@endsection
