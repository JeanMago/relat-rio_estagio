<?php

namespace Database\Factories;

use App\Models\MasterUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MasterUser>
 */
class MasterUserFactory extends Factory
{
    protected $model = MasterUser::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('5599#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('123456'),
            'perfil' => 'admin',
            'status' => true,
            'remember_token' => Str::random(10),
            'ultimo_login_at' => null,
            'avatar_url' => null,
            'observacoes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
