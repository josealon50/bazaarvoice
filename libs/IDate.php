<?php
/**
 * Facilitates the formating of dates
 *
 * On instansiation it will contain the current date and time. Use setDate() to use another date/time.
 *
 * @package iware\utils
 * @author Frank Ilagan <filagan@gmail.com
 * @version 1.0_0
 *
 */
class IDate {
	const DEFAULT_FORMAT = 'm/d/Y H:i:s';
    const MYSQL_FORMAT = 'Y-m-d H:i:s';
    const ORACLE_FORMAT = 'd-M-y';

	protected $sDate;   // Date Object

	/**
     * Constructor
     */
    public function __construct() {
        $this->sDate = new DateTime("now", new DateTimeZone("America/Los_Angeles"));
    }

	/**
	 * Set the date based on the string passed in. By default it uses DEFAULT_FORMAT to parse the stringDate parameter.
     * See PHP manual on date for valid formating options.
     * 
     * $stringDate Input of Date as a String.
     * $format     Format to process $stringDate parameter. Uses DEFAULT_FORMAT if this parameter is not specified.
 	 */
    public function setDate($stringDate, $format=self::DEFAULT_FORMAT) {
        $this->sDate =  new DateTime($stringDate, new DateTimeZone("America/Los_Angeles"));
        
		return $this->sDate->format($format);
    }

	/**
	 * Return the string version of the instance date.
     *
 	 * $format Specifies how the date should be formated as a string. See PHP manual on data for formating options.
     * returns String formated instance date.
	 */
	public function toString($format='m/d/Y') {
        $resv=$this->sDate;
		return $resv->format($format);
	}
    
    public function toStringMySQL() {
        return $this->sDate->format(self::MYSQL_FORMAT);
    }

    public function toStringOracle() {
    	return $this->sDate->format(self::ORACLE_FORMAT);
    }
    
	/**
	 * Get the date for the start of the week.
	 *
	 * @param $format Format for the date to be returned
	 * @return String The date for the start of the week as a string formatted as mm/dd/YYYY.
	 */
	public function getWeekStartDate($format='m/d/Y') {

		$retval = "";

		// Get the numeric day of the week
		$ndow = $this->toString('N'); // Numeric day of week. 1 - Monday thru 7 - Sunday
	
		if ($ndow == 1) {
			$retval=$this->toString($format);
		}
		else {
			// Format String for Search.
			$search_string = $this->toString('Y-m-d').' '.($ndow-1).' days ago';
			$retval=date($format, strtotime($search_string));
		}

		return $retval;
	}

	/**
	 * Get the date for the start of the week.
	 *
	 * @param $format Format for the date to be returned
	 * @return String The date for the end of the week as a string formatted as mm/dd/YYYY.
	 */
	public function getWeekEndDate($format='m/d/Y') {

		$retval = "";

		// Get the numeric day of the week
		$ndow = $this->toString('N'); // Numeric day of week. 1 - Monday thru 7 - Sunday
	
		if ($ndow == 7) {
			$retval=$this->toString($format);
		}
		else {
			// Format String for Search.
			$search_string = $this->toString('Y-m-d').' +'.(7-$ndow).' day';
			$retval=date($format, strtotime($search_string));
		}

		return $retval;
	}

	/**
	 * Get the date for the start of the week.
	 *
	 * @param $cond Condition to be applied to a date. See php strtotime for examples
	 * @return String The date for the end of the week as a string formatted as mm/dd/YYYY.
	 */
	public function computeDate($cond, $format='m/d/Y') {

		$retval = "";

		$search_string = $this->toString('Y-m-d').' '.$cond;
		$retval=date($format, strtotime($search_string));

		return $retval;
	}
}

?>
