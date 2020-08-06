<?php
add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );

function enqueue_parent_styles() {
   wp_enqueue_style('font-awesome',"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css");
   wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
   wp_enqueue_style( 'font-family', 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;1,300;1,400&display=swap');
   wp_enqueue_style( 'slick-css', get_stylesheet_directory_uri().'/slick/slick.css');
   wp_enqueue_style( 'slick-theme', get_stylesheet_directory_uri().'/slick/slick-theme.css');
   wp_enqueue_style( 'child-style', get_stylesheet_directory_uri().'/style.css' );
   wp_enqueue_script( 'child-jquery', 'https://code.jquery.com/jquery-3.5.1.js' );
   wp_enqueue_script( 'slick-script', get_stylesheet_directory_uri().'/slick/slick.min.js' );
   wp_enqueue_script( 'child-script', get_stylesheet_directory_uri().'/script.js' );

}

?>