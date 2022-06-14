<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
        DB::table('users')->insert([
            'id' => '1',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'password_digest' => '$2y$12$gFFaj0zpMMlTjZbLJyzHqedaL4CfZB2EBRVLGiDZ0nUaKDh30tfsq',
            'role_id'=> '1',
        ]);
    }
}
