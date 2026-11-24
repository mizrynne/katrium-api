<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1: lock the Supabase Data API (PostgREST) out of every table.
 *
 * Laravel talks to Postgres directly as the table owner, which bypasses RLS (we do NOT use FORCE),
 * so the app is unaffected. The anon / authenticated roles behind the public REST API get
 * RLS with zero policies (deny all) AND no table grants (defence in depth).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $table) {
            DB::statement(sprintf('ALTER TABLE public."%s" ENABLE ROW LEVEL SECURITY', str_replace('"', '""', $table->tablename)));
        }

        // anon / authenticated exist on Supabase only; skip silently on plain Postgres (local, CI).
        foreach (['anon', 'authenticated'] as $role) {
            if (! DB::selectOne('select 1 as present from pg_roles where rolname = ?', [$role])) {
                continue;
            }

            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$role}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$role}");
            // Tables created by later migrations must not be re-exposed.
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM {$role}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM {$role}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $table) {
            DB::statement(sprintf('ALTER TABLE public."%s" DISABLE ROW LEVEL SECURITY', str_replace('"', '""', $table->tablename)));
        }
        // Grants are intentionally NOT restored: re-exposing tables must be a deliberate act.
    }
};
