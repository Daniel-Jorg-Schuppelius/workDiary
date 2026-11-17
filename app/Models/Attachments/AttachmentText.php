<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Attachments;

use App\Enums\Attachments\MailFileFailure;
use App\Models\Concerns\BelongsToOrganization;
use App\Services\Attachments\Mail\Dto\MailFileMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gelesene Mail-Datei eines Anhangs (Feature 204, MVP-1652/1653): Suchtext,
 * Zusammenfassung für die Ansicht (nur Arrays und Skalare) und Verzeichnis der
 * einmal extrahierten Teile. Abgeleitetes Material, fällt mit dem Anhang weg;
 * das Verzeichnis räumt {@see \App\Services\Attachments\Mail\MailAttachmentContents}
 * beim Löschen des Anhangs und die Löschung der Organisation ab.
 *
 * Zustand: `extracted_at` leer = wird gelesen, `failure_reason` gesetzt =
 * nicht lesbar, sonst gelesen.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $attachment_id
 * @property string|null $text
 * @property array<string, mixed>|null $summary
 * @property string|null $parts_disk
 * @property string|null $parts_path
 * @property Carbon|null $extracted_at
 * @property MailFileFailure|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AttachmentText extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'attachment_id',
        'text',
        'summary',
        'parts_disk',
        'parts_path',
        'extracted_at',
        'failure_reason',
    ];

    protected $casts = [
        'summary' => 'array',
        'extracted_at' => 'datetime',
        'failure_reason' => MailFileFailure::class,
    ];

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo {
        return $this->belongsTo(Attachment::class);
    }

    public function isPending(): bool {
        return $this->extracted_at === null && $this->failure_reason === null;
    }

    /** Gelesene Zusammenfassung oder null (noch nicht gelesen, nicht lesbar). */
    public function message(): ?MailFileMessage {
        return $this->failure_reason === null && is_array($this->summary) ? MailFileMessage::fromArray($this->summary) : null;
    }
}
