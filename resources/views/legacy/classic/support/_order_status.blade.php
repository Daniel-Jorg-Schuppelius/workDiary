{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _order_status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Status eines Auftrags in der Bürosicht wie im Altportal; verstrichene Termine pulsieren. Erwartet: $order --}}
@php
    $orderModel = \App\Legacy\Models\LegacySupportOrder::class;
    [$statusKind, $statusLabel] = match (true) {
        $order->isOverdue() => ['overdue', __('legacy_support.classic.legend.overdue')],
        (int) $order->status === $orderModel::STATUS_NEW => ['new', __('legacy_support.classic.legend.new')],
        (int) $order->status === $orderModel::STATUS_SEEN => ['seen', __('legacy_support.classic.legend.seen')],
        (int) $order->status === $orderModel::STATUS_SCHEDULED => ['scheduled', __('legacy_support.classic.legend.scheduled')],
        (int) $order->status === $orderModel::STATUS_DONE => ['done', __('legacy_support.classic.legend.done')],
        default => ['archived', __('legacy_support.classic.legend.archived')],
    };
@endphp
<span class="kp-status">@include('legacy.classic.support._box', ['kind' => $statusKind]){{ $statusLabel }}</span>
