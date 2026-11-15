<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'browse_catalogue' => 'Browse catalogue',
        'place_orders' => 'Send enquiries and order requests',
        'request_repairs' => 'Request repairs',
        'raise_tickets' => 'Raise support tickets',
        'manage_own_shop' => 'Manage own shop, products and orders',
        'manage_own_repairs' => 'Manage assigned repair jobs',
        'view_orders' => 'View all orders',
        'view_repairs' => 'View all repairs',
        'view_users' => 'View users',
        'manage_tickets' => 'Handle support tickets',
        'manage_users' => 'Suspend / activate users',
        'verify_shops' => 'Verify shops',
        'verify_technicians' => 'Verify technicians',
        'manage_catalogue' => 'Manage categories, brands and models',
        'moderate_listings' => 'Moderate product listings',
        'moderate_reviews' => 'Moderate reviews',
        'manage_refunds' => 'Manage refunds and cancellations',
        'manage_settlements' => 'Manage merchant settlements',
        'manage_commissions' => 'Manage commission rules',
        'manage_templates' => 'Manage notification templates',
        'view_reports' => 'View reports and analytics',
        'manage_settings' => 'Manage system settings',
        'view_audit_logs' => 'View admin activity logs',
    ];

    public const ROLES = [
        Role::CUSTOMER => ['Customer', ['browse_catalogue', 'place_orders', 'request_repairs', 'raise_tickets']],
        Role::MERCHANT => ['Merchant', ['browse_catalogue', 'manage_own_shop', 'raise_tickets']],
        Role::TECHNICIAN => ['Technician', ['browse_catalogue', 'manage_own_repairs', 'raise_tickets']],
        Role::SUPPORT => ['Support operator', ['view_orders', 'view_repairs', 'view_users', 'manage_tickets']],
        Role::ADMIN => ['Super admin', '*'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $label) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }
        foreach (self::ROLES as $name => [$label, $perms]) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label]);
            $ids = $perms === '*' ? Permission::pluck('id') : Permission::whereIn('name', $perms)->pluck('id');
            $role->permissions()->sync($ids);
            Cache::forget("role_permissions:{$name}");
        }
    }
}
