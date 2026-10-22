<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage:users',
            'manage:property',
            'manage:units', 'view:units',
            'manage:guests',
            'manage:calendar', 'view:calendar',
            'manage:bookings', 'view:bookings',
            'manage:financials', 'view:financials'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $superadminRole = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $managerRole    = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $partnerRole    = Role::firstOrCreate(['name' => 'Partner', 'guard_name' => 'web']);
        
        $superadminRole->syncPermissions(Permission::all());
        
        $managerRole->syncPermissions([
            'view:units',
            'manage:guests',
            'manage:calendar',
            'manage:bookings'
        ]);

        $partnerRole->syncPermissions([
            'view:units',
            'view:calendar',
            'view:bookings',
            'view:financials'
        ]);

        foreach (config('katrium.bootstrap_emails') as $email) {
            User::firstOrCreate(['email' => $email], ['name' => 'KatriuM Dev'])->assignRole($superadminRole);
        }
    }
}