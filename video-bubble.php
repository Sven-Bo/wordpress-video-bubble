<?php
/**
 * Plugin Name: Video Bubble
 * Plugin URI:  https://pythonandvba.com
 * Description: A lightweight video bubble widget with muted autoplay, contact form, and webhook integration.
 * Version:     1.2.5
 * Author:      PythonAndVBA
 * Author URI:  https://pythonandvba.com
 * License:     GPL v2 or later
 * Text Domain: video-bubble
 * Requires at least: 5.6
 * Tested up to: 6.7
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'VB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VB_VERSION', '1.2.5' );

// ─── Auto-Update from GitHub ─────────────────────────────────────────────────

require VB_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$vb_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Sven-Bo/wordpress-video-bubble/',
    __FILE__,
    'video-bubble'
);
$vb_update_checker->setBranch( 'main' );
$vb_update_checker->getVcsApi()->enableReleaseAssets();

// ─── Admin Menu & Settings ───────────────────────────────────────────────────

add_action( 'admin_menu', 'vb_admin_menu' );
function vb_admin_menu() {
    add_menu_page(
        'Video Bubble',
        'Video Bubble',
        'manage_options',
        'video-bubble',
        'vb_settings_page',
        'dashicons-format-video',
        80
    );
}

add_action( 'admin_init', 'vb_register_settings' );
function vb_register_settings() {
    // General
    register_setting( 'vb_settings', 'vb_reoon_api_key', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_webhook_url', 'esc_url_raw' );
    register_setting( 'vb_settings', 'vb_email_validation', 'absint' );
    register_setting( 'vb_settings', 'vb_reoon_mode', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_email_accepted', array(
        'type'              => 'array',
        'sanitize_callback' => 'vb_sanitize_accepted_statuses',
        'default'           => array( 'valid', 'safe', 'unknown' ),
    ) );

    // Styling
    register_setting( 'vb_settings', 'vb_bubble_size', 'absint' );
    register_setting( 'vb_settings', 'vb_bubble_position', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_bubble_margin_x', 'absint' );
    register_setting( 'vb_settings', 'vb_bubble_margin_y', 'absint' );
    register_setting( 'vb_settings', 'vb_bubble_border_color', 'sanitize_hex_color' );
    register_setting( 'vb_settings', 'vb_overlay_text', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_overlay_font_size', 'absint' );
    register_setting( 'vb_settings', 'vb_overlay_padding_bottom', 'absint' );
    register_setting( 'vb_settings', 'vb_cta_text', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_success_message', 'sanitize_text_field' );
    register_setting( 'vb_settings', 'vb_hide_on_mobile', 'absint' );
    register_setting( 'vb_settings', 'vb_always_show_x', 'absint' );
    register_setting( 'vb_settings', 'vb_scroll_threshold', 'absint' );

    // Videos (stored as JSON array)
    register_setting( 'vb_settings', 'vb_video_rules', array(
        'type'              => 'string',
        'sanitize_callback' => 'vb_sanitize_video_rules',
        'default'           => '[]',
    ) );
}

function vb_sanitize_video_rules( $input ) {
    if ( is_string( $input ) ) {
        $decoded = json_decode( stripslashes( $input ), true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $clean = array();
            foreach ( $decoded as $rule ) {
                $clean[] = array(
                    'pages' => sanitize_text_field( $rule['pages'] ?? '*' ),
                    'video' => esc_url_raw( $rule['video'] ?? '' ),
                    'thumb' => esc_url_raw( $rule['thumb'] ?? '' ),
                );
            }
            return wp_json_encode( $clean );
        }
    }
    return '[]';
}

function vb_sanitize_accepted_statuses( $input ) {
    $allowed = array( 'valid', 'safe', 'unknown', 'role_based', 'disposable', 'invalid', 'spamtrap' );
    if ( ! is_array( $input ) ) {
        return array( 'valid', 'safe', 'unknown' );
    }
    return array_values( array_intersect( $input, $allowed ) );
}

/**
 * Detect if a URL is a Bunny Stream iframe embed.
 */
