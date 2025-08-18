<?php

namespace Roberts\Web3Laravel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Exceptions\KeyReleaseException;
use Roberts\Web3Laravel\Models\KeyRelease;
use Roberts\Web3Laravel\Models\Wallet;

class KeyReleaseService
{
    /**
     * Securely release a wallet's private key to its owner.
     * 
     * @throws KeyReleaseException
     */
    public function releasePrivateKey(
        Wallet $wallet, 
        Model $user, 
        ?Request $request = null
    ): array {
        // Security validation
        $this->validateKeyReleaseRequest($wallet, $user);

        // Extract security context
        $securityContext = $this->extractSecurityContext($request);

        try {
            // Get the decrypted private key
            $privateKey = $wallet->decryptKey();

            if (!$privateKey) {
                throw new KeyReleaseException('Wallet does not have a private key stored');
            }

            // Create audit record FIRST (before returning key)
            $keyRelease = KeyRelease::createRelease(
                $wallet,
                $user,
                $securityContext['ip_address'],
                $securityContext['user_agent'],
                $securityContext['additional']
            );

            // Change wallet type from custodial to shared
            if ($wallet->wallet_type === WalletType::CUSTODIAL) {
                $wallet->wallet_type = WalletType::SHARED;
                $wallet->save();

                Log::info('Wallet type changed due to key release', [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->getKey(),
                    'old_type' => 'custodial',
                    'new_type' => 'shared',
                    'key_release_id' => $keyRelease->id,
                ]);
            }

            // Log the key release for security monitoring
            Log::warning('Private key released to wallet owner', [
                'wallet_id' => $wallet->id,
                'wallet_address' => $wallet->address,
                'user_id' => $user->getKey(),
                'key_release_id' => $keyRelease->id,
                'ip_address' => $securityContext['ip_address'],
                'user_agent' => $securityContext['user_agent'],
                'previous_releases' => KeyRelease::forWallet($wallet)->forUser($user)->count(),
            ]);

            return [
                'private_key' => $privateKey,
                'wallet_address' => $wallet->address,
                'wallet_type_changed' => $wallet->wallet_type === WalletType::SHARED,
                'release_id' => $keyRelease->id,
                'released_at' => $keyRelease->released_at,
                'security_notice' => 'This private key has been released to you. Keep it secure and never share it.',
            ];

        } catch (\Exception $e) {
            // Log the failure for security monitoring
            Log::error('Private key release failed', [
                'wallet_id' => $wallet->id,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'ip_address' => $securityContext['ip_address'],
            ]);

            throw new KeyReleaseException('Failed to release private key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that the key release request is authorized and secure.
     * 
     * @throws KeyReleaseException
     */
    private function validateKeyReleaseRequest(Wallet $wallet, Model $user): void
    {
        // 1. Verify wallet ownership
        if ($wallet->owner_id !== $user->getKey()) {
            Log::critical('Unauthorized key release attempt', [
                'wallet_id' => $wallet->id,
                'wallet_owner_id' => $wallet->owner_id,
                'requesting_user_id' => $user->getKey(),
                'ip_address' => request()->ip(),
            ]);

            throw new KeyReleaseException('Unauthorized: You do not own this wallet');
        }

        // 2. Verify wallet can store private keys
        if (!$wallet->canStorePrivateKey()) {
            throw new KeyReleaseException('This wallet type cannot have private keys released');
        }

        // 3. Verify wallet has a private key
        if (!$wallet->key) {
            throw new KeyReleaseException('This wallet does not have a private key stored');
        }

        // 4. Verify wallet is active
        if (!$wallet->is_active) {
            throw new KeyReleaseException('Cannot release key for inactive wallet');
        }

        // 5. Rate limiting - prevent too many releases in short time
        $recentReleases = KeyRelease::forWallet($wallet)
            ->forUser($user)
            ->recent(5) // Last 5 minutes
            ->count();

        if ($recentReleases >= 3) {
            Log::warning('Rate limit exceeded for key release', [
                'wallet_id' => $wallet->id,
                'user_id' => $user->getKey(),
                'recent_releases' => $recentReleases,
            ]);

            throw new KeyReleaseException('Too many key release attempts. Please wait before trying again.');
        }

        // 6. Verify user is authenticated
        if (!Auth::check() || Auth::id() !== $user->getKey()) {
            throw new KeyReleaseException('User must be authenticated to release private key');
        }
    }

    /**
     * Extract security context from the request.
     */
    private function extractSecurityContext(?Request $request): array
    {
        $ipAddress = $request?->ip() ?? 'unknown';
        $userAgent = $request?->userAgent() ?? 'unknown';

        return [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'additional' => [
                'session_id' => session()->getId(),
                'csrf_token' => $request?->header('X-CSRF-TOKEN') ? 'present' : 'missing',
                'referer' => $request?->header('referer'),
                'authenticated_user_id' => Auth::id(),
            ],
        ];
    }

    /**
     * Get key release history for a wallet.
     */
    public function getReleaseHistory(Wallet $wallet, Model $user): array
    {
        // Verify ownership
        if ($wallet->owner_id !== $user->getKey()) {
            throw new KeyReleaseException('Unauthorized: You do not own this wallet');
        }

        $releases = KeyRelease::forWallet($wallet)
            ->forUser($user)
            ->orderBy('released_at', 'desc')
            ->get()
            ->map(function ($release) {
                return [
                    'id' => $release->id,
                    'released_at' => $release->released_at,
                    'ip_address' => $release->ip_address,
                    'user_agent' => $release->user_agent,
                ];
            });

        return [
            'wallet_id' => $wallet->id,
            'wallet_address' => $wallet->address,
            'total_releases' => $releases->count(),
            'releases' => $releases->toArray(),
        ];
    }

    /**
     * Check if a user can release a wallet's private key.
     */
    public function canReleaseKey(Wallet $wallet, Model $user): array
    {
        $canRelease = true;
        $reasons = [];

        try {
            $this->validateKeyReleaseRequest($wallet, $user);
        } catch (KeyReleaseException $e) {
            $canRelease = false;
            $reasons[] = $e->getMessage();
        }

        return [
            'can_release' => $canRelease,
            'reasons' => $reasons,
            'wallet_type' => $wallet->wallet_type->value,
            'has_private_key' => !is_null($wallet->key),
        ];
    }
}
