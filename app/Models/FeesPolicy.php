<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeesPolicy extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'is_active' => 'boolean'];
    }

    public function rules(): HasMany { return $this->hasMany(FeesRule::class); }
}
