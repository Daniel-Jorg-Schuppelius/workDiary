{{--
    Gemeinsame Tab-Navigation für den Rechnungs-/Beleg-Bereich:
    Angebote (Feature 066) · Rechnungen (lokales Rechnungsmodul) · Belege (Lexoffice-Cache).
--}}
<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('quotes.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('quotes.*')])
       @if (request()->routeIs('quotes.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">request_quote</span>
        {{ __('Angebote') }}
    </a>
    <a role="tab"
       href="{{ route('invoices.index') }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('invoices.*')])
       @if (request()->routeIs('invoices.*')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">receipt_long</span>
        {{ __('Rechnungen') }}
    </a>
    @if (\Illuminate\Support\Facades\Route::has('lexoffice.vouchers.index'))
        <a role="tab"
           href="{{ route('lexoffice.vouchers.index') }}"
           @class(['tab gap-1', 'tab-active' => request()->routeIs('lexoffice.vouchers.*')])
           @if (request()->routeIs('lexoffice.vouchers.*')) aria-current="page" @endif>
            <span class="material-symbols-outlined text-base" aria-hidden="true">receipt</span>
            {{ __('Belege') }}
        </a>
    @endif
</div>
