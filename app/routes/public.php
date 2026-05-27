<?php

use App\Services\AuthGoogleService;
use App\Services\AuthService;
use App\Services\MailConsentService;
use App\Services\SystemEventService;
use Core\AppContext;
use Core\Hooks;
use Core\ReleaseInfo;

/** @return array<string,mixed> */
$googleAuthBaseContext = function (): array {
    return [
        'enabled' => AuthGoogleService::isEnabled(),
    ];
};

$route->get('/manifest.webmanifest', 'Pwa@manifest');
$route->get('/service-worker.js', 'Pwa@serviceWorker');

/** @var \Core\Router $route */
$route->get('/', function () use ($googleAuthBaseContext) {
    $installedAt = null;
    if (defined('INSTALL_META') && is_array(INSTALL_META) && array_key_exists('installed_at', INSTALL_META)) {
        $installedAt = INSTALL_META['installed_at'];
    }

    $db = AppContext::dbProvider()->connection();

    $safeCount = function (string $sql) use ($db): int {
        try {
            $row = $db->fetchOnePrepared($sql, []);
            return (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    };

    // Core metrics: only query tables that belong to the core schema.
    $metrics = [
        'online_users' => $safeCount(
            'SELECT COUNT(*) AS cnt
             FROM characters
             WHERE date_last_signin IS NOT NULL
               AND date_last_signin > IFNULL(date_last_signout, \'1970-01-01 00:00:00\')
               AND DATE_ADD(date_last_seed, INTERVAL 20 MINUTE) > NOW()',
        ),
        'forum_threads_week' => $safeCount(
            'SELECT COUNT(*) AS cnt
             FROM forum_threads
             WHERE father_id IS NULL
               AND date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        ),
        // Optional module metrics default to 0; active modules populate them via the hook below.
        'module_activity_week' => 0,
        'active_events' => 0,
    ];

    // Active modules can augment the metrics array by registering:
    //   Hooks::add('landing.metrics', function(array $metrics, $db): array { ... return $metrics; });
    $metrics = Hooks::filter('landing.metrics', $metrics, $db);
    if (!is_array($metrics)) {
        $metrics = [];
    }

    $moduleFeed = [];
    $systemEventsFeed = [];
    try {
        $moduleFeed = Hooks::filter('landing.module_feed', [], 6, $db);
    } catch (\Throwable $e) {
        $moduleFeed = [];
    }
    try {
        $systemEventsFeed = (new SystemEventService($db))->listForHomepageFeed(6);
    } catch (\Throwable $e) {
        $systemEventsFeed = [];
    }

    $googleAuth = array_merge($googleAuthBaseContext(), [
        'error_code' => (string) (AppContext::session()->get('google_auth_error_code') ?? ''),
        'error_message' => (string) (AppContext::session()->get('google_auth_error_message') ?? ''),
        'toast_type' => (string) (AppContext::session()->get('google_auth_toast_type') ?? ''),
        'open_create_character' => ((int) (AppContext::session()->get('google_auth_open_create_character') ?? 0) === 1),
        'open_select_character' => ((int) (AppContext::session()->get('google_auth_open_select_character') ?? 0) === 1),
        'select_characters' => (array) (AppContext::session()->get('google_auth_select_characters') ?? []),
        'prefill_name' => (string) (AppContext::session()->get('google_auth_prefill_name') ?? ''),
    ]);
    AppContext::session()->delete('google_auth_error_code');
    AppContext::session()->delete('google_auth_error_message');
    AppContext::session()->delete('google_auth_toast_type');
    AppContext::session()->delete('google_auth_open_create_character');
    AppContext::session()->delete('google_auth_open_select_character');
    AppContext::session()->delete('google_auth_select_characters');
    AppContext::session()->delete('google_auth_prefill_name');

    $release = ReleaseInfo::status();
    $distribution = (string) ($release['distribution'] ?? 'legacy');
    $channel = (string) ($release['update_channel'] ?? 'stable');
    $webUpdaterAllowed = !empty($release['web_updater_allowed']);
    $minimumPhp = trim((string) (($release['local_manifest']['minimum_php'] ?? '')));

    $updatesLabel = 'Stato aggiornamenti non disponibile';
    if ($distribution === 'ready') {
        $updatesLabel = 'Distribuzione ready (canale ' . $channel . ')';
    } elseif ($distribution === 'source-dev') {
        $updatesLabel = 'Distribuzione source-dev (canale ' . $channel . ', update web non applicabile)';
    } elseif (!empty($release['is_legacy'])) {
        $updatesLabel = 'Distribuzione legacy (manifest locale non completo)';
    }

    $baselineLabel = 'Runtime core consolidato';
    if ($minimumPhp !== '') {
        $baselineLabel .= ' · PHP minimo ' . $minimumPhp;
    }
    if ($webUpdaterAllowed) {
        $baselineLabel .= ' · updater web abilitabile';
    } else {
        $baselineLabel .= ' · updater web solo informativo';
    }

    return AppContext::templateRenderer()->render('index.twig', [
        'landing_metrics' => [
            'online_users' => (int) ($metrics['online_users'] ?? 0),
            'module_activity_week' => (int) ($metrics['module_activity_week'] ?? 0),
            'forum_threads_week' => (int) ($metrics['forum_threads_week'] ?? 0),
            'active_events' => (int) ($metrics['active_events'] ?? 0),
        ],
        'system_state' => [
            'version' => (string) ($release['installed_version'] ?? '0.0.0'),
            'installed_at' => $installedAt,
            'updates' => $updatesLabel,
            'baseline' => $baselineLabel,
            'distribution' => $distribution,
            'update_channel' => $channel,
            'installed_commit' => (string) ($release['installed_commit'] ?? 'unknown'),
            'web_updater_allowed' => $webUpdaterAllowed,
        ],
        'home_module_feed' => is_array($moduleFeed) ? $moduleFeed : [],
        'home_system_events_feed' => is_array($systemEventsFeed) ? $systemEventsFeed : [],
        'google_auth' => $googleAuth,
    ]);
});
$route->get('/auth/google/start', function () {
    AuthGoogleService::redirectToGoogle();
});
$route->get('/auth/google/callback', function () {
    AuthGoogleService::handleCallback();
});
$route->get('/rules', function () use ($googleAuthBaseContext) {
    $viewModes = (new \App\Services\SettingsService())->getDocsViewModes();
    return AppContext::templateRenderer()->render('rules.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'view_mode' => $viewModes['rules_view_mode'],
    ]);
});
$route->apiPost('/rules/list', 'Rules@publicList');
$route->get('/storyboard', function () use ($googleAuthBaseContext) {
    $viewModes = (new \App\Services\SettingsService())->getDocsViewModes();
    return AppContext::templateRenderer()->render('storyboard.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'view_mode' => $viewModes['storyboard_view_mode'],
    ]);
});
$route->apiPost('/storyboards/list', 'Storyboards@publicList');
$route->get('/how-to-play', function () use ($googleAuthBaseContext) {
    $viewModes = (new \App\Services\SettingsService())->getDocsViewModes();
    return AppContext::templateRenderer()->render('how_to_play.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'view_mode' => $viewModes['how_to_play_view_mode'],
    ]);
});
$route->apiPost('/how-to-play/list', 'HowToPlays@publicList');

