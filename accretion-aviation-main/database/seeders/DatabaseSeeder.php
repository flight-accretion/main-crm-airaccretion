<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
    //    $this->call(UserTypeSeeder::class);
      // $this->call(CountrySeeder::class);
        // $this->call(CountryTableSeeder::class);
        // $this->call(StateTableSeeder::class);
        // $this->call(CityTableSeeder::class);
        // $this->call(FollowUpStatusTableSeeder::class);
        $this->call(UserTypeSeeder::class);

    }
}