function vb_is_bunny_embed( $url ) {
    return (bool) preg_match( '#^https?://(?:iframe|player)\.mediadelivery\.net/(?:embed|play)/#i', $url );
}

/**
 * Extract Bunny video GUID and library ID from an embed URL.
 * URL format: https://iframe.mediadelivery.net/play/{libraryId}/{videoId}
 */
function vb_parse_bunny_url( $url ) {
    if ( preg_match( '#https?://((?:iframe|player)\.mediadelivery\.net)/(?:embed|play)/(\d+)/([a-f0-9-]+)#i', $url, $m ) ) {
        return array( 'host' => $m[1], 'library_id' => $m[2], 'video_id' => $m[3] );
    }
    return false;
}

/**
 * Get Bunny Stream thumbnail URL.
 */
function vb_bunny_thumbnail( $url ) {
    $parsed = vb_parse_bunny_url( $url );
    if ( $parsed ) {
        return 'https://vz-' . $parsed['library_id'] . '.b-cdn.net/' . $parsed['video_id'] . '/thumbnail.jpg';
    }
    return '';
}

/**
 * Build a proper Bunny Stream embed URL with params.
 */
function vb_bunny_embed_url( $url, $autoplay = true, $loop = true, $muted = false, $controls = true ) {
    $parsed = vb_parse_bunny_url( $url );
    if ( ! $parsed ) {
        return $url;
    }
    $host = $parsed['host'] ?? 'iframe.mediadelivery.net';
    $base = 'https://' . $host . '/embed/' . $parsed['library_id'] . '/' . $parsed['video_id'];
    $params = array(
        'autoplay'   => $autoplay ? 'true' : 'false',
        'loop'       => $loop ? 'true' : 'false',
        'muted'      => $muted ? 'true' : 'false',
        'preload'    => 'true',
        'responsive' => 'true',
    );
    if ( ! $controls ) {
        $params['showControls'] = 'false';
    }
    return $base . '?' . http_build_query( $params );
}

// ─── Admin Settings Page ─────────────────────────────────────────────────────

