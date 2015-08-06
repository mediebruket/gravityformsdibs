<?php
define('DIBS_LANG', 'gf_dibs_lang');
define('DIBS_CLASS', 'GFDibsAddOn');
define('DIBS_POST_URL', 'dibs_post_url');
define('MERCHANT', 'dibs_merchant_id');
define('ORDER_ID_SUFFIX', 'suffix');
define('LICENCE', 'gravityformsdibs_licence_key');

add_action( 'plugins_loaded', 'registerAddon' );

function registerAddon(){

  if (class_exists("GFAddOn")) {

    GFForms::include_addon_framework();

    class GFDibsAddOn extends GFAddOn {
        protected $DAO;
        protected $dibs_table_name;
        protected $gf_table_name;
        protected $_version = "1.0.1";
        protected $_min_gravityforms_version = "1.9";
        protected $_full_path = __FILE__;
        protected $_url = "http://www.gravityforms.com";
        protected $_title = "Gravity Forms DIBS Add-On";
        protected $_short_title = "DIBS Add-On";

        public $payment_types =
          array(
            0 => __('Select a transaction type', DIBS_LANG),
            1 => __('Engangsbetaling', DIBS_LANG),
            2 => __('Betalingsavtale: med betaling', DIBS_LANG),
            3 => __('Betalingsavtale: kun registrering av kort', DIBS_LANG)
          );

        public function init(){
          if ( is_admin() ){
            add_filter("gform_addon_navigation", array('GFDibsAddon', 'createMenu') );

            if(RGForms::get("page") == "gf_settings"){
                RGForms::add_settings_page("DIBS", array("GFDibsAddon", "pluginSettingsFields") );
            }
          }
        }


        public static function pluginSettingsFields(){
          global $plugin_file;
          $Plugin = new GFDibsUpdater($plugin_file);
          $Plugin->getCurrentVersionInfo();
          $licence_status = null;

          $settings =
            array(
              DIBS_POST_URL   => __("Platform", DIBS_LANG),
              MERCHANT        => __("Merchant ID", DIBS_LANG),
              ORDER_ID_SUFFIX => __("Suffix (order id)", DIBS_LANG),
              LICENCE         => __("Licence key", DIBS_LANG),
            );

          $platforms = array ( 0 => __('Select platform', DIBS_LANG ) , 'https://payment.dibspayment.com/dpw/entrypoint' => 'DX', 'https://payment.architrade.com/paymentweb/start.action' => 'D2');

          if ( isset($_POST['updated']) ){
            foreach ($_POST as $key => $field) {
              update_option( $key, $field );
            }
          }

          // check licence
          $Plugin = new GFDibsUpdater($plugin_file);
          $valid = $Plugin->checkLicenceKey();

          $licence_status = ( $valid  != '3' ) ? 'valid' : 'invalid';
          set_transient( $Plugin->Slug.'_last_check', $valid, 0 );
        ?>
            <form action="" method="post">
              <h3><?php _e("DIBS Settings", DIBS_LANG) ?></h3>
              <input name="updated" type="hidden" value='1' />

              <!-- platform -->
              <p>
                <label for="<?php echo DIBS_POST_URL; ?>" class="gfdibs_setting"><?php echo $settings[DIBS_POST_URL];  ?></label>
                <select name="<?php echo DIBS_POST_URL; ?>" id="<?php echo DIBS_POST_URL; ?>">
                  <?php
                  foreach ($platforms as $key => $value){
                    echo sprintf('<option value="%s" %s>%s</option>', $key, selected( $key, get_option(DIBS_POST_URL), false), $value);
                  }
                  ?>
                </select>
               </p>

              <p>
                <label class="gfdibs_setting" for="<?php echo MERCHANT; ?>" class="inline"><?php echo $settings[MERCHANT]; ?></label>
                <input type="text" name="<?php echo MERCHANT; ?>" id="<?php echo $settings[MERCHANT]; ?>" value="<?php echo get_option(MERCHANT); ?>" size="80" />
              </p>

              <p>
                <label class="gfdibs_setting" for="<?php echo ORDER_ID_SUFFIX; ?>" class="inline"><?php echo $settings[ORDER_ID_SUFFIX]; ?></label>
                <input type="text" name="<?php echo ORDER_ID_SUFFIX; ?>" id="<?php echo ORDER_ID_SUFFIX; ?>" value="<?php echo get_option(ORDER_ID_SUFFIX); ?>" size="80" />
              </p>

              <p>
                <label class="gfdibs_setting" for="<?php echo LICENCE; ?>" class="inline"><?php echo $settings[LICENCE]; ?></label>
                <input class="licence <?php echo $licence_status; ?>" type="text" name="<?php echo LICENCE; ?>" id="<?php echo LICENCE; ?>" value="<?php echo get_option( LICENCE ); ?>" size="80" />
              </p>

              <div>
                <input type="submit" value="<?php _e("Save", DIBS_LANG) ?>" class="button-primary gfbutton gaddon-setting gaddon-submit" />
              </div>
            </form>
        <?php
        }


        public static function sendNotification ( $event = 'form_submission' , $form, $lead_id ) {
          $lead = GFFormsModel::get_lead($lead_id);

          $notifications         = GFCommon::get_notifications_to_send( $event, $form, $lead );
          $notifications_to_send = array();

          foreach ( $notifications as $notification ) {
            $notifications_to_send[] = $notification['id'];
          }

          GFCommon::send_notifications( $notifications_to_send, $form, $lead, true, $event );
        }


        public static function createMenu($menus){
          $menus[] =
            array(
              "name"      => "gf_dibs",
              "label"     => __("DIBS", "gravityformspaypal"),
              "callback"  =>  array('GFDibsAddon', "dibsPage"),
              // "permission" => $permission
            );

            return $menus;
        }


        public static function dibsPage(){
          $Addon = new GFDibsAddOn();
          if ( isset($_GET['page']) && $_GET['page'] == 'gf_dibs' && isset($_GET['id']) && is_numeric($_GET['id'])  ){
            $Addon->editPage();
          }
          else{
            $Addon->pluginPage();
          }
        }


        public function pluginPage() {
           $this->DAO = new GFDibsDao();

          if(rgpost('action') == "delete"){
              check_admin_referer("list_action", "gf_paypal_list");

              $id = absint($_POST["action_argument"]);
              $this->DAO->deleteFeed($id);
              ?>
              <div class="updated fade" style="padding:6px"><?php _e("Feed deleted.", DIBS_LANG) ?></div>
              <?php
          }
          else if (!empty($_POST["bulk_action"])){
              check_admin_referer("list_action", "gf_paypal_list");
              $selected_feeds = $_POST["feed"];
              if(is_array($selected_feeds)){
                  foreach($selected_feeds as $feed_id)
                      $this->DAO->deleteFeed($feed_id);
              }
              ?>
              <div class="updated fade" style="padding:6px"><?php _e("Feeds deleted.", DIBS_LANG) ?></div>
              <?php
          }

          ?>
          <div class="wrap">
              <h2><?php _e("DIBS Forms", DIBS_LANG);?>
                <a class="button add-new-h2" href="admin.php?page=gf_dibs&amp;view=edit&amp;id=0"><?php _e("Add New", DIBS_LANG) ?></a>
              </h2>

              <form id="feed_form" method="post">
                  <?php wp_nonce_field('list_action', 'gf_paypal_list') ?>
                  <input type="hidden" id="action" name="action"/>
                  <input type="hidden" id="action_argument" name="action_argument"/>

                  <div class="tablenav">
                      <div class="alignleft actions" style="padding:8px 0 7px 0;">
                          <label class="hidden" for="bulk_action"><?php _e("Bulk action", DIBS_LANG) ?></label>
                          <select name="bulk_action" id="bulk_action">
                              <option value=''> <?php _e("Bulk action", DIBS_LANG) ?> </option>
                              <option value='delete'><?php _e("Delete", DIBS_LANG) ?></option>
                          </select>
                          <?php
                          echo '<input type="submit" class="button" value="' . __("Apply", DIBS_LANG) . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("Delete selected feeds? ", DIBS_LANG) . __("\'Cancel\' to stop, \'OK\' to delete.", DIBS_LANG) .'\')) { return false; } return true;"/>';
                          ?>
                      </div>
                  </div>
                  <table class="widefat fixed" cellspacing="0">
                      <thead>
                          <tr>
                              <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                              <th scope="col" id="active" class="manage-column check-column"></th>
                              <th scope="col" class="manage-column"><?php _e("Form", DIBS_LANG) ?></th>
                              <th scope="col" class="manage-column"><?php _e("Transaction Type", DIBS_LANG) ?></th>
                          </tr>
                      </thead>

                      <tfoot>
                          <tr>
                              <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                              <th scope="col" id="active" class="manage-column check-column"></th>
                              <th scope="col" class="manage-column"><?php _e("Form", DIBS_LANG) ?></th>
                              <th scope="col" class="manage-column"><?php _e("Transaction Type", DIBS_LANG) ?></th>
                          </tr>
                      </tfoot>

                      <tbody class="list:user user-list">
                          <?php
                          $settings = $this->DAO->getFeeds();

                          if(!get_option("dibs_merchant_id")){
                              ?>
                              <tr>
                                  <td colspan="3" style="padding:20px;">
                                      <?php echo sprintf(__("To get started, please configure your %sDIBS Settings%s.", DIBS_LANG), '<a href="admin.php?page=gf_settings&addon=DIBS+Add-On&subview=DIBS">', "</a>"); ?>
                                  </td>
                              </tr>
                              <?php
                          }
                          else if(is_array($settings) && sizeof($settings) > 0){
                              foreach($settings as $setting){
                                  ?>
                                  <tr class='author-self status-inherit' valign="top">
                                      <th scope="row" class="check-column"><input type="checkbox" name="feed[]" value="<?php echo $setting["id"] ?>"/></th>
                                      <td><!-- <img src="<?php echo self::get_base_url() ?>/images/active<?php echo intval($setting["is_active"]) ?>.png" alt="<?php echo $setting["is_active"] ? __("Active", DIBS_LANG) : __("Inactive", DIBS_LANG);?>" title="<?php echo $setting["is_active"] ? __("Active", DIBS_LANG) : __("Inactive", DIBS_LANG);?>" onclick="ToggleActive(this, <?php echo $setting['id'] ?>); " /> --></td>
                                      <td class="column-title">
                                          <a href="admin.php?page=gf_dibs&amp;view=edit&amp;id=<?php echo $setting["id"] ?>" title="<?php _e("Edit", DIBS_LANG) ?>"><?php echo $setting["form_title"] ?></a>
                                          <div class="row-actions">
                                              <span class="edit">
                                              <a title="<?php _e("Edit", DIBS_LANG)?>" href="admin.php?page=gf_dibs&amp;view=edit&amp;id=<?php echo $setting["id"] ?>" ><?php _e("Edit", DIBS_LANG) ?></a>
                                              |
                                              </span>
                                              <span class="view">
                                              <a title="<?php _e("View Stats", DIBS_LANG)?>" href="admin.php?page=gf_dibs&amp;view=stats&amp;id=<?php echo $setting["id"] ?>"><?php _e("Stats", DIBS_LANG) ?></a>
                                              |
                                              </span>
                                              <span class="view">
                                              <a title="<?php _e("View Entries", DIBS_LANG)?>" href="admin.php?page=gf_entries&amp;view=entries&amp;id=<?php echo $setting["form_id"] ?>"><?php _e("Entries", DIBS_LANG) ?></a>
                                              |
                                              </span>
                                              <span class="trash">
                                              <a title="<?php _e("Delete", DIBS_LANG) ?>" href="javascript: if(confirm('<?php _e("Delete this feed? ", DIBS_LANG) ?> <?php _e("\'Cancel\' to stop, \'OK\' to delete.", DIBS_LANG) ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("Delete", DIBS_LANG)?></a>
                                              </span>
                                          </div>
                                      </td>
                                      <td class="column-date">


                                        <?php
                                        if ( isset($setting['meta']['gf_dibs_type']) ){
                                          echo $this->payment_types[$setting['meta']['gf_dibs_type']];
                                        }

                                        ?>

                                      </td>
                                  </tr>
                                  <?php
                              }
                          }
                          else{
                              ?>
                              <tr>
                                  <td colspan="4" style="padding:20px;">
                                      <?php echo sprintf(__("You don't have any DIBS feeds configured. Let's go %screate one%s!", DIBS_LANG), '<a href="admin.php?page=gf_dibs&view=edit&id=0&edit_feed=1">', "</a>"); ?>
                                  </td>
                              </tr>
                              <?php
                          }
                          ?>
                      </tbody>
                  </table>
              </form>
          </div>

          <?php
        }

        function editPage(){
          $this->DAO = new GFDibsDao();
          ?>

          <div class="wrap">
              <h2><?php _e("DIBS Settings", DIBS_LANG); ?></h2>

          <?php

          //getting setting id (0 when creating a new one)
          $feed_id = !empty($_POST["dibs_feed_id"]) ? $_POST["dibs_feed_id"] : absint($_GET["id"]);

          $update = null;
          $error = null;

          if ( isset($_POST['update']) ){
            if ( isset($_POST['gf_dibs_form']) && is_numeric($_POST['gf_dibs_form'])  ){
              $feed_id = $this->DAO->setDibsMeta($_POST, $feed_id);

              if ( is_numeric($feed_id) ){
                $update = true;
              }
              else{
                $error = true;
              }
            }
          }

          $feed = $this->DAO->getDibsMeta($feed_id);
        ?>

        <form method="post" action="" id="dibs_edit_form">

            <input type="hidden" name="dibs_feed_id" value="<?php echo $feed_id; ?>" />
            <input type="hidden" name="update" value="1" />

            <?php if ( $error): ?>
              <div class="margin_vertical_10 error">
                <span><?php _e('There was an issue saving your feed. Please address the errors below and try again.', DIBS_LANG); ?></span>
              </div>
            <?php endif; ?>

            <?php if ( $update): ?>
              <div class="margin_vertical_10 updated">
                  <span><?php _e('Settings are saved', DIBS_LANG); ?></span>
              </div>
            <?php endif; ?>


            <!--  production / test   -->
            <div class="margin_vertical_10">
              <label class="left_header"><?php _e("Mode", DIBS_LANG); ?> <?php //gform_tooltip("paypal_mode") ?></label>

              <!-- prod modus -->
              <input type="radio" name="gf_dibs_mode" id="gf_dibs_mode_production" value="0" <?php checked( $feed->meta['gf_dibs_mode'], 0 ); ?> />
              <label class="inline" for="gf_dibs_mode_production"><?php _e("Production", DIBS_LANG); ?></label>

              <!-- test modus -->
              <input type="radio" name="gf_dibs_mode" id="gf_dibs_mode_test" value="1" <?php checked( $feed->meta['gf_dibs_mode'], 1 ); ?> />
              <label class="inline" for="gf_dibs_mode_test"><?php _e("Test", DIBS_LANG); ?></label>
            </div>


            <!--  transaction type  -->
            <div class="margin_vertical_10">
                <label class="left_header" for="gf_dibs_type"><?php _e("Transaction Type", DIBS_LANG); ?></label>
                <select id="gf_dibs_type" name="gf_dibs_type" >
                  <?php foreach ($this->payment_types as $key => $value) : ?>
                    <option value="<?php echo $key; ?>" <?php  selected( $feed->meta['gf_dibs_type'], $key ); ?> ><?php echo $value; ?></option>
                  <?php
                  endforeach; ?>
                </select>
            </div>


            <!--  captureNow  -->
            <div class="margin_vertical_10" id="capture_now" >
                <label class="left_header" for="gf_dibs_capture_now"><?php _e("Capture after authorization", DIBS_LANG); ?></label>
                  <input type="checkbox" name="gf_dibs_capture_now" id="gf_dibs_capture_now" value="1" <?php checked( $feed->meta['gf_dibs_capture_now'], 1 ); ?> />
            </div>


            <!--  gf form id  -->
            <div id="paypal_form_container" valign="top" class="margin_vertical_10" >
                <label for="gf_paypal_form" class="left_header"><?php _e("Gravity Form", DIBS_LANG); ?></label>

              <?php
                // $active_form = rgar($config, 'form_id');
                $available_forms = $this->DAO->getAvailableForms($feed->meta['gf_dibs_form']);
              ?>

                <select id="gf_dibs_form" name="gf_dibs_form" >
                    <option value=""><?php _e("Select a form", DIBS_LANG); ?> </option>
                    <?php
                    $form_fields = array();

                    foreach($available_forms as $current_form) {
                      $form_meta =  $this->DAO->getFormMeta($current_form->id);

                      if ( isset($form_meta['fields']) && is_array($form_meta['fields']) ){
                        foreach ($form_meta['fields'] as $key => $value) {
                          if ( isset($value['type']) && $value['type'] == 'total' ){
                            $form_fields['form_'.$current_form->id] = $form_meta['fields'];
                            ?>
                            <option value="<?php echo absint($current_form->id) ?>" <?php echo selected($feed->meta['gf_dibs_form'], $current_form->id); ?>><?php echo esc_html($current_form->title) ?></option>
                          <?php
                          }
                        }

                      }
                    ?>
                    <?php } ?>
                </select>
                <script>
                  var form_fields = <?php echo json_encode($form_fields); ?>;
                  var feed_meta = <?php echo json_encode($feed->meta); ?>;
                </script>

            </div>

            <h3><?php _e("DIBS Parameters", DIBS_LANG); ?> <a href="http://tech.dibspayment.com/input_parameters_dpw" target="_blank">Info</a></h3>

            <?php

            $form_fields = null;
            if ( isset($feed->meta['gf_dibs_form']) ){
              $form_fields = $this->DAO->getFormFields($feed->meta['gf_dibs_form']);
            }

            ?>
            <div class="margin_vertical_10">
              <div class="left_header" for="amount"><?php _e("Total", DIBS_LANG); ?></div>
               <select id="amount" name="amount" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingFirstName"><?php _e("First name", DIBS_LANG); ?></div>
               <select id="billingFirstName" name="billingFirstName" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingLastName"><?php _e("Last name", DIBS_LANG); ?></div>
               <select id="billingLastName" name="billingLastName" class="form_field" ><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingEmail"><?php _e("Email", DIBS_LANG); ?></div>
               <select id="billingEmail" name="billingEmail" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingMobile"><?php _e("Mobile", DIBS_LANG); ?></div>
               <select id="billingMobile" name="billingMobile" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingAddress"><?php _e("Address", DIBS_LANG); ?></div>
               <select id="billingAddress" name="billingAddress" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingPostalCode"><?php _e("Postal code", DIBS_LANG); ?></div>
               <select id="billingPostalCode" name="billingPostalCode" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>

            <div class="margin_vertical_10">
              <div class="left_header" for="billingPostalCode"><?php _e("Postal place", DIBS_LANG); ?></div>
               <select id="billingPostalPlace" name="billingPostalPlace" class="form_field"><option value="" class="default"><?php _e('Select field', DIBS_LANG); ?></option></select>
            </div>


             <h3><?php _e("Notifications", DIBS_LANG); ?></h3>
            <!--  Confirmation mail   -->
            <div class="margin_vertical_10">
              <!-- <label class="left_header"><?php _e("Notifications", DIBS_LANG); ?> <?php //gform_tooltip("paypal_mode") ?></label> -->

              <!-- prod modus -->
              <input type="checkbox" name="gf_dibs_no_confirmations" id="gf_dibs_no_confirmations" value="1" <?php checked( $feed->meta['gf_dibs_no_confirmations'], 1 ); ?> />
              <label class="inline" for="gf_dibs_no_confirmations"><?php _e("Send notifications only when payment is received.", DIBS_LANG); ?></label>
            </div>


            <div id="paypal_submit_container" class="margin_vertical_30">
              <input type="submit" name="gf_paypal_submit" value="Save" class="button-primary"/>
              <input type="button" value="<?php _e("Cancel", DIBS_LANG); ?>" class="button"  />
            </div>

            </div>
        </form>
        </div>
        <?php
      }
    }
   new GFDibsAddOn();
  }
}
?>