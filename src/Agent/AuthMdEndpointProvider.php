<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusAuthMd\Agent;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Model\AgentEndpoint;
use Koh\SyliusAgentContracts\Provider\AgentEndpointProviderInterface;
use Koh\SyliusAgentContracts\Registry\AuthMethodRegistry;

/**
 * Announces /auth.md — but only while there is an authentication method to
 * describe, since the route 404s otherwise.
 */
final class AuthMdEndpointProvider implements AgentEndpointProviderInterface
{
    public function __construct(private readonly AuthMethodRegistry $authMethods)
    {
    }

    public function getAgentEndpoints(AgentContext $context): iterable
    {
        if ([] === $this->authMethods->all($context)) {
            return;
        }

        yield new AgentEndpoint(
            path: '/auth.md',
            title: 'Agent registration',
            contentType: 'text/markdown',
            rel: 'service-doc',
            description: 'How an agent registers and authenticates with this shop.',
        );
    }
}
