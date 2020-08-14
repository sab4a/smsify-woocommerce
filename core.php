<?php
if (!defined('ABSPATH')) exit;

//Get settings
$smsify_options = get_option('smsify_settings');
function smsify_field($var)
{
    global $smsify_options;
    return isset($smsify_options[$var]) ? $smsify_options[$var] : '';
}

//Check whether WPML is active
$wpml_active = function_exists('icl_object_id');
$wpml_regstr = function_exists('icl_register_string');
$wpml_trnslt = function_exists('icl_translate');

//registering strings to WPML
function smsify_register_string($str)
{
    global $smsify_options, $wpml_active, $wpml_regstr, $smsify_domain;
    if ($wpml_active)
    {
        ($wpml_regstr) ? icl_register_string($smsify_domain, $str, $smsify_options[$str]) : do_action('wpml_register_single_string', $smsify_domain, $str, $smsify_options[$str]);
    }
}

//fetch strings from WPML
function smsify_fetch_string($str)
{
    global $smsify_options, $wpml_active, $wpml_trnslt, $smsify_domain;
    if ($wpml_active)
    {
        return ($wpml_trnslt) ? icl_translate($smsify_domain, $str, $smsify_options[$str]) : apply_filters('wpml_translate_single_string', $smsify_options[$str], $smsify_domain, $str);
    }
    return smsify_field($str);
}

//Add phone field to Shipping Address
add_filter('woocommerce_checkout_fields', 'smsify_add_shipping_phone_field');
function smsify_add_shipping_phone_field($fields)
{
    if (!isset($fields['shipping']['shipping_phone']))
    {
        $fields['shipping']['shipping_phone'] = array(
            'label' => __('Mobile Phone', 'woocommerce') ,
            'placeholder' => _x('Mobile Phone', 'placeholder', 'woocommerce') ,
            'required' => false,
            'class' => array(
                'form-row-wide'
            ) ,
            'clear' => true
        );
    }
    return $fields;
}

//Display shipping phone field on order edit page
add_action('woocommerce_admin_order_data_after_shipping_address', 'smsify_display_shipping_phone_field', 10, 1);
function smsify_display_shipping_phone_field($order)
{
    echo '<p><strong>' . __('Shipping Phone') . ':</strong> ' . get_post_meta($order->get_id() , '_shipping_phone', true) . '</p>';
}

//Change label of billing phone field
add_filter('woocommerce_checkout_fields', 'smsify_phone_field_label');
function smsify_phone_field_label($fields)
{
    $fields['billing']['billing_phone']['label'] = 'Mobile Phone';
    return $fields;
}

//Initialize the plugin
add_action('init', 'smsify_initialize');
function smsify_initialize()
{
    smsify_register_string('msg_new_order');
    smsify_register_string('msg_pending');
    smsify_register_string('msg_on_hold');
    smsify_register_string('msg_processing');
    smsify_register_string('msg_completed');
    smsify_register_string('msg_cancelled');
    smsify_register_string('msg_refunded');
    smsify_register_string('msg_failure');
}

//Add settings page to woocommerce admin menu
add_action('admin_menu', 'smsify_admin_menu', 20);
function smsify_admin_menu()
{
    global $smsify_domain;
    add_submenu_page('woocommerce', __('SMSIFY', $smsify_domain) , __('SMSIFY', $smsify_domain) , 'manage_woocommerce', $smsify_domain, $smsify_domain . '_tab');
    function smsify_tab()
    {
        include ('settings.php');
    }
}

//Add screen id for enqueuing WooCommerce scripts
add_filter('woocommerce_screen_ids', 'smsify_screen_id');
function smsify_screen_id($screen)
{
    global $smsify_domain;
    $screen[] = 'woocommerce_page_' . $smsify_domain;
    return $screen;
}

//Set the options
add_action('admin_init', 'smsify_regiser_settings');
function smsify_regiser_settings()
{
    register_setting('smsify_settings_group', 'smsify_settings');
}

