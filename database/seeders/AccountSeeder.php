<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sign-in accounts for every role, plus a set of customers with enough
 * variety that the booking history looks like a real property's.
 *
 * The shared development password is set here in one place and printed by
 * DatabaseSeeder, so nobody has to go looking for it.
 */
class AccountSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->personnel();
        $this->customers();
    }

    private function personnel(): void
    {
        $people = [
            ['Amelia Rosales', 'admin@belmante.test', UserRole::Admin, true],
            ['Diego Villanueva', 'manager@belmante.test', UserRole::Admin, true],
            ['Marisol Cortez', 'frontdesk@belmante.test', UserRole::Staff, true],
            ['Nico Bautista', 'nico@belmante.test', UserRole::Staff, true],
            ['Perla Santos', 'perla@belmante.test', UserRole::Staff, true],
            // Deactivated rather than deleted: their attribution on past
            // payments has to keep resolving.
            ['Rafael Ocampo', 'rafael@belmante.test', UserRole::Staff, false],
        ];

        foreach ($people as [$name, $email, $role, $active]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => self::PASSWORD,
                    'role' => $role,
                    'is_active' => $active,
                    'email_verified_at' => now(),
                    'phone' => fake()->numerify('+63 9## ### ####'),
                    'last_login_at' => $active ? now()->subHours(rand(1, 72)) : null,
                ],
            );
        }
    }

    private function customers(): void
    {
        // A named customer with a predictable login, so the customer-facing
        // flows can be walked through without hunting for an address.
        User::updateOrCreate(
            ['email' => 'customer@belmante.test'],
            [
                'name' => 'Teresa Lim',
                'password' => self::PASSWORD,
                'role' => UserRole::Customer,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone' => '+63 917 555 0142',
                'address_line' => '44 Kalayaan Avenue',
                'city' => 'Quezon City',
                'country' => 'Philippines',
            ],
        );

        if (User::customers()->count() < 25) {
            User::factory()
                ->count(25 - User::customers()->count())
                ->create(['password' => self::PASSWORD]);
        }
    }
}
