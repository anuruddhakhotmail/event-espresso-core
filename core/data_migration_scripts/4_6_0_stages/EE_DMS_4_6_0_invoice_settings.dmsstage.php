<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }
/**
 * Event Espresso
 *
 * Event Registration and Ticketing Management Plugin for WordPress
 *
 * @ package			Event Espresso
 * @ author			    Event Espresso
 * @ copyright		(c) 2008-2014 Event Espresso  All Rights Reserved.
 * @ license			http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link					http://www.eventespresso.com
 * @ version		 	$VID:$
 *
 * ------------------------------------------------------------------------
 *
 * class EE_DMS_4_6_0_invoice_settings
 *
 * @package			Event Espresso
 * @subpackage		/core/data_migration_scripts/4_3_0_stages/
 * @author				Brent Christensen
 *
 * ------------------------------------------------------------------------
 */
class EE_DMS_4_6_0_invoice_settings extends EE_Data_Migration_Script_Stage {

	/**
	 * Just initializes the status of the migration
	 */
	public function __construct() {
		$this->_pretty_name = __( 'Update Invoice Settings', 'event_espresso' );
		parent::__construct();
	}



	/**
	 * _count_records_to_migrate
	 * Counts the records to migrate; the public version may cache it
	 *
	 * @access protected
	 * @return int
	 */
	protected function _count_records_to_migrate() {
		return 1;
	}



	/**
	 *	_migration_step
	 *
	 * @access protected
	 * @param int $num_items
	 * @throws EE_Error
	 * @return int number of items ACTUALLY migrated
	 */
	protected function _migration_step( $num_items = 1 ){

		EE_Registry::instance()->load_helper( 'Template' );
		$templates_relative_path = 'modules/gateways/Invoice/lib/templates/';
		$overridden_invoice_body = EEH_Template::locate_template( $templates_relative_path . 'invoice_body.template.php', NULL, FALSE, FALSE, TRUE );
		$overridden_receipt_body= EEH_Template::locate_template( $templates_relative_path . 'receipt_body.template.php', NULL, FALSE, FALSE, TRUE );
		if( $overridden_invoice_body || $overridden_receipt_body ) {
			EE_Error::add_persistent_admin_notice( 'invoice_overriding_templates', sprintf( __( 'Note: since Event Espresso 4.5, PDF and HTML Invoices and Receipts were converted Messages are can be changed just like any other messages. You will need to manually update the Invoice and Receipt message templates in Messages -> Invoice and Messages -> Receipt. Your old overridden templates will no longer be used.')), TRUE );
		}

		//regardless of whether it worked or not, we ought to continue the migration
		$this->set_completed();
		return 1;
	}
}
// End of file EE_DMS_4_6_0_invoice_settings.dmsstage.php
// Location: /core/data_migration_scripts/4_3_0_stages/EE_DMS_4_6_0_invoice_settings.dmsstage.php