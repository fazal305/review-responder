<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnableRowLevelSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enables_rls_on_every_table_on_postgres_and_is_a_no_op_elsewhere(): void
    {
        $this->artisan('db:enable-rls')->assertSuccessful();

        if (DB::getDriverName() !== 'pgsql') {
            $this->artisan('db:enable-rls')->expectsOutput('Not a PostgreSQL connection; nothing to do.');

            return;
        }

        $withoutRls = DB::select(
            "SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema() AND c.relkind = 'r' AND NOT c.relrowsecurity"
        );
        $this->assertSame([], $withoutRls);

        // The owning app role is unaffected by RLS, so the app keeps working.
        $this->assertSame(0, DB::table('users')->count());
    }
}
