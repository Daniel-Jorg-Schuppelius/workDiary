<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailableDocumentProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document\Contracts;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * Erweiterungspunkt des Belegversands (MVP-863): Ein Modul liefert für seine
 * Belegarten PDF, Dateiname, Vorlagen-Platzhalter und Empfänger; der
 * {@see \App\Services\Document\DocumentMailService} kennt kein Fachmodul.
 * Registrierung über `Manifest::extensions()`.
 */
interface MailableDocumentProvider {
    /** @return list<RenderDocumentKind> */
    public function kinds(): array;

    /** @return class-string<Model> Modell, das die Belegart erwartet */
    public function modelClass(RenderDocumentKind $kind): string;

    /** PDF-Bytes — exakt der Renderer des Downloads. */
    public function pdfBytes(Model $document, RenderDocumentKind $kind): string;

    /** Anhang-Dateiname inkl. `.pdf` — deckungsgleich mit dem Download. */
    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string;

    /** Belegnummer für Titel und Platzhalter. */
    public function documentNumber(Model $document, RenderDocumentKind $kind): string;

    /** @return array<string, string> Platzhalter der Mailvorlage (ohne die gemeinsamen company_name/document_label/custom_text) */
    public function variables(Model $document, RenderDocumentKind $kind): array;

    /** Empfänger-Vorbelegung (primäre E-Mail des Kunden bzw. Lieferanten). */
    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string;

    /** Kunde, dessen Belegsprache für die Vorlage gilt — null: Systemsprache. */
    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer;

    /** Fachliche Wirkung nach dem Versand (z. B. Schreiben festschreiben); Standard: nichts. */
    public function afterSent(Model $document, RenderDocumentKind $kind): void;
}
