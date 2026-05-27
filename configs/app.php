<?php

const APP = [
  'baseurl' => 'https://logeon.site',
  'lang' => 'it',
  'name' => 'Logeon - Engine PbC',
  'title' => 'Logeon - Engine PbC',
  'description' => 'Piattaforma per la creazione e la gestione di <em>browser game</em> di genere <em>giochi di ruolo</em> (gdr) in ambito del play by chat.',
  'brand_logo_icon' => '/assets/imgs/logo/logo.png',
  'brand_logo_wordmark' => '/assets/imgs/logo/logo_tipografy.png',
  'wm_name' => 'Geko (Fabio F.)',
  'wm_email' => 'supporto@logeon.it',
  'dba_name' => 'Geko (Fabio F.)',
  'dba_email' => 'supporto@logeon.it',
  'support_name' => 'Geko (Fabio F.)',
  'support_email' => 'supporto@logeon.it',
  'legal' =>
   [
    'privacy_policy_url' => '/privacy-policy',
    'terms_of_service_url' => '/terms-of-service',
    'cookie_policy_url' => '/cookie-policy',
    'privacy_policy_version' => '1.1.0',
    'terms_of_service_version' => '1.1.0',
    'cookie_policy_version' => '1.1.0',
    'updated_at' => '22/05/2026',
    'privacy_contact_name' => 'Geko (Fabio F.)',
    'privacy_contact_email' => 'supporto@logeon.it',
    'retention' =>
     [
      'interval_minutes' => 720,
      'cookie_consent_log_days' => 365,
      'mail_consent_log_days' => 1825,
      'legal_consent_log_days' => 1825,
      'gdpr_request_payload_days' => 365,
      'gdpr_request_closed_days' => 1825,
    ],
  ],
  'shop' =>
   [
    'sell_ratio' => 0.5,
  ],
  'oauth_google' =>
   [
    'enabled' => false,
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => '',
  ],
  'frontend' =>
   [
    'pilot_bundle_mode' => 'auto',
    'pilot_bundle_enabled' => false,
    'pilot_bundle_version' => 'auto',
  ],
  'pwa' =>
   [
    'enabled' => true,
    'name' => 'Logeon - Engine PbC',
    'short_name' => 'Logeon',
    'description' => 'Motore open-source per giochi play-by-chat installabile come app.',
    'start_path' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'landscape',
    'theme_color' => '#121827',
    'background_color' => '#121827',
    'icon_path' => '/assets/imgs/logo/logo.png',
    'icon_192_path' => '/assets/imgs/logo/pwa-192.png',
    'icon_512_path' => '/assets/imgs/logo/pwa-512.png',
    'icon_maskable_path' => '',
    'cache_enabled' => true,
    'cache_version' => '20260428',
  ],
  'theme' =>
   [
    'enabled' => true,
    'active_theme' => '',
    'strict_mode' => true,
    'allow_custom_js' => true,
  ],
  'updates' =>
   [
    'manifest_url' => 'https://raw.githubusercontent.com/Gekis-Geko/Logeon/main/update-manifest.json',
    'manifest_timeout_seconds' => 8,
  ],
];
