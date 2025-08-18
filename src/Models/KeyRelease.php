<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $released_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $security_context
 * @property-read Wallet $wallet
 * @property-read \Illuminate\Foundation\Auth\User $user
 */
class KeyRelease extends Model
{
    use HasFactory;

    protected $table = 'key_releases';

    protected $guarded = ['id'];

    protected $casts = [
        'wallet_id' => 'integer',
        'user_id' => 'integer',
        'released_at' => 'datetime',
        'security_context' => 'array',
    ];

    /**
     * Get the wallet associated with this key release.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the user who requested the key release.
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Create a new key release record with security context.
     */
    public static function createRelease(
        Wallet $wallet,
        Model $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $additionalContext = []
    ): self {
        return self::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->getKey(),
            'released_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'security_context' => array_merge([
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
            ], $additionalContext),
        ]);
    }

    /**
     * Get releases for a specific wallet.
     */
    public function scopeForWallet($query, Wallet $wallet)
    {
        return $query->where('wallet_id', $wallet->id);
    }

    /**
     * Get releases for a specific user.
     */
    public function scopeForUser($query, Model $user)
    {
        return $query->where('user_id', $user->getKey());
    }

    /**
     * Get recent releases within a time period.
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('released_at', '>=', now()->subMinutes($minutes));
    }
}