$route->get('/privacy-policy', function () use ($googleAuthBaseContext) {
    $legal = (is_array(APP) && isset(APP['legal']) && is_array(APP['legal'])) ? APP['legal'] : [];
    $instanceName = trim((string) (APP['name'] ?? 'questa istanza Logeon'));
    $privacyContactName = trim((string) ($legal['privacy_contact_name'] ?? (APP['dba_name'] ?? 'Gestore dell\'istanza')));
    if ($privacyContactName === '') {
        $privacyContactName = 'Gestore dell\'istanza';
    }
    $privacyContactEmail = trim((string) ($legal['privacy_contact_email'] ?? (APP['dba_email'] ?? '')));
    return AppContext::templateRenderer()->render('public/legal_page.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'page_title' => 'Privacy Policy',
        'page_subtitle' => 'Informativa sul trattamento dei dati personali ai sensi del Regolamento (UE) 2016/679 (GDPR) e della normativa italiana applicabile.',
        'page_version' => (string) ($legal['privacy_policy_version'] ?? '1.0.0'),
        'page_updated_at' => (string) ($legal['updated_at'] ?? ''),
        'intro_text' => 'Questa informativa descrive in modo trasparente come vengono trattati i dati personali degli utenti che utilizzano ' . $instanceName . '. Il trattamento avviene nel rispetto dei principi di liceita, correttezza, trasparenza, minimizzazione e sicurezza.',
        'sections' => [
            [
                'title' => '1) Titolare del trattamento e contatti',
                'items' => [
                    'Il titolare del trattamento e il gestore dell\'istanza che pubblica e amministra questo sito.',
                    'Referente privacy: ' . $privacyContactName . '.',
                    ($privacyContactEmail !== '' ? ('Contatto privacy: ' . $privacyContactEmail . '.') : 'Il contatto privacy e indicato nei riferimenti pubblici dell\'istanza.'),
                    'Se nominato, il Responsabile della protezione dei dati (DPO) viene comunicato in questa sezione o tramite comunicazione dedicata.',
                ],
            ],
            [
                'title' => '2) Categorie di dati trattati',
                'items' => [
                    'Dati identificativi e di account: nome utente, email, hash password, data e ora di registrazione.',
                    'Dati tecnici: indirizzo IP, user-agent, log di sicurezza, token di sessione, metadati tecnici necessari al funzionamento.',
                    'Dati di utilizzo del servizio: personaggi, messaggi, attivita di gioco, impostazioni e preferenze utente.',
                    'Dati sui consensi: storico di accettazione privacy/termini, preferenze newsletter, preferenze cookie e relativo versionamento.',
                    'Dati relativi alle richieste privacy: richieste di esportazione o cancellazione account, stato della pratica e log amministrativi correlati.',
                ],
            ],
            [
                'title' => '3) Finalita e basi giuridiche del trattamento',
                'items' => [
                    'Erogazione del servizio richiesto dall\'utente (creazione e gestione account, accesso al gioco, funzioni operative): base giuridica esecuzione di un contratto o di misure precontrattuali (art. 6, par. 1, lett. b GDPR).',
                    'Adempimento di obblighi normativi o richieste legittime dell\'autorita: base giuridica obbligo legale (art. 6, par. 1, lett. c GDPR).',
                    'Sicurezza della piattaforma, prevenzione abusi, difesa in giudizio e continuita tecnica del servizio: base giuridica legittimo interesse del titolare (art. 6, par. 1, lett. f GDPR), nel rispetto dei diritti dell\'interessato.',
                    'Invio di comunicazioni facoltative non strettamente necessarie (es. newsletter) e uso di cookie opzionali: base giuridica consenso libero e revocabile (art. 6, par. 1, lett. a GDPR).',
                ],
            ],
            [
                'title' => '4) Natura del conferimento dei dati',
                'items' => [
                    'Il conferimento dei dati contrassegnati come necessari e obbligatorio per creare l\'account e usare il servizio.',
                    'Il mancato conferimento dei dati necessari comporta l\'impossibilita di completare registrazione, autenticazione o erogazione delle funzionalita essenziali.',
                    'Il conferimento dei dati per finalita facoltative (es. newsletter, cookie opzionali) e sempre libero e non condiziona l\'uso delle funzionalita base.',
                ],
            ],
            [
                'title' => '5) Modalita del trattamento e misure di sicurezza',
                'items' => [
                    'I dati sono trattati con strumenti elettronici e, se necessario, con procedure manuali, secondo logiche strettamente correlate alle finalita dichiarate.',
                    'Sono adottate misure tecniche e organizzative adeguate al rischio, incluse misure di autenticazione, controllo accessi, tracciamento eventi rilevanti e protezione dell\'integrita dei dati.',
                    'L\'accesso ai dati e consentito solo a soggetti autorizzati, istruiti e vincolati a riservatezza.',
                ],
            ],
            [
                'title' => '6) Destinatari dei dati',
                'items' => [
                    'I dati possono essere trattati da personale autorizzato del titolare e, ove necessario, da fornitori che operano come responsabili del trattamento ai sensi dell\'art. 28 GDPR.',
                    'I dati possono essere comunicati ad autorita competenti ove previsto da norme di legge o ordini legittimi.',
                    'I dati non sono diffusi indiscriminatamente al pubblico fuori dai casi previsti dalle funzionalita del servizio e dalle scelte dell\'utente.',
                ],
            ],
            [
                'title' => '7) Trasferimenti extra SEE',
                'items' => [
                    'Se il trattamento comporta trasferimenti di dati personali verso Paesi non appartenenti allo Spazio Economico Europeo, tali trasferimenti avvengono solo nel rispetto degli artt. 44-49 GDPR.',
                    'Quando necessario, vengono adottate garanzie adeguate (ad esempio clausole contrattuali standard o altri strumenti riconosciuti dalla normativa).',
                ],
            ],
            [
                'title' => '8) Tempi di conservazione',
                'items' => [
                    'I dati di account e di servizio sono conservati per il tempo necessario a erogare il servizio e gestire il rapporto con l\'utente.',
                    'I log tecnici e di sicurezza sono conservati per periodi proporzionati alle finalita di sicurezza, prevenzione abusi e audit.',
                    'I dati dei consensi e delle richieste privacy sono conservati per i periodi definiti dalla configurazione legale dell\'istanza e dalle esigenze normative o difensive del titolare.',
                    'Al termine dei periodi applicabili, i dati sono cancellati o anonimizzati, salvo ulteriori obblighi di legge.',
                ],
            ],
            [
                'title' => '9) Diritti dell\'interessato',
                'items' => [
                    'L\'interessato puo esercitare i diritti previsti dagli artt. 15-22 GDPR: accesso, rettifica, cancellazione, limitazione, portabilita, opposizione e revoca del consenso (senza pregiudicare la liceita del trattamento precedente alla revoca).',
                    'L\'interessato puo presentare richiesta direttamente dalle funzioni disponibili in piattaforma (ove abilitate) o tramite i contatti privacy indicati.',
                    'L\'interessato ha diritto di proporre reclamo all\'Autorita Garante per la protezione dei dati personali (art. 77 GDPR), fatta salva ogni altra azione amministrativa o giudiziaria.',
                ],
            ],
            [
                'title' => '10) Minori',
                'items' => [
                    'L\'eventuale accesso da parte di minori deve rispettare i limiti di eta e le condizioni previste dalla normativa applicabile e dalle regole dell\'istanza.',
                    'Se richiesto dalla normativa o dalle policy dell\'istanza, possono essere adottate verifiche o misure aggiuntive relative al consenso dei genitori o esercenti la responsabilita genitoriale.',
                ],
            ],
            [
                'title' => '11) Modifiche alla presente informativa',
                'items' => [
                    'Questa informativa puo essere aggiornata in caso di modifiche normative, tecniche o organizzative.',
                    'In caso di aggiornamenti sostanziali, il titolare informa gli utenti con modalita proporzionate (es. avvisi in piattaforma o aggiornamento versione policy).',
                ],
            ],
        ],
        'privacy_contact_email' => $privacyContactEmail,
    ]);
});

