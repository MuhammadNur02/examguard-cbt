<?php

namespace Tests;

use App\Http\Middleware\EnsureSingleSession;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak bergantung pada aset hasil `npm run build`.
        $this->withoutVite();
    }

    /**
     * Masuk seperti lewat form login: token single session ikut disetel di
     * akun dan sesi, sehingga middleware EnsureSingleSession tetap berlaku.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user instanceof User) {
            $token = Str::random(64);
            $user->forceFill(['session_token' => $token])->save();
            $this->withSession([EnsureSingleSession::SESSION_KEY => $token]);
        }

        return parent::actingAs($user, $guard);
    }
}
