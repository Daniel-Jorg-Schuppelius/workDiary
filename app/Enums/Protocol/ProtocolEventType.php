<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolEventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

/**
 * Audit-Event-Typen, die in `protocol_events.event` persistiert werden.
 * String-Konstanten statt Enum-Backed-Typing, damit MVP-022/023 weitere
 * Werte ergaenzen koennen, ohne dass alte Datensaetze migriert werden muessen.
 */
final class ProtocolEventType {
    public const string Created = 'protocol.created';
    public const string ItemAdded = 'protocol.itemAdded';
    public const string ItemRemoved = 'protocol.itemRemoved';
    public const string ItemReordered = 'protocol.itemReordered';
    public const string ItemFilled = 'protocol.itemFilled';
    public const string RequestedReview = 'protocol.requestedReview';
    public const string ReturnedToDraft = 'protocol.returnedToDraft';
    public const string Signed = 'protocol.signed';
    public const string Archived = 'protocol.archived';
    public const string SupersededBy = 'protocol.supersededBy';
    public const string AttachmentAdded = 'protocol.attachmentAdded';
    public const string AttachmentRemoved = 'protocol.attachmentRemoved';
    public const string SignatureRequested = 'protocol.signatureRequested';
    public const string SignatureLinkOpened = 'protocol.signatureLinkOpened';
    public const string SignatureRejected = 'protocol.signatureRejected';
    // Widerruf externer Signatur-Links (Feature 012 MVP; Vollaudit 2026-07, M6).
    public const string SignatureLinkRevoked = 'protocol.signatureLinkRevoked';
    public const string CustomerQueryRaised = 'protocol.customerQueryRaised';
    public const string CustomerQueryAnswered = 'protocol.customerQueryAnswered';
    public const string PdfRendered = 'protocol.pdfRendered';
    public const string PdfDownloaded = 'protocol.pdfDownloaded';
    // Abruf im Kundenportal (Phase 137, E17): eigener Eintrag als Download-Nachweis.
    public const string PortalPdfDownloaded = 'protocol.portalPdfDownloaded';
    public const string ItemPhotoAdded = 'protocol.item.photoAdded';
    public const string ItemPhotoRemoved = 'protocol.item.photoRemoved';
    public const string ItemPhotoReordered = 'protocol.item.photoReordered';
    public const string ItemPhotoUpdatedCaption = 'protocol.item.photoUpdatedCaption';
}
