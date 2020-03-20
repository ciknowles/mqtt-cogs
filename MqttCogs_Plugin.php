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
		
		
		if (! wp_next_scheduled ($this->prefixTableName('watchdog'))) {
			wp_schedule_event(time(), '1min', $this->prefixTableName('watchdog'));
	    }

	    if (! wp_next_scheduled ($this->prefixTableName('prune') )) {
			wp_schedule_event(time(), 'daily',$this->prefixTableName('prune'));
	    }
    }
    
    //called when the plugin is deactivated
    public function deactivate() {
		wp_clear_scheduled_hook($this->prefixTableName('watchdog'));
		wp_clear_scheduled_hook($this->prefixTableName('prune'));
				
		try {
		      $file = './'.$this->prefixTableName('lock.pid');	
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
        	'MQTT_WriteAccessRole' => $writeroles,
			'MQTT_GVisOptions' => array(__('Google Visualization Global Options', 'mqttcogs')),
			'MQTT_LeafOptions' => array(__('Leaflet Visualization Global Options', 'mqttcogs')),
			
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
                else {
                    switch($key) {
                        case 'MQTT_GVisOptions':
                            $this->addOption($key,"{hAxis:{titleTextStyle:{color:'#607d8b'},textStyle:{color:'#b0bec5'}},vAxis:{gridlines:{color:'#37474f'},baselineColor:'transparent'},legend:{position:'top',alignment:'center',textStyle:{color:'#607d8b'}},colors:['#3f51b5','#2196f3','#03a9f4','#00bcd4','#009688','#4caf50','#8bc34a','#cddc39'],areaOpacity:0.24,lineWidth:1,backgroundColor:'transparent',pieSliceBorderColor:'#263238',pieSliceTextStyle:{color:'#607d8b'},pieHole:0.9,bar:{groupWidth:'40'},colorAxis:{colors:['#3f51b5','#2196f3','#03a9f4','#00bcd4']},backgroundColor:'transparent',datalessRegionColor:'#37474f',displayMode:'regions', cssClassNames:{'headerRow': 'cssHeaderRow','tableRow': 'cssTableRow','oddTableRow':'cssOddTableRow','selectedTableRow': 'cssSelectedTableRow','hoverTableRow': 'cssHoverTableRow','headerCell': 'cssHeaderCell','tableCell': 'cssTableCell','rowNumberCell': 'cssRowNumberCell'}}" );
                        break;
                        
                    }
                    
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
	
	
        //add_action( 'admin_enqueue_scripts',  array(&$this, 'enqueueStylesAndScripts_admin'));
        //
	
	    //makes sure mqtt reader is active
	    add_action($this->prefixTableName('watchdog'), array(&$this, 'do_mqtt_watchdog'));
	    
	    //prunes the database
	    add_action($this->prefixTableName('prune'), array(&$this, 'do_mqtt_prune'));


        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        fwp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));

		    
	
        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39
	 
    	 add_shortcode('mqttcogs_drawgoogle', array($this, 'shortcodeDrawGoogle'));
    	 
		 add_shortcode('mqttcogs_drawleaflet', array($this, 'shortcodeDrawLeaflet'));
		 
		 add_shortcode('mqttcogs_drawhtml', array($this, 'shortcodeDrawHTML'));
		 
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
		
		
		add_action('wp_ajax_doGetDefaultSPContent', array(&$this, 'ajaxACTION_doGetDefaultSPContent'));
		
		//experimental
		add_action( 'init',array(&$this, 'cptui_register_my_cpts_things' ));
		
		add_action( 'add_meta_boxes', array(&$this, 'mqttcogs_custom_meta' ));
		
		add_action( 'save_post',array(&$this,  'mqttcogs_meta_save'), 10, 3 );
		
		add_action( 'thingtypes_edit_form', array(&$this, 'thetypes_add_custom_fields_to_taxonomy_edit_page'), 10, 2 );
		add_action( 'thingtypes_add_form', array(&$this, 'thetypes_add_custom_fields_to_taxonomy_add_page'), 10, 1 );
		
		add_action( 'load-edit-tags.php', array(&$this, 'gettext_init' ));
		
	    add_filter('manage_edit-thingtypes_columns', function ( $columns ) 
            {
                if( isset( $columns['slug'] ) )
                    unset( $columns['slug'] );   
            
                return $columns;
            } );
		
//		apply_filters( "manage_{$post_type}_posts_columns", string[] $post_columns )

		add_filter('manage_thing_posts_columns',  array(&$this, 'thing_posts_column_headers' ),10,1);
		
		add_action( 'manage_thing_posts_custom_column', array(&$this, 'thing_posts_column_content'), 10, 2 );
   	}
   	

    function thing_posts_column_content( $column_name, $post_id ) {

        if ($column_name == 'type') {
            $tax_id = get_post_meta( $post_id, 'meta-type', true );
            $term = get_term_by( 'term_taxonomy_id', $tax_id, 'thingtypes');
            echo  $term->name;
        }
    }

   	
      
    function thing_posts_column_headers( $columns ) {
        if( isset( $columns['taxonomy-thingtypes'] ) )
            unset( $columns['taxonomy-thingtypes'] ); 
            
         $columns['type'] = __("Type", "mqttcogs-textdomain");
         
  
        $customOrder = array('cb', 'title', 'type', 'date');

          # return a new column array to wordpress.
          # order is the exactly like you set in $customOrder.
          foreach ($customOrder as $colname)
            $new[$colname] = $columns[$colname];    
          return $new;
    }
       
   function gettext_init() {
		add_filter('gettext', function ($translated, $original, $domain) {return $this->edittags_gettext($translated, $original, $domain);},10,3);
        add_filter('gettext_with_context', function($translated, $text, $context, $domain)  {return $this->edittags_gettext($translated, $text, $domain);},10,4);
   }
   
   function edittags_gettext( $translated, $original, $domain ) {
       if ($domain==='mqttcogs-textdomain') {
           return $translated;
       }
       
       switch($original) {
           case 'Description':
               $translated = __('Template', 'mqttcogs-textdomain');
               break;
       }
        return $translated;
   }

   	function thetypes_add_custom_fields_to_taxonomy_edit_page($tag, $taxonomy) {
   	     ?><style>
   	     .term-slug-wrap{display:none;}
   	     </style><?php
   	 ?>
   	 <?php
   	}
   	function thetypes_add_custom_fields_to_taxonomy_add_page($taxonomy) {
   	     ?><style>
   	     .term-slug-wrap{display:none;}
   	     </style><?php
   	 ?>
   	 <?php
   	}
   	
   	
	//NOT USED 
	function load_single_template( $template ) {    
		global $post;

		if ( 'thing' === $post->post_type && locate_template( array( 'single-thing.php' ) ) !== $template ) {
			/*
			 * This is a 'movie' post
			 * AND a 'single movie template' is not found on
			 * theme or child theme directories, so load it
			 * from our plugin directory.
			 */
			return plugin_dir_path( __FILE__ ) . 'single-thing.php';
		}
		return $template;
	}


	/* Adds a meta box to the post editing screen*/
	function mqttcogs_custom_meta() {
	    
		add_meta_box( 'mqttcogs_meta', __( 'Thing Properties', 'mqttcogs-textdomain' ),  array(&$this, 'mqttcogs_meta_callback'), 'thing','advanced' );
	}
	
	/**
	 * Outputs the content of the meta box
	 */
	function mqttcogs_meta_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'mqttcogs_nonce' );

	
		$mqttcogs_stored_meta = get_post_meta( $post->ID );
		
		if (!isset ( $mqttcogs_stored_meta['meta-type']))
		    $mqttcogs_stored_meta['meta-type'] = array(0);
		
		//array_merge
		
		$checked = 'checked="checked"';
		?>
	 
	 <table class="form-table" role="presentation">
		<tbody>		
		<tr>
			<th scope="row"><label for="meta-topic" class="mqttcogs-row-title"><?php _e( 'MQTT Topic', 'mqttcogs-textdomain' )?></label></th>
			<td><input class="regular-text" type="text" name="meta-topic" id="meta-topic" value="<?php if ( isset ( $mqttcogs_stored_meta['meta-topic'] ) ) echo $mqttcogs_stored_meta['meta-topic'][0]; ?>" />
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="meta-lnglat" class="mqttcogs-row-title"><?php _e( 'Longitude,Latitude', 'mqttcogs-textdomain' )?></label></th>
			<td>	<input class="regular-text" type="text" name="meta-lnglat" id="meta-lnglat" value="<?php if ( isset ( $mqttcogs_stored_meta['meta-lnglat'] ) ) echo $mqttcogs_stored_meta['meta-lnglat'][0]; ?>" />
			</td>
		</tr>
		
		<tr>
		<th scope="row"><label for="meta-type" class="mqttcogs-row-title"><?php _e( 'Type', 'mqttcogs-textdomain' )?></label></th>
	    <td>
        <select name="meta-type" id="meta-type">
            <option value="0" <?php if ( isset ( $mqttcogs_stored_meta['meta-type'] ))  selected( $mqttcogs_stored_meta['meta-type'][0], 0); ?>><?php _e('None', 'mqttcogs-textdomain' ) ?></option>
            <?php 
             $tax_terms = get_terms('thingtypes', array('hide_empty' => '0')); 
            foreach ($tax_terms as $thingtypes): ?>
            <option value="<?php echo $thingtypes->term_id; ?>" <?php if ( isset ( $mqttcogs_stored_meta['meta-type'] ) )  selected( $mqttcogs_stored_meta['meta-type'][0], strval($thingtypes->term_id) ); ?>><?php _e($thingtypes->name, 'mqttcogs-textdomain' ) ?></option>
            <?php endforeach; ?>
            
        </select>
        </td>
        </tr>

     
		
		<tr>
		<th scope="row"><label for="meta-defaultcontent" class="mqttcogs-row-title"><?php _e( 'Page Content', 'mqttcogs-textdomain' )?></label></th>
			<td><input class="regular-button" type="button" name="meta-defaultcontent" id="meta-defaultcontent" value="Generate" 
			onclick="(function() {
			    	wp.ajax.post('doGetDefaultSPContent',{postid:jQuery('#post_ID').val(), topic:jQuery('#meta-topic').val(), type:jQuery('#meta-type').val() })
                      .done(function(response) {
                      
                       // wp.data.dispatch( 'core/editor' ).resetBlocks([]);
            			var block = wp.blocks.createBlock( 'core/paragraph' );
            			block.attributes.content = response.data;
            			wp.data.dispatch( 'core/editor' ).insertBlocks( block );
                });
			})();"/><span><small><?php _e( '(You can <strong>replace</strong> the topic with page slug!)', 'mqttcogs-textdomain' )?></small></span>
			</td>
		</tr>
		
		
		<tr>
			<th scope="row"><label for="meta-notes" class="mqttcogs-row-title"><?php _e( 'Notes', 'mqttcogs-textdomain' )?></label></th>
			<td><?php
				if (isset($mqttcogs_stored_meta['meta-notes'])) {
					$meta_content = $mqttcogs_stored_meta['meta-notes'][0];
				}
				else {
					$meta_content = '';
				}
				$meta_content = wpautop($meta_content,true);
				$meta_content = stripslashes( wp_kses_decode_entities( $meta_content ));
				wp_editor($meta_content, 'meta_content_editor', array(
                        'wpautop'               =>  true,
                        'media_buttons' =>      false,
                        'textarea_name' =>      'meta-notes',
                        'textarea_rows' =>      10,
                        'teeny'                 =>  true
                ));
        ?>
			</td>
		</tr>
	 </tbody>
	 </table>
		<?php
	}

	/**
	 * Saves the custom meta input
	 */
	function mqttcogs_meta_save( $post_id, $post, $update ) {
	    
		// Checks save status
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST[ 'mqttcogs_nonce' ] ) && wp_verify_nonce( $_POST[ 'mqttcogs_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
	 
		// Exits script depending on save status
		if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
			return;
		}
	 
	  // Check permissions
		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;
	
		// Checks for input and sanitizes/saves if needed
		if( isset( $_POST[ 'meta-topic' ] ) ) {
			update_post_meta( $post_id, 'meta-topic', sanitize_text_field( $_POST[ 'meta-topic' ] ) );
		}
		
		if( isset( $_POST[ 'meta-type' ] ) ) {
			update_post_meta( $post_id, 'meta-type', sanitize_text_field( $_POST[ 'meta-type' ] ) );
		}
		
		if( isset( $_POST[ 'meta-lnglat' ] ) ) {
			update_post_meta( $post_id, 'meta-lnglat', sanitize_text_field( $_POST[ 'meta-lnglat' ] ) );
		}
		
		
		if( isset( $_POST[ 'meta-notes' ] ) ) {
			update_post_meta( $post_id, 'meta-notes', esc_attr( $_POST[ 'meta-notes' ] ) );
		}
		
		
		if( isset( $_POST[ 'meta-defaultcontent' ] ) ) {
			update_post_meta( $post_id, 'meta-defaultcontent', 'true');
		}
		else {
		    delete_post_meta( $post_id, 'meta-defaultcontent');
		}
	}


    function mqttcogs_content_save_pre($content) {
        if( isset( $_POST[ 'meta-defaultcontent' ] ) ) {
            return  "TEST";
        }
    }

	public function cptui_register_my_cpts_things() {
	
		
		$labels = array(
        'name' => _x( 'Thing Types', 'taxonomy general name' ),
        'singular_name' => _x( 'Thing Type', 'taxonomy singular name' ),
        'search_items' =>  __( 'Search Types' ),
        'all_items' => __( 'All Types' ),
        'parent_item' => __( 'Parent Type' ),
        'parent_item_colon' => __( 'Parent Type:' ),
        'edit_item' => __( 'Edit Type' ), 
        'update_item' => __( 'Update Type' ),
        'add_new_item' => __( 'Add New Type' ),
        'new_item_name' => __( 'New Type Name' ),
        'menu_name' => __( 'Types' ),
      ); 	
 
        register_taxonomy('thingtypes',array('thing'), array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'public'=> true,
            'show_in_nav_menus' => true
            )
        );
      
      
      
      
      
      /*  'rewrite' => array( 'slug' => 'type' ),*/


    	$labels = array(
			"name" => __( "Things" ),
			"singular_name" => __( "Thing"),
		);

		$args = array(
			"label" => __( "Thing"),
			"labels" => $labels,
			"description" => "",
			"public" => true,
			"publicly_queryable" => true,
			"show_ui" => true,
			"delete_with_user" => false,
			"show_in_rest" => true,
			"rest_base" => "",
			"rest_controller_class" => "WP_REST_Posts_Controller",
			"has_archive" => false,
			"show_in_menu" => true,
			"show_in_nav_menus" => true,
			"exclude_from_search" => false,
			"capability_type" => "page",
			"map_meta_cap" => true,
			"hierarchical" => true,
		/*	"rewrite" => array( "slug" => "thing", "with_front" => true ),*/
			"query_var" => true,
			"supports" => array("title","editor","page-attributes", "thumbnail","mqttcogs_meta"),
  		   'taxonomies' => array('thingtypes')

		);
		register_post_type( "thing", $args );
		
		if (!term_exists('Line Chart','thingtypes')) {
		    wp_insert_term(//this should probably be an array, but I kept getting errors..
                'Line Chart', // the term 
                'thingtypes', // the taxonomy
                array(
                'slug' => 'linechart',
                'description' =>  '[mqttcogs_drawgoogle ajax="true" charttype="LineChart" options="{width: \'100%\', height: \'100%\'}"][mqttcogs_data limit="100" order="DESC" topics=""][/mqttcogs_drawgoogle]'
                ));
		}
		
		if (!term_exists('Leaflet Map Chart','thingtypes')) {
		    wp_insert_term(//this should probably be an array, but I kept getting errors..
                'Leaflet Map', // the term 
                'thingtypes', // the taxonomy
                array(
                'slug' => 'leafletmap',
                'description' =>  '[mqttcogs_drawleaflet refresh_secs="15" height="400px" options="{zoom:13}"][mqttcogs_data topics="" order="DESC" limit="100"][/mqttcogs_drawleaflet]',
                ));
		}
		
		if (!term_exists('DataTable','thingtypes')) {
		    wp_insert_term(//this should probably be an array, but I kept getting errors..
                'DataTable', // the term 
                'thingtypes', // the taxonomy
                array(
                'slug' => 'datatable',
                'description' =>  '[mqttcogs_drawdatatable options="{width: \'100%\'}"][mqttcogs_data  order="DESC" limit="10" topics=""][/mqttcogs_drawdatatable]'
                ));
		}
		
		
		if (!term_exists('SparkLine','thingtypes')) {
		    wp_insert_term(//this should probably be an array, but I kept getting errors..
                'SparkLine', // the term 
                'thingtypes', // the taxonomy
                array(
                'slug' => 'sparkline',
                'description' =>  '[mqttcogs_drawgoogle charttype="SparklineChart" options="{areaOpacity:0, height:80, backgroundColor:\'transparent\',vAxis:{viewWindowMode:\'pretty\'},lineWidth:2, animation:{startup:true},curveType: \'function\'}" ajax="true"][mqttcogs_data  order="DESC" limit="100" topics=""][/mqttcogs_drawgoogle]'
                ));
		}


		
 
		
    }
	
	/*

        function thing_meta_box( $post ) {
            $this->setupLogging();
              Debug::Log(DEBUG::INFO, 'thing_meta_box');
        	$terms = get_terms( 'thingtypes', array( 'hide_empty' => false ) );
        
        	$post  = get_post();
        	$types = wp_get_object_terms( $post->ID, 'thingtypes', array( 'orderby' => 'term_id', 'order' => 'ASC' ) );
        	$name  = '';
        
            if ( ! is_wp_error( $types ) ) {
            	if ( isset( $types[0] ) && isset( $types[0]->name ) ) {
        			$name = $types[0]->name;
        	    }
            }
        
        	foreach ( $terms as $term ) {
        ?>
        		<label title='<?php esc_attr_e( $term->name ); ?>'>
        		    <input type="radio" name="thingtype" value="<?php esc_attr_e( $term->name ); ?>" <?php checked( $term->name, $name ); ?>>
        			<span><?php esc_html_e( $term->name ); ?></span>
        		</label><br>
        <?php
            }
        }
*/
	
	
	function my_editor_content( $post_content, $post ) { 
		if (!empty($post_content)) {
			return $post_content;		
		}
		switch($post->post_type) {
			case 'thing':
				$post_content = $this->getDefaultContent($post);
			break;
		}	
		return $post_content;
	}


	public function enqueueStylesAndScripts() {
		
		wp_enqueue_style('mqttcogs_styles', plugins_url('/css/mqttcogs_styles.css', __FILE__));
		
		wp_register_script('moment', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment-with-locales.min.js');
						
		wp_register_script('google_loadecharts','https://www.gstatic.com/charts/loader.js' );
		wp_register_script('loadgoogle', plugins_url('/js/loadgoogle.js', __FILE__));
		wp_register_script('chartdrawer', plugins_url('/js/googlechartdrawer.js', __FILE__), array(), '2.3');
		
		wp_register_style('leafletcss', 'https://unpkg.com/leaflet@1.5.1/dist/leaflet.css');
		wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.5.1/dist/leaflet.js');
		wp_register_script('leafletdrawer', plugins_url('/js/leafletdrawer.js', __FILE__), array(), '2.3');

		wp_register_script('htmldrawer', plugins_url('/js/htmldrawer.js', __FILE__), array(), '2.3');
				
		wp_register_style('datatablescss', 'https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css');
		wp_register_script('datatables', 'https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js');
		wp_register_script('datatablesdrawer', plugins_url('/js/datatablesdrawer.js', __FILE__), array(), '2.3');		
	}
	
    /*		   	 
	public function enqueueStylesAndScripts_admin() {
		wp_register_script('mqttcogs_support', plugins_url('/js/support.js', __FILE__), array(), '2.21ssswssse2sse12');
		global $pagenow;
 
		wp_enqueue_script( 'mqttcogs_support');
	}*/

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
	}    

    //
	function do_mqtt_watchdog() {
		
	try {
		//if no clientid just return
		if (empty($this->getOption("MQTT_ClientID"))) {
			return;
		}
		
		$this->setupLogging();
	  $file = './'.$this->prefixTableName('lock.pid');	
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
		
		$rows = $this->getLastN('data', $key.'/'.$nodeid.'/'.$sensor.'/255/3/0/32', 1, 'DESC');
		if (count($rows)==1) {
			$utcunixdate = strtotime($rows[0]['utc']);
			$stayalive = intval($rows[0]['utc']);
			return (($time()>=$utcunixdate) && ($time()<$utcunixdate + $stayalive));
		}			
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
		return $this->sendMqttInternal($id, $topic, $payload, $qos, $retained, true, $result);
	}
	
	public function sendMqttInternal($id, $topic, $payload, $qos, $retained, $trybuffer, $result) {
	
		if ($trybuffer) {
			if (!$this->isNodeOnline($topic, $this->getOption('MQTT_MySensorsRxTopic', 'mysensors_out'))) {
				$this->bufferMessage($topic,$payload,$qos,$retained);	
				$json->status = 'buffered';
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
    	
    	//CK: CHANGE TO TOPIC
    	$topic = $_GET['topic'];

        $splitTopic = $this->splitTopic($topic);
		$topic = $splitTopic["topic"];
	
    	$qos = (int) $_GET['qos'];
    	$payload = $_GET['payload'];
    	$retained = (int) $_GET['retained'];
    	$minrole = $_GET['minrole'];
    	//$pattern = $_GET['pattern'];
    	$id =  $_GET['id'];
    
    	$action = "doSet&id=$id&topic=$topic&qos=$qos&retained=$retained&minrole=$minrole";

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
	    
	       
	    $table = $this->getTopN($_GET['from'], $_GET['to'], $_GET['limit'], $_GET['topics'],$_GET['aggregations'], $_GET['group'], $_GET['order']);    
	  
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
	
	public function ajaxACTION_doGetDefaultSPContent() {
	    $this->setupLogging();
	    $json = new stdClass();     
	    
	    $term = get_term_by( 'term_taxonomy_id', $_POST['type'], 'thingtypes');
	    $json->data = $term->description;
	    $json->data = str_replace('topics=""', 'topics="'.$_POST['topic'].'"',$json->data);
	    
	    // Don't let IE cache this request
	    header("Pragma: no-cache");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
	    header("Content-type: application/javascript");
	    wp_send_json_success($json);
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
				'input_value'=>'0',
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
		
		if (!$currentvalue) {
			$currentvalue = $atts['input_value'];
		}
		
		$id =$atts['id'];
		$class = $atts['class'];
		$topic =  $this->replaceWordpressUser($atts['topic']);
	
		$qos = $atts['qos'];
		$retained = $atts['retained'];
		$minrole = $atts['minrole'];
		
		if (($atts['minrole']!='') && (!$this->isUserRoleEqualOrBetterThan($atts['minrole']))) {
			return $atts['restrictedtext'];
		}
		
		$label = $atts['label_text'];
		
		$input_title = $atts['input_title']==''?"":"title='".$atts['input_title']."'";
		$input_pattern = $atts['input_pattern']==''?"":"pattern='".$atts['input_pattern']."'";
		$input_type = ($atts['input_type']=='')?"":"type='".$atts['input_type']."'";
		$input_min = $atts['input_min']==''?"":"min='".$atts['input_min']."'";
		$input_max = $atts['input_max']==''?"":"max='".$atts['input_max']."'";
		$input_step = $atts['input_step']==''?"":"step='".$atts['input_step']."'";
		
		$action = "doSet&id=$id&topic=$topic&qos=$qos&retained=$retained&minrole=$minrole";
		$wpn = wp_create_nonce($action);
		$action = "$action&wpn=$wpn&payload=";
	
		$url = $this->getAjaxUrl($action);
		
		//case where there is no last value
		if (!$currentvalue) {
			$currentvalue= $atts['input_value'];

			//if it looks integer then make it integer
			if (is_numeric($currentvalue)) {
				$currentvalue = $currentvalue+0;
			}			
			//else it is a string
		}
		
		
		//TODO: how to notify user that update was successful
		$checked = '';
		if (($input_type=="type='checkbox'") && (($currentvalue>0) || (strcmp($currentvalue,'true')==0) )) {
			$checked = 'checked';
		}
		
		$script = "
	 	<div id='$id' class='$class'>
	 		<input id='input_$id' value='$currentvalue' $input_type $input_title $input_pattern $input_min $input_max $input_step onchange='setMQTTData$id()' $checked>
			<label for='input_$id'>$label<span class='sw'></span></label>	
			
	 	 	<script type='text/javascript'>
	    		
	      	function setMQTTData$id() {
	      		
	      		if (jQuery('#input_$id')[0].checkValidity()) { 				
					var val = (document.getElementById('input_$id')).value;
					
					//if it is a checkbox we decide how to proceed here
					if (document.getElementById('input_$id').type=='checkbox') {
						if (isNaN(val)) {
							val = document.getElementById('input_$id').checked?'true':'false';
						}
						else {
							val = document.getElementById('input_$id').checked?1:0;
						}
					}
					
					jQuery.get('$url' + val,function( data ) {
					
					  	switch(data.status) {
								case 'ok':
									//jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
									break;
								
								case 'buffered':
//									jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10004').text());
									break;
								
								default:
//									jQuery('#mqttcogs_set_btn_$id' ).val(jQuery('<div>').html('&#10060').text());
									break;
						}
	  					
	  					//location.reload();
						})
					  .fail(function(data) {
					    alert( 'error' + data);
					  })
					  .always(function() {
	//				    jQuery('#mqttcogs_set_btn_$id').prop('disabled', false);
					    
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
				if ($atts['dateformat'] === 'human_time_diff') {
					return human_time_diff((int) $ret/1000,  current_time( 'timestamp' ));
				}
				else {
					return  date_i18n($atts['dateformat'],  ((int) $ret)/1000, $local);
				}
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
	  									 'order'=>'DESC'
	                                 ], $atts, NULL);

		//whattypes of queries
		$table = $this->getTopN($atts['from'],$atts['to'],$atts['limit'],$atts['topics'],$atts['aggregations'], $atts['group'],$atts['order']);	
		$jsonret = json_encode($table);   
	    $jsonret = str_replace('"DSTART', 'new Date', $jsonret);
	    $jsonret = str_replace('DEND"', '', $jsonret);
	    
	   return $jsonret;		
	}
	
	public function getLastN($table, $topic, $limit, $order) {
	    global $wpdb;
	    $table_name = $this->prefixTableName($table);	
	    
	    $post = $this->getPostBySlug($topic);
	
    	$splitTopic = $this->splitTopic($topic);
		$payloadfield = $splitTopic["topic_sql"];
		
	    
		//$topic = $this->replaceWordpressUser($topic);
		//add the next column definition
		//$payloadfield = $this->getPayloadSQL($topic);
		
		$sql = $wpdb->prepare("SELECT `id`, `utc`,`topic`, $payloadfield, `qos`,`retain` from $table_name
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
	
	public function getTopN($from, $to, $limit, $topics, $aggregations = '', $group='', $order = 'ASC') {
	    global $wpdb;
	    $table_name = $this->prefixTableName('data');	
	  
    	
	    if (is_numeric($from)) {      
	    	$from = time() + floatval($from)*86400;
	    	$from = date('Y-m-d H:i:s', $from);
	    //	$from = CONVERT_TZ($from,get_option('timezone_string'),'GMT');
	    }
	    
	    if (is_numeric($to)) {
	    	$to = time() + floatval($to)*86400;
	    	$to = date('Y-m-d H:i:s', $to);
	    //	$from = CONVERT_TZ($from,get_option('timezone_string'),'GMT');
	    }
	
	    $topics = explode(',',$topics);
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
		//	if (empty($group)) {
				$json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"datetime"}');
		//	}
		//	else {
		//		$json->cols[] = json_decode('{"id":"utc", "label":"utc", "type":"number"}');
		//	}
			
			$tzs = get_option('timezone_string');

    	    foreach($topics as $idx=>$fulltopic) {
				$post = $this->getPostBySlug($fulltopic);
	
            	$splitTopic = $this->splitTopic($fulltopic);
        		$payloadfield = $splitTopic["topic_sql"];
				
				$agg = ($idx<count($aggregations))?"$aggregations[$idx]($payloadfield) as payload":"$payloadfield as payload";
				
							
				if (empty($group)) {
					$sql = $wpdb->prepare("SELECT `utc` as dtm,$agg from $table_name
    	    	 						WHERE topic=%s 
    	    	 						AND ((utc>=%s OR %s='') 
    	    	 						AND (utc<=%s OR %s='')) 
    	    	 						AND $payloadfield IS NOT NULL
    	    	 						order by utc $order limit %d",
    	    	 						
    	    	 						$splitTopic["topic_core"],
    	    	 						$from,
    	    	 						$from,
    	    	 						$to,
    	    	 						$to,
    	    	 						$limit			
    	    	 				        );
    	    	 
				} 
				else {
				    // DATE_ADD('1970-01-01 00:00:00', INTERVAL TIMESTAMPDIFF(HOUR, '1970-01-01 00:00:00', '2020-03-19 18:12:00') HOUR)
				    //EXTRACT($group FROM IFNULL(CONVERT_TZ(`utc`, 'GMT', %s), `utc`)) as grouping,$agg from $table_name
    	    	
					$sql = $wpdb->prepare("SELECT DATE_ADD('1970-01-01 00:00:00', INTERVAL TIMESTAMPDIFF($group, '1970-01-01 00:00:00', `utc`) $group) as dtm, $agg from $table_name
										WHERE topic=%s 
    	    	 						AND ((utc>=%s OR %s='') 
    	    	 						AND (utc<=%s OR %s='')) 
    	    	 						AND $payloadfield IS NOT NULL
										GROUP BY dtm
    	    	 						order by dtm $order limit %d",
    	    	 						$splitTopic["topic_core"],
    	    	 						$from,
    	    	 						$from,
    	    	 						$to,
    	    	 						$to,
    	    	 						$limit			
    	    	 				        );
				}
    	    	Debug::Log(DEBUG::DEBUG, $sql);
		
    	    	$therows =  $wpdb->get_results($sql, ARRAY_A );
				$therows = apply_filters('mqttcogs_shortcode_pre',$therows, $splitTopic["topic"]);

				$colset = false;
				
				foreach($therows as $row) {
					$o = new stdClass();
					$o->c = array();
					
		    		//add grouping
		//			if (empty($group)) {
						$o->c[] = json_decode('{"v":"DSTART('.(strtotime($row["dtm"])*1000).')DEND"}');
		//			}
		//			else {
		//				$o->c[] = json_decode('{"v":"DSTART('.(strtotime($row["grouping"])).')DEND"}');
		//			}
					
					//loop through columns
					for($i = 0; $i < count($topics); ++$i){
						if ($i==$index) {
							if (!$colset) {
			         		    
			         		    //add on lng,lat from associated post as column property
			         		    $p = "";
							    if (!is_null($post)) {
							        $lnglat = get_post_meta($post->ID, 'meta-lnglat', true);
							        $p = ',"p":';
							        if (!is_null($lnglat)) {
							            $p = $p.'{"lnglat":"'.$lnglat.'"}';
							        }
							    }
							    
								if (is_numeric($row['payload'])) {
									//add the next column definition
									$json->cols[] = json_decode('{"id":"'.$splitTopic["topic"].'","type":"number"'.$p.'}');	
								}
								else {
									//add the next column definition
									$json->cols[] = json_decode('{"id":"'.$splitTopic["topic"].'","type":"string"'.$p.'}');	
								}
								$colset = true;
							}
							if (is_numeric($row['payload'])) {
								$o->c[] = json_decode('{"v":'.$row['payload'].'}');   
							}
							else {
								$o->c[] = json_decode('{"v":'.$row['payload'].'}');   
							}
						}
						else {
							$o->c[] = json_decode('{"v":null}');  
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
	  									 'order'=>'DESC'
	                                 ], $atts, NULL);
	   $limit = $atts['limit'];
	   $topics = $atts['topics'];
	 
	   return $this->getAjaxUrl($atts['action'].'&limit='.$limit.'&topics='.$topics.'&from='.$atts["from"].'&to='.$atts["to"].'&aggregations='.$atts["aggregations"].'&group='.$atts["group"].'&order='.$atts["order"]);                                                              
	} 
	
	
	
	public function shortcodeDrawDataTable($atts, $content) {
		static $datatables = array();	
		$this->setupLogging();
		//we include google scripts here for JSON parsing and datatable support
		wp_enqueue_script('jquery');
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
		wp_enqueue_script('jquery');
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

		$script = '<div id="'.$id.'" style="height:'.$atts['height'].'">';
	 
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
	
	
	public function shortcodeDrawHTML($atts,$content) {
      $this->setupLogging();
	  
	  if (!$this->canUserDoRoleOption('MQTT_ReadAccessRole')) {
			return '';
	  }
		
	  static $htmls = array();	
	 
	  //only include google stuff for this shortcode
	  wp_enqueue_script('jquery');
	  wp_enqueue_script('google_loadecharts');
	  wp_enqueue_script('loadgoogle');
			
 	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
  	
	  $atts = shortcode_atts([
				'limit' => '1',
				'topics' => '#',
				'from'=>'',
				'to'=>'',
				'aggregations' => '',
				'group' => '',
				'order'=>'DESC',
				'local'=>'false',
				'refresh_secs'=>0,
				'script' => '',
				'dateformat' => '',
				'action' => 'doGetTopN'
		], $atts, NULL);
		
		$id = uniqid();
		$refresh_secs = $atts["refresh_secs"];
		$prescript='';

		if ($atts["script"]!=='') {
				global $post;
				$prescript = get_post_meta($post->ID, $atts["script"], true);
				$prescript = str_replace(array('\r', '\n', '\t'), '', trim($prescript));	
			}
	
		wp_enqueue_script('moment');
  		
		wp_enqueue_script('htmldrawer');
  	
		global $wp_scripts;
	 
	   $limit = $atts['limit'];
	   $topics = $atts['topics'];
	 
	   $querystring = $this->getAjaxUrl($atts['action'].'&limit='.$limit.'&topics='.$topics.'&from='.$atts["from"].'&to='.$atts["to"].'&aggregations='.$atts["aggregations"].'&group='.$atts["group"].'&order='.$atts["order"]);
	   
		$script = '
		 <div id="'.$id.'"></div>';
		 
		 $htmls[] = array(
			 "id"=>$id,
			 "refresh_secs"=>$refresh_secs,
			 "querystring"=>$querystring,
			 "content"=>$content,
			 "dateformat"=>$atts['dateformat'],
			 "script"=>$prescript
			);
			
			$wp_scripts->add_data('htmldrawer', 'data', '');
			wp_localize_script( 'htmldrawer', 'allhtmls', $htmls );
			return $script;	
	}


	public function shortcodeDrawGoogle($atts,$content) {
      $this->setupLogging();
	
	  static $graphs = array();	
	 
	  //only include google stuff for this shortcode
	  wp_enqueue_script('jquery');
	  wp_enqueue_script('google_loadecharts');
	  wp_enqueue_script('loadgoogle');
			
 	  $atts = array_change_key_case((array)$atts, CASE_LOWER);
  	//	Debug::Log(DEBUG::INFO, "Attrs {$atts['charttype']}");
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
  	     "globaloptions"=> wp_unslash($this->getOption('MQTT_GVisOptions', '{}')),
  	     "options"=> $options,
  	     "charttype"=>$charttype,
  	     "querystring"=>$querystring,
		 "script"=>$prescript
  	    );
  	    
        $wp_scripts->add_data('chartdrawer', 'data', '');
    //	wp_localize_script( 'chartdrawer', 'globaloptions',array('globaloptions'=>$this->getOption('MQTT_GVisOptions', '{}')));
    	wp_localize_script( 'chartdrawer', 'allcharts', $graphs );
    	return $script;	
	}

	function getGoogleLoadJS($charttype) {
		return 	 'google.charts.load("current", {"packages":["imagesparkline","corechart","bar","table","gauge","map"]});';

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

	public function getPostBySlug($slug) {
		$found_post = null;
        $found_post = get_page_by_path($slug, 'OBJECT', 'thing');
		return $found_post;
	}
	
	public function splitTopic($topicorslug) {
	    $ret = array(
	        "topic"=>"",
	        "topic_core"=>"",
	        //"topic_json"=>NULL,
	        "topic_sql"=>'`payload`'
	    );
	    
	    //return the topic from the shortcode OR referenced thing
	    $post = $this->getPostBySlug($topicorslug);
		if (!is_null($post)) {
			$topicorslug = get_post_meta( $post->ID, 'meta-topic', true );
		}	
		
		//replacewordpress user (To remove I think)
		$topicorslug = $this->replaceWordpressUser($topicorslug);
		$ret["topic"] = $topicorslug;
		
		$found = strpos($topicorslug, '$');
	    if ($found === FALSE) {
	        $ret["topic_core"]=$topicorslug;
	    }
	    else {
	        $json_extract =  substr($topicorslug, $found);
	        $ret["topic_core"]= substr($topicorslug, 0, $found);
	       // $ret["topic_right"]= $json_extract;
	        $ret["topic_sql"] = "JSON_EXTRACT(`payload`, '$json_extract')";
	    }
	    return $ret;
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
			
			$publish_objectarr = apply_filters('mqttcogs_msg_in_pre',$publish_object);
			
			if (!isset($publish_objectarr)) {
				return;
			}

			if (!is_array($publish_objectarr)) {
				$publish_objectarr = array($publish_objectarr);
			}
			foreach($publish_objectarr as $publish_object) {

				$utc = date_format($publish_object->getDateTime(), 'Y-m-d H:i:s');

				//deal with smartsleep nodes
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
