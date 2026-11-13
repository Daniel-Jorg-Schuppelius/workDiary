<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceSplitController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\AuthorizesOrganizationManagement;
use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sammel-PDF im Rechnungseingang (Feature 208, MVP-1662): vorgeschlagene
 * Rechnungsgrenzen ansehen, verschieben und bestätigen — oder den Eingang als
 * eine Rechnung behalten. Rechte wie beim Zuordnen (Abrechnungsrecht) und
 * zusätzlich die Sicht auf das Original im DMS; die Vorschaubilder entstehen
 * im Job, hier werden sie nur ausgeliefert.
 */
class IncomingInvoiceSplitController extends Controller {
    use AuthorizesOrganizationManagement;

    public function __construct(private readonly IncomingInvoiceSplitter $splitter) {}

    /** Dialog: Seiten mit Vorschaubild, Startseiten zum Umschalten. */
    public function form(IncomingEInvoice $incoming): View {
        $user = $this->authorizeBilling();
        $document = $this->documentOf($incoming);
        $document->auditConfidentialAccess($user);
        $proposal = (array) $incoming->split_proposal;
        $starts = array_values(array_map(intval(...), (array) ($proposal['starts'] ?? [1])));

        return view('finance.incoming-invoices._split_dialog', [
            'incoming' => $incoming,
            'pages' => IncomingInvoiceSplitter::pageCount($incoming),
            'starts' => $starts === [] ? [1] : $starts,
            'signals' => (array) ($proposal['signals'] ?? []),
            'images' => (bool) ($proposal['images'] ?? false),
            'blocker' => $this->splitter->blocker($incoming),
        ]);
    }

    /** Vorschaubild einer Seite (gerastert im Job, niedrige Auflösung). */
    public function page(IncomingEInvoice $incoming, string $page): BinaryFileResponse {
        $user = $this->authorizeBilling();
        $document = $this->documentOf($incoming);
        // Höchstens vierstellig (Route): kein Überlauf, kein 500er.
        abort_unless(ctype_digit($page) && strlen($page) <= 4, 404);
        $document->auditConfidentialAccess($user);
        $path = $this->splitter->imagePath($incoming, (int) $page);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Grenzen bestätigen: der Job teilt, erkennt und legt je Rechnung einen Eingang an. */
    public function split(Request $request, IncomingEInvoice $incoming): RedirectResponse {
        $actor = $this->authorizeBilling();
        $document = $this->documentOf($incoming);
        $data = $request->validate([
            'starts' => ['required', 'array', 'max:' . IncomingInvoiceSplitter::MAX_PARTS],
            'starts.*' => ['integer', 'min:1', 'max:' . IncomingInvoiceSplitter::MAX_PAGES],
        ]);

        try {
            $starts = $this->splitter->request($incoming, (array) $data['starts'], $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        // Synchrone Warteschlange (Tests, kleine Installationen): der Sammel-Eingang ist schon aufgeteilt.
        $fresh = IncomingEInvoice::query()->find($incoming->id);
        if ($fresh === null || $fresh->isSplitBundle()) {
            return redirect()->toList('finance.incoming-invoices.index')
                ->with('success', __('incoming_pdf.split.flash.done', ['count' => count($starts)]));
        }
        $error = ((array) $fresh->split_proposal)['error'] ?? null;
        if ($fresh->splitState() !== IncomingInvoiceSplitter::SPLITTING && is_string($error) && $error !== '') {
            return redirect()->route('finance.incoming-invoices.show', $document)->with('error', $error);
        }

        return redirect()->route('finance.incoming-invoices.show', $document)
            ->with('success', __('incoming_pdf.split.flash.queued', ['count' => count($starts)]));
    }

    /** Hängende Aufteilung zurücksetzen (Worker abgestürzt): zurück auf den Vorschlag. */
    public function reset(IncomingEInvoice $incoming): RedirectResponse {
        $actor = $this->authorizeBilling();
        $document = $this->documentOf($incoming);

        try {
            $this->splitter->resetStalled($incoming, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('finance.incoming-invoices.show', $document)->with('success', __('incoming_pdf.split.flash.reset'));
    }

    /** „Ist eine Rechnung“: Vorschlag verwerfen, Übergabe und Zahlung laufen weiter. */
    public function dismiss(IncomingEInvoice $incoming): RedirectResponse {
        $actor = $this->authorizeBilling();
        $document = $this->documentOf($incoming);

        try {
            $this->splitter->dismiss($incoming, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('finance.incoming-invoices.show', $document)->with('success', __('incoming_pdf.split.flash.dismissed'));
    }

    private function documentOf(IncomingEInvoice $incoming): Document {
        $document = $incoming->document;
        abort_if($document === null, 404);
        Gate::authorize('view', $document);

        return $document;
    }
}
