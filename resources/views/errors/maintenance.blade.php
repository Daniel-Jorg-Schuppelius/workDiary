{{-- errors/_page-Gerüst statt eigener Kopie (Vollaudit 2026-07, N42). --}}
@include('errors._page', [
    'icon' => 'engineering',
    'tone' => 'warning',
    'title' => __('Wartungsarbeiten'),
    'message' => $message ?? __('Dieser Bereich wird gerade gewartet. Bitte versuchen Sie es später erneut.'),
    'extraNote' => (($until ?? null) instanceof \Carbon\CarbonInterface)
        ? __('Voraussichtlich wieder verfügbar: :at', ['at' => $until->translatedFormat('d.m.Y H:i')])
        : null,
    'reportable' => false,
    'actions' => [
        ['label' => __('Erneut versuchen'), 'href' => url('/'), 'icon' => 'refresh'],
    ],
])
