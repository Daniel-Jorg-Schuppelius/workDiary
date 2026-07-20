@extends('careers.layout', ['embed' => false])

@section('title', $content['title'] ?? __('Stelle'))

@section('content')
    <p class="meta"><a href="{{ route('careers.index', ['org' => $organization->slug]) }}">&larr; {{ __('Alle Stellen') }}</a></p>
    @include('careers._detail')
@endsection
