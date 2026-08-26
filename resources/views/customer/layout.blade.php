{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    use App\Enums\CustomerPortal\PortalCapability;

    /** @var \App\Models\User|null $portalUser */
    $portalUser = auth('customer')->user();
    // Zentrale Bereichsfreigaben (MVP-511): Navigation zeigt nur, was für
    // diesen Kunden ausdrücklich freigegeben ist (Default-Deny).
    $portalCustomer = $portalUser?->customer;
    $portalVisibility = app(\App\Services\CustomerPortal\PortalVisibility::class);
    $portalAllows = fn (PortalCapability $cap): bool => $portalCustomer !== null && $portalVisibility->allows($portalCustomer, $cap);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Customer-Portal') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-base-200 min-h-screen text-base-content">
    {{-- Sprunglink (WCAG 2.4.1) — gleiches Muster wie layouts/app; MUSS das
         erste fokussierbare Element sein. --}}
    <a href="#main-content"
       class="wd-skip-link sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-100 focus:rounded-box focus:bg-primary focus:px-4 focus:py-2 focus:font-semibold focus:text-primary-content focus:shadow-lg focus:ring-2 focus:ring-primary/60">
        {{ __('Zum Inhalt springen') }}
    </a>
    <header class="bg-base-100 border-b border-base-300">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-2 font-semibold">
                <x-icon name="support_agent" />
                <span>{{ __('Customer-Portal') }}</span>
            </a>
            @if ($portalUser)
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('customer.dashboard') }}" class="hover:underline">{{ __('Übersicht') }}</a>
                    @if ($portalAllows(PortalCapability::Diary))
                        <a href="{{ route('customer.diary.index') }}" class="hover:underline">{{ __('Auftragsbuch') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Assets))
                        <a href="{{ route('customer.assets.index') }}" class="hover:underline">{{ __('Objekte') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Documents))
                        <a href="{{ route('customer.documents.index') }}" class="hover:underline">{{ __('document.customer.portal.title') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::TimeEntries))
                        <a href="{{ route('customer.time-entries.index') }}" class="hover:underline">{{ __('Zeiten') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Invoices))
                        <a href="{{ route('customer.invoices.index') }}" class="hover:underline">{{ __('Rechnungen') }}</a>
                        {{-- Abrechnungskonto (Feature 098): nur bei aktivem Konto-Modus-Profil. --}}
                        @if ($portalCustomer?->billingAgreement?->keepsLedger())
                            <a href="{{ route('customer.billing.index') }}" class="hover:underline">{{ __('customer-billing.portal_title') }}</a>
                        @endif
                    @endif
                    @if ($portalAllows(PortalCapability::Tickets))
                        <a href="{{ route('customer.tickets.index') }}" class="hover:underline">{{ __('Tickets') }}</a>
                        <a href="{{ route('customer.catalog.index') }}" class="hover:underline">{{ __('Servicekatalog') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::OpenIssues))
                        <a href="{{ route('customer.known-errors.index') }}" class="hover:underline">{{ __('Bekannte Fehler') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Claims))
                        <a href="{{ route('customer.claims.index') }}" class="hover:underline">{{ __('Reklamationen') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Rentals))
                        <a href="{{ route('customer.rentals.index') }}" class="hover:underline">{{ __('Verleih') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::RentalRequests))
                        <a href="{{ route('customer.rentals.requests.index') }}" class="hover:underline">{{ __('Verleih-Anfrage') }}</a>
                    @endif
                    @if ($portalAllows(PortalCapability::Queries))
                        <a href="{{ route('customer.queries.index') }}" class="hover:underline">{{ __('Rückfragen') }}</a>
                    @endif
                    <a href="{{ route('customer.profile.show') }}" class="hover:underline">{{ __('Profil') }}</a>
                    <a href="{{ route('customer.2fa.show') }}" class="hover:underline" title="{{ __('Zwei-Faktor-Authentifizierung') }}">{{ __('Sicherheit') }}</a>
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <x-button type="submit" tone="ghost" size="sm" icon="logout"><span>{{ __('Abmelden') }}</span></x-button>
                    </form>
                </nav>
            @endif
        </div>
    </header>
    <main id="main-content" class="max-w-5xl mx-auto px-4 py-6">
        {{-- Zentrale Flashes (I4, Gegenstück zu layouts/app): Portal-Views
             rendern success/error/warning/info nicht erneut. --}}
        @if (session('status'))
            <div role="status" class="alert alert-info mb-4">{{ session('status') }}</div>
        @endif
        @if (session('success'))
            <div role="status" class="alert alert-success mb-4 text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="alert alert-error mb-4 text-sm">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div role="alert" class="alert alert-warning mb-4 text-sm">{{ session('warning') }}</div>
        @endif
        @if (session('info'))
            <div role="status" class="alert alert-info mb-4 text-sm">{{ session('info') }}</div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')

        {{-- Stehendes Pagination-Panel: <x-pagination standing> pusht hierher
             (App-Konvention, Gegenstück zu layouts/app). Ohne Pagination wird
             nichts gepusht → kein Leerraum. --}}
        @stack('page-footer')
    </main>
</body>
</html>
