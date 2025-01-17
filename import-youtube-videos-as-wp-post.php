<?php

/*
  Plugin Name: Import YouTube videos as WP Posts
  Plugin URI: http://www.enguerranweiss.fr
  Description: Get a video list from a request to Youtube (free query, playlist ID, channel ID) and add their content to your own Wordpress :)
  Version: 2.1
  Author: Enguerran Weiss
  Author URI: http://www.enguerranweiss.fr
 */


/* -----------------------------------------------------------------------------
 *  Init functions
  ---------------------------------------------------------------------------- */


add_action( 'init', 'create_y2p_pt_rejects' );

add_action('admin_init', 'yt_to_posts_feed_init');
add_action('admin_menu', 'yt_to_posts_feed_menu');
add_action('admin_menu', 'yt_to_posts_options_page');

add_filter('yt_to_posts_options_page_render', 'yt_to_posts_register_options');


/* -----------------------------------------------------------------------------
 *  WP Ajax stuff
  ---------------------------------------------------------------------------- */

add_action("wp_ajax_yt_to_posts_insertPost", "yt_to_posts_insertPost");
add_action("wp_ajax_nopriv_yt_to_posts_insertPost", "yt_to_posts_insertPost");

add_action("wp_ajax_yt_to_posts_getPostTypeCats", "yt_to_posts_getPostTypeCats");
add_action("wp_ajax_nopriv_yt_to_posts_getPostTypeCats", "yt_to_posts_getPostTypeCats");

add_action("wp_ajax_yt_to_posts_api_call", "yt_to_posts_api_call");
add_action("wp_ajax_nopriv_yt_to_posts_api_call", "yt_to_posts_api_call");


add_action("wp_ajax_yt_to_posts_rejectPost", "yt_to_posts_rejectPost");
add_action("wp_ajax_nopriv_yt_to_posts_rejectPost", "yt_to_posts_rejectPost");

add_action("wp_ajax_yt_to_posts_getAllPostSlug", "yt_to_posts_getAllPostSlug");
add_action("wp_ajax_nopriv_yt_to_posts_getAllPostSlug", "yt_to_posts_getAllPostSlug");




/* -----------------------------------------------------------------------------
 *  Declare functions
  ---------------------------------------------------------------------------- */


function yt_to_posts_getStati() {
  return get_post_stati(array(), 'names');
}

function yt_to_posts_getAllPostSlug() { // Returns an array of all Youtube ID (to check dupes)
  $stati = yt_to_posts_getStati();
  $args = array( 'numberposts' => -1, 'post_status' => $stati);
  $posts = get_posts($args);
  $ids = array();
  foreach ($posts as $post) {
    if($post->yt_id != ""){
      $ids[] = $post->yt_id;
    }

  }
  echo json_encode($ids);
  die();
}

function yt_to_posts_getPostTypeCats() { // Returns an array of all Youtube ID (to check dupes)
  $type = $_POST['post_type'];
  $taxs = get_object_taxonomies($type);
  $results = array();


  if(empty($taxs)){
    echo 'empty';
  }
  else {
    foreach ($taxs as $tax) {
      if($tax === "post_tag" || $tax === "post_format"){

      }
      else {
        $args = array(
            'orderby'           => 'name',
            'order'             => 'ASC',
            'hide_empty'        => false,
            'exclude'           => array(),
            'exclude_tree'      => array(),
            'include'           => array(),
            'number'            => '',
            'fields'            => 'all',
            'slug'              => '',
            'parent'            => '',
            'hierarchical'      => true,
            'child_of'          => 0,
            'get'               => '',
            'name__like'        => '',
            'description__like' => '',
            'pad_counts'        => false,
            'offset'            => '',
            'search'            => '',
            'cache_domain'      => 'core'
        );
        $tax_terms = get_terms($tax, $args);
       // var_dump($tax_terms);
        foreach ($tax_terms as $tax_term) {
          if($tax_term){
            $object = new stdClass();
            $object->id = $tax_term->term_id;
            $object->name = $tax_term->name;
            $results[] = $object;

          }

        }
      }


    }
    $results = json_encode($results);
      echo $results;
  }
  die();
}


function yt_check_api_settings() { // Check if Youtube API settings are filled

  if(get_option('yt_to_posts_ck') === ''){
    return false;
  }
  else {
    return true;
  }

}

