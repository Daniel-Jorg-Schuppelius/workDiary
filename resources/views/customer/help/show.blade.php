{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Hilfeartikel im Kundenportal (MVP-959). body_html ist beim Einlesen escaped gerendert (html_input=escape). Erwartet: $row, $related --}}
@extends('customer.layout')

@section('title', $row->title)

@section('content')
    <x-page-toolbar :title="$row->title" :back="route('customer.help.index')" :back-label="__('customer_help.back')" class="mb-4" />
    <x-card as="article" class="prose max-w-none">
        {!! $row->body_html !!}
    </x-card>
    @if ($related !== [])
        <h2 class="mb-2 mt-6 text-lg font-semibold">{{ __('customer_help.related') }}</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($related as $item)
                <li><a href="{{ route('customer.help.show', $item['topic']) }}" class="hover:underline">{{ $item['title'] }}</a></li>
            @endforeach
        </ul>
    @endif
@endsection
