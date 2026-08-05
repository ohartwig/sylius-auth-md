<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusAuthMd\Tests;

use Koh\SyliusAuthMd\Controller\AuthMdController;
use Koh\SyliusAuthMd\KohSyliusAuthMdBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * /auth.md is a public document: anything the bundle is configured to put in it
 * is readable by every agent that finds the shop. The contact address in
 * particular is only there when an operator asks for it, so the default and the
 * wiring that carries it into the controller both matter.
 */
#[CoversClass(KohSyliusAuthMdBundle::class)]
final class BundleConfigurationTest extends TestCase
{
    public function testNoContactAddressIsPublishedUnlessConfigured(): void
    {
        // Defaulting this to anything non-null would publish an address the
        // operator never chose to expose.
        self::assertNull($this->parameters()['koh_auth_md.contact_email']);
    }

    public function testAConfiguredContactAddressIsExposedAsAParameter(): void
    {
        $parameters = $this->parameters(['contact_email' => 'agents@shop.example']);

        self::assertSame('agents@shop.example', $parameters['koh_auth_md.contact_email']);
    }

    public function testTheTemplateDefaultsToTheBundledOne(): void
    {
        self::assertSame('@KohSyliusAuthMd/auth.md.twig', $this->parameters()['koh_auth_md.template']);
    }

    public function testTheTemplateDefaultMatchesTheControllerFallback(): void
    {
        // The template name is defaulted twice: in the config tree here, and as
        // a constructor default on AuthMdController. If they drift, the document
        // rendered by the container and the one rendered by a directly
        // constructed controller are different files.
        $controllerDefault = (new \ReflectionClass(AuthMdController::class))
            ->getConstructor()
            ?->getParameters()[4]
            ->getDefaultValue();

        self::assertSame($this->parameters()['koh_auth_md.template'], $controllerDefault);
    }

    public function testTheDefaultTemplateActuallyExists(): void
    {
        // A default pointing at a missing file turns every request into a Twig
        // error rather than a document.
        $bundle = new KohSyliusAuthMdBundle();

        self::assertFileExists($bundle->getPath() . '/templates/auth.md.twig');
    }

    public function testAnOverriddenTemplateIsHonoured(): void
    {
        $parameters = $this->parameters(['template' => '@App/custom-auth.md.twig']);

        self::assertSame('@App/custom-auth.md.twig', $parameters['koh_auth_md.template']);
    }

    public function testTheBundlePathIsThePackageRootSoTemplatesResolve(): void
    {
        // templates/ and config/ sit beside src/, not inside it; returning the
        // src directory would break the @KohSyliusAuthMd namespace.
        $bundle = new KohSyliusAuthMdBundle();

        self::assertDirectoryExists($bundle->getPath() . '/templates');
        self::assertDirectoryExists($bundle->getPath() . '/config');
        self::assertFileExists($bundle->getPath() . '/composer.json');
    }

    public function testTheExtensionAliasIsStable(): void
    {
        // The alias is the key operators write in config/packages; changing it
        // silently discards their configuration.
        self::assertSame('koh_auth_md', $this->extension()->getAlias());
    }

    public function testLoadingTheExtensionRegistersTheControllerService(): void
    {
        $builder = $this->containerBuilder();
        $this->extension()->load([[]], $builder);

        self::assertTrue($builder->hasDefinition(AuthMdController::class));
    }

    // --- helpers -------------------------------------------------------------

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function parameters(array $config = []): array
    {
        $builder = $this->containerBuilder();
        $this->extension()->load([$config], $builder);

        return $builder->getParameterBag()->all();
    }

    private function extension(): ExtensionInterface
    {
        $extension = (new KohSyliusAuthMdBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function containerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => true,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => \dirname(__DIR__),
        ]));
    }
}
