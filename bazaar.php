<?php
/**
 * * BazaarVoice Feed: 
 * * Usage: Application will query PRODUCT_MV and ITM_IMAGES and will generate a CSV file to be placed on the out folder and will generate  
 * * and XML object (see exmaple)  to feed to bazaar voice.
 * * XML Example: 
 * *  <?xml version="1.0" encoding="utf-8" standalone="yes"?>
 * *     <Feed name="Mor Furniture For Less" extractDate="2022-01-16T19:14:13-08:00" incremental="false"
 * *          xmlns="http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.6">
 * *           <!-- Brands go here -->
 * *           <Brands>
 * *               <Brand>
 * *                   <ExternalId>ITM_STYLE_.STYLE_CD</ExternalId>
 * *                   <Name>ITM_STYLE.ECOMM_DES</Name>
 * *               </Brand>
 * *           </Brands>
 * *           <!-- Categories go here -->
 * *           <Categories>
 * *               <Category>
 * *                   <ExternalId>COLLECTION_CD</ExternalId>
 * *                   <Name>PRODUCT_MV.CATEGORIES</Name>
 * *                   <ParentExternalId></ParentExternalId>
 * *                   <CategoryPageUrl>http://example.com/18f9a</CategoryPageUrl>
 * *                   <ImageUrl></ImageUrl>
 * *               </Category>
 * *           </Categories>
 * *           <!-- Products go here -->
 * *           <Products>
 * *               <Product>
 * *                   <ExternalId>PRODUCT_MV.ITM_CD</ExternalId>
 * *                   <Name>PRODUCT_MV.MERC_NAME</Name>
 * *                   <Description>PRODUCT_MV.WEB_PRODUCT_GROUP</Description>
 * *                   <CategoryExternalId>PRODUCT_MV.CATEGORIES</CategoryExternalId>
 * *                   <ProductPageUrl>PRODUCT_MV.PRODUCT_PAGE_URL</ProductPageUrl>
 * *                   <ImageUrl>PRODUCT_MV.URL</ImageUrl>
 * *                   <UPCs>
 * *                       <UPC>PRODUCT_MV.ITM_CD</UPC>
 * *                   </UPCs>
 * *                   <ModelNumbers>
 * *                       <ModelNumber></ModelNumber>
 * *                   </ModelNumbers>
 * *                   <BrandExternalId></BrandExternalId>
 * *               </Product>
 * *           </Products>
 * *      </Feed>
 * *
 * * Arguments: 
 * *
 * * Out: CSV file 
 * *-------------------------------------------------------------------------------------------------------------------------------------
 * * 01/16/22   JL  Created Script
 * * 01/17/22   JL  Fixed header for CSV file and build XML correctly
 * * 02/02/22   JL  Moving brand data inside product 
 * *
 * *
***/

    date_default_timezone_set('America/Los_Angeles');
    set_include_path('./libs/phpseclib');

    include_once('../config.php');
    include_once('autoload.php');
    include_once('Net/SFTP.php');

    global $appconfig, $logger;

    $logger = new ILog($appconfig['bazaar']['logger']['username'], date('ymdhms') . ".log", $appconfig['bazaar']['logger']['log_folder'], $appconfig['bazaar']['logger']['priority']);

    $exception = fopen( './out/exception.csv', 'w+' );
    $timestamp = time();
    $dt = new DateTime("now", new DateTimeZone("America/Los_Angeles")); 
    $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
    $header = explode(",", "Product ID, Product Name, Brand, Brand ID, Image Url, Product Page Url, Product Families, UPC/EAN");
    
    //Check if any parmaeters are passed to the script and if its in upload mode
    $logger->debug( "Starting process: bazaar voice feed" );

    //Check if running in upload mode 
    $isUploadMode = isRunningInUploadMode( $argv, $argc );
    if( $isUploadMode ){
        $logger->debug( "Bazaar voice feed upload mode" );
        $error = onlyUpload();
        if( $error ){
            $logger->error( "Could not upload filename" );
        }
        $logger->debug( "Finished bazaar voice feed upload mode" );
        exit(0);

    }

    $mor = new Morcommon();
    $db = $mor->standAloneAppConnect();
     
    if ( !$db ){
        $logger->error( "Could not connect to database" );
        exit(1);
    }

    $logger->debug( "Querying items from table PRODUCT_MV and ITM_IMAGES" );
    $products = getBazaarVoiceProducts( $db, $exception );
    $logger->debug( "Finished getting bazaar product voice products" );

    //Generate XML and upload
    $xml = createXML( $products, $dt );
    $logger->debug( "Generating XML file" );
    $xml_filename = sprintf( $appconfig['bazaar']['xml_filename'], date('YmdHis') );
    $error = generateXMLFile(  $xml_filename, $xml ); 
    if( !$error ){ $logger->error( "Could not generate XML file" ); exit(1); }
    $error = upload( $xml_filename ); 
    if( $error ){ $logger->error( "Could not upload XML file" ); exit(1); }
    $logger->debug( "Uploading to SFTP of bazaar voice" );
    $error = archive( $xml_filename );
    if( $error ){ $logger->error( "Could not archive XML file" ); exit(1); }
    $logger->debug( "Uploading to SFTP of bazaar voice succesful" );


    //Generate CSV
    $logger->debug( "Generating CSV for bazaar products" );
    $filename = sprintf( $appconfig['bazaar']['filename'], date('YmdHis'));
    $error = generateCSV( $products['PRODUCTS'], $appconfig['bazaar']['out'], $filename, $header );
    if( $error ){ $logger->error( "Could not generate CSV file" ); exit(1); }
    //Need an archiving function 
    $error = archive( $filename );
    if( $error ){ $logger->error( "Could not archive CSV file" ); exit(1); }
    
    $logger->debug( "Finished Execution bazaar products" );


    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * generateXMLFile: 
     * *   Generates XML file on the out folder 
     * * Arguments: 
     * *    filename: File name 
     * *    xml: XML string 
     * *
     * * Return: TRUE for upload mode false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/

    function generateXMLFile( $filename, $xml ){
        global $appconfig, $logger;

        if( !$file = fopen( $appconfig['bazaar']['out'] . $filename, 'w+' )){
            $logger->error( "Could not opent file for xml writing" );
            exit(1);
        }

        return fwrite( $file, $xml );

    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * isRunningInUploadMode: 
     * *   Checks if script is running in upload mode 
     * * Arguments: 
     * *    argc: Argument count 
     * *    argv: Command line argments 
     * *
     * * Return: TRUE for upload mode false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function isRunningInUploadMode( $argc, $argv ){
        global $appconfig, $logger;

        if(array_count_values($argc) > 1){
            if( isset($argc[1]) ){
                return $argc[1] === 'upload_only';
            }
            else{
                return false;
            }
        }
        else{
            return false;
        }
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * onlyUpload: 
     * *   Function will upload only one file that is in the out folder 
     * * Arguments: 
     * *
     * *
     * * Return: TRUE for success false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function onlyUpload() {
        global $appconfig, $logger;

        $scanned_directory = array_diff( scandir($appconfig['bazaar']['out']), array('..', '.') );
        foreach( $scanned_directory as $file ){
            $logger->debug( "File found: " . $file );
            if ( strpos($file, "zaar") > 0 ){
                $error = upload( $file );
                if( $error ){
                    $logger->error( "Could not upload filename: " . $file );
                    exit(1);
                }
                $error = archive( $file );
                if( $error ){
                    $logger->error( "Could not archive filename: " . $file );
                }
                return false;
            }
        }
        return true;
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * archive: 
     * *   Moves filename to archive folder 
     * * Arguments: 
     * *    filename: File to archive
     * *
     * * Return: TRUE for success false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function archive( $filename ){
        global $appconfig, $logger;
        
        return !rename( $appconfig['bazaar']['out'] . $filename, $appconfig['bazaar']['archive'] .$filename );

    }
    
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * upload: 
     * *   Upload CSV file on $path directoy with filename and heeader 
     * * Arguments: 
     * *
     * * Return: TRUE for success false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function upload( $filename ){
        global $appconfig, $logger;

        try{ 
            $sftp = new Net_SFTP( $appconfig['bazaar']['sftp']['host'], $port=$appconfig['bazaar']['sftp']['port'] );
            if ( !$sftp->login( $appconfig['bazaar']['sftp']['username'], $appconfig['bazaar']['sftp']['pw']) ) {
                $logger->debug('SFTP connection failed');
                return false;
            }
            $logger->debug("SFTP connection succesful");
            $sftp->chdir( $appconfig['bazaar']['sftp']['remote_out'] ); 
            $logger->debug("SFTP changed directory");
            $sftp->put( $filename, $appconfig['bazaar']['out'] . $filename, NET_SFTP_LOCAL_FILE );

            return false;

        }
        catch( Exception $e ){
            return true;
        }

    }


    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * generateCSV: 
     * *   Generate CSV file on $path directoy with filename and heeader 
     * * Arguments: 
     * *    data: CSV data 
     * *    path: path to where it should be saves 
     * *    filename: file name 
     * *    header: header row
     * *
     * * Return: TRUE for failure false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function generateCSV( $data, $path, $filename, $header='' ) {
        global $appconfig, $logger;
        //Generating timestamp CSV file name
        try {
            $file = fopen( $path . $filename, "w" );
            if ( $header !== '' ){
                fputcsv( $file, $header );
            }
            foreach( $data as $line ) {
                fwrite( $file, '"' . $line['ITM_CD'] . '"' . "," );
                fwrite( $file, '"' . $line['MERCH_NAME'] . '"' . "," );
                fwrite( $file, '"' . $line['ECOMM_DES'] . '"' . "," );
                fwrite( $file, '"' . $line['COLLECTION_CD'] . '"' . "," );
                fwrite( $file, '"' . $line['PRODUCT_IMAGE_URL'] . '"' . "," );
                fwrite( $file, '"' . $line['PRODUCT_PAGE_URL'] . '"' . "," );
                fwrite( $file, '"' . $line['STYLE_CD'] . '"' . "," );
                fwrite( $file, '"' . $line['ITM_CD'] . '"' . "," );
                fwrite( $file,  "\n" );
            }
            fclose($file);
            return false;
        }
        catch( Exception $e ){
            $logger->debug( "Generating CSV Exception caused: " . $e->getMessage() );
            return true;
        }
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * createXML: 
     * *   Function will generate XML for bazaarvoice  
     * * Arguments: 
     * *       - product: Array with product information
     * *       - dt :  Datetime object
     * *
     * * Return: Array with XML information  
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function createXML( $products, $dt ){
        global $appconfig, $logger;
        $xml= simplexml_load_string( "<?xml version=\"1.0\" encoding=\"utf-8\" ?> <Feed name=\"Mor Furniture For Less\" extractDate=\"" . $dt->format('Y-m-d\TH:i:sP') . "\" incremental=\"false\" xmlns=\"http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.6\"></Feed> ");
        $childProducts = $xml->addChild('Products'); 

        //Iterate through the products array 
        foreach( $products['PRODUCTS'] as $product ){
            $childProduct = $childProducts->addChild('Product'); 
            $childProduct->addChild( "ExternalId", $product['ITM_CD'] );
            $childProduct->addChild( "Name", htmlspecialchars($product['MERCH_NAME']) );
            $childProduct->addChild( "Description", htmlspecialchars($product['WEB_PRODUCT_GROUP']) );
            $childProduct->addChild( "ProductPageUrl", $product['PRODUCT_PAGE_URL'] );
            $childProduct->addChild( "ImageUrl", htmlspecialchars($product['PRODUCT_IMAGE_URL']) );

            $brand = $childProduct->addChild('Brand'); 
            $brand->addChild( 'ExternalId', htmlspecialchars($product['BRAND']['EXTERNAL_ID']) );
            $brand->addChild( 'Name', htmlspecialchars($product['BRAND']['NAME']) );
        }
        $logger->debug( "Product XML: \n" . tidy_repair_string( $xml->asXML(), ['input-xml'=> 1, 'indent' => 1, 'wrap' => 0] ) . "\n" );

        return $xml->asXML();
    }
    
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * formatURL: 
     * *   Function will verify that URL have the domain    
     * * Arguments: 
     * *    string: Image URL 
     * *
     * * Return: String URL website 
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function formatURL( $url ){
        global $appconfig, $logger;

        return strpos($url, 'https') !== FALSE ? $url : $appconfig['bazaar']['bazaar_mor_image_url'] . $url; 
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * getBazaarVoiceProducts: 
     * *   Function will query for products and build a xml feed for bazaarvoice   
     * * Arguments: 
     * *       - db: IDBT database connectoin 
     * *
     * * Return: Array with product information 
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function getBazaarVoiceProducts( $db, $exception ){
        global $appconfig, $logger;
        
        $bazaar = new Bazaar($db);
        $products = [];

        $where = "WHERE MERCH_VISIBILITY = 'CATALOG, SEARCH'";
        $result = $bazaar->query($where);
        if( $result < 0 ){
            $logger->error( "Could not query tables PRODUCT_MV, and ITM_IMAGES" );
            exit(1);
        }

        $brands = [];
        $categories = [];

        while( $bazaar->next() ){
            $tmp = [];

            array_push( $brands , [ 'EXTERNAL_ID' => str_replace( ' ', '', $bazaar->get_ECOMM_DES()), 'NAME' => $bazaar->get_ECOMM_DES() ]); 
            $url = formatURL( $bazaar->get_URL() );
            $httpCode = validURL($url);

            $tmp['ITM_CD'] = $bazaar->get_ITM_CD();
            $tmp['MERCH_NAME'] = preg_replace('/[^A-Za-z0-9. -]/', '', $bazaar->get_MERCH_NAME());
            $tmp['ECOMM_DES'] = $bazaar->get_ECOMM_DES();
            $tmp['COLLECTION_CD'] = $bazaar->get_COLLECTION_CD();
            $tmp['PRODUCT_PAGE_URL'] = $appconfig['bazaar']['bazaar_mor_product_url'] . $bazaar->get_ITM_CD();
            $tmp['PRODUCT_IMAGE_URL'] = $url;
            $tmp['STYLE_CD'] = $bazaar->get_STYLE_CD();
            $tmp['CATEGORIES'] = str_replace(" ", "-", $bazaar->get_CATEGORIES());
            $tmp['WEB_PRODUCT_GROUP'] = str_replace(" ", "-", $bazaar->get_WEB_PRODUCT_GROUP());
            $tmp['BRAND'] = [ 'EXTERNAL_ID' => str_replace( ' ', '', $bazaar->get_ECOMM_DES()), 'NAME' => $bazaar->get_ECOMM_DES() ];

            if( $httpCode !== 200 ){
              unset($tmp['BRAND']);
              fputcsv( $exception, $tmp );
              continue;
            }

            array_push( $products, $tmp );
        }
        $brands = getUniqueBrands( $brands );

        $logger->debug( "Dumping all products for bazaar voice: " . print_r($products, 1) );
        return array( 'PRODUCTS' => $products, 'BRANDS' => $brands );

    }

    function validURL( $url ){
        global $appconfig, $logger;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,10);

        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  
        return $httpcode;


    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * getUniqueBrands: 
     * *   Dedup brands array 
     * * Arguments: 
     * *    brands: Array of brands 
     * *
     * * Return: Array of dedup brands 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function getUniqueBrands( $brands ){
        global $appconfig, $logger;

        $tmp = [];
        foreach( $brands as $brand ){
            if( !in_array( $brand, $tmp )){
                array_push( $tmp, $brand );
            }
        }
        return $tmp;


    }

?>