function vb_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $bubble_size       = get_option( 'vb_bubble_size', 120 );
    $bubble_position   = get_option( 'vb_bubble_position', 'bottom-right' );
    $bubble_margin_x   = get_option( 'vb_bubble_margin_x', 24 );
    $bubble_margin_y   = get_option( 'vb_bubble_margin_y', 24 );
    $bubble_border     = get_option( 'vb_bubble_border_color', '#6c63ff' );
    $overlay_text      = get_option( 'vb_overlay_text', 'Hi 👋' );
    $overlay_font_size = get_option( 'vb_overlay_font_size', 15 );
    $overlay_pad_btm   = get_option( 'vb_overlay_padding_bottom', 6 );
    $cta_text          = get_option( 'vb_cta_text', 'Contact Me' );
    $success_message   = get_option( 'vb_success_message', "Thanks for reaching out. I'll get back to you within 24h." );
    $hide_on_mobile    = get_option( 'vb_hide_on_mobile', 0 );
    $always_show_x     = get_option( 'vb_always_show_x', 0 );
    $scroll_threshold  = get_option( 'vb_scroll_threshold', 1 );
    $email_validation  = get_option( 'vb_email_validation', 0 );
    $reoon_mode        = get_option( 'vb_reoon_mode', 'quick' );
    $email_accepted    = get_option( 'vb_email_accepted', array( 'valid', 'safe', 'unknown' ) );
    $reoon_key         = get_option( 'vb_reoon_api_key', '' );
    $webhook_url       = get_option( 'vb_webhook_url', '' );
    $video_rules_json  = get_option( 'vb_video_rules', '[]' );
    ?>
    <div class="wrap vb-admin-wrap">
        <h1>Video Bubble Settings</h1>
        <form method="post" action="options.php" id="vb-settings-form">
            <?php settings_fields( 'vb_settings' ); ?>

            <!-- General -->
            <div class="vb-card">
                <h2>General</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="vb_webhook_url">Webhook URL</label></th>
                        <td>
                            <input type="password" id="vb_webhook_url" name="vb_webhook_url"
                                   value="<?php echo esc_attr( $webhook_url ); ?>" class="regular-text"
                                   placeholder="https://hooks.example.com/..." autocomplete="off" />
                            <p class="description">Form submissions will be POSTed here as JSON.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Email Validation -->
            <div class="vb-card">
                <h2>Email Validation</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="vb_email_validation">Enable Validation</label></th>
                        <td>
                            <label><input type="checkbox" id="vb_email_validation" name="vb_email_validation" value="1" <?php checked( $email_validation, 1 ); ?> /> Validate emails via Reoon API before allowing form submission</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_reoon_api_key">Reoon API Key</label></th>
                        <td>
                            <input type="password" id="vb_reoon_api_key" name="vb_reoon_api_key"
                                   value="<?php echo esc_attr( $reoon_key ); ?>" class="regular-text"
                                   placeholder="Your Reoon API key" autocomplete="off" />
                            <p class="description">Required when validation is enabled. <a href="https://emailverifier.reoon.com" target="_blank">Get a key</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_reoon_mode">Verification Mode</label></th>
                        <td>
                            <select id="vb_reoon_mode" name="vb_reoon_mode">
                                <option value="quick" <?php selected( $reoon_mode, 'quick' ); ?>>Quick</option>
                                <option value="power" <?php selected( $reoon_mode, 'power' ); ?>>Power</option>
                            </select>
                            <p class="description">Quick is faster but less accurate. Power does a deeper check (uses more API credits).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Accept These Statuses</th>
                        <td>
                            <p class="description" style="margin-bottom:8px;">Check which Reoon verification results should be accepted as valid:</p>
                            <?php
                            $all_statuses = array(
                                'valid'      => 'Valid — confirmed real mailbox',
                                'safe'       => 'Safe — likely valid but unverifiable',
                                'unknown'    => 'Unknown — could not determine',
                                'role_based' => 'Role-based — e.g. info@, support@',
                                'disposable' => 'Disposable — temporary email service',
                                'spamtrap'   => 'Spamtrap — known spam trap address',
                                'invalid'    => 'Invalid — does not exist',
                            );
                            foreach ( $all_statuses as $key => $label ) :
                            ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="vb_email_accepted[]" value="<?php echo esc_attr( $key ); ?>"
                                        <?php checked( in_array( $key, (array) $email_accepted, true ) ); ?> />
                                    <strong><?php echo esc_html( ucfirst( $key ) ); ?></strong> — <?php echo esc_html( explode( ' — ', $label )[1] ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Appearance -->
            <div class="vb-card">
                <h2>Appearance</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="vb_overlay_text">Overlay Text</label></th>
                        <td>
                            <input type="text" id="vb_overlay_text" name="vb_overlay_text"
                                   value="<?php echo esc_attr( $overlay_text ); ?>" class="regular-text" />
                            <p class="description">Text shown on the collapsed bubble (e.g. "Hi 👋").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_overlay_font_size">Overlay Font Size (px)</label></th>
                        <td>
                            <input type="number" id="vb_overlay_font_size" name="vb_overlay_font_size"
                                   value="<?php echo esc_attr( $overlay_font_size ); ?>" min="10" max="30" step="1" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_overlay_padding_bottom">Overlay Bottom Padding (px)</label></th>
                        <td>
                            <input type="number" id="vb_overlay_padding_bottom" name="vb_overlay_padding_bottom"
                                   value="<?php echo esc_attr( $overlay_pad_btm ); ?>" min="0" max="40" step="1" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_cta_text">CTA Button Text</label></th>
                        <td>
                            <input type="text" id="vb_cta_text" name="vb_cta_text"
                                   value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text" />
                            <p class="description">Text on the call-to-action button in expanded view.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_success_message">Success Message</label></th>
                        <td>
                            <input type="text" id="vb_success_message" name="vb_success_message"
                                   value="<?php echo esc_attr( $success_message ); ?>" class="regular-text" />
                            <p class="description">Shown after the form is submitted successfully.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_scroll_threshold">Show After Scroll (%)</label></th>
                        <td>
                            <input type="number" id="vb_scroll_threshold" name="vb_scroll_threshold"
                                   value="<?php echo esc_attr( $scroll_threshold ); ?>" min="1" max="100" step="1" />
                            <p class="description">Show the bubble only after the visitor scrolls this percentage of the page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_hide_on_mobile">Hide on Mobile</label></th>
                        <td>
                            <label><input type="checkbox" id="vb_hide_on_mobile" name="vb_hide_on_mobile" value="1" <?php checked( $hide_on_mobile, 1 ); ?> /> Hide the video bubble on mobile devices</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_always_show_x">Always Show Dismiss Button</label></th>
                        <td>
                            <label><input type="checkbox" id="vb_always_show_x" name="vb_always_show_x" value="1" <?php checked( $always_show_x, 1 ); ?> /> Always show the &times; button (instead of only on hover)</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_bubble_size">Bubble Size (px)</label></th>
                        <td>
                            <input type="number" id="vb_bubble_size" name="vb_bubble_size"
                                   value="<?php echo esc_attr( $bubble_size ); ?>" min="60" max="200" step="1" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_bubble_position">Position</label></th>
                        <td>
                            <select id="vb_bubble_position" name="vb_bubble_position">
                                <option value="bottom-right" <?php selected( $bubble_position, 'bottom-right' ); ?>>Bottom Right</option>
                                <option value="bottom-left" <?php selected( $bubble_position, 'bottom-left' ); ?>>Bottom Left</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vb_bubble_margin_x">Horizontal Margin (px)</label></th>
                        <td><input type="number" id="vb_bubble_margin_x" name="vb_bubble_margin_x" value="<?php echo esc_attr( $bubble_margin_x ); ?>" min="0" max="200" /></td>
                    </tr>
                    <tr>
                        <th><label for="vb_bubble_margin_y">Vertical Margin (px)</label></th>
                        <td><input type="number" id="vb_bubble_margin_y" name="vb_bubble_margin_y" value="<?php echo esc_attr( $bubble_margin_y ); ?>" min="0" max="200" /></td>
                    </tr>
                    <tr>
                        <th><label for="vb_bubble_border_color">Border Color</label></th>
                        <td><input type="text" id="vb_bubble_border_color" name="vb_bubble_border_color" value="<?php echo esc_attr( $bubble_border ); ?>" class="vb-color-picker" /></td>
                    </tr>
                </table>
            </div>

            <!-- Video Rules -->
            <div class="vb-card">
                <h2>Video Rules</h2>
                <p class="description">Assign a video URL to pages. Select pages from the dropdown or use <strong>All Pages (*)</strong>. For multiple specific pages, select them in the multi-select. The first matching rule wins.</p>
                <p class="description">Supports <strong>Bunny Stream</strong> embed URLs (e.g. <code>https://iframe.mediadelivery.net/play/...</code>) and direct <code>.mp4</code> video URLs. Portrait (9:16) videos work best.</p>
                <div id="vb-video-rules">
                    <!-- JS will populate -->
                </div>
                <button type="button" id="vb-add-rule" class="button button-secondary">+ Add Rule</button>
                <input type="hidden" id="vb_video_rules" name="vb_video_rules" value="<?php echo esc_attr( $video_rules_json ); ?>" />
            </div>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// ─── Admin Assets ────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'vb_admin_assets' );
function vb_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_video-bubble' ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_style( 'vb-admin', VB_PLUGIN_URL . 'assets/css/admin-settings.css', array(), VB_VERSION );
    wp_enqueue_script( 'vb-admin', VB_PLUGIN_URL . 'assets/js/admin-settings.js', array( 'jquery', 'wp-color-picker' ), VB_VERSION, true );

    // Pass WP pages list to admin JS
    $pages = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
    $page_list = array();
    foreach ( $pages as $p ) {
        $page_list[] = array(
            'id'    => $p->ID,
            'title' => $p->post_title,
            'slug'  => $p->post_name,
        );
    }
    wp_localize_script( 'vb-admin', 'vbAdmin', array( 'pages' => $page_list ) );
}

// ─── Frontend Output ─────────────────────────────────────────────────────────

add_action( 'wp_footer', 'vb_render_bubble' );
function vb_render_bubble() {
    if ( is_admin() ) {
        return;
    }

    // Hide on mobile if enabled
    if ( get_option( 'vb_hide_on_mobile', 0 ) && wp_is_mobile() ) {
        return;
    }

    $video_rules = json_decode( get_option( 'vb_video_rules', '[]' ), true );
    if ( empty( $video_rules ) ) {
        return;
    }

    // Determine current page ID
    $current_page_id = get_queried_object_id();

    $matched_video = '';
    foreach ( $video_rules as $rule ) {
        $pages_value = trim( $rule['pages'] ?? '*' );

        // Wildcard matches everything
        if ( $pages_value === '*' ) {
            $matched_video = $rule['video'];
            break;
        }

        // Comma-separated page IDs
        $page_ids = array_map( 'absint', array_filter( explode( ',', $pages_value ) ) );
        if ( in_array( $current_page_id, $page_ids, true ) ) {
            $matched_video = $rule['video'];
            break;
        }
    }

    if ( empty( $matched_video ) ) {
        return;
    }

    // Detect video type
    $is_bunny = vb_is_bunny_embed( $matched_video );

    // Gather settings
    $bubble_size       = absint( get_option( 'vb_bubble_size', 120 ) );
    $bubble_position   = get_option( 'vb_bubble_position', 'bottom-right' );
    $margin_x          = absint( get_option( 'vb_bubble_margin_x', 24 ) );
    $margin_y          = absint( get_option( 'vb_bubble_margin_y', 24 ) );
    $border_color      = sanitize_hex_color( get_option( 'vb_bubble_border_color', '#6c63ff' ) );
    $overlay_text      = esc_html( get_option( 'vb_overlay_text', 'Hi 👋' ) );
    $overlay_font_size = absint( get_option( 'vb_overlay_font_size', 15 ) );
    $overlay_pad_btm   = absint( get_option( 'vb_overlay_padding_bottom', 6 ) );
    $cta_text          = esc_html( get_option( 'vb_cta_text', 'Contact Me' ) );
    $success_message   = esc_html( get_option( 'vb_success_message', "Thanks for reaching out. I'll get back to you within 24h." ) );
    $webhook_url       = esc_url( get_option( 'vb_webhook_url', '' ) );

    // Enqueue frontend assets
    wp_enqueue_style( 'vb-front', VB_PLUGIN_URL . 'assets/css/video-bubble.css', array(), VB_VERSION );
    wp_enqueue_script( 'vb-front', VB_PLUGIN_URL . 'assets/js/video-bubble.js', array(), VB_VERSION, true );

    // Build CSS custom properties
    $pos_x_prop = ( $bubble_position === 'bottom-left' ) ? 'left' : 'right';
    $css_vars   = sprintf(
        '--vb-size:%dpx;--vb-margin-x:%dpx;--vb-margin-y:%dpx;--vb-border-color:%s;--vb-pos-x-prop:%s;--vb-overlay-fs:%dpx;--vb-overlay-pb:%dpx;',
        $bubble_size, $margin_x, $margin_y, $border_color, $pos_x_prop, $overlay_font_size, $overlay_pad_btm
    );

    ?>
    <!-- Video Bubble -->
    <?php
    $always_show_x    = get_option( 'vb_always_show_x', 0 );
    $scroll_threshold = get_option( 'vb_scroll_threshold', 1 );
    $container_classes = array( 'vb-scroll-hidden' );
    if ( $always_show_x ) $container_classes[] = 'vb-x-always';
    ?>
    <div id="vb-container" style="<?php echo esc_attr( $css_vars ); ?>" data-position="<?php echo esc_attr( $bubble_position ); ?>" data-video-type="<?php echo $is_bunny ? 'bunny' : 'direct'; ?>"<?php echo $container_classes ? ' class="' . esc_attr( implode( ' ', $container_classes ) ) . '"' : ''; ?>>

        <!-- Bubble wrapper (positions the X relative to the circle) -->
        <div id="vb-bubble-wrap">
            <button id="vb-bubble-close" aria-label="Dismiss bubble">&times;</button>
            <div id="vb-bubble" aria-label="Open video message">
            <?php if ( $is_bunny ) : ?>
                <iframe id="vb-bubble-iframe"
                        src="<?php echo esc_url( vb_bunny_embed_url( $matched_video, true, true, true, false ) ); ?>"
                        data-src="<?php echo esc_url( vb_bunny_embed_url( $matched_video, true, true, true, false ) ); ?>"
                        allow="autoplay; encrypted-media"
                        loading="lazy"
                        scrolling="no"
                        frameborder="0"></iframe>
            <?php else : ?>
                <video id="vb-bubble-video" src="<?php echo esc_url( $matched_video ); ?>" muted autoplay loop playsinline></video>
            <?php endif; ?>
            <span id="vb-bubble-text"><?php echo $overlay_text; ?></span>
            </div>
        </div>

        <!-- Expanded Panel -->
        <div id="vb-panel" aria-hidden="true">
            <div id="vb-panel-inner">
                <button id="vb-panel-close" aria-label="Close panel">&times;</button>

                <!-- Video View -->
                <div id="vb-panel-video-view">
                    <?php if ( $is_bunny ) : ?>
                        <div id="vb-iframe-wrap">
                            <iframe id="vb-panel-iframe"
                                    src=""
                                    data-src="<?php echo esc_url( vb_bunny_embed_url( $matched_video, true, true, false ) ); ?>"
                                    data-src-muted="<?php echo esc_url( vb_bunny_embed_url( $matched_video, true, true, true ) ); ?>"
                                    loading="lazy"
                                    allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
                                    allowfullscreen="true"></iframe>
                        </div>
                    <?php else : ?>
                        <video id="vb-panel-video" src="<?php echo esc_url( $matched_video ); ?>" playsinline controls></video>
                    <?php endif; ?>
                    <button id="vb-cta-btn"><?php echo $cta_text; ?></button>
                </div>

                <!-- Contact Form View -->
                <div id="vb-panel-form-view" style="display:none;">
                    <button id="vb-form-back" aria-label="Back to video">&larr; Back</button>
                    <h3>Send a Message</h3>
                    <form id="vb-contact-form" novalidate>
                        <div class="vb-field">
                            <label for="vb-field-name">Name</label>
                            <input type="text" id="vb-field-name" name="name" required placeholder="Your name" />
                        </div>
                        <div class="vb-field">
                            <label for="vb-field-email">Email</label>
                            <input type="email" id="vb-field-email" name="email" required placeholder="you@example.com" />
                            <span id="vb-email-status"></span>
                        </div>
                        <div class="vb-field">
                            <label for="vb-field-message">Message</label>
                            <textarea id="vb-field-message" name="message" rows="3" required placeholder="How can I help you?"></textarea>
                        </div>
                        <button type="submit" id="vb-submit-btn">Send</button>
                        <div id="vb-form-feedback"></div>
                    </form>
                </div>

                <!-- Success View -->
                <div id="vb-panel-success-view" style="display:none;">
                    <div class="vb-success-icon">✓</div>
                    <h3>Message Sent!</h3>
                    <p><?php echo $success_message; ?></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        window.vbConfig = {
            webhookUrl: <?php echo wp_json_encode( $webhook_url ); ?>,
            ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
            nonce: <?php echo wp_json_encode( wp_create_nonce( 'vb_nonce' ) ); ?>,
            emailValidation: <?php echo (int) get_option( 'vb_email_validation', 0 ); ?>,
            scrollThreshold: <?php echo max( 1, (int) get_option( 'vb_scroll_threshold', 1 ) ); ?>
        };
    </script>
    <?php
}

// ─── AJAX: Email Verification Proxy ─────────────────────────────────────────

add_action( 'wp_ajax_vb_verify_email', 'vb_verify_email' );
add_action( 'wp_ajax_nopriv_vb_verify_email', 'vb_verify_email' );
function vb_verify_email() {
    check_ajax_referer( 'vb_nonce', 'nonce' );

    // If validation is disabled, always accept
    if ( ! get_option( 'vb_email_validation', 0 ) ) {
        wp_send_json_success( array( 'status' => 'valid', 'message' => '' ) );
    }

    $email = sanitize_email( $_GET['email'] ?? '' );
    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'status' => 'invalid', 'message' => 'Invalid email format.' ) );
    }

    $api_key = get_option( 'vb_reoon_api_key', '' );
    if ( empty( $api_key ) ) {
        // No API key configured — silently allow through
        wp_send_json_success( array( 'status' => 'valid', 'message' => '' ) );
    }

    $mode = get_option( 'vb_reoon_mode', 'quick' );
    $url  = add_query_arg( array(
        'email' => $email,
        'key'   => $api_key,
        'mode'  => $mode,
    ), 'https://emailverifier.reoon.com/api/v1/verify' );

    $response = wp_remote_get( $url, array( 'timeout' => 5 ) );
    if ( is_wp_error( $response ) ) {
        // On failure, silently allow through
        wp_send_json_success( array( 'status' => 'valid', 'message' => '' ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $status = $body['status'] ?? 'unknown';

    $accepted = get_option( 'vb_email_accepted', array( 'valid', 'safe', 'unknown' ) );
    if ( in_array( $status, (array) $accepted, true ) ) {
        wp_send_json_success( array( 'status' => 'valid', 'message' => '' ) );
    } else {
        wp_send_json_error( array( 'status' => $status, 'message' => 'Please use a valid email address.' ) );
    }
}

// ─── AJAX: Webhook Proxy ─────────────────────────────────────────────────────

add_action( 'wp_ajax_vb_submit_form', 'vb_submit_form' );
add_action( 'wp_ajax_nopriv_vb_submit_form', 'vb_submit_form' );
function vb_submit_form() {
    check_ajax_referer( 'vb_nonce', 'nonce' );

    $name    = sanitize_text_field( $_POST['name'] ?? '' );
    $email   = sanitize_email( $_POST['email'] ?? '' );
    $message = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
        wp_send_json_error( array( 'message' => 'All fields are required.' ) );
    }

    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
    }

    $webhook_url = get_option( 'vb_webhook_url', '' );
    if ( empty( $webhook_url ) ) {
        wp_send_json_error( array( 'message' => 'Webhook not configured.' ) );
    }

    $payload = array(
        'name'      => $name,
        'email'     => $email,
        'message'   => $message,
        'page_url'  => sanitize_url( $_POST['page_url'] ?? '' ),
        'timestamp' => current_time( 'c' ),
    );

    $response = wp_remote_post( $webhook_url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( $payload ),
        'timeout' => 10,
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'Failed to send message. Please try again.' ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code >= 200 && $code < 300 ) {
        wp_send_json_success( array( 'message' => 'Message sent successfully!' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Webhook returned an error. Please try again.' ) );
    }
}
