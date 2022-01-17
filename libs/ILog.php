<?php 
/****************************************************************
 * ILog PHP Logging Class log message to a file
 *
 * Use to log messages to a file from a PHP program. The message
 * log will be designated on of 5 classes. (See Class Constant 
 * definition below). Only the current class setting will be written 
 * to a file.
 * Example:
 *  $logger = new ILog('Frank', "application.log", "/tmp", ILog::ERROR);
 *
 *  $logger->error("Error Message");
 *  $logger->debug("Debug Message");
 *  $logger->fatal("Fatal Message");
 *  
 *  Of the 3 logged messages above only the error and fatal will be written
 *  to the /tmp/application.log file. The last parameter ILog::ERROR determines
 * which messages are written to the file. In this case any message equal to or
 * less than the logpriority will be written to the file. That being said, if 
 * if the log priority was set to ILog::INFO then all messages will be written
 * to a file.
 *
 * This class will be useful for adding messages to a file that you may want to
 * see at a later time. If a program is no longer working as expected you may 
 * want to display log messages.
 *
 *-----------------------------------------------------------------------------
 * Programmer		Date   		Notes
 * Frank Ilagan	    12/15/2010  Created
 * Frank Ilagan     07/15/2015  Added Class comments to describe usage.
 */

//Multiline error log class 
// ersin güvenç 2008 eguvenc@gmail.com 
//For break use "\n" instead '\n' 

class ILog { 
	// Priority Constants for different classes of messages
	const FATAL			= 0;
	const ERROR			= 1;
	const WARN			= 2;
	const DEBUG			= 3;
	const INFO			= 4;

	protected $log_folder;			// Folder where log file resides
	protected $log_file_name;		// File where messages are to be written.
	protected $priority;			// Current message level. Indicates what type of messages are to be written to the log file.
	protected $source;				// PHP source where Ilog was invoked.
	protected $user;				// Name of the entity writing to the log file.

	private   $log_destination; 	// Formated log_folder + log_file_name. For internal use only.

	/**
	 * Constructor
     *
	 */
    public function __construct($user, $logfile="AppLog.log", $logdir=".", $logpriority=self::ERROR) {
	
		$this->user				= $user;
        $this->priority 		= $logpriority;
		$this->log_folder		= $logdir;
		$this->log_file_name	= $logfile;

		$this->log_distination = $this->log_folder.DIRECTORY_SEPARATOR.$this->log_file_name;

    }

	/**
	 * Set the user writing to the log file.
	 *
	 * @param $user User writing to the file.
	 */
	public function setUser($user) {
		$this->user	= $user;
	}

	/**
	 * Set the user writing to the log file.
	 *
	 * @return String User writing to the file.
	 */
	public function getUser() {
		return $this->user;
	}
	/**
	 * Set the folder where the log file is to be stored. The $folder will be validated to ensure that it exists and is
	 * a folder. If it does not exist or is not a folder an exception will be thrown.
     *
	 * @param $folder Directory where log file is located.
	 * throws Exception is $folder does not exist or is not a folder.
	 */
	public function setLogFolder($folder) {
		if (is_dir($folder)) {
			$this->log_folder		= $folder;

			$this->log_distination = $this->log_folder.DIRECTORY_SEPARATOR.$this->log_file_name;
		}

		throw new Exception($folder." is not a valid folder.");
	}

	/**
	 * Set the name of the file where messages will be logged.
	 *
	 * @param $filename Name of the logging file.
	 */
	public function setLogFile($filename) {
		$this->log_file_name	= $filename;

		$this->log_distination = $this->log_folder.DIRECTORY_SEPARATOR.$this->log_file_name;
	}

	/**
	 * Return the name of the log file
	 *
	 * @return Stirng The name of the log file.
	 */
	public function getLogfile() {
		return $this->log_file_name;
	}

	/**
 	 * Return the directory name where the log file is located
	 *
	 * @return String the name of the folder where the log file is located.
	 */
	public function getLogFolder() {
		return $this->log_folder;
	}

	/**
	 * Return the full path to the log file.
	 *
	 * @return String the full path to the log file.
	 */
	public function getLogDistination() {
		return $this->log_distination;
	}

	/**
	 * Set the reporting priority. This determines which messages are written to the log.
	 *
	 * @param $level The level to be reported.
	 */
	public function setPriority($level) {

		if ($level == self::FATAL || $level == "FATAL") {
			$this->priority = self::FATAL;
		}
		else if ($level == self::DEBUG || $level == "DEBUG") {
			$this->priority = self::DEBUG;
		}
		else if ($level == self::WARN || $level == "WARN") {
			$this->priority = self::WARN;
		}
		else if ($level == self::INFO || $level == "INFO") {
			$this->priority = self::INFO;
		}
		else {
			// Default Error
			$this->priority = self::ERROR;
		}

	}

	/**
	 * Return the loggin priority
	 *
	 * @return int Log Priority
	 */
	public function getPriority() {
		return $this->priority;
	}
	
	/**
	 * Internal function used to format message written to log file.
	 *
	 * @param $priority The string literal for the priority writing the message.
	 * @param $msg Message to be written to the log file.
	 */
	protected function write_log($priority, $msg) {
		$date = date('m/d/Y H:i:s'); 
		$log = $date." ".$this->user." ".$priority." ".$msg."\n"; 

		return error_log($log, 3, $this->log_distination); 
	}

	/**
	 * Write fatal message to the log file. Fatal is the highest log priority and will always be written to
     * the log file.
	 *
	 * @param $msg Message to be written to the log file.
	 */
	public function fatal($msg) {
		return $this->write_log("FATAL", $msg); 
	}

	/**
	 * Write error message to the log file. Error is the second highest log priority and will always be written to
     * the log file except if the log priority is set to FATAL.
	 *
	 * @param $msg Message to be written to the log file.
	 */
	public function error($msg) {
		if (self::ERROR <= $this->priority) {
			return $this->write_log("ERROR", $msg); 
		}
	}

	/**
	 * Write warn message to the log file. Warn is the third highest log priority and will always be written to
     * the log file except if the log priority to a higher priority than WARN.
	 *
	 * @param $msg Message to be written to the log file.
	 */
	public function warn($msg) {
		if (self::WARN <= $this->priority) {
			return $this->write_log("WARN", $msg); 
		}
	}

	/**
	 * Write debug message to the log file. The message will always be written to
     * the log file except if the log priority to a higher priority that DEBUG.
	 *
	 * @param $msg Message to be written to the log file.
	 */
	public function debug($msg) {
		if (self::DEBUG <= $this->priority) {
			return $this->write_log("DEBUG", $msg); 
		}
	}

	/**
	 * Write info message to the log file. The message will always be written to
     * the log file except if the log priority to a higher priority that INFO.
	 *
	 * @param $msg Message to be written to the log file.
	 */
	public function info($msg) {
		if (self::INFO <= $this->priority) {
			return $this->write_log("INFO", $msg); 
		}
	}

} 

?>
