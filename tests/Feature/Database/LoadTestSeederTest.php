<?php

namespace Tests\Feature\Database;

use App\Enums\ExamStatus;
use App\Enums\Role;
use App\Models\Exam;
use App\Models\User;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/** Task 5.4: data uji beban siap dipakai skrip tools/uji-beban/uji-beban.mjs. */
class LoadTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_peserta_dan_ujian_yang_sedang_dibuka(): void
    {
        putenv('LOAD_TEST_USERS=3');
        try {
            (new LoadTestSeeder)->run();
        } finally {
            putenv('LOAD_TEST_USERS');
        }

        $this->assertSame(['B0001', 'B0002', 'B0003'], User::where('role', Role::Mahasiswa)->orderBy('nim_nidn')->pluck('nim_nidn')->all());
        $this->assertTrue(Hash::check('password', User::firstWhere('nim_nidn', 'B0002')->password));

        $exam = Exam::sole();
        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertTrue($exam->dalamJadwal());
        $this->assertSame([], $exam->masalahPublikasi());
        $this->assertSame(22, $exam->questions()->count());
    }

    public function test_menolak_berjalan_di_produksi(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        (new LoadTestSeeder)->run();
    }
}
