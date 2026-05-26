<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->transformTokens(fn (?string $value): ?string => $this->encryptIfPlaintext($value));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->transformTokens(fn (?string $value): ?string => $this->decryptIfEncrypted($value));
    }

    /**
     * @param  callable(?string): ?string  $transform
     */
    private function transformTokens(callable $transform): void
    {
        DB::table('social_accounts')
            ->select(['id', 'access_token', 'refresh_token'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use ($transform): void {
                foreach ($accounts as $account) {
                    DB::table('social_accounts')
                        ->where('id', $account->id)
                        ->update([
                            'access_token' => $transform($account->access_token),
                            'refresh_token' => $transform($account->refresh_token),
                        ]);
                }
            });
    }

    private function encryptIfPlaintext(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            Crypt::decryptString($value);

            return $value;
        } catch (DecryptException) {
            return Crypt::encryptString($value);
        }
    }

    private function decryptIfEncrypted(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
};
