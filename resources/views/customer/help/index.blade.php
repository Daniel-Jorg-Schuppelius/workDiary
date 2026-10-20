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
    <x-page-toolbar :title="__('customer_help.title')" :subtitle="__('customer_help.subtitle')" class="mb-4" />
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