$route->get('/terms-of-service', function () use ($googleAuthBaseContext) {
    $legal = (is_array(APP) && isset(APP['legal']) && is_array(APP['legal'])) ? APP['legal'] : [];
    $instanceName = trim((string) (APP['name'] ?? 'questa istanza Logeon'));
    return AppContext::templateRenderer()->render('public/legal_page.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'page_title' => 'Termini del Servizio',
        'page_subtitle' => 'Condizioni d\'uso del servizio rivolte agli utenti della piattaforma.',
        'page_version' => (string) ($legal['terms_of_service_version'] ?? '1.0.0'),
        'page_updated_at' => (string) ($legal['updated_at'] ?? ''),
        'intro_text' => 'Usando ' . $instanceName . ' dichiari di aver letto e accettato questi Termini del Servizio. Se non accetti le condizioni, non utilizzare la piattaforma.',
        'sections' => [
            [
                'title' => '1) Oggetto del servizio',
                'items' => [
                    'La piattaforma fornisce funzionalita software per la gestione di un ambiente PbC e dei relativi account utente.',
                    'Le funzionalita disponibili possono variare nel tempo per esigenze tecniche, di sicurezza o evoluzione del progetto.',
                ],
            ],
            [
                'title' => '2) Requisiti di accesso e account',
                'items' => [
                    'Per accedere al servizio e necessario creare un account con dati veritieri, aggiornati e non fuorvianti.',
                    'L\'utente e responsabile della riservatezza delle proprie credenziali e di tutte le attivita svolte tramite il proprio account.',
                    'E vietato cedere, condividere o utilizzare account altrui senza autorizzazione.',
                ],
            ],
            [
                'title' => '3) Regole di condotta',
                'items' => [
                    'E vietato usare la piattaforma per attivita illecite, fraudolente, diffamatorie, discriminatorie, moleste o lesive dei diritti altrui.',
                    'E vietato tentare accessi non autorizzati, compromettere sicurezza, stabilita o integrita del servizio, oppure aggirare misure tecniche di protezione.',
                    'L\'utente deve rispettare regolamenti di gioco, regole di community e indicazioni dello staff dell\'istanza.',
                ],
            ],
            [
                'title' => '4) Contenuti degli utenti e moderazione',
                'items' => [
                    'L\'utente resta responsabile dei contenuti che pubblica o trasmette.',
                    'Il titolare/lo staff puo intervenire su contenuti e comportamenti non conformi ai Termini, al regolamento interno o alla legge, incluse rimozione contenuti, limitazioni o sospensioni.',
                    'Le decisioni di moderazione sono adottate in base a criteri di sicurezza, tutela della community e rispetto delle norme applicabili.',
                ],
            ],
            [
                'title' => '5) Sospensione, limitazione o chiusura account',
                'items' => [
                    'In caso di violazioni o rischi per sicurezza e continuita del servizio, l\'account puo essere temporaneamente limitato o sospeso.',
                    'Nei casi piu gravi o reiterati, l\'account puo essere chiuso.',
                    'Restano ferme le tutele previste dalla normativa applicabile e le procedure privacy disponibili per l\'utente.',
                ],
            ],
            [
                'title' => '6) Disponibilita, manutenzione e aggiornamenti',
                'items' => [
                    'Il servizio puo essere soggetto a manutenzione programmata o interventi urgenti non programmati.',
                    'Il titolare non garantisce continuita assoluta o assenza totale di errori, ma adotta misure ragionevoli per assicurare affidabilita e ripristino.',
                    'Aggiornamenti tecnici, funzionali o di sicurezza possono modificare alcune funzionalita o interfacce.',
                ],
            ],
            [
                'title' => '7) Proprieta intellettuale',
                'items' => [
                    'Software, interfacce, marchi, elementi grafici e documentazione della piattaforma restano tutelati dalle norme su proprieta intellettuale e diritto d\'autore.',
                    'E vietato copiare, distribuire, alterare o riutilizzare parti sostanziali della piattaforma oltre i limiti consentiti dalla licenza applicabile e dalla legge.',
                ],
            ],
            [
                'title' => '8) Limitazione di responsabilita',
                'items' => [
                    'Nei limiti consentiti dalla legge, il servizio e fornito secondo disponibilita tecnica e senza garanzie implicite ulteriori rispetto a quelle obbligatorie.',
                    'Il titolare non risponde di danni indiretti o conseguenti derivanti da uso improprio del servizio, interruzioni di rete, comportamenti di terzi o forza maggiore.',
                    'Nessuna clausola di questi Termini limita diritti inderogabili dell\'utente previsti dalla normativa europea e italiana applicabile.',
                ],
            ],
            [
                'title' => '9) Privacy e protezione dati',
                'items' => [
                    'Il trattamento dei dati personali e disciplinato dalla Privacy Policy e dalla Cookie Policy, che costituiscono parte integrante dei presenti Termini.',
                    'Per i dettagli su finalita, basi giuridiche, tempi di conservazione e diritti dell\'interessato, fai riferimento alle informative dedicate.',
                ],
            ],
            [
                'title' => '10) Legge applicabile e foro competente',
                'items' => [
                    'I presenti Termini sono disciplinati dalla legge italiana, salva l\'applicazione di norme imperative eventualmente piu favorevoli previste dalla legge dello Stato di residenza dell\'utente consumatore.',
                    'Per gli utenti qualificabili come consumatori, la competenza territoriale inderogabile resta quella del giudice del luogo di residenza o domicilio del consumatore, quando prevista dalla normativa.',
                ],
            ],
            [
                'title' => '11) Modifiche ai Termini',
                'items' => [
                    'Il titolare puo aggiornare questi Termini per ragioni normative, tecniche o organizzative.',
                    'Le nuove versioni diventano efficaci dalla data di pubblicazione indicata, salvo diverso termine comunicato.',
                ],
            ],
        ],
        'privacy_contact_email' => (string) ($legal['privacy_contact_email'] ?? (APP['dba_email'] ?? '')),
    ]);
});

