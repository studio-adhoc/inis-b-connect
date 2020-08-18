<?php
/*
Plugin Name: Initiativen Berlin Connector
Plugin URI: http://www.studioadhoc.de
Description: Dieses Plugin erzeugt eine eigene Taxonomie für Beiträge und ergänzt die WP Rest API.
Version: 1.0
Author: studio adhoc GmbH
Author URI: http://www.studioadhoc.de
GitHub Plugin URI: studio-adhoc/inis-b-connect
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Define Server URL */
define('PARTNER_SERVER_URL', apply_filters( 'inis_b_connect_partner_server_url', 'https://iniforum-berlin.de' ));

/* Adds Language Support */
function inis_b_connect_init() {
  $plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages/'; /* Relative to WP_PLUGIN_DIR */
  load_plugin_textdomain( 'inis-b-connect', false, $plugin_rel_path );
}
add_action('plugins_loaded', 'inis_b_connect_init');

/* Define Plugin Path */
define( 'INIS_B_CONNECT_PATH', plugin_dir_path( __FILE__ ) );

if ( !function_exists('inis_b_partner_init') && get_theme_mod('partner_connection') == 1 && get_theme_mod( 'partner_imprint_url' ) != '' ) {
  /* Adds Custom Taxonomy
   * Endpoint: https://domain.de/wp-json/wp/v2/city-topics
   */
  function ibc_add_custom_taxonomy() {
    $city_topic_slug = __('city-topics','inis-b-connect');

    register_taxonomy( 'city-topics', 'post',
  		array(
  			'labels' => array (
  		    'name' => __( 'City Topics', 'inis-b-connect' ),
  		    'singular_name' => __( 'City Topic', 'inis-b-connect' ),
  		    'search_items' => __( 'Search City Topics', 'inis-b-connect' ),
  		    'all_items' => __( 'All City Topics', 'inis-b-connect' ),
  		    'parent_item' => __( 'Parent City Topic', 'inis-b-connect' ),
  		    'parent_item_colon'  => __( 'Parent City Topic:', 'inis-b-connect' ),
  		    'edit_item' => __( 'Edit Topic', 'inis-b-connect' ),
  		    'update_item' => __( 'Update Topic', 'inis-b-connect' ),
  		    'add_new_item' => __( 'Add New Topic', 'inis-b-connect' ),
  		    'new_item_name' => __( 'New Topic Name', 'inis-b-connect' ),
  		    'menu_name' => __( 'Topics', 'inis-b-connect' )
  			),
  	    'hierarchical' => true,
  	    'show_admin_column' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
    		'show_in_rest' => true,
  			'query_var' => 'city-topics',
  			'rewrite' => array('slug' => $city_topic_slug ),
        'capabilites' => array(
          'manage_terms'  => 'activate_plugins',
          'edit_terms'    => 'activate_plugins',
          'delete_terms'  => 'activate_plugins',
          'assign_terms'  => 'edit_posts'
        )
  		)
  	);

    if (is_admin()) {
      $partner_topics = array();

      if ( false === ( $partner_topics = get_transient( 'inis_b_partner_topics' ) ) ) {
        $partner_server_get = wp_remote_get( PARTNER_SERVER_URL . '/wp-json/wp/v2/city-topics' );
        if (is_array( $partner_server_get )) {
          $partner_topics = json_decode( $partner_server_get['body'] );
          set_transient( 'inis_b_partner_topics', $partner_topics, 30 * MINUTE_IN_SECONDS );
        }
      }
      if ($partner_topics) {
        foreach ($partner_topics as $topic) {
          wp_insert_term($topic->name, 'city-topics');
        }
      }
    }
  }
  add_action( 'init', 'ibc_add_custom_taxonomy', 0 );

  /* Extends Rest API */
  function ibc_change_post_per_page( $args, $request ) {
    $max = max( (int) $request->get_param( 'custom_per_page' ), 2000 );
    $args['posts_per_page'] = $max;
    return $args;
  }
  add_filter( 'rest_post_query', 'ibc_change_post_per_page', 10, 2 );

  function categories_names_get_post_meta_cb($object, $field_name, $request){
    $formatted_categories = array();
    $categories = get_the_category( $object['id'] );
    $cat_i = 0;
    if ($categories) {
      foreach ($categories as $category) {
        $formatted_categories[$cat_i]['link'] = get_category_link( $category->term_id );
        $formatted_categories[$cat_i]['name'] = $category->name;
        $cat_i++;
      }
    }

    return $formatted_categories;
  }

  function tag_names_get_post_meta_cb($object, $field_name, $request){
    $formatted_tags = array();
    $tags = get_the_tags( $object['id'] );
    $tag_i = 0;
    if ($tags) {
      foreach ($tags as $tag) {
        $formatted_tags[$tag_i]['link'] = get_category_link( $tag->term_id );
        $formatted_tags[$tag_i]['name'] = $tag->name;
        $tag_i++;
      }
    }

    return $formatted_tags;
  }

  function city_topics_names_get_post_meta_cb($object, $field_name, $request){
    $formatted_categories = array();
    $categories = get_the_terms( $object['id'], 'city-topics' );
    $cat_i = 0;

    if ($categories) {
      foreach ($categories as $category) {
        $formatted_categories[$cat_i]['link'] = get_category_link( $category->term_id );
        $formatted_categories[$cat_i]['name'] = $category->name;
        $cat_i++;
      }
    }

    return $formatted_categories;
  }

  function partner_color_1_get_post_meta_cb($object, $field_name, $request){
    $output = '';
    if (get_theme_mod('partner_color_1')) {
      $output = get_theme_mod('partner_color_1');
    }
    return $output;
  }

  function partner_color_2_get_post_meta_cb($object, $field_name, $request){
    $output = '';
    if (get_theme_mod('partner_color_2')) {
      $output = get_theme_mod('partner_color_2');
    }
    return $output;
  }

  function featured_image_get_post_meta_cb($object, $field_name, $request){
    $output = '';
    if (has_post_thumbnail( $object['id'] )) {
      $output = get_the_post_thumbnail_url($object['id'],'full');
    }
    return $output;
  }

  function liability_content_get_theme_mod_cb($object, $field_name, $request){
    $output = '';
    if (get_theme_mod('partner_liability_content') != '') {
      $output = get_theme_mod('partner_liability_content');
    }
    return $output;
  }

  function imprint_url_get_theme_mod_cb($object, $field_name, $request){
    $output = '';
    if (get_theme_mod('partner_imprint_url') != '') {
      $output = get_theme_mod('partner_imprint_url');
    }
    return $output;
  }

  add_action('rest_api_init', function(){
    register_rest_field('post', 'categories_names',
      array(
        'get_callback' => 'categories_names_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'tag_names',
      array(
        'get_callback' => 'tag_names_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'city_topics_names',
      array(
        'get_callback' => 'city_topics_names_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'partner_color_1',
      array(
        'get_callback' => 'partner_color_1_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'partner_color_2',
      array(
        'get_callback' => 'partner_color_2_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'featured_image_url',
      array(
        'get_callback' => 'featured_image_get_post_meta_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'liability_content',
      array(
        'get_callback' => 'liability_content_get_theme_mod_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
    register_rest_field('post', 'imprint_url',
      array(
        'get_callback' => 'imprint_url_get_theme_mod_cb',
        'update_callback' => null,
        'schema' => null
      )
    );
  });
}

/* Add Partner Server URL to Customizer */
function inis_b_connect_customizer( $wp_customize ) {
  $wp_customize->add_section( 'inis_b_connect_section' , array(
    'title'       => __( 'Partner Connection', 'inis-b-connect' ),
    'priority'    => 62
  ) );

  $wp_customize->add_setting('partner_connection', array(
    'capability'     => 'edit_theme_options',
    'sanitize_callback' => 'absint',
  ));

  $wp_customize->add_control('partner_connection', array(
    'label'      => __('Partner Connection active', 'inis-b'),
    'section'    => 'inis_b_connect_section',
    'type'       => 'checkbox',
    'settings'   => 'partner_connection',
  ));

  $wp_customize->add_setting('partner_imprint_url', array(
    'capability'     => 'edit_theme_options',
    'sanitize_callback' => 'wp_filter_nohtml_kses',
  ));

  $wp_customize->add_control('partner_imprint_url', array(
    'label'      => __('Imprint URL', 'inis-b-connect'),
    'description'=> __('Please  enter the URL to your imprint', 'inis-b-connect'),
    'section'    => 'inis_b_connect_section',
    'type'       => 'text',
    'settings'   => 'partner_imprint_url',
  ));

  $wp_customize->add_setting('partner_liability_content', array(
    'capability'     => 'edit_theme_options',
    'sanitize_callback' => 'wp_filter_nohtml_kses',
  ));

  $wp_customize->add_control('partner_liability_content', array(
    'label'      => __('Liability for editorial content', 'inis-b-connect'),
    'section'    => 'inis_b_connect_section',
    'type'       => 'text',
    'settings'   => 'partner_liability_content',
  ));

  $wp_customize->add_setting('partner_color_1', array(
    'capability'        => 'edit_theme_options',
    'default'           => '#000000',
    'sanitize_callback' => 'sanitize_hex_color',
  ));

  $wp_customize->add_control(
    new WP_Customize_Color_Control(
    $wp_customize,
    'partner_color_1',
    array(
      'label'      => __('Partner Color 1', 'inis-b-connect'),
      'section'    => 'inis_b_connect_section',
      'settings'   => 'partner_color_1',
    ) )
  );

  $wp_customize->add_setting('partner_color_2', array(
    'capability'        => 'edit_theme_options',
    'default'           => '#000000',
    'sanitize_callback' => 'sanitize_hex_color',
  ));

  $wp_customize->add_control(
    new WP_Customize_Color_Control(
    $wp_customize,
    'partner_color_2',
    array(
      'label'      => __('Partner Color 2', 'inis-b-connect'),
      'section'    => 'inis_b_connect_section',
      'settings'   => 'partner_color_2',
    ) )
  );
}
add_action('customize_register', 'inis_b_connect_customizer');

/*-----------------------------------------------------------------------------------*/
/* Sanitize Select
/*-----------------------------------------------------------------------------------*/
function inis_b_connect_sanitize_select( $input, $setting ) {
  // Ensure input is a slug.
  //$input = sanitize_key( $input );

  // Get list of choices from the control associated with the setting.
  $choices = $setting->manager->get_control($setting->id)->choices;

  // If the input is a valid key, return it; otherwise, return the default.
  return ( array_key_exists( $input, $choices ) ? $input : $setting->default );
}
