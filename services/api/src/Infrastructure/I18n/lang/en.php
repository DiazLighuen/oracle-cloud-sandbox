<?php
declare(strict_types=1);

return [
    // Landing
    'landing.subtitle'       => 'A microservices ecosystem with centralized authentication. Explore OAuth, stateless JWT and hexagonal architecture running in containers.',
    'landing.cta_google'     => 'Sign in with Google',
    'landing.instances'      => '// active instances',

    // Services
    'service.auth.name' => 'Auth Gateway',
    'service.auth.desc' => 'Centralized login via Google OAuth 2.0. A single identity point for all services.',
    'service.jwt.name'  => 'JWT Stateless',
    'service.jwt.desc'  => 'HS256-signed tokens valid across containers. No sessions, no shared state.',
    'service.hex.name'  => 'Hexagonal PHP',
    'service.hex.desc'  => 'Decoupled Domain, Application and Infrastructure. Ports & Adapters for full testability.',

    // Dashboard / user
    'dashboard.authenticated' => '✓ Authenticated',
    'dashboard.email'         => 'Email',
    'dashboard.user_id'       => 'User ID',
    'dashboard.logout'        => 'Sign out',

    // Nav
    'nav.dashboard' => 'Dashboard',
    'nav.users'     => 'Users',
    'nav.profile'   => 'Profile',

    // Users view
    'users.title'        => 'Authorized Users',
    'users.admin'        => 'Admin',
    'users.user'         => 'User',
    'users.joined'       => 'Joined',
    'users.empty'        => 'No authorized users found.',
    'users.add'          => 'Add user',
    'users.add_title'    => 'Add new user',
    'users.email'        => 'Email',
    'users.name'         => 'Name (optional)',
    'users.make_admin'   => 'Grant admin',
    'users.revoke_admin' => 'Revoke admin',
    'users.delete'       => 'Delete',
    'users.cancel'       => 'Cancel',
    'users.save'         => 'Save',
    'users.confirm_delete' => 'Delete this user?',

    // Metrics summary
    'metrics.summary'    => 'Summary',
    'metrics.containers' => 'Containers',

    // Metrics
    'metrics.title'       => 'Container Metrics',
    'metrics.cpu'         => 'CPU',
    'metrics.memory'      => 'Memory',
    'metrics.network'     => 'Network',
    'metrics.disk'        => 'Disk I/O',
    'metrics.processes'   => 'Processes',
    'metrics.rx'          => 'In',
    'metrics.tx'          => 'Out',
    'metrics.read'        => 'Read',
    'metrics.write'       => 'Write',
    'metrics.of'          => 'of',
    'metrics.running'     => 'running',
    'metrics.stopped'     => 'stopped',
    'metrics.no_socket'   => 'Docker socket unavailable. Mount /var/run/docker.sock into the container.',
    'metrics.no_data'     => 'No containers found.',

    // Unauthorized
    'error.title'       => 'Access denied',
    'error.desc'        => 'Your account is not authorized to access this resource.',
    'error.redirecting' => 'Redirecting in',

    // Footer
    'footer.made_by' => 'Built by',
];
