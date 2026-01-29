<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'quote_id',
        'delivery_id',
        'status',
        'reply',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function customQuote()
    {
        return $this->hasOne(CustomQuote::class);
    }

    public function serviceQuote()
    {
        return $this->hasOne(ServiceQuote::class);
    }

    public static function generateQuote_id()
    {
        // Get the latest order ID
        $latestOrder = self::orderBy('quote_id', 'desc')->first();

        if ($latestOrder) {
            // Increment the latest order ID
            $nextNumber = intval($latestOrder->quote_id) + 1;
        } else {
            // First order starts at 1
            $nextNumber = 1;
        }

        // Pad with leading zeros to ensure 6 digits
        $orderid = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        return $orderid;
    }
}
