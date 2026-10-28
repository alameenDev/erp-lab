<?php

namespace App\Services;

class PermissionCatalog
{
    public const NAMES = [
            'main dashboard view',

            // Inventory
            'inventory view',
            'inventory manage',
            'inventory reports',
            'inventory repeat',

            // Reports
            'reports view',
            'accounting reports view',

            // Sample collection
            'sample logistics view',
            'sample logistics manage',
            'sample logistics review',

            // Invoices
            'invoices view',
            'invoices create',
            'invoices edit',
            'invoices delete',
            'invoices print',
            'invoices export',
            'invoices send whatsapp',
            'invoices job order',
            'invoices thermal reciept print',

            // Roles
            'roles view',
            'roles create',
            'roles edit',
            'roles delete',

            // Medical Reports
            'medical reports patient history',
            'medical reports patient details',
            'medical reports signiture',
            'medical reports attachments',
            'medical reports update',
            'medical reports results view',

            // Patients
            'patients view',
            'patients create',
            'patients edit',
            'patients delete',

            // Price List
            'price list view',
            'price list create',
            'price list edit',
            'price list delete',

            // Tests
            'tests view',
            'tests create',
            'tests edit',
            'tests delete',

            // Test Groups
            'test groups view',
            'test groups create',
            'test groups edit',
            'test groups delete',

            // Categories
            'categories view',
            'categories create',
            'categories edit',
            'categories delete',

            // Samples
            'samples view',
            'samples create',
            'samples edit',
            'samples delete',

            // Packages
            'packages view',
            'packages create',
            'packages edit',
            'packages delete',

            // Cultures
            'cultures view',
            'cultures create',
            'cultures edit',
            'cultures delete',

            // Test Questions
            'test questions view',
            'test questions create',
            'test questions edit',
            'test questions delete',

            // Antibiotics
            'antibiotics view',
            'antibiotics create',
            'antibiotics edit',
            'antibiotics delete',

            // Users
            'users view',
            'users create',
            'users edit',
            'users delete',

            // Referrals
            'referrals view',
            'referrals create',
            'referrals edit',
            'referrals delete',

            // Contracts
            'contracts view',
            'contracts create',
            'contracts edit',
            'contracts delete',

            // Payments
            'payments view',
            'payments create',
            'payments edit',
            'payments delete',

            // Activities
            'activities view',
            'activities create',
            'activities edit',
            'activities delete',

            // Super Admin
            'super admin dashboard',
            'super admin labs',
            'super admin activity log',

            // Subscriptions
            'subscriptions view',
            'subscriptions create',
            'subscriptions edit',
            'subscriptions delete',

            'lab settings edit',
            'doctors view', 'doctors create', 'doctors edit', 'doctors delete',
            'promo codes view', 'promo codes create', 'promo codes edit', 'promo codes delete',

            // Devices
            'devices view',
            'devices create',
            'devices edit',
            'devices delete',
        ];
}
