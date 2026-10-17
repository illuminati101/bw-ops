<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public const PERMISSIONS = [
        'bookings.view' => 'View bookings',
        'bookings.create' => 'Create bookings for clients',
        'bookings.update' => 'Edit bookings',
        'bookings.approve' => 'Approve / decline bookings and send quotes',
        'bookings.assign' => 'Assign operators and dispatch',
        'bookings.cancel' => 'Cancel bookings and waive fees',
        'control_room.view' => 'See the live control room',
        'control_room.respond' => 'Acknowledge and resolve SOS alerts',
        'clients.view' => 'View clients',
        'clients.update' => 'Edit clients, strikes and blocks',
        'clients.verify' => 'Review client ID verification',
        'companies.view' => 'View company accounts',
        'companies.update' => 'Edit company accounts and approve EFT',
        'operators.view' => 'View operators',
        'operators.update' => 'Add, edit and remove operators',
        'vehicles.view' => 'View vehicles',
        'vehicles.update' => 'Add, edit and remove vehicles',
        'documents.view' => 'View compliance documents',
        'documents.update' => 'Upload and approve compliance documents',
        'catalog.view' => 'View services, rates, extras and features',
        'catalog.update' => 'Edit services, rates, extras and features (drafts)',
        'catalog.publish' => 'Publish service and rate changes',
        'content.view' => 'View website pages and FAQs',
        'content.update' => 'Edit website pages and FAQs (drafts)',
        'content.publish' => 'Publish website content',
        'finance.view' => 'View payments, invoices and payouts',
        'finance.update' => 'Mark invoices and payouts paid',
        'reports.view' => 'View reports and exports',
        'enquiries.view' => 'View enquiries and job applications',
        'enquiries.update' => 'Handle enquiries and job applications',
        'messages.view' => 'View the outbound message log (incl. login codes in test mode)',
        'settings.update' => 'Change business settings and feature switches',
        'staff.manage' => 'Manage staff accounts and roles',
        'audit.view' => 'View the audit log',
    ];

    public const ROLES = [
        'Owner' => '*',
        'Ops Manager' => ['bookings.*', 'control_room.*', 'clients.*', 'companies.*', 'operators.*', 'vehicles.*', 'documents.*', 'catalog.*', 'content.*', 'finance.view', 'reports.view', 'enquiries.*', 'messages.view', 'settings.update', 'audit.view'],
        'Dispatcher' => ['bookings.view', 'bookings.create', 'bookings.update', 'bookings.approve', 'bookings.assign', 'bookings.cancel', 'control_room.*', 'clients.view', 'clients.verify', 'companies.view', 'operators.view', 'vehicles.view', 'documents.view', 'messages.view'],
        'Finance' => ['bookings.view', 'clients.view', 'companies.*', 'operators.view', 'finance.*', 'reports.view'],
        'Content Editor' => ['content.view', 'content.update', 'catalog.view', 'catalog.update', 'enquiries.view'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $all = array_keys(self::PERMISSIONS);
        foreach (self::ROLES as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            if ($perms === '*') {
                $r->syncPermissions($all);
                continue;
            }
            $expanded = [];
            foreach ($perms as $p) {
                if (str_ends_with($p, '.*')) {
                    $prefix = substr($p, 0, -1);
                    $expanded = array_merge($expanded, array_filter($all, fn ($a) => str_starts_with($a, $prefix)));
                } else {
                    $expanded[] = $p;
                }
            }
            $r->syncPermissions(array_unique($expanded));
        }
    }
}
