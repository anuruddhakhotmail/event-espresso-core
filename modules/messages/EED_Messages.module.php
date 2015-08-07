<?php
/**
 * This file contains the module for the messages system
 *
 * @since 4.5.0
 * @package  Event Espresso
 * @subpackage modules, messages
 */
if ( ! defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
/**
 *
 * Messages module.  Takes care of registering all the triggers for messages.
 *
 * @since 4.5.0
 *
 * @package		Event Espresso
 * @subpackage	modules, messages
 * @author 		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class EED_Messages  extends EED_Module {

	/**
	 * This holds the EE_messages controller
	 *
	 * @var EE_messages
	 */
	protected  static $_EEMSG;


	/**
	 * This holds the EE_Messages_Processor business class.
	 *
	 * @type EE_Messages_Processor
	 */
	protected static $_MSGPROCESSOR;


	/**
	 * holds all the paths for various messages components.
	 * Utilized by autoloader registry
	 *
	 * @var array
	 */
	protected static $_MSG_PATHS;



	/**
	 * This will hold an array of messages template packs that are registered in the messages system.
	 * Format is:
	 * array(
	 * 	'template_pack_dbref' => EE_Messages_Template_Pack (instance)
	 * )
	 *
	 * @var EE_Messages_Template_Pack[]
	 */
	protected static $_TMP_PACKS = array();





	/**
	 * @return EED_Module
	 */
	public static function instance() {
		return parent::get_instance( __CLASS__ );
	}




	/**
	 *  set_hooks - for hooking into EE Core, other modules, etc
	 *
	 *  @since 4.5.0
	 *
	 *  @return 	void
	 */
	public static function set_hooks() {
		//actions
		add_action( 'AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Messages', 'payment' ), 10, 2 );
		add_action( 'AHEE__EE_Registration_Processor__trigger_registration_update_notifications', array( 'EED_Messages', 'maybe_registration' ), 10, 2 );
		//filters
		add_filter( 'FHEE__EE_Registration__receipt_url__receipt_url', array( 'EED_Messages', 'registration_message_trigger_url' ), 10, 4 );
		add_filter( 'FHEE__EE_Registration__invoice_url__invoice_url', array( 'EED_Messages', 'registration_message_trigger_url' ), 10, 4 );
		//register routes
		self::_register_routes();
	}

	/**
	 * 	set_hooks_admin - for hooking into EE Admin Core, other modules, etc
	 *
	 *  @access 	public
	 *  @return 	void
	 */
	public static function set_hooks_admin() {
		//actions
		add_action( 'AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Messages', 'payment' ), 10, 2 );
		add_action( 'AHEE__Transactions_Admin_Page___send_payment_reminder__process_admin_payment_reminder', array( 'EED_Messages', 'payment_reminder'), 10 );
		add_action( 'AHEE__EE_Registration_Processor__trigger_registration_update_notifications', array( 'EED_Messages', 'maybe_registration' ), 10, 3 );
		add_action( 'AHEE__Extend_Registrations_Admin_Page___newsletter_selected_send', array( 'EED_Messages', 'send_newsletter_message'), 10, 2 );
		add_action( 'AHEE__EES_Espresso_Cancelled__process_shortcode__transaction', array( 'EED_Messages', 'cancelled_registration' ), 10 );
		//filters
		add_filter( 'FHEE__EE_Admin_Page___process_resend_registration__success', array( 'EED_Messages', 'process_resend' ), 10, 2 );
		add_filter( 'FHEE__EE_Admin_Page___process_admin_payment_notification__success', array( 'EED_Messages', 'process_admin_payment'), 10, 2 );
		add_filter( 'FHEE__EE_Registration__receipt_url__receipt_url', array( 'EED_Messages', 'registration_message_trigger_url' ), 10, 4 );
		add_filter( 'FHEE__EE_Registration__invoice_url__invoice_url', array( 'EED_Messages', 'registration_message_trigger_url' ), 10, 4 );
	}




	/**
	 * All the message triggers done by route go in here.
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	protected static function _register_routes() {
		EE_Config::register_route( 'msg_url_trigger', 'Messages', 'run' );
		EE_Config::register_route( 'msg_cron_trigger', 'Messages', 'run_cron' );
		EE_Config::register_route( 'msg_browser_trigger', 'Messages', 'browser_trigger' );
		do_action( 'AHEE__EED_Messages___register_routes' );
	}



	/**
	 * This is called when a browser display trigger is executed.
	 * The browser display trigger is typically used when a already generated message is displayed directly in the browser.
	 * @since 4.9.0
	 * @param WP $WP
	 */
	public function browser_trigger( $WP ) {
		//ensure controller is loaded
		self::_load_controller();
		$token = EE_Registry::instance()->REQ->get( 'token' );
		try {
			$mtg = new EE_Message_Generated_From_Token( $token, 'html', self::$_EEMSG );
			self::$_MSGPROCESSOR->generate_and_send_now( $mtg );
		} catch( EE_Error $e ) {
			$error_msg = __( 'Please note that a system message failed to send due to a technical issue.', 'event_espresso' );
			// add specific message for developers if WP_DEBUG in on
			$error_msg .= '||' . $e->getMessage();
			EE_Error::add_error( $error_msg, __FILE__, __FUNCTION__, __LINE__ );
		}
	}



	/**
	 *  This runs when the msg_url_trigger route has initiated.
	 *
	 * @since 4.5.0
	 * @param WP $WP
	 * @throws EE_Error
	 * @return    void
	 */
	public function run( $WP ) {
		//ensure controller is loaded
		self::_load_controller();
		// attempt to process message
		try {
			$mtg = new EE_Message_To_Generate_From_Request( self::$_EEMSG, EE_Registry::instance()->REQ );
			self::$_MSGPROCESSOR->generate_and_send_now( $mtg );
		} catch ( EE_Error $e ) {
			$error_msg = __( 'Please note that a system message failed to send due to a technical issue.', 'event_espresso' );
			// add specific message for developers if WP_DEBUG in on
			$error_msg .= '||' . $e->getMessage();
			EE_Error::add_error( $error_msg, __FILE__, __FUNCTION__, __LINE__ );
		}
	}


	/**
	 * This is triggered by the 'msg_cron_trigger' route.
	 * @param WP $WP
	 */
	public function run_cron( $WP ) {
		self::_load_controller();
		//get required vars
		$cron_type = EE_Registry::instance()->REQ->get( 'type' );
		$nonce = EE_Registry::instance()->REQ->get( '_nonce' );
		header("HTTP/1.1 200 OK");

		//now let's verify nonce, if not valid exit immediately
		if ( ! wp_verify_nonce( $nonce, 'EE_Messages_Scheduler_' . $cron_type ) ) {
			/**
			 * trigger error so this gets in the error logs.  This is important because it happens on a non-user request.
			 */
			trigger_error( __( 'Invalid Nonce', 'event_espresso' ) );
		}

		$method = 'batch_' . $cron_type . '_from_queue';
		if ( method_exists( self::$_MSGPROCESSOR, $method ) ) {
			self::$_MSGPROCESSOR->$method();
		} else {
			//no matching task
			/**
			 * trigger error so this gets in the error logs.  This is important because it happens on a non user request.
			 */
			trigger_error( sprintf( __('There is no task corresponding to this route %s', 'event_espresso' ), $cron_type ) );
		}
		exit();
	}




	/**
	 * This is used to retrieve the template pack for the given name.
	 * Retrieved packs are cached on the static $_TMP_PACKS array.  If there is no class matching the given name then the default template pack is returned.
	 *
	 * @deprecated 4.9.0  @see EEH_MSG_Template::get_template_pack()
	 *
	 * @param string $template_pack_name This should correspond to the dbref of the template pack (which is also used in generating the Pack class name).
	 *
	 * @return EE_Messages_Template_Pack
	 */
	public static function get_template_pack( $template_pack_name ) {
		EE_Registry::instance()->load_helper( 'MSG_Template' );
		return EEH_MSG_Template::get_template_pack( $template_pack_name );
	}




	/**
	 * Retrieves an array of all template packs.
	 * Array is in the format array( 'dbref' => EE_Messages_Template_Pack )
	 * @deprecated 4.9.0  @see EEH_MSG_Template_Pack::get_template_pack_collection
	 *
	 * @return EE_Messages_Template_Pack[]
	 */
	public static function get_template_packs() {
		EE_Registry::instance()->load_helper( 'MSG_Template' );

		//for backward compat, let's make sure this returns in the same format as originally.
		$template_pack_collection = EEH_MSG_Template::get_template_pack_collection();
		$template_pack_collection->rewind();
		$template_packs = array();
		while ( $template_pack_collection->valid() ) {
			$template_packs[ $template_pack_collection->current()->dbref ] = $template_pack_collection->current();
			$template_pack_collection->next();
		}
		return $template_packs;
	}



	/**
	 * This simply makes sure the autoloaders are registered for the EE_Messages system.
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	public static function set_autoloaders() {
		if ( empty( self::$_MSG_PATHS ) ) {
			self::_set_messages_paths();
			EE_Registry::instance()->load_helper( 'Autoloader' );
			foreach ( self::$_MSG_PATHS as $path ) {
				EEH_Autoloader::register_autoloaders_for_each_file_in_folder( $path );
			}
		}
	}




	/**
	 * Take care of adding all the paths for the messages components to the $_MSG_PATHS property
	 * for use by the Messages Autoloaders
	 *
	 * @since 4.5.0
	 *
	 * @return void.
	 */
	protected static function _set_messages_paths() {
		$dir_ref = array(
			'messages',
			'messages/message_type',
			'messages/messenger',
			'messages/defaults',
			'messages/defaults/email',
			'messages/data_class',
			'messages/validators',
			'messages/validators/email',
			'messages/validators/html',
			'shortcodes'
			);
		$paths = array();
		foreach ( $dir_ref as $index => $dir ) {
			$paths[$index] = EE_LIBRARIES . $dir;
		}
		self::$_MSG_PATHS = apply_filters( 'FHEE__EED_Messages___set_messages_paths___MSG_PATHS', $paths );
	}


	/**
	 * Takes care of loading the Messages system controller into the $_EEMSG property
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	protected static function _load_controller() {
		if ( ! self::$_EEMSG instanceof EE_messages ) {
			EE_Registry::instance()->load_core( 'Request_Handler' );
			self::set_autoloaders();
			self::$_EEMSG = new EE_messages();
			self::$_MSGPROCESSOR = EE_Registry::instance()->load_class( 'Messages_Processor', self::$_EEMSG );
		}
	}



	/**
	 * @param EE_Transaction $transaction
	 */
	public static function payment_reminder( EE_Transaction $transaction ) {
		self::_load_controller();
		$data = array( $transaction, null );
		self::$_MSGPROCESSOR->generate_for_all_active_messengers( 'payment_reminder', $data );
	}



	/**
	 * Any messages triggers for after successful gateway payments should go in here.
	 * @param  EE_Transaction object
	 * @param  EE_Payment object
	 * @return void
	 */
	public static function payment( EE_Transaction $transaction, EE_Payment $payment ) {
		self::_load_controller();
		$data = array( $transaction, $payment );
		$message_type = self::_get_payment_message_type( $payment->STS_ID() );
		//if payment amount is less than 0 then switch to payment_refund message type.
		$message_type = $payment->amount() < 0 ? 'payment_refund' : $message_type;
		self::$_MSGPROCESSOR->generate_for_all_active_messengers( $message_type, $data );
	}



	/**
	 * @param EE_Transaction $transaction
	 */
	public static function cancelled_registration( EE_Transaction $transaction ) {
		self::_load_controller();
		$data = array( $transaction, NULL );
		self::$_MSGPROCESSOR->generate_for_all_active_messengers( 'cancelled_registration', $data );
	}



	/**
	 * Trigger for Registration messages
	 * Note that what registration message type is sent depends on what the reg status is for the registrations on the incoming transaction.
	 *
	 * @param EE_Registration $registration
	 * @param array $extra_details
	 * @return void
	 */
	public static function maybe_registration( EE_Registration $registration, $extra_details = array() ) {

		if ( ! self::_verify_registration_notification_send( $registration, $extra_details ) ) {
			//no messages please
			return;
		}


		EE_Registry::instance()->load_helper( 'MSG_Template' );

		//get all registrations so we make sure we send messages for the right status.
		$all_registrations = $registration->transaction()->registrations();

		//cached array of statuses so we only trigger messages once per status.
		$statuses_sent = array();
		self::_load_controller();
		$mtgs = array();

		//loop through registrations and trigger messages once per status.
		foreach ( $all_registrations as $reg ) {

			//already triggered?
			if ( in_array( $reg->status_ID(), $statuses_sent ) ) {
				continue;
			}

			$message_type = EEH_MSG_Template::convert_reg_status_to_message_type( $reg->status_ID() );
			$mtgs = $mtgs + self::$_MSGPROCESSOR->setup_mtgs_for_all_active_messengers( $message_type, array( $registration->transaction(), null, $reg->status_ID() ) );
			$statuses_sent[] = $reg->status_ID();
		}

		$mtgs = $mtgs + self::$_MSGPROCESSOR->setup_mtgs_for_all_active_messengers( 'registration_summary', array( $registration->transaction(), null ) );

		//batchqueue and initiate request
		self::$_MSGPROCESSOR->batch_queue_for_generation_and_persist( $mtgs );
		self::$_MSGPROCESSOR->get_queue()->initiate_request_by_priority();
	}



	/**
	 * This is a helper method used to very whether a registration notification should be sent or
	 * not.  Prevents duplicate notifications going out for registration context notifications.
	 *
	 * @param EE_Registration $registration  [description]
	 * @param array           $extra_details [description]
	 *
	 * @return bool          true = send away, false = nope halt the presses.
	 */
	protected static function _verify_registration_notification_send( EE_Registration $registration, $extra_details = array() ) {
		 //self::log(
		 //	__CLASS__, __FUNCTION__, __LINE__,
		 //	$registration->transaction(),
		 //	array( '$extra_details' => $extra_details )
		 //);
		// currently only using this to send messages for the primary registrant
		if ( ! $registration->is_primary_registrant() ) {
			return false;
		}
		// first we check if we're in admin and not doing front ajax
		if ( is_admin() && ! EE_FRONT_AJAX ) {
			//make sure appropriate admin params are set for sending messages
			if ( empty( $_REQUEST[ 'txn_reg_status_change' ][ 'send_notifications' ] ) || ! absint( $_REQUEST[ 'txn_reg_status_change' ][ 'send_notifications' ] ) ) {
				//no messages sent please.
				return false;
			}
		} else {
			// frontend request (either regular or via AJAX)
			// TXN is NOT finalized ?
			if ( ! isset( $extra_details[ 'finalized' ] ) || $extra_details[ 'finalized' ] === false ) {
				return false;
			}
			// return visit but nothing changed ???
			if (
				isset( $extra_details[ 'revisit' ], $extra_details[ 'status_updates' ] ) &&
				$extra_details[ 'revisit' ] && ! $extra_details[ 'status_updates' ]
			) {
				return false;
			}
			// NOT sending messages && reg status is something other than "Not-Approved"
			if (
				! apply_filters( 'FHEE__EED_Messages___maybe_registration__deliver_notifications', false ) &&
				$registration->status_ID() !== EEM_Registration::status_id_not_approved
			) {
				return false;
			}
		}
		// release the kraken
		return true;
	}



	/**
	 * Simply returns an array indexed by Registration Status ID and the related message_type name associated with that status id.
	 *
	 * @deprecated 4.9.0  Use EEH_MSG_Template::reg_status_to_message_type_array()
	 *                    or EEH_MSG_Template::convert_reg_status_to_message_type
	 *
	 * @param string $reg_status
	 *
	 * @return array
	 */
	protected static function _get_reg_status_array( $reg_status = '' ) {
		EE_Registry::instance()->load_helper( 'MSG_Template' );
		return EEH_MSG_Template::convert_reg_status_to_message_type( $reg_status )
			? EEH_MSG_Template::convert_reg_status_to_message_type( $reg_status )
			: EEH_MSG_Template::reg_status_to_message_type_array();
	}



	/**
	 * Simply returns the payment message type for the given payment status.
	 *
	 * @deprecated 4.9.0 Use EEH_MSG_Template::payment_status_to_message_type_array
	 *                   or EEH_MSG_Template::convert_payment_status_to_message_type
	 *
	 * @param string  $payment_status The payment status being matched.
	 *
	 * @return string|bool The payment message type slug matching the status or false if no match.
	 */
	protected static function _get_payment_message_type( $payment_status ) {
		EE_Registry::instance()->load_helper( 'MSG_Template' );
		return EEH_MSG_Template::convert_payment_status_to_message_type( $payment_status )
			? EEH_MSG_Template::convert_payment_status_to_message_type( $payment_status )
			: false;
	}




	/**
	 * Message triggers for a resending already sent message(s) (via EE_Message list table)
	 *
	 * @access public
	 * @param array $req_data This is the $_POST & $_GET data sent from EE_Admin Pages
	 * @return bool          success/fail
	 */
	public static function process_resend( $req_data ) {
		self::_load_controller();

		//if $msgID in this request then skip to the new resend_message
		if( EE_Registry::instance()->REQ->get( 'MSG_ID' ) ) {
			return self::resend_message();
		}

		if ( ! $messages_to_send = self::$_MSGPROCESSOR->setup_messages_to_generate_from_registration_ids_in_request() ) {
			return false;
		}

		try {
			self::$_MSGPROCESSOR->batch_queue_for_generation_and_persist( $messages_to_send );
			self::$_MSGPROCESSOR->get_queue()->initiate_request_by_priority();
		} catch( EE_Error $e ) {
			EE_Error::add_error( $e->getMessage(), __FILE__, __FUNCTION__, __LINE__ );
			return false;
		}
		return true; //everything got queued.
	}


	/**
	 * Message triggers for a resending already sent message(s) (via EE_Message list table)
	 * @return bool
	 */
	public static function resend_message() {
		self::_load_controller();


		$msgID = EE_Registry::instance()->REQ->get( 'MSG_ID' );
		if ( ! $msgID ) {
			EE_Error::add_error( __('Something went wrong because there is no "MSG_ID" value in the request', 'event_espresso'), __FILE__, __FUNCTION__, __LINE__ );
			return false;
		}

		self::$_MSGPROCESSOR->setup_messages_from_ids_and_send( (array) $msgID );

		//setup success message.
		$count_ready_for_resend = self::$_MSGPROCESSOR->get_queue()->count_STS_in_queue( EEM_Message::status_resend );
		EE_Error::add_success( sprintf(
			_n(
				'There was %d message queued for resending.',
				'There were %d messages queued for resending.',
				$count_ready_for_resend,
				'event_espresso'
			),
			$count_ready_for_resend
		) );
		return true;
	}





	/**
	 * Message triggers for manual payment applied by admin
	 * @param  bool     $success incoming success value
	 * @param  EE_Payment $payment EE_payment object
	 * @return bool              success/fail
	 */
	public static function process_admin_payment( $success = TRUE, EE_Payment $payment ) {
		EE_Registry::instance()->load_helper( 'MSG_Template' );
		//we need to get the transaction object
		$transaction = $payment->transaction();
		if ( $transaction instanceof EE_Transaction ) {
			$data = array( $transaction, $payment );
			$message_type = EEH_MSG_Template::convert_payment_status_to_message_type( $payment->STS_ID() );

			//if payment amount is less than 0 then switch to payment_refund message type.
			$message_type = $payment->amount() < 0 ? 'payment_refund' : $message_type;

			//if payment_refund is selected, but the status is NOT accepted.  Then change message type to false so NO message notification goes out.
			$message_type = $message_type == 'payment_refund' && $payment->STS_ID() != EEM_Payment::status_id_approved ? false : $message_type;

			self::_load_controller();

			self::$_MSGPROCESSOR->generate_for_all_active_messengers( $message_type, $data );

			//get count of queued for generation
			$count_to_generate = self::$_MSGPROCESSOR->get_queue()->count_STS_in_queue( EEM_Message::status_incomplete );

			if ( $count_to_generate > 0 ) {
				return true;
			} else {
				$count_failed = self::$_MSGPROCESSOR->get_queue()->count_STS_in_queue( EEM_Message::instance()->stati_indicating_failed_sending() );
				EE_Error::add_error( sprintf(
					_n(
						'The payment notification generation failed.',
						'%d payment notifications failed being sent.',
						$count_failed,
						'event_espresso'
					),
					$count_failed
				), __FILE__, __FUNCTION__, __LINE__ );
				return false;
			}
		} else {
			EE_Error::add_error(
				'Unable to generate the payment notification because the given value for the transaction is invalid.',
				'event_espresso'
			);
			return false;
		}
	}



	/**
	 * Callback for AHEE__Extend_Registrations_Admin_Page___newsletter_selected_send trigger
	 *
	 * @since   4.3.0
	 *
	 * @param  EE_Attendee[]  $contacts   an array of EE_Attendee objects
	 * @param  int      	      $grp_id     a specific message template group id.
	 * @return void
	 */
	public static function send_newsletter_message( $contacts, $grp_id ) {
		//make sure mtp is id and set it in the EE_Request Handler later messages setup.
		EE_Registry::instance()->REQ->set( 'GRP_ID', (int) $grp_id );
		self::_load_controller();
		self::$_MSGPROCESSOR->generate_for_all_active_messengers( 'newsletter', $contacts );
	}


	/**
	 * Callback for AHEE__Extend_Registrations_Admin_Page___newsletter_selected_send trigger
	 *
	 * @since   4.3.0
	 *
	 * @param 	string 	$registration_message_trigger_url
	 * @param 	EE_Registration $registration
	 * @param string 	$messenger
	 * @param string 	$message_type
	 * @return 	string
	 */
	public static function registration_message_trigger_url( $registration_message_trigger_url, EE_Registration $registration, $messenger = 'html', $message_type = 'invoice' ) {
		EE_Registry::instance()->load_helper('MSG_Template');
		// whitelist $messenger
		switch ( $messenger ) {
			case 'pdf' :
				$sending_messenger = 'pdf';
				$generating_messenger = 'html';
				break;
			case 'html' :
			default :
				$sending_messenger = 'html';
				$generating_messenger = 'html';
				break;
		}
		// whitelist $message_type
		switch ( $message_type ) {
			case 'receipt' :
				$message_type = 'receipt';
				break;
			case 'invoice' :
			default :
				$message_type = 'invoice';
				break;
		}
		// verify that both the messenger AND the message type are active
		if ( EEH_MSG_Template::is_messenger_active( $sending_messenger ) && EEH_MSG_Template::is_mt_active( $message_type )) {
			//need to get the correct message template group for this (i.e. is there a custom invoice for the event this registration is registered for?)
			$template_query_params = array(
				'MTP_is_active' => TRUE,
				'MTP_messenger' => $generating_messenger,
				'MTP_message_type' => $message_type,
				'Event.EVT_ID' => $registration->event_ID()
			);
			//get the message template group.
			$msg_template_group = EEM_Message_Template_Group::instance()->get_one( array( $template_query_params ));
			//if we don't have an EE_Message_Template_Group then return
			if ( ! $msg_template_group instanceof EE_Message_Template_Group ) {
				// remove EVT_ID from query params so that global templates get picked up
				unset( $template_query_params[ 'Event.EVT_ID' ] );
				//get global template as the fallback
				$msg_template_group = EEM_Message_Template_Group::instance()->get_one( array( $template_query_params ));
			}
			//if we don't have an EE_Message_Template_Group then return
			if ( ! $msg_template_group instanceof EE_Message_Template_Group ) {
				return '';
			}
			// generate the URL
			$registration_message_trigger_url = EEH_MSG_Template::generate_url_trigger(
				$sending_messenger,
				$generating_messenger,
				'purchaser',
				$message_type,
				$registration,
				$msg_template_group->ID(),
				$registration->transaction_ID()
			);

		}
		return $registration_message_trigger_url;
	}




	/**
	 * Use to generate and return a message preview!
	 * @param  string $type    This should correspond with a valid message type
	 * @param  string $context This should correspond with a valid context for the message type
	 * @param  string $messenger This should correspond with a valid messenger.
	 * @param bool 	  $send true we will do a test send using the messenger delivery, false we just do a regular preview
	 * @return string          The body of the message.
	 */
	public static function preview_message( $type, $context, $messenger, $send = FALSE ) {
		self::_load_controller();
		$mtg = new EE_Message_To_Generate(
			$messenger,
			$type,
			array(),
			self::$_EEMSG,
			$context,
			true
		);
		$generated_preview_queue = self::$_MSGPROCESSOR->generate_for_preview( $mtg );
		if ( $generated_preview_queue instanceof EE_Messages_Queue ) {
			return $generated_preview_queue->get_queue()->current()->content();
		} else {
			return $generated_preview_queue;
		}
	}




	/**
	 * This is a method that allows for sending a message using a messenger matching the string given and the provided
	 * EE_Message_Queue object.
	 *
	 * @since 4.9.0
	 *
	 * @param string               $messenger a string matching a valid active messenger in the system
	 * @param string $message_type Although it seems contrary to the name of the method, a message type name is
	 *                             still required to send along the message type to the messenger because this is used
	 *                             for determining what specific variations might be loaded for the generated message.
	 * @param EE_Messages_Queue     $queue
	 *
	 * @return bool          success or fail.
	 */
	public static function send_message_with_messenger_only( $messenger, $message_type, EE_Messages_Queue $queue ) {
		self::_load_controller();
		//set mtg
		$mtg = new EE_Message_To_Generate_From_Queue(
			$messenger,
			$message_type,
			self::$_EEMSG,
			$queue
		);

		return self::$_MSGPROCESSOR->queue_for_sending( $mtg );
	}



	/**
	 * debug
	 *
	 * @param string $class
	 * @param string $func
	 * @param string $line
	 * @param \EE_Transaction $transaction
	 * @param array $info
	 * @param bool $display_request
	 */
	protected static function log( $class = '', $func = '', $line = '', EE_Transaction $transaction, $info = array(), $display_request = false ) {
		EE_Registry::instance()->load_helper('Debug_Tools');
		if ( WP_DEBUG && false ) {
			if ( $transaction instanceof EE_Transaction ) {
				// don't serialize objects
				$info = EEH_Debug_Tools::strip_objects( $info );
				$info[ 'TXN_status' ] = $transaction->status_ID();
				$info[ 'TXN_reg_steps' ] = $transaction->reg_steps();
				if ( $transaction->ID() ) {
					$index = 'EE_Transaction: ' . $transaction->ID();
					EEH_Debug_Tools::log( $class, $func, $line, $info, $display_request, $index );
				}
			}
		}

	}

}
// End of file EED_Messages.module.php
// Location: /modules/messages/EED_Messages.module.php
