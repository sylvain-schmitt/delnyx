<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

// Autorise Symfony à faire confiance aux headers envoyés par Traefik (proxy)
// Remplace ['0.0.0.0/0'] par l'IP de ton proxy en production pour plus de sécurité
Request::setTrustedProxies(
    ['0.0.0.0/0'], 
    Request::HEADER_X_FORWARDED_ALL
);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
