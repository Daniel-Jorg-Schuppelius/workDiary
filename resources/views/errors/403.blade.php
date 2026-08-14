{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 403.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@include('errors._page', [
    'code' => 403,
    'icon' => 'lock',
    'tone' => 'warning',
    'title' => __('errors.403.title'),
    'message' => ($exception ?? null) && $exception->getMessage() !== '' && $exception->getMessage() !== 'This action is unauthorized.'
        ? $exception->getMessage()
        : __('errors.403.message'),
])
