<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model {
    protected $fillable = [
        'date',
        'name',
        'is_recurring',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