$route->get('/cookie-policy', function () use ($googleAuthBaseContext) {
    $legal = (is_array(APP) && isset(APP['legal']) && is_array(APP['legal'])) ? APP['legal'] : [];
    $instanceName = trim((string) (APP['name'] ?? 'questa istanza Logeon'));
    return AppContext::templateRenderer()->render('public/legal_page.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'page_title' => 'Cookie Policy',
        'page_subtitle' => 'Informativa su cookie e strumenti di tracciamento ai sensi della normativa UE e italiana applicabile.',
        'page_version' => (string) ($legal['cookie_policy_version'] ?? '1.0.0'),
        'page_updated_at' => (string) ($legal['updated_at'] ?? ''),
        'intro_text' => 'Questa informativa descrive come ' . $instanceName . ' utilizza cookie e tecnologie analoghe, in coerenza con il GDPR, la disciplina ePrivacy e le Linee guida cookie del Garante Privacy del 10 giugno 2021.',
        'sections' => [
            [
                'title' => '1) Cosa sono cookie e strumenti di tracciamento',
                'items' => [
                    'I cookie sono piccoli file di testo che i siti salvano sul dispositivo dell\'utente per garantire funzioni tecniche, memorizzare preferenze o raccogliere dati statistici/di profilazione.',
                    'Per strumenti di tracciamento si intendono anche tecnologie alternative ai cookie che possono identificare o distinguere il dispositivo o l\'utente.',
                ],
            ],
            [
                'title' => '2) Cookie tecnici necessari (sempre attivi)',
                'items' => [
                    'Questi cookie sono indispensabili per autenticazione, mantenimento sessione, sicurezza, bilanciamento tecnico e corretta erogazione del servizio richiesto.',
                    'Senza cookie tecnici il sito potrebbe non funzionare correttamente o non consentire accesso ad aree riservate.',
                    'Per i cookie tecnici non e richiesto consenso preventivo, fermo restando l\'obbligo di informativa.',
                ],
            ],
            [
                'title' => '3) Cookie opzionali (solo con consenso)',
                'items' => [
                    'Eventuali cookie di preferenza, analytics non anonimizzati o marketing/profilazione sono attivati solo dopo consenso libero, specifico e informato dell\'utente.',
                    'Il mancato consenso ai cookie opzionali non pregiudica l\'accesso alle funzioni essenziali del servizio.',
                    'Se categorie opzionali non sono usate dall\'istanza, restano disattivate.',
                ],
            ],
            [
                'title' => '4) Gestione del consenso cookie',
                'items' => [
                    'Il banner consente di accettare tutto, rifiutare i cookie opzionali o personalizzare le preferenze per categoria.',
                    'Le preferenze espresse vengono registrate per documentare la scelta e ridurre ripetizioni non necessarie della richiesta di consenso.',
                    'L\'utente puo aggiornare le preferenze in qualsiasi momento tramite gli strumenti messi a disposizione dal sito o mediante impostazioni del browser.',
                ],
            ],
            [
                'title' => '5) Durata dei cookie',
                'items' => [
                    'I cookie di sessione restano attivi solo per la durata della sessione di navigazione.',
                    'I cookie persistenti restano memorizzati fino alla scadenza tecnica impostata o fino a cancellazione manuale da parte dell\'utente.',
                    'I tempi di conservazione delle prove di consenso sono definiti dalla configurazione legale dell\'istanza, in coerenza con principi di minimizzazione e limitazione della conservazione.',
                ],
            ],
            [
                'title' => '6) Cookie di terze parti',
                'items' => [
                    'Quando il sito integra servizi esterni, possono essere presenti cookie o strumenti di terze parti, soggetti alle rispettive informative e responsabilita.',
                    'In tali casi il titolare adotta, per quanto di competenza, misure di trasparenza e configurazioni coerenti con la normativa applicabile.',
                ],
            ],
            [
                'title' => '7) Base giuridica del trattamento via cookie',
                'items' => [
                    'Cookie tecnici necessari: base giuridica esecuzione del servizio richiesto e legittimo interesse alla sicurezza e funzionalita del sito.',
                    'Cookie opzionali: base giuridica consenso dell\'utente, revocabile in ogni momento.',
                ],
            ],
            [
                'title' => '8) Contatti e diritti',
                'items' => [
                    'Per chiarimenti su uso dei cookie e trattamento dei dati puoi contattare il referente privacy indicato nelle informative legali.',
                    'Resta salvo il diritto di proporre reclamo all\'Autorita Garante per la protezione dei dati personali.',
                ],
            ],
        ],
        'privacy_contact_email' => (string) ($legal['privacy_contact_email'] ?? (APP['dba_email'] ?? '')),
    ]);
});

