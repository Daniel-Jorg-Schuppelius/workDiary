{{--
  Created on   : Mon Nov 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : shell.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Klassische Ansicht für angemeldete Nutzer: Kopfzeile der App, darunter Menü und Masken im Aussehen der Altportale. --}}
@extends('layouts.app')
@section('legacy-classic', '1')
@section('wrapper-height-class', '')
@section('nav-title')
    @yield('title')
@endsection
@section('main-class', trim('kp-classic kp-page ' . $__env->yieldContent('bodyClass')))

@push('head')
    @vite(['resources/css/legacy-classic.css', 'resources/js/legacy-classic.js'])
@endpush
