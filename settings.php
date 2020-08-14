<?php
if (!defined('ABSPATH')) exit;

global $smsify_domain, $smsify_options, $wpml_active;

?>

<style>
:not(pre)>code, :not(pre)>kbd, :not(pre)>samp {
    font-family: Consolas,monaco,monospace;
    font-size: .875rem;
    color: #f0506e;
    white-space: nowrap;
    padding: 2px 6px;
    background: #f8f8f8;
}

body{
    background-color:#fff !important;
}
h3.title {
border-bottom: 1px solid #7fadff !important;
	padding: 10px;
}
#template_options {
    width: 100%;
    display: inline-block;
    margin: 0;
}
#edit_instructions {
    width: 25%;
    display: inline-block;
    margin: 0;
    padding: 0 10px;
    vertical-align: top;
    margin-top: 1rem;
}
</style>
<?php
function smsify_checked_value($var, $check = false)
{
    global $smsify_options;
    $retval = '';
    if (isset($smsify_options[$var]))
    {
        if ($check)
        {
            if ($smsify_options[$var] == 1)
            {
                $retval = 'checked="checked"';
            }
        }
        else
        {
            $retval = $smsify_options[$var];
        }
    }
    return $retval;
}
?>
<div class="wrap woocommerce">
  <?php settings_errors(); ?>

  <h2>SMSIFY</h2>
  <br/>
  
  <form method="post" action="options.php" id="mainform">
    <?php settings_fields('smsify_settings_group'); ?>
    
    <h3 class="title">ანგარიშის მონაცემები</h3>
    <?php _e('გასაღების მისაღებად ეწვიეთ <a href="http://smsoffice.ge/you/profile/integration/" target="_blank">smsoffice.ge</a>', $smsify_domain); ?>
    <br/>
    
    <table class="form-table">
    <?php
$reg_fields = array(
    'aid' => 'გასაღები',
    'sender' => 'გამგზავნის სახელი',
    'mnumber' => 'ადმინისტრატორის ტელ.',
);

foreach ($reg_fields as $k => $v)
{
?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo $k; ?>"><?php echo $v; ?></label>
              
            </th>
            <td class="forminp">
                <input type="text" id="<?php echo $k; ?>" name="smsify_settings[<?php echo $k; ?>]" size="50" value="<?php echo smsify_checked_value($k); ?>" <?php echo ($k == 'sender') ? 'maxlength="11" ' : ''; ?> <?php echo ($k != 'mnumber') ? 'required="required"' : ''; ?>/>
            </td>
        </tr>
    <?php
}
?>

    </table>
    
    <span id="template_options">
    <h3 class="title">SMS-ის შაბლონები</h3>
    <ol>
       
        <li style="list-style-type: circle;">
            <?php
_e('თქვენ შეგიძლიათ გამოიყენოთ შემდეგი ცვლადები:', $smsify_domain);

$vars = array(
    'id',
    'order_key',
    'billing_first_name',
    'billing_last_name',
    'billing_company',
    'billing_address_1',
    'billing_address_2',
    'billing_city',
    'billing_postcode',
    'billing_country',
    'billing_state',
    'billing_email',
    'billing_phone',
    'shipping_first_name',
    'shipping_last_name',
    'shipping_company',
    'shipping_address_1',
    'shipping_address_2',
    'shipping_city',
    'shipping_postcode',
    'shipping_country',
    'shipping_state',
    'shipping_method',
    'shipping_method_title',
    'payment_method',
    'payment_method_title',
    'order_discount',
    'cart_discount',
    'order_tax',
    'order_shipping',
    'order_shipping_tax',
    'order_total',
    'status',
    'prices_include_tax',
    'tax_display_cart',
    'display_totals_ex_tax',
    'display_cart_ex_tax',
    'order_date',
    'modified_date',
    'customer_message',
    'customer_note',
    'post_status',
    'shop_name',
    'order_product'
);

