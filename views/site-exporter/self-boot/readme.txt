================================================================================
ULTIMATE MULTISITE SELF-BOOT BUNDLE
================================================================================

Bundle type : {{BUNDLE_TYPE}}
Plugin      : Ultimate Multisite {{PLUGIN_VERSION}}
Exported    : {{EXPORT_DATE}}
Source URL  : {{SOURCE_URL}}

--------------------------------------------------------------------------------
QUICK START (Browser Wizard — recommended)
--------------------------------------------------------------------------------

1. Extract the entire ZIP onto the web root of your new server.

       unzip wu-site-export-*.zip -d /var/www/html/

   The ZIP already contains a wordpress/ subdirectory that the installer will
   use.  If you prefer to use existing WordPress files, place them in a
   "wordpress/" folder next to index.php.

2. Point your domain's DNS A record at the new server's IP address.

3. Open the site URL in a browser:

       http://example.com/

4. Follow the 5-step wizard:
   - Step 1: Database credentials (host, name, user, password, prefix)
   - Step 2: Site URL, admin username/password, admin email
   - Step 3: WordPress core is downloaded from wordpress.org (or detected if
             bundled)
   - Step 4: WordPress is installed
   - Step 5: The Ultimate Multisite plugin is activated and the bundle is
             imported

5. On success the wizard deletes itself (index.php + readme.txt) and redirects
   you to /wp-admin/.  An audit log is saved as wu-bootstrap-complete.log.

--------------------------------------------------------------------------------
REQUIREMENTS
--------------------------------------------------------------------------------

- PHP 8.2 or later
- MySQL 5.7+ or MariaDB 10.2+
- Outbound HTTPS to wordpress.org (unless core was bundled)
- ZipArchive PHP extension
- ~200 MB free disk space for WordPress + bundle data

--------------------------------------------------------------------------------
MANUAL IMPORT VIA WP-CLI (alternative)
--------------------------------------------------------------------------------

If you prefer to run the import from the command line without a browser:

  # 1. Install WordPress (replace values with your own)
  wp core download --path=wordpress/
  wp config create --dbname=mydb --dbuser=myuser --dbpass=mypassword \
      --dbhost=localhost --path=wordpress/
  wp core install \
      --url=http://example.com \
      --title="My Site" \
      --admin_user=admin \
      --admin_password=secret \
      --admin_email=admin@example.com \
      --path=wordpress/

  # For NETWORK bundles only: convert to multisite
  wp core multisite-convert --path=wordpress/

  # 2. Install and activate the plugin
  wp plugin install ultimate-multisite --activate --path=wordpress/
  # OR from the bundled copy:
  cp -r wp-ultimate-plugin/ wordpress/wp-content/plugins/ultimate-multisite/
  wp plugin activate ultimate-multisite --path=wordpress/

  # 3. Import the bundle
  #    Single-site:
  wp mu-migration import all wu-site-export-*.zip --path=wordpress/

  #    Network bundle:
  wp db import network.sql --path=wordpress/
  for zip in sites/*.zip; do
    wp mu-migration import all "$zip" --path=wordpress/
  done

--------------------------------------------------------------------------------
TROUBLESHOOTING
--------------------------------------------------------------------------------

Problem: "Could not connect to the database"
  - Double-check DB host, name, user, and password.
  - Ensure the DB user has CREATE, INSERT, UPDATE, DELETE, DROP, and ALTER
    privileges on the target database.
  - If using a socket path instead of a host, enter it in the "Database Host"
    field (e.g. /var/run/mysqld/mysqld.sock).

Problem: "Could not download WordPress from wordpress.org"
  - The target server may not have outbound HTTPS access.
  - Download WordPress manually and extract it into a "wordpress/" folder next
    to index.php, then run the wizard again — the installer will detect it.
  - Alternatively, export again from your source site with the
    "Include WordPress core for offline self-boot" option enabled.

Problem: "PHP Version Incompatible"
  - Upgrade PHP on the target server to match the source version or higher.

Problem: "A WordPress installation was detected"
  - The installer found a wp-config.php in the same directory.  It cannot run
    over an existing installation.
  - To import into an existing Ultimate Multisite network, use:
    Ultimate Multisite > Tools > Import Site

Problem: exec() / proc_open() disabled
  - The browser wizard will fall back to a PHP-native installation path for
    steps that don't require WP-CLI.
  - The import step requires WP-CLI.  If exec/proc_open are permanently
    disabled, use the WP-CLI manual steps above from an SSH session.

Problem: Import fails partway through (network bundle)
  - The installer tracks completed steps in .wu-bootstrap-state.json.
  - Refresh the page or re-open the URL to resume from the failed step.
  - Inspect wu-bootstrap-complete.log for a summary of what ran.

Problem: "No site export ZIP found in the bundle directory"
  - Ensure the export ZIP was extracted correctly.  All files should be in the
    same directory as index.php (the web root), not in a subdirectory.

Problem: Uploads missing after import
  - Check that wp-content/uploads/ was included in the export.  Re-export with
    "Include Uploads" enabled, then re-import.

--------------------------------------------------------------------------------
SECURITY NOTES
--------------------------------------------------------------------------------

- index.php deletes itself on successful completion so it cannot be re-run.
- If the wizard fails midway, delete index.php manually once import is complete.
- .wu-bootstrap-state.json and .wu-bootstrap-token contain sensitive data
  (DB credentials, CSRF token).  Both are prefixed with "." so they are hidden
  on most systems and are deleted on successful completion.
- Always run this installer over HTTPS in production, or at minimum ensure the
  server is not publicly accessible until the wizard is complete.

--------------------------------------------------------------------------------
WHERE TO GET HELP
--------------------------------------------------------------------------------

Documentation : https://ultimatemultisite.com/docs
Community     : https://community.ultimatemultisite.com
Issues        : https://github.com/Ultimate-Multisite/ultimate-multisite/issues

================================================================================
