<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintApprovalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Print\Portal;

use App\Enums\Notification\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\User;
use App\Models\Print\PrintOrder;
use App\Services\Customer\Intake\CustomerIntakeNotifier;
use App\Services\Print\PrintOrderService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Storage};
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kunden-Druckfreigabe im Portal (MVP-1076): der Kunde sieht die angeforderte
 * Dateiversion samt Prüfsumme und Parametern, lädt sie herunter und gibt sie
 * frei oder weist sie mit Begründung zurück. Nur am eigenen Eingang.
 */
class PrintApprovalController extends Controller {
    public function __construct(
        private readonly PrintOrderService $orders,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    /** Die zur Freigabe vorgelegte Dateiversion — nur solange sie gebunden und vorhanden ist. */
    public function file(CustomerIntake $intake): BinaryFileResponse {
        $order = $this->orderOf($intake);
        $version = $order->documentVersion;
        abort_unless(
            $version !== null && $order->hasProductionFile()
            && (int) data_get($order->customer_approval_request, 'file.document_version_id') === (int) $version->id,
            404,
        );
        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        return response()->download($disk->path($version->path), $version->original_name);
    }

    public function decide(Request $request, CustomerIntake $intake): RedirectResponse {
        $order = $this->orderOf($intake);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $approved = $data['decision'] === 'approve';
        $user = $this->portalUser();

        $this->orders->recordCustomerDecision($order, $user, $approved, $data['reason'] ?? null);
        $intake->record($approved ? 'print_approved' : 'print_declined', ['file_hash' => $order->file_hash, 'reason' => $approved ? null : ($data['reason'] ?? null)], $user);
        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, $approved ? 'print_approved_message' : 'print_declined_message');

        return redirect()->route('customer.intakes.show', $intake)
            ->with('status', __($approved ? 'print.intake.flash.approved' : 'print.intake.flash.declined'));
    }

    private function orderOf(CustomerIntake $intake): PrintOrder {
        $user = $this->portalUser();
        abort_unless(
            (int) $intake->organization_id === (int) $user->organization_id
            && (int) $intake->customer_id === (int) $user->customer_id,
            404,
        );
        $target = $intake->target_id !== null ? $intake->target()->withoutGlobalScopes()->first() : null;
        abort_unless($target instanceof PrintOrder && $target->customer_approval_request !== null, 404);

        return $target;
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
