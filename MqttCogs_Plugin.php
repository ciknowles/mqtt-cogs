<?php
require(__DIR__ . '/./sskaje/autoload.example.php');
require(__DIR__ . '/./flock/Lock.php');

//require_once __DIR__ . '/./AutoLoadByNamespace.php';

//spl_autoload_register("AutoloadByNamespace::autoload");
//AutoloadByNamespace::register("Google\Visualization\DataSource", __DIR__ . '/./google-visualization');

include_once('MqttCogs_LifeCycle.php');

use \sskaje\mqtt\MQTT;
use \sskaje\mqtt\Debug;
use \sskaje\mqtt\MessageHandler;
use \flock\Lock;

//See http://plugin.michael-simpson.com
class MqttCogs_Plugin extends MqttCogs_LifeCycle {

    public $mqtt;
		
    //called when the plugin is activated
    public function activate() {
        Debug::Log(DEBUG::INFO,"MqttCogs plugin activated");		
		
		if (! wp_next_scheduled ( 'mqtt_cogs_watchdog' )) {
			wp_schedule_event(time(), '1min', 'mqtt_cogs_watchdog');
	    }

	    if (! wp_next_scheduled ( 'mqtt_cogs_prune' )) {
			wp_schedule_event(time(), 'daily', 'mqtt_cogs_prune');
	    }
    }
    
    //called when the plugin is deactivated
    public function deactivate() {
		wp_clear_scheduled_hook('mqtt_cogs_watchdog');
		delete_transient( 'doing_mqtt' );
		wp_clear_scheduled_hook('mqtt_cogs_prune');
				
		try {
		    $file = './mqttcogs_lock.pid';    
		    unlink($file);
		}
		catch (Exception $e) {
		        Debug::Log(DEBUG::ERR, $e->getMessage());
		}
    }

