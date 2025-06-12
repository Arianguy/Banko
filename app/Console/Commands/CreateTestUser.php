<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user for development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = 'test@example.com';
        $password = 'password';

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->info("Test user already exists with email: {$email}");
            return;
        }

        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Test user created successfully!");
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
        $this->info("You can now log in at: http://127.0.0.1:8000/login");
    }
}
