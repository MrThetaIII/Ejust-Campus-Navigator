<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupApplication extends Command
{
    protected $signature = 'app:setup {--fresh : Wipe the database}';
    protected $description = 'Setup the application with migrations and default admin user';

    public function handle()
    {
        $this->info('Setting up the application...');

        // Run migrations
        if ($this->option('fresh')) {
            $this->call('migrate:fresh');
        } else {
            $this->call('migrate');
        }

        // Create admin user
        $this->call('admin:create');

        $this->info('Application setup completed!');
        
        // Display admin credentials from .env
        $this->info('');
        $this->info('Admin credentials (from .env):');
        $this->info('Email: ' . env('ADMIN_EMAIL'));
        $this->info('Password: [hidden - check your .env file]');
        
        return 0;
    }
}