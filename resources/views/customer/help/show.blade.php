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
    <a href="{{ route('customer.help.index') }}" class="text-sm hover:underline">← {{ __('customer_help.back') }}</a>
    <h1 class="mb-3 mt-2 text-2xl font-semibold">{{ $row->title }}</h1>
    <article class="prose max-w-none">
        {!! $row->body_html !!}
    </article>
    @if ($related !== [])
        <h2 class="mb-2 mt-6 text-lg font-semibold">{{ __('customer_help.related') }}</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($related as $item)
                <li><a href="{{ route('customer.help.show', $item['topic']) }}" class="hover:underline">{{ $item['title'] }}</a></li>
            @endforeach
        </ul>
    @endif
@endsection
