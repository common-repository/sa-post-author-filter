<?php

/**
* Plugin Name: Kikx Simple Post Author Filter
* Description: A simple way to filter post authors on admin.
* Requires at least: 5.0 
* Tested up to: 5.2
* Stable tag: 1.0
* Version: 1.0
* Author: kiKx
* Author URI: https://facebook.com/kikx.xkik
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if(!function_exists('wp_get_current_user')) {

    include(ABSPATH . "wp-includes/pluggable.php"); 

}

if(current_user_can('edit_others_posts')){

    add_action('restrict_manage_posts', 'kikxsfilterbyauthor');

}

function kikxsfilterbyauthor() {

    $option = '';

    if(isset($_GET['author'])){

        //make sure the ID is valid

        if(absint($_GET['author'])>0){

            $author = $_GET['author'];

            $user = get_userdata($author);

            $option .= '<option selected value="'.$user->ID.'">'.$user->user_login.' - '.$user->user_email.'</option>';

        }

    }

    echo '<select name="author_select" id="author_select" class="">'.$option.'</select><input type="hidden" value="'.$author.'" name="author" id="author" />';

    wp_nonce_field( 'kikx-ajax-select-nonce', 'kikxsfilterselect' );

}

function kikxsfilterselect(){

    $the_theme = wp_get_theme();

    global $pagenow;

    $single = false;

    //check if it's edit page or list page

    if($pagenow=='post.php' or $pagenow=='post-new.php'){

        $user = get_currentuserinfo();

        $default_id_single = $user->ID;

        $default_id = '';

        $default_login = $user->user_login;

        $single = true;

        //Reset the user dropdown

        add_filter( 'wp_dropdown_users_args', 'kikxsfilterunsetdropdown', 10, 2 );

    }

    else{

        $default_id = '';

        $default_id_single = '';

        $default_login = 'All Users';

    }

    //check if acf is active to avoid conflict (you can add your own list here if it causes conflict)

    if ((!is_plugin_active('advanced-custom-fields/acf.php') and !is_plugin_active('advanced-custom-fields-pro/acf.php')) or $pagenow=='edit.php') {

        wp_enqueue_style( 'kikx-select2-css', plugin_dir_url( __FILE__ ) . '/select2/select2.css', array(), $the_theme->get( 'Version' ) );

        wp_register_script('kikx-select2-script', plugin_dir_url( __FILE__ ) . '/select2/select2.min.js', array('jquery') ); 

    }

    //should only run on edit.php or post.php

    if($pagenow=='edit.php' or $pagenow=='post.php'){

        wp_enqueue_script('kikx-select2-script');

        wp_register_script('kikx-ajax-select-script', plugin_dir_url( __FILE__ ) . '/select2/kikx-ajax-select-script.js', array('jquery') ); 

        wp_enqueue_script('kikx-ajax-select-script');

        wp_localize_script( 'kikx-ajax-select-script', 'kikxsfilterobject', array( 

            'ajaxurl' => admin_url( 'admin-ajax.php' ),

            'default_id' => $default_id,

            'number' => 10,

            'minimum_letters' => 1,

            'default_id_single' => $default_id_single,

            'default_login' => $default_login,

            'single' => $single

        ));

    }

    add_action( 'wp_ajax_kikxajaxselect', 'kikxsfilterajaxselect' );

}

if(current_user_can('edit_others_posts')){

    add_action('admin_init', 'kikxsfilterselect');

}

function kikxsfilterajaxselect(){

    // First check the nonce, if it fails the function will break

    check_ajax_referer( 'kikx-ajax-select-nonce', 'kikxsfilterselect' );

    //check roles that can create posts

    $filter_roles = kikxsfilterallowedroles();

    $results = array();

    //start search

    $search = wp_strip_all_tags($_POST['term']);

    $page = absint($_POST['page']);

    $number = absint($_POST['number']);

    $offset = $page ? ($page - 1) * $number : 0;

    $search_result = new WP_User_Query(array('role__in' => $filter_roles,'offset' => $offset,'number' => $number,'fields' => array('ID','user_login','user_email'),'search_columns' => array('user_email','user_login'),'search' => $search.'*'));

    $search_result = $search_result->get_results();

    $total_result = new WP_User_Query(array('role__in' => $filter_roles,'fields' => array('ID'),'search_columns' => array('user_email','user_login'),'search' => $search.'*'));

    $total_result = count($total_result->get_results());

    $count = 0;

    //check if there are results and the page number is valid

    if(count($search_result)>0 and $page>=0 and $number>0 and $offset>=0) { 

        foreach($search_result as $u){

            $results['result'][$count]['user'] = $u->user_login.' - '.$u->user_email;

            $results['result'][$count]['ids'] = $u->ID;

            $count++;

        }

    }

    //check if there's a next page

    if($total_result>$number){

        $results['pagination']['total'] = $total_result;

    }

    else{

        $results['pagination']['total'] = 0;

    }

    echo json_encode($results);

    die();

}

function kikxsfilterunsetdropdown( $query_args, $r ) {

    global $post;

    $user = get_userdata($post->post_author);

    //get current author

    $option = '<option selected value="'.$user->ID.'">'.$user->user_login.' - '.$user->user_email.'</option>';

    //random role to unset the dropdown
  
    $query_args['role'] = array('8ew4qs');
 
    // Unset the 'who' as this defaults to the 'author' role
    unset( $query_args['who'] );

    //create the select box

    echo '<select name="author_select" id="author_select" class="">'.$option.'</select><input type="hidden" value="'.$user->ID.'" name="post_author_override" id="post_author_override" />';

    wp_nonce_field( 'kikx-ajax-select-nonce', 'kikxsfilterselect' );
 
    return $query_args;

}

function kikxsfiltersuperroles(){

    global $wp_roles;

    $roles = $wp_roles->roles;

    $capability  = 'edit_others_posts';

    foreach($roles as $key=>$value){

        if(array_key_exists($capability,$value['capabilities'])){

            $filter_roles[] = $key;

        }

    }

    return $filter_roles;

}

function kikxsfilterallowedroles(){

    global $wp_roles;

    $roles = $wp_roles->roles;

    $filter_roles[] = array();

    $capability  = 'edit_posts';

    foreach($roles as $key=>$value){

        if(array_key_exists($capability,$value['capabilities'])){

            $filter_roles[] = $key;

        }

    }

    return $filter_roles;

}