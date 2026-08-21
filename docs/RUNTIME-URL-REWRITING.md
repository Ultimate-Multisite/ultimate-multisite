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
define('WP_ULTIMO_RUNTIME_URL_FROM', 'https://www.example.com');
define('WP_ULTIMO_RUNTIME_URL_TO', 'https://staging.example.test');
```

## Multiple or mapped domains

Use a source-to-target map when a network has independent mapped domains:

```php
define(
	'WP_ULTIMO_RUNTIME_URL_MAP',
	[
		'https://www.example.com' => 'https://www.staging.example.test',
		'https://shop.example.org' => 'https://shop.staging.example.test',
	]
);
```

Environment variables cannot contain a PHP array, so use a JSON object:

```text
WP_ULTIMO_RUNTIME_URL_MAP={"https://www.example.com":"https://www.staging.example.test","https://shop.example.org":"https://shop.staging.example.test"}
```

## Large domain sets

For hundreds of domains, keep the mappings in a dedicated JSON file instead of
putting the complete array in `wp-config.php`. Only the absolute file path needs
to be configured:

```php
define(
	'WP_ULTIMO_RUNTIME_URL_MAP_FILE',
	'/etc/ultimate-multisite/runtime-url-map.json'
);
```

The path can instead be supplied as an environment variable, which avoids any
`wp-config.php` change:

```text
WP_ULTIMO_RUNTIME_URL_MAP_FILE=/etc/ultimate-multisite/runtime-url-map.json
```

The JSON file is a source-to-target object:

```json
{
  "https://customer-one.example": "https://customer-one.staging.example.test",
  "https://customer-two.example": "https://customer-two.staging.example.test"
}
```

Store the file outside the public web root and make it readable by PHP. It is
loaded during Sunrise, so remote URLs are not supported. Missing, unreadable,
or malformed files fail closed without enabling runtime rewriting. Inline
`WP_ULTIMO_RUNTIME_URL_MAP` entries can be used as overrides; when both sources
contain the same canonical URL, the inline entry wins.

Paths and ports are supported on both sides. Source authorities, including any
port, must match the domain stored for the corresponding site or network. Paths
are case-sensitive. Longer source URLs are processed first so a mapped
subdirectory can override its network root. URLs containing credentials, query
strings, or fragments are rejected as invalid mapping configuration.

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

- Every independently routed site needs its own source-to-target mapping. Two
  root-level sites cannot share one target hostname and path.
- Code that reads the database directly and bypasses WordPress filters may still
  expose canonical URLs.
- Encodings other than the documented plain, JSON-escaped, and URL-encoded forms
  may not be rewritten.
- Cached HTML generated before enabling the mapping must be purged.
- Configure staging email, payment, cron, indexing, object-cache, and access
  protections separately. URL rewriting does not make a production clone safe.
