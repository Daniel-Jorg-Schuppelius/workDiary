{{--
    Gemeinsame Tab-Navigation für den Rechnungs-/Beleg-Bereich:
    Angebote (Feature 066) · Rechnungen (lokales Rechnungsmodul) · Belege (Lexoffice-Cache).
--}}
<x-tab-nav :items="[
    ['route' => 'quotes.index', 'routeIs' => 'quotes.*', 'icon' => 'request_quote', 'label' => __('Angebote')],
    ['route' => 'invoices.index', 'routeIs' => 'invoices.*', 'icon' => 'receipt_long', 'label' => __('Rechnungen')],
    ['route' => 'lexoffice.vouchers.index', 'routeIs' => 'lexoffice.vouchers.*', 'icon' => 'receipt', 'label' => __('Belege'),
     'when' => \Illuminate\Support\Facades\Route::has('lexoffice.vouchers.index')],
]" />
