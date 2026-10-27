<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OwnerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $role = DB::table('user_types')->where('user_type', 'Owner')->first();

        if (!$role) {
            throw new \RuntimeException('Owner role not found. Run the role seeder before the Owner account seeder.');
        }

        $configuredEmail = trim((string) getenv('OWNER_EMAIL'));
        $owner = DB::table('users')
            ->where('user_type_id', $role->id)
            ->orderBy('created_at')
            ->first();
        $email = $configuredEmail !== ''
            ? $configuredEmail
            : ($owner->email ?? 'owner@accretion.in');
        $password = getenv('OWNER_PASSWORD');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('OWNER_EMAIL must contain a valid email address.');
        }

        $emailOwner = DB::table('users')->where('email', $email)->first();
        if ($emailOwner && (!$owner || $emailOwner->id !== $owner->id)) {
            throw new \RuntimeException('OWNER_EMAIL is already assigned to a different user.');
        }

        if (!$owner && (!is_string($password) || $password === '')) {
            throw new \RuntimeException(
                'Set OWNER_PASSWORD in the process environment to create the Owner account.'
            );
        }

        $attributes = [
            'email' => $email,
            'status' => 1,
            'updated_at' => $now,
        ];
        if (is_string($password) && $password !== '') {
            $attributes['password'] = Hash::make($password);
        }

        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update($attributes);
            return;
        }

        DB::table('users')->insert(array_merge($attributes, [
            'id' => (string) Str::uuid(),
            'name' => 'Owner User',
            'address' => 'Owner Office',
            'contact_number' => '9999999999',
            'user_type_id' => $role->id,
            'joining_date' => $now,
            'last_login' => $now,
            'created_at' => $now,
        ]));
    }
}
