<?php
declare(strict_types=1);

return [
    // Landing
    'landing.subtitle'       => 'Ein Microservices-Ökosystem mit zentralisierter Authentifizierung. Entdecke OAuth, zustandsloses JWT und hexagonale Architektur in Containern.',
    'landing.cta_google'     => 'Mit Google anmelden',
    'landing.instances'      => '// aktive Instanzen',

    // Services
    'service.auth.name' => 'Auth Gateway',
    'service.auth.desc' => 'Zentralisierter Login via Google OAuth 2.0. Ein einziger Identitätspunkt für alle Dienste.',
    'service.jwt.name'  => 'JWT Stateless',
    'service.jwt.desc'  => 'HS256-signierte Tokens, gültig zwischen Containern. Keine Sessions, kein gemeinsamer Zustand.',
    'service.hex.name'  => 'Hexagonales PHP',
    'service.hex.desc'  => 'Domain, Application und Infrastructure entkoppelt. Ports & Adapters für maximale Testbarkeit.',

    // Dashboard / user
    'dashboard.authenticated' => '✓ Authentifiziert',
    'dashboard.email'         => 'E-Mail',
    'dashboard.user_id'       => 'Benutzer-ID',
    'dashboard.logout'        => 'Abmelden',

    // Nav
    'nav.dashboard' => 'Dashboard',
    'nav.users'     => 'Benutzer',
    'nav.profile'   => 'Profil',

    // Users view
    'users.title'        => 'Autorisierte Benutzer',
    'users.admin'        => 'Admin',
    'users.user'         => 'Benutzer',
    'users.joined'       => 'Beigetreten',
    'users.empty'        => 'Keine autorisierten Benutzer gefunden.',
    'users.add'          => 'Benutzer hinzufügen',
    'users.add_title'    => 'Neuer Benutzer',
    'users.email'        => 'E-Mail',
    'users.name'         => 'Name (optional)',
    'users.make_admin'   => 'Admin erteilen',
    'users.revoke_admin' => 'Admin entziehen',
    'users.delete'       => 'Löschen',
    'users.cancel'       => 'Abbrechen',
    'users.save'         => 'Speichern',
    'users.confirm_delete' => 'Diesen Benutzer löschen?',

    // Metrics summary
    'metrics.summary'    => 'Übersicht',
    'metrics.containers' => 'Container',

    // Metrics
    'metrics.title'       => 'Container-Metriken',
    'metrics.cpu'         => 'CPU',
    'metrics.memory'      => 'Arbeitsspeicher',
    'metrics.network'     => 'Netzwerk',
    'metrics.disk'        => 'Festplatten-I/O',
    'metrics.processes'   => 'Prozesse',
    'metrics.rx'          => 'Eingehend',
    'metrics.tx'          => 'Ausgehend',
    'metrics.read'        => 'Lesen',
    'metrics.write'       => 'Schreiben',
    'metrics.of'          => 'von',
    'metrics.running'     => 'läuft',
    'metrics.stopped'     => 'gestoppt',
    'metrics.no_socket'   => 'Docker-Socket nicht verfügbar. Binde /var/run/docker.sock in den Container ein.',
    'metrics.no_data'     => 'Keine Container gefunden.',

    // Unauthorized
    'error.title'       => 'Zugang verweigert',
    'error.desc'        => 'Ihr Konto ist nicht berechtigt, auf diese Ressource zuzugreifen.',
    'error.redirecting' => 'Weiterleitung in',

    // Footer
    'footer.made_by' => 'Erstellt von',
];
