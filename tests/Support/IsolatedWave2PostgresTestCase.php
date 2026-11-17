<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class IsolatedWave2PostgresTestCase extends TestCase
{
    public function createApplication()
    {
        if (!getenv('CRM_WAVE1_QA_ROOT')) $this->markTestSkipped('Requires the isolated PostgreSQL QA environment.');
        $app = require __DIR__.'/wave1_qa_bootstrap.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $identity = DB::selectOne('SELECT current_database() AS db, current_user AS role');
        $this->assertSame('accretion_wave1_qa', $identity->db);
        $this->assertSame('accretion_wave1_qa_role', $identity->role);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) DB::rollBack();
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }
}