    //returns a list of settings for plugin
    public function getOptionMetaData() {
		global $wp_roles;
    	
		$arr = array('Anyone');
		foreach ( $wp_roles->get_names() as $value) {
			$arr[]=  $value;
		}
			
    	$readroles = array_merge(array(__('MQTT Read Role', 'mqttcogs')),  $arr);

    	$writeroles = array('Contributor') +  $arr;    	
    	$writeroles = array_merge(array(__('MQTT Write Role', 'mqttcogs')), $arr);
    	
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            
        	'MQTT_Version' => array(__('MQTT Version', 'mqttcogs'), "3_1_1", "3_1"),
        	'MQTT_Server' => array(__('MQTT Server/Port', 'mqttcogs')),
			'MQTT_ClientID' => array(__('MQTT ClientID', 'mqttcogs')),
			'MQTT_Username' => array(__('MQTT User', 'mqttcogs')),
			'MQTT_Password' => array(__('MQTT Password', 'mqttcogs')),
			'MQTT_TopicFilter' => array(__('MQTT TopicFilter', 'mqttcogs')),
			
			'MQTT_MySensorsRxTopic' => array(__('MySensors Receive Topic (msgs from nodes)','mqttcogs')),
			'MQTT_MySensorsTxTopic' => array(__('MySensors Transmit Topic (msgs to nodes)', 'mqttcogs')),
									
			'MQTT_KeepArchive' => array(__('Save MQTT data for', 'mqttcogs'),
					'Forever', '365 Days', '165 Days', '30 Days', '7 Days', '1 Day'),
                                
            'MQTT_Recycle' => array(__('MQTT Connection Recycle (secs)', 'mqttcogs')),
            'MQTT_Debug' => array(__('MQTT Debug', 'mqttcogs'), 'All', 'Info', 'None'),
        		      	
        	'MQTT_ReadAccessRole' => $readroles,
        	'MQTT_WriteAccessRole' => $writeroles
        );
    }

    //initializes options on first run
    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }
    }
    
	public function setupLogging() {
		//error_log("setupLogging");	
		switch($this->getOption("MQTT_Debug", "Info")) {
        		    case "All":
        		      
        		        Debug::SetLogPriority(Debug::ALL);	
						break;
        		    case "Info":
        		      
        		        Debug::SetLogPriority(Debug::INFO);	
						break;
        		    case "None":
        		        Debug::Disable();
					
						break;
        }
	}
	
    public function getPluginDisplayName() {
        return 'Mqtt Cogs';
    }

    protected function getMainPluginFileName() {
        return 'mqtt-cogs.php';
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    public function installDatabaseTables() {
        Debug::Log(DEBUG::INFO, 'Installing MqttCogs database table');
		
		global $wpdb;
		$table_name = $this->prefixTableName('data');				
		$charset_collate = $wpdb->get_charset_collate();
					
		$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		utc  datetime NOT NULL,
		topic tinytext NOT NULL,
		payload text NOT NULL,
		qos tinyint NOT NULL,
		retain tinyint NOT NULL,
		PRIMARY KEY  (id)
		) $charset_collate;";
		
		$wpdb->query($sql);
	
		$sql = "ALTER TABLE $table_name ADD INDEX `idx_data_topic` (`topic`(50), `utc`);";
		$wpdb->query($sql);
		
		$table_name = $this->prefixTableName('buffer');				
		$charset_collate = $wpdb->get_charset_collate();
					
		$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		utc  datetime NOT NULL,
		topic tinytext NOT NULL,
		payload text NOT NULL,
		qos tinyint NOT NULL,
		retain tinyint NOT NULL,
		PRIMARY KEY  (id)
		) $charset_collate;";
		
		$wpdb->query($sql);
	
		$sql = "ALTER TABLE $table_name ADD INDEX `idx_buffer_topic` (`topic`(50), `utc`);";
		$wpdb->query($sql);
		
		$table_name = $this->prefixTableName('lastdata');				
		$charset_collate = $wpdb->get_charset_collate();
					
		$sql = "CREATE TABLE $table_name (
		topic tinytext NOT NULL,
		utc  datetime NOT NULL,
		payload text NOT NULL,
		qos tinyint NOT NULL,
		retain tinyint NOT NULL,
		PRIMARY KEY  (topic(50))
		) $charset_collate;";
		
		$wpdb->query($sql);
	
		$sql = "ALTER TABLE $table_name ADD INDEX `idx_lastdata_topic` (`topic`(50), utc);";
		$wpdb->query($sql);
		
		$table_name = $this->prefixTableName('data');
		$lastdatatable_name = $this->prefixTableName('lastdata');
		
		$sql = "DELIMITER // CREATE TRIGGER trg_data_ins AFTER INSERT ON $table_name FOR EACH ROW BEGIN INSERT INTO $lastdatatable_name (topic, utc, payload, qos,retain) VALUES (NEW.topic,NEW.utc,NEW.payload,NEW.qos,NEW.retain) ON DUPLICATE KEY UPDATE utc = VALUES(utc), payload = VALUES(payload), qos = VALUES(qos), retain = VALUES(retain); END; // DELIMITER ;";

		$wpdb->query($sql);
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    public function unInstallDatabaseTables() {
	    Debug::Log(DEBUG::INFO, 'Removing MqttCogs database table');
			
		global $wpdb;
		$tableName = $this->prefixTableName('data');
		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
		
		
		$tableName = $this->prefixTableName('buffer');
		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
		
		$tableName = $this->prefixTableName('lastdata');
		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
    }

    public function addActionsAndFilters() {

        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));

        // Example adding a script & style just for the options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        //        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
        //            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
        //            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        }


        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37
    	add_action('wp_enqueue_scripts', array(&$this, 'enqueueStylesAndScripts'));
	
	
	    //makes sure mqtt reader is active
	    add_action('mqtt_cogs_watchdog', array(&$this, 'do_mqtt_watchdog'));
	    
	    //prunes the database
	    add_action('mqtt_cogs_prune', array(&$this, 'do_mqtt_prune'));


        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        fwp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));

	    //  wp_enqueue_script('googlecharts','https://www.gstatic.com/charts/loader.js', __FILE__ );

        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39
	 
    	 add_shortcode('mqttcogs_drawgoogle', array($this, 'shortcodeDrawGoogle'));
    	 
		 //this is not completed yet
		 add_shortcode('mqttcogs_drawleaflet', array($this, 'shortcodeDrawLeaflet'));
		 
		  //this is not completed yet
		 add_shortcode('mqttcogs_drawdatatable', array($this, 'shortcodeDrawDataTable'));
			 
		 //this is experimental
		 add_shortcode('mqttcogs_graph', array($this, 'shortcodeDrawGoogle2'));
	
	 
    	 add_shortcode('mqttcogs_data', array($this, 'shortcodeData'));	
    	 add_shortcode('mqttcogs_ajax_data', array($this, 'ajax_data'));

    	 add_shortcode('mqttcogs_get', array($this, 'shortcodeGet'));
    	 add_shortcode('mqttcogs_set', array($this, 'shortcodeSet'));
	 
				 
        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41
    	add_action('wp_ajax_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN'));
    	
    	add_action('wp_ajax_nopriv_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN')); 
    	
    	add_action('wp_ajax_doSet', array(&$this, 'ajaxACTION_doSet'));
    	add_action('wp_ajax_nopriv_doSet', array(&$this, 'ajaxACTION_doSet'));
   	}
   	   	 
	public function enqueueStylesAndScripts() {
		
		wp_register_script('google_loadecharts','https://www.gstatic.com/charts/loader.js' );
		wp_register_script('loadgoogle', plugins_url('/js/loadgoogle.js', __FILE__));
		wp_register_script('chartdrawer', plugins_url('/js/googlechartdrawer.js', __FILE__), array(), '2.2');
		
		wp_register_style('leafletcss', 'https://unpkg.com/leaflet@1.5.1/dist/leaflet.css');
		wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.5.1/dist/leaflet.js');
		wp_register_script('leafletdrawer', plugins_url('/js/leafletdrawer.js', __FILE__), array(), '2.2');
		
		wp_register_style('datatablescss', 'https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css');
		wp_register_script('datatables', 'https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js');
		wp_register_script('datatablesdrawer', plugins_url('/js/datatablesdrawer.js', __FILE__), array(), '2.43');
	
	
	}

    //prunes the mqttcogs database table. Run daily
	function do_mqtt_prune() {
		$this->setupLogging();
		global $wpdb;
		
		$dur = explode(' ', $this->getOption("MQTT_KeepArchive", "Forever"));
		
		if (count($dur)<2) {
			$dur = 99999;
		}
		else {
			$dur = intval($dur[0]);
		}
		
		//DATEDIFF(d1,d2) -- value in days				
	    $table_name = $this->prefixTableName('data');
		$sql = "DELETE from $table_name WHERE DATEDIFF(NOW(),utc) > $dur;";	
	    Debug::Log(DEBUG::DEBUG, 'Pruning MqttCogs database table');
		$wpdb->query($sql);										
		
		$table_name = $this->prefixTableName('buffer');
		$sql = "DELETE from $table_name WHERE DATEDIFF(NOW(),utc) > $dur;";
	    Debug::Log(DEBUG::DEBUG, 'Pruning MqttCogs buffer table');
		$wpdb->query($sql);										
		
		$table_name = $this->prefixTableName('lastdata');
		$sql = "DELETE from $table_name WHERE DATEDIFF(NOW(),utc) > $dur;";
	    Debug::Log(DEBUG::DEBUG, 'Pruning MqttCogs lastdata table');
		$wpdb->query($sql);			
	}    

    //
	function do_mqtt_watchdog() {
		
	try {
		//if no clientid just return
		if (empty($this->getOption("MQTT_ClientID"))) {
			return;
		}
		
		$this->setupLogging();
		$file = './mqttcogs_lock.pid';
		$lock = new flock\Lock($file);
		
		// Non-blocking case. Acquire lock if it's free, otherwse exit immediately
		$gmt_time = microtime( true );
	
		if ($lock->acquire()) { 
		        register_shutdown_function(array($this, 'shutdownHandler'));
        		
        		if ("false" == $this->getOption("MQTT_Recycle", "false")) {
        			$this->addOption("MQTT_Recycle", "295");
        		}
        		
        		$recycle_secs = intval($this->getOption("MQTT_Recycle"));
        		
				$mqtt = $this->buildMQTTClient($this->getOption("MQTT_ClientID"));
				
				$result = $mqtt->connect();
							
				if (!($result)) {
				    Debug::Log(DEBUG::ERR, "MQTT can't connect");
					return;
				}
	
				$this->mqtt = $mqtt;
				
				$topics[$this->getOption("MQTT_TopicFilter")] = 1;
				$callback = new MySubscribeCallback($this);
				$mqtt->setHandler($callback);
				$mqtt->subscribe($topics);
						
				while(($this->mqtt) && (microtime(true)-$gmt_time<$recycle_secs) && $mqtt->loop()) {
					set_time_limit(0);
				}
				
				$mqtt->disconnect();
			}
		}
		
		catch (Exception $e) {
		      Debug::Log(DEBUG::ERR, $e->getMessage());
				if (!empty($mqtt)) {
					$mqtt->disconnect();
				}
		}
		finally {
				$this->mqtt = NULL;
				if (!empty($lock)) {
					try {
						$lock->release();	
					}	
					catch (Exception $ee) {
						   Debug::Log(DEBUG::DEBUG, $ee->getMessage());
					}
				}
		}
	}	
	
    public function errorHandler($error_level, $error_message, $error_file, $error_line, $error_context)
    {
        $error = "lvl: " . $error_level . " | msg:" . $error_message . " | file:" . $error_file . " | ln:" . $error_line;
	      Debug::Log(DEBUG::ERR, $error);
    }

    public function shutdownHandler() //will be called when php script ends.
    {
        $lasterror = error_get_last();
	    $error = "[SHUTDOWN] lvl:" . $lasterror['type'] . " | msg:" . $lasterror['message'] . " | file:" . $lasterror['file'] . " | ln:" . $lasterror['line'];            
        
           Debug::Log(DEBUG::ERR, $error);  
    }
	
	public function buildMQTTClient($cid) {
		$mqtt = new MQTT($this->getOption("MQTT_Server"), $cid);
    		
		switch ($this->getOption("MQTT_Version")) {
			case "3_1_1":
				$mqtt->setVersion(MQTT::VERSION_3_1_1);
				break;
			default: 
				$mqtt->setVersion(MQTT::VERSION_3_1 );
				break;
		}
	
		if (strpos($this->getOption("MQTT_Server"), 'ssl://') === 0) {
			 $mqtt->setSocketContext(stream_context_create([
				   'ssl' => [
					/*   'cafile'                => '/path/to/CACert-mqtt.crt',*/
					   'verify_peer'           => false,
					   'verify_peer_name'      => false,
					   'disable_compression'   => true,
					   'ciphers'               => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK',
					   'crypto_method'         => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
					   'SNI_enabled'           => true,
					   'allow_self_signed'     => true
				   ]
				]
			 ));
		}
		else {	
			$mqtt->setSocketContext(stream_context_create());
		}
						
		if ($this->getOption("MQTT_Username")) {
			$mqtt->setAuth($this->getOption("MQTT_Username"), $this->getOption("MQTT_Password"));
		}
		return $mqtt;
	}
	
	
	public function isNodeOnline($topic, $rootkey) {
		$pieces = explode("/", $topic);
		
		//mysensors_out/10/255/3/0/32
		if (count($pieces)<6)  {
			return true;				
		}
		
		$key = $pieces[0];
		$key = $rootkey;
		
		$nodeid = $pieces[1];
		$sensor = $pieces[2];
		$cmd = $pieces[3];
		$ack = $pieces[4];
		$type = $pieces[5];
		$time = time();
		//mysensors_out/10/255/3/0/32
		$rows = $this->getLastN('data', $key.'/'.$nodeid.'/255/3/0/32', 1, 'DESC');
		if (count($rows)==1) {
			$utcunixdate = strtotime($rows[0]['utc']);
			$stayalive = intval($rows[0]['payload']);
		
		    //remember it is in ms	
			return (($time>=$utcunixdate) && ($time*1000<$utcunixdate*1000 + $stayalive));
		}			
		Debug::Log(DEBUG::DEBUG,"isNodeOnline: no last value");
		return false;
	}
	
	public function bufferMessage($topic,$payload,$qos,$retained) {
		global $wpdb;
		$tableName = $this->prefixTableName('buffer');
		$utc = current_time( 'mysql', true );
		$wpdb->insert(
				$tableName,
				array(
						'utc' => $utc,
						'topic' => $topic,
						'payload' => $payload,
						'qos' =>$qos,
						'retain' => $retained
				),
				array(
						'%s',
						'%s',
						'%s',
						'%d',
						'%d'
				)
			);
	}
	
	public function sendMqtt($id, $topic, $payload, $qos, $retained, $result) {
	    $this->setupLogging();
		return $this->sendMqttInternal($id, $topic, $payload, $qos, $retained, true, $result);
	}
	
	public function sendMqttInternal($id, $topic, $payload, $qos, $retained, $trybuffer, $result) {
    	$json = new stdClass();	
    	
		if ($trybuffer) {
		    Debug::Log(DEBUG::DEBUG,"sendMqttInternal: Attempting to buffer {$topic}");
		
			if (!$this->isNodeOnline($topic, $this->getOption('MQTT_MySensorsRxTopic', 'mysensors_out'))) {
				$this->bufferMessage($topic,$payload,$qos,$retained);	
				$json->status = 'buffered';
				  Debug::Log(DEBUG::DEBUG,"sendMqttInternal: Buffered {$topic}");
				return true;
			}
		     
		}
		
	    //if online send to broker
    	$mqtt = $this->buildMQTTClient($id);
    	    		
    	$result = $mqtt->connect();
    	
    	if (!($result)) {
    	    Debug::Log(DEBUG::ERR,"doSet: MQTT can't connect");
        		$json->status = 'error';
    		$error_1 = new stdClass();
    		$error_1->reason='mqtt connection failure';
    		$error_1->message = '';
    		$json->errors = array();
    		$json->errors.push($error_1); 	
			return false;
    	}
    	else {   	
        	if (!$mqtt->publish_sync($topic, $payload, $qos, $retained)) {
        	    Debug::Log(DEBUG::ERR,"doSet: MQTT publish failure");
        		
        		$json->status = 'error';
        		$error_1 = new stdClass();
        		$error_1->reason='mqtt publish failure';
        		$error_1->message = '';
        		$json->errors = array();
        		$json->errors.push($error_1);
				return false;
        	}
    	}
    	return true;
	}
	
    /*
     * Called to set a mqtt value
     */
	 
	 // SELECT * FROM `wp_mqttcogs_plugin_data` where topic = 'mysensors_out/10/7/1/0/2' order by utc DESC LIMIT 1 
	 // mysensors_out/10/255/3/0/32
	 //
    public function ajaxACTION_doSet() {
		$this->setupLogging();
    	if (!$this->canUserDoRoleOption('MQTT_WriteAccessRole')) {
    		die();
    	}
    	
    	
    	$topic = $_GET['topic'];
    	$qos = (int) $_GET['qos'];
    	$payload = $_GET['payload'];
    	$retained = (int) $_GET['retained'];
    	$minrole = $_GET['minrole'];
    	$pattern = $_GET['pattern'];
    	$id =  $_GET['id'];
    
    	$action = "doSet&topic=$topic&qos=$qos&retained=$retained&minrole=$minrole&pattern=$pattern";

    	if (!check_ajax_referer($action, 'wpn')) {
    		die();
    	}
    	
    	//check we are able to do role according to value in shortcode
    	if (($minrole!='') && (!$this->isUserRoleEqualOrBetterThan($minrole))) {
    		die();
    	}
    	
		//if node not online then buffer message
		
		$json = new stdClass();	
		$json->status = 'ok';	
				
		//if here
		if ($this->sendMqttInternal($id, $topic, $payload, $qos, $retained, true, $json)) {
			Debug::Log(DEBUG::DEBUG,"doSet: MQTT publish success");			
		}
		
		wp_send_json($json);
    }
	
    	
	public function ajaxACTION_doGetTopN() {
		$this->setupLogging();
		
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
           
		global $wpdb;
	    $tqxparams = explode(';', $_GET['tqx']);
	    $tqx = array();
	    foreach($tqxparams as $tqxparam) {
		$item = explode(':',$tqxparam);
		$tqx[$item[0]]=$item[1];
	    }
	    
	    /*if (!wp_verify_nonce($_GET['wpn'], 'doGetTopN')) {
	    	die();
	    }*/
	    
	       
	    $table = $this->getTopN($_GET['from'], $_GET['to'], $_GET['limit'], $_GET['topics'],$_GET['aggregations'], $_GET['group'], $_GET['order'],$_GET['pivot']);    
	  
	    $json = new stdClass();        
	    $json->status = 'ok';
	    $json->reqId = $tqx['reqId'];
	    $json->table = $table;
	 
	    // Don't let IE cache this request
	    header("Pragma: no-cache");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
	    header("Content-type: application/javascript");
	    
		if (array_key_exists('responseHandler', $tqx)) {    
			$jsonret = $tqx['responseHandler'].'('.json_encode($json).');';
		} else {
			$jsonret = 'google.visualization.Query.setResponse('.json_encode($json).');';
		}
	   
	   $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	   $jsonret = str_replace('DEND"', '', $jsonret);
	   echo $jsonret;
	   die();
		//wp_send_json($jsonret);
	}
	
	
	private function replaceWordpressUser($somestring) {
	     $current_user = wp_get_current_user();
	     if ( !($current_user instanceof WP_User) )
            return $somestring;
     
        foreach($current_user as $key => $value) {
            if (is_numeric($value) || is_string($value)) {
                $somestring = str_replace('{'.$key.'}', strval($value), $somestring);    
            }
        }
        
        return $somestring;
	}
	
	public function shortcodeSet($atts,$content) {
		
		if (!$this->canUserDoRoleOption('MQTT_WriteAccessRole')) {
			return '';
		}
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		
		$atts = shortcode_atts([
				'topic' => '',
				'qos'=>'0',
				'retained'=>'0',
				'id'=>uniqid(),
				'class'=>'',
				'button_text'=>'+',
				'label_text'=>'',
				
				'input_type'=> 'text',
				'input_title' => '',
				'input_pattern' => '',
				'input_min'=> '',
				'input_max'=> '',
				'input_step'=>'',
				
				'restrictedtext'=>'Please log in to be able to publish to this topic',
				'minrole'=>''
		], $atts, NULL);
	
		$currentvalue = $this->shortcodeGet($atts);
		$id =uniqid();
		$class = $atts['class'];
		$topic =  $this->replaceWordpressUser($atts['topic']);
	
		$qos = $atts['qos'];
		$retained = $atts['retained'];
		$minrole = $atts['minrole'];
		
		if (($atts['minrole']!='') && (!$this->isUserRoleEqualOrBetterThan($atts['minrole']))) {
			return $atts['restrictedtext'];
		}
		
		$label = $atts['label_text'];
		
		$button_text = $atts['button_text'];
		$input_title = $atts['input_title']==''?"":"title='".$atts['input_title']."'";
		$input_pattern = $atts['input_pattern']==''?"":"pattern='".$atts['input_pattern']."'";
		$input_type = ($atts['input_type']=='')?"":"type='".$atts['input_type']."'";
		$input_min = $atts['input_min']==''?"":"min='".$atts['input_min']."'";
		$input_max = $atts['input_max']==''?"":"max='".$atts['input_max']."'";
		$input_step = $atts['input_step']==''?"":"step='".$atts['input_step']."'";
		
		$action = "doSet&topic=$topic&qos=$qos&retained=$retained&minrole=$minrole&pattern=$pattern";
		$wpn = wp_create_nonce($action);
		$action = "$action&wpn=$wpn&payload=";
	
		$url = $this->getAjaxUrl($action);
		
		
		//TODO: how to notify user that update was successful
		
		$script = "
	 	<div id='$id' class='$class'>
	 		
	 		<label for='mqttcogs_set_$id'>$label</label>
	 		<input id='mqttcogs_set_$id' value='$currentvalue' $input_type $input_title $input_pattern $input_min $input_max $input_step >
	 		<input id='mqttcogs_set_btn_$id' type='submit' value='$button_text' onclick='setMQTTData$id()'>
	 	
	 	 	<script type='text/javascript'>
	    		   
	 	 	jQuery('#mqttcogs_set_$id').focusin(function () {
	 	 		jQuery('#mqttcogs_set_btn_$id').val('$button_text');
	 	 	});
	 	 	
	      	function setMQTTData$id() {
	      		
	      		if (jQuery('#mqttcogs_set_$id')[0].checkValidity()) { 
					jQuery('#mqttcogs_set_btn_$id').prop('disabled', true);
					
					jQuery.get('$url' + document.getElementById('mqttcogs_set_$id').value,function( data ) {
					
					  	switch(data.status) {
								case 'ok':
									jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
									break;
								
								case 'buffered':
									jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
									break;
								
								default:
									jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10060').text());
									break;
						}
	  					
	  					//location.reload();
						})
					  .fail(function(data) {
					    alert( 'error' + data);
					  })
					  .always(function() {
					    jQuery('#mqttcogs_set_btn_$id').prop('disabled', false);
					    
					  });
				 }
	      	}  	
	    	</script>
		</div>";
			
		return $script;
	}
	
	public function shortcodeGet($atts,$content=NULL) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
	
		$atts = shortcode_atts([
				'limit' => '1',
				'topic' => '#',
				'from'=>'',
				'to'=>'',
				'aggregations' => '',
				'group' => '',
				'order'=>'DESC',
				'field'=>'payload',
				'local'=>'false',
				'dateformat' => get_option( 'date_format' ) 
		], $atts, NULL);
	
		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],1,$atts['topic'],$atts['aggregations'],$atts['group'],$atts['order']);
	
		if (sizeof($table->rows) == 0) 
			return '';
		
		$local = !($atts['local'] === 'true');
				
		switch ($atts['field']) {
			case "datetime":
				$ret = str_replace('DSTART(', '', $table->rows[0]->c[0]->v);
				$ret = str_replace(')DEND', '', $ret);
				return  date_i18n($atts['dateformat'],  ((int) $ret)/1000, $local);
			default:
				return $table->rows[0]->c[1]->v;
		}
		
		return '-'; 
	}
	
	public function shortcodeData($atts,$content) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
     	$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                                     'limit' => '999999',
	                                     'topics' => '#',
	                                     'from'=>'',
	                                     'to'=>'',
										 'aggregations' =>'',
										 'group' => '',
	  									 'order'=>'DESC',
	  									 'pivot'=>'true'
	                                 ], $atts, NULL);

		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],$atts['limit'],$atts['topics'],$atts['aggregations'], $atts['group'],$atts['order'],$atts['pivot']);	
		$jsonret = json_encode($table);   
	   $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	   $jsonret = str_replace('DEND"', '', $jsonret);
	    
	   return $jsonret;		
	}
	
	public function getLastN($table, $topic, $limit, $order) {
	    global $wpdb;
	    $table_name = $this->prefixTableName($table);	
		$topic = $this->replaceWordpressUser($topic);
		//add the next column definition
		
		$sql = $wpdb->prepare("SELECT `id`, `utc`,`topic`, `payload`, `qos`,`retain` from $table_name
								WHERE topic LIKE %s 
								order by utc $order limit %d",
								$topic,
								$limit			
								);
		 
    	$therows =  $wpdb->get_results(
    	    	 		$sql, ARRAY_A );
    	
		$therows = apply_filters('mqttcogs_shortcode_pre',$therows,$topic);
		
    	return $therows;
	}
	
	public function deleteBufferById($id) {
	    global $wpdb;
	    $table_name = $this->prefixTableName('buffer');	
		//$topic = $this->replaceWordpressUser($topic);
		//add the next column definition
		$wpdb->delete( $table_name, array( 'id' => $id ) );
	}
	
	public function getTopN($from, $to, $limit, $topics, $aggregations = '', $group='', $order = 'ASC', $pivot='true') {
	    global $wpdb;
	    $table_name = $this->prefixTableName('data');	
		$last_table_name = $this->prefixTableName('lastdata');	
		
	    if (is_numeric($from)) {      
	    	$from = time() + floatval($from)*86400;
	    	$from = date('Y-m-d H:i:s', $from);
	    }
	    
	    if (is_numeric($to)) {
	    	$to = time() + floatval($to)*86400;
	    	$to = date('Y-m-d H:i:s', $to);
	    }
	
	    $reqtopics = explode(',',$topics);
		
		$topics = array();
		foreach($reqtopics as $idx=>&$topic) {
    	   
			$topic = $this->replaceWordpressUser($topic);
			
			//detect if we have a wildcard %
			if (strpos($topic, '%') !== false) {
				//if we do, then we need to find all matched topics
				$sql = $wpdb->prepare("SELECT DISTINCT(`topic`) from $last_table_name
    	    	 						WHERE topic LIKE %s",
    	    	 						$topic
    	    	 				        );
				$therows =  $wpdb->get_results($sql, ARRAY_A );
				foreach($therows as $row) {
					array_push($topics, $row['topic']);
				}
			}
			else {
				array_push($topics, $topic);
			}
		}
		unset($topic);
		
				
	    $aggregations = explode(',',$aggregations);
		
	    //prevent sql injection here
	    if ($order != 'ASC') {
	    	$order = 'DESC';
	    }
	    
	    $index=0;
	    $json = new stdClass(); 
	    $json->cols = array();
	    $json->rows = array();
	    
	    	
				
	    //add the datetime column or grouping column
		if (empty($group)) {
			$json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"datetime"}');
		}
		else {
			$json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"number"}');
		}
		
		//add the topic column if not pivoting
	    if ($pivot=='false') {
	        $json->cols[] = json_decode('{"id":"topic", "label":"topic", "type":"string"}');
	        $json->cols[] = json_decode('{"id":"payload", "label":"payload", "type":"string"}');
	        
	    }
	

	    foreach($topics as $idx=>$topic) {
			$topic = apply_filters('mqttcogs_topic_pre', $topic);
		
			$agg = $aggregations[$idx].'(`payload`) as payload';
			
			if (empty($group)) {
				$sql = $wpdb->prepare("SELECT `utc`,$agg from $table_name
	    	 						WHERE topic=%s 
	    	 						AND ((utc>=%s OR %s='') 
	    	 						AND (utc<=%s OR %s='')) 
	    	 						order by utc $order limit %d",
	    	 						$topic,
	    	 						$from,
	    	 						$from,
	    	 						$to,
	    	 						$to,
	    	 						$limit			
	    	 				        );
	    	 
			} 
			else {
				$sql = $wpdb->prepare("SELECT EXTRACT($group FROM `utc`) as grouping,$agg from $table_name
	    	 						WHERE topic=%s 
	    	 						AND ((utc>=%s OR %s='') 
	    	 						AND (utc<=%s OR %s='')) 
									GROUP BY grouping
	    	 						order by utc $order limit %d",
	    	 						$topic,
	    	 						$from,
	    	 						$from,
	    	 						$to,
	    	 						$to,
	    	 						$limit			
	    	 				        );
	    	 
				
			}
	    	 
	    	$therows =  $wpdb->get_results($sql, ARRAY_A );
			$therows = apply_filters('mqttcogs_shortcode_pre',$therows, $topic);
			$colset = false;
            $topic = apply_filters('mqttcogs_topic', $topic);
			
			foreach($therows as $row) {
		
				$o = new stdClass();
				$o->c = array();
				
				//add grouping
				if (empty($group)) {
					$o->c[] = json_decode('{"v":"DSTART('.(strtotime($row["utc"])*1000).')DEND"}');
				}
				else {
					$o->c[] = json_decode('{"v":'.$row["grouping"].'}');
				}
				
				//default is to pivot, each topic is a new column
				if ($pivot=='true') {
    				//loop through columns
    				for($i = 0; $i < count($topics); ++$i){
    					if ($i==$index) {
    						if (!$colset) {
    							if (is_numeric($row['payload'])) {
    								//add the next column definition
    								$json->cols[] = json_decode('{"id":"'.$topic.'","type":"number"}');	
    							}
    							else {
    								//add the next column definition
    								$json->cols[] = json_decode('{"id":"'.$topic.'","type":"string"}');	
    							}
    							$colset = true;
    						}
    						
						     if (is_numeric($row['payload']) || ($this->startsWith($row['payload'], '{')) || ($this->startsWith($row['payload'], '['))) {
        			        	$o->c[] = json_decode('{"v":'.$row['payload'].'}');  
        				    }
        				    else {
        				        $o->c[] = json_decode('{"v":"'.$row['payload'].'"}');
        				    }
						
    					}
    					else {
    						$o->c[] = json_decode('{"v":null}');  
    					}
    				}	
				}
				else {
				    $o->c[] = json_decode('{"v":"'.$topic.'"}');   
			         if (is_numeric($row['payload']) || ($this->startsWith($row['payload'], '{')) || ($this->startsWith($row['payload'], '['))) {
    			        	$o->c[] = json_decode('{"v":'.$row['payload'].'}');  
    				    }
    				    else {
    				        $o->c[] = json_decode('{"v":"'.$row['payload'].'"}');
    				    }
					
			        	
				}
				
				$json->rows[] =$o;
			} //row
	      
	    $index++; 
	    } //topic	 
    
	   return $json;    
	}
	

	public function ajax_data($atts,$content) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	    $atts = shortcode_atts([
	                                     'limit' => '999999',
	                                     'topics' => '',
	                                     'action' => 'doGetTopN',
	                                     'from'=>'',
	                                     'to'=>'',
										 'aggregations' => '',
										 'group' => '',
	  									 'order'=>'DESC',
	  									 'pivot'=>'true'
	                                 ], $atts, NULL);
	   $limit = $atts['limit'];
	   $topics = $atts['topics'];
	 
	   return $this->getAjaxUrl($atts['action'].'&limit='.$limit.'&topics='.$topics.'&from='.$atts["from"].'&to='.$atts["to"].'&aggregations='.$atts["aggregations"].'&group='.$atts["group"].'&order='.$atts["order"].'&pivot='.$atts["pivot"]);                                                              
	} 
	
	
	
	public function shortcodeDrawDataTable($atts, $content) {
		static $datatables = array();	
		$this->setupLogging();
		//we include google scripts here for JSON parsing and datatable support
		wp_enqueue_script('google_loadecharts');
	    wp_enqueue_script('loadgoogle');
	  
	    //leaflet libraries
	    wp_enqueue_style('datatablescss');
	    wp_enqueue_script('datatables');
		wp_enqueue_script('datatablesdrawer');	
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		$id = uniqid();
		
		$atts = shortcode_atts([
	                           'height' => '180px',
							   'width' =>'',
	                           'options' => '{}',
					           'refresh_secs'=>0,
							   'script' => ''
	                       ], $atts, NULL);
		
		$options = $atts["options"];
		$height = $atts["height"];
		$width = $atts["width"];
		$refresh_secs = $atts["refresh_secs"];
		
		//$prescript = $atts["script"];
		$prescript='';
		
		if ($atts["script"]!=='') {
			global $post;
			//$post->ID; 
			$prescript = get_post_meta($post->ID, $atts["script"], true);
			$prescript = str_replace(array('\r', '\n', '\t'), '', trim($prescript));	
		}
	
		global $wp_scripts;
		$content = str_replace('mqttcogs_', 'mqttcogs_ajax_', $content);
		$content= strip_tags($content);
		$content = trim($content);
		$content = str_replace(array('\r', '\n'), '', trim($content));	

		$content = strip_tags(do_shortcode($content));
		$querystring =  str_replace(array("\r", "\n"), '', $content);

		$script = '<div><table id="'.$id.'" style="height:'.$atts['height'].'"><thead><tr></tr></thead><tbody></tbody></table></div>';
	

	 
	    $datatables[] = array(
  	     "id"=>$id,
  	     "refresh_secs"=>$refresh_secs,
  	     "options"=> $options,
  	     "querystring"=>$querystring,
		 "script"=>$prescript
  	    );
  	    
        $wp_scripts->add_data('datatablesdrawer', 'data', '');
    	wp_localize_script( 'datatablesdrawer', 'alltables', $datatables);
    	return $script;	
	}
	
	public function shortcodeDrawLeaflet($atts, $content) {
		static $leafletmaps = array();	
		$this->setupLogging();
		//we include google scripts here for JSON parsing and datatable support
		wp_enqueue_script('google_loadecharts');
	    wp_enqueue_script('loadgoogle');
	  
	    //leaflet libraries
	    wp_enqueue_style('leafletcss');
	    wp_enqueue_script('leaflet');
		wp_enqueue_script('leafletdrawer');	
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		$id = uniqid();
		
		$atts = shortcode_atts([
	                           'height' => '180px',
							   'width' =>'',
	                           'options' => '{}',
					           'refresh_secs'=>0,
							   'tilelayers' => '{urlTemplate:\'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png\',options: {attribution: \'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors\'}}',
							   'script' => ''
	                       ], $atts, NULL);
		
		$options = $atts["options"];
		$height = $atts["height"];
		$width = $atts["width"];
		$refresh_secs = $atts["refresh_secs"];
		$tileLayers = $atts["tilelayers"];
		//$prescript = $atts["script"];
		$prescript='';
		
		if ($atts["script"]!=='') {
			global $post;
			//$post->ID; 
			$prescript = get_post_meta($post->ID, $atts["script"], true);
			$prescript = str_replace(array('\r', '\n', '\t'), '', trim($prescript));	
		}
	
		global $wp_scripts;
		$content = str_replace('mqttcogs_', 'mqttcogs_ajax_', $content);
		$content= strip_tags($content);
		$content = trim($content);
		$content = str_replace(array('\r', '\n'), '', trim($content));	

		$content = strip_tags(do_shortcode($content));
		$querystring =  str_replace(array("\r", "\n"), '', $content);

		$script = '<div><div id="'.$id.'" style="height:'.$atts['height'].'"/></div>';
	 
	    $leafletmaps[] = array(
  	     "id"=>$id,
  	     "refresh_secs"=>$refresh_secs,
  	     "options"=> $options,
		 "tilelayers"=> $tileLayers,
  	     "querystring"=>$querystring,
		 "script"=>$prescript
  	    );
  	    
        $wp_scripts->add_data('leafletdrawer', 'data', '');
    	wp_localize_script( 'leafletdrawer', 'allmaps', $leafletmaps);
    	return $script;	
	}
	
	//////

	public function shortcodeDrawGoogle($atts,$content) {
      $this->setupLogging();
	
	  static $graphs = array();	
	 
	  //only include google stuff for this shortcode
	  wp_enqueue_script('google_loadecharts');
	  wp_enqueue_script('loadgoogle');
			
 	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                           'charttype' => 'LineChart',
	                            'options' => '{"width":400,"height":300}',
					            'refresh_secs'=>0,
								'script' => ''
	                       ], $atts, NULL);
	
		
	$id = uniqid();
    $options = $atts["options"];
    $charttype = $atts["charttype"];
	$refresh_secs = $atts["refresh_secs"];
	$prescript='';
	
	if ($atts["script"]!=='') {
			global $post;
			//$post->ID; 
			$prescript = get_post_meta($post->ID, $atts["script"], true);
			$prescript = str_replace(array('\r', '\n', '\t'), '', trim($prescript));	
		}
		
  	wp_enqueue_script('chartdrawer');
  	
  	global $wp_scripts;
 
	$content = str_replace('mqttcogs_', 'mqttcogs_ajax_', $content);
	$content= strip_tags($content);
	$conent = trim($content);
	$content = str_replace(array('\r', '\n'), '', trim($content));	

	$content = strip_tags(do_shortcode($content));
	$querystring =  str_replace(array("\r", "\n"), '', $content);

	$script = '
	 <div id="'.$id.'"></div>';
	 
	 $graphs[] = array(
  	     "id"=>$id,
  	     "refresh_secs"=>$refresh_secs,
  	     "options"=> $options,
  	     "charttype"=>$charttype,
  	     "querystring"=>$querystring,
		 "script"=>$prescript
  	    );
  	    
        $wp_scripts->add_data('chartdrawer', 'data', '');
    	wp_localize_script( 'chartdrawer', 'allcharts', $graphs );
    	return $script;	
	}

	function getGoogleLoadJS($charttype) {
		return 	 'google.charts.load("current", {"packages":["corechart","bar","table","gauge","map"]});';

	}
	
	public function startsWith($haystack, $needle)
	{
		 $length = strlen($needle);
		 return (substr($haystack, 0, $length) === $needle);
	}

	public function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}

		return (substr($haystack, -$length) === $needle);
	}
}


