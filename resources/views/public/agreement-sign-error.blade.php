{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-sign-error.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('contract-signing.public.error_title')" main="mx-auto max-w-xl p-4">
    <div role="alert" class="alert alert-error">
        <span>{{ $message ?? __('contract-signing.error.link_unusable') }}</span>
    </div>
    <p class="mt-3 text-sm text-muted">{{ __('contract-signing.public.error_hint') }}</p>
</x-public-page>
