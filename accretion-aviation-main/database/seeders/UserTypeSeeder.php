<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserTypeSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // Define hierarchy
        $roleHierarchy = [
            'Super Admin' => [
                'Admin' => [],
                'Senior Sales Manager' => [
                    'Sales Manager' => [
                        'Sales Executive' => []
                    ]
                ],
                'Accounts' => [],
                'Senior Operations Manager' => [
                    'Operations Manager' => [
                        'Operations Executive' => []
                    ]
                ],
                'Hr' => []
            ]
        ];

        // Clean up roles not in the hierarchy
        $allRoles = $this->flattenRoles($roleHierarchy);
        DB::table('user_types')->whereNotIn('user_type', $allRoles)->delete();

        // Insert hierarchy + users recursively
        $this->insertRolesAndUsers($roleHierarchy, null, $now);
    }

    private function insertRolesAndUsers(array $roles, $parentId = null, $now = null)
    {
        foreach ($roles as $role => $children) {
            // Check if role exists
            $existing = DB::table('user_types')->where('user_type', $role)->first();

            if ($existing) {
                $roleId = $existing->id;
                DB::table('user_types')
                    ->where('id', $roleId)
                    ->update([
                        'parent_id'  => $parentId,
                        'status'     => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                $roleId = (string) Str::uuid();
                DB::table('user_types')->insert([
                    'id'          => $roleId,
                    'user_type'   => $role,
                    'description' => null,
                    'status'      => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                    'parent_id'   => $parentId,
                ]);
            }

            // Create default user if not already exists
            $email = strtolower(str_replace(' ', '', $role)) . '@accretion.in';
            $existsUser = DB::table('users')->where('email', $email)->exists();


            if (!$existsUser) {
                $id = (string) Str::uuid();
                DB::table('users')->insert([
                    'id'              => $id,
                    'name'            => $role . ' User',
                    'email'           => $email,
                    'password'        => Hash::make('password123'), // default password
                    'address'         => $role . ' Office',
                    'contact_number'  => '9999999999',
                    'user_type_id'    => $roleId,
                    'status'          => 1,
                    'joining_date'    => $now,
                    'last_login'      => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }

            // Recursive for children
            if (!empty($children)) {
                $this->insertRolesAndUsers($children, $roleId, $now);
            }
        }
    }

    private function flattenRoles(array $roles)
    {
        $flat = [];

        foreach ($roles as $role => $children) {
            $flat[] = $role;
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenRoles($children));
            }
        }

        return $flat;
    }
}
