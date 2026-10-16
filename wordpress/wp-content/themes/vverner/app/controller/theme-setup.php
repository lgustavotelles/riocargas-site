<?php

# FRONTEND ASSETS
function vvtheme_frontend_assets()
{ 
   # CSS
   wp_enqueue_style('vv-main', VV_ASSETS . '/css/main.css');

   # JS
   wp_enqueue_script('vv-main', VV_ASSETS . '/js/main.js', ['jquery'], null, true);
   wp_localize_script('vv-main', 'vvData', array(
      'ajaxUrl'  => admin_url('admin-ajax.php')
   ));
}
add_action('wp_enqueue_scripts', 'vvtheme_frontend_assets');