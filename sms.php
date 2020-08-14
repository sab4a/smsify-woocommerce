<?php
/*
Plugin Name: SMSIFY WooCommerce
Version: 1.2.0
Plugin URI: https:/ink.ge/
Description: Sends WooCommerce order status notifications as SMS , to administrator and clients
Author URI: http://ink.ge/
Author: Saba Meskhi
Tested up to: 5.4.2
*/

//Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit();
}

$smsify_plugin_name = 'WooCommerce SMS Notification';
$smsify_plugin_file = plugin_basename(__FILE__);
$smsify_domain = 'smsify';
load_plugin_textdomain($smsify_domain, false, dirname($smsify_plugin_file) . '/languages');

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{
    include ('core.php');
}
else
{
    add_action('admin_notices', 'smsify_require_wc');
    function smsify_require_wc()
    {
        global $smsify_plugin_name, $smsify_domain;
        echo '<div class="error fade" id="message"><h3>' . $smsify_plugin_name . '</h3><h4>' . __("This plugin requires WooCommerce", $smsify_domain) . '</h4></div>';
        deactivate_plugins($smsify_plugin_file);
    }
}

//Add links
add_filter("plugin_action_links_$smsify_plugin_file", 'smsify_add_action_links');
function smsify_add_action_links($links)
{
    global $smsify_domain;
    $links[] = '<a href="' . admin_url("admin.php?page=$smsify_domain") . '">Settings</a>';
    return $links;
}

//Handle uninstallation
register_uninstall_hook(__FILE__, 'smsify_uninstaller');
function smsify_uninstaller()
{
    delete_option('smsify_settings');
}

