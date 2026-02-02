<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'colors')
            ->where('name', 'primary')
            ->update(['payload' => json_encode('#326BFF')]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'colors')
            ->where('name', 'primary')
            ->update(['payload' => json_encode('#2563EB')]);
    }
};