$route->get('/shared/chat-archive/{token}', function ($token) use ($googleAuthBaseContext) {
    return AppContext::templateRenderer()->render('public/chat_archive_shared.twig', [
        'google_auth' => $googleAuthBaseContext(),
        'archive_token' => $token,
    ]);
});

$route->get('/reset-password/{token}', function ($token) use ($googleAuthBaseContext) {
    return AppContext::templateRenderer()->render('sys/reset_password.twig', [
        'token' => $token,
        'google_auth' => $googleAuthBaseContext(),
    ]);
});
$route->get('/verify-email/{token}', function ($token) use ($googleAuthBaseContext) {
    try {
        $result = AuthService::verifyEmailToken((string) $token);
    } catch (\Throwable $e) {
        $result = [
            'status' => 'invalid',
            'message' => 'Verifica email non disponibile in questo momento.',
        ];
    }

    return AppContext::templateRenderer()->render('sys/verify_email.twig', [
        'verification' => [
            'status' => (string) ($result['status'] ?? 'invalid'),
            'message' => (string) ($result['message'] ?? 'Verifica non riuscita.'),
        ],
        'google_auth' => $googleAuthBaseContext(),
    ]);
});

$route->get('/unsubscribe/{token}', function ($token) use ($googleAuthBaseContext) {
    try {
        $service = new MailConsentService();
        $result = $service->consumeUnsubscribeToken(
            (string) $token,
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        );
    } catch (\Throwable) {
        $result = [
            'success' => false,
            'status' => 'invalid',
            'message' => 'Disiscrizione non disponibile in questo momento.',
        ];
    }

    return AppContext::templateRenderer()->render('sys/unsubscribe.twig', [
        'unsubscribe' => $result,
        'google_auth' => $googleAuthBaseContext(),
    ]);
});
