<?php if (!defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
do_action('action_hook_espresso_log', __FILE__, ' FILE LOADED', '' );
/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package			Event Espresso
 * @ author				Seth Shoultes
 * @ copyright		(c) 2008-2011 Event Espresso  All Rights Reserved.
 * @ license			{@link http://eventespresso.com/support/terms-conditions/}   * see Plugin Licensing *
 * @ link					{@link http://www.eventespresso.com}
 * @ since		 		4.0
 *
 * ------------------------------------------------------------------------
 *
 * EE_Datetime class
 *
 * @package				Event Espresso
 * @subpackage		includes/classes/EE_Datetime.class.php
 * @author				Brent Christensen 
 *
 * ------------------------------------------------------------------------
 */
class EE_Datetime extends EE_Base_Class{
	
    /**
    *	Datetime ID
	* 
	* 	primary key
	*	
	* 	@access	protected
    *	@var int	
    */
	protected $_DTT_ID;
	
	
	
    /**
    *	Event ID
	* 
	* 	foreign key
	* 
	*	@access	protected
    *	@var int	
    */
	protected $_EVT_ID;
	
	
	
    /**
    *	Primary Datetime (first)
	* 
	* 	foreign key
	* 
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_is_primary = NULL;
	
	
	
    /**
    *	Event Start Timestamp
	* 
	*	date / time
	*  
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_EVT_start;
	
	
	
    /**
    *	Event End Timestamp
	* 
	*	date / time
	*  
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_EVT_end;
	
	
	
	
	
	
    /**
    *	REG Start Timestamp
	* 
	*	date / time
	*  
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_REG_start;
	
	
	
    /**
    *	REG End Timestamp
	* 
	*	date / time
	*  
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_REG_end;
		
	
	
    /**
    *	reg limit
	* 
    *	registration limit for this date/time slot
	* 
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_reg_limit = NULL;	
		
	
	
    /**
    *	available spaces left
	* 
    *	registration limit for this date/time slot
	* 
	*	@access	protected
    *	@var int	
    */
	protected $_DTT_tckts_left = NULL;	
	
	
	
    /**
    *	date format
	* 
    *	pattern or format for displaying dates
	* 
	*	@access	protected
    *	@var string	
    */
	protected $_dt_frmt = 'F j, Y';	
	
	
	
    /**
    *	time format
	* 
    *	pattern or format for displaying time
	* 
	*	@access	protected
    *	@var string	
    */
	protected $_tm_frmt = 'g:i a';



	/**
	 * Timezone
	 * This gets set by the "set_timezone()" method so that we know what timezone incoming strings|timestamps are in.  This can also be used before a get to set what timezone you want strings coming out of the object to be in.
	 * @var string
	 */
	protected $_timezone = NULL;


	/**
	 *
	 * @var EE_Event
	 */
	protected $_Event;
	
	
	/**
	 *
	 * @var EE_Registration[]
	 */
	protected $_Registration;

	
	


	/**
	* Event Datetime constructor
	*
	* @access 		public
	* @param			int								$EVT_ID 						Event ID
	* @param			int								$DTT_is_primary 		Marks this as a Primary Date time - the first event or reg date
	* @param			mixed int | string 		$DTT_EVT_start 			Unix timestamp or date string for the event beginning
	* @param			mixed int | string		$DTT_EVT_end			Unix timestamp or date string for the event  end
	* @param			mixed int | string 		$DTT_REG_start 			Unix timestamp or date string for the registration beginning
	* @param			mixed int | string		$DTT_REG_end			Unix timestamp or date string for the registration end
	* @param			mixed int | NULL		$DTT_reg_limit 			Registration Limit for this time period - int for limit, NULL for no limit
	* @param			mixed int | NULL		$DTT_tckts_left 		Spaces left for this timeslot - int for limit, NULL for no limit
	* @param			int								$DTT_ID 						Event Datetime ID
	*/
	public function __construct( 
														$EVT_ID = NULL, 
														$DTT_is_primary = 0, 
														$DTT_EVT_start = NULL, 
														$DTT_EVT_end = NULL, 
														$DTT_REG_start = NULL, 
														$DTT_REG_end = NULL, 
														/* DO NOT DELETE - NEW FEATURE IN PROGRESS 
														$DTT_reg_limit = NULL, 
														$DTT_tckts_left = NULL, */
														$DTT_ID = NULL 
												) {
	
	if(is_array($EVT_ID)){
			parent::__construct($EVT_ID);
			return;
		}
		$reflector = new ReflectionMethod($this,'__construct');	
		$arrayForParent=array();
		foreach($reflector->getParameters() as $param){
			$paramName=$param->name;
			$arrayForParent[$paramName]=$$paramName;//yes, that's using a variable variable.
		}
		parent::__construct($arrayForParent);

	}





	/**
	 * See $_timezone property for description of what the timezone property is for.  This SETS the timezone internally for being able to refernece what timezone we are running conversions on when converting TO the internal timezone (UTC Unix Timestamp) for the object OR when converting FROM the internal timezone (UTC Unix Timestamp).
	 *
	 *
	 * @access public
	 * @param string $timezone A valid timezone string as described by @link http://www.php.net/manual/en/timezones.php
	 * @return void
	 */
	public function set_timezone( $timezone ) {
		$timezone = empty( $timezone ) ? get_option( 'timezone_string' ) : $timezone;
		EE_Datetime_Field::validate_timezone( $timezone ); //just running validation on the timezone.
		$this->_timezone = $timezone;
	}




	/**
	 * This just returns whatever is set for the current timezone.
	 *
	 * @access public
	 * @return string timezone string
	 */
	public function get_timezone() {
		return $this->_timezone;
	}




	/**
	*		Set event start date
	* 
	*		set the start date for an event 
	* 
	* 		@access		public		
	*		@param		string		$date 		a string representation of the event's date ex:  Dec. 25, 2025 or 12-25-2025
	*/	
	public function set_start_date( $date ) {
		$this->_set_date_for($date, 'DTT_EVT_start');
	}





	/**
	*		Set event start time
	* 
	*		set the start time for an event 
	* 
	* 		@access		public		
	*		@param		string		$time 		a string representation of the event time ex:  9am  or  7:30 PM
	*/	
	public function set_start_time( $time ) {
		$this->_set_time_for($time,'DTT_EVT_start');
	}




	/**
	*		Set event end date
	* 
	*		set the end date for an event 
	* 
	* 		@access		public		
	*		@param		string		$date 		a string representation of the event's date ex:  Dec. 25, 2025 or 12-25-2025
	*/	
	public function set_end_date( $date ) {
		$this->_set_date_for($date,'DTT_EVT_end');
	}





	/**
	*		Set event end time
	* 
	*		set the end time for an event 
	* 
	* 		@access		public		
	*		@param		string		$time 		a string representation of the event time ex:  9am  or  7:30 PM
	*/	
	public function set_end_time( $time ) {
		$this->_set_time_for($time,'DTT_EVT_end');
	}
	
	/**
	 * Privately used to set the time on the specified field. IE, doesnt change the date part thats already set 
	 * @param string $time representation of the time, eg 9am
	 * @param string $field_name like DTT_EVT_end
	 */
	private function _set_time_for($time,$field_name){
		$field = $this->_get_model()->field_settings_for($field_name);
		$attribute_field_name = $this->_get_private_attribute_name($field_name);
		$this->$attribute_field_name = $field->prepare_for_set_with_new_time($time, $this->$attribute_field_name, $this->_timezone);
	}
	
	
	
	/**
	 *  Privately used to set the date on the specified field. IE, doesn't change the time part thats already set
	 * @param string $date repsentation of the date, eg 2013-03-12
	 * @param string $field_name like DTT_REG_start
	 */
	private function _set_date_for($date,$field_name){
		$field = $this->_get_model()->field_settings_for($field_name);
		$attribute_field_name = $this->_get_private_attribute_name($field_name);
		$this->$attribute_field_name = $field->prepare_for_set_with_new_date($date, $this->$attribute_field_name, $this->_timezone );
	}




	/**
	*		Set registration start date
	* 
	*		set the registration start date for an event 
	* 
	* 		@access		public		
	*		@param		string		$date 		a string representation of the event's date ex:  Dec. 25, 2025 or 12-25-2025
	*/	
	public function set_reg_start_date( $date ) {
		$this->_set_date_for( $date, 'DTT_REG_start' );
	}





	/**
	*		Set registration start time
	* 
	*		set the registration start time for an event 
	* 
	* 		@access		public		
	*		@param		string		$time 		a string representation of the event time ex:  9am  or  7:30 PM
	*/	
	public function set_reg_start_time( $time ) {
		$this->_set_time_for( $time, 'DTT_REG_start' );
	}




	/**
	*		Set registration end date
	* 
	*		set the registration end date for an event 
	* 
	* 		@access		public		
	*		@param		string		$date 		a string representation of the event's date ex:  Dec. 25, 2025 or 12-25-2025
	*/	
	public function set_reg_end_date( $date ) {
		$this->_set_date_for( $date, 'DTT_REG_end' );
	}





	/**
	*		Set registration end time
	* 
	*		set the registration end time for an event 
	* 
	* 		@access		public		
	*		@param		string		$time 		a string representation of the event time ex:  9am  or  7:30 PM
	*/	
	public function set_reg_end_time( $time ) {
		$this->_set_time_for( $time, 'DTT_REG_end' );
	}





	/**
	*		Set as primary date
	* 
	*		set the datetime as the primary datetime - please verify that all other datetimes are now set to false
	* 
	* 		@access		public		
	*		@param		string		$primary 		True or False ?
	*/	
	public function set_primary( $primary ) {
		$this->_DTT_is_primary = (bool)absint( $primary );
	}





	/**
	*		Set registration limit
	* 
	*		set the maximum number of attendees that can be registered for this datetime slot
	* 
	* 		@access		public		
	*		@param		int		$reg_limit 	
	*/	
	public function set_reg_limit( $reg_limit ) {
		$this->_DTT_reg_limit = absint( $reg_limit );
	}





	/**
	*		Set availalbe spaces
	* 
	*		set remaining number of spaces left for this datetime slot
	* 
	* 		@access		public		
	*		@param		int		$tckts_left 
	*/	
	public function set_tckts_left( $tckts_left ) {
		$this->_DTT_tckts_left = absint( $tckts_left );
	}





	/**
	*		get Datetime ID
	* 
	* 		@access		public		
	*		@return 		mixed		int on success, FALSE on fail
	*/	
	public function ID() {
		if (isset($this->_DTT_ID)) {
			return $this->_DTT_ID;
		} else {
			return FALSE;
		}
	}





	/**
	*		get the $EVT_ID for the event that this datetime belongs to
	* 
	* 		@access		public		
	*		@return 		mixed		int on success, FALSE on fail
	*/	
	public function event_ID() {
		if (isset($this->_EVT_ID)) {
			return $this->_EVT_ID;
		} else {
			return FALSE;
		}
	}





	/**
	*		whether this is the primary datetime for the event or registration
	* 
	* 		@access		public		
	*		@return 		bool		bool on success, FALSE on fail
	*/	
	public function is_primary() {
	
		if ( is_bool( $this->_DTT_is_primary )) {
			return $this->_DTT_is_primary ? TRUE : FALSE;
		} else {
			return 'NOT SET';
		}
	}





	/**
	*		show date and/or time
	* 
	* 		@access		private	
	* 		@param		string		$date_or_time - whether to display a date or time or both
	* 		@param		string		$EVT_or_REG - whether to display event or registration datetimes
	* 		@param		string		$start_or_end - whether to display start or end datetimes
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	private function _show_datetime( $date_or_time = NULL, $EVT_or_REG = 'EVT', $start_or_end = 'start', $dt_frmt = FALSE, $tm_format = FALSE ) {
		
		if ( ! $dt_frmt ){
			$dt_frmt = $this->_dt_frmt;
		}
		
		if ( ! $tm_format ){
			$tm_format = $this->_tm_frmt;
		}

		$var_name = "_DTT_{$EVT_or_REG}_{$start_or_end}";
		$field = $this->_get_model()->field_settings_for(ltrim($var_name, '_'));
		
		switch ( $date_or_time ) {
			
			case 'D' :
				return $field->prepare_for_get( $this->{$var_name}, $dt_frmt, $this->_timezone );
				break;
			
			case 'T' :
				return $field->prepare_for_get( $this->{$var_name}, $tm_format, $this->_timezone );
				break;
			
			default :
				return $field->prepare_for_get( $this->{$var_name}, $dt_frmt . ' ' . $tm_format, $this->_timezone );
		}

	}




	/**
	*		get event start date
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function start_date( $dt_frmt = FALSE ) {		
		return $this->_show_datetime( 'D', 'EVT', 'start', $dt_frmt );
	}




	/**
	*		get end date
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function end_date( $dt_frmt = FALSE ) {		
		return $this->_show_datetime( 'D', 'EVT', 'end', $dt_frmt );
	}





	/**
	*		get start time
	* 
	* 		@access		public	
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function start_time( $tm_format = FALSE ) {
		return $this->_show_datetime( 'T', 'EVT', 'start', FALSE, $tm_format );
	}





	/**
	*		get end time
	* 
	* 		@access		public	
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function end_time( $tm_format = FALSE ) {
		return $this->_show_datetime( 'T', 'EVT', 'end', FALSE, $tm_format );
	}





	/**
	*		get start date and start time
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function start_date_and_time( $dt_frmt = FALSE, $tm_format = FALSE ) {
		return $this->_show_datetime( '', 'EVT', 'start', $dt_frmt, $tm_format );
	}





	/**
	*		get end date and time
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function end_date_and_time( $dt_frmt = FALSE, $tm_format = FALSE ) {
		return $this->_show_datetime( '', 'EVT', 'end', $dt_frmt, $tm_format );
	}




	/**
	*		get registration start date
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_start_date( $dt_frmt = FALSE ) {		
		return $this->_show_datetime( 'D', 'REG', 'start', $dt_frmt );
	}




	/**
	*		get registration end date
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_end_date( $dt_frmt = FALSE ) {		
		return $this->_show_datetime( 'D', 'REG', 'end', $dt_frmt );
	}





	/**
	*		get registration start time
	* 
	* 		@access		public	
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_start_time( $tm_format = FALSE ) {
		return $this->_show_datetime( 'T', 'REG', 'start', FALSE, $tm_format );
	}





	/**
	*		get registration end time
	* 
	* 		@access		public	
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_end_time( $tm_format = FALSE ) {
		return $this->_show_datetime( 'T', 'REG', 'end', FALSE, $tm_format );
	}





	/**
	*		get registrationstart date and start time
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_start_date_and_time( $dt_frmt = FALSE, $tm_format = FALSE ) {
		return $this->_show_datetime( '', 'REG', 'start', $dt_frmt, $tm_format );
	}





	/**
	*		get registration end date and time
	* 
	* 		@access		public	
	* 		@param		string		$dt_format - string representation of date format defaults to 'F j, Y'
	* 		@param		string		$tm_format - string representation of time format defaults to 'g:i a'
	*		@return 		mixed		string on success, FALSE on fail
	*/	
	public function reg_end_date_and_time( $dt_frmt = FALSE, $tm_format = FALSE ) {
		return $this->_show_datetime( '', 'REG', 'end', $dt_frmt, $tm_format );
	}




	/**
	*		get start timestamp
	* 
	* 		@access		public	
	*		@return 		int
	*/	
	public function start() {
		return $this->_DTT_EVT_start;
	}




	/**
	*		get end timestamp
	* 
	* 		@access		public	
	*		@return 		int
	*/	
	public function end() {
		return $this->_DTT_EVT_end;
	}




	/**
	*		get registration start timestamp
	* 
	* 		@access		public	
	*		@return 		int
	*/	
	public function reg_start() {
		return $this->_DTT_REG_start;
	}




	/**
	*		get registration end timestamp
	* 
	* 		@access		public	
	*		@return 		int
	*/	
	public function reg_end() {
		return $this->_DTT_REG_end;
	}





	/**
	*		get the registration limit for this datetime slot
	* 
	* 		@access		public		
	*		@return 		mixed		int on success, FALSE on fail
	*/	
	public function reg_limit() {
		return $this->_DTT_reg_limit;
	}





	/**
	*		get the available spaces left for this datetime slot
	* 
	* 		@access		public		
	*		@return 		mixed		int on success, FALSE on fail
	*/	
	public function tckts_left() {
		return $this->_DTT_tckts_left;
	}		
}

/* End of file EE_Datetime.class.php */
/* Location: includes/classes/EE_Datetime.class.php */	
	