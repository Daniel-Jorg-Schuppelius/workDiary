{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _customer_status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Status aus Kundensicht wie im Altportal: neu, in Bearbeitung, erledigt, archiviert. Erwartet: $order --}}
@php
    $status = (int) $order->status;
    $statusKind = match (true) {
        $status === \App\Legacy\Models\LegacySupportOrder::STATUS_NEW => 'new',
        $status >= \App\Legacy\Models\LegacySupportOrder::STATUS_ARCHIVED => 'archived',
        $status >= \App\Legacy\Models\LegacySupportOrder::STATUS_DONE => 'done',
        default => 'seen',
    };
@endphp
<span class="kp-status">@include('legacy.classic.support._box', ['kind' => $statusKind]){{ $order->customerStatusLabel() }}</span>
