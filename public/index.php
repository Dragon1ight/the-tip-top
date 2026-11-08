<?php

use App\Kernel;

// Sous Apache, les variables du conteneur ne sont visibles que par getenv() : elles priment sur les fichiers .env.
foreach (getenv() as $key => $value) {
    $_SERVER[$key] = $_ENV[$key] = $value;
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
