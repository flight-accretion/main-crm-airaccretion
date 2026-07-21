<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Country;
use Illuminate\Support\Facades\File;

class CountryTableSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {
    // $data = array(
    //   ['id' => 1, 'name' => 'India','alpha_code'=>'IN','iso_letter'=>'IND','symbol'=>'₹','currency_code'=>'RS','status'=>1]);

    DB::table('countries')->delete();
    $jsonPath = database_path('seeders/data/countries.json');
    $jsonData = File::get($jsonPath);
    $countries = json_decode($jsonData, true);

    if (!$countries) {
      echo "Invalid JSON file.";
      return;
    }

    $countryData = [];
    foreach ($countries as $country) {
      $countryData[] = [
        'id' => $country['id'],
        'name' => $country['name'],
        'alpha_code' => $country['alpha_code'],
        'symbol' => $country['symbol'],
        'currency_code' => $country['currency_code'],
        'isd_code' => $country['isd_code'],
        'status' => $country['status'],
        'created_at' => now(),
        'updated_at' => now(),
      ];
    }
    $this->batchInsert('countries', $countryData);
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
