<?php
add_filter("gform_pre_render", array("GFDibsHook", "preRenderForm")); // hidden input with return url
add_action("gform_confirmation", array("GFDibsHook", "dibsTransition"), 10, 4 ); // before payment on DIBS
add_filter("gform_disable_notification", array("GFDibsHook", 'disableNotifications'), 10, 4); // disabled notification on submit
add_filter("gform_form_tag",  array("GFDibsHook", "formTag"), 10, 2); // shows confirmation message after payment
add_action("init",  array("GFDibsHook", "updateLeadAfterPayment"), 10, 2); // checks if payment received
add_filter("gform_pre_send_email", array("GFDibsHook", "parseNotification"), 10, 1); // send notification mail after payment
add_filter("gform_entries_column_filter", array("GFDibsHook" , "changeColumnData") , 10, 5); // gravity forms backend
add_action("gform_entry_detail", array("GFDibsHook", 'addPaymentDetails'), 10, 2); // gravity forms back end
add_filter("gform_leads_before_export", array("GFDibsHook" , "modifyExportData") , 10, 3); // gravity forms lead export
add_filter("wp_enqueue_scripts", array("GFDibsHook" , "includeFrontendScripts") , 10, 3); // front end assets
add_filter("admin_enqueue_scripts", array("GFDibsHook" , "includeAdminScripts") , 10, 3); // back end assets
add_filter("admin_body_class", array("GFDibsHook" , "addAdminBodyClass")  );


class GFDibsHook{

  public static function addAdminBodyClass( $classes ) {
    $classes .= ' gf-dibs ';

    return $classes;
  }


  public static function setupDBTables() {
    $DAO = new GFDibsDao();
    $DAO->setupTables();
  }


  public static function includeFrontendScripts(){
    wp_enqueue_script('jquery');
    wp_register_script( 'gfdibsuser', plugin_dir_url(__FILE__).'/assets/gfdibs_user.js', null, '1.2.0' );
    wp_enqueue_script('gfdibsuser');
  }


  public static function includeAdminScripts(){
    wp_enqueue_script('jquery');
    wp_register_script( 'gfdibsadmin', plugin_dir_url(__FILE__).'/assets/gfdibs_admin.js', null, '1.2.0' );
    wp_enqueue_script('gfdibsadmin');

    wp_register_style( 'gfdibsadmin_style', plugin_dir_url(__FILE__).'/assets/gfdibs_admin.css', null, '1.2.0' );
    wp_enqueue_style('gfdibsadmin_style');
  }


  public static function preRenderForm($form) {

    $Dao = new GFDibsDao();

    if ( $feed_id = $Dao->isDibsForm($form['id']) ){
      global $post;

      // $feed = $Dao->getDibsMeta($feed_id);

      // hidden input form id
      $Field = new GF_Field_Hidden();

      $Field->type          = 'hidden';
      $Field->label         = 'dibs_form_id';
      $Field->pageNumber    = 1;
      $Field->formId        = $form['id'];

      $Field->id            = 9998;
      $Field->inputName     = 'dibs_form_id';
      $Field->defaultValue  = $form['id'];

      array_push($form['fields'], $Field);


      // hidden input return url
      $Field = new GF_Field_Hidden();

      $Field->type          = 'hidden';
      $Field->label         = 'return_rl';
      $Field->pageNumber    = 1;
      $Field->formId        = $form['id'];

      $Field->id            = 9999;
      $Field->inputName     = 'return_rl';
      $Field->defaultValue  = get_permalink($post->ID );

      array_push($form['fields'], $Field);
    }

    return $form;
  }


  public static function changeColumnData($value, $lead_id, $index, $Entry){
    if ( $index == 'transaction_id'){
      $Dao = new GFDibsDao();
      $Transaction = $Dao->getTransactionByLeadId($Entry['id']);
      $value =  $Transaction->transaction_id;
    }
    else if ( $index == 'order_id'){
      $Dao = new GFDibsDao();
      $Transaction = $Dao->getTransactionByLeadId($Entry['id']);
      $value =  $Transaction->order_id;
    }
    else if ( $index ==  'payment_status'){
      $Dao = new GFDibsDao();
      $Transaction = $Dao->getTransactionByLeadId($Entry['id']);
      $value = ( isset($Transaction->completed) && $Transaction->completed == 1 ) ? 'completed' : 'open' ;
    }
    else if ( $index ==  'payment_amount'){
      $Dao = new GFDibsDao();
      $Transaction = $Dao->getTransactionByLeadId($Entry['id']);
      $value = ( isset($Transaction->amount) && $Transaction->amount > 0 ) ? ($Transaction->amount/100) : 0 ;
    }

    return $value;
  }


