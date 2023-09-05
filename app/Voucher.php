<?php

namespace App;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Voucher
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Eloquent
 */
class Voucher extends Model
{
    protected $with = ['user'];

    protected $fillable = [
        'code',
        'amount',
        'is_used',
        'used_at',
        'used_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
