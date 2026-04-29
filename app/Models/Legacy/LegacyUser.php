<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyUser extends Model {
    protected $connection = 'legacy';

    protected $table = 'user';

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = 'id';

    public function entries(): HasMany {
        return $this->hasMany(LegacyDiaryEntry::class, 'user', 'id');
    }
}
