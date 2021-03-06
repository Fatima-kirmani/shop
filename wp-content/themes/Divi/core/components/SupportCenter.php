<?php
/**
 * Elegant Themes Support Center adds a new item to the [Divi/Divi Builder/Extra] Admin Menu.
 *
 * System Status
 * We will make note of system settings that could potentially cause problems. An extended view (displaying all
 * settings we check, not just those with problematic results) can be toggled, with an option to copy this report
 * to the clipboard so it can be pasted in a support ticket.
 *
 * Remote Access
 * If this is enabled, Elegant Themes will have limited access to the users site (see new user role below). Further,
 * a second toggle appears for the user that will allow them to enable "Admin Access" which has no restrictions
 * (Only specific ET Support staff will be able to request that the user enables this). Admin access can be disabled
 * at anytime, but is disabled whenever the normal remote access is disabled manually or by timeout. Time left will
 * be indicated as well as a way to manually turn off. This will include a description of what this is allowing and
 * enabling. A link for initiating a chat https://www.elegantthemes.com/members-area/help/ will also be available.
 *
 * Divi Documentation & Help
 * This will consist of common help videos, articles, and a link to full documentation. This is not meant to be a
 * full service documentation center as we don't want to duplicate something we already have elsewhere. It's mainly
 * a launch off point. However, if @sofyansitorus builds an easy way to access a searchable list of docs titles in
 * his Quick Actions feature, we could add a search bar that would function the same way that searching for docs
 * works in Quick Actions (creating a most robust search system is TBD).
 *
 * Divi Safe Mode
 * A quick and easy way for users and support to quickly disable plugins and scripts to see if Divi is the cause of
 * an issue. This call to action disables active plugins, custom css, child themes, scripts in integrations tab,
 * static css, and combination/minification of CSS and JS. When enabling this, the user will be presented with a
 * list of plugins and scripts that will be affected (disabled). Likewise, when disabling it, we will indicate which
 * items will be re-enabled. This will basically just put things back the way they were. Site wide (not including
 * the VB), there will be a floating indicator in the upper right or left corner of the website that will indicate
 * that Safe Mode is enabled and will contain a link that takes you to the Support Page to disabled it.
 *
 * Logs
 *
 * @package ET\Core\SupportCenter
 * @author  Elegant Themes <http://www.elegantthemes.com>
 * @license GNU General Public License v2 <http://www.gnu.org/licenses/gpl-2.0.html>
 */

// Quick exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ET_Support_Center Provides admin tools to assist with troubleshooting and fixing issues in our products.
 *
 * @since 3.20
 */
class ET_Support_Center {
	/**
	 * Catch whether the ET_DEBUG flag is set.
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $DEBUG_ET_SUPPORT_CENTER = false;

	/**
	 * Identifier for the parent theme or plugin activating the Support Center.
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $parent = '';

	/**
	 * "Nice name" for the parent theme or plugin activating the Support Center.
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $parent_nicename = '';

	/**
	 * Whether the Support Center was activated through a `plugin` or a `theme`.
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $child_of = '';

	/**
	 * Identifier for the parent theme or plugin activating the Support Center.
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $local_path;

	/**
	 * Support User options
	 *
	 * @since 3.20
	 *
	 * @type array
	 */
	protected $support_user_options;

	/**
	 * Support User account name
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $support_user_account_name = 'elegant_themes_support';

	/**
	 * Support options name in the database
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $support_user_options_name = 'et_support_options';

	/**
	 * Name of the cron job we use to auto-delete the Support User account
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $support_user_cron_name = 'et_cron_delete_support_account';

	/**
	 * Expiration time to auto-delete the Support User account via cron
	 *
	 * @since 3.20
	 *
	 * @type string
	 */
	protected $support_user_expiration_time = '+4 days';

	/**
	 * Collection of plugins that we will NOT disable when Safe Mode is activated.
	 *
	 * @since 3.20
	 *
	 * @type array
	 */
	protected $safe_mode_plugins_whitelist = array(
		'ari-adminer/ari-adminer.php', // ARI Adminer
		'etdev/etdev.php', // ET Development Workspace
		'divi-builder/divi-builder.php', // Divi Builder Plugin
		'query-monitor/query-monitor.php', // Query Monitor
		'woocommerce/woocommerce.php', // WooCommerce
	);

	/**
	 * Core functionality of the class
	 *
	 * @since 3.20
	 *
	 * @param string $parent Identifier for the parent theme or plugin activating the Support Center.
	 */
	public function __construct( $parent = '' ) {
		// Verbose logging: only log if `wp-config.php` has defined `ET_DEBUG='support_center'`
		$this->DEBUG_ET_SUPPORT_CENTER = defined( 'ET_DEBUG' ) && 'support_center' === ET_DEBUG;

		// Set the identifier for the parent theme or plugin activating the Support Center.
		$this->parent = $parent;

		// Get `et_support_options` settings & set $this->support_user_options
		$this->support_user_get_options();

		// Set the Site ID data via Elegant Themes API & token
		$this->maybe_set_site_id();

		// Set the plugins whitelist for Safe Mode
		$this->set_safe_mode_plugins_whitelist();
	}

	/**
	 * WordPress action & filter setup
	 *
	 * @since 3.20
	 */
	public function init() {
		update_option( 'et_support_center_installed', 'true' );

		// Establish which theme or plugin has loaded the Support Center
		$this->set_parent_properties();

		// When initialized, deactivate conflicting plugins
		$this->deactivate_conflicting_plugins();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		// SC scripts are only used in FE for the "Turn Off Divi Safe Mode" floating button.
		if ( et_core_is_safe_mode_active() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		}

		// Make sure that our Support Account's roles are set up
		add_filter( 'add_et_builder_role_options', array( $this, 'support_user_add_role_options' ), 10, 1 );

		// On Multisite installs, grant `unfiltered_html` capabilities to the Support User
		add_filter( 'map_meta_cap', array( $this, 'support_user_map_meta_cap' ), 1, 3 );

		// Add CSS class name(s) to the Support Center page's body tag
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class_name' ) );

		// Add a link to the Support Center in the admin menu
		add_filter( 'admin_menu', array( $this, 'add_admin_menu_item' ) );

		// When Safe Mode is enabled, add floating frontend indicator
		add_action( 'admin_footer', array( $this, 'maybe_add_safe_mode_indicator' ) );
		add_action( 'wp_footer', array( $this, 'maybe_add_safe_mode_indicator' ) );

		// Delete our Support User settings on deactivation (whether parent is a plugin or theme)
		if ( 'plugin' === $this->child_of ) {
			register_deactivation_hook( __FILE__, array( $this, 'support_user_delete_account' ) );
			register_deactivation_hook( __FILE__, array( $this, 'unlist_support_center' ) );
		}
		if ( 'theme' === $this->child_of ) {
			add_action( 'switch_theme', array( $this, 'support_user_delete_account' ) );
			add_action( 'switch_theme', array( $this, 'unlist_support_center' ) );
		}

		// Automatically delete our Support User when the time runs out
		add_action( $this->support_user_cron_name, array( $this, 'support_user_cron_maybe_delete_account' ) );
		add_action( 'init', array( $this, 'support_user_maybe_delete_expired_account' ) );
		add_action( 'admin_init', array( $this, 'support_user_maybe_delete_expired_account' ) );

		// Remove KSES filters for ET Support User
		add_action( 'admin_init', array( $this, 'support_user_kses_remove_filters' ) );

		// Update Support User settings via AJAX
		add_action( 'wp_ajax_et_support_user_update', array( $this, 'support_user_update_via_ajax' ) );

		// Toggle Safe Mode via AJAX
		add_action( 'wp_ajax_et_safe_mode_update', array( $this, 'safe_mode_update_via_ajax' ) );

		// Safe Mode: Block restricted actions when Safe Mode active
		add_action( 'admin_footer', array( $this, 'render_safe_mode_block_restricted' ) );

		// Safe Mode: Temporarily disable Child Theme
		add_filter( 'stylesheet', array( $this, 'maybe_disable_child_theme' ) );
		add_filter( 'template', array( $this, 'maybe_disable_child_theme' ) );

		// Safe Mode: Temporarily disable Custom CSS
		add_action( 'init', array( $this, 'maybe_disable_custom_css' ) );

		// Safe Mode: Remove "Additional CSS" from WP Head action hook
		if ( et_core_is_safe_mode_active() ) {
			remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
		}
	}

	/**
	 * Set variables that change depending on whether a theme or a plugin activated the Support Center
	 *
	 * @since 3.20
	 */
	public function set_parent_properties() {
		$core_path = _et_core_normalize_path( trailingslashit( dirname( __FILE__ ) ) );
		$theme_dir = _et_core_normalize_path( realpath( get_template_directory() ) );

		if ( 0 === strpos( $core_path, $theme_dir ) ) {
			$this->child_of   = 'theme';
			$this->local_path = get_template_directory_uri() . '/core/';
		} else {
			$this->child_of   = 'plugin';
			$this->local_path = plugin_dir_url( __FILE__ );
		}

		switch ( $this->parent ) {
			case 'extra_theme':
				$this->parent_nicename = 'Extra';
				break;
			case 'divi_theme':
			case 'divi_builder_plugin':
			default:
				$this->parent_nicename = 'Divi';
		}
	}

	/**
	 * Prevent any possible conflicts with the Elegant Themes Support plugin
	 *
	 * @since 3.20
	 */
	public function deactivate_conflicting_plugins() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Load WP user management functions
		if ( is_multisite() ) {
			require_once( ABSPATH . 'wp-admin/includes/ms.php' );
		} else {
			require_once( ABSPATH . 'wp-admin/includes/user.php' );
		}

		// Verify that WP user management functions are available
		$can_delete_user = false;
		if ( is_multisite() && function_exists( 'wpmu_delete_user' ) ) {
			$can_delete_user = true;
		}
		if ( ! is_multisite() && function_exists( 'wp_delete_user' ) ) {
			$can_delete_user = true;
		}

