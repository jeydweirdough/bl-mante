<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogueSeeder::class,
            AccountSeeder::class,
            ReservationSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Seeded '.Room::count().' rooms and '.Reservation::count().' reservations.');
        $this->command->newLine();

        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@belmante.test', AccountSeeder::PASSWORD],
                ['Front desk', 'frontdesk@belmante.test', AccountSeeder::PASSWORD],
                ['Customer', 'customer@belmante.test', AccountSeeder::PASSWORD],
            ],
        );

        $this->command->line('  Customers: '.User::customers()->count().'  Personnel: '.User::personnel()->count());
        $this->command->newLine();
    }
}
