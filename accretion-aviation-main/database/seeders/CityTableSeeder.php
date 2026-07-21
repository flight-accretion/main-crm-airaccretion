<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CityTableSeeder extends Seeder
{

  public function run()
  {
   
    DB::table('cities')->delete();
    $jsonPath = database_path('seeders/data/city.json');
    $jsonData = File::get($jsonPath);
    $cities = json_decode($jsonData, true);

    if (!$cities) {
      echo "Invalid JSON file.";
      return;
    }

    $cityData = [];
    foreach ($cities as $city) {
      $cityData[] = [
        'id' => $city['id'],
        'name' => $city['name'],
        'lat' => $city['lat'],
        'lng' => $city['lng'],
        'country_id' => $city['country_id'],
        'state_id' => $city['state_id'],
        'status' => $city['status'],
        'timezone' => $city['timezone'],
        'utc' => $city['utc'],
        'status' => $city['status'],
        'created_at' => now(),
        'updated_at' => now(),
      ];
    }
    $this->batchInsert('cities', $cityData);
  }

  /**
   * Insert data in batches.
   */
  private function batchInsert(string $table, array $data, int $batchSize = 5000): void
  {
    $chunks = array_chunk($data, $batchSize);
    foreach ($chunks as $chunk) {
      DB::table($table)->insert($chunk);
    }
  }
}
