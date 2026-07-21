<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FollowUpStatusTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('follow_up_status')->insert([
            [
                'uuid' => Str::uuid(),
                'title' => 'Initial Contact',
                'description' => 'First interaction with the client',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'title' => 'Follow-Up Scheduled',
                'description' => 'Follow-up meeting or call scheduled',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'title' => 'Closed',
                'description' => 'The follow-up is closed or resolved',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

