<?php

# Função anonima para ajuste da URL do ACF
add_filter('acf/settings/url', function(){
    return VV_APP_URL . '/fields/';
});

# Função anonima para visibilidade do ACF no wp-admin
add_filter('acf/settings/show_admin', function(){
    return (get_current_user_id() === 1);
});
