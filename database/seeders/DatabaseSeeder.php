<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * @psalm-suppress UnusedClass
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SpaceSeeder::class,
            ComponentSeeder::class,
            InitialDataSeeder::class,
        ]);

        $this->command->info('Database seeding completed successfully!');
    }
}
