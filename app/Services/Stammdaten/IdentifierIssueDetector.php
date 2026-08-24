<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierIssueDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Stammdaten;

use CommonToolkit\Helper\Data\BankHelper;
use CommonToolkit\ValueObjects\{Bic, GermanTaxId, GermanTaxNumber, Gtin, Iban, VatNumber};
use Illuminate\Database\Eloquent\Model;

/**
 * Findet unbrauchbare Identifikatoren (USt-IdNr., Steuernummer, IBAN, BIC, GTIN)
 * an einem Datensatz — für den Hinweis in der Oberfläche und den Prüflauf
 * `identifiers:audit`.
 *
 * Zweck ist nicht, Werte zu ändern: Wer die Daten kennt, korrigiert sie an der
 * Oberfläche, von wo aus sie in die angebundenen Dienste zurückfließen. Ein
 * stiller Automatismus würde falsche Zahlungsdaten nur verstecken.
 *
 * Wo eine Korrektur eindeutig ableitbar ist (IBAN mit einer Stelle zu viel und
 * genau einer prüfziffernkonformen Variante), liefert der Befund sie mit.
 */
class IdentifierIssueDetector {
    /** Feld => Art des Identifikators. */
    private const FIELDS = [
        'vat_id' => 'vat',
        'tax_number' => 'tax_number',
        'tax_identification_number' => 'tax_id',
        'bank_iban' => 'iban',
        'bank_bic' => 'bic',
        'iban' => 'iban',
        'bic' => 'bic',
        'gtin' => 'gtin',
    ];

    /**
     * Befunde eines Kontakts **samt** seiner Bankverbindungen.
     *
     * Die hinterlegten Bankverbindungen ({@see \App\Models\ContactBankAccount})
     * haben keine eigene Detailseite — ohne diesen Durchgriff bliebe eine falsche
     * IBAN dort unsichtbar, obwohl sie im Zahlungsverkehr landet.
     *
     * @return list<array{field: string, value: string, reason: string, suggestion: ?string, context: ?string}>
     */
    public function forContact(Model $contact): array {
        $issues = array_map(static fn (array $issue): array => $issue + ['context' => null], $this->forModel($contact));

        if (! method_exists($contact, 'bankAccounts')) {
            return $issues;
        }

        $accounts = $contact->bankAccounts()->get();
        if ($accounts->isNotEmpty()) {
            // F8/E6: bank_iban/bank_bic am Kontakt sind nur noch die Projektion
            // der primären Bankverbindung — dieselbe kaputte IBAN würde sonst
            // doppelt gemeldet (einmal inline, einmal an der Bankverbindung).
            $issues = array_values(array_filter(
                $issues,
                static fn (array $issue): bool => ! in_array($issue['field'], ['bank_iban', 'bank_bic'], true),
            ));
        }

        foreach ($accounts as $account) {
            $label = trim((string) ($account->bank_name ?? ''));
            foreach ($this->forModel($account) as $issue) {
                $issues[] = $issue + ['context' => (string) __('stammdaten.identifier.context.bank_account', [
                    'label' => $label !== '' ? $label : (string) __('stammdaten.identifier.context.bank_account_fallback'),
                ])];
            }
        }

        return $issues;
    }

    /**
     * Befunde eines Artikels **samt** seiner Varianten — eine falsche GTIN an
     * der Variante wandert genauso in Katalog- und Bestell-Exporte wie eine am
     * Artikel selbst.
     *
     * @return list<array{field: string, value: string, reason: string, suggestion: ?string, context: ?string}>
     */
    public function forArticle(Model $article): array {
        $issues = array_map(static fn (array $issue): array => $issue + ['context' => null], $this->forModel($article));

        if (! method_exists($article, 'variants')) {
            return $issues;
        }

        foreach ($article->variants()->get() as $variant) {
            $label = trim((string) ($variant->sku ?? '')) ?: (string) $variant->getKey();
            foreach ($this->forModel($variant) as $issue) {
                $issues[] = $issue + ['context' => (string) __('stammdaten.identifier.context.variant', ['label' => $label])];
            }
        }

        return $issues;
    }

