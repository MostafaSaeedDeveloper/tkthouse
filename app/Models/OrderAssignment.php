<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history of who an order was assigned to. */
class OrderAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_user_id', 'to_user_id', 'changed_by_user_id', 'source', 'reason', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
