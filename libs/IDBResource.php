<?php

/**
 * Class to manage a database resource.
 * 
 *  Actions
 * ================================
 * open()                           Establishes a connection to a database.
 * close()                          Terminates a connection to a database
 * save()                           Save any pending data to the database.
 * rollback()                       Ignore any pending changes for the database.
 * getError()                      Retrieve the error number and error message from the latest database operation
 * beginTransaction()      Start a database tansaction.
 * endTransaction()         Terminate a database tansaction.
 *
 * @package iware\database  
 * @author Frank Ilagan <filagan@gmail.com
 * @version 1.0_0
 * 
 */
class IDBResource {
    protected $attributes;

	/**
	 * Constructor
	 */
    public function __construct($hostname, $username, $password, $database) {       
        $this->attributes = array();
        $this->attributes['host']      = $hostname;
        $this->attributes['user']      = $username;
        $this->attributes['pwd']       = $password;
        $this->attributes['dbname']    = $database;
        
        $this->attributes['cnx']       = false;
    }    
    
	/**
     * Default function called for getter and setter methods on db columns.
     *
     */
	function __call( $method, $args )
	{
        if ( preg_match( "/set_(.*)/", $method, $found ) )  {
            if ( array_key_exists( $found[1], $this->attributes ) )  {
                $this->attributes[ $found[1] ] = $args[0];
                return true;
            }
        }
        else if ( preg_match( "/get_(.*)/", $method, $found ) )  {
            if ( array_key_exists( $found[1], $this->attributes ) ) {
                return $this->attributes[ $found[1] ];
            }
        }
        return false;
	}	
    
    /**
     * Open a connection to a database.
     *
     * returns true on Success, false otherwise.
     */
    function open() {

        // Connect to Oracle
        $con = oci_connect($this->attributes['user'], $this->attributes['pwd'], $this->attributes['host']."/".$this->attributes['dbname']);
        
        if (!$con) {
            return false;
        }
        $this->attributes['cnx'] = $con;
        
        return true;
    }
    
    /**
     * Open a connection to a database using apersistent connection.
     *
     * returns true on Success, false otherwise.
     */
    function popen() {

        // Connect to Oracle
        $con = oci_pconnect($this->attributes['user'], $this->attributes['pwd'], $this->attributes['host']."/".$this->attributes['dbname']);
        
        if (!$con) {
            return false;
        }
        $this->attributes['cnx'] = $con;
        
        return true;
    }

    /**
     * Close a connection to a database.
     *
     * returns true on Success, false otherwise.
     */    
     function close() {
        return oci_close($this->attributes['cnx'] );
    }
 
    /**
     * Save any pending data to the database.
     *
     * returns true on Success, false otherwise.
     */     
    function save() {
        return oci_commit($this->attributes['cnx']);        
    }
 
     /**
     * Ignore any pending changes for the database.
     *
     * returns true on Success, false otherwise.
     */    
     function rollback() {
        return oci_rollback($this->attributes['cnx']);        
    }
   
     /**
     * Start a database tansaction. Kept to have the same interface as the DBResource class for mysqli.s
     *
     * returns true on Success, false otherwise.
     */       
    function beginTransaction() {
        
        return true;
    }
    
     /**
     * Terminate a database tansaction. This call is deprecated. Use save() or rollback() instead.
     *
     * returns true on Success, false otherwise.
     */       
     function endTransaction() {
        
        return true;
    }
    
    /**
     * Get the oci connection instance
     */
    function getConnection() {
        return $this->attributes['cnx'];
    }

   /**
     * Retrieve the error number and error message from the latest database operation
     *
     * returns true on Success, false otherwise.
     */           
    function getError() {
        if ($this->attributes['cnx'] === false) {
            $e = oci_error(); 
        }
        else {
            $e = oci_error($this->attributes['cnx']); 
        }

        if ($e === false) {
            return "";
        }
        
        return $e['code'].
                    " : ".
                    $e['message'];        
    }
}

?>
