<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsConnectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Finance\{BankAccount, EbicsConnection, PaymentRun};
use App\Models\Platform\User;
use App\Services\Finance\Ebics\{EbicsConnectionService, EbicsLetterPdfRenderer, EbicsPaymentSubmission, EbicsStatementService};
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use App\Support\UrlSafety;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * EBICS-Bankzugang eines Bankkontos (MVP-124): Einrichtung Schritt für
 * Schritt, Initialisierungsbrief, Auszugsabruf und Einreichung von Zahlläufen.
 */
class EbicsConnectionController extends Controller {
    private const IDENT = '/^[A-Za-z0-9][A-Za-z0-9,=]{0,34}$/';

    public function show(BankAccount $bankAccount): View {
        Gate::authorize(P::FinanceConfig->value);

        return view('finance.ebics.show', [
            'account' => $bankAccount,
            'connection' => $this->connection($bankAccount),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount, EbicsConnectionService $service): RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);
        $data = $request->validate([
            'host_url' => ['required', 'string', 'url:https', 'max:255', static function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ! UrlSafety::isAcceptableExternalHttpUrl($value)) {
                    $fail((string) __('ebics.error.host_not_allowed'));
                }
            }],
            'ebics_host' => ['required', 'string', 'regex:' . self::IDENT],
            'ebics_partner' => ['required', 'string', 'regex:' . self::IDENT],
            'ebics_user' => ['required', 'string', 'regex:' . self::IDENT],
        ]);

        return $this->run(fn () => $service->save($bankAccount, $data, $this->actor()), 'saved');
    }

    public function keys(BankAccount $bankAccount, EbicsConnectionService $service): RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);

        return $this->run(fn () => $service->createKeys($this->required($bankAccount), $this->actor()), 'keys_created');
    }

    public function initialize(BankAccount $bankAccount, EbicsConnectionService $service): RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);

        return $this->run(fn () => $service->initialize($this->required($bankAccount), $this->actor()), 'initialized');
    }

    public function letter(BankAccount $bankAccount, EbicsLetterPdfRenderer $renderer): Response|RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);
        try {
            $pdf = $renderer->render($this->required($bankAccount), $this->actor());
        } catch (EbicsException $e) {
            return back()->with('error', __('ebics.error.' . $e->reason));
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="EBICS-Initialisierungsbrief.pdf"',
        ]);
    }

    public function activate(BankAccount $bankAccount, EbicsConnectionService $service): RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);

        return $this->run(fn () => $service->activate($this->required($bankAccount), $this->actor()), 'activated');
    }

    public function suspend(BankAccount $bankAccount, EbicsConnectionService $service): RedirectResponse {
        Gate::authorize(P::FinanceConfig->value);

        return $this->run(fn () => $service->suspend($this->required($bankAccount), $this->actor()), 'suspended');
    }

    public function fetch(BankAccount $bankAccount, EbicsStatementService $statements): RedirectResponse {
        Gate::authorize(P::FinancePaymentImport->value);
        try {
            $counts = $statements->fetch($this->required($bankAccount), $this->actor());
        } catch (EbicsException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', __('ebics.flash.fetched', $counts));
    }

    public function submit(PaymentRun $run, EbicsPaymentSubmission $submission): RedirectResponse {
        Gate::authorize(P::FinancePaymentRelease->value);
        try {
            $orderId = $submission->submit($run, $this->actor());
        } catch (EbicsException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', __('ebics.flash.submitted', ['order' => $orderId !== '' ? $orderId : '—']));
    }

    private function run(callable $step, string $flash): RedirectResponse {
        try {
            $step();
        } catch (EbicsException $e) {
            return back()->withInput()->with('error', $this->message($e));
        }

        return back()->with('success', __('ebics.flash.' . $flash));
    }

    private function message(EbicsException $e): string {
        $text = (string) __('ebics.error.' . $e->reason);

        return $e->ebicsCode !== null ? $text . ' (' . $e->ebicsCode . ')' : $text;
    }

    private function connection(BankAccount $account): ?EbicsConnection {
        return EbicsConnection::query()->where('bank_account_id', $account->id)->with('journal')->first();
    }

    private function required(BankAccount $account): EbicsConnection {
        $connection = EbicsConnection::query()->where('bank_account_id', $account->id)->first();
        abort_unless($connection instanceof EbicsConnection, 404);

        return $connection;
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
