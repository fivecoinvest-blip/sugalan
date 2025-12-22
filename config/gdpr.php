<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GDPR Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GDPR compliance features
    |
    */

    'enabled' => env('GDPR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Whether to retain financial records for regulatory/accounting purposes
    | even after account deletion (anonymized)
    |
    */

    'retain_financial_records' => env('GDPR_RETAIN_FINANCIAL', true),

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    */

    'export' => [
        // How long export files are kept before automatic deletion (hours)
        'expiry_hours' => 24,
        
        // Maximum number of exports per user per day
        'max_exports_per_day' => 3,
        
        // Include sensitive data in exports
        'include_ip_addresses' => true,
        'include_audit_logs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deletion Settings
    |--------------------------------------------------------------------------
    */

    'deletion' => [
        // Grace period before permanent deletion (days)
        'grace_period_days' => 30,
        
        // Whether to require password confirmation
        'require_password' => true,
        
        // Whether to require explicit confirmation text
        'require_confirmation_text' => true,
        'confirmation_text' => 'DELETE',
        
        // Archive deleted account data (for potential recovery during grace period)
        'archive_deleted_data' => true,
        'archive_retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Information
    |--------------------------------------------------------------------------
    */

    'contact' => [
        'dpo_email' => env('GDPR_DPO_EMAIL', 'privacy@yourdomain.com'),
        'support_email' => env('GDPR_SUPPORT_EMAIL', 'support@yourdomain.com'),
        'company_name' => env('APP_NAME', 'Casino Platform'),
        'company_address' => env('COMPANY_ADDRESS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Consent
    |--------------------------------------------------------------------------
    */

    'cookies' => [
        'enabled' => true,
        'expiry_days' => 365,
        
        'categories' => [
            'essential' => [
                'name' => 'Essential Cookies',
                'description' => 'Required for the website to function properly',
                'required' => true,
            ],
            'functional' => [
                'name' => 'Functional Cookies',
                'description' => 'Enable enhanced functionality and personalization',
                'required' => false,
            ],
            'analytics' => [
                'name' => 'Analytics Cookies',
                'description' => 'Help us understand how visitors use our website',
                'required' => false,
            ],
            'marketing' => [
                'name' => 'Marketing Cookies',
                'description' => 'Used to track visitors across websites for marketing purposes',
                'required' => false,
            ],
        ],
    ],

];
