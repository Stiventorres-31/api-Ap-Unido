<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Materiale>
 */
class MaterialeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "referencia_material"=>$this->faker->randomNumber(5),
            "nombre_material"=>$this->faker->name(),
            "user_id"=>1,
            "estado"=>"A",

        ];
    }
}
