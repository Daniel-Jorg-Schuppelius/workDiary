{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-thanks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('contract-signing.public.thanks_title')" main="mx-auto max-w-xl p-4">
    <div role="status" class="alert alert-success">
        <span>
            @if ($outcome === 'uploaded')
                {{ __('contract-signing.public.thanks_uploaded') }}
            @else
                {{ __('contract-signing.public.thanks_signed') }}
            @endif
        </span>
    </div>
    <p class="mt-3 text-sm text-muted">{{ __('contract-signing.public.thanks_hint') }}</p>
</x-public-page>
