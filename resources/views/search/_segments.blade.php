{{-- Text mit hervorgehobenen Fundstellen; jedes Segment escaped, kein Leerraum zwischen den Segmenten. Erwartet: $segments (list<array{0: string, 1: bool}>). --}}
@foreach ($segments as [$segmentText, $segmentHit])<{{ $segmentHit ? 'mark' : 'span' }} @class(['rounded-sm bg-warning/40 px-0.5 text-base-content' => $segmentHit])>{{ $segmentText }}</{{ $segmentHit ? 'mark' : 'span' }}>@endforeach
