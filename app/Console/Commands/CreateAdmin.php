<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Buat akun admin pertama di produksi tanpa data contoh dan tanpa kata sandi bawaan.
 */
#[Signature('examguard:buat-admin {username : Username login admin} {nama : Nama tampilan}')]
#[Description('Buat akun admin baru; kata sandi diminta secara tersembunyi.')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $sandi = (string) $this->secret('Kata sandi (minimal 10 karakter)');
        $ulang = (string) $this->secret('Ulangi kata sandi');

        $validator = Validator::make(
            ['nim_nidn' => $this->argument('username'), 'nama' => $this->argument('nama'), 'password' => $sandi, 'password_confirmation' => $ulang],
            [
                'nim_nidn' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,nim_nidn'],
                'nama' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:10', 'confirmed'],
            ],
            [],
            ['nim_nidn' => 'username', 'password' => 'kata sandi'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $admin = User::create([
            'nim_nidn' => $this->argument('username'),
            'nama' => $this->argument('nama'),
            'role' => Role::Admin,
            'password' => $sandi,
        ]);
        AuditLog::catat('akun_dibuat', $admin, ['peran' => 'admin', 'lewat' => 'konsol']);

        $this->info("Admin {$admin->nim_nidn} dibuat.");

        return self::SUCCESS;
    }
}
