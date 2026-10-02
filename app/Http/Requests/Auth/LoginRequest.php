<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Percobaan gagal berturut-turut sebelum penundaan (FR-01.4). */
    public const MAKS_PERCOBAAN = 5;

    /** Lama penundaan dalam detik. */
    public const DETIK_PENUNDAAN = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nim_nidn' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nim_nidn'))) {
            $this->merge(['nim_nidn' => trim($this->input('nim_nidn'))]);
        }
    }

    /**
     * Autentikasi dengan rate limiting per kombinasi identitas + IP.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $nonaktif = false;
        $berhasil = Auth::attemptWhen(
            $this->only('nim_nidn', 'password'),
            function (User $user) use (&$nonaktif): bool {
                $nonaktif = ! $user->aktif;

                return ! $nonaktif;
            },
        );

        if ($nonaktif) {
            throw ValidationException::withMessages(['nim_nidn' => __('auth.inactive')]);
        }

        if (! $berhasil) {
            RateLimiter::hit($this->throttleKey(), self::DETIK_PENUNDAAN);

            throw ValidationException::withMessages(['nim_nidn' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAKS_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'nim_nidn' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    /**
     * Kunci per identitas + IP: penyerang dari IP lain tidak dapat mengunci
     * akun mahasiswa lain menjelang ujian.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('nim_nidn')).'|'.$this->ip());
    }
}
