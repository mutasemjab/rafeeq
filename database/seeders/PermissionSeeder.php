<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions_admin = [
            // Roles Management
            'roles-index',
            'roles-create',
            'roles-edit',
            'roles-delete',

            // Employees Management
            'employees-index',
            'employees-create',
            'employees-edit',
            'employees-delete',

            // Users Management
            'users-index',
            'users-create',
            'users-edit',
            'users-delete',

        ];

        $permissions = [];

        foreach ($permissions_admin as $permission) {
            $permissions[] = [
                'name'       => $permission,
                'guard_name' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Permission::insert($permissions);

    }
}