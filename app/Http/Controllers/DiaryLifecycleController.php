<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryLifecycleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\{DiaryEntry, Protocol, User};
use App\Services\Diary\OrderService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use InvalidArgumentException;

class DiaryLifecycleController extends Controller {
    public function __invoke(Request $request, DiaryEntry $diary, string $action, OrderService $orders): RedirectResponse {
        Gate::authorize($this->ability($action), $diary);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($action) {
                'accept' => $orders->accept($diary, $actor),
                'start' => $orders->start($diary, $actor),
                'pause' => $orders->pause($diary, $actor, ...$this->pauseData($request)),
                'resume' => $orders->resume($diary, $actor),
                'complete' => $orders->complete($diary, $actor, $this->requiredText($request, 'summary', 5000)),
                'handover' => $orders->handover($diary, $actor, $this->protocol($request, $diary)),
                'markInvoiced' => $orders->markInvoiced($diary, $actor, $this->requiredText($request, 'reference', 120)),
                'cancel' => $orders->cancel($diary, $actor, $this->requiredText($request, 'reason', 2000)),
                default => abort(404),
            };
        } catch (InvalidOrderTransitionException|InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }

        return back()->with('success', __('Auftragsstatus aktualisiert.'));
    }

    private function ability(string $action): string {
        return match ($action) {
            'accept' => 'accept',
            'start' => 'start',
            'pause' => 'pause',
            'resume' => 'resume',
            'complete' => 'complete',
            'handover' => 'handover',
            'markInvoiced' => 'markInvoiced',
            'cancel' => 'cancel',
            default => abort(404),
        };
    }

    /** @return array{0: string, 1: string|null} */
    private function pauseData(Request $request): array {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:customer,material'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return [$data['reason'], $data['note'] ?? null];
    }

    private function requiredText(Request $request, string $field, int $max): string {
        $data = $request->validate([
            $field => ['required', 'string', 'max:' . $max],
        ]);

        return $data[$field];
    }

    private function protocol(Request $request, DiaryEntry $diary): Protocol {
        $data = $request->validate(['protocol_id' => ['required', 'integer']]);

        return Protocol::query()
            ->where('organization_id', $diary->organization_id)
            ->findOrFail((int) $data['protocol_id']);
    }
}
