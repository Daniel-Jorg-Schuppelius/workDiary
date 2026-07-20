@extends('careers.layout', ['embed' => false])

@section('title', __('Offene Stellen'))

@section('content')
    @forelse($postings as $posting)
        <div class="card">
            <h2><a href="{{ route('careers.show', ['org' => $organization->slug, 'posting' => $posting->public_slug]) }}">{{ $posting->public_title }}</a></h2>
            @if($posting->work_location)
                <p class="meta">{{ $posting->work_location }}</p>
            @endif
            @if($posting->public_summary)
                <p>{{ $posting->public_summary }}</p>
            @endif
            @if($posting->application_deadline)
                <p class="muted">{{ __('Bewerbungsschluss') }}: {{ $posting->application_deadline->format('d.m.Y') }}</p>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">{{ __('Zurzeit sind keine Stellen ausgeschrieben.') }}</p></div>
    @endforelse
@endsection
