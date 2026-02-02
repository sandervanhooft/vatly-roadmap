<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        // Copy the logo from public/ to storage/app/public/ if it doesn't exist there
        $source = public_path('logo_vatly_blue.svg');
        $target = 'logo-vatly-blue.svg';

        if (File::exists($source) && !Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, File::get($source));
        }

        // Update the settings to use the correct relative path
        DB::table('settings')
            ->where('group', 'colors')
            ->where('name', 'logo')
            ->update(['payload' => json_encode($target)]);
    }

    public function down(): void
    {
        // No rollback needed
    }
};
