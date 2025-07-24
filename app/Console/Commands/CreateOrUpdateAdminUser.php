<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOrUpdateAdminUser extends Command
{
    protected $signature = 'admin:create 
                            {--email= : Override email from .env}
                            {--password= : Override password from .env}
                            {--name= : Override name from .env}
                            {--update : Update existing admin user}';
    
    protected $description = 'Create or update admin user from .env configuration';

    public function handle()
    {
        // Get credentials from options or .env
        $email = $this->option('email') ?? env('ADMIN_EMAIL');
        $password = $this->option('password') ?? env('ADMIN_PASSWORD');
        $name = $this->option('name') ?? env('ADMIN_NAME', 'Administrator');

        // Validate that we have required env variables
        if (!$email || !$password) {
            $this->error('Admin credentials not found in .env file!');
            $this->info('Please add the following to your .env file:');
            $this->info('ADMIN_EMAIL=admin@example.com');
            $this->info('ADMIN_PASSWORD=your-secure-password');
            $this->info('ADMIN_NAME=Administrator');
            return 1;
        }

        // Validate email and password
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Check if user exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($this->option('update') || $this->confirm('Admin user already exists. Do you want to update it?')) {
                $existingUser->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                    'is_admin' => true,
                ]);
                
                $this->info('Admin user updated successfully!');
                $this->table(['Field', 'Value'], [
                    ['Name', $name],
                    ['Email', $email],
                    ['Status', 'Updated'],
                ]);
            } else {
                $this->info('No changes made.');
            }
        } else {
            // Create new admin user
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            $this->info('Admin user created successfully!');
            $this->table(['Field', 'Value'], [
                ['Name', $name],
                ['Email', $email],
                ['Status', 'Created'],
            ]);
        }

        $this->warn('Remember to change the default password after first login!');
        
        return 0;
    }
}