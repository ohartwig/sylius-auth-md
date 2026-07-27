<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusAuthMd;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Serves /auth.md, rendered from the installed authentication methods.
 */
final class KohSyliusAuthMdBundle extends AbstractBundle
{
    protected string $extensionAlias = 'koh_auth_md';

    /** templates/ and config/ sit at the package root, not under src/. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('template')
                    ->defaultValue('@KohSyliusAuthMd/auth.md.twig')
                ->end()
                ->scalarNode('contact_email')
                    ->defaultNull()
                    ->info('Shown under "Contact" when set.')
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('koh_auth_md.template', (string) ($config['template'] ?? '@KohSyliusAuthMd/auth.md.twig'));
        $builder->setParameter('koh_auth_md.contact_email', $config['contact_email'] ?? null);

        $container->import('../config/services.php');
    }
}
