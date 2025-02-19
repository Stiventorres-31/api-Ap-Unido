<?php

namespace Database\Seeders;

use App\Models\Materiale;
use App\Models\TipoInmueble;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        User::factory(1)->create();
        // TipoInmueble::factory(1)->create();
        // Materiale::factory(3)->create();
    }
}