    /**
     * @return list<array{field: string, value: string, reason: string, suggestion: ?string}>
     */
    public function forModel(Model $model): array {
        $issues = [];

        foreach (self::FIELDS as $field => $kind) {
            $raw = $model->getAttribute($field);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }

            $issue = $this->check($kind, trim($raw));
            if ($issue !== null) {
                $issues[] = ['field' => $field] + $issue;
            }
        }

        return $issues;
    }

    /**
     * @return array{value: string, reason: string, suggestion: ?string}|null
     */
    private function check(string $kind, string $value): ?array {
        $valid = match ($kind) {
            'vat' => VatNumber::tryFrom($value) !== null,
            'tax_number' => GermanTaxNumber::tryFrom($value) !== null,
            'tax_id' => GermanTaxId::tryFrom($value) !== null,
            'iban' => Iban::tryFrom($value) !== null,
            'bic' => Bic::tryFrom($value) !== null,
            'gtin' => Gtin::tryFrom($value) !== null,
            default => true,
        };

        if ($valid) {
            return null;
        }

        return [
            'value' => $value,
            'reason' => $this->reason($kind, $value),
            'suggestion' => $this->suggestion($kind, $value),
        ];
    }

    /** Fachliche Erklärung statt „ungültig" — sonst rät der Anwender. */
    private function reason(string $kind, string $value): string {
        return match (true) {
            $kind === 'vat' && $this->looksLikeGermanTaxNumber($value) => (string) __('stammdaten.identifier.reason.tax_number_in_vat_field'),
            $kind === 'vat' => (string) __('stammdaten.identifier.reason.vat_invalid'),
            $kind === 'tax_number' && preg_match('/^\D*(?:\d\D*){1,9}$/', $value) === 1 => (string) __('stammdaten.identifier.reason.tax_number_too_short'),
            $kind === 'tax_number' => (string) __('stammdaten.identifier.reason.tax_number_invalid'),
            $kind === 'tax_id' => (string) __('stammdaten.identifier.reason.tax_id_invalid'),
            $kind === 'iban' => (string) __('stammdaten.identifier.reason.iban_invalid'),
            $kind === 'bic' => (string) __('stammdaten.identifier.reason.bic_invalid'),
            $kind === 'gtin' => (string) __('stammdaten.identifier.reason.gtin_invalid'),
            default => (string) __('stammdaten.identifier.reason.generic'),
        };
    }

    /**
     * Eindeutig ableitbare Korrektur — nur wenn genau eine Variante gültig ist.
     */
    private function suggestion(string $kind, string $value): ?string {
        if ($kind === 'iban') {
            // W4.4: Kompaktierung über den Toolkit-Format-Anker statt inline
            // (byte-gleiche Semantik: Whitespace-Strip + Uppercase).
            $compact = BankHelper::normalizeIBAN($value) ?? '';
            $candidates = [];
            for ($i = 4; $i < strlen($compact); $i++) {
                $candidate = substr($compact, 0, $i) . substr($compact, $i + 1);
                if (Iban::tryFrom($candidate) !== null) {
                    $candidates[$candidate] = true;
                }
            }

            return count($candidates) === 1 ? (string) array_key_first($candidates) : null;
        }

        // Für BIC gibt es keinen belastbaren Vorschlag: ein achtstelliger BIC ist
        // entweder gültig (dann kein Befund) oder falsch, und ein fehlender
        // Buchstabe lässt sich ohne Bankverzeichnis nicht eindeutig ergänzen.
        return null;
    }

    /** Schrägstrich-Schreibweise oder fehlendes Länderkürzel ⇒ Steuernummer. */
    private function looksLikeGermanTaxNumber(string $value): bool {
        return str_contains($value, '/') || preg_match('/^\d/', $value) === 1;
    }
}
