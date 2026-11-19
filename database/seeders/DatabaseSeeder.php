<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::factory()->create(['name' => 'Toko Sembako Demo']);

        User::factory()->create([
            'store_id' => $store->id,
            'name' => 'Pemilik Demo',
            'email' => 'admin@stokcerdas.test',
        ]);

        User::factory()->karyawan()->create([
            'store_id' => $store->id,
            'name' => 'Karyawan Demo',
            'email' => 'karyawan@stokcerdas.test',
        ]);

        $this->call(DemoCatalogSeeder::class);
    }
}
