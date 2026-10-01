<?php
/**
 * Plugin Name:       URL Remap
 * Plugin URI:        https://bytevorous.com/plugins/url-remap
 * Description:       Rewrites one host string to another in the rendered front-end HTML. Runtime only - does not modify the database. From/To are configurable under Settings - URL Remap.
 * Version:           0.5.1
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Daniel Ellis
 * Author URI:        https://bytevorous.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       url-remap
 *
 * NOTE: After activating this plugin or changing the From/To values, FLUSH /
 * PURGE the page cache (Flywheel cache, and any CDN/full-page cache). Pages
 * cached before the change keep serving their old host until purged, so the
 * rewrite will look like it isn't working on already-cached pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('URL_REMAP_OPT', 'url_remap_settings');

/**
 * Default From/To. Used until the user saves the settings form.
 */
function url_remap_defaults() {
    return array(
        'from'    => 'www.wcrp-cmip.org',
        'to'      => 'wcrp-cmip.org',
        'exclude' => '/wp-content, /wp-includes, /favicon.ico',
    );
}

/**
 * Current settings, merged over defaults so missing keys never break.
 */
function url_remap_get() {
    $saved = get_option(URL_REMAP_OPT, array());
    if (!is_array($saved)) {
        $saved = array();
    }
    return array_merge(url_remap_defaults(), $saved);
}


// -----------------------------------------------------------------------------
// Front-end rewrite
// -----------------------------------------------------------------------------

/**
 * Replace occurrences of From with To in a blob of HTML.
 *
 * As long as From is the fully qualified host (e.g. "www.wcrp-cmip.org")
 * the result cannot re-introduce the stripped prefix.
 *
 * Exclude is a comma-separated list of path prefixes (e.g. /wp-content,
 * /wp-includes). The host is left UNCHANGED where it is immediately
 * followed by any of those paths, so asset URLs keep their original host,
 * while page links (/, /cmip-phases/, etc.) are still remapped.
 *
 * Reminder: this only rewrites the HTML WordPress renders on a page load.
 * A full-page cache (Flywheel / CDN) serves a stored copy, so purge the
 * cache after activating or changing settings for the change to show on
 * already-cached pages.
 */
function url_remap_rewrite($html) {
    $opt     = url_remap_get();
    $from    = $opt['from'];
    $to      = $opt['to'];
    $exclude = $opt['exclude'];

    // Nothing configured, or a no-op pair: leave the HTML untouched.
    if ($from === '' || $from === $to) {
        return $html;
    }

    // Cheap early exit when the From host isn't present at all.
    if (strpos($html, $from) === false) {
        return $html;
    }

    // Parse the exclude list (comma-separated path prefixes).
    $tokens = array_filter(array_map('trim', explode(',', $exclude)), 'strlen');

    // No exclusions: simple, fast global replace.
    if (empty($tokens)) {
        return str_replace($from, $to, $html);
    }

    // Rewrite From everywhere EXCEPT where it is immediately followed by one
    // of the excluded prefixes (negative lookahead over their alternation).
    $alt = implode('|', array_map(
        function ($t) { return preg_quote($t, '~'); },
        $tokens
    ));

    $pattern = '~' . preg_quote($from, '~') . '(?!' . $alt . ')~';

    $result = preg_replace($pattern, $to, $html);

    // On regex failure preg_replace returns null; fall back to the
    // untouched HTML rather than dropping the page.
    return $result === null ? $html : $result;
}

/**
 * Start output buffering as early as possible on the front end,
 * so the rewrite covers the whole page. Never touches the admin area.
 */
function url_remap_start_buffer() {
    if (is_admin()) {
        return;
    }

    ob_start('url_remap_rewrite');
}
add_action('template_redirect', 'url_remap_start_buffer', 1);


// -----------------------------------------------------------------------------
// Settings page  (Settings -> URL Remap)
// -----------------------------------------------------------------------------

