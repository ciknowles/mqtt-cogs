<?php
require(__DIR__ . '/./sskaje/autoload.example.php');
require(__DIR__ . '/./flock/Lock.php');

require_once __DIR__ . '/./AutoLoadByNamespace.php';

spl_autoload_register("AutoloadByNamespace::autoload");
AutoloadByNamespace::register("Google\Visualization\DataSource", __DIR__ . '/./google-visualization');

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
    //	$this->write_log(var_dump($arr));
    	
    	$writeroles = array_merge(array(__('MQTT Write Role', 'mqttcogs')), $arr);
    	
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            
        	'MQTT_Version' => array(__('MQTT Version', 'mqttcogs'), "3_1_1", "3_1"),
        	'MQTT_Server' => array(__('MQTT Server/Port', 'mqttcogs')),
			'MQTT_ClientID' => array(__('MQTT ClientID', 'mqttcogs')),
			'MQTT_Username' => array(__('MQTT User', 'mqttcogs')),
			'MQTT_Password' => array(__('MQTT Password', 'mqttcogs')),
			
			'MQTT_TopicFilter' => array(__('MQTT TopicFilter', 'mqttcogs')),
			
			'MQTT_KeepArchive' => array(__('Save MQTT data for', 'mqttcogs'),
					'Forever', '365 Days', '165 Days', '30 Days', '7 Days', '1 Day'),
                                
            'MQTT_Recycle' => array(__('MQTT Connection Recycle (secs)', 'mqttcogs')),
            'MQTT_Debug' => array(__('MQTT Debug', 'mqttcogs'), 'On', 'Off'),
        		      	
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
			PRIMARY KEY  (id)
			) $charset_collate;";
			
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
                $wpdb->query("DROP TABLE IF EXISTS '$tableName'");
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
	 
    	 add_shortcode('mqttcogs_drawgoogle', array($this, 'doDrawGoogle'));
    	 add_shortcode('mqttcogs_graph', array($this, 'doDrawGoogle2'));
	
	 
    	 add_shortcode('mqttcogs_data', array($this, 'doData'));	
    	 add_shortcode('mqttcogs_ajax_data', array($this, 'ajax_data'));

    	 add_shortcode('mqttcogs_get', array($this, 'doGet'));
    	 add_shortcode('mqttcogs_set', array($this, 'doSet'));
	 
		 
        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41
    	add_action('wp_ajax_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN'));
    	
    	add_action('wp_ajax_nopriv_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN')); 
    	
    	add_action('wp_ajax_doSet', array(&$this, 'ajaxACTION_doSet'));
    	add_action('wp_ajax_nopriv_doSet', array(&$this, 'ajaxACTION_doSet'));
    
    	add_action('wp_ajax_doSQL', array(&$this, 'ajaxACTION_doSQL'));
    	add_action('wp_ajax_nopriv_doSQL', array(&$this, 'ajaxACTION_doSQL'));
   	}
   	   	 
	public function enqueueStylesAndScripts() {
	wp_enqueue_script('google_loadecharts','https://www.gstatic.com/charts/loader.js' );
	wp_enqueue_script('loadgoogle', plugins_url('/js/loadgoogle.js', __FILE__));

	}

    //prunes the mqttcogs database table. Run daily
	function do_mqtt_prune() {
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
	//	$this->write_log($sql);			
	    Debug::Log(DEBUG::INFO, 'Pruning MqttCogs database table');
		$wpdb->query($sql);										
	}    

    //
	function do_mqtt_watchdog() {
		
	try {
		$file = './mqttcogs_lock.pid';
		$lock = new flock\Lock($file);
		
		// Non-blocking case. Acquire lock if it's free, otherwse exit immediately
		$gmt_time = microtime( true );
	
		if ($lock->acquire()) { 
		        register_shutdown_function(array($this, 'shutdownHandler'));
        		
        		if ("on" == $this->getOption("MQTT_Debug", "on")) {
        		    Debug::Enable();
        		    Debug::SetLogPriority(Debug::INFO);	
        		}
        		else {
        		    Debug::Disable();
        		}
        		
        		
        		
        		if ("false" == $this->getOption("MQTT_Recycle", "false")) {
        			$this->addOption("MQTT_Recycle", "295");
        		}
        		
        		$recycle_secs = intval($this->getOption("MQTT_Recycle"));
        		
				$mqtt = new MQTT($this->getOption("MQTT_Server"), $this->getOption("MQTT_ClientID"));
				
				switch ($this->getOption("MQTT_Version")) {
					case "3_1_1":
						$mqtt->setVersion(MQTT::VERSION_3_1_1);
					default: 
						$mqtt->setVersion(MQTT::VERSION_3_1 );
				}
				
				$context = stream_context_create();
				$mqtt->setSocketContext($context);
					
				if ($this->getOption("MQTT_Username")) {
					$mqtt->setAuth($this->getOption("MQTT_Username"), $this->getOption("MQTT_Password"));
				}
				
				$result = $mqtt->connect();
							
				if (!($result)) {
				    Debug::Log(DEBUG::ERR, "MQTT can't connect");
					return;
				}
	
			//	$this->write_log("phpMQTT connected");
				$this->mqtt = $mqtt;
				
				$topics[$this->getOption("MQTT_TopicFilter")] = 1;
				$callback = new MySubscribeCallback($this);
				$mqtt->setHandler($callback);
				$mqtt->subscribe($topics);
						
				while(($this->mqtt) && (microtime(true)-$gmt_time<$recycle_secs) && $mqtt->loop()) {
					set_time_limit(0);
				}
				
			//	$this->write_log("disconnecting");
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
						   Debug::Log(DEBUG::ERR, $ee->getMessage());
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
	
	
    /*
     * Called to set a mqtt value
     */
    public function ajaxACTION_doSet() {
    
    	if (!$this->canUserDoRoleOption('MQTT_WriteAccessRole')) {
    		die();
    	}
    	
    	global $wpdb;
    	
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
    	
    	$json = new stdClass();
    	$json->status = 'ok';
    	
    	//we now connect to the broker
    	$mqtt = new MQTT($this->getOption("MQTT_Server"), $atts['id']);
    		
    	switch ($this->getOption("MQTT_Version")) {
    		case "3_1_1":
    			$mqtt->setVersion(MQTT::VERSION_3_1_1);
    		default:
    			$mqtt->setVersion(MQTT::VERSION_3_1 );
    	}
    		
    	$context = stream_context_create();
    	$mqtt->setSocketContext($context);
    	
    	if ($this->getOption("MQTT_Username")) {
    		$mqtt->setAuth($this->getOption("MQTT_Username"), $this->getOption("MQTT_Password"));
    	}
    		
    	$result = $mqtt->connect();
    	
    	if (!($result)) {
    	    Debug::Log(DEBUG::ERR,"doSet: MQTT can't connect");
        		$json->status = 'error';
    		$error_1 = new stdClass();
    		$error_1->reason='mqtt connection failure';
    		$error_1->message = '';
    		$json->errors = array();
    		$json->errors.push($error_1); 		
    	}
    	
    	else {
        //	$this->write_log("doSet: MQTT connected");
        	
        	
        	// Don't let IE cache this request
        	header("Pragma: no-cache");
        	header("Cache-Control: no-cache, must-revalidate");
        	header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
        	header("Content-type: application/json");
        	
        	if (!$mqtt->publish_sync($topic, $payload, $qos, $retained)) {
        	    Debug::Log(DEBUG::ERR,"doSet: MQTT publish failure");
        		
        		$json->status = 'error';
        		$error_1 = new stdClass();
        		$error_1->reason='mqtt publish failure';
        		$error_1->message = '';
        		$json->errors = array();
        		$json->errors.push($error_1);
        	}
    	}
    	
    	Debug::Log(DEBUG::INFO,"doSet: MQTT publish success");
    	$jsonret = json_encode($json);
       	echo $jsonret;	
    	die();
    }
	
    	
	public function ajaxACTION_doGetTopN() {
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
	    
	       
	    $table = $this->getTopN($_GET['from'], $_GET['to'], $_GET['limit'], $_GET['topics'], $_GET['order'],$_GET['jsonfields']);    
	    //       echo( $_GET['topics']);
	    //echo json_encode($table);
	    $json = new stdClass();        
	    $json->status = 'ok';
	    $json->reqId = $tqx['reqId'];
	    $json->table = $table;
	 
	    // Don't let IE cache this request
	    header("Pragma: no-cache");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
	 
	    header("Content-type: application/json");
	 
	   $jsonret = 'google.visualization.Query.setResponse('.json_encode($json).');';
	   
	   $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	   $jsonret = str_replace('DEND"', '', $jsonret);
	    
	   echo $jsonret;
	   die();
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
	
	public function doSet($atts,$content) {
		
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
	
		$currentvalue = $this->doGet($atts);
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
					
					    if (data.status == 'ok') {
    	  					jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
	  					}
	  					else {
	  					    alert('Error!');
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
	
	public function doGet($atts,$content=NULL) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
	
		$atts = shortcode_atts([
				'limit' => '1',
				'topic' => '#',
				'from'=>'',
				'to'=>'',
				'order'=>'DESC',
				'field'=>'payload',
				'local'=>'false',
				'dateformat' => get_option( 'date_format' ) 
		], $atts, NULL);
	
		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],1,$atts['topic'],$atts['order']);
	
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
	
	public function doData($atts,$content) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
     	$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                                     'limit' => '999999',
	                                     'topics' => '#',
	                                     'from'=>'',
	                                     'to'=>'',
	  									 'order'=>'DESC',
	  									 'jsonfields'=>''
	                                 ], $atts, NULL);

		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],$atts['limit'],$atts['topics'],$atts['order'],$atts['jsonfields']);	
		$jsonret = json_encode($table);   
	   $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	   $jsonret = str_replace('DEND"', '', $jsonret);
	    
	   return $jsonret;		
	}
	
	public function getTopN($from, $to, $limit, $topics, $order = 'ASC', $jsonfields='') {
	    global $wpdb;
	    $table_name = $this->prefixTableName('data');	
	  
	    if (is_numeric($from)) {
	       
	    	$from = time() + floatval($from)*86400;
	    	$from = date('Y-m-d H:i:s', $from);
	    
	    }
	    
	    if (is_numeric($to)) {
	    	$to = time() + floatval($to)*86400;
	    	$to = date('Y-m-d H:i:s', $to);
	    }
	    	
	   // $this->write_log($topics);
	   // $this->write_log($from);
	
	    $topics = explode(',',$topics);
	    
	    //prevent sql injection here
	    if ($order != 'ASC') {
	    	$order = 'DESC';
	    }
	    
	    $index=0;
	    $json = new stdClass(); 
	    $json->cols = array();
	    $json->rows = array();
	    
	    //if not json then we decode as we have always
	    if ($jsonfields=='') {
    	    //add the datetime column
    	    $json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"datetime"}');
    	    foreach($topics as $topic) {
    	    	
    	    	$topic = $this->replaceWordpressUser($topic);
    	    	//add the next column definition
    	    	$json->cols[] = json_decode('{"id":"topic_'.$index.'","type":"number"}');		    	
    	    	
    	    	 $sql = $wpdb->prepare("SELECT `utc`,`payload` from $table_name
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
    	    	 
    	    	 $therows =  $wpdb->get_results(
    	    	 		$sql		, ARRAY_A );
    	    		    	 
    	  	foreach($therows as $row) {
    	  		$o = new stdClass();
    			$o->c = array();
    		        $o->c[] = json_decode('{"v":"DSTART('.(strtotime($row["utc"])*1000).')DEND"}');
    	  		
    	  		
    	  		//loop through columns
    	  		for($i = 0; $i < count($topics); ++$i){
    	  			
    	  			if ($i==$index) {
    	  				if (is_numeric($row['payload'])) {
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
    			
    			 
    		        $json->rows[] =$o;
    	        }
    	      
    	    	$index++; 
    	    } 	 
	    }
	    //we are returning json data....
	    
	    else {
	         $jsonpropsraw = explode(',', $jsonfields);
	         $jsonprops= array();
	         $jsontypes= array();
	         
	         foreach($jsonpropsraw as $prop) {
	             $nameandtype = explode('|', $prop);
	             $jsonprops[] = $nameandtype[0];
	             if (sizeof($nameandtype)==2) {
    	         	 $json->cols[] = json_decode('{"id":"'.$nameandtype[0].'", "label":"'.$nameandtype[0].'", "type":"'.$nameandtype[1].'"}');
    	         	 $jsontypes[] = $nameandtype[1];
	             }
	             else {
	                  $json->cols[] = json_decode('{"id":"'.$nameandtype[0].'", "label":"'.$nameandtype[0].'", "type":"number"}');
	                  $jsontypes[] = 'number';
	             }
	         }
	         
	         foreach($topics as $topic) {
	             	$topic = $this->replaceWordpressUser($topic);
        	    	 $sql = $wpdb->prepare("SELECT `utc`,`payload` from $table_name
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
        	    	 
        	    	 $therows =  $wpdb->get_results(
        	    	 		$sql		, ARRAY_A );
        	    		    	 
        	    try
        	    {
        	        foreach($therows as $row) {
            	  		$o = new stdClass();
            			$o->c = array();
            			
            	        $payload =json_decode($row['payload'], true);
            	        
            	        for ($col = 0; $col < sizeof($jsonprops); $col++) {
            	            $prop = $jsonprops[$col];
                            switch ($jsontypes) {
                                
                                case 'datetime':
                                    $dtm = strtotime($payload[$prop]);
                                    $o->c[] = json_decode('{"v":"new Date('.$dtm.')"}');   
                                    break;
                                case 'number':
                               
                                 	$o->c[] = json_decode('{"v":'.$payload[$prop].'}');    
                                    break;    
                                case 'string':
                                    $o->c[] = json_decode('{"v":"'.$payload[$prop].'"}');   
                                    break;
                                default:
                                    if (is_numeric($payload[$prop])) {
                		  				$o->c[] = json_decode('{"v":'.$payload[$prop].'}'); 
                	  				}
                	  				else {
                		  				$o->c[] = json_decode('{"v":"'.$payload[$prop].'"}');   
                	  				}      
                                    break;
                            }
                        } 
            	        
            	        
    	        	  /*  foreach($jsonprops as $prop) {
        	  			    if (is_numeric($payload[$prop])) {
        		  				$o->c[] = json_decode('{"v":'.$payload[$prop].'}'); 
        	  				}
        	  				else {
        		  				$o->c[] = json_decode('{"v":"'.$payload[$prop].'"}');   
        	  				}        
    	  			    }*/
            	  			
              		    $json->rows[] =$o;
        	        }
        	    }
        	    catch(Exception $e) {
        	        Log(DEBUG::ERR,$e->getMessage());
        	    }
	        }
	        
	    }
	    
	   return $json;    
	
	}
	
	
	
	public function ajaxACTION_doSQL() {
		/*if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}*/
           
           header("Pragma: no-cache");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
	 
	    /*header("Content-type: text/plain");*/
	   
    
          $table_name = $this->prefixTableName('data');	
          
     
	   new MyDataSource($table_name);
	    
	   die();
	   
	  
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
	  									 'order'=>'DESC',
	  									 'jsonfields'=>''
	                                 ], $atts, NULL);
	  $limit = $atts['limit'];
	  $topics = $atts['topics'];
	 
	  return $this->getAjaxUrl($atts['action'].'&limit='.$limit.'&topics='.$topics.'&from='.$atts["from"].'&to='.$atts["to"].'&order='.$atts["order"].'&jsonfields='.$atts["jsonfields"]);                               
	                                 
	} 
	
	public function doDrawGoogle2($atts,$content) {
	 /* wp_enqueue_script('loadgoogle', plugins_url('/js/loadgoogle.js', __FILE__));*/
	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
 
 	  
	  $atts = shortcode_atts([
	                                     'charttype' => 'LineChart',
	                                     'options' => '{"width":400,"height":300}',
					     'refresh_secs'=>60,
					     'query'=>'SELECT utc,payload LIMIT 1'
	                                 ], $atts, NULL);
	
	$script = '';// 'monkey'.$atts['query'];
	$id = uniqid();
        $options = $atts["options"];
        $charttype = $atts["charttype"];
	$refresh_secs = $atts["refresh_secs"];
	$query = explode('|', urldecode($atts["query"]));
	
	$querystring =  $this->getAjaxUrl('doSQL');   

	$script = $script.'
	 <div id="'.$id.'">
	 <script type="text/javascript">
		google.charts.setOnLoadCallback(drawChart'.$id.');

		function drawChart'.$id.'() {

	        var allresults=[];
	        allresults.length = '.count($query).';';
	      
	        
	for ($queryid = 0; $queryid  <= count($query)-1; $queryid ++) {
  		 $script = $script.'var query = new google.visualization.Query("'.$querystring.'");
  		  allresults['.$queryid.'] =undefined;
  		  query.setQuery("'.$query[$queryid].'");
  		   query.send(function (response) {
       				  handleResponse'.$id.'(response, '.$queryid.');
			    }); 
  		   
  		   if ('.$refresh_secs.'>0) {
		     	query.setRefreshInterval('.$refresh_secs.');
		   }';
	 }
	
	$script = $script.'	   
  	
  	 	  
  		   function handleResponse'.$id.'(response, id) {
			if (response.isError()) {
	    			alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());
	     			return;
		        }
	
		   	allresults[id] = response.getDataTable();	 
			
			//if all are loaded then we join them
			for(var idx=0;idx<allresults.length-1;idx++){
				if (!allresults[idx]) {
					return;
				}	
			}   	
			var joinedData = allresults[0];


			var columns = [];
			if (allresults.length>1) {
				$.each(allresults, function (index, datatable) {
			        if (index != 0) {
			            columns.push(index);
			            joinedData = google.visualization.data.join(joinedData, datatable, "full", [[0, 0]], columns, [1]);
			        }
			    });
			}
	   	
	   		var chart = new google.visualization.'.$charttype.'(document.getElementById("'.$id.'"));
			chart.draw(joinedData , '.$options.');
	   		
	   	  
	  
	      } //handlerespoonse
	      }//drawchart
	    </script></div>';
	   
    	return $script;	
	}
	
	
	//////

	public function doDrawGoogle($atts,$content) {
      
 	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                           'charttype' => 'LineChart',
	                            'options' => '{"width":400,"height":300}',
					            'refresh_secs'=>60
	                       ], $atts, NULL);
	
	$id = uniqid();
	$ajax = ($atts['refresh_secs'] > 0);
	
    $options = $atts["options"];
    $charttype = $atts["charttype"];
   
	$refresh_secs = $atts["refresh_secs"];
	if (!$atts["ajax"]) {
		$script = '
	 <div id="'.$id.'">
	 <script type="text/javascript">
	      google.charts.setOnLoadCallback(drawChart'.$id.');
	      
	      function drawChart'.$id.'() {
	      var data = new google.visualization.DataTable('.strip_tags(do_shortcode($content)).');	
		
	        var chart = new google.visualization.'.$charttype.'(document.getElementById("'.$id.'"));
	        chart.draw(data, '.$options.');      
	      }
	    </script></div>';
        return $script;
	}
	else {

	$content = str_replace('mqttcogs_', 'mqttcogs_ajax_', $content);
	$content= strip_tags($content);
	$conent = trim($content);
	$content = str_replace(array('\r', '\n'), '', trim($content));	

	$content = strip_tags(do_shortcode($content));
	$querystring =  str_replace(array("\r", "\n"), '', $content);

	$script = '
	 <div id="'.$id.'">
	 <script type="text/javascript">
	      google.charts.setOnLoadCallback(drawChart'.$id.');
	        
	      var chart'.$id.';
	      var query'.$id.';
	      function drawChart'.$id.'() {
	          chart'.$id.'= new google.visualization.'.$charttype.'(document.getElementById("'.$id.'"));

		      query'.$id.' = new google.visualization.Query("'.$querystring.'");
		      query'.$id.'.send(handleResponse'.$id.');
	      }
	      
	      function handleResponse'.$id.'(response) {
	    	if (response.isError()) {
    			alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());
     			return;
	        }

	   	    var data = response.getDataTable();	 
	        chart'.$id.'.draw(data, '.$options.');
	        
	        setTimeout(function () {
	            query'.$id.'.send(handleResponse'.$id.');
	        },'.$refresh_secs.'*1000);
	      }
	    </script></div>';
    	return $script;	
	
	}
	}

	function getGoogleLoadJS($charttype) {
		return 	 'google.charts.load("current", {"packages":["corechart","bar","table","gauge","map"]});';

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
		    Debug::Log(DEBUG::INFO,
		    "MqttCogs msg received {$topic}, {$msg}");
		   
		
			$tableName = $this->mqttcogs_plugin->prefixTableName('data');
			$utc = current_time( 'mysql', true );
		
			$publish_object = apply_filters('mqttcogs_msg_in_pre',$publish_object, $utc);
		
			$wpdb->insert(
					$tableName,
					array(
							'utc' => $utc,
							'topic' => $publish_object->getTopic(),
							'payload' => $publish_object->getMessage()
					),
					array(
							'%s',
							'%s',
							'%s'
					)
					);
			$publish_object = apply_filters('mqttcogs_msg_in',$publish_object, $utc);
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

 // The custom class that defines how the data is generated
 class MyDataSource extends Google\Visualization\DataSource\DataSource
  {
    private $table;
	
    public function __construct($table)
    {
	$this->table = $table;	

        $_REQUEST['tq'] = stripslashes ($_REQUEST['tq']);

	parent::__construct();
    }
	
    public function getCapabilities() { return Google\Visualization\DataSource\Capabilities::SQL; }

    public function generateDataTable(Google\Visualization\DataSource\Query\Query $query)
    {          
     

      global $wpdb; 
      return Google\Visualization\DataSource\Util\WPDataSourceHelper::executeQuery($query, $wpdb, $this->table);
    }

    public function isRestrictedAccessMode() { return FALSE; }
 }

