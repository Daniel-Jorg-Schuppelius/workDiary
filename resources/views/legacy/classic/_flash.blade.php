{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _flash.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldungen als schlichter Text wie im Altportal; dieses Layout zeigt keine Flashes zentral. --}}
@foreach (['success', 'status'] as $flashKey)
    @if (session($flashKey))
        <p class="kp-msg" role="status">{{ session($flashKey) }}</p>
    @endif
@endforeach
@if (session('error'))
    <p class="kp-msg kp-msg--error" role="alert">{{ session('error') }}</p>
@endif
<x-validation-errors class="kp-msg kp-msg--error" />
