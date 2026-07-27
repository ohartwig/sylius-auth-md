<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusAgentContracts\Registry\AuthMethodRegistry;
use Koh\SyliusAuthMd\Agent\AuthMdEndpointProvider;
use Koh\SyliusAuthMd\Controller\AuthMdController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(AuthMdController::class)
        ->args([
            service(AuthMethodRegistry::class),
            service(AgentContextFactoryInterface::class),
            service(AgentJsonResponder::class),
            service('twig'),
            param('koh_auth_md.template'),
            param('koh_auth_md.contact_email'),
        ])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AuthMdEndpointProvider::class)
        ->args([service(AuthMethodRegistry::class)])
        ->tag('koh_agent.endpoint');
};
