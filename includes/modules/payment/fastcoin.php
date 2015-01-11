<?php
/* 
  osCommerce Fastcoin Payment Module
 
  Copyright (c) 2012 David Sterry

  Released under the GNU General Public License

*/
  class fastcoin {
    var $code, $title, $description, $enabled;

    // class constructor
    function fastcoin() {
      global $order;

      $this->code        = 'fastcoin';
      $this->title       = MODULE_PAYMENT_FASTCOIN_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_FASTCOIN_TEXT_DESCRIPTION;
      $this->sort_order  = MODULE_PAYMENT_FASTCOIN_SORT_ORDER;
      $this->notification_key     = MODULE_PAYMENT_FASTCOIN_NOTIFICATION_KEY;
      $this->enabled     = (MODULE_PAYMENT_FASTCOIN_STATUS == 'True');

      if ((int) MODULE_PAYMENT_FASTCOIN_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_FASTCOIN_ORDER_STATUS_ID;
      }

      /*if (is_object($order)) 
        $this->update_status();*/

      $this->email_footer = MODULE_PAYMENT_FASTCOIN_TEXT_EMAIL_FOOTER;
    }

    // class methods
    /*function update_status() {
      global $order;

      if (($this->enabled) && ((int) MODULE_PAYMENT_FASTCOIN_ZONE > 0)) {
        $check_flag = false;
        $check = tep_db_query(
           "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . 
           " where geo_zone_id = '" . MODULE_PAYMENT_FASTCOIN_ZONE . "'" .
           " and zone_country_id = '" . $order->billing['country']['id'] . "'" .
           " order by zone_id");

        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }*/

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      //Here we will generate a new payment address and any other related tasks
      global $order;

      require_once 'fastcoin/jsonRPCClient.php';

      $fastcoin = new jsonRPCClient('http://'.MODULE_PAYMENT_FASTCOIN_LOGIN.':'.MODULE_PAYMENT_FASTCOIN_PASSWORD.'@'.MODULE_PAYMENT_FASTCOIN_HOST.'/'); 

      try {
        $fastcoin->getinfo();
      } catch (Exception $e) {
        $confirmation = array('title'=>'Error: Fastcoin server is down.  Please email system administrator regarding your order after confirmation.');
        return $confirmation;
      }
		
      $confirmation = array('title' => '');
      $confirmation['fields'] 
          = array(
                array('title'=>'Instructions', 'field'=>'<div>Please send payment to the adddress shown after you confirm your order.</div> <hr>'));
		
      return $confirmation;
    }

    function process_button() {
      return false;
    }

    function before_process() {
      global $insert_id, $order;
      $address = $order->customer['email_address'].'-'.tep_create_random_value(32);

      require_once 'fastcoin/jsonRPCClient.php';

      $fastcoin = new jsonRPCClient('http://'.MODULE_PAYMENT_FASTCOIN_LOGIN.':'.MODULE_PAYMENT_FASTCOIN_PASSWORD.'@'.MODULE_PAYMENT_FASTCOIN_HOST.'/'); 

      try {
        $fastcoin->getinfo();
      } catch (Exception $e) {
        $confirmation = array('title'=>'Error: Fastcoin server is down.  Please email system administrator regarding your order after confirmation.');
        return $confirmation;
      }

      $address = $fastcoin->getaccountaddress($address);
      $order->info['comments'] .= ' | Payment Address: '.$address.' | ';

      return false;
    }

    function after_process() {
      return false;
    }

    function get_error() {
      return false;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_FASTCOIN_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }

      return $this->_check;
    }

    function install() {
      global $messageStack;

      if (defined('MODULE_PAYMENT_FASTCOIN_STATUS')) {
        $messageStack->add_session('Fastcoin module already installed.', 'error');
        tep_redirect(tep_href_link(FILENAME_MODULES, 'set=payment&module=fastcoin', 'NONSSL'));
        return 'failed';
      }

      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Fastcoin Module', 'MODULE_PAYMENT_FASTCOIN_STATUS', 'True', 'Do you want to accept Fastcoin payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Host Address', 'MODULE_PAYMENT_FASTCOIN_HOST', 'localhost:8332', 'The host address and port for Fastcoin RPC', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Username', 'MODULE_PAYMENT_FASTCOIN_LOGIN', '', 'The Username for Fastcoin RPC', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_PAYMENT_FASTCOIN_PASSWORD', '', 'The Password for Fastcoin RPC', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_FASTCOIN_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Notification Key', 'MODULE_PAYMENT_FASTCOIN_NOTIFICATION_KEY', '', 'Authenticates python script to update order status on payment received. Set to more than 15 random characters', '7', '0', now())");
    //tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_FASTCOIN_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_FASTCOIN_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove() {
      tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return 
          array('MODULE_PAYMENT_FASTCOIN_STATUS', 
              //'MODULE_PAYMENT_FASTCOIN_ZONE', 
                'MODULE_PAYMENT_FASTCOIN_ORDER_STATUS_ID', 
                'MODULE_PAYMENT_FASTCOIN_SORT_ORDER', 
                'MODULE_PAYMENT_FASTCOIN_NOTIFICATION_KEY', 
                'MODULE_PAYMENT_FASTCOIN_HOST', 
                'MODULE_PAYMENT_FASTCOIN_LOGIN', 
                'MODULE_PAYMENT_FASTCOIN_PASSWORD');
    }
  }
