=== URL Remap ===
Author: bytevorous.com
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 0.5.1

Rewrites one host string to another in the rendered front-end HTML
(default: www.wcrp-cmip.org -> wcrp-cmip.org). Runtime only - it does
not modify the database.

== Description ==

On every front-end page load, the plugin buffers the rendered HTML and
replaces the configured "From" host with the "To" host. This covers
server-rendered links, menus, navbars, canonical/OG tags and image
sources. It does NOT touch the database, so deactivating reverts the
behaviour instantly.

From/To are set under Settings -> URL Remap (defaults to
www.wcrp-cmip.org -> wcrp-cmip.org).

== Important ==

* Flush / purge the page cache (Flywheel cache and any CDN) after
  activating the plugin or changing the From/To values. Pages cached
  before the change keep serving the old host until purged.
* Links rendered by client-side JavaScript are not rewritten, because
  that HTML never passes through the PHP output buffer.
* Keep From fully qualified (include the "www.") so the replacement can
  never re-introduce the stripped prefix (avoids "www.www." doubling).

== Install ==

Upload url-remap.zip via Plugins -> Add New -> Upload Plugin,
then activate. Build the zip with `make` (see Makefile).

== Changelog ==

= 0.5.1 =
* Add /favicon.ico to the default Exclude list.

= 0.5.0 =
* Exclude is now a comma-separated list of path prefixes (default
  /wp-content, /wp-includes); host left unchanged where followed by any.

= 0.4.0 =
* Add "Exclude" setting: skip the host rewrite where it is immediately
  followed by a given path (default /wp-content), so asset URLs keep
  their original host while page links are still remapped.

= 0.3.0 =
* Add "Settings" shortcut link on the Plugins screen.
* Remove default-host example from the plugin description.

= 0.2.1 =
* Renamed plugin to URL Remap.
* Add cache-flush note to header, settings page and source.

= 0.2.0 =
* Add Settings page with configurable From/To.

= 0.1.0 =
* Initial output-buffer host rewrite.
