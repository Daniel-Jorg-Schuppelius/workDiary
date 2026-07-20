@extends('careers.layout', ['embed' => true])

@section('title', $content['title'] ?? __('Stelle'))

@section('content')
    @include('careers._detail')
@endsection
