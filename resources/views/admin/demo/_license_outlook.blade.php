{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _license_outlook.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tarif-Vorschau einer Demo-Organisation (MVP-836): unter welcher Lizenz die
  Demo tatsächlich läuft. Quelle: DemoSeederService::licenseOutlook().
--}}
@php
    /** @var array{source: string, plan: string} $licenseOutlook */
    $planLabel = __('values.' . $licenseOutlook['plan']);
    $text = match ($licenseOutlook['source']) {
        'issuer' => __('Diese Instanz stellt der Demo-Organisation eine befristete Lizenz im Tarif :plan aus (:days Tage).', ['plan' => $planLabel, 'days' => (int) config('demo.license_days', 30)]),
        'organization' => __('Die Demo-Organisation hat eine eigene Lizenz (Tarif :plan).', ['plan' => $planLabel]),
        'installation' => __('Die Demo-Organisation nutzt die Installationslizenz (Tarif :plan).', ['plan' => $planLabel]),
        'development' => __('Entwicklungsumgebung: ohne Lizenz gilt der Plan der Organisation (:plan).', ['plan' => $planLabel]),
        default => __('Ohne Lizenz läuft die Demo-Organisation im Tarif Free, die meisten Module bleiben gesperrt. Vorher eine Lizenz ausstellen oder einspielen.'),
    };
    $tone = $licenseOutlook['source'] === 'free' ? 'alert-warning' : 'alert-info';
@endphp
<div class="alert {{ $tone }} text-sm" role="status">
    <x-icon :name="$licenseOutlook['source'] === 'free' ? 'warning' : 'verified'" />
    <span>
        {{ $text }}
        @if ($licenseOutlook['source'] === 'free')
            @can(\App\Enums\User\Permission::PlatformLicenseInstall->value)
                <a class="link" href="{{ route('admin.license.index') }}">{{ __('Zur Lizenzverwaltung') }}</a>
            @endcan
        @endif
    </span>
</div>
