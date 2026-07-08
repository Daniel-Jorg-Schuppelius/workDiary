<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailReferenceMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\{Customer, Invoice, Organization, Project};
use App\Services\Integration\Match\MatchStrategy;

/**
 * Betreff-/Referenz-Matching für eingehende Mails (Feature 056, MVP-117): sucht
 * in Betreff + Text nach Referenznummern (Kunden-, Rechnungs-, Projektnummer)
 * und löst sie auf den zugehörigen Kunden auf. Erhöht NUR die Match-Konfidenz
 * (Kandidatenvorschlag) — es findet nie eine automatische Zuordnung statt; die
 * Auflösung bleibt eine bewusste Entscheidung in der Integrations-Inbox.
 *
 * Mandantengrenze: alle Lookups sind org-gescopt — eine fremde Referenznummer
 * trifft in der eigenen Organisation nie.
 */
class MailReferenceMatcher {
    /** Kappe gegen extrem lange Mails (Signaturen, Verläufe). */
    private const MAX_TOKENS = 60;

    /**
     * Kunden-Kandidaten aus den im Text gefundenen Referenznummern.
     *
     * @return list<array{model: Customer, confidence: string, reasons: list<string>}>
     */
    public function customerCandidates(Organization $organization, string $text): array {
        $tokens = $this->extractTokens($text);
        if ($tokens === []) {
            return [];
        }

        /** @var array<int, list<string>> $reasonsByCustomer */
        $reasonsByCustomer = [];

        foreach (Customer::query()->where('organization_id', $organization->id)->whereIn('number', $tokens)->get(['id', 'number']) as $customer) {
            $reasonsByCustomer[(int) $customer->getKey()][] = (string) __('mail.reference.customer_number', ['number' => (string) $customer->number]);
        }

        foreach (Invoice::query()->where('organization_id', $organization->id)->whereNotNull('customer_id')->whereIn('number', $tokens)->get(['id', 'customer_id', 'number']) as $invoice) {
            $reasonsByCustomer[(int) $invoice->customer_id][] = (string) __('mail.reference.invoice_number', ['number' => (string) $invoice->number]);
        }

        foreach (Project::query()->where('organization_id', $organization->id)->whereNotNull('customer_id')->whereIn('number', $tokens)->get(['id', 'customer_id', 'number']) as $project) {
            $reasonsByCustomer[(int) $project->customer_id][] = (string) __('mail.reference.project_number', ['number' => (string) $project->number]);
        }

        if ($reasonsByCustomer === []) {
            return [];
        }

        // Kunden gebündelt und org-gescopt laden (doppelte Mandantengrenze).
        $candidates = [];
        foreach (Customer::query()->where('organization_id', $organization->id)->whereKey(array_keys($reasonsByCustomer))->get() as $customer) {
            $candidates[] = [
                'model' => $customer,
                'confidence' => MatchStrategy::EXACT, // exakte Nummer = starkes Signal
                'reasons' => array_values(array_unique($reasonsByCustomer[(int) $customer->getKey()])),
            ];
        }

        return $candidates;
    }

    /**
     * Referenzartige Tokens aus dem Text (optionales Buchstabenpräfix + Ziffern
     * mit -/‑Trennern), in Original- UND Großschreibung (case-tolerantes Matching
     * gegen die gespeicherten Nummern).
     *
     * @return list<string>
     */
    private function extractTokens(string $text): array {
        if (! preg_match_all('/\b[A-Za-z]{0,5}[-\/]?\d{2,}(?:[-\/]\d+)*\b/u', $text, $matches)) {
            return [];
        }

        $tokens = [];
        foreach ($matches[0] as $raw) {
            $token = trim($raw);
            if (strlen($token) < 3) {
                continue;
            }
            $tokens[$token] = true;
            $tokens[strtoupper($token)] = true;
        }

        return array_slice(array_keys($tokens), 0, self::MAX_TOKENS);
    }
}
