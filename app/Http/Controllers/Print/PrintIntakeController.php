<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Print;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Attachments\Attachment;
use App\Models\Customer\CustomerIntake;
use App\Models\Print\PrintOrder;
use App\Services\Customer\Intake\CustomerIntakeNotifier;
use App\Services\Print\PrintOrderService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Druckauftrag aus einem Kundeneingang (MVP-1076): Produktionsdatei aus den
 * Eingangsdateien festlegen und die Kunden-Druckfreigabe anfordern. Der
 * Eingang bleibt Herkunft und Nachweis; die Fachakte ist maßgeblich.
 */
class PrintIntakeController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly PrintOrderService $orders,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    public function bindIntakeFile(Request $request, PrintOrder $order, Attachment $attachment): RedirectResponse {
        Gate::authorize('update', $order);
        $intake = $this->intakeOf($order);
        abort_unless(
            $attachment->attachable_type === $intake->getMorphClass() && (int) $attachment->attachable_id === (int) $intake->getKey(),
            404,
        );

        $actor = $request->user() ?? abort(401);
        $this->orders->bindIntakeFile($order, $attachment, $actor);
        $intake->record('production_file_selected', ['file' => $attachment->original_name], $actor);

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.file_bound'));
    }

    public function requestCustomerApproval(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $intake = $this->intakeOf($order);

        $validated = $request->validate([
            'final_format' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'min:1'],
            'color_mode' => ['required', 'string', 'max:40'],
            'material' => ['required', 'string', 'max:120'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'finishing' => ['nullable', 'string', 'max:500'],
        ]);
        $validated['finishing'] = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['finishing'] ?? ''))), static fn (string $item): bool => $item !== ''));

        $actor = $request->user() ?? abort(401);
        $this->orders->requestCustomerApproval($order, $validated, $actor);
        $intake->record('print_approval_requested', ['file_hash' => $order->file_hash], $actor);
        $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::PRINT_APPROVAL);

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.intake.flash.approval_requested'));
    }

    /** Eingang, aus dem der Druckauftrag übernommen wurde — sonst 404 (Branchenprofil-Gate inklusive). */
    private function intakeOf(PrintOrder $order): CustomerIntake {
        $organization = $this->currentOrganization();
        abort_unless($organization->hasBranchProfile(PrintOrderService::PROFILE_CODE) && $order->organization_id === $organization->id, 404);

        return CustomerIntake::query()
            ->where('target_type', $order->getMorphClass())
            ->where('target_id', $order->id)
            ->firstOrFail();
    }
}
