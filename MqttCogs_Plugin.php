<?php
require(__DIR__ . '/./sskaje/autoload.example.php');
require(__DIR__ . '/./flock/Lock.php');

include_once('MqttCogs_LifeCycle.php');

use \sskaje\mqtt\MQTT;
use \sskaje\mqtt\Debug;
use \sskaje\mqtt\MessageHandler;
use \flock\Lock;

class MqttCogs_Plugin extends MqttCogs_LifeCycle {

    public $mqtt;

    public function write_log ( $log )  {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
    }


    public function activate() {
    		
		$this->write_log("activate");
		if (! wp_next_scheduled ( 'mqtt_cogs_watchdog' )) {
			wp_schedule_event(time(), '1min', 'mqtt_cogs_watchdog');
	    }

	    if (! wp_next_scheduled ( 'mqtt_cogs_prune' )) {
			wp_schedule_event(time(), 'daily', 'mqtt_cogs_prune');
	    }

    }
    
  
    public function deactivate() {
		wp_clear_scheduled_hook('mqtt_cogs_watchdog');
		delete_transient( 'doing_mqtt' );
		wp_clear_scheduled_hook('mqtt_cogs_prune');
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
		global $wp_roles;
    	
		$arr = array('Anyone');
		foreach ( $wp_roles->get_names() as $value) {
			$arr[]=  $value;
		}
			
    	$readroles = array_merge(array(__('MQTT Read Role', 'mqttcogs')),  $arr);
    	//$this->write_log(var_dump($arr));
    
    	$writeroles = array('Contributor') +  $arr;
    	$this->write_log(var_dump($arr));
    	
    	$writeroles = array_merge(array(__('MQTT Write Role', 'mqttcogs')), $arr);
    	
    	
        
        //http://plugin.michael-simpson.com/?page_id=31
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
        		      	
        		
        	'MQTT_ReadAccessRole' => $readroles,
        	'MQTT_WriteAccessRole' => $writeroles
			
        );
    }


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
		$this->write_log("installing db");        
		
		
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
		$this->write_log("Removing database...");
		
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
	//add_action('admin_enqueue_scripts', array(&$this, 'load_google'));
	//add_action( 'wp_enqueue_scripts', array(&$this, 'load_google') );
	add_action('wp_enqueue_scripts', array(&$this, 'enqueueStylesAndScripts'));
	
	
	add_action('mqtt_cogs_watchdog', array(&$this, 'do_mqtt_watchdog'));
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
	 
	 add_shortcode('mqttcogs_data', array($this, 'doData'));	
	 add_shortcode('mqttcogs_ajax_data', array($this, 'ajax_data'));
	 add_shortcode('mqttcogs_get', array($this, 'doGet'));
	 
	 add_shortcode('mqttcogs_set', array($this, 'doSet'));

        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41
		
	//	add_action( 'update_option', 'action_update_option', 10, 3 ); 
		
	//	add_action( 'update_option', string $option, mixed $old_value, mixed $value )
	
	add_action('wp_ajax_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN'));
	
	add_action('wp_ajax_nopriv_doGetTopN', array(&$this, 'ajaxACTION_doGetTopN')); // optional
	
	add_action('wp_ajax_doSet', array(&$this, 'ajaxACTION_doSet'));
	add_action('wp_ajax_nopriv_doSet', array(&$this, 'ajaxACTION_doSet')); // optional

	
	
   	}
   	   	 
    	public function enqueueStylesAndScripts() {
		wp_enqueue_script('google_loadecharts','https://www.gstatic.com/charts/loader.js' );
    	}

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
		$this->write_log($sql);			
		$wpdb->query($sql);										
	}    

	function do_mqtt_watchdog() {
		
	try {
		$file = './mqttcogs_lock.pid';
		$lock = new flock\Lock($file);
		
		// Non-blocking case. Acquire lock if it's free, otherwse exit immediately
		$gmt_time = microtime( true );
	
		if ($lock->acquire()) { 
		        register_shutdown_function(array($this, 'shutdownHandler'));
        		
        		Debug::Enable();
        		Debug::SetLogPriority(Debug::INFO);
        		
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
					$this->write_log("MQTT can't connect");
					
					return;
				}
	
				$this->write_log("phpMQTT connected");
				$this->mqtt = $mqtt;
				
				$topics[$this->getOption("MQTT_TopicFilter")] = 1;
				$callback = new MySubscribeCallback($this);
				$mqtt->setHandler($callback);
				$mqtt->subscribe($topics);
						
			
			
				
				while(($this->mqtt) && (microtime(true)-$gmt_time<$recycle_secs) && $mqtt->loop()) {
					set_time_limit(0);
				}
				
				$this->write_log("disconnecting");
				$mqtt->disconnect();
			}
			
		}
		
		catch (Exception $e) {
				$this->write_log($e->getMessage());
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
						$this->write_log($ee->getMessage());
					}
				}
		}
	}	
	
    public function errorHandler($error_level, $error_message, $error_file, $error_line, $error_context)
    {
        $error = "lvl: " . $error_level . " | msg:" . $error_message . " | file:" . $error_file . " | ln:" . $error_line;

	$this->write_log($error);
    }

    public function shutdownHandler() //will be called when php script ends.
    {
         $lasterror = error_get_last();
	 $error = "[SHUTDOWN] lvl:" . $lasterror['type'] . " | msg:" . $lasterror['message'] . " | file:" . $lasterror['file'] . " | ln:" . $lasterror['line'];            
         $this->write_log($error, "fatal");
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
    	
    	//http://mqttcogs.sailresults.org/wp-admin/admin-ajax.php?action=doSet&topic=tests/blog/publishingdata&qos=0&retained=0&minrole=Subscriber&wpn=c49abe7f33&payload=15
    	
    	if (!check_ajax_referer($action, 'wpn')) {
    		die();
    	}
    	
    	//check we are able to do role according to value in shortcode
    	if (!$this->isUserRoleEqualOrBetterThan($minrole)) {
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
    		$this->write_log("doSet: MQTT can't connect");
    		$json->status = 'error';
    		$error_1 = new stdClass();
    		$error_1->reason='mqtt connection failure';
    		$error_1->message = '';
    		$json->errors = array();
    		$json->errors.push($error_1); 		
    	}
    	
    	$this->write_log("doSet: MQTT connected");
    	
    	
    	// Don't let IE cache this request
    	header("Pragma: no-cache");
    	header("Cache-Control: no-cache, must-revalidate");
    	header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    	header("Content-type: application/json");
    	
    	if (!$mqtt->publish_sync($topic, $payload, $qos, $retained)) {
    		$this->write_log("doSet: MQTT publish failure");
    		$json->status = 'error';
    		$error_1 = new stdClass();
    		$error_1->reason='mqtt publish failure';
    		$error_1->message = '';
    		$json->errors = array();
    		$json->errors.push($error_1);
    	}
    	
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
	    
	    //echo 'hello'.$_GET['from'].'hello';
	    $table = $this->getTopN($_GET['from'], $_GET['to'], $_GET['limit'], $_GET['topics'], $_GET['order']);    
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
				'minrole'=>'Administrator'
		], $atts, NULL);
	
		$currentvalue = $this->doGet($atts);
		$id =uniqid();
		$class = $atts['class'];
		$topic = $atts['topic'];
		$qos = $atts['qos'];
		$retained = $atts['retained'];
		$minrole = $atts['minrole'];
		
		if (!$this->isUserRoleEqualOrBetterThan($atts['minrole'])) {
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
					
	  					jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
	  					
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
	                                     'limit' => '100',
	                                     'topics' => '#',
	                                     'from'=>'',
	                                     'to'=>'',
	  									 'order'=>'DESC'
	                                 ], $atts, NULL);

		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],$atts['limit'],$atts['topics'],$atts['order']);	
		$jsonret = json_encode($table);   
	   $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	   $jsonret = str_replace('DEND"', '', $jsonret);
	    
	   return $jsonret;		
	}
	
	public function getTopN($from, $to, $limit, $topics, $order = 'ASC') {
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
	    		
	    $topics = explode(',',$topics);
	    
	    //prevent sql injection here
	    if ($order != 'ASC') {
	    	$order = 'DESC';
	    }
	    
	    $index=0;
	    $json = new stdClass(); 
	    $json->cols = array();
	    $json->rows = array();
	    //add the datetime column
	    $json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"datetime"}');
	    foreach($topics as $topic) {
	    	
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
	    
	   return $json;    
	 
	   
	}

	public function ajax_data($atts,$content) {
		if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
		}
		
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                                     'limit' => '100',
	                                     'topics' => '',
	                                     'action' => 'doGetTopN',
	                                     'from'=>'',
	                                     'to'=>'',
	  									 'order'=>'DESC'
	                                 ], $atts, NULL);
	  $limit = $atts['limit'];
	  $topics = $atts['topics'];
	 
	  return $this->getAjaxUrl($atts['action'].'&limit='.$limit.'&topics='.$topics.'&from='.$atts["from"].'&to='.$atts["to"].'&order='.$atts["order"]);                               
	                                 
	} 

	public function doDrawGoogle($atts,$content) {
 	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
 
	  $atts = shortcode_atts([
	                                     'charttype' => 'LineChart',
	                                     'options' => '{"width":400,"height":300}',
					     'ajax'=>'false',
					     'refresh_secs'=>60
	                                 ], $atts, NULL);
	
	$id = uniqid();
        $options = $atts["options"];
        $charttype = $atts["charttype"];
	$refresh_secs = $atts["refresh_secs"];
	if ($atts["ajax"]=='false') {
		$script = '
	 <div id="'.$id.'">
	 <script type="text/javascript">
	    '.$this->getGoogleLoadJS($charttype).'
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
		'.$this->getGoogleLoadJS($charttype).'
	     
	      google.charts.setOnLoadCallback(drawChart'.$id.');
	            
	    
	      function drawChart'.$id.'() {
		      var query = new google.visualization.Query("'.$querystring.'");
		      query.send(handleResponse'.$id.');
		      if ('.$refresh_secs.'>0) {
		      	query.setRefreshInterval('.$refresh_secs.');
		      }
	      }
	      
	      function handleResponse'.$id.'(response) {
		if (response.isError()) {
    			alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());
     			return;
	        }

	   	var data = response.getDataTable();	   
	        var chart = new google.visualization.'.$charttype.'(document.getElementById("'.$id.'"));
	        chart.draw(data, '.$options.');
	      }
	    </script></div>';
    	return $script;	
	
	}
	}

	function getGoogleLoadJS($charttype) {
		return 	 'google.charts.load("current", {"packages":["corechart","bar","table"]});';

	}
}


class MySubscribeCallback extends MessageHandler
{
	private $mqttcogs_plugin;
	
	public function __construct($theownerobject)
	{
		$this->mqttcogs_plugin = $theownerobject;
		$this->mqttcogs_plugin->write_log('constructed handler');
	}
			
	public function publish($mqtt, $publish_object)
	{
		global $wpdb;
		try
		{
			$this->mqttcogs_plugin->write_log('message received');
			$this->mqttcogs_plugin->write_log( $publish_object->getTopic());
			$this->mqttcogs_plugin->write_log( $publish_object->getMessage());
		
			$tableName = $this->mqttcogs_plugin->prefixTableName('data');
			$utc = current_time( 'mysql', true );
		
			apply_filters('mqttcogs_msg_in_pre',$publish_object->getMessage() , $utc, $publish_object->getTopic());
		
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
					
			apply_filters('mqttcogs_msg_in_pre',$publish_object->getMessage() , $utc, $publish_object->getTopic());
		}
		catch (Exception $e) {
			$this->mqttcogs_plugin->write_log($e->getMessage());
				//force loop to exit
			$this->mqttcogs_plugin->mqtt = null;
			
			//attempt graceful disconnect
			$mqtt->disconnect();
		}
	}
}