class MySubscribeCallback extends MessageHandler
{
	private $mqttcogs_plugin;
	
	public function __construct($theownerobject)
	{
		$this->mqttcogs_plugin = $theownerobject;
	}
			
	public function publish(sskaje\mqtt\MQTT $mqtt,sskaje\mqtt\Message\PUBLISH $publish_object)
	{
		global $wpdb;
		try
		{
		    $topic = $publish_object->getTopic();
		    $msg = $publish_object->getMessage();
			$qos = $publish_object->getQos();
			$retain = $publish_object->getRetain();
		    Debug::Log(DEBUG::INFO,
		    "MqttCogs msg received {$topic}, {$msg}, {$qos}, {$retain}");
		   
		
			$tableName = $this->mqttcogs_plugin->prefixTableName('data');
			
			$datetime = new DateTime(); //current_time( 'mysql', true );
			
			$publish_object = apply_filters('mqttcogs_msg_in_pre',$publish_object ,$datetime);
			
			if (!isset($publish_object)) {
				return;
			}

			$utc = date_format($datetime, 'Y-m-d H:i:s');
 			
			//deal with smartsleep nodes
			// mysensors_out/10/255/3/0/32
			if ($this->mqttcogs_plugin->endsWith($publish_object->getTopic(), '/255/3/0/32')) {				
				$pieces = explode("/", $publish_object->getTopic());
				$nodeid = $pieces[1];
				$subnode = $pieces[2];
				
				//node is online so....
				//TO DO BETTER CHECK HERE
				$txtopic = $this->mqttcogs_plugin->getOption('MQTT_MySensorsTxTopic', 'mysensors_in');	
				$therows = $this->mqttcogs_plugin->getLastN('buffer', $txtopic.'/'.$nodeid.'/%',10, 'ASC');
				
				//rows are descending by datetime 
				//$therows = array_reverse($therows);
				$json = new stdClass();
				
				foreach($therows as $row) {
					if ($this->mqttcogs_plugin->sendMqttInternal($row['id'],$row['topic'], $row['payload'], $row['qos'],$row['retain'], false, $json)) {						
						$this->mqttcogs_plugin->deleteBufferById($row['id']);
					}
					else {
						break;
					}
				}
			}
			
			$result = false;
		    if ($publish_object->getRetain() == 1) {
	       		Debug::Log(DEBUG::INFO,'MSG IS RETAINED');
	
		        //attempt to update
		        $result = $wpdb->update( 
                	$tableName, 
                	array(
							'utc' => $utc,
							'payload' => $publish_object->getMessage(),
					),
                	array(
                	    'topic' => $publish_object->getTopic(),
                	    'retain' => 1), 
                	array( 
                		'%s',
                		'%s'
                	),
                	array( '%s','%d' ) 
                );
            	Debug::Log(DEBUG::INFO,'Result:'.$result);
		    }
		
		    //if we didn't manage to update, then just insert
		    //be really careful here. If the update doesn't change
		    //the data then 0 is returned.....ARRRG
		    if (false===$result) {
		        Debug::Log(DEBUG::INFO,'INSERTING:');
    			$wpdb->insert(
    					$tableName,
    					array(
    							'utc' => $utc,
    							'topic' => $publish_object->getTopic(),
    							'payload' => $publish_object->getMessage(),
    							'qos' =>$publish_object->getQos(),
    							'retain' => $publish_object->getRetain()
    					),
    					array(
    							'%s',
    							'%s',
    							'%s'
    					)
    				);
		    }
			
			do_action_ref_array(
								'after_mqttcogs_msg_in',
								array(
									$utc,
									$publish_object->getTopic(),
									$publish_object->getMessage(),
									$publish_object->getQos(),
									$publish_object->getRetain()
								)
							);
				
			//is this a node sleep? If yes then get any buffered messages
			
		}
		catch (Exception $e) {
		        Debug::Log(DEBUG::ERR,$e->getMessage());
	
					//force loop to exit
			$this->mqttcogs_plugin->mqtt = null;
			
			//attempt graceful disconnect
			$mqtt->disconnect();
		}
	}
}

