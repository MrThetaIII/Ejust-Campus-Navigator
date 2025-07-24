<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin credentials from .env
        $adminEmail = env('ADMIN_EMAIL', 'admin@admin.com');
        $adminName = env('ADMIN_NAME', 'admin');
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        // Check if admin already exists
        $existingAdmin = User::where('email', $adminEmail)->first();

        if ($existingAdmin) {
            $this->command->info('Admin user already exists with email: ' . $adminEmail);
            
            // Optionally update the existing admin
            $existingAdmin->update([
                'name' => $adminName,
                'is_admin' => true,
            ]);
            
            $this->command->info('Admin user updated.');
        } else {
            // Create new admin user
            User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: ' . $adminEmail);
            $this->command->warn('Please change the default password after first login!');
        }
    }
}