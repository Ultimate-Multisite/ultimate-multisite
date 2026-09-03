# Runtime URL rewriting proof of concept

Ultimate Multisite can present canonical production data through a different
environment URL without changing the database. The feature is opt-in and runs
only when the incoming request matches a configured target URL.

`SUNRISE` must be enabled because multisite has to resolve the target hostname
before normal plugins load. Define constants in `wp-config.php`, or provide
environment variables to PHP, so the configuration exists before sunrise runs.

## One network domain

When `DOMAIN_CURRENT_SITE` contains the canonical hostname, only the target URL
is required:

```php
define('WP_ULTIMO_RUNTIME_URL', 'https://staging.example.test');
```

The same value can be provided as an environment variable:

```text
WP_ULTIMO_RUNTIME_URL=https://staging.example.test
```

To declare both sides explicitly:

```php
define('WP_ULTIMO_RUNTIME_URL_FROM', 'https://example.com');
define('WP_ULTIMO_RUNTIME_URL_TO', 'https://staging.example.com');
```

## Domain suffix mappings

A configured domain is a suffix rule. One root mapping automatically applies to
the root and every subdomain while preserving all leading labels:

```php
define('WP_ULTIMO_RUNTIME_URL_FROM', 'https://example.com');
define('WP_ULTIMO_RUNTIME_URL_TO', 'https://staging.example.com');
```

That single rule produces mappings such as:

| Canonical URL | Environment URL |
|---|---|
| `https://example.com` | `https://staging.example.com` |
| `https://customer-one.example.com` | `https://customer-one.staging.example.com` |
| `https://site.example.com` | `https://site.staging.example.com` |
| `https://deep.site.example.com` | `https://deep.site.staging.example.com` |

No per-site configuration is required. Use a source-to-target map only when a
network contains multiple unrelated root domains:

```php
define(
	'WP_ULTIMO_RUNTIME_URL_MAP',
	[
		'https://example.com' => 'https://staging.example.com',
		'https://example.org' => 'https://staging.example.org',
	]
);
```

Environment variables cannot contain a PHP array, so use a JSON object:

```text
WP_ULTIMO_RUNTIME_URL_MAP={"https://example.com":"https://staging.example.com","https://example.org":"https://staging.example.org"}
```

More specific child rules override a parent suffix rule. For example, an
explicit `vip.example.com` rule is selected before the broader `example.com`
rule. The child rule also applies to its own subdomains.

Paths and ports are supported on both sides. Source authorities, including any
preserved subdomain labels and port, must match the domain stored for the
corresponding site or network. Paths are case-sensitive. Longer source URLs are
processed first so a mapped child domain or subdirectory can override its
parent rule. URLs containing credentials, query strings, or fragments are
rejected as invalid mapping configuration.

## Runtime behavior

For a request to a configured target URL, the proof of concept:

1. Translates the hostname and path back to the canonical address during
   `get_site_by_path()` and `get_network_by_path()`.
2. Rewrites core-generated site, network, content, plugin, theme, attachment,
   canonical, login, feed, and redirect URLs.
3. Rewrites rendered post, excerpt, widget, block, embed, email, upload, image
   source-set, attachment, and REST response values.
4. Handles ordinary, protocol-relative, JSON-escaped, and URL-encoded absolute
   URLs.

Bare domains are not replaced. This avoids changing email addresses and prose.
Database values are never updated.

Additional integrations can register their own filters at sunrise time:

```php
add_action(
	'wu_runtime_url_rewriter_register_filters',
	function ($rewrite) {
		add_filter('my_plugin_generated_html', $rewrite);
	}
);
```

## Proof-of-concept limitations

- Each unrelated root domain needs one source-to-target suffix rule.
- Code that reads the database directly and bypasses WordPress filters may still
  expose canonical URLs.
- Encodings other than the documented plain, JSON-escaped, and URL-encoded forms
  may not be rewritten.
- Cached HTML generated before enabling the mapping must be purged.
- Configure staging email, payment, cron, indexing, object-cache, and access
  protections separately. URL rewriting does not make a production clone safe.
