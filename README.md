<!--
SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
SPDX-License-Identifier: MIT
-->

# koh/sylius-auth-md

Serves `/auth.md` — how an agent registers with and authenticates against your
Sylius shop, in prose an agent can read.

## Install

```bash
composer require koh/sylius-auth-md
```

```php
// config/bundles.php
Koh\SyliusAuthMd\KohSyliusAuthMdBundle::class => ['all' => true],
```

```yaml
# config/routes/koh_agent.yaml
koh_auth_md:
    resource: '@KohSyliusAuthMdBundle/config/routes.php'
```

## Configure

```yaml
# config/packages/koh_auth_md.yaml
koh_auth_md:
    contact_email: 'agents@example.com'   # optional
```

## The document writes itself

Sections come from the installed authentication methods — every service
implementing `AuthMethodDescriptorInterface` from `koh/sylius-agent-contracts`.
Install `koh/sylius-mcp-oauth` and the OAuth flow appears; add
`koh/sylius-web-bot-auth` and its section appears too.

That is the point rather than a convenience: **the same descriptors feed the
`agent_auth` block** of the OAuth metadata. A hand-written `/auth.md` drifts from
the machine-readable version the moment either changes, and an agent that
believes the wrong one fails in a way nobody notices.

With no method registered the route returns **404**. A document listing no way in
reads as *this shop refuses agents* — a stronger and different claim than
*nothing published yet*.

The H1 contains `auth.md`, which is how scanners recognise the document.

## Override the template

```
templates/bundles/KohSyliusAuthMdBundle/auth.md.twig
```

The default renders the heading, links to the machine-readable metadata, one
section per method, a note on rate limits and a pointer at `/robots.txt` for the
Content Signal.

## Decommission

Remove the `composer require`, the bundle entry, the route import and
`config/packages/koh_auth_md.yaml`. No table, no cache pool. The `agent_auth`
metadata is unaffected — it lives in `koh/sylius-mcp-oauth`.

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Advisories
are published as CSAF 2.0 at <https://ole-hartwig.eu/.well-known/csaf/>.

## Licence

MIT — see [LICENSE](LICENSE).
