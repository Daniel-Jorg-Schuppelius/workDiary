<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningRunController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\{DunningException, DunningService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Mahnlauf-Cockpit (Feature 127, MVP-691 — Vollscan H8): Arbeitsliste aller
 * lokal geführten, überfälligen Rechnungen, deren nächste Mahnstufe fällig
 * ist (Karenz der Stufe seit Fälligkeit bzw. letzter Mahnung erreicht,
 * Stufe < 3, keine Mahnsperre). Sammelmahnung = je Rechnung ein
 * {@see DunningService::dunInvoice}-Vollzug mit Stufen-Defaults + Mail an
 * die Rechnungs-E-Mail; Fehler werden je Rechnung gesammelt.
 *
 * Rechnungshoheit (Feature 045): extern fakturierte Kunden erscheinen hier
 * NIE — gemahnt wird dort, wo der Beleg geführt wird (z. B. Lexoffice).
 */
class DunningRunController extends Controller {
    public function __construct(private readonly DunningService $dunning) {}

    public function index(Request $request): View {
        abort_unless($request->user()?->canManageBilling() ?? false, 403);

        [$candidates, $waiting, $blocked] = $this->partition();

        $openSum = 0.0;
        foreach ($candidates as $row) {
            $openSum += $row['open'];
        }

        return view('finance.dunning.index', [
            'candidates' => $candidates,
            'waiting' => $waiting,
            'blocked' => $blocked,
            'openSum' => round($openSum, 2),
            'interestRate' => $this->dunning->interestRate(),
        ]);
    }

    /** Sammelmahnung: je Rechnung Stufen-Defaults + Mail; Fehler einzeln sammeln. */
    public function run(Request $request): RedirectResponse {
        abort_unless($request->user()?->canManageBilling() ?? false, 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'max:64'],
        ]);

        $done = 0;
        $errors = [];
        foreach ($data['ids'] as $raw) {
            $id = Sqid::decode(Invoice::class, (string) $raw);
            /** @var Invoice|null $invoice */
            $invoice = $id !== null ? Invoice::query()->with('customer')->find($id) : null;
            if ($invoice === null) {
                continue; // fremde/gelöschte IDs still übergehen (org-Scope filtert)
            }

            $number = (string) ($invoice->number ?? $raw);
            // Kandidaten-Recheck: Die Liste kann veraltet sein — Hoheit,
            // Karenz, Sperre und Stufe zählen zum Zeitpunkt des Vollzugs.
            if (! $this->dunning->isLocallyBilled($invoice)) {
                $errors[] = $number . ': ' . __('finance.dunning.error_external');

                continue;
            }
            if (! $this->dunning->isReadyForNextStep($invoice)) {
                $errors[] = $number . ': ' . __('finance.dunning.error_not_ready');

                continue;
            }

            try {
                $this->dunning->dunInvoice($invoice, [
                    'apply_defaults' => true,
                    'send_mail' => true,
                    // Rechnungs-E-Mail: primärer Ansprechpartner, sonst Kundenadresse.
                    'email' => (string) ($invoice->customer->primaryContact()['email'] ?? $invoice->customer->email ?? ''),
                ]);
                $done++;
            } catch (DunningException $e) {
                $errors[] = $number . ': ' . $e->getMessage();
            }
        }

        $redirect = redirect()->route('finance.dunning.index');
        if ($done > 0) {
            $redirect->with('success', trans_choice('finance.dunning.flash_run', $done, ['count' => $done]));
        }
        if ($errors !== []) {
            $redirect->with('error', __('finance.dunning.flash_errors', ['errors' => implode(' · ', $errors)]));
        }

        return $redirect;
    }

    /**
     * Überfällige, lokal geführte Rechnungen in Kandidaten (nächste Stufe
     * fällig), Wartende (Karenz läuft) und Gesperrte (Mahnsperre) teilen.
     * Höchststufe (Level 3) fällt bewusst heraus — mehr als drei Stufen kennt
     * das Mahnwesen nicht (danach: Übergabe an Inkasso/Mahnbescheid).
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function partition(): array {
        $today = CarbonImmutable::today();
        $invoices = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today->toDateString())
            ->with(['customer.organization'])
            ->orderBy('due_on')
            ->get();

        $candidates = [];
        $waiting = [];
        $blocked = [];
        foreach ($invoices as $invoice) {
            if (! $this->dunning->isLocallyBilled($invoice)) {
                continue;
            }

            $row = $this->row($invoice, $today);
            if ($invoice->isDunningBlocked()) {
                $blocked[] = $row;

                continue;
            }
            if ((int) $invoice->dunning_level >= DunningService::MAX_LEVEL) {
                continue;
            }
            if ($this->dunning->isReadyForNextStep($invoice, $today)) {
                $candidates[] = $row;
            } else {
                $waiting[] = $row;
            }
        }

        return [$candidates, $waiting, $blocked];
    }

    /** @return array<string, mixed> */
    private function row(Invoice $invoice, CarbonImmutable $today): array {
        $nextLevel = min(DunningService::MAX_LEVEL, (int) $invoice->dunning_level + 1);

        return [
            'invoice' => $invoice,
            'open' => $this->dunning->openAmount($invoice)->toFloat(),
            'overdue_days' => (int) CarbonImmutable::parse((string) $invoice->due_on?->toDateString())->diffInDays($today, false),
            'next_level' => $nextLevel,
            'fee' => $this->dunning->stepConfig($nextLevel)['fee'],
            'next_due_on' => $this->dunning->nextStepDueOn($invoice),
        ];
    }
}