  public static function modifyExportData($leads, $form, $offset){
    if ( is_array($leads) ){
      $Dao = new GFDibsDao();
      foreach ($leads as $key => $Lead) {
        $Transaction = $Dao->getTransactionByLeadId($Lead['id']);
        $leads[$key]['transaction_id'] = ( isset($Transaction->transaction_id) && $Transaction->transaction_id ) ? $Transaction->transaction_id : '' ;
        $leads[$key]['payment_date']    = ( isset($Transaction->date_completed) && $Transaction->date_completed ) ? $Transaction->date_completed : '' ;
        $leads[$key]['payment_status'] = ( isset($Transaction->completed) && $Transaction->completed == 1 ) ? 'completed' : 'open' ;
        $leads[$key]['payment_amount'] = ( isset($Transaction->amount) && $Transaction->amount ) ? $Transaction->amount/100 : 0 ;
      }
    }

    return $leads;
  }


  public static function parseNotification( $mail ){
    $Dao = new GFDibsDao();
    $Transaction = null;

    if ( isset($_POST['orderId']) ){ // check if orderId exists
      if ( $Transaction = $Dao->getTransactionByOrderId($_POST['orderId']) ){ // check if orderId is related to a transaction
        $lead = GFFormsModel::get_lead($Transaction->lead_id); // get lead (to get form_id)

        if ( isset($lead['form_id']) && is_numeric($lead['form_id']) ){ // check if form_id is set
          if( $feed_id = $Dao->isDibsForm($lead['form_id']) ) {// get feed
            $feed = $Dao->getDibsMeta($feed_id); // get feed settings
            $placeholders = self::getPlaceholders($Transaction, $feed);
            // replace placeholders
            $mail = self::replacePlaceholders($mail, $placeholders);
          }
        }
      }
    }

    return $mail;
  }


  public static function disableNotifications($unknown, $confirmation, $form, $lead){
    $Dao = new GFDibsDao();
    //_log('GFDibsHook::disableNotifications()');

    $is_disabled = false;

    if ( $feed_id = $Dao->isDibsForm($form['id']) ){
      $feed = $Dao->getDibsMeta($feed_id);

      if(  self::isDibsPayment($feed, $lead) ){
        //$Dao->log($feed->meta['gf_dibs_no_confirmations']);
        if ( isset($feed->meta['gf_dibs_no_confirmations']) && $feed->meta['gf_dibs_no_confirmations'] == '1' ){
          $is_disabled = true;
        }
      }
    }

    return $is_disabled;
  }


  public static function isDibsPayment($feed, $lead){
    // _log('$feed');
    // _log($feed);
    // _log('$lead');
    // _log($lead);
    $is_dibs_payment = true;

    if ( isset($feed->meta['paymentMethods']) && trim($feed->meta['paymentMethods']) ){
      $payment_method_field_id = $feed->meta['paymentMethods'];

      if ( isset($lead[$payment_method_field_id]) && trim($lead[$payment_method_field_id]) && strtolower($lead[$payment_method_field_id]) != 'dibs' ){
        $is_dibs_payment = false;
      }
    }

    return $is_dibs_payment;
  }


  public static function checkIfCustomMerchantId($feed){
    $custom_merchant_id = null;
    if ( isset($feed->meta['gf_dibs_custom_merchant_id']) ){
      $tmp_cmid = trim($feed->meta['gf_dibs_custom_merchant_id']);
      if ( is_numeric($tmp_cmid) ){
        $custom_merchant_id = $tmp_cmid;
      }
    }

    return $custom_merchant_id;
  }


