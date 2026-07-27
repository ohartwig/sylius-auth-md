<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusAuthMd\Controller\AuthMdController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // Site root, no locale prefix — scanners fetch exactly /auth.md.
    $routes->add('koh_auth_md', '/auth.md')
        ->controller(AuthMdController::class)
        ->methods(['GET', 'HEAD']);
};
