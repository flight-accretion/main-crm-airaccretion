<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StateTableSeeder extends Seeder
{
  public function run()
  {

    DB::table('states')->delete();
    $jsonPath = database_path('seeders/data/states.json');
    $jsonData = File::get($jsonPath);
    $states = json_decode($jsonData, true);

    if (!$states) {
      echo "Invalid JSON file.";
      return;
    }

    $stateData = [];
    foreach ($states as $state) {
      $stateData[] = [
        'id' => $state['id'],
        'country_id' => $state['country_id'],
        'name' => $state['name'],
        'status' => $state['status'],
        'created_at' => now(),
        'updated_at' => now(),
      ];
    }
    $this->batchInsert('states', $stateData);
  }

  /**
   * Insert data in batches.
   */
  private function batchInsert(string $table, array $data, int $batchSize = 1000): void
  {
    $chunks = array_chunk($data, $batchSize);
    foreach ($chunks as $chunk) {
      DB::table($table)->insert($chunk);
    }
  }
}
