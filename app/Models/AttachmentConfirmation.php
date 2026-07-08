<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentConfirmation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kundenbestätigung eines kundensichtbaren Anhangs (Feature 012, Rang 55):
 * genau eine Bestätigung je Anhang und Portal-Benutzer (DB-Unique).
 */
class AttachmentConfirmation extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'attachment_id',
        'user_id',
        'confirmed_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo {
        return $this->belongsTo(Attachment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
