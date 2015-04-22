<?php


/*
	* Plugin Name: Lingo
	* Plugin URI: https://wordpress.org/plugins/lingo/
	* Description: Show translations for visitors from other nations.
	* Text Domain: lingo
	* Domain Path: /lang/
	* Version: 1.0.0
	* Author: Leonard Lamprecht
	* Author URI: https://profiles.wordpress.org/mindrun/#content-plugins
	* License: GPLv2
*/


namespace lingo;

class load {

	public function __construct() {

		$hooks = array(

			'actions' => array(
				'init',
				'admin_menu',
				'admin_init',
				'admin_enqueue_scripts',
				'add_meta_box',
				'save_post',
				'wp_before_admin_bar_render',
				'manage_translation_posts_custom_column',
				'do_meta_boxes'
			),

			'filters' => array(
				'the_content',
				'language_attributes',
				'post_row_actions',
				'manage_translation_posts_columns',
				'plugin_action_links_translate'
			)

		);

		foreach( $hooks as $type => $which ) {

			foreach( $which as $hook ) {

				$params = array( $hook, array( $this, $hook ), 10, 2 );

				call_user_func_array( 'add_' . substr( $type, 0, -1 ), $params );

			}

		}

		add_filter( 'get_sample_permalink_html', array( $this, 'empty_string' ) );
		add_filter( 'pre_get_shortlink', array( $this, 'empty_string' ) );

		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 99, 2 );

