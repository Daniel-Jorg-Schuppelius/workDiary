<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionOrderMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\AssetCompliance\AssetInspectionOrder;
use App\Models\Platform\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** Prüfauftrag an einen Dienstleister (MVP-938); der Link steht nur in der Mail. */
class InspectionOrderMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $orderId, public readonly string $token) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('inspection_order.mail.subject', ['org' => $this->organization()->name, 'title' => $this->order()->title]));
    }

    public function content(): Content {
        $order = $this->order();
        $text = (string) __('inspection_order.mail.body', [
            'org' => $this->organization()->name,
            'title' => $order->title,
            'count' => $order->items()->withoutGlobalScopes()->count(),
            'url' => route('inspection-order.public', $this->token),
            'until' => $order->expires_at->format('d.m.Y'),
        ]);

        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['html' => nl2br(e($text)), 'text' => $text]);
    }

    private function order(): AssetInspectionOrder {
        return AssetInspectionOrder::query()->withoutGlobalScopes()->findOrFail($this->orderId);
    }

    private function organization(): Organization {
        return Organization::query()->withoutGlobalScopes()->findOrFail($this->order()->organization_id);
    }
}