  public static function dibsTransition($confirmation, $form, $lead, $ajax){
    $Dao = new GFDibsDao();
    _log('GFDibsHook::dibsTransition()');

    if ( $feed_id = $Dao->isDibsForm($form['id']) ){
      $feed = $Dao->getDibsMeta($feed_id);

      //$Dao->log('feed:');
      //$Dao->log($feed);

      // check if user has choosen an alternative payment method
      if( !self::isDibsPayment($feed, $lead) ){
        return $confirmation;
      }

      // dibs test modus
      if ( isset($feed->meta['gf_dibs_mode']) && $feed->meta['gf_dibs_mode'] == '1' ){
        $_POST['test'] = 1;
      }

      // payment type
      if ( isset($feed->meta['gf_dibs_type']) ){
        if ( $feed->meta['gf_dibs_type'] == '2' ){
          $_POST['createTicketAndAuth'] = 1;
          $_POST['paymentType'] = 'createTicketAndAuth';
          $_POST['captureNow'] = '1';
        }
        else if ( $feed->meta['gf_dibs_type'] == '3' ){
          $_POST['createTicket'] = 1;
          $_POST['paymentType'] = 'createTicket';
        }
        else{
          $_POST['paymentType'] = 'noAgreement';
          $_POST['captureNow'] = '1';
        }
      }

      unset($feed->meta['gf_dibs_form']);
      unset($feed->meta['dibs_feed_id']);
      unset($feed->meta['gf_dibs_type']);
      unset($feed->meta['gf_dibs_mode']);


      foreach ($feed->meta as $key => $value) {
        $value = str_replace('.', '_', $value);
        //$Dao->log( $key );
        //$Dao->log( 'input_'.$value  );
        if ( isset($_POST['input_'.$value]) && strlen(trim($_POST['input_'.$value]))  ){
          $_POST[$key]  = $_POST['input_'.$value];

          if ( $key == 'amount' ){
            $_POST[$key] = $_POST[$key] * 100;
          }

          if ( $key == 'billingEmail' ){
            $_POST['email'] = $_POST[$key];
          }
        }
      }

      // set ordertext
      $_POST['ordertext'] = null;
      if ( isset($_POST['billingFirstName']) ){
        $_POST['ordertext'] .= $_POST['billingFirstName']." ";
      }
      if ( isset($_POST['billingLastName']) ){
        $_POST['ordertext'] .= $_POST['billingLastName']." ";
      }

      if ( isset($_POST['email']) ){
        $_POST['ordertext'] .= "(".$_POST['email'].")";
      }
      $_POST['ordertext'] =  trim($_POST['ordertext']);


      // $_POST['orderId'] = hexdec(uniqid());
      $_POST['leadId']    = $lead['id'];

      $order_id = uniqid();

      if ( $suffix = get_option(ORDER_ID_SUFFIX) ){
        $order_id = $suffix.'_'.$order_id;
      }

      // dx
      $_POST['orderId']   = $order_id;

      // d2
      $_POST['orderid']   = $order_id;

      $_POST['currency']  = get_option('rg_gforms_currency');
      $_POST['language']  = 'nb_NO';
      $_POST['merchant']  = trim(get_option(MERCHANT));

      if ( $custom_merchant_id = self::checkIfCustomMerchantId($feed) ){
        $_POST['merchant'] = $custom_merchant_id;
      }


      $_POST['send_to_dibs']  = '1';


      if ( isset($_POST['input_9998']) ){ // input_9999 => return url
        $_POST['dibs_form_id'] = $_POST['input_9998'];
      }

      if ( isset($_POST['input_9999']) ){ // input_9999 => return url
        // D2
        $_POST['callbackurl']     = $_POST['input_9999'];
        $_POST['accepturl']       = $_POST['input_9999'];
        // DX
        $_POST['callbackUrl']     = $_POST['input_9999'];
        $_POST['acceptReturnUrl'] = $_POST['input_9999'];

        //$Dao->log('$_POST');
        //$Dao->log($_POST);
      }


      if ( get_option(USE_MD5 ) ){
        $md5key = null;
        $k1 = trim( get_option(MD5_K1) );
        $k2 = trim( get_option(MD5_K2) );

        if ( $k1 && $k2 ){
          $md5_vars = "merchant=".$_POST['merchant']."&orderid=".$order_id."&currency=".$_POST['currency']."&amount=".$_POST['amount'];
          $md5key = md5( $k2.md5($k1.$md5_vars) );
        }

        $_POST['md5key'] = $md5key;
      }


      if ( $decorator = get_option(DECORATOR ) ){
        $_POST['decorator'] = $decorator;
      }

      $transaction_id = $Dao->createTransaction($_POST);
      $Dao->log('wp_rg_dibs_transaction: id');
      $Dao->log($transaction_id);


      $confirmation = '<form action="'.get_option(DIBS_POST_URL).'" name="dibs_post_form" id="dibs_post_form" method="post" accept-charset="utf-8" >';
      foreach ($_POST as $key => $value) {
        if ( !is_numeric(strpos($key, 'input')) && !is_numeric(strpos($key, 'MAX_FILE_SIZE'))  && !is_numeric(strpos($key, 'state')) && !is_numeric(strpos($key, 'gform')) ){
          $confirmation .=  sprintf('<input type="hidden" name="%s" id="%s" value="%s" />', $key, $key, $value );
        }

      }

      $confirmation .= _e('Videresending til DIBS ...', DIBS_LANG);
      $confirmation .= '</form>';

    }

    return $confirmation;
  }


