<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingRetentionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Invoicing\RetentionKind;
use App\Http\Controllers\Controller;
use App\Models\Finance\IncomingInvoiceRetention;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Billing\Sepa\IncomingRetentionService;
use App\Support\ErrorText;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Einbehalte an Eingangsrechnungen (MVP-953); Recht wie die Rechnungsfreigabe. */
class IncomingRetentionController extends Controller {
    public function store(Request $request, IncomingEInvoice $incoming, IncomingRetentionService $retentions): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(RetentionKind::class)],
            'percent' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_without:amount'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999', 'required_without:percent'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $retentions->add(
                $incoming,
                RetentionKind::from($data['kind']),
                isset($data['percent']) ? NumberHelper::normalizeDecimalString((string) $data['percent']) : null,
                isset($data['amount']) ? NumberHelper::normalizeDecimalString((string) $data['amount']) : null,
                isset($data['due_on']) ? CarbonImmutable::parse($data['due_on']) : null,
                $data['note'] ?? null,
                $this->authUser(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => ErrorText::for($e)])->withInput();
        }

        return back()->with('success', __('sepa.retention.flash.saved'));
    }

    public function release(IncomingInvoiceRetention $retention, IncomingRetentionService $retentions): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        try {
            $retentions->release($retention, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('sepa.retention.flash.released'));
    }

    public function destroy(IncomingInvoiceRetention $retention, IncomingRetentionService $retentions): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        try {
            $retentions->remove($retention);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('sepa.retention.flash.removed'));
    }
}
