<?php

namespace olpo;

class Settings {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Settings.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setting up admin fields
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'admin_footer', array( $this, 'render_modal' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_modal_scripts' ) );
		add_filter( 'plugin_action_links_ollie-pro/ollie-pro.php', array( $this, 'add_quick_links' ) );
	}

	/**
	 * Register quick links in plugins settings page.
	 *
	 * @param array $links given list of links.
	 *
	 * @return array
	 */
	public function add_quick_links( $links ) {
		$settings_url = esc_url( add_query_arg( 'page', 'ollie', get_admin_url() . 'themes.php' ) );
		$docs_url     = esc_url( 'https://olliewp.com/docs/' );

		$links[] = '<a href="' . $settings_url . '">' . esc_html__( 'Settings', 'ollie-pro' ) . '</a>';
		$links[] = '<a target="_blank" href="' . $docs_url . '">' . esc_html__( 'Docs', 'ollie-pro' ) . '</a>';

		return $links;
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function add_menu() {
		$settings_suffix = add_theme_page(
			esc_html__( 'Ollie', 'ollie-pro' ),
			esc_html__( 'Ollie', 'ollie-pro' ),
			'manage_options',
			'ollie',
			array( $this, 'render_settings' )
		);

		add_action( "admin_print_scripts-{$settings_suffix}", array( $this, 'add_settings_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_settings_scripts' ) );
	}

	/**
	 * Enqueue admin settings scripts.
	 *
	 * @return void
	 */
	public function add_settings_scripts() {
		$screen = get_current_screen();

		// Skip if not on Ollie settings page.
		if ( 'appearance_page_ollie' !== $screen->base ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'ollie-onboarding', OLPO_URL . '/inc/onboarding/build/index.js', array(
			'wp-api',
			'wp-components',
			'wp-plugins',
			'wp-edit-post',
			'wp-edit-site',
			'wp-element',
			'wp-api-fetch',
			'wp-data',
			'wp-i18n',
			'wp-block-editor'
		), OLPO_VERSION, true );

		$args = array(
			'screen'              => 'ollie-onboarding',
			'version'             => OLPO_VERSION,
			'dashboard_link'      => esc_url( admin_url() ),
			'home_link'           => esc_url( home_url() ),
			'permalink_structure' => true,
		);

		// Check if credentials are defined via config.
		$email    = defined( 'OLLIE_EMAIL' ) ? OLLIE_EMAIL : '';
		$password = defined( 'OLLIE_PASSWORD' ) ? OLLIE_PASSWORD : '';

		// Check if credentials are stored in DB.
		$options = get_option( 'ollie' );

		if ( isset( $options['login'] ) && $options['login'] ) {
			$credentials = explode( ':', base64_decode( $options['login'] ) );
			$email       = $credentials[0];
			$password    = $credentials[1];
		}

		// Pass them to global options data.
		$args['email']    = $email;
		$args['password'] = $password;

		// Check permalink structure.
		$permalinks = get_option( 'permalink_structure' );

		if ( empty( $permalinks ) ) {
			$args['permalink_structure'] = false;
		}

		wp_localize_script( 'ollie-onboarding', 'ollie_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-onboarding', 'ollie-pro', OLPO_PATH . '/languages' );
		}

		wp_enqueue_style( 'ollie-onboarding-style', OLPO_URL . '/inc/onboarding/build/index.css', array( 'wp-components' ), OLPO_VERSION );
	}

