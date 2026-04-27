<?php
/**
 * Zentrale Konfiguration.
 * Sensible Secrets (Crypto-Key) liegen separat in includes/secrets.php
 * und werden beim ersten Setup via install.php erzeugt.
 */

return [
    // ---------- Branding ----------
    'app_name'      => 'Karriere bei Musterfirma',
    'company_name'  => 'Musterfirma GmbH',
    'contact_email' => 'karriere@musterfirma.de',
    'contact_phone' => '+49 30 12345678',
    'logo_path'     => 'assets/logo.svg',

    'colors' => [
        'primary'    => '#2563eb',
        'primary_dk' => '#1d4ed8',
        'accent'     => '#0ea5e9',
        'bg'         => '#ffffff',
        'bg_soft'    => '#f8fafc',
        'surface'    => '#ffffff',
        'text'       => '#0f172a',
        'text_soft'  => '#64748b',
        'danger'     => '#dc2626',
        'success'    => '#059669',
    ],

    // ---------- Offene Positionen ----------
    'positions' => [
        'Software Engineer (m/w/d)',
        'Produktmanager:in',
        'UX/UI Designer:in',
        'Werkstudent:in',
        'Initiativbewerbung',
    ],

    // ---------- Admin-Benachrichtigung ----------
    'admin_notify_emails' => [
        'hr@musterfirma.de',
    ],

    // ---------- SMTP ----------
    'smtp' => [
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'encryption' => 'tls',              // 'tls' (STARTTLS) | 'ssl' | 'none'
        'username'   => 'karriere@musterfirma.de',
        'password'   => '',                 // leer lassen und via Env SMTP_PASSWORD setzen
        'from_email' => 'karriere@musterfirma.de',
        'from_name'  => 'Musterfirma Karriere',
        'timeout'    => 15,
    ],

    // ---------- Uploads ----------
    'uploads' => [
        'max_files'      => 3,
        'max_size_bytes' => 8 * 1024 * 1024,
        'allowed_mimes'  => ['application/pdf'],
        'allowed_ext'    => ['pdf'],
    ],

    // ---------- Sicherheit ----------
    'security' => [
        'rate_limit_per_hour' => 5,
        'login_max_attempts'  => 5,
        'login_lockout_min'   => 15,
        'session_lifetime'    => 3600,
    ],
];
