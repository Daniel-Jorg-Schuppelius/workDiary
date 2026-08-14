{{--
  Created on   : Sat May 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : missing-organization.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- errors/_page-Gerüst statt eigener Kopie (Vollaudit 2026-07, N42);
     reportable=false wie zuvor — ohne Organisation ist der
     Problem-melden-Flow (org-gebunden) nicht nutzbar. --}}
@include('errors._page', [
    'icon' => 'domain_disabled',
    'tone' => 'warning',
    'title' => __('Keine Organisation zugeordnet'),
    'message' => $userMessage,
    'extraNote' => __('Bitte wenden Sie sich an Ihre Administration, damit Ihr Konto einer Organisation zugewiesen wird.'),
    'reportable' => false,
    'details' => config('app.debug') && ! empty($modelShortName) ? ('Model: ' . $modelShortName) : null,
])