function yt_to_posts_insertPost(){ // Setting and calling wp_insert_post();

    $replacers = array('/%title%/', '/%description%/', '/%embed%/', '/%thumbnail%/', '/%query%/', '/%user%/', '/%date%/');
    $title = $_POST['title'];
    $description = $_POST['content'];
    $id = $_POST['id'];
    $user = $_POST['username'] === '' ? 'unknown user':$_POST['username'];
    $author = get_option('yt_to_posts_author');
    $imgSrc = $_POST['imgSrc'];
    $query = $_POST['query'];
    $use_video_date = (get_option('yt_to_posts_use_video_date') == 'true');
    $status = get_option('yt_to_posts_post_status');
    $postType = get_option('yt_to_posts_post_type');
    $cat = intval(get_option('yt_to_posts_cat'));
    $title_template = get_option('yt_to_posts_title_format');
    $content_template = get_option('yt_to_posts_content_format');
    $date = date("Y-m-d H:i:s", strtotime($_POST['date']));
    $embed = '[embed]'.$_POST['mediaUrl'].'[/embed]';
    $content = $embed . ' <br> '. $description;
    $templateVals = array($title, $content, $embed, $imgSrc, $query, $user, $date );

    // If template selected, construct the title
    if($title_template !== ''){
        $customTitle = preg_replace( $replacers, $templateVals, $title_template);

        $title = $customTitle;
    }
     if($content_template !== ''){
        $customContent = preg_replace($replacers, $templateVals, $content_template);
        $content = $customContent;
    }

    // Creating new post
    $my_post = array(
      'post_title'    => $title,
      'post_content'  => $content,
      'post_status'   => $status,
      'post_author'   => $author,
      'post_type'     => $postType,
      'post_category' => array($cat)
    );
    if($use_video_date){
      $my_post['post_date'] = $date;
    }

    

    $post_ID = wp_insert_post($my_post);

    // updating post meta
    // if a media is detected, add its source url
    if($imgSrc){
      add_post_meta( $post_ID, 'media_url', $imgSrc, true ) || update_post_meta( $post_ID, 'media_url', $imgSrc );

      add_post_meta( $post_ID, 'yt_id', $id, true );
      // Create and upload thumbnail with $title as description
      $image = media_sideload_image($imgSrc, $title);

      // then find the last image added to the post attachments
      $attachments = get_posts(array('numberposts' => '1', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'DESC', 'orderby' => 'date'));
      // Set this attachment as post thumbnail
      if(sizeof($attachments) > 0){
          set_post_thumbnail($post_ID, $attachments[0]->ID);
      }
    }
    echo 'ok';
    die();
}
function yt_to_posts_rejectPost(){ // Reject post : insert

    $title = $_POST['title'];
    $content = $_POST['content'];
    $id = $_POST['id'];
    $author = $_POST['author'];



    $my_post = array(
      'post_name'     => $id,
      'post_title'    => $title,
      'post_content'  => $content,
      'post_status'   => 'publish',
      'tags_input'    => $author,
      'post_author'   => 1,
      'post_type'     => 't2p_reject'
    );

    wp_insert_post($my_post);

    echo 'ok';
    die();
}

function yt_to_posts_feed_init() {

  load_plugin_textdomain('youtube-to-posts', false, basename( dirname( __FILE__ ) ) . '/i18n' );
}



function yt_to_posts_feed_menu() {

    $page = add_menu_page(
            __('Youtube importer', 'yt_to_posts'), // The Menu Title
            __('Youtube importer', 'yt_to_posts'), // The Page title
            'manage_options', // The capability required for access to this item
            'yt_to_posts_feed', // the slug to use for the page in the URL
            'yt_to_posts_feed_admin',  // The function to call to render the page
            "dashicons-format-gallery",58 // Position
    );

    /* Using registered $page handle to hook script load */
    add_action('admin_print_styles-' . $page, 'yt_to_posts_feed_styles');
    add_action( 'admin_init', 'yt_to_posts_register_options' );
}

function yt_to_posts_feed_styles() {
    /*
     * It will be called only on your plugin admin page, enqueue our script here
     */

    wp_enqueue_script('yt_to_posts_feed_script', plugins_url('/script.js', __FILE__));
    wp_enqueue_style('style', plugins_url('/styles.css', __FILE__));
}


/* -----------------------------------------------------------------------------
 *  Render the admin page
  ---------------------------------------------------------------------------- */

function yt_to_posts_feed_admin() {

    wp_enqueue_script('yt_to_posts_feed_script', plugins_url('/script.js', __FILE__));
    wp_register_script('yt_to_posts_feed_script', plugins_url('/script.js', __FILE__), 'jquery');

    require_once(dirname(__FILE__) . '/admin.php');

}


/* -----------------------------------------------------------------------------
 *  Call to the Tweet API file
  ---------------------------------------------------------------------------- */


function yt_to_posts_api_call(){

    require_once(dirname(__FILE__) ."/youtube-api-interface.php"); // Path to youtube interface
    die();
}


/* -----------------------------------------------------------------------------
 *  Plugin Page options
  ---------------------------------------------------------------------------- */


function yt_to_posts_options_page() {
  add_options_page('Youtube importer', 'Youtube importer', 'manage_options', 'yt-to-posts', 'yt_to_posts_options_page_render');
  wp_enqueue_style('style', plugins_url('/styles.css', __FILE__));

}
function yt_to_posts_options_page_render(){
  require_once(dirname(__FILE__) . '/options.php');

}

function yt_to_posts_register_options() { //register our settings

  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_query' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_post_type' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_post_status' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_cat' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_author' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_query_type' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_number' );
  register_setting( 'yt_to_posts-query-settings-group', 'yt_to_posts_use_video_date' );

  register_setting( 'yt_to_posts-admin-settings-group', 'yt_to_posts_ck' );
  register_setting( 'yt_to_posts-admin-settings-group', 'yt_to_posts_title_format' );
  register_setting( 'yt_to_posts-admin-settings-group', 'yt_to_posts_content_format' );
}


/* -----------------------------------------------------------------------------
 *  Register private post type for rejects
  ---------------------------------------------------------------------------- */

function create_y2p_pt_rejects() {
  register_post_type( 'y2p_reject',
    array(
      'labels' => array(
        'name' => __( 'Y2P rejects' ),
        'singular_name' => __( 'Y2P rejects' )
      ),
      'public' => false,
      'has_archive' => false
    )
  );
}
