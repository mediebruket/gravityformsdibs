<?php
  class GFDibsDao {
      protected $dibs_table_name;
      protected $gf_table_name;
      protected $db;

      function log($message = null){
        if( WP_DEBUG === true ){
          error_log( print_r( $message, true ) );
        }
      }

      function GFDibsDao(){
        global $wpdb;
        $this->db              = $wpdb;

        $this->dibs_table_name = self::getDibsTableName();
        $this->gf_table_name   = self::getDibsTransactionTableName();
      }

      function getAvailableForms( $current_form_id = null){
        $forms = RGFormsModel::get_forms();
        $available_forms = array();

        foreach($forms as $form) {
          if ( !$this->isDibsForm($form->id) || $current_form_id == $form->id ){
            $available_forms[] = $form;
          }
        }

        return $available_forms;
      }

      /*
      ------------------------------------------------------------------------------------
        Transaction
      ------------------------------------------------------------------------------------
      */
      function createTransaction( $post ){
        $this->log('gform_confirmation');
        $this->log('POST variables');
        $this->log($post);

         $this->db->insert(
            $this->gf_table_name,
            array(
              'order_id'      => $post['orderId'],
              'lead_id'       => $post['leadId'],
              'payment_type'  => $post['paymentType'],
              'test'          => ( isset($post['test']) && $post['test'] == '1' ) ? 1 : 0,
              'amount'        => $post['amount'],
              'date_created'  => date('Y-m-d H:i:s')
            ),
            array(
              '%d', // order_id
              '%d', // lead_id
              '%s', // payment_type
              '%d', // test
              '%d', // amount
              '%s' // date
            )
          );

          return $this->db->insert_id;
      }

      function updateTransaction( $post ){
        $this->db->update(
          $this->gf_table_name,
          array(
            'completed'         => 1,
            'transaction_id'    => ( isset($post['transaction']) ) ? $post['transaction'] : false,
            'ticket'            => ( isset($post['ticket']) ) ? $post['ticket'] : false,
          ),
          array( 'order_id' => $post['orderId'] ),
          array(
            '%d',
            '%s',
            '%s'
          )
          );
      }

      function getTransactionByLeadId($lead_id){
        $sql = sprintf('SELECT * FROM %s where lead_id = %d', $this->gf_table_name, $lead_id);
        return $this->db->get_row($sql);
      }

      function getTransactionByOrderId($order_id){
        $sql = sprintf('SELECT * FROM %s where order_id = %d', $this->gf_table_name, $order_id);
        return $this->db->get_row($sql);
      }

      /*
      ---------------------------------------------------------------------------------------------------
        Feed
      ---------------------------------------------------------------------------------------------------
      */
      function isDibsForm($form_id){
        global $wpdb;

        $sql = sprintf('SELECT id from %s WHERE form_id=%s', $this->dibs_table_name, $form_id);

        $feed_id = $this->db->get_var($sql);

        return $feed_id;
      }


      function getDefaultField(){
        $field =
          array(
            'adminLabel' => null,
            'adminOnly' => null,
            'allowsPrepopulate' => null,
            'defaultValue' => null,
            'description' => null,
            'content' => null,
            'cssClass' => null,
            'errorMessage' => null,
            'id' => null,
            'inputName' => null,
            'isRequired' => null,
            'label' => null,
            'noDuplicates' => null,
            'size' => null,
            'type' => null,
            'postCustomFieldName' => null,
            'displayAllCategories' => null,
            'displayCaption' => null,
            'displayDescription' => null,
            'displayTitle' => null,
            'inputType' => null,
            'rangeMin' => null,
            'rangeMax' => null,
            'calendarIconType' => null,
            'calendarIconUrl' => null,
            'dateType' => null,
            'dateFormat' => null,
            'phoneFormat' => null,
            'addressType' => null,
            'defaultCountry' => null,
            'defaultProvince' => null,
            'defaultState' => null,
            'hideAddress2' => null,
            'hideCountry' => null,
            'hideState' => null,
            'inputs' => null,
            'nameFormat' => null,
            'allowedExtensions' => null,
            'captchaType' => null,
            'pageNumber' => null,
            'captchaTheme' => null,
            'simpleCaptchaSize' => null,
            'simpleCaptchaFontColor' => null,
            'simpleCaptchaBackgroundColor' => null,
            'failed_validation' => null,
            'productField' => null,
            'enablePasswordInput' => null,
            'maxLength' => null,
            'enablePrice' => null,
            'basePrice' => null,
            'formId' => null,
            'descriptionPlacement' => null
          );
      }

      /* database */

      function setupTables(){
        global $wpdb;

        if ( ! empty($wpdb->charset) )
          $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
          $charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE IF NOT EXISTS $this->dibs_table_name (
            id mediumint(8) unsigned not null auto_increment,
            form_id mediumint(8) unsigned not null,
            is_active tinyint(1) not null default 1,
            meta longtext,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
          )$charset_collate;";

        $wpdb->query($sql);

        // error_log($sql);

        $transaction_table = self::getDibsTransactionTableName();
         $sql = "CREATE TABLE IF NOT EXISTS   $transaction_table (
            `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            `order_id` int(15) unsigned NOT NULL,
            `completed` int(1) DEFAULT NULL,
            `transaction_id` varchar(50) DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `test` int(1) DEFAULT NULL,
            `amount` int(10) DEFAULT NULL,
            `date_created` datetime DEFAULT NULL,
            `date_completed` datetime DEFAULT NULL,
            `ticket` varchar(50) DEFAULT NULL,
            `lead_id` int(10) unsigned NOT NULL,
            PRIMARY KEY (`id`)
          )$charset_collate;";

          $wpdb->query($sql);

        // error_log($sql);

      }

      function setDateCompleted($lead_id){
        $sql = sprintf('UPDATE %s SET date_completed="%s" where lead_id=%d', $this->gf_table_name, date('Y-m-d H:i:s'), $lead_id );
        error_log($sql);
        $this->db->query($sql);
      }

      function getDateCompleted($lead_id){
        $sql = sprintf('SELECT date_completed FROM %s where lead_id=%d', $this->gf_table_name, $lead_id );
        error_log($sql);
        return $this->db->get_var($sql);
      }


      function getDibsMeta($feed_id = 0 ){
        global $wpdb;
        $sql = sprintf( 'SELECT form_id, meta FROM %s WHERE id=%d', self::getDibsTableName(), $feed_id );

        $feed = $wpdb->get_row($sql);

        if ( is_object($feed) ){
          $feed->meta = maybe_unserialize($feed->meta  );
        }
        else{
          $feed = new stdClass();
          $feed->meta =
            array(
              'dibs_feed_id'              => null,
              'gf_dibs_mode'              => null,
              'gf_dibs_type'              => null,
              'gf_dibs_no_confirmations'  => 1,
              'gf_dibs_form'              => null,
              'gf_dibs_capture_now'       => null,
              'billingFirstName'          => null,
              'billingLastName'           => null,
              'billingEmail'              => null,
              'billingMobile'             => null,
              'billingAddress'            => null,
              'billingPostalCode'         => null,
              'billingPostalPlace'        => null
            );
        }

        return $feed;
      }


      function setDibsMeta($meta, $feed_id = null){

        // get form id
        $form_id = $meta['gf_dibs_form'];


        // unset fields
        // unset($meta['gf_dibs_form']);
        unset($meta['update']);
        unset($meta['gf_paypal_submit']);

        global $wpdb;
        $this->dibs_table_name = self::getDibsTableName();

        if ( !$feed_id || $feed_id == 0){
          $wpdb->insert(
            $this->dibs_table_name,
            array(
              'form_id' => $form_id,
              'meta'    => maybe_serialize($meta )
            ),
            array(
              '%d',
              '%s'
            )
          );

          $feed_id = $wpdb->insert_id;

        }
        else{
          $wpdb->update(
            $this->dibs_table_name,
            array(
              'form_id' => $form_id,
              'meta'    => maybe_serialize($meta )
            ),
            array( 'id' => $feed_id ),
            array(
              '%d',
              '%s'
            ),
              array( '%d' )
            );
        }


        return $feed_id;
      }


      function getFeeds(){
          global $wpdb;

          $this->dibs_table_name = self::getDibsTableName();
          $this->gf_table_name = RGFormsModel::get_form_table_name();

          $sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                   FROM $this->dibs_table_name s
                   INNER JOIN $this->gf_table_name f ON s.form_id = f.id";

          $results = $wpdb->get_results($sql, ARRAY_A);

          $count = sizeof($results);
          for($i=0; $i<$count; $i++){
              $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
          }

          return $results;
      }


      function getDibsTableName(){
        global $wpdb;
        return $wpdb->prefix . "rg_dibs";
      }

      function getDibsTransactionTableName(){
        global $wpdb;
        return $wpdb->prefix . "rg_dibs_transaction";
      }


      function deleteFeed($id){
        global $wpdb;
        $this->dibs_table_name = self::getDibsTableName();
        $sql = sprintf('DELETE FROM %s where id=%d', $this->dibs_table_name, $id);
        $wpdb->query($sql);
      }


    function getFormFields($form_id = null){
      $form_fields = null;

      if ( $form_id ){

        $form_meta = $this->getFormMeta($form_id);

        if ( isset($form_meta['fields']) && is_array($form_meta['fields']) ){
          foreach ($form_meta['fields'] as $key => $value) {
            if ( isset($value['type']) && $value['type'] ){
              $form_fields .= '<option value="">'.$value['label'].'</option>';
            }
          }
        }
      }

      return $form_fields;
    }

    function getFormMeta($form_id){
      $form = RGFormsModel::get_form_meta($form_id);
      // print_r("<pre>");
      // print_r($form);
      // print_r("</pre>");
      return $form;
    }

    /*
    ---------------------------------------------------------------------------------------------------
      Lead
    ---------------------------------------------------------------------------------------------------
    */

    function getLeadMetaValue($lead_id, $field_number){
      $sql = sprintf('select value from %srg_lead_detail where lead_id = %d and field_number like %s', $this->db->prefix, $lead_id, $field_number);
      $this->log($sql);
      return $this->db->get_var($sql);
    }

  }
?>