		register_activation_hook( __FILE__, array( $this, 'activate' ) ) ;

	}

	public function filter_post_data( $data, $postarr ) {

		$p = $postarr;

		if( $p['post_type'] == 'translation' && $p['post_status'] !== 'trash' && $data['post_title'] !== translate( 'Auto Draft' ) ) {
			$data['post_status'] = 'publish';
		}

		return $data;

	}

	public function wp_before_admin_bar_render() {

		global $wp_admin_bar;

		$wp_admin_bar->remove_node( 'new-translation' );

	}

	public function save_post( $post_id ) {

		if( get_post_type( $post_id ) !== 'translation' ) {
			return;
		}

		if( ! isset( $_POST['lingo_nonce'] ) || ! isset( $_POST['post_language'] ) ) {
			return;
		}

		if( ! wp_verify_nonce( $_POST['lingo_nonce'], 'lingo_update' ) ) {
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

		}

		update_post_meta( $post_id, '_language', sanitize_text_field( $_POST['post_language'] ) );

	}

	private function make_select( $trans_id ) {

		$languages = $this->get_language();

		$main_lang = get_post_meta( $trans_id, '_language', true );

		if( ! $main_lang ) {
			$main_lang = 'de_DE';
		}

		$select = '<select name="post_language">';

		foreach( $languages as $key => $info ) {

			$selected = ( $main_lang == $key ? ' selected' : null );

			$select .= '<option value="' . $key . '"' . $selected . '>' . $info['native_name'] . '</option>';

		}

		$select .= '</select>';

		return $select;

	}

	private function has_translation( $post_id, $language ) {

		$post_args = array(
			'post_type' => 'translation',
			'post_status' => 'publish'
		);

		$translations = get_posts( $post_args );

		if( empty( $translations ) ) {
			return false;
		}

		foreach( $translations as $key => $post ) {

			$base = get_post_meta( $post->ID, '_original', true );
			$lang = get_post_meta( $post->ID, '_language', true );

			if( $base == $post_id && $lang == $language ) {

				$translation = $post;
				break;

			}

		}

		if( isset( $translation ) ) {
			return $translation;
		}

	}

	public function language_attributes( $default ) {

		$client_lang = $this->get_language( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
		$has = $this->has_translation( get_the_ID(), $client_lang );

		if( ! is_single() || ! $has ) {
			return;
		}

		return str_replace( get_bloginfo( 'language' ), $client_lang, $default );

	}

	public function the_content( $original ) {

		$post_id = get_the_ID();
		$translation = $this->has_translation( $post_id, $this->get_language( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );

		if( is_admin() || ! $this->is_translated( $post_id ) ) {
			$content = $original;
		}

		if( $translation ) {

			setup_postdata( $translation );

			$content = get_the_content();

			wp_reset_postdata();

		} else {

			$content = $original;

		}

		return $content;

	}

	public function empty_string( $output ) {

		global $post_type;

		switch( $post_type ) {

			case 'translation':
				return null;

			default:
				return $output;

		}

	}

	public function activate() {

		$this->init();
		flush_rewrite_rules();

	}

	private function is_translated( $post_ID ) {

		global $wpdb;

		$results = $wpdb->get_results( "SELECT * FROM wp_postmeta WHERE meta_value='" . $post_ID . "' AND meta_key='_original'" );

		if( ! empty( $results ) && is_array( $results ) ) {

			$translated = false;

			foreach( $results as $key => $object ) {

				if( get_post_status( $object->post_id ) !== 'trash' ) {

					$translated = true;
					break;

				}

			}

			if( $translated ) {
				return true;
			}

		}

	}

	public function admin_enqueue_scripts( $hook ) {

		if( get_current_screen()->id == 'translation' ) {

			wp_enqueue_style( 'lingo', plugins_url( 'assets/admin.css', __FILE__ ) );
			wp_enqueue_script( 'lingo', plugins_url( 'assets/admin.js', __FILE__ ) );

		}

	}

	public function manage_translation_posts_columns( $columns ) {

		unset( $columns['date'] );

		$new = array(
			'original' => __( 'Original', 'lingo' ),
			'language' => '<span class="dashicons dashicons-translation"></span>'
		);

		return array_merge( $columns, $new );

	}

	public function manage_translation_posts_custom_column( $column_name, $post_id ) {

		$slug = get_post_meta( $post_id, '_language', true );

		switch( $column_name ) {

			case 'original':
				echo get_the_title( get_post_meta( $post_id, '_original', true ) );
				break;

			case 'language':
				echo $this->get_language( false, $slug );
				break;

		}

	}

	public function post_row_actions( $actions, $post ) {

		if( $post->post_type == 'translation' ) {

			unset( $actions['inline hide-if-no-js'] );

			$original = admin_url( 'post.php?post=' . get_post_meta( $post->ID, '_original', true ) . '&action=edit' );

			if( isset( $actions['view'] ) ) {

				unset( $actions['view'] );
				$actions['edit_base'] = '<a href="' . $original . '">' . __( 'Edit original', 'lingo' ) . '</a>';

			}

		} else {

			if( $this->is_translated( $post->ID ) ) {

				$actions['lingo'] = 'Translated';

			} else {

				$link = admin_url( 'post.php?post=' . $post->ID . '&action=translate' );
				$title = __( 'Translate this item', 'lingo' );

				$actions['lingo'] = '<a href="' . $link . '" title="' . $title . '">' . __( 'Translate', 'lingo' ) . '</a>';

			}

		}

		return $actions;

	}

	private function get_language( $http_accept = null, $pointer = null ) {

		require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );

		$languages = array();
		$accepted = explode( ',', $http_accept );
		$api = wp_get_available_translations();

		if( ! $http_accept ) {

			if( $pointer ) {
				return $api[$pointer]['native_name'];
			} else {
				return $api;
			}

		}

		foreach( $accepted as $accepted_lang ) {

			$language = array();
			$lang = trim( ( stripos( $accepted_lang, ';' ) ? stristr( $accepted_lang, ';', true ) : $accepted_lang ) );

			if( stripos( $lang, '-' ) ) {

				$language['lang'] = stristr( $lang, '-', true );
				$language['full'] = str_replace( '-', '_', $language['lang'] . mb_strtoupper( stristr( $lang, '-' ) ) );

			} else {

				$language['lang'] = $lang;

			}

			preg_match( '/q=([0-9.]+)/', $accepted_lang, $qvalue );

			$language['qvalue'] = ( ! empty( $qvalue[1] ) ) ? $qvalue[1] : 1;

			array_push( $languages, $language );

		}

		$usable = null;

		foreach( $languages as $id => $language ) {

			if( isset( $language['full'] ) && array_key_exists( $language['full'], $api ) ) {

				$usable = $language['full'];
				break;

			} else {

				foreach( $api as $slug => $info ) {

					if( $info['iso'][1] == $language['lang'] ) {

						$usable = $slug;
						break 2;

					}

				}

			}

		}

		return $usable;

	}

	public function init() {

		define( 'LANG', $this->get_language( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );

		if( post_type_exists( 'translation' ) ) {
			remove_post_type_support( 'translation', 'revisions' );
		}

		$type_arguments = array(

			'label' => __( 'Translations', 'lingo' ),

			'labels' => array(
				'singular_name' => __( 'Translation', 'lingo' ),
				'add_new_item' => __( 'Add new translation', 'lingo' ),
				'not_found' => __( 'No translations found.', 'lingo' ),
				'search_items' => __( 'Find translations', 'lingo' ),
				'edit_item' => __( 'Edit translation', 'lingo' )
			),

			'show_ui' => true,
			'menu_position' => 80,
			'menu_icon' => 'dashicons-translation',
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'query_var' => false,
			'public' => true,

			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields'
			)

		);

		register_post_type( 'translation', $type_arguments );

	}

	public function admin_init() {

		if( isset( $_GET['action'] ) && $_GET['action'] == 'translate' ) {

			$post = get_post( $_GET['post'] );

			$post_id = $post->ID;

			$post->ID = null;
			$post->post_type = 'translation';
			$post->guid = null;

			$insert = wp_insert_post( $post );

			if( $insert !== 0 ) {

				add_post_meta( $insert, '_original', $post_id );
				add_post_meta( $insert, '_language', ( substr( get_bloginfo( 'language' ), 0, 2 ) == 'en' ? 'de_DE' : 'en_GB' ) );

				wp_redirect( admin_url( 'post.php?post=' . $insert . '&action=edit' ) );
				exit;

			}

		}

	}

	private function is_translation( $post_id ) {

		global $pagenow;

		$original = get_post_meta( $post_id, '_original', true );

		if( isset( $_GET['post'] ) && $pagenow == 'post.php' && get_post_type( $_GET['post'] ) == 'translation' ) {
			return $original;
		}

	}

	public function admin_menu() {

		remove_meta_box( 'submitdiv', 'translation', 'side' );
		add_meta_box( 'submitdiv', __( 'Options', 'lingo' ), array( $this, 'metabox' ), 'translation', 'side' );

	}

	public function do_meta_boxes() {

		$current = get_the_id();
		$screen = ( $current ? $this->is_translation( $current ) : false );

		if( $screen ) {

			foreach( get_metadata( 'post', $screen ) as $name => $item ) {

				if( substr( $name, 0, 1 ) !== '_' ) {

					$custom_fields = true;
					break;

				}

			}

			if( ! isset( $custom_fields ) ) {
				remove_meta_box( 'postcustom', 'translation', 'normal' );
			}

			if( ! has_post_thumbnail( $screen ) && ! has_post_thumbnail( $current ) ) {
				remove_meta_box( 'postimagediv', 'translation', 'side' );
			}

			if( ! has_excerpt( $screen ) && ! has_excerpt( $current ) ) {
				remove_meta_box( 'postexcerpt', 'translation', 'normal' );
			}

		}


		remove_meta_box( 'slugdiv', 'translation', 'normal' );

	}

	public function metabox() {

		wp_nonce_field( 'lingo_update', 'lingo_nonce' );

		$id = get_the_ID();

		$lang_meta = get_post_meta( $id, '_language', true );

		if( ! $lang_meta ) {
			$lang_meta = 'de_DE';
		}

		$language = $this->get_language( false, $lang_meta );

		$post = get_post( $id, 'ARRAY_A' );
		$author = get_user_by( 'id', $post['post_author'] );

		require( plugin_dir_path( __FILE__ ) . 'metabox.php' );

	}

}

new load;

?>