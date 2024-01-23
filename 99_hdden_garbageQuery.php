<?php
/*
Plugin Name: HDDen Garbage Query Remove
Description: Выполняет 301 редирект от URL с неизвестными get-параметрами (против дублирования и HTTP-флуда)
Version: 1.0.0
Author: HDDen
*/

if (!defined( 'ABSPATH' )){
    die();
}

if (!empty($_SERVER["QUERY_STRING"]) 
    && is_readable(__DIR__ . '/hdden_garbageQuery/hdden_garbageQuery_allowed.php') 
    && (isset($_SERVER['REQUEST_METHOD']) && strcasecmp($_SERVER['REQUEST_METHOD'], "GET") == 0) 
    && hdden_garbageQuery_is_this_page_workable()) {

    hdden_garbageQuery();
}

function hdden_garbageQuery(){

    // грузим 
    try {
        // получаем содержимое
        $config = file_get_contents(__DIR__ . '/hdden_garbageQuery/hdden_garbageQuery_allowed.php');

        // удаляем первую строку
        $config = preg_split("/\r\n|\n|\r/", $config);
        unset($config[0]);

        // собираем конфиг
        $config = implode("", $config);

        // декодируем
        $config = json_decode($config, true);
    } catch (\Throwable $e) {

        // здесь ошибка извлечения конфига
        trigger_error("hdden_garbageQuery: config doesn't exists or wrong");

        return false;
    }

    // получить переменные
    if (empty($config['allowed'])){
        return false;
    }

    $query = explode('&', str_replace('?', '', $_SERVER["QUERY_STRING"]));

    // перебрать каждый, лишние отбросить
    $result_str = '';
    foreach ($query as $value){
        if (in_array(strtok($value, '='), $config['allowed'])){
            if ($result_str !== ''){
                $result_str .= '&';
            }
            $result_str .= $value;
        }
    }

    // проверяем, ставим редирект если не совпало с исходным
    if ($result_str !== $_SERVER["QUERY_STRING"]){

        if (
            isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        $result_url = $protocol.$_SERVER['HTTP_HOST'].(strtok($_SERVER['REQUEST_URI'], '?')).($result_str ? '?'.$result_str : '');

        header('X-HDDen-Query: 301');
        header("HTTP/1.1 301 Moved Permanently");
        header( "Location: ".$result_url, true, 301 );
        die();
    }
}

function hdden_garbageQuery_is_this_page_workable(){
    if( 
        hdden_garbageQuery_is_api_request() || 
        ( isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) || 
        ( isset( $_SERVER['REQUEST_URI'] ) && substr( $_SERVER['REQUEST_URI'], 0, 16 ) == '/wp-register.php' ) || 
        ( isset( $_SERVER['REQUEST_URI'] ) && substr( $_SERVER['REQUEST_URI'], 0, 13 ) == '/wp-login.php' ) || 
        ( isset( $_SERVER['REQUEST_METHOD'] ) && strcasecmp( $_SERVER['REQUEST_METHOD'], 'GET' ) != 0 ) || 
        ( !defined('SWCFPC_CACHE_BUSTER' ) && isset( $_GET['swcfpc'] ) ) || 
        ( defined( 'SWCFPC_CACHE_BUSTER' ) && isset( $_GET[SWCFPC_CACHE_BUSTER] ) ) || 
        is_admin() || 
        ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) || 
        ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || 
        ( defined( 'WP_CLI' ) && WP_CLI ) || 
        ( defined( 'DOING_CRON' ) && DOING_CRON ) 
    ) {
        return false;
    }

    return true;
}

function hdden_garbageQuery_is_api_request() {

    // Wordpress standard API
    if( (defined('REST_REQUEST') && REST_REQUEST) || strcasecmp( substr($_SERVER['REQUEST_URI'], 0, 8), '/wp-json' ) == 0 )
        return true;

    // WooCommerce standard API
    if( strcasecmp( substr($_SERVER['REQUEST_URI'], 0, 8), '/wc-api/' ) == 0 )
        return true;

    // WooCommerce standard API
    if( strcasecmp( substr($_SERVER['REQUEST_URI'], 0, 9), '/edd-api/' ) == 0 )
        return true;

    return false;

}