function url_remap_register_settings() {
    register_setting(
        'url_remap_group',
        URL_REMAP_OPT,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'url_remap_sanitize',
            'default'           => url_remap_defaults(),
        )
    );
}
add_action('admin_init', 'url_remap_register_settings');

/**
 * Trim and strip tags from the two fields. We keep them as plain strings;
 * they are substituted literally into HTML output, so no URL parsing here.
 */
function url_remap_sanitize($input) {
    $clean = url_remap_defaults();

    if (is_array($input)) {
        if (isset($input['from'])) {
            $clean['from'] = trim(wp_strip_all_tags($input['from']));
        }
        if (isset($input['to'])) {
            $clean['to'] = trim(wp_strip_all_tags($input['to']));
        }
        if (isset($input['exclude'])) {
            $clean['exclude'] = trim(wp_strip_all_tags($input['exclude']));
        }
    }

    return $clean;
}

function url_remap_menu() {
    add_options_page(
        'URL Remap',       // page title
        'URL Remap',       // menu label
        'manage_options',  // capability
        'url-remap',       // slug
        'url_remap_page'   // render callback
    );
}
add_action('admin_menu', 'url_remap_menu');

/**
 * "Settings" shortcut on the Plugins screen, next to Activate/Deactivate.
 */
function url_remap_action_links($links) {
    $settings = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=url-remap')),
        esc_html__('Settings', 'url-remap')
    );
    array_unshift($links, $settings);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'url_remap_action_links');

function url_remap_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $opt = url_remap_get();
    ?>
    <div class="wrap">
        <h1>URL Remap</h1>
        <p>
            Rewrites the <strong>From</strong> host to the <strong>To</strong> host
            in the rendered front-end HTML on every page load. This does not change
            the database &mdash; deactivating the plugin reverts the behaviour instantly.
        </p>

        <div class="notice notice-warning inline" style="margin: 12px 0;">
            <p>
                <strong>Flush the cache after saving.</strong> This rewrite runs when a
                page is rendered, but a full-page cache (Flywheel cache and any CDN)
                serves a stored copy. After activating the plugin or changing the
                values below, <strong>purge the page cache</strong> &mdash; otherwise
                already-cached pages keep showing the old host.
            </p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('url_remap_group'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="url_remap_from">From</label>
                    </th>
                    <td>
                        <input
                            name="<?php echo esc_attr(URL_REMAP_OPT); ?>[from]"
                            id="url_remap_from"
                            type="text"
                            class="regular-text code"
                            value="<?php echo esc_attr($opt['from']); ?>"
                            placeholder="www.wcrp-cmip.org"
                        />
                        <p class="description">Host string to replace, e.g. <code>www.wcrp-cmip.org</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="url_remap_to">To</label>
                    </th>
                    <td>
                        <input
                            name="<?php echo esc_attr(URL_REMAP_OPT); ?>[to]"
                            id="url_remap_to"
                            type="text"
                            class="regular-text code"
                            value="<?php echo esc_attr($opt['to']); ?>"
                            placeholder="wcrp-cmip.org"
                        />
                        <p class="description">Replacement host, e.g. <code>wcrp-cmip.org</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="url_remap_exclude">Exclude</label>
                    </th>
                    <td>
                        <input
                            name="<?php echo esc_attr(URL_REMAP_OPT); ?>[exclude]"
                            id="url_remap_exclude"
                            type="text"
                            class="regular-text code"
                            value="<?php echo esc_attr($opt['exclude']); ?>"
                            placeholder="/wp-content, /wp-includes"
                        />
                        <p class="description">Comma-separated path prefixes. The host is left unchanged where it is immediately followed by one of these, so asset URLs keep their original host, e.g. <code>/wp-content, /wp-includes</code>. Leave blank to rewrite every occurrence.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save changes'); ?>
        </form>
    </div>
    <?php
}