foreach ($vars as $var)
{
    echo ' <code>%' . $var . '%</code>';
}
?>
        </li>

    </ol>
    
    <table class="form-table">
    
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="signature"><?php _e('ხელმოწერა', $smsify_domain); ?></label>
                <?php _e(wc_help_tip('ტექსტი რომელიც დაემატება ყველა კლიენტის მესიჯს. მაგ: დაგვიკავშირდით - support@yoursite.com') , $smsify_domain); ?>
            </th>
            <td class="forminp">
                <input type="text" id="signature" name="smsify_settings[signature]" size="50" value="<?php echo smsify_checked_value('signature'); ?>"/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="addnumber"><?php _e('დამატებითი ნომრები', $smsify_domain); ?></label>
                <?php _e(wc_help_tip('დამატებით ნომრები სადაც ასევე გაიგზავნება ადმინისტრატორის შეტყობინებები : ნომრები გამოყავით მძიმით') , $smsify_domain); ?>
            </th>
            <td class="forminp">
                <input type="text" id="addnumber" name="smsify_settings[addnumber]" size="50" value="<?php echo smsify_checked_value('addnumber'); ?>"/>
            </td>
        </tr>
    <?php
$templates = array(
    'msg_new_order' => array(
        'New Order <br> ახალი შეკვეთა',
        'მესიჯი, რომელიც გაიგზავნება ახალი შეკვეთის გაფორმებისას',
        isset($smsify_options['msg_new_order']) ? $smsify_options['msg_new_order'] : "შეკვეთა ID - %id% მიღებულია %shop_name%."
    ) ,
    'msg_pending' => array(
        'Pending <br> განხილვის პროცესი',

        'მესიჯი, რომელიც გაიგზავნება როცა გაფორმებული შეკვეთა არის გადახდის რეჟიმში',
        isset($smsify_options['msg_pending']) ? $smsify_options['msg_pending'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთა ელოდება გადახდას. %signature%"
    ) ,
    'msg_on_hold' => array(
        'On-Hold <br> მოლოდინის რეჟიმი ',
        'მესიჯი, რომელიც გაიგზავნება როცა გაფორმებული შეკვეთა გადავა On-Hold რეჟიმში',
        isset($smsify_options['msg_on_hold']) ? $smsify_options['msg_on_hold'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთა ID: %id% არის on-hold - რეჟიმში. %signature%"
    ) ,
    'msg_processing' => array(
        'Order Processing <br> შეკვეთა მუშავდება ',
        'მესიჯი, რომელიც გაიგზავნება როცა შეკვეთა მუშავდება',
        isset($smsify_options['msg_processing']) ? $smsify_options['msg_processing'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთა ID: %id%  მუშავდება. %signature%"
    ) ,
    'msg_completed' => array(
        'Order Completed <br> შეკვეთა დასრულებულია',
        'მესიჯი, რომელიც გაიგზავნება როცა შეკვეთა დასრულდება',
        isset($smsify_options['msg_completed']) ? $smsify_options['msg_completed'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთა ID: %id% დასრულებულია. %signature%"
    ) ,
    'msg_cancelled' => array(
        'Order Cancelled <br> შეკვეთა გაუქმებულია',
        'მესიჯი, რომელიც გაიგზავნება როცა შეკვეთა გაუქმდება',
        isset($smsify_options['msg_cancelled']) ? $smsify_options['msg_cancelled'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთა ID: %id% გაუქმებულია. %signature%"
    ) ,
    'msg_refunded' => array(
        'Payment Refund <br> თანხის დაბრუნება',
        'მესიჯი, რომელიც გაიგზავნება როცა შეკვეთის თანხა დაუბრუნდება კლიენტს',
        isset($smsify_options['msg_refunded']) ? $smsify_options['msg_refunded'] : "ძვირფასო %billing_first_name%, თქვენი შეკვეთის თანხა ID: %id% დაბრუნებულია. თანხის ასახვას შესაძლოა დასჭირდეს რამდენიმე სამუშაო დღე. %signature%"
    ) ,
    'msg_failure' => array(
        'Payment Failure <br> გადახდა ვერ შესრულდა',
        'მესიჯი, რომელიც გაიგზავნება როცა გადახდა ვერ შესრულდება ',
        isset($smsify_options['msg_failure']) ? $smsify_options['msg_failure'] : "ძვირფასო %billing_first_name%, გადახდის მცდელობა წარუმატებელია. გთხოვთ სცადოთ თავიდან. %signature%"
    )
);

$script_cont = "";
foreach ($templates as $k => $a)
{
?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo 'use_' . $k; ?>"><?php _e($a[0], $smsify_domain); ?></label>
                <?php _e(wc_help_tip($a[1]) , $smsify_domain); ?>
            </th>
            <td class="forminp">
                <input id="<?php echo 'use_' . $k; ?>" name="smsify_settings[<?php echo 'use_' . $k; ?>]" type="checkbox" value="1" <?php echo smsify_checked_value('use_' . $k, true); ?> /> <?php _e('მონიშვნა', $smsify_domain); ?>
                <span class="<?php echo $k; ?>">
                    <br/>
                    <input class="msg-template" id="<?php echo $k; ?>" name="smsify_settings[<?php echo $k; ?>]" type="text" size="50" value="<?php echo stripcslashes($a[2]); ?>" readonly="readonly" required="required"/>
                    <a class="<?php echo $k; ?>_link"><?php _e('შესწორება', $smsify_domain); ?></a>
                </span>
            </td>
        </tr>
  
        
    <?php
    $script_cont .= ($smsify_options['use_' . $k] == 1) ? '' : ('$(".' . $k . '").hide();' . PHP_EOL);
    $script_cont .= '$("input#use_' . $k . '").change(function(){$(".' . $k . '").toggle();});' . PHP_EOL;
    $script_cont .= '$(".' . $k . '_link").click(function(){$(".' . $k . ' input").attr("readonly", false).focus();});' . PHP_EOL;
    // $script_cont .= 'defaults["' . $k . '"] = "' . $a[2] . '";' . PHP_EOL;
    
}
?>
    </table>
    
         <?php _e('შეკვეთის სტატუსები იხილეთ: <a href="https://docs.woocommerce.com/document/managing-orders/" target="_blank">აქ</a>', $smsify_domain); ?>
    
    <h3 class="title">დამატებითი პარამეტრები</h3>
    გაქვთ რაიმე კითხვა? გსურთ თქვენს საიტზე მორგება?  დაგვიკავშირდით <a href="mailto:hello@ink.ge">hello@ink.ge</a>
    <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="alt_phone"><?php _e('გამოიყენე ბილინგის ტელ.', $smsify_domain); ?></label>
            </th>
            <td class="forminp">
                <input id="alt_phone" name="smsify_settings[alt_phone]" type="checkbox" value="1" <?php echo smsify_checked_value('alt_phone', true); ?> /> <?php _e('გააგზავნე SMS შეკვეთისას მითითებულ ნომერზე', $smsify_domain); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="log_sms"><?php _e('შეინახე ლოგები', $smsify_domain); ?></label>
            </th>
            <td class="forminp">
                <input id="log_sms" name="smsify_settings[log_sms]" type="checkbox" value="1" <?php echo smsify_checked_value('log_sms', true); ?> /> <?php _e('შეინახე SMS აქტივობის ლოგები', $smsify_domain); ?>
            </td>
        </tr>
    </table>
    </span>

    <p class="submit">
        <input class="button-primary" type="submit" value="<?php _e('დამახსოვრება', $smsify_domain); ?>"  name="submit" id="submit" />
    </p>
  </form>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($){
       <?php echo $script_cont; ?>
       
       if ( $('#aid').val() == '' || $('#sender').val() == '' )
           $('#template_options, #edit_instructions').hide();
    });
</script>