  /*
    function getPlaceholders

    Placeholders:
    @order_id@
    @transaction_id@
    @amount@
    @date_created@
    @ticket@
    @firstname@
    @lastname@
    @email@
    @mobile@
    @address@
    @postalcode@
    @postalplace@
  */

  public static function getPlaceholders( $Transaction, $feed){
    $Dao = new GFDibsDao();

    $placeholders = array();

    //  parse placeholders
    $placeholders['order_id']       = $Transaction->order_id;
    $placeholders['transaction_id'] = $Transaction->transaction_id;
    $placeholders['amount']         = (int)$Transaction->amount/100;
    $placeholders['date_created']   = $Transaction->date_created;
    $placeholders['ticket']         = $Transaction->ticket;

    $placeholders['firstname']   = null;
    $placeholders['lastname']    = null;
    $placeholders['email']       = null;
    $placeholders['mobile']      = null;
    $placeholders['address']     = null;
    $placeholders['postalcode']  = null;
    $placeholders['postalplace'] = null;

    // get lead values
    foreach ($feed->meta as $key => $value) {
      if ( is_numeric(strpos($key, 'billing'))  ){
        if ( strlen($value) ){
          if ( $meta_value = $Dao->getLeadMetaValue($Transaction->lead_id, $value ) ){
            $index =  strtolower( str_replace('billing', '', $key) );
            $placeholders[$index]   = $meta_value;
          }
        }
      }
    }

    return $placeholders;
  }

  public static function replacePlaceholders( $message, $placeholders ){
    foreach ($placeholders as $key => $value) {
      $message = str_replace('@'.$key.'@', $value, $message);
    }

    return $message;
  }


  public static function hasRedirect($form){
    $redirect = null;
    if ( isset($form['confirmations']) && is_array($form['confirmations']) ){
      foreach ($form['confirmations'] as $key => $confirmation) {
        if ( is_array($confirmation) && $confirmation['isDefault'] == '1' ){
          if ( $confirmation['type'] == 'page' ){
            $redirect = get_permalink( $confirmation['pageId'] );
          }
          elseif ( $confirmation['type'] == 'redirect' ){
            $redirect = $confirmation['url'];
          }
        }
      }
    }

    return $redirect;
  }

  public static function grabOrderId( $post ){
    $order_id = null;
    if ( isset($post['orderId']) && strlen($post['orderId']) ){
      $order_id = $post['orderId'];
    }
    else if ( isset($post['orderid']) && strlen($post['orderid']) ){
      $order_id = $post['orderid'];
    }

    return $order_id;

  }


