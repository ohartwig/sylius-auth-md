<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusAuthMd\Tests;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusAgentContracts\Model\AuthMethodDescriptor;
use Koh\SyliusAgentContracts\Provider\AuthMethodDescriptorInterface;
use Koh\SyliusAgentContracts\Registry\AuthMethodRegistry;
use Koh\SyliusAuthMd\Agent\AuthMdEndpointProvider;
use Koh\SyliusAuthMd\Controller\AuthMdController;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AuthMdControllerTest extends TestCase
{
    /** The H1 is how a scanner recognises the document at all. */
    public function testHeadingContainsAuthMd(): void
    {
        $body = $this->render();

        self::assertStringStartsWith('# auth.md', $body);
    }

    public function testServedAsMarkdown(): void
    {
        $response = $this->controller()(new Request());

        self::assertSame('text/markdown; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEveryInstalledMethodGetsASection(): void
    {
        $body = $this->render([
            $this->descriptor('oauth2', 'OAuth 2.1', 'Run the code flow.', 'https://shop.example.com/mcp/oauth/register'),
            $this->descriptor('web-bot-auth', 'Web Bot Auth', 'Sign your requests.'),
        ]);

        self::assertStringContainsString('## OAuth 2.1', $body);
        self::assertStringContainsString('## Web Bot Auth', $body);
        self::assertStringContainsString('Run the code flow.', $body);
        self::assertStringContainsString('Sign your requests.', $body);
    }

    public function testRegistrationAndRevocationUrisAreSurfaced(): void
    {
        $body = $this->render([
            $this->descriptor(
                'oauth2',
                'OAuth 2.1',
                'doc',
                'https://shop.example.com/mcp/oauth/register',
                'https://shop.example.com/mcp/oauth/revoke',
            ),
        ]);

        self::assertStringContainsString('https://shop.example.com/mcp/oauth/register', $body);
        self::assertStringContainsString('https://shop.example.com/mcp/oauth/revoke', $body);
    }

    public function testPointsAtTheMachineReadableMetadata(): void
    {
        $body = $this->render();

        self::assertStringContainsString('/.well-known/oauth-protected-resource', $body);
        self::assertStringContainsString('/.well-known/oauth-authorization-server', $body);
        self::assertStringContainsString('/robots.txt', $body);
    }

    /**
     * A document listing no way in reads as "this shop refuses agents", which is
     * a different and stronger claim than "nothing published yet".
     */
    public function testShopWithoutAuthMethodsReturns404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        ($this->controller([]))(new Request());
    }

    public function testEndpointIsAnnouncedOnlyWhenItAnswers(): void
    {
        $context = $this->context();

        $withMethods = new AuthMdEndpointProvider(
            new AuthMethodRegistry([$this->descriptor('oauth2', 'OAuth 2.1', 'doc')]),
        );
        $without = new AuthMdEndpointProvider(new AuthMethodRegistry([]));

        self::assertCount(1, iterator_to_array($withMethods->getAgentEndpoints($context)));
        self::assertCount(0, iterator_to_array($without->getAgentEndpoints($context)));
    }

    /**
     * AUD-068-F01: the fixed text once promised a limit "per credential" on
     * every installation, whatever it enforced. Limits are now stated only by
     * the method that enforces them.
     */
    public function testTheTemplatePromisesNoRateLimitOfItsOwn(): void
    {
        $document = $this->render([$this->descriptor('oauth2', 'OAuth 2.1', 'Run the code flow.')]);

        self::assertStringNotContainsString('per credential', $document);
        self::assertStringContainsString('`429`', $document);
        self::assertStringContainsString('Retry-After', $document);
    }

    public function testAMethodsOwnRateLimitIsPrinted(): void
    {
        $document = $this->render([$this->descriptor('oauth2', 'OAuth 2.1', "### Rate limits\n\n- `/_mcp`: 300 requests per 1 minute per credential.")]);

        self::assertStringContainsString('300 requests per 1 minute per credential', $document);
    }

    public function testBlankRunsAreCollapsed(): void
    {
        self::assertStringNotContainsString("\n\n\n", $this->render());
    }

    /** @param list<AuthMethodDescriptorInterface>|null $descriptors */
    private function render(?array $descriptors = null): string
    {
        return (string) ($this->controller($descriptors))(new Request())->getContent();
    }

    /** @param list<AuthMethodDescriptorInterface>|null $descriptors */
    private function controller(?array $descriptors = null): AuthMdController
    {
        $twig = new Environment(new FilesystemLoader([\dirname(__DIR__).'/templates']));
        $twig->getLoader() instanceof FilesystemLoader
            && $twig->getLoader()->addPath(\dirname(__DIR__).'/templates', 'KohSyliusAuthMd');

        return new AuthMdController(
            new AuthMethodRegistry($descriptors ?? [$this->descriptor('oauth2', 'OAuth 2.1', 'Run the code flow.')]),
            $this->contextFactory(),
            new AgentJsonResponder(),
            $twig,
        );
    }

    private function descriptor(
        string $id,
        string $title,
        string $documentation,
        ?string $registerUri = null,
        ?string $revocationUri = null,
    ): AuthMethodDescriptorInterface {
        $descriptor = new AuthMethodDescriptor(
            id: $id,
            title: $title,
            documentation: $documentation,
            registerUri: $registerUri,
            revocationUri: $revocationUri,
        );

        return new class($descriptor) implements AuthMethodDescriptorInterface {
            public function __construct(private AuthMethodDescriptor $descriptor)
            {
            }

            public function getAuthMethodDescriptor(AgentContext $context): AuthMethodDescriptor
            {
                return $this->descriptor;
            }
        };
    }

    private function contextFactory(): AgentContextFactoryInterface
    {
        $factory = $this->createMock(AgentContextFactoryInterface::class);
        $factory->method('create')->willReturn($this->context());

        return $factory;
    }

    private function context(): AgentContext
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB_DE');
        $channel->method('getName')->willReturn('Tabak Budde');

        return new AgentContext($channel, 'https://shop.example.com', 'de_DE', ['de_DE']);
    }
}
