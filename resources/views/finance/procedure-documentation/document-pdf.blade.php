{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : document-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Verfahrensdokumentation als PDF (Feature 134); Layout wie Auskunft/DSFA, ohne SVG. Variablen: $document, $payload, $publishedAt --}}
@extends('reports.pdf.layout')

@section('pdf-title', __('procedure-documentation.pdf.title') . ' ' . $document->displayVersion())
@section('pdf-heading', __('procedure-documentation.pdf.title') . ' — ' . ($payload['organization']['name'] ?? ''))

@push('pdf-styles')
<style>
    body { line-height: 1.45; }
    h2 { margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; font-size: 14px; }
    h3 { margin: 12px 0 4px; font-size: 12px; }
    h4 { margin: 10px 0 3px; font-size: 11px; color: #374151; }
    table { margin-top: 4px; }
    td.k { width: 34%; color: #374151; }
    .prose { white-space: pre-wrap; }
    .hint { color: #6b7280; font-size: 9px; margin-top: 4px; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('procedure-documentation.pdf.meta_version') }}: {{ $document->displayVersion() }} ·
    {{ __('procedure-documentation.pdf.meta_published') }}: {{ $publishedAt->format('d.m.Y H:i') }} ·
    {{ __('procedure-documentation.pdf.meta_generated') }}: {{ \Illuminate\Support\Carbon::parse($payload['generated_at'])->format('d.m.Y H:i') }}
@endsection

@section('pdf-table')
    <h2>{{ __('procedure-documentation.pdf.part_operator') }}</h2>
    @foreach (\App\Models\Finance\ProcedureDocumentation::TEXT_FIELDS as $field)
        <h3>{{ $loop->iteration }}. {{ __('procedure-documentation.text.' . $field) }}</h3>
        @if (filled($document->{$field}))
            <p class="prose">{{ $document->{$field} }}</p>
        @else
            <p class="hint">{{ __('procedure-documentation.generated.empty_text') }}</p>
        @endif
    @endforeach

    <h2>{{ __('procedure-documentation.pdf.part_generated') }}</h2>
    @foreach ($payload['sections'] as $section)
        <h3>{{ $section['title'] }}</h3>

        @if (! empty($section['fields']))
            <table>
                @foreach ($section['fields'] as $field)
                    <tr>
                        <td class="k">{{ $field['label'] }}</td>
                        <td>{{ $field['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach ($section['tables'] ?? [] as $table)
            <h4>{{ $table['title'] }}</h4>
            <table>
                <tr>
                    @foreach ($table['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
                @forelse ($table['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($table['columns']) }}" class="hint">{{ __('procedure-documentation.generated.no_rows') }}</td></tr>
                @endforelse
            </table>
        @endforeach

        @foreach ($section['notes'] ?? [] as $note)
            <p class="hint">{{ $note }}</p>
        @endforeach
    @endforeach

    <h2>{{ __('procedure-documentation.pdf.proof') }}</h2>
    <table>
        <tr><td class="k">{{ __('procedure-documentation.field.version') }}</td><td>{{ $document->displayVersion() }}</td></tr>
        <tr><td class="k">{{ __('procedure-documentation.field.generated_at') }}</td><td>{{ $payload['generated_at'] }}</td></tr>
        <tr><td class="k">{{ __('procedure-documentation.field.chains_verified') }}</td><td>{{ ! empty($payload['chains_verified']) ? __('procedure-documentation.yes') : __('procedure-documentation.no') }}</td></tr>
        <tr><td class="k">{{ __('procedure-documentation.field.snapshot_sha256') }}</td><td class="mono">{{ \CommonToolkit\Helper\Data\CryptoHelper::hash(\CommonToolkit\Helper\Data\JsonHelper::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</td></tr>
    </table>
    <p class="hint">{{ __('procedure-documentation.pdf.proof_hint') }}</p>
@endsection
