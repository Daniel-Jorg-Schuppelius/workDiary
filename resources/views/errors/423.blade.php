{{-- errors/_page-Gerüst statt eigener Kopie (Vollaudit 2026-07, N42);
     reportable=false wie zuvor — ein Lizenz-Block ist kein meldbarer Fehler. --}}
@include('errors._page', [
    'code' => 423,
    'icon' => 'workspace_premium',
    'tone' => 'primary',
    'title' => __('Modul nicht im Plan enthalten'),
    'message' => ($exception ?? null) && $exception->getMessage() ? $exception->getMessage() : __('Diese Funktion ist in Ihrem aktuellen Plan nicht verfügbar.'),
    'extraNote' => __('Für den Zugang ist ein höherer Plan erforderlich. Bitte wenden Sie sich an Ihre Administration.'),
    'reportable' => false,
])
