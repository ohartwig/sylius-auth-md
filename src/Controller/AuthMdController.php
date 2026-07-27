<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusAuthMd\Controller;

use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusAgentContracts\Registry\AuthMethodRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Serves /auth.md.
 *
 * The document is assembled from the installed authentication methods rather
 * than written by hand, so it cannot describe a flow the shop does not offer —
 * or omit one it does. The same descriptors feed the `agent_auth` block of the
 * OAuth metadata, which is what keeps the prose and the machine-readable
 * version in agreement.
 */
final class AuthMdController
{
    public function __construct(
        private readonly AuthMethodRegistry $authMethods,
        private readonly AgentContextFactoryInterface $contextFactory,
        private readonly AgentJsonResponder $responder,
        private readonly Environment $twig,
        private readonly string $template = '@KohSyliusAuthMd/auth.md.twig',
        private readonly ?string $contactEmail = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $context = $this->contextFactory->create();
        $methods = $this->authMethods->all($context);

        // Nothing to describe. A document that lists no way in is worse than a
        // 404: it reads as "this shop refuses agents" rather than "this shop
        // has not published a way in yet".
        if ([] === $methods) {
            throw new NotFoundHttpException('No authentication methods are registered.');
        }

        $body = $this->twig->render($this->template, [
            'methods' => $methods,
            'context' => $context,
            'channelName' => $context->channel->getName() ?? 'this shop',
            'contactEmail' => $this->contactEmail,
        ]);

        return $this->responder->respondWithBody(
            $this->normalize($body),
            'text/markdown',
            request: $request,
        );
    }

    /** Twig leaves a gap wherever a block was skipped; collapse the runs. */
    private function normalize(string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $markdown = (string) preg_replace('/\n{3,}/', "\n\n", $markdown);

        return trim($markdown)."\n";
    }
}
