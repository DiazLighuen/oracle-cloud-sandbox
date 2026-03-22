<?php
declare(strict_types=1);

return [
    // Landing
    'landing.subtitle'       => 'Un ecosistema de microservicios con autenticación centralizada. Explorá OAuth, JWT stateless y arquitectura hexagonal corriendo en contenedores.',
    'landing.cta_google'     => 'Entrar con Google',
    'landing.instances'      => '// instancias activas',

    // Services
    'service.auth.name' => 'Auth Gateway',
    'service.auth.desc' => 'Login centralizado via Google OAuth 2.0. Un único punto de identidad para todos los servicios.',
    'service.jwt.name'  => 'JWT Stateless',
    'service.jwt.desc'  => 'Tokens firmados HS256 válidos entre contenedores. Sin sesiones, sin estado compartido.',
    'service.hex.name'  => 'Hexagonal PHP',
    'service.hex.desc'  => 'Domain, Application e Infrastructure desacoplados. Ports & Adapters para máxima testabilidad.',

    // Dashboard / user
    'dashboard.authenticated' => '✓ Autenticado',
    'dashboard.email'         => 'Email',
    'dashboard.user_id'       => 'ID de usuario',
    'dashboard.logout'        => 'Cerrar sesión',

    // Nav
    'nav.dashboard' => 'Dashboard',
    'nav.users'     => 'Usuarios',
    'nav.profile'   => 'Perfil',

    // Users view
    'users.title'        => 'Usuarios Autorizados',
    'users.admin'        => 'Admin',
    'users.user'         => 'Usuario',
    'users.joined'       => 'Registro',
    'users.empty'        => 'No hay usuarios autorizados.',
    'users.add'          => 'Agregar usuario',
    'users.add_title'    => 'Nuevo usuario',
    'users.email'        => 'Email',
    'users.name'         => 'Nombre (opcional)',
    'users.make_admin'   => 'Dar admin',
    'users.revoke_admin' => 'Quitar admin',
    'users.delete'       => 'Eliminar',
    'users.cancel'       => 'Cancelar',
    'users.save'         => 'Guardar',
    'users.confirm_delete' => '¿Eliminar este usuario?',

    // Metrics summary
    'metrics.summary'    => 'Resumen',
    'metrics.containers' => 'Contenedores',

    // Metrics
    'metrics.title'       => 'Métricas de Contenedores',
    'metrics.cpu'         => 'CPU',
    'metrics.memory'      => 'Memoria',
    'metrics.network'     => 'Red',
    'metrics.disk'        => 'Disco I/O',
    'metrics.processes'   => 'Procesos',
    'metrics.rx'          => 'Entrada',
    'metrics.tx'          => 'Salida',
    'metrics.read'        => 'Lectura',
    'metrics.write'       => 'Escritura',
    'metrics.of'          => 'de',
    'metrics.running'     => 'corriendo',
    'metrics.stopped'     => 'detenido',
    'metrics.no_socket'   => 'Docker socket no disponible. Montá /var/run/docker.sock en el contenedor.',
    'metrics.no_data'     => 'No se encontraron contenedores.',

    // Unauthorized
    'error.title'       => 'Acceso denegado',
    'error.desc'        => 'Tu cuenta no está autorizada para acceder a este recurso.',
    'error.redirecting' => 'Redirigiendo en',

    // Footer
    'footer.made_by' => 'Hecho por',
];
