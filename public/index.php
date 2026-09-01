<?php

use App\Kernel;

// Fuseau métier unique de l’application : les horaires de planning et de pointage
// sont exprimés dans le fuseau local de l’entreprise.
date_default_timezone_set('Europe/Paris');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