	/**
	 * Add modal related scripts.
	 *
	 * @return void
	 */
	public function add_modal_scripts() {
		$ollie_settings = get_option( 'ollie' );
		$theme          = wp_get_theme();

		// Skip if onboarding is already complete.
		if ( true === isset( $ollie_settings['skip_onboarding'] ) ) {
			return;
		}

		wp_enqueue_script( 'ollie-onboarding', OLPO_URL . '/inc/onboarding/build/index.js', array(
			'wp-api',
			'wp-components',
			'wp-plugins',
			'wp-edit-post',
			'wp-edit-site',
			'wp-element',
			'wp-api-fetch',
			'wp-data',
			'wp-i18n',
			'wp-block-editor'
		), OLPO_VERSION, true );

		$args = array(
			'screen'          => 'ollie-modal',
			'onboarding_link' => admin_url() . 'themes.php?page=ollie',
			'skip_onboarding' => false,
		);

		if ( isset( $ollie_settings['skip_onboarding'] ) ) {
			$args['skip_onboarding'] = $ollie_settings['skip_onboarding'];
		}

		wp_localize_script( 'ollie-onboarding', 'ollie_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-onboarding', 'ollie-pro', OLPO_PATH . '/languages' );
		}
	}

	/**
	 * Render Ollie settings.
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
        <div id="ollie-onboarding"></div>
		<?php
	}

	/**
	 * Set up Rest API routes.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		register_rest_route( 'ollie/v1', '/settings', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/settings', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_settings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-child-theme', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_child_theme' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/skip-onboarding', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'skip_onboarding' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-pages', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_pages' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'patterns/download', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'download_pattern' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );


		register_rest_route( 'ollie/v1', 'template-parts/download', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'download_template_part' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'patterns/delete', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_pattern' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'favorites/add', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'add_favorite' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'favorites/delete', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_favorite' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-blog-page', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_blog_page' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', '/update-typography-style', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_typography_style' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/update-template-part', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_template_part' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/update-template', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_template' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/delete-preview-page', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'delete_preview_page' ),
			'permission_callback' => function () {
				return current_user_can( 'delete_pages' );
			}
		) );

		register_rest_route( 'ollie/v1', '/preview-page', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'manage_preview_page' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			}
		) );

		register_rest_route( 'ollie/v1', '/check-plugins', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'check_plugins_status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/install-plugins', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'install_plugins' ),
			'permission_callback' => function () {
				return current_user_can( 'install_plugins' );
			}
		) );

		register_rest_route( 'ollie/v1', '/reset-templates', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_templates' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				}
			)
		);

		register_rest_route( 'ollie/v1', '/reset-global-styles', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_global_styles' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/remove-color-values', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove_color_values' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/clear-credentials', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'clear_credentials' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			},
		) );
	}

	/**
	 * Get Ollie settings via Rest API.
	 *
	 * @return false|mixed|null
	 */
	public function get_settings() {
		return get_option( 'ollie' );
	}

	/**
	 * Save settings via Rest API.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function save_settings( $request ) {
		if ( $request->get_params() ) {
			$options = $this->sanitize_options_array( $request->get_params() );

			// Save to Ollie options
			update_option( 'ollie', $options );

			// Handle WordPress core settings for homepage display
			if ( isset( $options['show_on_front'] ) ) {
				update_option( 'show_on_front', $options['show_on_front'] );

				if ( $options['show_on_front'] === 'page' ) {
					if ( isset( $options['page_on_front'] ) ) {
						update_option( 'page_on_front', absint( $options['page_on_front'] ) );
					}
					if ( isset( $options['page_for_posts'] ) ) {
						update_option( 'page_for_posts', absint( $options['page_for_posts'] ) );
					}
				} else {
					// Reset page settings when showing posts
					update_option( 'page_on_front', 0 );
					update_option( 'page_for_posts', 0 );
				}
			}

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "No data received." ] );
	}

	/**
	 * Create child theme via helper method.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function create_child_theme( $request ) {
		if ( $request->get_params() ) {
			$params = $request->get_params();
			Helper::create_child_theme( $params );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not create child theme." ] );
	}

	/**
	 * Skip onboarding.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function skip_onboarding( $request ) {
		if ( $request->get_params() ) {
			$options = (array) get_option( 'ollie', [] );

			// Set skip onboarding to true and update.
			$options['skip_onboarding'] = true;
			update_option( 'ollie', $options );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "There was a problem skipping the onboarding." ] );
	}

	/**
	 * Create pages.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function create_pages( $request ) {
		if ( $request->get_params() ) {
			$pages = $request->get_params();
			unset( $pages['_locale'] );
			unset( $pages['rest_route'] );

			$created_pages = Helper::create_pages( $pages );

			return json_encode( [ "status" => 200, "pages" => $created_pages, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not create pages." ] );
	}

	/**
	 * Install a pattern to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function download_pattern( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$created_pattern = Helper::download_pattern( $pattern );

			if ( $created_pattern ) {
				return json_encode( [ "status" => 200, "pattern" => $created_pattern, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}

	/**
	 * Install a template part to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function download_template_part( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$created_template_part = Helper::download_pattern( $pattern, true );

			if ( $created_template_part ) {
				return json_encode( [ "status" => 200, "template_part" => $created_template_part, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}


	/**
	 * Install a pattern to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function add_favorite( $request ) {
		if ( $request->get_params() ) {
			$data              = $request->get_params();
			$favorite_patterns = get_option( 'ollie_favorite_patterns', [] );

			// Check before use.
			if ( isset( $data[0] ) ) {
				$favorite_to_add = $data[0];

				// Add the pattern to the list.
				$favorite_patterns[] = $favorite_to_add;

				// Update the favorites list.
				update_option( 'ollie_favorite_patterns', $favorite_patterns );

				return json_encode( [ "status" => 200, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}

	/**
	 * Uninstall a pattern from the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function delete_favorite( $request ) {
		if ( $request->get_params() ) {
			$data              = $request->get_params();
			$favorite_patterns = get_option( 'ollie_favorite_patterns', [] );

			// Check before use.
			if ( isset( $data[0] ) && ! empty( $favorite_patterns ) ) {
				$favorite_to_delete = $data[0];

				if ( ( $key = array_search( $favorite_to_delete, $favorite_patterns ) !== false ) ) {

					unset( $favorite_patterns[ $key ] );

					// Update the list.
					update_option( 'ollie_favorite_patterns', $favorite_patterns );
				}
			}

			return json_encode( [
				"status"  => 200,
				"message" => "Ok"
			] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not remove favorite." ] );
	}

	/**
	 * Uninstall a pattern from the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function delete_pattern( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$uninstalled = Helper::delete_pattern( $pattern );

			return json_encode( [
				"status"      => 200,
				"uninstalled" => $uninstalled,
				"message"     => "Ok"
			] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not uninstall pattern." ] );
	}

	/**
	 * Sanitize options array before saving to database.
	 *
	 * @param array $options User-submitted options.
	 *
	 * @return array Sanitized array of options.
	 */
	private function sanitize_options_array( array $options = [] ) {
		$sanitized_options = [];

		foreach ( $options as $key => $value ) {
			switch ( $key ) {
				case 'skip_onboarding':
				case 'onboarding_complete':
					$sanitized_options[ $key ] = (bool) $value;
					break;
				case 'color_palette':
					$sanitized_options[ $key ] = $value;
					break;
				default:
					$sanitized_options[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized_options;
	}

	/**
	 * Render Ollie onboarding modal.
	 *
	 * @return void
	 */
	public function render_modal() {
		?>
        <div id="ollie-modal"></div>
        <style>
            @keyframes OllieFadeIn {
                0% {
                    opacity: 0;
                }
                100% {
                    opacity: 1;
                }
            }

            .ollie-modal-background {
                background: rgba(93, 93, 111, 0.7);
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9991;
                animation: OllieFadeIn .5s;
            }

            .ollie-modal-content {
                background: white;
                padding: 50px;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 440px;
                box-shadow: 0 3px 10px rgb(0, 0, 0, 0.2);
                z-index: 99;
                border-radius: 3px;
            }

            .ollie-modal-close {
                background: none;
                border: none;
                padding: 0;
                position: absolute;
                right: 20px;
                top: 20px;
            }

            .ollie-modal-close:hover {
                cursor: pointer;
                opacity: .6;
            }

            .ollie-modal-content img {
                max-width: 300px;
                margin: 0 auto 35px auto;
                display: block;
            }

            .ollie-modal-content h2 {
                text-align: center;
                font-size: 2.2em;
            }

            .ollie-modal-content p {
                margin: 25px auto;
                font-size: 16px;
                text-align: center;
            }

            .ollie-modal-content .ollie-modal-inner button {
                padding: 15px 20px;
                transition: 0.3s ease;
                background: #3858e9;
                color: white;
                border: none;
                cursor: pointer;
                border-radius: 2px;
                font-size: 16px;
            }

            .ollie-modal-content .ollie-modal-inner button:hover {
                background: #2145e6;
            }

            .ollie-modal-content button.ollie-modal-skip {
                background: none;
                color: #3c434a;
            }

            .ollie-modal-content button.ollie-modal-skip:hover {
                text-decoration: underline;
                background: none;
            }
        </style>
		<?php
	}

	public function create_blog_page() {
		// Check if a page with slug 'blog' exists
		$existing_page = get_page_by_path( 'blog' );

		// Set up the page details
		$page_title = $existing_page ? 'My Blog' : 'Blog';
		$page_slug  = $existing_page ? 'my-blog' : 'blog';

		// Create the page
		$page_id = wp_insert_post( array(
			'post_title'  => $page_title,
			'post_name'   => $page_slug,
			'post_status' => 'publish',
			'post_type'   => 'page'
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			wp_send_json( [
				"status"     => 200,
				"page_id"    => $page_id,
				"page_title" => $page_title,
				"message"    => "Blog page created successfully"
			] );
		}

		wp_send_json( [
			"status"  => 400,
			"message" => "Could not create blog page"
		] );
	}

	/**
	 * Updates the typography style setting.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function update_typography_style( $request ) {
		$style = $request->get_param( 'style' );

		if ( ! $style ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Style parameter is required'
				),
				400
			);
		}

		// Update the global styles
		$result = Helper::update_global_typography( $style );

		if ( $result['success'] ) {
			// Update the Ollie settings
			$settings               = get_option( 'ollie', array() );
			$settings['typography'] = $style;
			update_option( 'ollie', $settings );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Typography style updated successfully'
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $result['message']
			),
			500
		);
	}

	/**
	 * Updates a template part with the selected pattern.
	 *
	 * @param WP_REST_Request $request Request object containing 'style' and 'type' parameters.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function update_template_part( $request ) {
		// Get and validate required parameters
		$style = $request->get_param( 'style' );
		$type  = $request->get_param( 'type' );

		if ( ! $style || ! $type ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Style and type parameters are required', 'ollie-pro' )
			) );
		}

		// Get current theme
		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet(); // This gets child theme if active, parent theme if no child

		// Get the pattern content
		$pattern_registry = \WP_Block_Patterns_Registry::get_instance();
		$pattern_name     = "ollie/$style";

		if ( ! $pattern_registry->is_registered( $pattern_name ) ) {
			return rest_ensure_response( array(
				'success' => false,
				/* translators: %s: Name of the pattern */
				'message' => sprintf( __( 'Pattern %s not found', 'ollie-pro' ), $pattern_name )
			) );
		}

		$pattern = $pattern_registry->get_registered( $pattern_name );

		// Get or create the template part
		$template_part = \get_block_template( "$theme_slug//$type", 'wp_template_part' );

		if ( ! $template_part || ! isset( $template_part->wp_id ) ) {
			// Create new template part
			$post_id = wp_insert_post( array(
				'post_title'   => ucfirst( $type ),
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_content' => $pattern['content'],
				'post_name'    => $type,
				'tax_input'    => array(
					'wp_theme' => array( $theme_slug )
				)
			) );

			if ( is_wp_error( $post_id ) ) {
				return rest_ensure_response( array(
					'success' => false,
					'message' => $post_id->get_error_message()
				) );
			}

			$result = $post_id;
		} else {
			// Update existing template part
			$result = wp_update_post( array(
				'ID'           => $template_part->wp_id,
				'post_content' => $pattern['content']
			) );
		}

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		return rest_ensure_response( array(
			'success' => true,
			/* translators: %s: Type to update */
			'message' => sprintf( __( '%s updated successfully', 'ollie-pro' ), ucfirst( $type ) )
		) );
	}

	/**
	 * Updates a template with the selected pattern.
	 *
	 * @param WP_REST_Request $request Request object containing 'style' and 'type' parameters.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function update_template( $request ) {
		// Get and validate required parameters
		$style  = $request->get_param( 'style' );
		$type   = $request->get_param( 'type' );
		$is_pro = false;

		// If style doesn't include ollie it's a pro pattern.
		if ( strpos( $style, 'ollie/' ) === false ) {
			$is_pro = true;

			// We need to replace / with -.
			$style = str_replace( '/', '-', $style );
		}

		// Get current theme
		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet(); // This gets child theme if active, parent theme if no child

		if ( ! $style || ! $type ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Style and type parameters are required', 'ollie-pro' )
			) );
		}

		// Get the pattern content
		$pattern_registry = \WP_Block_Patterns_Registry::get_instance();
		$pattern_name     = $style;

		if ( $is_pro ) {
			// We need to get rid of ollie/ from the pattern name.
			$pattern_name = str_replace( 'ollie/', '', $pattern_name );
		}

		if ( ! $pattern_registry->is_registered( $pattern_name ) ) {
			return rest_ensure_response( array(
				'success' => false,
				/* translators: %s: Name of a pattern */
				'message' => sprintf( __( 'Pattern %s not found', 'ollie-pro' ), $pattern_name )
			) );
		}

		$pattern = $pattern_registry->get_registered( $pattern_name );

		// Get or create the template
		$template = \get_block_template( "$theme_slug//$type" );

		if ( ! $template || ! isset( $template->wp_id ) ) {
			// Create new template
			$post_id = wp_insert_post( array(
				'post_title'   => ucfirst( $type ),
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_content' => $pattern['content'],
				'post_name'    => $type,
				'tax_input'    => array(
					'wp_theme' => array( $theme_slug )
				)
			) );

			if ( is_wp_error( $post_id ) ) {
				return rest_ensure_response( array(
					'success' => false,
					'message' => $post_id->get_error_message()
				) );
			}

			$result = $post_id;
		} else {
			// Update existing template
			$result = wp_update_post( array(
				'ID'           => $template->wp_id,
				'post_content' => $pattern['content']
			) );
		}

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		return rest_ensure_response( array(
			'success' => true,
			/* translators: %s: the type of the template */
			'message' => sprintf( __( '%s template updated successfully', 'ollie-pro' ), ucfirst( $type ) )
		) );
	}

	/**
	 * Deletes the temporary preview page
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function delete_preview_page() {
		// Delete preview page
		$page_slug = 'ollie-preview-page';
		$page      = get_page_by_path( $page_slug, OBJECT, 'page' );

		if ( $page ) {
			wp_delete_post( $page->ID, true );
		}

		// Delete preview post
		$post_slug = 'ollie-preview-post';
		$post      = get_page_by_path( $post_slug, OBJECT, 'post' );

		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Preview content deleted successfully', 'ollie-pro' )
		) );
	}

	/**
	 * Manages preview content - creates if it doesn't exist, returns URL if it does, or deletes if requested
	 *
	 * @param WP_REST_Request $request Request object containing optional 'action' parameter ('get' or 'delete') and 'type' ('page' or 'post')
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function manage_preview_page( $request ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'ollie-pro' ), [
				'status' => 403
			] );
		}

		$action = $request->get_param( 'action' ) ?? 'get';
		$type   = $request->get_param( 'type' ) ?? 'page';
		$slug   = 'ollie-preview-' . $type;

		$content = get_page_by_path( $slug, OBJECT, $type );

		if ( $action === 'delete' ) {
			if ( ! $content ) {
				return rest_ensure_response( array(
					'success' => true,
					/* translators: %s: Type to delete */
					'message' => sprintf( __( 'Preview %s already deleted', 'ollie-pro' ), $type )
				) );
			}

			$result = wp_delete_post( $content->ID, true );

			return rest_ensure_response( array(
				'success' => (bool) $result,
				'message' => $result
					/* translators: %s: Type to delete */
					? sprintf( __( 'Preview %s deleted successfully', 'ollie-pro' ), $type )
					/* translators: %s: Type to delete */
					: sprintf( __( 'Failed to delete preview %s', 'ollie-pro' ), $type )
			) );
		}

		// Return existing content URL if it exists
		if ( $content ) {
			return rest_ensure_response( array(
				'success' => true,
				'url'     => get_permalink( $content->ID )
			) );
		}

		// Sample post content
		$sample_content = '<!-- wp:paragraph --><p>WordPress has evolved dramatically in recent years, and Ollie is leading the charge toward a more intuitive, powerful site-building experience. Gone are the days of needing expensive or bloated page builders to create beautiful WordPress websites. With Ollie, you can design stunning, responsive sites using WordPress\'s native tools.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">WordPress Like You\'ve Never Seen It Before</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Ollie is a WordPress block theme that integrates seamlessly with the WordPress site editor, unlocking a level of design and customization you never thought possible. It combines the power of a design system, pattern library, and block theme into one cohesive package that makes website building faster and more intuitive.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>The real magic of Ollie is how it leverages WordPress\'s powerful new features:</p><!-- /wp:paragraph --><!-- wp:list {"className":"is-style-list-boxed"} --><ul class="wp-block-list is-style-list-boxed"><!-- wp:list-item --><li><strong>Full Site Editing</strong> - Design your entire site with drag-and-drop simplicity directly in WordPress</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Patterns Library</strong> - Choose from 50+ pre-designed components to quickly build beautiful pages</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Global Styles</strong> - Make site-wide style changes with just a few clicks</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Responsive Design</strong> - Everything scales gracefully across all devices with zero extra work</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:heading --><h2 class="wp-block-heading">Build Blazing-Fast Websites Without the Bloat</h2><!-- /wp:heading --><!-- wp:paragraph --><p>What makes Ollie truly special isn\'t just how good it looks—it\'s built to perform. While other solutions add layers of code that slow down your site, Ollie is lightweight and optimized for speed right out of the box.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Ollie only loads the critical styles and assets needed for each page, ensuring your site scores top marks in performance tests. This means better SEO, improved user experience, and no need for performance hacks to build a turbocharged WordPress website.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Take It to the Next Level with Ollie Pro</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Love what Ollie offers but want even more design flexibility? Ollie Pro takes the experience to new heights with:</p><!-- /wp:paragraph --><!-- wp:list {"className":"is-style-list-boxed"} --><ul class="wp-block-list is-style-list-boxed"><!-- wp:list-item --><li><strong>Cloud Pattern Library</strong> - Access 200+ additional patterns and 30+ full page designs</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Pattern Browser</strong> - Browse, preview, and insert patterns with our intuitive interface</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Setup Wizard</strong> - Skip tedious setup steps and create new pages in minutes</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Mix and Match Styles</strong> - Combine patterns from different collections for unlimited design possibilities</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:paragraph --><p>The Ollie Pro pattern browser gives you a live preview of each pattern with responsive toggles to view designs on desktop, tablet, and mobile before you add them to your page.</p><!-- /wp:paragraph -->';

		$post_id = wp_insert_post( array(
			'post_title'   => 'Ollie Preview ' . ucfirst( $type ),
			'post_content' => $sample_content,
			'post_status'  => 'draft',
			'post_type'    => $type,
			'post_name'    => $slug
		) );

		if ( is_wp_error( $post_id ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $post_id->get_error_message()
			) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'url'     => get_permalink( $post_id )
		) );
	}

	/**
	 * Check the installation status of specified plugins
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function check_plugins_status() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = array(
			'icon-block'          => array(
				'file' => 'icon-block/icon-block.php',
				'slug' => 'icon-block'
			),
			'block-visibility'    => array(
				'file' => 'block-visibility/block-visibility.php',
				'slug' => 'block-visibility'
			),
			'advanced-query-loop' => array(
				'file' => 'advanced-query-loop/index.php',
				'slug' => 'advanced-query-loop'
			),
			'performance-lab'     => array(
				'file' => 'performance-lab/load.php',
				'slug' => 'performance-lab'
			),
			'custom-post-type-ui' => array(
				'file' => 'custom-post-type-ui/custom-post-type-ui.php',
				'slug' => 'custom-post-type-ui'
			),
			'create-block-theme'  => array(
				'file' => 'create-block-theme/create-block-theme.php',
				'slug' => 'create-block-theme'
			)
		);

		$status = array();
		foreach ( $plugins as $key => $plugin ) {
			$file_path      = WP_PLUGIN_DIR . '/' . $plugin['file'];
			$status[ $key ] = array(
				'installed' => file_exists( $file_path ),
				'activated' => is_plugin_active( $plugin['file'] )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'plugins' => $status
		) );
	}

	/**
	 * Install and activate selected plugins
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function install_plugins( $request ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

		$plugins = $request->get_param( 'plugins' );

		if ( empty( $plugins ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'No plugins specified', 'ollie-pro' )
			) );
		}

		$plugin_data = array(
			'icon-block'          => array(
				'file' => 'icon-block/icon-block.php',
				'slug' => 'icon-block'
			),
			'block-visibility'    => array(
				'file' => 'block-visibility/block-visibility.php',
				'slug' => 'block-visibility'
			),
			'advanced-query-loop' => array(
				'file' => 'advanced-query-loop/index.php',
				'slug' => 'advanced-query-loop'
			),
			'performance-lab'     => array(
				'file' => 'performance-lab/load.php',
				'slug' => 'performance-lab'
			),
			'custom-post-type-ui' => array(
				'file' => 'custom-post-type-ui/custom-post-type-ui.php',
				'slug' => 'custom-post-type-ui'
			),
			'create-block-theme'  => array(
				'file' => 'create-block-theme/create-block-theme.php',
				'slug' => 'create-block-theme'
			)
		);

		$installed = array();
		$errors    = array();

		foreach ( $plugins as $plugin ) {
			if ( ! isset( $plugin_data[ $plugin ] ) ) {
				continue;
			}

			$slug = $plugin_data[ $plugin ]['slug'];
			$file = $plugin_data[ $plugin ]['file'];

			// Skip if already active
			if ( is_plugin_active( $file ) ) {
				continue;
			}

			// Install if not installed
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				$api = plugins_api( 'plugin_information', array(
					'slug'   => $slug,
					'fields' => array(
						'short_description' => false,
						'sections'          => false,
						'requires'          => false,
						'rating'            => false,
						'ratings'           => false,
						'downloaded'        => false,
						'last_updated'      => false,
						'added'             => false,
						'tags'              => false,
						'compatibility'     => false,
						'homepage'          => false,
						'donate_link'       => false,
					),
				) );

				if ( is_wp_error( $api ) ) {
					$errors[] = $plugin;
					continue;
				}

				$skin     = new \WP_Ajax_Upgrader_Skin();
				$upgrader = new \Plugin_Upgrader( $skin );
				$result   = $upgrader->install( $api->download_link );

				if ( is_wp_error( $result ) ) {
					$errors[] = $plugin;
					continue;
				}
			}

			// Activate the plugin
			$activation_result = activate_plugin( $file );
			if ( is_wp_error( $activation_result ) ) {
				$errors[] = $plugin;
				continue;
			}

			$installed[] = $plugin;
		}

		return rest_ensure_response( array(
			'success'   => true,
			'installed' => $installed,
			'errors'    => $errors
		) );
	}

	/**
	 * Reset all template and template part customizations.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function reset_templates() {
		$result = Helper::reset_templates();

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 500 );
	}

	/**
	 * Reset global styles.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function reset_global_styles() {
		$result = Helper::reset_global_styles();

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Removes color values from global styles
	 *
	 * @return \WP_REST_Response
	 */
	public function remove_color_values() {
		$result = Helper::remove_color_values();

		return rest_ensure_response( $result );
	}

	/**
	 * Clear stored credentials.
	 *
	 *
	 * @return string
	 */
	public function clear_credentials() {
		$options = get_option( 'ollie' );

		// Unset credentials.
		if ( isset( $options['login'] ) && $options['login'] ) {
			// Remove login.
			unset( $options['login'] );

			// Resave options
			update_option( 'ollie', $options );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "No data received." ] );
	}
}
