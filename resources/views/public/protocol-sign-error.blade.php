{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : protocol-sign-error.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('Signaturlink ungültig')" main="mx-auto max-w-xl p-4">
    <div role="alert" class="alert alert-error">
        <span>{{ $message ?? __('protocol.signature.tokenExpired') }}</span>
    </div>
</x-public-page>
