<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores the Envato purchase license for this installation.
 *
 * Only one row should exist at a time. Use License::current() to retrieve it.
 */
class License extends Model
{
    protected $table = 'license';

    protected $fillable = [
        'purchase_code',
        'envato_username',
        'buyer_email',
        'purchase_date',
        'license_type',
        'is_verified',
        'verified_at',
        'app_url',
    ];

    protected $casts = [
        'is_verified'   => 'boolean',
        'verified_at'   => 'datetime',
        'purchase_date' => 'datetime',
    ];

    /**
     * Retrieve the currently stored license, or null if not yet activated.
     */
    public static function current(): ?self
    {
        return static::orderBy('id', 'desc')->first();
    }

    /**
     * Determine whether this installation has a verified license.
     */
    public static function isActivated(): bool
    {
        $license = static::current();

        return $license !== null && $license->is_verified;
    }
}