//Schedule notifications for new order
if (smsify_field('use_msg_new_order') == 1) add_action('woocommerce_new_order', 'smsify_owner_notification', 20);
function smsify_owner_notification($order_id)
{
    if (smsify_field('mnumber') == '') return;
    $order = new WC_Order($order_id);
    $template = smsify_fetch_string('msg_new_order');
    $message = smsify_process_variables($template, $order);
    $owners_phone = smsify_process_phone($order, smsify_field('mnumber') , false, true);
    smsify_send_sms($owners_phone, $message);
    $additional_numbers = smsify_field('addnumber');
    if (!empty($additional_numbers))
    {
        $numbers = explode(",", $additional_numbers);
        foreach ($numbers as $number)
        {
            $phone = smsify_process_phone($order, trim($number) , false, true);
            smsify_send_sms($phone, $message);
        }
    }
}

//Schedule notifications for order status change
add_action('woocommerce_order_status_changed', 'smsify_process_status', 10, 3);
function smsify_process_status($order_id, $old_status, $status)
{
    $order = new WC_Order($order_id);
    $shipping_phone = false;
    $phone = $order->get_billing_phone();

    //If have to send messages to shipping phone
    if (smsify_field('alt_phone') == 1)
    {
        $phone = get_post_meta($order->get_id() , '_shipping_phone', true);
        $shipping_phone = true;
    }

    //Remove old 'wc-' prefix from the order status
    $status = str_replace('wc-', '', $status);

    //Sanitize the phone number
    $phone = smsify_process_phone($order, $phone, $shipping_phone);

    //Get the message corresponding to order status
    $message = "";
    switch ($status)
    {
        case 'pending':
            if (smsify_field('use_msg_pending') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_new_order') , $order);
            break;
        case 'on-hold':
            if (smsify_field('use_msg_on_hold') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_on_hold') , $order);
            break;
        case 'processing':
            if (smsify_field('use_msg_processing') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_processing') , $order);
            break;
        case 'completed':
            if (smsify_field('use_msg_completed') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_completed') , $order);
            break;
        case 'cancelled':
            if (smsify_field('use_msg_cancelled') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_cancelled') , $order);
            break;
        case 'refunded':
            if (smsify_field('use_msg_refunded') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_refunded') , $order);
            break;
        case 'failed':
            if (smsify_field('use_msg_failure') == 1) $message = smsify_process_variables(smsify_fetch_string('msg_failure') , $order);
            break;

        }

        //Send the SMS
        if (!empty($message)) smsify_send_sms($phone, $message);
    }

    function smsify_message_encode($message)
    {
        return urlencode(html_entity_decode($message, ENT_QUOTES, "UTF-8"));
    }

    function smsify_process_phone($order, $phone, $shipping = false, $owners_phone = false)
    {
        //Sanitize phone number
        $phone = str_replace(array(
            '+',
            '-'
        ) , '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));
        $phone = ltrim($phone, '0');

        //Obtain country code prefix
        $country = WC()
            ->countries
            ->get_base_country();
        if (!$owners_phone)
        {
            $country = $shipping ? $order->get_shipping_country() : $order->get_billing_country();
        }
        $intl_prefix = smsify_country_prefix($country);

        //Check for already included prefix
        preg_match("/(\d{1,4})[0-9.\- ]+/", $phone, $prefix);

        //If prefix hasn't been added already, add it
        if (strpos($prefix[1], $intl_prefix) !== 0)
        {
            $phone = $intl_prefix . $phone;
        }
        $phone = preg_replace('/[^0-9.]+/', '', $phone);
		
        return $phone;
    }

    function smsify_process_variables($message, $order)
    {
        $sms_strings = array(
            "id",
            "status",
            "prices_include_tax",
            "tax_display_cart",
            "display_totals_ex_tax",
            "display_cart_ex_tax",
            "order_date",
            "modified_date",
            "customer_message",
            "customer_note",
            "post_status",
            "shop_name",
            "note",
            "order_product"
        );
        $smsify_variables = array(
            "order_key",
            "billing_first_name",
            "billing_last_name",
            "billing_company",
            "billing_address_1",
            "billing_address_2",
            "billing_city",
            "billing_postcode",
            "billing_country",
            "billing_state",
            "billing_email",
            "billing_phone",
            "shipping_first_name",
            "shipping_last_name",
            "shipping_company",
            "shipping_address_1",
            "shipping_address_2",
            "shipping_city",
            "shipping_postcode",
            "shipping_country",
            "shipping_state",
            "shipping_method",
            "shipping_method_title",
            "payment_method",
            "payment_method_title",
            "order_discount",
            "cart_discount",
            "order_tax",
            "order_shipping",
            "order_shipping_tax",
            "order_total"
        );
        $specials = array(
            "order_date",
            "modified_date",
            "shop_name",
            "id",
            "order_product",
            'signature'
        );
        $order_variables = get_post_custom($order->get_id()); //WooCommerce 2.1
        

        preg_match_all("/%(.*?)%/", $message, $search);
        foreach ($search[1] as $variable)
        {
            $variable = strtolower($variable);

            if (!in_array($variable, $sms_strings) && !in_array($variable, $smsify_variables) && !in_array($variable, $specials) && !in_array($variable))
            {
                continue;
            }

            if (!in_array($variable, $specials))
            {
                if (in_array($variable, $sms_strings))
                {
                    $message = str_replace("%" . $variable . "%", $order->$variable, $message); //Standard fields
                    
                }
                else if (in_array($variable, $smsify_variables))
                {
                    $message = str_replace("%" . $variable . "%", $order_variables["_" . $variable][0], $message); //Meta fields
                    
                }
            }
            else if ($variable == "order_date" || $variable == "modified_date")
            {
                $message = str_replace("%" . $variable . "%", date_i18n(woocommerce_date_format() , strtotime($order->$variable)) , $message);
            }
            else if ($variable == "shop_name")
            {
                $message = str_replace("%" . $variable . "%", get_bloginfo('name') , $message);
            }
            else if ($variable == "id")
            {
                $message = str_replace("%" . $variable . "%", $order->get_order_number() , $message);
            }
            else if ($variable == "order_product")
            {
                $products = $order->get_items();
                $quantity = $products[key($products) ]['name'];
                if (strlen($quantity) > 10)
                {
                    $quantity = substr($quantity, 0, 10) . "...";
                }
                if (count($products) > 1)
                {
                    $quantity .= " (+" . (count($products) - 1) . ")";
                }
                $message = str_replace("%" . $variable . "%", $quantity, $message);
            }
            else if ($variable == "signature")
            {
                $message = str_replace("%" . $variable . "%", smsify_field('signature') , $message);
            }
        }
        return $message;
    }

    function smsify_country_prefix($country = '')
    {
        $countries = array(
            'AC' => '247',
            'AD' => '376',
            'AE' => '971',
            'AF' => '93',
            'AG' => '1268',
            'AI' => '1264',
            'AL' => '355',
            'AM' => '374',
            'AO' => '244',
            'AQ' => '672',
            'AR' => '54',
            'AS' => '1684',
            'AT' => '43',
            'AU' => '61',
            'AW' => '297',
            'AX' => '358',
            'AZ' => '994',
            'BA' => '387',
            'BB' => '1246',
            'BD' => '880',
            'BE' => '32',
            'BF' => '226',
            'BG' => '359',
            'BH' => '973',
            'BI' => '257',
            'BJ' => '229',
            'BL' => '590',
            'BM' => '1441',
            'BN' => '673',
            'BO' => '591',
            'BQ' => '599',
            'BR' => '55',
            'BS' => '1242',
            'BT' => '975',
            'BW' => '267',
            'BY' => '375',
            'BZ' => '501',
            'CA' => '1',
            'CC' => '61',
            'CD' => '243',
            'CF' => '236',
            'CG' => '242',
            'CH' => '41',
            'CI' => '225',
            'CK' => '682',
            'CL' => '56',
            'CM' => '237',
            'CN' => '86',
            'CO' => '57',
            'CR' => '506',
            'CU' => '53',
            'CV' => '238',
            'CW' => '599',
            'CX' => '61',
            'CY' => '357',
            'CZ' => '420',
            'DE' => '49',
            'DJ' => '253',
            'DK' => '45',
            'DM' => '1767',
            'DO' => '1809',
            'DO' => '1829',
            'DO' => '1849',
            'DZ' => '213',
            'EC' => '593',
            'EE' => '372',
            'EG' => '20',
            'EH' => '212',
            'ER' => '291',
            'ES' => '34',
            'ET' => '251',
            'EU' => '388',
            'FI' => '358',
            'FJ' => '679',
            'FK' => '500',
            'FM' => '691',
            'FO' => '298',
            'FR' => '33',
            'GA' => '241',
            'GB' => '44',
            'GD' => '1473',
            'GE' => '995',
            'GF' => '594',
            'GG' => '44',
            'GH' => '233',
            'GI' => '350',
            'GL' => '299',
            'GM' => '220',
            'GN' => '224',
            'GP' => '590',
            'GQ' => '240',
            'GR' => '30',
            'GT' => '502',
            'GU' => '1671',
            'GW' => '245',
            'GY' => '592',
            'HK' => '852',
            'HN' => '504',
            'HR' => '385',
            'HT' => '509',
            'HU' => '36',
            'ID' => '62',
            'IE' => '353',
            'IL' => '972',
            'IM' => '44',
            'IN' => '91',
            'IO' => '246',
            'IQ' => '964',
            'IR' => '98',
            'IS' => '354',
            'IT' => '39',
            'JE' => '44',
            'JM' => '1876',
            'JO' => '962',
            'JP' => '81',
            'KE' => '254',
            'KG' => '996',
            'KH' => '855',
            'KI' => '686',
            'KM' => '269',
            'KN' => '1869',
            'KP' => '850',
            'KR' => '82',
            'KW' => '965',
            'KY' => '1345',
            'KZ' => '7',
            'LA' => '856',
            'LB' => '961',
            'LC' => '1758',
            'LI' => '423',
            'LK' => '94',
            'LR' => '231',
            'LS' => '266',
            'LT' => '370',
            'LU' => '352',
            'LV' => '371',
            'LY' => '218',
            'MA' => '212',
            'MC' => '377',
            'MD' => '373',
            'ME' => '382',
            'MF' => '590',
            'MG' => '261',
            'MH' => '692',
            'MK' => '389',
            'ML' => '223',
            'MM' => '95',
            'MN' => '976',
            'MO' => '853',
            'MP' => '1670',
            'MQ' => '596',
            'MR' => '222',
            'MS' => '1664',
            'MT' => '356',
            'MU' => '230',
            'MV' => '960',
            'MW' => '265',
            'MX' => '52',
            'MY' => '60',
            'MZ' => '258',
            'NA' => '264',
            'NC' => '687',
            'NE' => '227',
            'NF' => '672',
            'NG' => '234',
            'NI' => '505',
            'NL' => '31',
            'NO' => '47',
            'NP' => '977',
            'NR' => '674',
            'NU' => '683',
            'NZ' => '64',
            'OM' => '968',
            'PA' => '507',
            'PE' => '51',
            'PF' => '689',
            'PG' => '675',
            'PH' => '63',
            'PK' => '92',
            'PL' => '48',
            'PM' => '508',
            'PR' => '1787',
            'PR' => '1939',
            'PS' => '970',
            'PT' => '351',
            'PW' => '680',
            'PY' => '595',
            'QA' => '974',
            'QN' => '374',
            'QS' => '252',
            'QY' => '90',
            'RE' => '262',
            'RO' => '40',
            'RS' => '381',
            'RU' => '7',
            'RW' => '250',
            'SA' => '966',
            'SB' => '677',
            'SC' => '248',
            'SD' => '249',
            'SE' => '46',
            'SG' => '65',
            'SH' => '290',
            'SI' => '386',
            'SJ' => '47',
            'SK' => '421',
            'SL' => '232',
            'SM' => '378',
            'SN' => '221',
            'SO' => '252',
            'SR' => '597',
            'SS' => '211',
            'ST' => '239',
            'SV' => '503',
            'SX' => '1721',
            'SY' => '963',
            'SZ' => '268',
            'TA' => '290',
            'TC' => '1649',
            'TD' => '235',
            'TG' => '228',
            'TH' => '66',
            'TJ' => '992',
            'TK' => '690',
            'TL' => '670',
            'TM' => '993',
            'TN' => '216',
            'TO' => '676',
            'TR' => '90',
            'TT' => '1868',
            'TV' => '688',
            'TW' => '886',
            'TZ' => '255',
            'UA' => '380',
            'UG' => '256',
            'UK' => '44',
            'US' => '1',
            'UY' => '598',
            'UZ' => '998',
            'VA' => '379',
            'VA' => '39',
            'VC' => '1784',
            'VE' => '58',
            'VG' => '1284',
            'VI' => '1340',
            'VN' => '84',
            'VU' => '678',
            'WF' => '681',
            'WS' => '685',
            'XC' => '991',
            'XD' => '888',
            'XG' => '881',
            'XL' => '883',
            'XN' => '857',
            'XN' => '858',
            'XN' => '870',
            'XP' => '878',
            'XR' => '979',
            'XS' => '808',
            'XT' => '800',
            'XV' => '882',
            'YE' => '967',
            'YT' => '262',
            'ZA' => '27',
            'ZM' => '260',
            'ZW' => '263'
        );

        return ($country == '') ? $countries : (isset($countries[$country]) ? $countries[$country] : '');
    }

    function smsify_remote_get($url)
    {
		$response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response))
        {
            $response = $response->get_error_message();
        }
        elseif (is_array($response))
        {
            $response = $response['body'];
        }
        return $response;
    }

    function smsify_send_sms($phone, $message)
    {
        $aid = smsify_field('aid');
        $sender = smsify_field('sender');
        smsify_send_sms_text($phone, $message, $aid, $sender);
    }

    function smsify_send_sms_text($phone, $message, $aid, $sender)
    {
        global $smsify_domain;

        //Don't send the SMS if required fields are missing
        if (empty($phone) || empty($message) || empty($aid) || empty($sender)) return;

        //Send the SMS by calling the API
        $message = smsify_message_encode($message);

        $fetchurl = "http://smsoffice.ge/api/v2/send/?key=$aid&destination=$phone&sender=$sender&content=$message";

        $response = smsify_remote_get($fetchurl);

        //Log the response
        if (1 == smsify_field('log_sms'))
        {
            $log_txt = __('Mobile number: ', $smsify_domain) . $phone . PHP_EOL;
			$log_txt .= __('Message: ', $smsify_domain) . rawurldecode($message) . PHP_EOL;
            $log_txt .= __('Gateway response: ', $smsify_domain) . $response . PHP_EOL;
            file_put_contents(__DIR__ . '/sms_log.txt', $log_txt, FILE_APPEND);
        }
    }

    function smsify_sanitize_data($data)
    {
        $data = (!empty($data)) ? sanitize_text_field($data) : '';
        $data = preg_replace('/[^0-9]/', '', $data);
        return ltrim($data, '0');
    }

    function smsify_country_name($country = '')
    {
        $countries = array(
            "AL" => 'Albania',
            "DZ" => 'Algeria',
            "AS" => 'American Samoa',
            "AD" => 'Andorra',
            "AO" => 'Angola',
            "AI" => 'Anguilla',
            "AQ" => 'Antarctica',
            "AG" => 'Antigua and Barbuda',
            "AR" => 'Argentina',
            "AM" => 'Armenia',
            "AW" => 'Aruba',
            "AU" => 'Australia',
            "AT" => 'Austria',
            "AZ" => 'Azerbaijan',
            "BS" => 'Bahamas',
            "BH" => 'Bahrain',
            "BD" => 'Bangladesh',
            "BB" => 'Barbados',
            "BY" => 'Belarus',
            "BE" => 'Belgium',
            "BZ" => 'Belize',
            "BJ" => 'Benin',
            "BM" => 'Bermuda',
            "BT" => 'Bhutan',
            "BO" => 'Bolivia',
            "BA" => 'Bosnia and Herzegovina',
            "BW" => 'Botswana',
            "BV" => 'Bouvet Island',
            "BR" => 'Brazil',
            "BQ" => 'British Antarctic Territory',
            "IO" => 'British Indian Ocean Territory',
            "VG" => 'British Virgin Islands',
            "BN" => 'Brunei',
            "BG" => 'Bulgaria',
            "BF" => 'Burkina Faso',
            "BI" => 'Burundi',
            "KH" => 'Cambodia',
            "CM" => 'Cameroon',
            "CA" => 'Canada',
            "CT" => 'Canton and Enderbury Islands',
            "CV" => 'Cape Verde',
            "KY" => 'Cayman Islands',
            "CF" => 'Central African Republic',
            "TD" => 'Chad',
            "CL" => 'Chile',
            "CN" => 'China',
            "CX" => 'Christmas Island',
            "CC" => 'Cocos [Keeling] Islands',
            "CO" => 'Colombia',
            "KM" => 'Comoros',
            "CG" => 'Congo - Brazzaville',
            "CD" => 'Congo - Kinshasa',
            "CK" => 'Cook Islands',
            "CR" => 'Costa Rica',
            "HR" => 'Croatia',
            "CU" => 'Cuba',
            "CY" => 'Cyprus',
            "CZ" => 'Czech Republic',
            "CI" => 'Côte d’Ivoire',
            "DK" => 'Denmark',
            "DJ" => 'Djibouti',
            "DM" => 'Dominica',
            "DO" => 'Dominican Republic',
            "NQ" => 'Dronning Maud Land',
            "DD" => 'East Germany',
            "EC" => 'Ecuador',
            "EG" => 'Egypt',
            "SV" => 'El Salvador',
            "GQ" => 'Equatorial Guinea',
            "ER" => 'Eritrea',
            "EE" => 'Estonia',
            "ET" => 'Ethiopia',
            "FK" => 'Falkland Islands',
            "FO" => 'Faroe Islands',
            "FJ" => 'Fiji',
            "FI" => 'Finland',
            "FR" => 'France',
            "GF" => 'French Guiana',
            "PF" => 'French Polynesia',
            "TF" => 'French Southern Territories',
            "FQ" => 'French Southern and Antarctic Territories',
            "GA" => 'Gabon',
            "GM" => 'Gambia',
            "GE" => 'Georgia',
            "DE" => 'Germany',
            "GH" => 'Ghana',
            "GI" => 'Gibraltar',
            "GR" => 'Greece',
            "GL" => 'Greenland',
            "GD" => 'Grenada',
            "GP" => 'Guadeloupe',
            "GU" => 'Guam',
            "GT" => 'Guatemala',
            "GG" => 'Guernsey',
            "GN" => 'Guinea',
            "GW" => 'Guinea-Bissau',
            "GY" => 'Guyana',
            "HT" => 'Haiti',
            "HM" => 'Heard Island and McDonald Islands',
            "HN" => 'Honduras',
            "HK" => 'Hong Kong SAR China',
            "HU" => 'Hungary',
            "IS" => 'Iceland',
            "IN" => 'India',
            "ID" => 'Indonesia',
            "IR" => 'Iran',
            "IQ" => 'Iraq',
            "IE" => 'Ireland',
            "IM" => 'Isle of Man',
            "IL" => 'Israel',
            "IT" => 'Italy',
            "JM" => 'Jamaica',
            "JP" => 'Japan',
            "JE" => 'Jersey',
            "JT" => 'Johnston Island',
            "JO" => 'Jordan',
            "KZ" => 'Kazakhstan',
            "KE" => 'Kenya',
            "KI" => 'Kiribati',
            "KW" => 'Kuwait',
            "KG" => 'Kyrgyzstan',
            "LA" => 'Laos',
            "LV" => 'Latvia',
            "LB" => 'Lebanon',
            "LS" => 'Lesotho',
            "LR" => 'Liberia',
            "LY" => 'Libya',
            "LI" => 'Liechtenstein',
            "LT" => 'Lithuania',
            "LU" => 'Luxembourg',
            "MO" => 'Macau SAR China',
            "MK" => 'Macedonia',
            "MG" => 'Madagascar',
            "MW" => 'Malawi',
            "MY" => 'Malaysia',
            "MV" => 'Maldives',
            "ML" => 'Mali',
            "MT" => 'Malta',
            "MH" => 'Marshall Islands',
            "MQ" => 'Martinique',
            "MR" => 'Mauritania',
            "MU" => 'Mauritius',
            "YT" => 'Mayotte',
            "FX" => 'Metropolitan France',
            "MX" => 'Mexico',
            "FM" => 'Micronesia',
            "MI" => 'Midway Islands',
            "MD" => 'Moldova',
            "MC" => 'Monaco',
            "MN" => 'Mongolia',
            "ME" => 'Montenegro',
            "MS" => 'Montserrat',
            "MA" => 'Morocco',
            "MZ" => 'Mozambique',
            "MM" => 'Myanmar [Burma]',
            "NA" => 'Namibia',
            "NR" => 'Nauru',
            "NP" => 'Nepal',
            "NL" => 'Netherlands',
            "AN" => 'Netherlands Antilles',
            "NT" => 'Neutral Zone',
            "NC" => 'New Caledonia',
            "NZ" => 'New Zealand',
            "NI" => 'Nicaragua',
            "NE" => 'Niger',
            "NG" => 'Nigeria',
            "NU" => 'Niue',
            "NF" => 'Norfolk Island',
            "KP" => 'North Korea',
            "VD" => 'North Vietnam',
            "MP" => 'Northern Mariana Islands',
            "NO" => 'Norway',
            "OM" => 'Oman',
            "PC" => 'Pacific Islands Trust Territory',
            "PK" => 'Pakistan',
            "PW" => 'Palau',
            "PS" => 'Palestinian Territories',
            "PA" => 'Panama',
            "PZ" => 'Panama Canal Zone',
            "PG" => 'Papua New Guinea',
            "PY" => 'Paraguay',
            "YD" => 'People\'s Democratic Republic of Yemen',
            "PE" => 'Peru',
            "PH" => 'Philippines',
            "PN" => 'Pitcairn Islands',
            "PL" => 'Poland',
            "PT" => 'Portugal',
            "PR" => 'Puerto Rico',
            "QA" => 'Qatar',
            "RO" => 'Romania',
            "RU" => 'Russia',
            "RW" => 'Rwanda',
            "RE" => 'Réunion',
            "BL" => 'Saint Barthélemy',
            "SH" => 'Saint Helena',
            "KN" => 'Saint Kitts and Nevis',
            "LC" => 'Saint Lucia',
            "MF" => 'Saint Martin',
            "PM" => 'Saint Pierre and Miquelon',
            "VC" => 'Saint Vincent and the Grenadines',
            "WS" => 'Samoa',
            "SM" => 'San Marino',
            "SA" => 'Saudi Arabia',
            "SN" => 'Senegal',
            "RS" => 'Serbia',
            "CS" => 'Serbia and Montenegro',
            "SC" => 'Seychelles',
            "SL" => 'Sierra Leone',
            "SG" => 'Singapore',
            "SK" => 'Slovakia',
            "SI" => 'Slovenia',
            "SB" => 'Solomon Islands',
            "SO" => 'Somalia',
            "ZA" => 'South Africa',
            "GS" => 'South Georgia and the South Sandwich Islands',
            "KR" => 'South Korea',
            "ES" => 'Spain',
            "LK" => 'Sri Lanka',
            "SD" => 'Sudan',
            "SR" => 'Suriname',
            "SJ" => 'Svalbard and Jan Mayen',
            "SZ" => 'Swaziland',
            "SE" => 'Sweden',
            "CH" => 'Switzerland',
            "SY" => 'Syria',
            "ST" => 'São Tomé and Príncipe',
            "TW" => 'Taiwan',
            "TJ" => 'Tajikistan',
            "TZ" => 'Tanzania',
            "TH" => 'Thailand',
            "TL" => 'Timor-Leste',
            "TG" => 'Togo',
            "TK" => 'Tokelau',
            "TO" => 'Tonga',
            "TT" => 'Trinidad and Tobago',
            "TN" => 'Tunisia',
            "TR" => 'Turkey',
            "TM" => 'Turkmenistan',
            "TC" => 'Turks and Caicos Islands',
            "TV" => 'Tuvalu',
            "UM" => 'U.S. Minor Outlying Islands',
            "PU" => 'U.S. Miscellaneous Pacific Islands',
            "VI" => 'U.S. Virgin Islands',
            "UG" => 'Uganda',
            "UA" => 'Ukraine',
            "SU" => 'Union of Soviet Socialist Republics',
            "AE" => 'United Arab Emirates',
            "GB" => 'United Kingdom',
            "US" => 'United States',
            "ZZ" => 'Unknown or Invalid Region',
            "UY" => 'Uruguay',
            "UZ" => 'Uzbekistan',
            "VU" => 'Vanuatu',
            "VA" => 'Vatican City',
            "VE" => 'Venezuela',
            "VN" => 'Vietnam',
            "WK" => 'Wake Island',
            "WF" => 'Wallis and Futuna',
            "EH" => 'Western Sahara',
            "YE" => 'Yemen',
            "ZM" => 'Zambia',
            "ZW" => 'Zimbabwe',
            "AX" => 'Åland Islands',
        );

        return ($country == '') ? $countries : (isset($countries[$country]) ? $countries[$country] : '');
    }

    function smsify_get_user_by_phone($phone_number)
    {
        return reset(get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone_number,
            'number' => 1,
            'fields' => 'ids',
            'count_total' => false
        )));
    }
    
