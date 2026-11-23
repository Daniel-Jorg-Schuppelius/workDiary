{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _flash.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldungen als Hinweiskasten; App-Hülle und eigenes Layout geben sie hier statt zentral aus. --}}
@foreach (['success' => ['kp-alert--success', 'status'], 'status' => ['kp-alert--success', 'status'], 'info' => ['', 'status'], 'warning' => ['kp-alert--warning', 'alert'], 'error' => ['kp-alert--error', 'alert']] as $flashKey => [$flashTone, $flashRole])
    @if (is_string(session($flashKey)) && session($flashKey) !== '')
        <p class="kp-alert {{ $flashTone }}" role="{{ $flashRole }}">{{ session($flashKey) }}</p>
    @endif
@endforeach
<x-validation-errors class="kp-alert kp-alert--error" />
