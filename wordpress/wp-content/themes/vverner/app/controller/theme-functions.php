<?php

/**
 * Função para Manutenção de páginas
 * @param array|string $location O local onde está em manutenção, passar "all" irá resultar em manutenção em todo o site
 */
function vvtheme_maintenance($location = 'all')
{
    global $post;

    if ($location === 'all') return true;
    if (is_array($location) && in_array($post->post_name, $location))  return true;
    if ($post->post_name === $location) return true;

    return false;
}

/**
 * Função para debug no tema
 */
function vvtheme_debug()
{
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG){
        ini_set('error_log', get_stylesheet_directory() . '/debug.log');
    }
}

/**
 * Função para buscar view
 * @param object|string $view a view para ser apresentada
 * @return false|string False se não existir a view, String com o path do arquivo
 */
function vvtheme_get_view_file($view)
{
    $dir  = '/';
    $file = is_string($view) ? $view : $view->post_name;

    if ( is_object($view) && $view->post_parent ) {
        $parent   = get_post($view->post_parent);
        $dir     .= $parent->post_name . '/';
    }

    $filename = VV_VIEWS . $dir . $file . '.php';

    return file_exists($filename) ? $filename : false;
}
