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
            'view:calendar', 'manage:calendar',
            'manage:units',
            'manage:guests',
            'manage:bookings',
            'view:financials', 'manage:financials',
            'manage:users'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $superadminRole = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $adminRole      = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $managerRole    = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $partnerRole    = Role::firstOrCreate(['name' => 'Partner', 'guard_name' => 'web']);
        $guestRole      = Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'web']);

        $adminRole->syncPermissions(Permission::all());
        
        $managerRole->syncPermissions([
            'view:calendar', 'manage:calendar',
            'manage:guests',
            'manage:bookings'
        ]);

        $partnerRole->syncPermissions([
            'view:financials'
        ]);

        $emails = explode(',', env('BOOTSTRAP_EMAILS'));

        foreach ($emails as $email) {
            $cleanEmail = trim($email);

            if (empty($cleanEmail)) {
                continue;
            }

            User::firstOrCreate(
                ['email' => $cleanEmail],
                ['name' => 'KatriuM Dev']
            )->assignRole($superadminRole);
        }
    }
}