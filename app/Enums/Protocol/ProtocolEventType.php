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
    public const Created = 'protocol.created';
    public const ItemAdded = 'protocol.itemAdded';
    public const ItemRemoved = 'protocol.itemRemoved';
    public const ItemReordered = 'protocol.itemReordered';
    public const ItemFilled = 'protocol.itemFilled';
    public const RequestedReview = 'protocol.requestedReview';
    public const ReturnedToDraft = 'protocol.returnedToDraft';
    public const Signed = 'protocol.signed';
    public const Archived = 'protocol.archived';
    public const SupersededBy = 'protocol.supersededBy';
    public const AttachmentAdded = 'protocol.attachmentAdded';
    public const AttachmentRemoved = 'protocol.attachmentRemoved';
    public const SignatureRequested = 'protocol.signatureRequested';
    public const SignatureLinkOpened = 'protocol.signatureLinkOpened';
    public const PdfRendered = 'protocol.pdfRendered';
    public const PdfDownloaded = 'protocol.pdfDownloaded';
    public const ItemPhotoAdded = 'protocol.item.photoAdded';
    public const ItemPhotoRemoved = 'protocol.item.photoRemoved';
    public const ItemPhotoReordered = 'protocol.item.photoReordered';
    public const ItemPhotoUpdatedCaption = 'protocol.item.photoUpdatedCaption';
}
