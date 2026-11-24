{{--
  Created on   : Mon Nov 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : shell.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Klassische Ansicht für angemeldete Nutzer: Kopfzeile der App mit dem Menü des Altportals (Support-Portal bzw. Tagebuch), darunter die Masken im Aussehen der Altportale. --}}
@extends('layouts.app')
@section('legacy-classic', '1')
@section('wrapper-height-class', 'kp-fill')
@section('nav-title')
    @yield('title')
@endsection
@section('main-class', trim('kp-classic kp-page ' . $__env->yieldContent('bodyClass')))
@section('legacy-menu')
    @include(request()->routeIs('legacy.support.*') ? 'legacy.classic.support._office_menu' : 'legacy.classic.diary._menu')
@endsection

@push('head')
    @vite(['resources/css/legacy-classic.css', 'resources/js/legacy-classic.js'])
@endpush
