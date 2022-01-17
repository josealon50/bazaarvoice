<?php
    class Morcommon{
        /*-----------------------------------------------------------------------------------
         *------------------------------ standAloneAppConnect -------------------------------
         *-----------------------------------------------------------------------------------
         *
         * Global routine that facilitates the connection to Mor's GERS database
         *
         * @return mixed $db connection object from IDBResource, or false if there is an error
         * @see IDBResource
         */
        public function standAloneAppConnect() {
            global $appconfig;

            $db = new IDBResource($appconfig['dbhost'], $appconfig['dbuser'], $appconfig['dbpwd'],  $appconfig['dbname']);
            
            try {
                $db->open();
            }
            catch (Exception $e) {
                    $errmsg   = 'Invalid Username/Password';
                        
                    return false; 
            }

            return $db;

        }
        /*-----------------------------------------------------------------------------------
         *------------------------------------- genDocNum -----------------------------------
         *-----------------------------------------------------------------------------------
         *
         * Gernerate a GERS Document number for store codes. Assumes the system date
         *
         * @param $db 	The database connection as an IDBResource instance
         * @param $storeCd The GERS Store Code
         * @return String GERS Document number, False on error
         * 
         */
        public function genDocNum($db, $storeCd) {
            global $logger;
            $stmt = oci_parse(
                $db->getConnection(),
                'CALL MOR_UTILS.genDocNum(:STORECD, sysdate, :DOCNUM, :DOCSEQ) into :TEMP'
            );

            if (! $stmt) {
                            $logger->error(oci_error());
                            return false;
            }

            oci_bind_by_name($stmt, ':STORECD', $storeCd);
            oci_bind_by_name($stmt, ':DOCNUM', $docNum, 11);
            oci_bind_by_name($stmt, ':DOCSEQ', $seqNum, 4);
            oci_bind_by_name($stmt, ':TEMP', $autoDoc, 11);

            if (!oci_execute($stmt)) {
                $logger->error(oci_error());
                return false;
            }
            return $seqNum;

            return $autoDoc;
        }
    }
?>
