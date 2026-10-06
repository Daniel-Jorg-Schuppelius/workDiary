{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Hilfe im Kundenportal (MVP-959). Erwartet: $topics --}}
@extends('customer.layout')

@section('title', __('customer_help.title'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('customer_help.title') }}</h1>
    <p class="mb-4 text-sm text-base-content/70">{{ __('customer_help.subtitle') }}</p>
    <ul class="grid gap-2 sm:grid-cols-2">
        @forelse ($topics as $topic)
            <li>
                <x-card as="a" padding="p-3" class="block hover:bg-base-200" href="{{ route('customer.help.show', $topic->topic) }}">
                    <span class="font-medium">{{ $topic->title }}</span>
                </x-card>
            </li>
        @empty
            <li class="text-sm text-base-content/70">{{ __('customer_help.empty') }}</li>
        @endforelse
    </ul>
@endsection