  public static function updateLeadAfterPayment(){

    $order_id = self::grabOrderId($_POST);

    if ( isset($_POST) && $order_id && isset($_POST['dibs_form_id']) && is_numeric($_POST['dibs_form_id']) ){
      _log('GF DIBS add-on');
      _log($order_id);
      _log('User is coming back from DIBS');
      _log('Post variables');
      _log($_POST);
      $Dao = new GFDibsDao();

      $form = GFAPI::get_form( $_POST['dibs_form_id'] );
      $feed_id = $Dao->isDibsForm($form['id']);
      $feed = $Dao->getDibsMeta($feed_id);

      $block = false;
      if ( isset($_SERVER['HTTP_USER_AGENT']) && is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], 'Java')) or isset($_SERVER['HTTP_X_ORIG_UA']) && is_numeric(strpos($_SERVER['HTTP_X_ORIG_UA'], 'Java'))  ){
        $block = true;
      }


      if ( $feed_id && $order_id && !$block ){

        // update Transaction
        $Dao->updateTransaction($_POST);

        // send confirmation mails
        if ( $Transaction = $Dao->getTransactionByOrderId($order_id) ){
          _log($Transaction);
           /* prod */
          if ( isset($feed->meta['gf_dibs_no_confirmations']) && $feed->meta['gf_dibs_no_confirmations'] == '1' && !$Dao->getDateCompleted($Transaction->lead_id) ){
            GFDibsAddOn::sendNotification('form_submission', $form, $Transaction->lead_id);
            $Dao->setDateCompleted($Transaction->lead_id);
          }
          /* test */
          // GFDibsAddOn::sendNotification('form_submission', $form, $Transaction->lead_id);
          //
          //

          do_action('gravityformsdibs_after_payment', $order_id, $form, GFAPI::get_entry($Transaction->lead_id), $Transaction );
        }


        if ( $location = self::hasRedirect($form) ){
          wp_redirect( $location );
          die();
        }
      }
    }
  }





  public static function formTag($form_tag, $form){

    if ( isset($_POST['orderId']) ){
      $Dao = new GFDibsDao();
      $feed_id = $Dao->isDibsForm($form['id']);
      // get feed settings

      $feed = $Dao->getDibsMeta($feed_id);

      $placeholders = array();
      if ( $Transaction = $Dao->getTransactionByOrderId($_POST['orderId']) ){

        // get confirmation message
        $form_meta = $Dao->getFormMeta($form['id']);
        $message = null;
        if ( is_array($form_meta['confirmations']) ){
          foreach ($form_meta['confirmations'] as $confirmation) {
            if ( $confirmation['isDefault'] == '1'){
              $message = $confirmation['message'];
            }
          }
        }

        // get placeholders
        $placeholders = self::getPlaceholders($Transaction, $feed );

        // replace placeholders
        $message = self::replacePlaceholders($message, $placeholders);

        // sanitize form tag
        $form_tag = preg_replace("|action='(.*?)'|", "style='display:none;'", $form_tag);

        printf('<div class="thank-you"><p>%s</p></div>', $message);
      }
    }


    return $form_tag;
  }


  public static function addPaymentDetails($form, $lead){
    $Dao = new GFDibsDao();

    $Transaction = $Dao->getTransactionByLeadId($lead['id']);

    if ( $Transaction ):
  ?>
    <table cellspacing="0" class="widefat fixed entry-detail-view">
    <thead>
    <tr>
      <th id="details"><strong><?php _e('DIBS payment status', DIBS_LANG); ?></strong></th>
      <th style="width:140px; font-size:10px; text-align: right;"></th>
    </tr>
    </thead>
    <tbody>
      <tr>
        <td colspan="2" class="entry-view-field-name"><?php _e('Order id:');?> <span><?php if(isset($Transaction->order_id)) echo $Transaction->order_id; ?></span></td>
      </tr>
      <tr>
        <td colspan="2" class="no-padding">
          <table class="entry-products dibs-transaction" cellspacing="0" width="97%">
            <colgroup>
              <col class="entry-products-col1">
              <col class="entry-products-col2">
              <col class="entry-products-col3">
              <col class="entry-products-col4">
            </colgroup>

            <thead>
              <th scope="col" class="width_15"><?php _e('Date', DIBS_LANG);?></th>
              <th scope="col" class="width_15"><?php _e('Transaction id', DIBS_LANG);?></th>
              <th scope="col" class="width_15"><?php _e('Ticket', DIBS_LANG);?></th>
              <th scope="col" class="width_10"><?php _e('Payment Type', DIBS_LANG);?></th>
              <th scope="col" class="width_10"><?php _e('Completed' , DIBS_LANG);?></th>
              <th scope="col" class="width_5"><?php _e('Amount', DIBS_LANG);?></th>
              <th scope="col" class="width_15"><?php _e('Credit card', DIBS_LANG);?></th>
              <th scope="col" class="width_5"><?php _e('Test', DIBS_LANG);?></th>
            </thead>

            <tbody>
              <tr>
              <td><?php echo date('d.m.y h:i', strtotime($Transaction->date_created) ); ?></td>
              <td><?php echo _is($Transaction, 'transaction_id'); ?></td>
              <td><?php echo _is($Transaction, 'ticket'); ?></td>
              <td><?php echo _is($Transaction, 'payment_type'); ?></td>
              <td><?php echo ( (_is($Transaction, 'completed') ) ? __('yes', DIBS_LANG) : __('no', DIBS_LANG)  ); ?></td>
              <td><?php echo ( _is($Transaction, 'amount')/100); ?> <?php echo get_option('rg_gforms_currency');?></td>
              <td><?php echo _is($Transaction, 'paytype'); ?></td>
              <td><?php echo ( ( _is($Transaction, 'test') ) ? __('yes', DIBS_LANG) : __('no', DIBS_LANG)  ); ?></td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
    </table>

  <?php
   endif;
  }
}
?>