		if ( $can_delete_user ) {
			deactivate_plugins( '/elegant-themes-support/elegant-themes-support.php' );
		} else {
			et_error( 'Support Center: Unable to deactivate the ET Support Plugin.' );
		}
	}

	/**
	 * Add Safe Mode Autoloader Must-Use Plugin
	 *
	 * @since 3.20
	 */
	public function maybe_add_mu_autoloader() {
		$file_name = '/SupportCenterMUAutoloader.php';
		$file_path = dirname( __FILE__ );

		// Exit if the `mu-plugins` directory doesn't exist & we're unable to create it
		if ( ! et_()->ensure_directory_exists( WPMU_PLUGIN_DIR ) ) {
			et_error( 'Support Center Safe Mode: mu-plugin folder not found.' );

			return;
		}

		$pathname_to   = WPMU_PLUGIN_DIR . $file_name;
		$pathname_from = $file_path . $file_name;

		// Exit if we can't find the mu-plugins autoloader
		if ( ! file_exists( $pathname_from ) ) {
			et_error( 'Support Center Safe Mode: mu-plugin autoloader not found.' );

			return;
		}

		// Try to create a new subdirectory for our mu-plugins; if it fails, log an error message
		$pathname_plugins_from = dirname( __FILE__ ) . '/mu-plugins';
		$pathname_plugins_to   = WPMU_PLUGIN_DIR . '/et-safe-mode';
		if ( ! et_()->ensure_directory_exists( $pathname_plugins_to ) ) {
			et_error( 'Support Center Safe Mode: mu-plugins subfolder not found.' );

			return;
		}

		// Try to copy the mu-plugins; if any fail, log an error message
		if ( $mu_plugins = glob( dirname( __FILE__ ) . '/mu-plugins/*.php' ) ) {
			foreach ( $mu_plugins as $plugin ) {
				$new_file_path = str_replace( $pathname_plugins_from, $pathname_plugins_to, $plugin );

				// Skip if this particular mu-plugin hasn't changed
				if ( file_exists( $new_file_path ) && md5_file( $new_file_path ) === md5_file( $plugin ) ) {
					continue;
				}

				$copy_file = @copy( $plugin, $new_file_path );

				if ( ! $this->DEBUG_ET_SUPPORT_CENTER ) {
					continue;
				}

				if ( $copy_file ) {
					et_error( 'Support Center Safe Mode: mu-plugin [' . $plugin . '] installed.' );
				} else {
					et_error( 'Support Center Safe Mode: mu-plugin [' . $plugin . '] failed installation. ' );
				}
			}
		}

		// Finally, try to copy the autoloader file; if it fails, log an error message

		// Skip if the mu-plugins autoloader hasn't changed
		if ( file_exists( $pathname_to ) && md5_file( $pathname_to ) === md5_file( $pathname_from ) ) {
			return;
		}

		$copy_file = @copy( $pathname_from, $pathname_to );

		if ( $this->DEBUG_ET_SUPPORT_CENTER ) {
			if ( $copy_file ) {
				et_error( 'Support Center Safe Mode: mu-plugin installed.' );
			} else {
				et_error( 'Support Center Safe Mode: mu-plugin failed installation. ' );
			}
		}
	}

	public function maybe_remove_mu_autoloader() {
		@unlink( WPMU_PLUGIN_DIR . '/SupportCenterMUAutoloader.php' );
		@unlink( WPMU_PLUGIN_DIR . '/et-safe-mode/SupportCenterSafeModeDisablePlugins.php' );
		et_()->remove_empty_directories( WPMU_PLUGIN_DIR . '/et-safe-mode' );
	}

	/**
	 * Update the Site ID data via Elegant Themes API
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function maybe_set_site_id() {
		$site_id = get_option( 'et_support_site_id' );

		if ( ! empty( $site_id ) ) {
			return;
		}

		$site_id = '';

		$send_to_api = array(
			'action' => 'get_site_id',
		);

		$settings = array(
			'timeout' => 30,
			'body'    => $send_to_api,
		);

		$request = wp_remote_post( 'https://www.elegantthemes.com/api/token.php', $settings );

		if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {
			$response = unserialize( wp_remote_retrieve_body( $request ) );

			if ( ! empty( $response['site_id'] ) ) {
				$site_id = esc_attr( $response['site_id'] );
			}
		}

		update_option( 'et_support_site_id', $site_id );
	}

	/**
	 * Safe Mode temporarily deactivates all plugins *except* those in the whitelist option set here
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function set_safe_mode_plugins_whitelist() {
		update_option( 'et_safe_mode_plugins_whitelist', $this->safe_mode_plugins_whitelist );
	}

	/**
	 * Add Support Center menu item (but only if it's enabled for current user)
	 *
	 * When initialized we were given an identifier for the plugin or theme doing the initializing. We're going to use
	 * that identifier here to insert the Support Center menu item in the correct location within the WP Admin Menu.
	 *
	 * @since 3.20
	 */
	public function add_admin_menu_item() {
		$parent_menu_slug = '';

		switch ( $this->parent ) {
			case 'extra_theme':
				$parent_menu_slug = 'et_extra_options';
				break;
			case 'divi_theme':
			case 'divi_builder_plugin':
				$parent_menu_slug = 'et_divi_options';
				break;
		}

		if ( et_pb_is_allowed( 'et_support_center' ) ) {
			add_submenu_page(
				$parent_menu_slug,
				esc_html__( 'Support Center', 'et-core' ),
				esc_html__( 'Support Center', 'et-core' ),
				'manage_options',
				'et_support_center',
				array( $this, 'add_support_center' )
			);
		}
	}

	/**
	 * Add class name to Support Center page
	 *
	 * @since 3.20
	 *
	 * @param string $admin_classes Current class names for the body tag.
	 *
	 * @return string
	 */
	public function add_admin_body_class_name( $admin_classes = '' ) {
		$classes   = explode( ' ', $admin_classes );
		$classes[] = 'et-admin-page';

		if ( et_core_is_safe_mode_active() ) {
			$classes[] = 'et-safe-mode-active';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Support Center admin page JS
	 *
	 * @since 3.20
	 *
	 * @param $hook string Unique identifier for WP admin page.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts_styles( $hook ) {
		// Load only on `_et_support_center` pages
		if ( strpos( $hook, '_et_support_center' ) ) {
			// ePanel CSS
			wp_enqueue_style( 'epanel-theme-style',
				$this->local_path . '../epanel/css/panel.css',
				array(),
				ET_CORE_VERSION
			);

			// Support Center CSS
			wp_enqueue_style( 'et-support-center',
				$this->local_path . 'admin/css/support-center.css',
				array(),
				ET_CORE_VERSION
			);

			// Support Center uses ePanel controls, so include the necessary scripts
			if ( function_exists( 'et_epanel_admin_js' ) ) {
				et_epanel_admin_js();
			}
		}
	}

	/**
	 * Support Center frontend CSS/JS
	 *
	 * @since 3.20
	 *
	 * @param $hook string Unique identifier for WP admin page.
	 *
	 * @return void
	 */
	public function enqueue_scripts_styles( $hook ) {
		// We only need to add this for authenticated users on the frontend
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Support Center JS
		wp_enqueue_script( 'et-support-center',
			$this->local_path . 'admin/js/support-center.js',
			array( 'jquery', 'underscore' ),
			ET_CORE_VERSION,
			true
		);

		$support_center_nonce = wp_create_nonce( 'support_center' );

		wp_localize_script( 'et-support-center', 'etSupportCenter', array(
			'ajaxLoaderImg'    => esc_url( get_template_directory_uri() . '/core/admin/images/ajax-loader.gif' ),
			'ajaxURL'          => admin_url( 'admin-ajax.php' ),
			'siteURL'          => get_site_url(),
			'supportCenterURL' => get_admin_url( null, 'admin.php?page=et_support_center#et_card_safe_mode' ),
			'safeModeCTA'      => esc_html__( sprintf( 'Turn Off %1$s Safe Mode', $this->parent_nicename ), 'et-core' ),
			'nonce'            => $support_center_nonce,
		) );
	}

	/**
	 * Divi Support Center :: Card
	 *
	 * Take an array of attributes and build a WP Card block for display on the Divi Support Center page.
	 *
	 * @since 3.20
	 *
	 * @param array $attrs
	 *
	 * @return string
	 */
	protected function add_support_center_card( $attrs = array( 'title' => '', 'content' => '' ) ) {

		$card_classes = array(
			'card',
		);

		if ( array_key_exists( 'additional_classes', $attrs ) ) {
			$card_classes = array_merge( $card_classes, $attrs['additional_classes'] );
		}

		$card = PHP_EOL . '<div class="' . esc_attr( implode( ' ', $card_classes ) ) . '">' .
				PHP_EOL . "\t" . '<h2>' . esc_html( $attrs['title'] ) . '</h2>' .
				PHP_EOL . "\t" . '<div class="main">' . et_core_intentionally_unescaped( $attrs['content'], 'html' ) . '</div>' .
				PHP_EOL . '</div>';

		return $card;
	}

	/**
	 * Prepare the "Divi Documentation & Help" video player block
	 *
	 * @since 3.20
	 *
	 * @param bool $formatted Return either a formatted HTML block (true) or an array (false)
	 *
	 * @return array|string
	 */
	protected function get_documentation_video_player( $formatted = true ) {

		/**
		 * Define the videos list
		 */
		$documentation_videos = array(
			array(
				'name'       => esc_attr__( 'Getting Started With The Divi Builder', 'et-core' ),
				'youtube_id' => 'T-Oe01_J62c',
			),
			array(
				'name'       => esc_attr__( 'Using Premade Layout Packs', 'et-core' ),
				'youtube_id' => '9eqXcrLcnoc',
			),
			array(
				'name'       => esc_attr__( 'The Divi Library', 'et-core' ),
				'youtube_id' => 'boNZZ0MYU0E',
			),
		);

		// If we just want the array (not a formatted HTML block), return that now
		if ( false === $formatted ) {
			return $documentation_videos;
		}

		$videos_list_html = '';
		$playlist         = array();

		foreach ( $documentation_videos as $key => $video ) {
			$extra = '';
			if ( 0 === $key ) {
				$extra = ' class="active"';
			}
			$videos_list_html .= sprintf( '<li %1$s data-ytid="%2$s">%3$s%4$s</li>',
				$extra,
				esc_attr( $video['youtube_id'] ),
				'<span class="dashicons dashicons-arrow-right"></span>',
				et_core_intentionally_unescaped( $video['name'], 'fixed_string' )
			);
			$playlist[]       = et_core_intentionally_unescaped( $video['youtube_id'], 'fixed_string' );
		}

		$html = sprintf( '<div class="et_docs_videos">'
						 . '<div class="wrapper"><div id="et_documentation_player" data-playlist="%2$s"></div></div>'
						 . '<ul class="et_documentation_videos_list">%2$s</ul>'
						 . '</div>',
			esc_attr( implode( ',', $playlist ) ),
			$videos_list_html
		);

		return $html;
	}

	/**
	 * Prepare the "Divi Documentation & Help" articles list
	 *
	 * @since 3.20
	 *
	 * @param bool $formatted Return either a formatted HTML block (true) or an array (false)
	 *
	 * @return array|string
	 */
	protected function get_documentation_articles_list( $formatted = true ) {

		$articles_list_html = '';


		switch ( $this->parent ) {
			case 'extra_theme':
				$articles = array(
					array(
						'title' => esc_attr__( 'Getting Started With Extra', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/extra/overview-extra/',
					),
					array(
						'title' => esc_attr__( 'Setting Up The Extra Theme Options', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/extra/theme-options-extra/',
					),
					array(
						'title' => esc_attr__( 'The Extra Category Builder', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/extra/category-builder/',
					),
					array(
						'title' => esc_attr__( 'Getting Started With The Divi Builder', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/visual-builder/',
					),
					array(
						'title' => esc_attr__( 'How To Update The Extra Theme', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/update-divi/',
					),
					array(
						'title' => esc_attr__( 'An Overview Of All Divi Modules', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/modules/',
					),
					array(
						'title' => esc_attr__( 'Getting Started With Layout Packs', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/premade-layouts/',
					),
					array(
						'title' => esc_attr__( 'Customizing Your Header And Navigation', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/extra/theme-customizer/',
					),
				);
				break;
			case 'divi_theme':
				$articles = array(
					array(
						'title' => esc_attr__( 'Getting Started With The Divi Builder', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/visual-builder/',
					),
					array(
						'title' => esc_attr__( 'How To Update The Divi Theme', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/update-divi/',
					),
					array(
						'title' => esc_attr__( 'An Overview Of All Divi Modules', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/modules/',
					),
					array(
						'title' => esc_attr__( 'Using The Divi Library', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/divi-library/',
					),
					array(
						'title' => esc_attr__( 'Setting Up The Divi Theme Options', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/theme-options/',
					),
					array(
						'title' => esc_attr__( 'Getting Started With Layout Packs', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/premade-layouts/',
					),
					array(
						'title' => esc_attr__( 'Customizing Your Header And Navigation', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/customizer-header/',
					),
					array(
						'title' => esc_attr__( 'Divi For Developers', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/developers/',
					),
				);
				break;
			case 'divi_builder_plugin':
				$articles = array(
					array(
						'title' => esc_attr__( 'Getting Started With The Divi Builder', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/visual-builder/',
					),
					array(
						'title' => esc_attr__( 'How To Update The Divi Builder', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi-builder/update-divi-builder/',
					),
					array(
						'title' => esc_attr__( 'An Overview Of All Divi Modules', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/modules/',
					),
					array(
						'title' => esc_attr__( 'Using The Divi Library', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/divi-library/',
					),
					array(
						'title' => esc_attr__( 'Selling Products With Divi And WooCommerce', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/ecommerce-divi/',
					),
					array(
						'title' => esc_attr__( 'Getting Started With Layout Packs', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/premade-layouts/',
					),
					array(
						'title' => esc_attr__( 'Importing And Exporting Divi Layouts', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/divi/library-import/',
					),
					array(
						'title' => esc_attr__( 'Divi For Developers', 'et-core' ),
						'url'   => 'https://www.elegantthemes.com/documentation/developers/',
					),
				);
				break;
			default:
				$articles = array();
		}

		// If we just want the array (not a formatted HTML block), return that now
		if ( false === $formatted ) {
			return $articles;
		}

		foreach ( $articles as $key => $article ) {
			$articles_list_html .= sprintf(
				'<li class="et-support-center-article"><a href="%1$s" target="_blank">%2$s</a></li>',
				esc_url( $article['url'] ),
				et_core_intentionally_unescaped( $article['title'], 'fixed_string' )
			);
		}

		$html = sprintf(
			'<div class="et_docs_articles"><ul class="et_documentation_articles_list">%1$s</ul></div>',
			$articles_list_html
		);

		return $html;
	}

	/**
	 * Look for Elegant Themes Support Account
	 *
	 * @since 3.20
	 *
	 * @return WP_User|false WP_User object on success, false on failure.
	 */
	public function get_et_support_user() {
		return get_user_by( 'slug', $this->support_user_account_name );
	}

	/**
	 * Look for saved Elegant Themes Username & API Key
	 *
	 * @since 3.20
	 *
	 * @return array|false license credentials on success, false on failure.
	 */
	public function get_et_license() {

		/** @var array License credentials [username|api_key] */
		if ( ! $et_license = get_site_option( 'et_automatic_updates_options' ) ) {
			$et_license = get_option( 'et_automatic_updates_options', array() );
		}

		if ( ! array_key_exists( 'username', $et_license ) || empty( $et_license['username'] ) ) {
			return false;
		}

		if ( ! array_key_exists( 'api_key', $et_license ) || empty( $et_license['api_key'] ) ) {
			return false;
		}

		return $et_license;
	}

	/**
	 * Try to load the WP debug log. If found, return the last [$lines_to_return] lines of the file and the filesize.
	 *
	 * @since 3.20
	 *
	 * @param int $lines_to_return Number of lines to read and return from the end of the wp_debug.log file.
	 *
	 * @return array
	 */
	protected function get_wp_debug_log( $lines_to_return = 10 ) {
		$log = array(
			'entries' => '',
			'size'    => 0,
		);

		// Early exit: internal PHP function `file_get_contents()` appears to be on lockdown
		if ( ! function_exists( 'file_get_contents' ) ) {
			$log['error'] = esc_attr__( 'Divi Support Center :: WordPress debug log cannot be read.', 'et-core' );
			et_error( $log['error'] );

			return $log;
		}

		// Early exit: WP_DEBUG_LOG isn't defined in wp-config.php (or it's defined, but it's empty)
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			$log['error'] = esc_attr__( 'Divi Support Center :: WordPress debug.log is not configured.', 'et-core' );
			et_error( $log['error'] );

			return $log;
		}

		/**
		 * WordPress 5.1 introduces the option to define a custom path for the WP_DEBUG_LOG file.
		 *
		 * @since 3.20
		 *
		 * @see wp_debug_mode()
		 */
		if ( in_array( strtolower( (string) WP_DEBUG_LOG ), array( 'true', '1' ), true ) ) {
			$wp_debug_log_path = realpath( WP_CONTENT_DIR . '/debug.log' );
		} else if ( is_string( WP_DEBUG_LOG ) ) {
			$wp_debug_log_path = realpath( WP_DEBUG_LOG );
		}

		// Early exit: `debug.log` doesn't exist or otherwise can't be read
		if ( ! isset( $wp_debug_log_path ) || ! file_exists( $wp_debug_log_path ) || ! is_readable( $wp_debug_log_path ) ) {
			$log['error'] = esc_attr__( 'Divi Support Center :: WordPress debug log cannot be found.', 'et-core' );
			et_error( $log['error'] );

			return $log;
		}

		// Load the debug.log file
		$file = new SplFileObject( $wp_debug_log_path );

		// Get the filesize of debug.log
		$log['size'] = $this->get_size_in_shorthand( 0 + $file->getSize() );

		// If $lines_to_return is a positive integer, fetch the last [$lines_to_return] lines of the log file
		$lines_to_return = (int) $lines_to_return;
		if ( $lines_to_return > 0 ) {
			$file->seek( PHP_INT_MAX );
			$total_lines = $file->key();
			// If the file is smaller than the number of lines requested, return the entire file
			$reader         = new LimitIterator( $file, max( 0, $total_lines - $lines_to_return ) );
			$log['entries'] = '';
			foreach ( $reader as $line ) {
				$log['entries'] .= $line;
			}
		}
		// Unload the SplFileObject
		$file = null;

		return $log;
	}

	/**
	 * When a predefined system setting is passed to this function, it will return the observed value.
	 *
	 * @since 3.20
	 *
	 * @param bool $formatted Whether to return a formatted report or just the data array
	 * @param string $format Return the report as either a `div` or `plain` text (if $formatted = true)
	 *
	 * @return array|string
	 */
	protected function system_diagnostics_generate_report( $formatted = true, $format = 'plain' ) {
		/** @var array Collection of system settings to run diagnostic checks on. */
		$system_diagnostics_settings = array(
			array(
				'name'           => esc_attr__( 'File Permissions', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'size',
				'pass_minus_one' => false,
				'pass_zero'      => false,
				'minimum'        => null,
				'recommended'    => 755,
				'actual'         => (int) substr( sprintf( '%o', fileperms( WP_CONTENT_DIR ) ), -4 ),
				'help_text'      => et_core_intentionally_unescaped( __( 'We recommend that the wp-content directory on your server be writable by WordPress in order to ensure the full functionality of Divi Builder themes and plugins.', 'et-core' ), 'html' ),
				'learn_more'     => 'https://wordpress.org/support/article/changing-file-permissions/',
			),
			array(
				'name'           => esc_attr__( 'PHP Version', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'version',
				'pass_minus_one' => false,
				'pass_zero'      => false,
				'minimum'        => null,
				'recommended'    => '7.2 or higher',
				'actual'         => (float) phpversion(),
				'help_text'      => et_core_intentionally_unescaped( __( 'We recommend using the latest stable version of PHP. This will not only ensure compatibility with Divi, but it will also greatly speed up your website leading to less memory and CPU related issues.', 'et-core' ), 'html' ),
				'learn_more'     => 'http://php.net/releases/',
			),
			array(
				'name'           => esc_attr__( 'memory_limit', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'size',
				'pass_minus_one' => true,
				'pass_zero'      => false,
				'minimum'        => null,
				'recommended'    => '128M',
				'actual'         => ini_get( 'memory_limit' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'By default, memory limits set by your host or by WordPress may be too low. This will lead to applications crashing as PHP reaches the artificial limit. You can adjust your memory limit within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance. You may also need to define a memory limited in <a href="%2$s" target=_blank">wp-config.php</a>.', 'et-core' ), 'http://php.net/manual/en/ini.core.php#ini.memory-limit', 'https://codex.wordpress.org/Editing_wp-config.php' ) ),
				'learn_more'     => 'http://php.net/manual/en/ini.core.php#ini.memory-limit',
			),
			array(
				'name'           => esc_attr__( 'post_max_size', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'size',
				'pass_minus_one' => false,
				'pass_zero'      => true,
				'minimum'        => null,
				'recommended'    => '64M',
				'actual'         => ini_get( 'post_max_size' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'Post Max Size limits how large a page or file can be on your website. If your page is larger than the limit set in PHP, it will fail to load. Post sizes can become quite large when using the Divi Builder, so it is important to increase this limit. It also affects file size upload/download, which can prevent large layouts from being imported into the builder. You can adjust your max post size within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance.', 'et_core' ), 'http://php.net/manual/en/ini.core.php#ini.post-max-size' ) ),
				'learn_more'     => 'http://php.net/manual/en/ini.core.php#ini.post-max-size',
			),
			array(
				'name'           => esc_attr__( 'max_execution_time', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'seconds',
				'pass_minus_one' => false,
				'pass_zero'      => true,
				'minimum'        => null,
				'recommended'    => '180',
				'actual'         => ini_get( 'max_execution_time' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'Max Execution Time affects how long a page is allowed to load before it times out. If the limit is too low, you may not be able to import large layouts and files into the builder. You can adjust your max execution time within your <a href="%1$s">php.ini file</a>, or by contacting your host for assistance.', 'et-core' ), 'http://php.net/manual/en/info.configuration.php#ini.max-execution-time' ) ),
				'learn_more'     => 'http://php.net/manual/en/info.configuration.php#ini.max-execution-time',
			),
			array(
				'name'           => esc_attr__( 'upload_max_filesize', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'size',
				'pass_minus_one' => false,
				'pass_zero'      => false,
				'minimum'        => null,
				'recommended'    => '64M',
				'actual'         => ini_get( 'upload_max_filesize' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'Upload Max File Size determines that maximum file size that you are allowed to upload to your server. If the limit is too low, you may not be able to import large collections of layouts into the Divi Library. You can adjust your max file size within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance.', 'et-core' ), 'http://php.net/manual/en/ini.core.php#ini.upload-max-filesize' ) ),
				'learn_more'     => 'http://php.net/manual/en/ini.core.php#ini.upload-max-filesize',
			),
			array(
				'name'           => esc_attr__( 'max_input_time', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'seconds',
				'pass_minus_one' => true,
				'pass_zero'      => true,
				'minimum'        => null,
				'recommended'    => '180',
				'actual'         => ini_get( 'max_input_time' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'This sets the maximum time in seconds a script is allowed to parse input data. If the limit is too low, the Divi Builder may time out before it is allowed to load. You can adjust your max input time within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance.', 'et-core' ), 'http://php.net/manual/en/info.configuration.php#ini.max-input-time' ) ),
				'learn_more'     => 'http://php.net/manual/en/info.configuration.php#ini.max-input-time',
			),
			array(
				'name'           => esc_attr__( 'max_input_vars', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'size',
				'pass_minus_one' => false,
				'pass_zero'      => false,
				'minimum'        => null,
				'recommended'    => '3000',
				'actual'         => ini_get( 'max_input_vars' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'This setting affects how many input variables may be accepted. If the limit is too low, it may prevent the Divi Builder from loading. You can adjust your max input variables within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance.', 'et-core' ), 'http://php.net/manual/en/info.configuration.php#ini.max-input-vars' ) ),
				'learn_more'     => 'http://php.net/manual/en/info.configuration.php#ini.max-input-vars',
			),
			array(
				'name'           => esc_attr__( 'display_errors', 'et-core' ),
				'environment'    => 'server',
				'type'           => 'string',
				'pass_minus_one' => null,
				'pass_zero'      => null,
				'pass_exact'     => true,
				'minimum'        => null,
				'recommended'    => '0',
				'actual'         => ini_get( 'display_errors' ),
				'help_text'      => et_get_safe_localization( sprintf( __( 'This setting determines whether or not errors should be printed as part of the page output. This is a feature to support your site\'s development and should never be used on production sites. You can edit this setting within your <a href="%1$s" target="_blank">php.ini file</a>, or by contacting your host for assistance.', 'et-core' ), 'http://php.net/manual/en/info.configuration.php#ini.display-errors' ) ),
				'learn_more'     => 'http://php.net/manual/en/info.configuration.php#ini.display-errors',
			),
		);

		/** @var string Formatted report. */
		$report = '';

		// pass/fail Should be one of pass|minimal|fail|unknown. Defaults to 'unknown'.
		foreach ( $system_diagnostics_settings as $i => $scan ) {
			/**
			 * 'pass_fail': four-step process to set its value:
			 * - begin with `unknown` state;
			 * - if recommended value exists, change to `fail`;
			 * - if minimum value exists, compare against it & change to `minimal` if it passes;
			 * - compare against recommended value & change to `pass` if it passes.
			 */
			$system_diagnostics_settings[ $i ]['pass_fail'] = 'unknown';
			if ( ! is_null( $scan['recommended'] ) ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'fail';
			}

			if ( ! is_null( $scan['minimum'] ) && $this->value_is_at_least( $scan['minimum'], $scan['actual'], $scan['type'] ) ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'minimal';
			}

			if ( empty( $scan['pass_exact'] ) && ! is_null( $scan['recommended'] ) && $this->value_is_at_least( $scan['recommended'], $scan['actual'], $scan['type'] ) ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'pass';
			}

			if ( $scan['pass_minus_one'] && -1 === (int) $scan['actual'] ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'pass';
			}

			if ( $scan['pass_zero'] && 0 === (int) $scan['actual'] ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'pass';
			}

			if ( ! empty( $scan['pass_exact'] ) && $scan['recommended'] === $scan['actual'] ) {
				$system_diagnostics_settings[ $i ]['pass_fail'] = 'pass';
			}

			/**
			 * Build messaging for minimum required values
			 */
			$message_minimum = '';
			if ( ! is_null( $scan['minimum'] ) && 'fail' === $system_diagnostics_settings[ $i ]['pass_fail'] ) {
				$message_minimum = sprintf(
					esc_html__( 'This fails to meet our minimum required value (%1$s). ', 'et-core' ),
					$scan['minimum']
				);
			}
			if ( ! is_null( $scan['minimum'] ) && 'minimal' === $system_diagnostics_settings[ $i ]['pass_fail'] ) {
				$message_minimum = sprintf(
					esc_html__( 'This meets our minimum required value (%1$s). ', 'et-core' ),
					esc_html( $scan['minimum'] )
				);
			}

			/**
			 * Build description messaging for results & recommendation
			 */
			$learn_more_link = '';
			if ( ! is_null( $scan['learn_more'] ) ) {
				$learn_more_link = sprintf( ' <a href="%1$s" target="_blank">%2$s</a>',
					esc_url( $scan['learn_more'] ),
					esc_html__( 'Learn more.', 'et-core' )
				);
			}

			switch ( $system_diagnostics_settings[ $i ]['pass_fail'] ) {
				case 'pass':
					$system_diagnostics_settings[ $i ]['description'] = sprintf(
						'- %1$s %2$s',
						sprintf(
							esc_html__( 'Congratulations! This meets or exceeds our recommendation of %1$s.', 'et-core' ),
							esc_html( $scan['recommended'] )
						),
						et_core_intentionally_unescaped( $learn_more_link, 'html' )
					);
					break;
				case 'minimal':
				case 'fail':
					$system_diagnostics_settings[ $i ]['description'] = sprintf(
						'- %1$s%2$s %3$s',
						esc_html( $message_minimum ),
						sprintf(
							esc_html__( 'We recommend %1$s for the best experience.', 'et-core' ),
							esc_html( $scan['recommended'] )
						),
						et_core_intentionally_unescaped( $learn_more_link, 'html' )
					);
					break;
				case 'unknown':
				default:
					$system_diagnostics_settings[ $i ]['description'] = sprintf(
						esc_html__( '- We are unable to determine your setting. %1$s', 'et-core' ),
						et_core_intentionally_unescaped( $learn_more_link, 'html' )
					);
			}
		}

		// If we just want the array (not a formatted HTML block), return that now
		if ( false === $formatted ) {
			return $system_diagnostics_settings;
		}

		foreach ( $system_diagnostics_settings as $item ) {
			// Add reported setting to plaintext report:
			if ( 'plain' === $format ) {
				switch ( $item['pass_fail'] ) {
					case 'pass':
						$status = '  ';
						break;
					case 'minimal':
						$status = '~ ';
						break;
					case 'fail':
						$status = "! ";
						break;
					case 'unknown':
					default:
						$status = '? ';
				}

				$report .= $status . $item['name'] . PHP_EOL
						   . '  ' . $item['actual'] . PHP_EOL . PHP_EOL;
			}

			// Add reported setting to table:
			if ( 'div' === $format ) {
				$help_text = '';
				if ( ! is_null( $item['help_text'] ) ) {
					$help_text = $item['help_text'];
				}

				$report .= sprintf( '<div class="et-epanel-box et_system_status_row et_system_status_%1$s">
				<div class="et-box-title setting">
				    <h3>%2$s</h3>
				    <div class="et-box-descr"><p>%3$s</p></div>
				</div>
				<div class="et-box-content results">
				    <span class="actual">%4$s</span>
				    <span class="description">%5$s</span>
				</div>
				<span class="et-box-description"></span>
				</div>',
					esc_attr( $item['pass_fail'] ),
					esc_html( $item['name'] ),
					et_core_intentionally_unescaped( $help_text, 'html' ),
					esc_html( $item['actual'] ),
					et_core_intentionally_unescaped( $item['description'], 'html' )
				);
			}

		}

		// Prepend title and timestamp
		if ( 'plain' === $format ) {
			$report = '## ' . esc_html__( 'System Status', 'et-core' ) . ' ##' . PHP_EOL
					  . ':: ' . date( 'Y-m-d @ H:i:s e' ) . PHP_EOL . PHP_EOL
					  . $report;
		}
		if ( 'div' === $format ) {
			$report = sprintf( '<div class="%3$s-report">%1$s</div><p class="%3$s-congratulations">%2$s</p>',
				$report,
				esc_html__( 'Congratulations, all system checks have passed. Your hosting configuration is compatible with Divi.', 'et-core' ),
				'et-system-status'
			);
		}

		return $report;
	}

	/**
	 * Convert size string with "shorthand byte" notation to raw byte value for comparisons.
	 *
	 * @since 3.20
	 *
	 * @param string $size
	 *
	 * @return int size in bytes
	 */
	protected function get_size_in_bytes( $size = '' ) {
		// Capture the denomination and convert to uppercase, then do math to it
		switch ( strtoupper( substr( $size, -1 ) ) ) {
			// Terabytes
			case 'T':
				return (int) $size * 1099511627776;
			// Gigabytes
			case 'G':
				return (int) $size * 1073741824;
			// Megabytes
			case 'M':
				return (int) $size * 1048576;
			// Kilobytes
			case 'K':
				return (int) $size * 1024;
			default:
				return (int) $size;
		}
	}

	/**
	 * Convert size string with "shorthand byte" notation to raw byte value for comparisons.
	 *
	 * @since 3.20
	 *
	 * @param int $bytes
	 * @param int $precision
	 *
	 * @return string size in "shorthand byte" notation
	 */
	protected function get_size_in_shorthand( $bytes = 0, $precision = 2 ) {
		$units = array( ' bytes', 'KB', 'MB', 'GB', 'TB' );
		$i     = 0;

		while ( $bytes > 1024 ) {
			$bytes /= 1024;
			$i++;
		}

		return round( $bytes, $precision ) . $units[ $i ];
	}

	/**
	 * Size comparisons between two values using a variety of calculation methods.
	 *
	 * @since 3.20.2
	 *
	 * @param string|int|float $a Value to compare against
	 * @param string|int|float $b Value being compared
	 * @param string $type Comparison type
	 *
	 * @return bool Whether the second value is equal to or greater than the first
	 */
	protected function value_is_at_least( $a, $b, $type = 'size' ) {
		switch ( $type ) {
			case 'version':
				return (float) $a <= (float) $b;
			case 'seconds':
				return (int) $a <= (int) $b;
			case 'size':
			default:
				return $this->get_size_in_bytes( $a ) <= $this->get_size_in_bytes( $b );
		}
	}

	/**
	 * SUPPORT CENTER :: REMOTE ACCESS
	 */

	/**
	 * Add Support Center options to the Role Editor screen
	 *
	 * @since 3.20
	 *
	 * @param $all_role_options
	 *
	 * @return array
	 */
	public function support_user_add_role_options( $all_role_options ) {

		$all_role_options['support_center'] = array(
			'section_title' => esc_attr__( 'Support Center', 'et-core' ),
			'options'       => array(
				'et_support_center'               => array(
					'name' => esc_attr__( 'Divi Support Center Page', 'et-core' ),
				),
				'et_support_center_system'        => array(
					'name' => esc_attr__( 'System Status', 'et-core' ),
				),
				'et_support_center_remote_access' => array(
					'name' => esc_attr__( 'Remote Access', 'et-core' ),
				),
				'et_support_center_documentation' => array(
					'name' => esc_attr__( 'Divi Documentation &amp; Help', 'et-core' ),
				),
				'et_support_center_safe_mode'     => array(
					'name' => esc_attr__( 'Safe Mode', 'et-core' ),
				),
				'et_support_center_logs'          => array(
					'name' => esc_attr__( 'Logs', 'et-core' ),
				),
			),
		);

		return $all_role_options;
	}

	/**
	 * Create the Divi Support user (if it doesn't already exist)
	 *
	 * @since 3.20
	 *
	 * @return void|WP_Error
	 */
	public function support_user_maybe_create_user() {
		if ( username_exists( $this->support_user_account_name ) ) {
			return;
		}

		// Define user roles that will be used to control ET Support User permissions
		$this->support_user_create_roles();

		$token = $this->support_user_generate_token();

		$password = $this->support_user_generate_password( $token );

		$user_id = false;

		if ( $password && ! is_wp_error( $password ) ) {
			$user_id = wp_insert_user( array(
				'user_login'   => $this->support_user_account_name,
				'user_pass'    => $password,
				'first_name'   => 'Elegant Themes',
				'last_name'    => 'Support',
				'display_name' => 'Elegant Themes Support',
				'role'         => 'et_support',
			) );
		}

		if ( $user_id && ! is_wp_error( $user_id ) ) {
			$account_settings = array(
				'date_created' => time(),
				'token'        => $token,
			);

			update_option( $this->support_user_options_name, $account_settings );

			// update options variable
			$this->support_user_get_options();

			$this->support_user_init_cron_delete_account();
		} else {
			return new WP_Error( 'create_user_error', $user_id->get_error_message() );
		}
	}

	/**
	 * Define both Standard and Elevated roles for the Divi Support user
	 *
	 * @since 3.22 Added filters to extend the list of capabilities for the ET Support User
	 * @since 3.20
	 */
	public function support_user_create_roles() {
		// Make sure old versions of these roles do not exist
		$this->support_user_remove_roles();

		// Divi Support :: Standard
		$standard_capabilities = array(
			'assign_product_terms'               => true,
			'delete_pages'                       => true,
			'delete_posts'                       => true,
			'delete_private_pages'               => true,
			'delete_private_posts'               => true,
			'delete_private_products'            => true,
			'delete_product'                     => true,
			'delete_product_terms'               => true,
			'delete_products'                    => true,
			'delete_published_pages'             => true,
			'delete_published_posts'             => true,
			'delete_published_products'          => true,
			'edit_dashboard'                     => true,
			'edit_files'                         => true,
			'edit_others_pages'                  => true,
			'edit_others_posts'                  => true,
			'edit_others_products'               => true,
			'edit_pages'                         => true,
			'edit_posts'                         => true,
			'edit_private_pages'                 => true,
			'edit_private_posts'                 => true,
			'edit_private_products'              => true,
			'edit_product'                       => true,
			'edit_product_terms'                 => true,
			'edit_products'                      => true,
			'edit_published_pages'               => true,
			'edit_published_posts'               => true,
			'edit_published_products'            => true,
			'edit_theme_options'                 => true,
			'list_users'                         => true,
			'manage_categories'                  => true,
			'manage_links'                       => true,
			'manage_options'                     => true,
			'manage_product_terms'               => true,
			'moderate_comments'                  => true,
			'publish_pages'                      => true,
			'publish_posts'                      => true,
			'publish_products'                   => true,
			'read'                               => true,
			'read_private_pages'                 => true,
			'read_private_posts'                 => true,
			'read_private_products'              => true,
			'read_product'                       => true,
			'unfiltered_html'                    => true,
			'upload_files'                       => true,
			// Divi
			'ab_testing'                         => true,
			'add_library'                        => true,
			'disable_module'                     => true,
			'divi_builder_control'               => true,
			'divi_library'                       => true,
			'edit_borders'                       => true,
			'edit_buttons'                       => true,
			'edit_colors'                        => true,
			'edit_configuration'                 => true,
			'edit_content'                       => true,
			'edit_global_library'                => true,
			'edit_layout'                        => true,
			'export'                             => true,
			'lock_module'                        => true,
			'page_options'                       => true,
			'portability'                        => true,
			'read_dynamic_content_custom_fields' => true,
			'save_library'                       => true,
			'use_visual_builder'                 => true,
			// WooCommerce Capabilities
			'manage_woocommerce'                 => true,
		);

		// Divi Support :: Elevated
		$elevated_capabilities = array_merge( $standard_capabilities, array(
			'activate_plugins' => true,
			'delete_plugins'   => true,
			'delete_themes'    => true,
			'edit_plugins'     => true,
			'edit_themes'      => true,
			'install_plugins'  => true,
			'install_themes'   => true,
			'switch_themes'    => true,
			'update_plugins'   => true,
			'update_themes'    => true,
		) );

		// Filters to allow other code to extend the list of capabilities
		$additional_standard = apply_filters( 'add_et_support_standard_capabilities', array() );
		$additional_elevated = apply_filters( 'add_et_support_elevated_capabilities', array() );

		// Apply filter capabilities to our definitions
		$standard_capabilities = array_merge( $additional_standard, $standard_capabilities );
		// Just like Elevated gets all of Standard's capabilities, it also inherits Standard's filter caps
		$elevated_capabilities = array_merge( $additional_standard, $additional_elevated, $elevated_capabilities );

		// Create the new roles
		add_role( 'et_support', 'ET Support', $standard_capabilities );
		add_role( 'et_support_elevated', 'ET Support - Elevated', $elevated_capabilities );
	}

	/**
	 * Remove our Standard and Elevated Support roles
	 *
	 * @since 3.20
	 */
	public function support_user_remove_roles() {
		// Divi Support :: Standard
		remove_role( 'et_support' );

		// Divi Support :: Elevated
		remove_role( 'et_support_elevated' );
	}

	/**
	 * Set the ET Support User's role
	 *
	 * @since 3.20
	 *
	 * @param string $role
	 */
	public function support_user_set_role( $role = '' ) {
		// Get the Divi Support User object
		$support_user = new WP_User( $this->support_user_account_name );

		// Set the new Role
		switch ( $role ) {
			case 'et_support':
				$support_user->set_role( 'et_support' );
				break;
			case 'et_support_elevated':
				$support_user->set_role( 'et_support_elevated' );
				break;
			case '':
			default:
				$support_user->set_role( '' );
		}
	}

	/**
	 * Ensure the `unfiltered_html` capability is added to the ET Support roles in Multisite
	 *
	 * @since 3.22
	 *
	 * @param  array  $caps    An array of capabilities.
	 * @param  string $cap     The capability being requested.
	 * @param  int    $user_id The current user's ID.
	 *
	 * @return array Modified array of user capabilities.
	 */
	function support_user_map_meta_cap( $caps, $cap, $user_id ) {

		if ( ! $this->is_support_user( $user_id ) ) {
			return $caps;
		}

		// This user is in an ET Support user role, so add the capability
		if ( 'unfiltered_html' === $cap ) {
			$caps = array( 'unfiltered_html' );
		}

		return $caps;
	}

	/**
	 * Remove KSES filters on ET Support User's content
	 *
	 * @since 3.22
	 */
	function support_user_kses_remove_filters() {
		if ( $this->is_support_user() ) {
			kses_remove_filters();
		}
	}

	/**
	 * Clear "Delete Account" cron hook
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function support_user_clear_delete_cron() {
		wp_clear_scheduled_hook( $this->support_user_cron_name );
	}

	/**
	 * Delete the support account if it's expired or the expiration date is not set
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function support_user_cron_maybe_delete_account() {
		if ( ! username_exists( $this->support_user_account_name ) ) {
			return;
		}

		if ( isset( $this->support_user_options['date_created'] ) ) {
			$this->support_user_maybe_delete_expired_account();
		} else {
			// if the expiration date isn't set, delete the account anyway
			$this->support_user_delete_account();
		}
	}

	/**
	 * Schedule account removal check
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function support_user_init_cron_delete_account() {
		$this->support_user_clear_delete_cron();

		wp_schedule_event( time(), 'hourly', $this->support_user_cron_name );
	}

	/**
	 * Get plugin options
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function support_user_get_options() {
		$this->support_user_options = get_option( $this->support_user_options_name );
	}

	/**
	 * Generate random token
	 *
	 * @since 3.20
	 *
	 * @param  integer $length Token Length
	 * @param  bool $include_symbols Whether to include special characters (or just stick to alphanumeric)
	 *
	 * @return string  $token           Generated token
	 */
	public function support_user_generate_token( $length = 17, $include_symbols = true ) {
		$alphanum = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$symbols  = '!@$^*()-=+';
		$token    = substr( str_shuffle( $include_symbols ? $alphanum . $symbols : $alphanum ), 0, $length );

		return $token;
	}

	/**
	 * Generate password from token
	 *
	 * @since 3.20
	 *
	 * @param  string $token Token
	 *
	 * @return string $password Password
	 */
	public function support_user_generate_password( $token ) {
		global $wp_version;

		$salt = '';

		/** @see ET_Support_Center::maybe_set_site_id() */
		$site_id = get_option( 'et_support_site_id' );

		if ( empty( $site_id ) ) {
			return false;
		}

		// Site ID must be a string
		if ( ! is_string( $site_id ) ) {
			return false;
		}

		$et_license = $this->get_et_license();

		if ( ! $et_license ) {
			return false;
		}

		$send_to_api = array(
			'action'    => 'get_salt',
			'site_id'   => esc_attr( $site_id ),
			'username'  => esc_attr( $et_license['username'] ),
			'api_key'   => esc_attr( $et_license['api_key'] ),
			'site_url'  => esc_url( home_url( '/' ) ),
			'login_url' => 'https://www.elegantthemes.com/members-area/admin/token/?url=' . urlencode( wp_login_url() )
						   . '&token=' . urlencode( $token . '|' . $site_id ),
		);

		$support_user_options = array(
			'timeout'    => 30,
			'body'       => $send_to_api,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url( '/' ),
		);

		$request = wp_remote_post( 'https://www.elegantthemes.com/api/token.php', $support_user_options );

		if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {
			$response = unserialize( wp_remote_retrieve_body( $request ) );

			if ( ! empty( $response['incorrect_token'] ) && $response['incorrect_token'] ) {
				// Delete Site ID from database, if API returns the incorrect_token error
				delete_option( 'et_support_site_id' );

				return new WP_Error( 'incorrect_token', esc_html__( 'Please, try again.', 'et-core' ) );
			} else if ( ! empty( $response['salt'] ) ) {
				$salt = sanitize_text_field( $response['salt'] );
			}
		}

		if ( empty( $salt ) ) {
			return false;
		}

		$password = hash( 'sha256', $token . $salt );

		return $password;
	}

	/**
	 * Delete the account if it's expired
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function support_user_maybe_delete_expired_account() {
		if ( empty( $this->support_user_options['date_created'] ) ) {
			return;
		}

		$expiration_date_unix = strtotime( $this->support_user_expiration_time, $this->support_user_options['date_created'] );

		// Delete the user account if the expiration date is in the past
		if ( time() >= $expiration_date_unix ) {
			$this->support_user_delete_account();
		}

		return;
	}

	/**
	 * Delete support account and the plugin options ( token, expiration date )
	 *
	 * @since 3.20
	 *
	 * @return string | WP_Error  Confirmation message on success, WP_Error on failure
	 */
	public function support_user_delete_account() {
		if ( defined( 'DOING_CRON' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/user.php' );
		}

		if ( ! username_exists( $this->support_user_account_name ) ) {
			return new WP_Error( 'get_user_data', esc_html__( 'Support account doesn\'t exist.', 'et-core' ) );
		}

		$support_account_data = get_user_by( 'login', $this->support_user_account_name );

		if ( $support_account_data ) {
			$support_account_id = $support_account_data->ID;

			if (
				( is_multisite() && ! wpmu_delete_user( $support_account_id ) )
				|| ( ! is_multisite() && ! wp_delete_user( $support_account_id ) )
			) {
				return new WP_Error( 'delete_user', esc_html__( 'Support account hasn\'t been removed. Try to regenerate token again.', 'et-core' ) );
			}

			delete_option( $this->support_user_options_name );
		} else {
			return new WP_Error( 'get_user_data', esc_html__( 'Cannot get the support account data. Try to regenerate token again.', 'et-core' ) );
		}

		$this->support_user_remove_roles();

		$this->support_user_remove_site_id();

		$this->support_user_clear_delete_cron();

		// update options variable
		$this->support_user_get_options();

		new WP_Error( 'get_user_data', esc_html__( 'Token has been deleted successfully.', 'et-core' ) );

		return esc_html__( 'Token has been deleted successfully. ', 'et-core' );
	}

	/**
	 * Is this user the ET Support User?
	 *
	 * @since 3.22
	 *
	 * @param int|null $user_id Pass a User ID to check. We'll get the current user's ID otherwise.
	 *
	 * @return bool Returns whether this user is the ET Support User.
	 */
	function is_support_user( $user_id = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		// Gather this user's associated role(s)
		$user_roles      = (array) $user->roles;
		$user_is_support = false;

		// First, check the username
		if ( ! $this->support_user_account_name === $user->user_login ) {
			return $user_is_support;
		}

		// Determine whether this user has the ET Support User role
		if ( in_array( 'et_support', $user_roles ) ) {
			$user_is_support = true;
		}
		if ( in_array( 'et_support_elevated', $user_roles ) ) {
			$user_is_support = true;
		}

		return $user_is_support;
	}

	/**
	 * Delete support account and the plugin options ( token, expiration date )
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function unlist_support_center() {
		delete_option( 'et_support_center_installed' );
	}

	/**
	 *
	 */
	public function support_user_remove_site_id() {
		$site_id = get_option( 'et_support_site_id' );

		if ( empty( $site_id ) ) {
			return;
		}

		// Site ID must be a string
		if ( ! is_string( $site_id ) ) {
			return;
		}

		$et_license = $this->get_et_license();

		if ( ! $et_license ) {
			return;
		}

		$send_to_api = array(
			'action'   => 'remove_site_id',
			'site_id'  => esc_attr( $site_id ),
			'username' => esc_attr( $et_license['username'] ),
			'api_key'  => esc_attr( $et_license['api_key'] ),
			'site_url' => esc_url( home_url( '/' ) ),
		);

		$settings = array(
			'timeout' => 30,
			'body'    => $send_to_api,
		);

		$request = wp_remote_post( 'https://www.elegantthemes.com/api/token.php', $settings );

		if ( is_wp_error( $request ) ) {
			wp_remote_post( 'https://cdn.elegantthemes.com/api/token.php', $settings );
		}
	}

	function support_user_update_via_ajax() {
		// Verify nonce
		et_core_security_check( 'manage_options', 'support_center', 'nonce' );

		// Get POST data
		$support_update = sanitize_text_field( $_POST['support_update'] );

		$response = array();

		// Update option(s)
		if ( 'activate' === $support_update ) {
			$this->support_user_maybe_create_user();
			$this->support_user_set_role( 'et_support' );
			$account_settings   = get_option( $this->support_user_options_name );
			$site_id            = get_option( 'et_support_site_id' );
			$response['expiry'] = strtotime( date( 'Y-m-d H:i:s ', $this->support_user_options['date_created'] ) . $this->support_user_expiration_time );
			$response['token']  = '';
			if ( ! empty( $site_id ) && is_string( $site_id ) ) {
				$response['token'] = $account_settings['token'] . '|' . $site_id;
			}
			$response['message'] = esc_html__( 'ET Support User role has been activated.', 'et-core' );
		}
		if ( 'elevate' === $support_update ) {
			$this->support_user_set_role( 'et_support_elevated' );
			$response['message'] = esc_html__( 'ET Support User role has been elevated.', 'et-core' );
		}
		if ( 'deactivate' === $support_update ) {
			$this->support_user_set_role( '' );
			$this->support_user_delete_account();
			$this->support_user_clear_delete_cron();
			$response['message'] = esc_html__( 'ET Support User role has been deactivated.', 'et-core' );
		}

		// `echo` data to return
		if ( isset( $response ) ) {
			echo json_encode( $response );
		}

		// `die` when we're done
		wp_die();
	}

	/**
	 * SUPPORT CENTER :: SAFE MODE
	 */

	/**
	 * Safe Mode: Set session cookie to temporarily disable Plugins
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	function safe_mode_update_via_ajax() {
		et_core_security_check( 'manage_options', 'support_center', 'nonce' );

		// Get POST data
		$support_update = sanitize_text_field( $_POST['support_update'] );

		$response = array();

		// Update option(s)
		if ( 'activate' === $support_update ) {
			$this->toggle_safe_mode();
			$response['message'] = esc_html__( 'ET Safe Mode has been activated.', 'et-core' );
		}
		if ( 'deactivate' === $support_update ) {
			$this->toggle_safe_mode( false );
			$response['message'] = esc_html__( 'ET Safe Mode has been deactivated.', 'et-core' );
		}

		$this->set_safe_mode_cookie();

		// `echo` data to return
		if ( isset( $response ) ) {
			echo json_encode( $response );
		}

		// `die` when we're done
		wp_die();
	}

	/**
	 * Toggle Safe Mode
	 *
	 * @since 3.20
	 *
	 * @param bool $activate TRUE if enabling Safe Mode, FALSE if disabling Safe mode.
	 */
	public function toggle_safe_mode( $activate = true ) {
		$activate = (bool) $activate;
		$user_id  = get_current_user_id();

		update_user_meta( $user_id, '_et_support_center_safe_mode', $activate ? 'on' : 'off' );

		$activate ? $this->maybe_add_mu_autoloader() : $this->maybe_remove_mu_autoloader();
	}

	/**
	 * Set Safe Mode Cookie
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	function set_safe_mode_cookie() {
		if ( et_core_is_safe_mode_active() ) {
			// This random string ensures old cookies aren't used to view the site in Safe Mode
			$passport = md5( rand() );

			update_option( 'et-support-center-safe-mode-verify', $passport );
			setcookie( 'et-support-center-safe-mode', $passport, time() + DAY_IN_SECONDS, SITECOOKIEPATH, false, is_ssl() );
		} else {
			// Force-expire the cookie
			setcookie( 'et-support-center-safe-mode', '', 1, SITECOOKIEPATH, false, is_ssl() );
		}
	}

	/**
	 * Render modal that intercepts plugin activation/deactivation
	 *
	 * @since 3.20
	 *
	 * @return void
	 */
	public function render_safe_mode_block_restricted() {
		if ( ! et_core_is_safe_mode_active() ) {
			return;
		}

		?>
		<script type="text/template" id="et-ajax-saving-template">
			<div class="et-core-modal-overlay et-core-form et-core-safe-mode-block-modal">
				<div class="et-core-modal">
					<div class="et-core-modal-header">
						<h3 class="et-core-modal-title">
							<?php print esc_html__( 'Safe Mode', 'et-core' ); ?>
						</h3>
						<a href="#" class="et-core-modal-close" data-et-core-modal="close"></a>
					</div>
					<div id="et-core-safe-mode-block-modal-content">
						<div class="et-core-modal-content">
							<p><?php print esc_html__(
									'Safe Mode is enabled and the current action cannot be performed.',
									'et-core'
								); ?></p>
						</div>
						<a class="et-core-modal-action"
						   href="<?php echo admin_url( null, 'admin.php?page=et_support_center#et_card_safe_mode' ); ?>">
							<?php print esc_html__( sprintf( 'Turn Off %1$s Safe Mode', $this->parent_nicename ), 'et-core' ); ?>
						</a>
					</div>
				</div>
			</div>
		</script>
		<?php

	}

	/**
	 * Disable Child Theme (if Safe Mode is active)
	 *
	 * The `is_child_theme()` function returns TRUE if a child theme is active. Parent theme info can be gathered from
	 * the child theme's settings, so in the case of an active child theme we can capture the parent theme's info and
	 * temporarily push the parent theme as active (similar to how WP lets the user preview a theme before activation).
	 *
	 * @since 3.20
	 *
	 * @param $current_theme
	 *
	 * @return false|string
	 */
	function maybe_disable_child_theme( $current_theme ) {
		// Don't do anything if the user isn't logged in
		if ( ! is_user_logged_in() ) {
			return $current_theme;
		}

		if ( ! is_child_theme() ) {
			return $current_theme;
		}

		if ( et_core_is_safe_mode_active() ) {
			$child_theme = wp_get_theme( $current_theme );
			if ( $parent_theme = $child_theme->get( 'Template' ) ) {
				return $parent_theme;
			}
		}

		return $current_theme;
	}

	/**
	 * Disable Custom CSS (if Safe Mode is active)
	 *
	 * @since 3.20
	 */
	function maybe_disable_custom_css() {
		// Don't do anything if the user isn't logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( et_core_is_safe_mode_active() ) {
			// Remove "Additional CSS" from WP Head action hook
			remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
		}
	}

	/**
	 * Add Safe Mode Indicator (if Safe Mode is active)
	 *
	 * @since 3.20
	 */
	function maybe_add_safe_mode_indicator() {
		// Don't do anything if the user isn't logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Don't display when Visual Builder is active
		if ( et_core_is_fb_enabled() ) {
			return;
		}

		if ( et_core_is_safe_mode_active() ) {
			print sprintf( '<a class="%1$s" href="%2$s">%3$s</a>',
				'et-safe-mode-indicator',
				esc_url( get_admin_url( null, 'admin.php?page=et_support_center#et_card_safe_mode' ) ),
				esc_html__( sprintf( 'Turn Off %1$s Safe Mode', $this->parent_nicename ), 'et-core' )
			);

			print sprintf( '<div id="%1$s"><img src="%2$s" alt="%3$s" id="%3$s"/></div>',
				'et-ajax-saving',
				esc_url( $this->local_path . '/admin/images/ajax-loader.gif' ),
				'loading'
			);
		}
	}

	/**
	 * Prints the admin page for Support Center
	 *
	 * @since 3.20
	 */
	public function add_support_center() {

		$is_current_user_et_support = 0;
		if ( in_array( 'et_support', wp_get_current_user()->roles ) ) {
			$is_current_user_et_support = 1;
		}
		if ( in_array( 'et_support_elevated', wp_get_current_user()->roles ) ) {
			$is_current_user_et_support = 2;
		}

		?>
		<div id="et_support_center" class="wrap et-divi-admin-page--wrapper">
			<h1><?php esc_html_e( 'Divi Help &amp; Support Center', 'et-core' ); ?></h1>

			<div id="epanel">
				<div id="epanel-content">

					<?php

					/**
					 * Run code before any of the Support Center cards have been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_above_cards' );

					// Build Card :: System Status
					if ( et_pb_is_allowed( 'et_support_center_system' ) ) {
						$card_title   = esc_html__( 'System Status', 'et-core' );
						$card_content = sprintf( '<div class="et-system-status summary">%1$s</div>'
												 . '<textarea id="et_system_status_plain">%2$s</textarea>'
												 . '<div class="et_card_cta">%3$s %4$s %5$s</div>',
							et_core_intentionally_unescaped( $this->system_diagnostics_generate_report( true, 'div' ), 'html' ),
							et_core_intentionally_unescaped( $this->system_diagnostics_generate_report( true, 'plain' ), 'html' ),
							sprintf( '<a class="full_report_show">%1$s</a>', esc_html__( 'Show Full Report', 'et-core' ) ),
							sprintf( '<a class="full_report_hide">%1$s</a>', esc_html__( 'Hide Full Report', 'et-core' ) ),
							sprintf( '<a class="full_report_copy">%1$s</a>', esc_html__( 'Copy Full Report', 'et-core' ) )
						);

						print $this->add_support_center_card( array(
							'title'              => $card_title,
							'content'            => $card_content,
							'additional_classes' => array(
								'et_system_status',
								'summary',
							),
						) );
					}

					/**
					 * Run code after the 1st Support Center card has been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_below_position_1' );

					// Build Card :: Remote Access
					if ( et_pb_is_allowed( 'et_support_center_remote_access' ) && ( 0 === $is_current_user_et_support ) ) {

						$card_title   = esc_html__( 'Elegant Themes Support', 'et-core' );
						$card_content = __( '<p>Enabling <strong>Remote Access</strong> will give the Elegant Themes support team limited access to your WordPress Dashboard. If requested, you can also enable full admin privileges. Remote Access should only be turned on if requested by the Elegant Themes support team. Remote Access is automatically disabled after 4 days.</p>', 'et-core' );

						$support_account = $this->get_et_support_user();

						$is_et_support_user_active = 0;

						$has_et_license = $this->get_et_license();

						if ( ! $has_et_license ) {

							$card_content .= sprintf(
								'<div class="et-support-user"><h4>%1$s</h4><p>%2$s</p></div>',
								esc_html__( 'Remote Access', 'et-core' ),
								__( 'Remote Access cannot be enabled because you do not have a valid API Key or your Elegant Themes subscription has expired. You can find your API Key by <a href="https://www.elegantthemes.com/members-area/api/" target="_blank">logging in</a> to your Elegant Themes account. It should then be added to your <a href="https://www.elegantthemes.com/documentation/divi/update-divi/" target=_blank">Options Panel</a>.', 'et-core' )
							);

						} else {

							if ( is_object( $support_account ) && property_exists( $support_account, 'roles' ) ) {
								if ( in_array( 'et_support', $support_account->roles ) ) {
									$is_et_support_user_active = 1;
								}
								if ( in_array( 'et_support_elevated', $support_account->roles ) ) {
									$is_et_support_user_active = 2;
								}
							}

							$support_user_active_state = ( intval( $is_et_support_user_active ) > 0 ) ? ' et_pb_on_state' : ' et_pb_off_state';

							$expiry = '';
							if ( ! empty( $this->support_user_options['date_created'] ) ) {
								// Calculate the 'Created Date' plus the 'Time To Expire'
								$date_created = date( 'Y-m-d H:i:s ', $this->support_user_options['date_created'] );
								$expiry       = strtotime( $date_created . $this->support_user_expiration_time );
							}

							// Toggle Support User activation
							$card_content .= sprintf( '<div class="et-support-user"><h4>%1$s</h4>'
													  . '<div class="et_support_user_toggle">'
													  . '<div class="%7$s_wrapper"><div class="%7$s %2$s">'
													  . '<span class="%8$s et_pb_on_value">%3$s</span>'
													  . '<span class="et_pb_button_slider"></span>'
													  . '<span class="%8$s et_pb_off_value">%4$s</span>'
													  . '</div></div>'
													  . '<span class="et-support-user-expiry" data-expiry="%5$s">%6$s'
													  . '<span class="support-user-time-to-expiry"></span>'
													  . '</span>'
													  . '</div>'
													  . '</div>',
								esc_html__( 'Remote Access', 'et-core' ),
								esc_attr( $support_user_active_state ),
								esc_html__( 'Enabled', 'et-core' ),
								esc_html__( 'Disabled', 'et-core' ),
								esc_attr( $expiry ),
								esc_html__( 'Remote Access will be automatically disabled in: ', 'et-core' ),
								'et_pb_yes_no_button',
								'et_pb_value_text'
							);

							// Toggle Support User role elevation (only visible if Support User is active)
							$extra_css                   = ( intval( $is_et_support_user_active ) > 0 ) ? 'style="display:block;"' : '';
							$support_user_elevated_state = ( intval( $is_et_support_user_active ) > 1 ) ? ' et_pb_on_state' : ' et_pb_off_state';

							$card_content .= sprintf( '<div class="et-support-user-elevated" %5$s><h4>%1$s</h4>'
													  . '<div class="et_support_user_elevated_toggle">'
													  . '<div class="%6$s_wrapper"><div class="%6$s %2$s">'
													  . '<span class="%7$s et_pb_on_value">%3$s</span>'
													  . '<span class="et_pb_button_slider"></span>'
													  . '<span class="%7$s et_pb_off_value">%4$s</span>'
													  . '</div></div>'
													  . '</div>'
													  . '</div>',
								esc_html__( 'Activate Full Admin Privileges', 'et-core' ),
								esc_attr( $support_user_elevated_state ),
								esc_html__( 'Enabled', 'et-core' ),
								esc_html__( 'Disabled', 'et-core' ),
								et_core_intentionally_unescaped( $extra_css, 'html' ),
								'et_pb_yes_no_button',
								'et_pb_value_text'
							);
						}

						// Add a "Copy Support Token" CTA if Remote Access is active
						$site_id           = get_option( 'et_support_site_id' );
						$support_token_cta = '';
						if ( intval( $is_et_support_user_active ) > 0 && ! empty( $site_id ) && is_string( $site_id ) ) {
							$account_settings  = get_option( $this->support_user_options_name );
							$support_token_cta = '<a class="copy_support_token" data-token="'
												 . esc_attr( $account_settings['token'] . '|' . $site_id )
												 . '">'
												 . esc_html__( 'Copy Support Token', 'et-core' )
												 . '</a>';
						}

						$card_content .= '<div class="et_card_cta">'
										 . '<a target="_blank" href="https://www.elegantthemes.com/members-area/help/">'
										 . esc_html__( 'Chat With Support', 'et-core' )
										 . '</a>'
										 . $support_token_cta
										 . '</div>';

						print $this->add_support_center_card( array(
							'title'              => $card_title,
							'content'            => $card_content,
							'additional_classes' => array(
								'et_remote_access',
								'et-epanel-box',
							),
						) );
					}

					/**
					 * Run code after the 2nd Support Center card has been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_below_position_2' );

					// Build Card :: Divi Documentation & Help
					if ( et_pb_is_allowed( 'et_support_center_documentation' ) ) {
						switch ( $this->parent ) {
							case 'extra_theme':
								$documentation_url = 'https://www.elegantthemes.com/documentation/extra/';
								break;
							case 'divi_theme':
								$documentation_url = 'https://www.elegantthemes.com/documentation/divi/';
								break;
							case 'divi_builder_plugin':
								$documentation_url = 'https://www.elegantthemes.com/documentation/divi-builder/';
								break;
							default:
								$documentation_url = 'https://www.elegantthemes.com/documentation/';
						}

						$card_title   = esc_html__(
							sprintf( '%1$s Documentation &amp; Help', $this->parent_nicename ),
							'et-core'
						);
						$card_content = $this->get_documentation_video_player();
						$card_content .= $this->get_documentation_articles_list();
						$card_content .= '<div class="et_card_cta">'
										 . '<a href="' . $documentation_url . '" class="launch_documentation" target="_blank">'
										 . esc_html__(
											 sprintf( 'View Full %1$s Documentation', $this->parent_nicename ),
											 'et-core'
										 )
										 . '</a>'
										 . '</div>';

						print $this->add_support_center_card( array(
							'title'              => $card_title,
							'content'            => $card_content,
							'additional_classes' => array(
								'et_documentation_help',
								'et-epanel-box',
							),
						) );
					}

					/**
					 * Run code after the 3rd Support Center card has been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_below_position_3' );

					// Build Card :: Safe Mode
					if ( et_pb_is_allowed( 'et_support_center_safe_mode' ) ) {

						$card_title   = esc_html__( 'Safe Mode', 'et-core' );
						$card_content = __( '<p>Enabling <strong>Safe Mode</strong> will temporarily disable features and plugins that may be causing problems with your Elegant Themes product. This includes all Plugins, Child Themes, and Custom Code added to your integration areas. These items are only disabled for your current user session so your visitors will not be disrupted. Enabling Safe Mode makes it easy to figure out what is causing problems on your website by identifying or eliminating third party plugins and code as potential causes.</p>', 'et-core' );

						$safe_mode_active = ( et_core_is_safe_mode_active() ) ? ' et_pb_on_state' : ' et_pb_off_state';
						$plugins_list     = array();
						$plugins_output = '';

						$has_mu_plugins_dir        = file_exists( WPMU_PLUGIN_DIR ) && is_writable( WPMU_PLUGIN_DIR );
						$can_create_mu_plugins_dir = is_writable( WP_CONTENT_DIR ) && ! file_exists( WPMU_PLUGIN_DIR );

						if ( $has_mu_plugins_dir || $can_create_mu_plugins_dir ) {
							// Gather list of plugins that will be temporarily deactivated in Safe Mode
							$all_plugins    = get_plugins();
							$active_plugins = get_option( 'active_plugins' );

							foreach ( $active_plugins as $plugin ) {
								// Verify this 'active' plugin actually exists in the plugins directory
								if ( ! in_array( $plugin, array_keys( $all_plugins ) ) ) {
									continue;
								}

								// If it's not in our whitelist, add it to the list of plugins we'll disable
								if ( ! in_array( $plugin, $this->safe_mode_plugins_whitelist ) ) {
									$plugins_list[] = '<li>' . esc_html( $all_plugins[ $plugin ]['Name'] ) . '</li>';
								}
							}

						} else {
							$error_message  = et_get_safe_localization( sprintf( __( 'Plugins cannot be disabled because your <code>wp-content</code> directory has inconsistent file permissions. <a href="%1$s">Click here</a> for more information.', 'et-core'), 'https://wordpress.org/support/article/changing-file-permissions/' ) );
							$plugins_list[] = '<li class="et-safe-mode-error">' . $error_message . '</li>';
						}

						if ( count( $plugins_list ) > 0 ) {
							$plugins_output = sprintf( '<p>%1$s</p><ul>%2$s</ul>',
								esc_html__( 'The following plugins will be temporarily disabled for you only:', 'et-core' ),
								et_core_intentionally_unescaped( implode( ' ', $plugins_list ), 'html' )
							);
						}

						// Toggle Safe Mode activation
						$card_content .= sprintf( '<div id="et_card_safe_mode" class="et-safe-mode">'
						                          . '<div class="et_safe_mode_toggle">'
						                          . '<div class="%5$s_wrapper"><div class="%5$s %1$s">'
						                          . '<span class="%6$s et_pb_on_value">%2$s</span>'
						                          . '<span class="et_pb_button_slider"></span>'
						                          . '<span class="%6$s et_pb_off_value">%3$s</span>'
						                          . '</div></div>'
						                          . '%4$s'
						                          . '</div>'
						                          . '</div>',
							esc_attr( $safe_mode_active ),
							esc_html__( 'Enabled', 'et-core' ),
							esc_html__( 'Disabled', 'et-core' ),
							$plugins_output,
							'et_pb_yes_no_button',
							'et_pb_value_text'
						);

						print $this->add_support_center_card( array(
							'title'              => $card_title,
							'content'            => $card_content,
							'additional_classes' => array(
								'et_safe_mode',
								'et-epanel-box',
							),
						) );
					}

					/**
					 * Run code after the 4th Support Center card has been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_below_position_4' );

					// Build Card :: Logs
					if ( et_pb_is_allowed( 'et_support_center_logs' ) ) {
						$debug_log_lines = apply_filters( 'et_debug_log_lines', 200 );
						$wp_debug_log    = $this->get_wp_debug_log( $debug_log_lines );
						$card_title      = esc_html__( 'Logs', 'et-core' );

						$card_content = '<p>If you have <a href="https://codex.wordpress.org/Debugging_in_WordPress" target=_blank" >WP_DEBUG_LOG</a> enabled, WordPress related errors will be archived in a log file. For your convenience, we have aggregated the contents of this log file so that you and the Elegant Themes support team can view it easily. The file cannot be edited here.</p>';

						if ( isset( $wp_debug_log['error'] ) ) {
							$card_content .= '<div class="et_system_status_log_preview">'
											 . '<textarea>' . $wp_debug_log['error'] . '</textarea>'
											 . '</div>';
						} else {
							$card_content .= '<div class="et_system_status_log_preview">'
											 . '<textarea id="et_logs_display">' . $wp_debug_log['entries'] . '</textarea>'
											 . '<textarea id="et_logs_recent">' . $wp_debug_log['entries'] . '</textarea>'
											 . '</div>'
											 . '<div class="et_card_cta">'
											 . '<a href="' . content_url( 'debug.log' ) . '" class="download_debug_log" download>'
											 . esc_html__( 'Download Full Debug Log', 'et-core' )
											 . ' (' . $wp_debug_log['size'] . ')'
											 . '</a>'
											 . '<a class="copy_debug_log">'
											 . esc_html__( 'Copy Recent Log Entries', 'et-core' )
											 . '</a>'
											 . '</div>';
						}

						print $this->add_support_center_card( array(
							'title'              => $card_title,
							'content'            => $card_content,
							'additional_classes' => array(
								'et_system_logs',
								'et-epanel-box',
							),
						) );
					}

					/**
					 * Run code after all of the Support Center cards have been output
					 *
					 * @since 3.20
					 */
					do_action( 'et_support_center_below_cards' );

					?>
				</div>
			</div>
		</div>
		<div id="et-ajax-saving">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/core/admin/images/ajax-loader.gif' ); ?>" alt="loading" id="loading" />
		</div>
		<?php

	}

}
