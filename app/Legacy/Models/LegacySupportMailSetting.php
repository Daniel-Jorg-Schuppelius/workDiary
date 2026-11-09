<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportMailSetting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Mail-Hinweis des Support-Portals (`email`, eine Zeile): Empfänger und
 * Schalter für neue Kundenaufträge.
 *
 * Das Altportal schaltet per XOR 2 zwischen 1 (an) und 3 (aus); beide Werte
 * bleiben so, damit es bei Parallelbetrieb weiter umschalten kann.
 *
 * @property int $id
 * @property string $email
 * @property int $schalt
 *
 * @method static Builder<static>|LegacySupportMailSetting newModelQuery()
 * @method static Builder<static>|LegacySupportMailSetting newQuery()
 * @method static Builder<static>|LegacySupportMailSetting query()
 */
class LegacySupportMailSetting extends Model {
    public const int ROW_ID = 1;

    public const int SWITCH_ON = 1;

    public const int SWITCH_OFF = 3;

    /** Spaltenbreite im Altsystem. */
    public const int EMAIL_MAX_LENGTH = 25;

    protected $connection = 'legacy';

    protected $table = 'email';

    public $timestamps = false;

    protected $fillable = ['email', 'schalt'];

    protected $primaryKey = 'id';

    protected function casts(): array {
        return ['schalt' => 'integer'];
    }

    public static function current(): ?self {
        return self::query()->find(self::ROW_ID);
    }

    public function isEnabled(): bool {
        return (int) $this->schalt === self::SWITCH_ON && filled($this->email);
    }
}
