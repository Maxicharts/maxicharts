<?php
/*
 * Plugin Name: MaxiCharts
 * Plugin URI: https://maxicharts.com/
 * Description: Create beautiful interactive HTML5 charts from Gravity Forms / CSV into your posts with a simple shortcode. Uses chart.js.
 * Version: 1.7.6
 * Author: MaxiCharts
 * Author URI: https://profiles.wordpress.org/maxicharts
 * Text Domain: maxicharts
 * Domain Path: /lang
 */
if (! defined('ABSPATH')) {
    exit();
}

define('MAXICHARTS_URL', trailingslashit(plugins_url('', __FILE__)));
define('MAXICHARTS_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('MAXICHARTS_BASENAME', plugin_basename(__FILE__));
define('MAXICHARTS_CHARTJS_VERSION', '2.7.2');

require_once __DIR__ . "/mcharts_utils.php";
require_once __DIR__ . '/libs/vendor/autoload.php';

require_once __DIR__ . '/mcharts_data_conversion_plugin.php';
require_once __DIR__ . '/mcharts_custom_criteria_plugin.php';
require_once __DIR__ . '/mcharts_table_plugin.php';

require_once (sprintf("%s/mcharts_settings.php", dirname(__FILE__)));

use Maxicharts\Maxicharts_SettingsPage;
// use Maxicharts\MAXICHARTSAPI;

// fix for PHP versions < 5.5
if (! function_exists('boolval')) {

    function boolval($var)
    {
        return ! ! $var;
    }
}

if (! class_exists('maxicharts_reports')) {

    class maxicharts_reports
    {

        protected $maxicharts_settings = null;
        private static $license_key = null;
        //private static $log_level = 'warn';
        protected static $instance = null;

        protected $criteria_module = NULL;

        protected $table_module = NULL;

        protected static $availableColorSets = NULL;

        // protected static $doff_logger = null;
        function mce_button()
        {
            // check user permissions
            if (! current_user_can('edit_posts') && ! current_user_can('edit_pages')) {
                return;
            }
            // check if WYSIWYG is enabled
            if ('true' == get_user_option('rich_editing')) {
                /*
                 * add_filter( 'mce_external_plugins', array( $this, 'add_mce_plugin' ) );
                 * add_filter( 'mce_buttons', array( $this, 'register_mce_button' ) );
                 */
                MAXICHARTSAPI::getLogger()->trace("Adding TinyMCE filters...");
                add_filter("mce_external_plugins", array(
                    $this,
                    "enqueue_tinymce_scripts"
                ));
                add_filter("mce_buttons", array(
                    $this,
                    "register_buttons_editor"
                ));
            }
        }

        function maxicharts_load_textdomain()
        {
            load_plugin_textdomain('maxicharts', false, dirname(plugin_basename(__FILE__)) . '/lang/');
        }

        /*
         * function __construct() {
         * // maxicharts_log("Construct " . __CLASS__);
         * MAXICHARTSAPI::getLogger ()->debug ( "Construct " . __CLASS__ );
         *
         * }
         */
        public function __construct($file)
        {
            if (static::$instance !== null) {
                $ex = new Exception();
                MAXICHARTSAPI::getLogger()->fatal("Construct " . __CLASS__, $ex);
                throw $ex;
            }
            MAXICHARTSAPI::getLogger()->debug("Construct " . __CLASS__);
            static::$instance = $this;
            static::$instance->maxicharts_init();
        }

        public function boot()
        {}

        public static function get()
        {
            return static::$instance;
        }

        function init_hooks()
        {
            add_action("wp_enqueue_scripts", array(
                $this,
                "maxicharts_enqueue_scripts"
            ));
            
            add_action('wp_head', array(
                $this,
                'maxicharts_reports_html5_support'
            ));
            
            if (! is_admin()) {
                add_shortcode('maxicharts', array(
                    $this,
                    'maxicharts_shortcode'
                ));
            }
            add_filter("maxicharts_get_data_from_source", array(
                $this,
                "get_data_from_user"
            ), 10, 3);
            
            add_action('wp_ajax_gf_forms_list', array(
                $this,
                'list_gf_ajax'
            ));
            add_action('before_wp_tiny_mce', array(
                $this,
                'gf_forms_list_script'
            ));
            
            // add_action( 'admin_footer', array( $this, 'gf_forms_list_script' ) );
            add_action('admin_head', array(
                $this,
                'mce_button'
            ));
            
            do_action('maxicharts_add_shortcodes');
            
            /*
             * add_filter("mce_external_plugins", array($this,"enqueue_tinymce_scripts"));
             * add_filter("mce_buttons", array($this,"register_buttons_editor"));
             */
            add_filter("mcharts_modify_colors", array(
                $this,
                "setColorPalette"
            ), 10, 3);
            add_filter("mcharts_filter_chart_title", array(
                $this,
                "mcharts_clean_chart_title"
            ));
            add_filter("mcharts_filter_chart_labels", array(
                $this,
                "mcharts_clean_chart_labels"
            ));
            
            add_action('plugins_loaded', array(
                $this,
                'maxicharts_load_textdomain'
            ));
            
            // add_action('init', array($this, 'maxicharts_init'));
            // add_action('admin_print_footer_scripts','maxicharts_eg_quicktags');
        }

        function maxicharts_init()
        {
            MAXICHARTSAPI::getLogger()->trace("Init " . __CLASS__);
            /*
             * $class = __CLASS__;
             * new $class ();
             */
            // new maxicharts_reports();
            $this->maxicharts_settings = new Maxicharts_SettingsPage();
            //MAXICHARTSAPI::getLogger()->debug("Maxicharts options");
            $plugin_options = $this->maxicharts_settings->getOptions();
            self::$license_key = $plugin_options['license_key'];
            MAXICHARTSAPI::$maxicharts_log_level = isset($plugin_options['maxicharts_log_level']) ? $plugin_options['maxicharts_log_level'] : 'warn';
            //define("MAXICHARTS_DEBUG_LEVEL",self::$log_level);
            MAXICHARTSAPI::$maxicharts_logger = NULL;
            MAXICHARTSAPI::getLogger()->info("MAXICHARTS_DEBUG_LEVEL = ".MAXICHARTSAPI::$maxicharts_log_level);
            if (self::$availableColorSets == NULL) {
                $defaultColorSets = self::getDefaultColorSets();
                MAXICHARTSAPI::getLogger()->trace("color sets : " . count($defaultColorSets));
                self::$availableColorSets = apply_filters('mcharts_available_color_sets', $defaultColorSets);
                MAXICHARTSAPI::getLogger()->trace("color sets : " . count(self::$availableColorSets));
            }
        }

        static function mcharts_clean_chart_label($string)
        {
            $chartTitleCleaned = str_replace('"', "", $string);
            $chartTitleCleaned = str_replace("'", "", $chartTitleCleaned);
            $chartTitleCleaned = self::removeQuotes($chartTitleCleaned);
            $chartTitleCleaned = trim(preg_replace('/\s+/', ' ', $chartTitleCleaned));
            
            MAXICHARTSAPI::getLogger()->debug("Cleaned title: " . $chartTitleCleaned);
            
            return $chartTitleCleaned;
        }

        function mcharts_clean_chart_title($title)
        {
            $chartTitleCleaned = str_replace('"', "", $title);
            $chartTitleCleaned = str_replace("'", "", $chartTitleCleaned);
            $chartTitleCleaned = self::removeQuotes($chartTitleCleaned);
            $chartTitleCleaned = trim(preg_replace('/\s+/', ' ', $chartTitleCleaned));
            
            MAXICHARTSAPI::getLogger()->debug("Cleaned title: " . $chartTitleCleaned);
            
            return $chartTitleCleaned;
        }

        function mcharts_clean_chart_labels($labels)
        {
            $labelsCleaned = array_map(array(
                $this,
                'removeQuotesAndConvertHtml'
            ), $labels);
            
            return $labelsCleaned;
        }

        function enqueue_tinymce_scripts($plugin_array)
        {
            // enqueue TinyMCE plugin script with its ID.
            $plugin_array["maxicharts_button"] = plugin_dir_url(__FILE__) . "js/gfcr_tinymce_button.js";
            MAXICHARTSAPI::getLogger()->trace("Add button to Tiny MCE using file : " . $plugin_array["maxicharts_button"]);
            return $plugin_array;
        }

        function register_buttons_editor($buttons)
        {
            // register buttons with their id.
            array_push($buttons, "maxicharts_insert", 'mcharts_insert_graph');
            MAXICHARTSAPI::getLogger()->trace("Add button to WP buttons array: ");
            MAXICHARTSAPI::getLogger()->trace($buttons);
            
            return $buttons;
        }

        public function getForms()
        {
            $list = array();
            if (is_multisite()) {
                $blog_id = get_current_blog_id();
                switch_to_blog($blog_id);
                $forms = GFAPI::get_forms();
                restore_current_blog();
            } else {
                $forms = GFAPI::get_forms();
            }
            MAXICHARTSAPI::getLogger()->trace(count($forms) . " forms retrieved");
            foreach ($forms as $form) {
                // MAXICHARTSAPI::getLogger ()->debug ( $form );
                $selected = '';
                $post_id = $form['id'];
                $post_name = $form['title'];
                MAXICHARTSAPI::getLogger()->trace($post_id . ' -> ' . $post_name);
                $list[] = array(
                    'text' => $post_name,
                    'value' => strval($post_id)
                );
            }
            wp_send_json(apply_filters('mcharts_modify_form_list', $list));
        }

        public function list_gf_ajax()
        {
            // check for nonce
            MAXICHARTSAPI::getLogger()->trace("list_gf_ajax");
            check_ajax_referer('maxicharts-nonce', 'security');
            $this->getForms();
        }

        public function gf_forms_list_script()
        {
            
            // create nonce
            MAXICHARTSAPI::getLogger()->trace('gf_forms_list_script');
            global $pagenow;
            MAXICHARTSAPI::getLogger()->trace($pagenow);
            // var_dump($pagenow);
            if ($pagenow != 'admin.php') {
                $nonce = wp_create_nonce('maxicharts-nonce');
                
                // do_action( 'before_wp_tiny_mce', array $mce_settings )
                ?>
<script type="text/javascript">
							jQuery( document ).on( 'tinymce-editor-init', function( $ ) {
								var data = {
									'action'	: 'gf_forms_list',				// wp ajax action
									'security'	: '<?php echo $nonce; ?>'		// nonce value created earlier
								};
/*
								alert(ajaxurl);
								alert(data);*/
								// fire ajax
							  	jQuery.post( ajaxurl, data, function( response ) {
							  		// if nonce fails then not authorized else settings saved
							  		if( response === '-1' ){
								  		// do nothing
								  		console.log('error');
							  		} else {								  		
							  			//console.log('response != -1');
							  			if (typeof(tinyMCE) != 'undefined') {
							  				//console.log('not undefined');
							  				if (tinyMCE.DOM != null) {
							  					console.log('array value set to active ed: '+ tinyMCE.activeEditor.id);
							  					//console.log(response);
							  					//tinyMCE.activeEditor.windowManager.alert(response);
							  					console.log("editor ID: "+ tinyMCE.activeEditor.id);
												tinyMCE.activeEditor.settings.gfFormsList = response;
												console.log(tinyMCE.activeEditor.settings.gfFormsList);
												
												var content = tinyMCE.get("content");
												if (content) {
												    content.settings.gfFormsList = response;
												    console.log(content.settings.gfFormsList);
												}										
												
							  				} else {
											    console.error('null tinyMCE.DOM');
											}
										}
							  		}
							  	});
							});
						</script>
<?php
            }
        }

        /**
         * Add IE Fallback for HTML5 and canvas
         *
         * @since Unknown
         */
        // maxicharts_reports_js responsive canvas CSS override
        function maxicharts_reports_html5_support()
        {
            echo '<!--[if lte IE 8]>';
            echo '<script src="' . plugins_url('/js/excanvas.js', __FILE__) . '"></script>';
            echo '<![endif]-->';
            echo '	<style>
    			
    			.maxicharts_reports_canvas {
    				width:100%!important;
    				max-width:100%;
    			}

    			@media screen and (max-width:480px) {
    				div.maxicharts_reports-wrap {
                        /*position: relative;
                        margin: auto;
                        height: 100vh;
                        width: 100vw;
                        display: block;*/
    					width:100%!important;
    					float: none!important;
						margin-left: auto!important;
						margin-right: auto!important;
						text-align: center;
    				}
    			}
    		</style>';
        }

        /**
         * Register Script
         *
         * @since Unknown
         */
        function maxicharts_enqueue_scripts($force = false)
        {
            if (! is_admin() || $force) {
                
                MAXICHARTSAPI::getLogger()->debug("Registering scripts and styles");
                
                // REGISTER scripts
                // register ChartJS
                $chartjsPath = "libs/node_modules/chart.js/dist";
                $chartjsScriptFilename = "Chart.min.js";
                $chartJsCDN = plugins_url(trailingslashit($chartjsPath) . $chartjsScriptFilename, __FILE__);
                wp_register_script('chart-js', $chartJsCDN, null, false, false);
                // does not work if enqueued in maxicharts_enqueue_scripts, why ?
                
                // Register chartjs Annotation plugin
                $annotationPluginPath = "libs/node_modules/chartjs-plugin-annotation";
                $annotationPluginFilename = "chartjs-plugin-annotation.min.js";
                $chartJsAnnotationPlugin = plugins_url(trailingslashit($annotationPluginPath) . $annotationPluginFilename, __FILE__);
                wp_register_script('chart-js-annotation-plugin', $chartJsAnnotationPlugin, array('chart-js'));
                
                $datalabelsPluginPath = "libs/node_modules/chartjs-plugin-datalabels/dist";
                $datalabelsPluginFilename = "chartjs-plugin-datalabels.min.js";
                $chartJsDatalabelsPlugin = plugins_url(trailingslashit($datalabelsPluginPath) . $datalabelsPluginFilename, __FILE__);
                wp_register_script('chart-js-datalabels-plugin', $chartJsDatalabelsPlugin, array('chart-js'));
                
                
                
                if (ENABLE_LINEARGAUGE) {
                    // register chartjs Linear Gauge plugin
                    $linearGaugePluginPath = "libs/chartjs-plugin-linearGauge";
                    $linearGaugePluginFilename = "chart.lineargauge.js";
                    $linearGaugePluginFilename2 = "chart.scale.lineargauge.js";
                    $linearGaugePluginFilename3 = "chart.element.gaugerect.js";
                    
                    $chartJsLinearGaugePlugin = plugins_url(trailingslashit($linearGaugePluginPath) . $linearGaugePluginFilename, __FILE__);
                    $chartJsLinearGaugePlugin2 = plugins_url(trailingslashit($linearGaugePluginPath) . $linearGaugePluginFilename2, __FILE__);
                    $chartJsLinearGaugePlugin3 = plugins_url(trailingslashit($linearGaugePluginPath) . $linearGaugePluginFilename3, __FILE__);
                                        
                    wp_register_script('chart-js-lineargauge-scale-plugin', $chartJsLinearGaugePlugin2, array('chart-js'));
                    wp_register_script('chart-js-lineargauge-rect-plugin', $chartJsLinearGaugePlugin3, array('chart-js'));
                    wp_register_script('chart-js-lineargauge-plugin', $chartJsLinearGaugePlugin, array('chart-js'));
                }
                // not in footer
                // wp_register_script('maxicharts_chartjs-tooltips', MAXICHARTS_URL . 'js/chartjs_tooltips.js', null, false, false);
                
                // Register Maxicharts JS
                // in footer
                wp_register_script('maxicharts_reports-functions', MAXICHARTS_URL . 'js/functions.js', array(
                    'jquery'  , 'chart-js'), '', false);
                
                // css
                $mcr_css = plugins_url('css/maxichartsreports.css', __FILE__);
                wp_register_style('maxicr-css', $mcr_css, __FILE__);
                
                // ENQUEUE Scripts
                wp_enqueue_script('chart-js');
                wp_enqueue_script('maxicharts_reports-functions');                
                wp_enqueue_style('maxicr-css');
                // wp_enqueue_script('maxicharts_chartjs-tooltips');
                //self::maxicharts_enqueue_scripts();
            }
        }
/*
        static function maxicharts_enqueue_scripts()
        {
            MAXICHARTSAPI::getLogger()->debug("Enqueueing scripts and styles");
            
            // wp_enqueue_script('jquery');
            
            wp_enqueue_script('maxicharts_reports-functions');
            
            wp_enqueue_style('maxicr-css');
        }
*/
        //
        public function maxicharts_shortcode($atts = [], $content = null, $tag = '')
        {
            if (! is_admin()) {
                $source = 'user';
                $destination = 'chartjs';
                return self::chartReports($source, $destination, $atts, $content, $tag);
            }
        }

        function get_data_from_user($reportFields, $source, $atts = [], $content = null, $tag = '')
        {
            if ($source == 'user') {
                $defaultsParameters = array(
                    'type' => 'pie',
                    'url' => '',
                    'position' => '',
                    'float' => false,
                    'center' => false,
                    'title' => 'chart',
                    'canvaswidth' => '625',
                    'canvasheight' => '625',
                    'width' => '48%',
                    'height' => 'auto',
                    'margin' => '5px',
                    'relativewidth' => '1',
                    'align' => '',
                    'class' => '',
                    'labels' => '',
                    'data' => '30,50,100',
                    'data_conversion' => '',
                    'datasets_invert' => '',
                    'datasets' => '',
                    'gf_form_ids' => '',
                    'multi_include' => '',
                    'gf_form_id' => '1',
                    'maxentries' => '',
                    'gf_criteria' => '',
                    'include' => '',
                    'exclude' => '',
                    'colors' => '',
                    'color_set' => '',
                    'color_rand' => false,
                    'chart_js_options' => '',
                    'tooltip_style' => 'BOTH',
                    'grouped_tooltips' => false,
                    'custom_search_criteria' => '',
                    'fillopacity' => '0.7',
                    'pointstrokecolor' => '#FFFFFF',
                    'animation' => 'true',
                    'xaxislabel' => '',
                    'yaxislabel' => '',
                    'scalefontsize' => '12',
                    'scalefontcolor' => '#666',
                    'scaleoverride' => 'false',
                    'scalesteps' => 'null',
                    'scalestepwidth' => 'null',
                    'scalestartvalue' => 'null',
                    'case_insensitive' => false,
                    'no_score_computation' => false,
                    'list_series_names' => '',
                    'list_series_values' => '',
                    'list_labels_names' => '',
                    'list_sum_keys' => 'all',
                    'data_only' => '',
                    'xcol' => '0',
                    'ycol' => '1',
                    'compute' => '',
                    'header_start' => '0',
                    'header_size' => '1',
                    // new CSV
                    'columns' => '',
                    'rows' => '',
                    'delimiter' => '',
                    'information_source' => '',
                    'no_entries_custom_message' => ''
                );
                
                // override default attributes with user attributes
                /*
                 * $wporg_atts = shortcode_atts([
                 * 'title' => 'WordPress.org',
                 * ], $atts, $tag);
                 */
                $final_atts = shortcode_atts($defaultsParameters, $atts);
                
                $type = trim($final_atts['type']); // str_replace(' ', '', $type);
                $url = trim($final_atts['url']); // str_replace(' ', '', $url);
                $title = trim($final_atts['title']); // str_replace(' ', '', $title);
                $data = explode(',', trim($final_atts['data'])); // str_replace(' ', '', $data));
                $data_conversion = trim($final_atts['data_conversion']); // str_replace(' ', '', $data_conversion);
                $datasets_invert = trim($final_atts['datasets_invert']); // str_replace(' ', '', $datasets_invert);
                                                                         // $gv_approve_status = explode ( ";", str_replace ( ' ', '', $gv_approve_status) );
                $datasets = explode("next", trim($final_atts['datasets'])); // str_replace(' ', '', $datasets));
                
                if ($data) {
                    $reportFields[] = array(
                        'scores' => $data
                    );
                }
            }
            
            return $reportFields;
        }

        static function getDefaultColorSets()
        {
            $availableColorSets = array(
                'default' => array(
                    '#a6cee3',
                    '#1f78b4',
                    '#b2df8a',
                    '#33a02c',
                    '#fb9a99',
                    '#e31a1c',
                    '#fdbf6f',
                    '#ff7f00',
                    '#cab2d6',
                    '#6a3d9a',
                    '#ffff99',
                    '#b15928'
                ),
                'blue' => array(
                    '#ffffd9',
                    '#edf8b1',
                    '#c7e9b4',
                    '#7fcdbb',
                    '#41b6c4',
                    '#1d91c0',
                    '#225ea8',
                    '#253494',
                    '#081d58'
                ),
                'red' => array(
                    '#ffffcc',
                    '#ffeda0',
                    '#fed976',
                    '#feb24c',
                    '#fd8d3c',
                    '#fc4e2a',
                    '#e31a1c',
                    '#bd0026'
                ),
                'green' => array(
                    '#ffffe5',
                    '#f7fcb9',
                    '#d9f0a3',
                    '#addd8e',
                    '#78c679',
                    '#41ab5d',
                    '#238443',
                    '#006837',
                    '#004529'
                ),
                'purple' => array(
                    '#fff7f3',
                    '#fde0dd',
                    '#fcc5c0',
                    '#fa9fb5',
                    '#f768a1',
                    '#dd3497',
                    '#ae017e',
                    '#7a0177',
                    '#49006a'
                ),
                'orange' => array(
                    '#ffffe5',
                    '#fff7bc',
                    '#fee391',
                    '#fec44f',
                    '#fe9929',
                    '#ec7014',
                    '#cc4c02',
                    '#993404',
                    '#662506'
                )
            );
            
            return $availableColorSets;
        }

        function setColorPalette($color_set, $color_rand, $colors)
        {
            MAXICHARTSAPI::getLogger()->debug("SETTING COLOR PALETTE... $color_set, $color_rand, $colors");
            MAXICHARTSAPI::getLogger()->debug(count(self::$availableColorSets) . " sets available");
            $colorsArray = array();
            
            if (! empty($colors)) {
                // custom user colors
                $colorsArray = explode(',', $colors);
                MAXICHARTSAPI::getLogger()->debug("exploded : ");
                MAXICHARTSAPI::getLogger()->debug($colorsArray);
                MAXICHARTSAPI::getLogger()->debug("### User colors : " . count($colorsArray));
            } elseif (! empty($color_set)) {
                // user defined color set
                MAXICHARTSAPI::getLogger()->debug("### Color Set is : " . $color_set);
                if ($color_set == 'random') {
                    // random colors
                    
                    MAXICHARTSAPI::getLogger()->debug("### RANDOM : " . $color_set);
                    $random_set = array_rand(self::$availableColorSets);
                    MAXICHARTSAPI::getLogger()->debug($random_set);
                    $color_set = $random_set;
                } elseif (! array_key_exists($color_set, self::$availableColorSets)) {
                    MAXICHARTSAPI::getLogger()->warn("Unkown color set: " . $color_set);
                    MAXICHARTSAPI::getLogger()->warn("Available color sets: ");
                    MAXICHARTSAPI::getLogger()->warn(self::$availableColorSets);
                    $color_set = 'default';
                }
                $colorsArray = self::$availableColorSets[$color_set];
            }
            MAXICHARTSAPI::getLogger()->trace($colorsArray);
            
            if (empty($colorsArray)) {
                $color_set = 'default';
                $colorsArray = self::$availableColorSets[$color_set];
            }
            
            if (! empty($color_rand) && $color_rand == true) {
                MAXICHARTSAPI::getLogger()->debug("### Shuffled colors : " . count($colorsArray));
                shuffle($colorsArray);
            }
            
            return $colorsArray;
        }

        static function updateLabels($reportFields)
        {
            MAXICHARTSAPI::getLogger()->debug("--- updateLabels ---");
            
            // MAXICHARTSAPI::getLogger ()->debug ( "$scoreKey => $scoreVal" );
            foreach ($reportFields as $fieldID => $fieldDatas) {
                if (isset($fieldDatas['type']) && $fieldDatas['type'] != 'radio' && $fieldDatas['type'] != 'select') {
                    continue;
                }
                if (! isset($fieldDatas['labels'])) {
                    MAXICHARTSAPI::getLogger()->warn("No labels for report field id : " . $fieldID);
                    continue;
                }
                $newLabels = array();
                MAXICHARTSAPI::getLogger()->debug("Label size before update : " . count($fieldDatas['labels']));
                foreach ($fieldDatas['labels'] as $labelKey => $labelVal) {
                    
                    MAXICHARTSAPI::getLogger()->trace("Replacing key $labelKey => $labelVal ?");
                    $replacmentFound = false;
                    if (empty($fieldDatas['choices'])) {
                        MAXICHARTSAPI::getLogger()->trace("empty choices for " . $labelKey);
                        continue;
                    }
                    $replacementFound = false;
                    foreach ($fieldDatas['choices'] as $choiceKey => $choiceVal) {
                        MAXICHARTSAPI::getLogger()->trace($choiceKey . ' =choices> ' . $choiceVal['value']);
                        if ($choiceVal['value'] == $labelVal) {
                            $newText = trim(html_entity_decode(wp_strip_all_tags($choiceVal['text'])));
                            MAXICHARTSAPI::getLogger()->trace("YES :: $labelKey replace=> $newText");
                            $newLabels[] = $newText;
                            $replacementFound = true;
                        }
                    }
                    
                    if (! $replacementFound) {
                        MAXICHARTSAPI::getLogger()->trace("NO :: $labelKey stays");
                        $newLabels[$labelKey] = $labelVal;
                    }
                }
                MAXICHARTSAPI::getLogger()->debug("Label size after update : " . count($newLabels));
                if (empty($newLabels)) {
                    // $reportFields [$fieldID] ['labels'] = apply_filters ( 'mcharts_modify_new_labels', $fieldDatas ['labels'] );
                    $reportFields[$fieldID]['labels'] = $fieldDatas['labels'];
                } else {
                    // $reportFields [$fieldID] ['labels'] = apply_filters ( 'mcharts_modify_new_labels', $newLabels );
                    $reportFields[$fieldID]['labels'] = $newLabels;
                }
                
                MAXICHARTSAPI::getLogger()->debug("Label size after filter : " . count($reportFields[$fieldID]['labels']));
            }
            
            return $reportFields;
        }

        static function get_all_ranges($inputRows)
        {
            $rawRows = explode(',', str_replace(' ', '', $inputRows));
            MAXICHARTSAPI::getLogger()->debug($rawRows);
            $result = array();
            foreach ($rawRows as $rowsItems) {
                MAXICHARTSAPI::getLogger()->debug($rowsItems);
                if (stripos($rowsItems, '-') !== false) {
                    $limits = explode('-', $rowsItems);
                    $newRows = range($limits[0], $limits[1]);
                    $result = array_merge($result, $newRows);
                } else {
                    $result[] = $rowsItems;
                }
            }
            
            if (count($result) == 1 && empty($result[0])) {
                $result = false;
            }
            MAXICHARTSAPI::getLogger()->debug($result);
            return $result;
        }

        static function createStdDevAnnotation($annotation_parameters, $average, $std_dev, $side)
        {
            $a_mode = isset($annotation_parameters[0]) ? $annotation_parameters[0] : 'vertical';
            MAXICHARTSAPI::getLogger()->info("Adding std dev line annotation...");
            MAXICHARTSAPI::getLogger()->debug($annotation_parameters);
            $a_border_color = isset($annotation_parameters[1]) ? $annotation_parameters[1] : "red";
            $a_border_width = isset($annotation_parameters[2]) ? $annotation_parameters[2] : 2;
            $a_prefix = isset($annotation_parameters[3]) ? $annotation_parameters[3] : '';
            $a_mean_offset = isset($annotation_parameters[4]) ? $annotation_parameters[4] : 0;
            
            if ($side == 'left') {
                $computed_std_dev_offset = number_format($average - $std_dev + $a_mean_offset, 2);
            } else {
                $computed_std_dev_offset = number_format($average + $std_dev + $a_mean_offset, 2);
            }
            $displayed_std_dev = number_format($std_dev, 2);
            
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $currentAnnotationId = substr(str_shuffle($permitted_chars), 0, 10);
            
            $newAnnotation = " {
							id: '" . $currentAnnotationId . "',
							type: 'line',
							mode: '" . $a_mode . "',
							scaleID: 'x-axis-0',
							value: " . $computed_std_dev_offset . ",
							borderColor: '" . $a_border_color . "',
							borderWidth: " . $a_border_width . ",
							label: {
								backgroundColor: 'black',
								content: '" . $a_prefix . $displayed_std_dev . "',
								enabled: true
							},
							onClick: function(e) {
								// The annotation is is bound to the `this` variable
								console.log('Annotation', e.type, this);
							}
						}";
            
            MAXICHARTSAPI::getLogger()->debug($newAnnotation);
            return $newAnnotation;
        }

        static function createMeanAnnotation($annotation_parameters, $average)
        {
            $a_mode = isset($annotation_parameters[0]) ? $annotation_parameters[0] : 'vertical';
            MAXICHARTSAPI::getLogger()->info("Adding mean line annotation...");
            // MAXICHARTSAPI::getLogger()->debug($dataset);
            $a_border_color = isset($annotation_parameters[1]) ? $annotation_parameters[1] : "red";
            $a_border_width = isset($annotation_parameters[2]) ? $annotation_parameters[2] : 2;
            $a_prefix = isset($annotation_parameters[3]) ? $annotation_parameters[3] : '';
            $a_mean_offset = isset($annotation_parameters[4]) ? $annotation_parameters[4] : 0;
            
            $computed_mean_offset = number_format($average + $a_mean_offset, 2);
            $displayed_average = number_format($average, 2);
            
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $currentAnnotationId = substr(str_shuffle($permitted_chars), 0, 10);
            
            $newAnnotation = " {
							id: '" . $currentAnnotationId . "',
							type: 'line',
							mode: '" . $a_mode . "',
							scaleID: 'x-axis-0',
							value: " . $computed_mean_offset . ",
							borderColor: '" . $a_border_color . "',
							borderWidth: " . $a_border_width . ",
							label: {
								backgroundColor: 'black',
								content: '" . $a_prefix . $displayed_average . "',
								enabled: true
							},
							onClick: function(e) {
								// The annotation is is bound to the `this` variable
								console.log('Annotation', e.type, this);
							}
						}";
            
            MAXICHARTSAPI::getLogger()->debug($newAnnotation);
            return $newAnnotation;
        }

        static function prepareMinMaxAnnotation($annotation_min, $min)
        {
            $js_annotations = [];
            
            $annotation_parameters = explode(',', $annotation_min);
            $newAnnotation = self::createMeanAnnotation($annotation_parameters, $min);
            $js_annotations[] = $newAnnotation;
            
            return $js_annotations;
        }

        static function prepareAnnotations($annotation, $annotation_mean, $average, $annotation_std_dev, $std_dev)
        {
            $js_annotations = [];
            
            if (! empty($annotation)) {
                MAXICHARTSAPI::getLogger()->debug("### annotation");
                MAXICHARTSAPI::getLogger()->debug($annotation);
                
                $annotations_array = explode(';', $annotation);
                MAXICHARTSAPI::getLogger()->debug($annotations_array);
                
                foreach ($annotations_array as $annotation) {
                    MAXICHARTSAPI::getLogger()->debug($annotation);
                    $annotation_parameters = explode(',', $annotation);
                    MAXICHARTSAPI::getLogger()->debug($annotation_parameters);
                    $a_type = isset($annotation_parameters[0]) ? $annotation_parameters[0] : 'line';
                    if ($a_type == 'line') {
                        MAXICHARTSAPI::getLogger()->info("Adding line annotation...");
                        $a_mode = isset($annotation_parameters[1]) ? $annotation_parameters[1] : 'vertical';
                        $a_value = isset($annotation_parameters[2]) ? $annotation_parameters[2] : '1';
                        $a_border_color = isset($annotation_parameters[3]) ? $annotation_parameters[3] : 'red';
                        $a_border_width = isset($annotation_parameters[4]) ? $annotation_parameters[4] : '5';
                        $a_label = isset($annotation_parameters[5]) ? $annotation_parameters[5] : '';
                        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                        $currentAnnotationId = substr(str_shuffle($permitted_chars), 0, 10);
                        // $currentAnnotationId = self::clean ( uniqid ( 'aid_' ) . $chartId . '_' . $chartType );
                        // endValue: " . ($a_value + 5) . ",
                        $newAnnotation = " {
							id: '" . $currentAnnotationId . "',
							type: '" . $a_type . "',
							mode: '" . $a_mode . "',
							scaleID: 'x-axis-0',
							value: " . $a_value . ",
                     
							borderColor: '" . $a_border_color . "',
							borderWidth: " . $a_border_width . ",
							label: {
								backgroundColor: 'black',
								content: '" . $a_label . "',
								enabled: true
							},
							onClick: function(e) {
								// The annotation is is bound to the `this` variable
								console.log('Annotation', e.type, this);
							}
						}";
                    } else if ($a_type == 'box') {
                        MAXICHARTSAPI::getLogger()->info("Adding box annotation...");
                        $a_xmin = $annotation_parameters[1];
                        $a_xmax = $annotation_parameters[2];
                        $a_ymin = $annotation_parameters[3];
                        $a_ymax = $annotation_parameters[4];
                        
                        $a_border_color = $annotation_parameters[5];
                        $a_border_width = $annotation_parameters[6];
                        $a_background_color = $annotation_parameters[7];
                        
                        // $a_label = $annotation_parameters[5];
                        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                        $currentAnnotationId = substr(str_shuffle($permitted_chars), 0, 10);
                        $newAnnotation = " {
							type: '" . $a_type . "',
							xScaleID: 'x-axis-0',
                            yScaleID: 'y-axis-0',
                            xMin: \"" . $a_xmin . "\",
                            xMax: \"" . $a_xmax . "\",
                            yMin:  \"" . $a_ymin . "\",
                            yMax: \"" . $a_ymax . "\",
							borderColor: '" . $a_border_color . "',
							borderWidth: " . $a_border_width . ",
                            backgroundColor: '" . $a_background_color . "'
						}";
                    } else {
                        MAXICHARTSAPI::getLogger()->error("Unkown annotation type: " . $a_type);
                    }
                    
                    MAXICHARTSAPI::getLogger()->debug($newAnnotation);
                    
                    $js_annotations[] = $newAnnotation;
                }
            }
            
            if (! empty($annotation_mean) && ! empty($average)) {
                $annotation_parameters = explode(',', $annotation_mean);
                $newAnnotation = self::createMeanAnnotation($annotation_parameters, $average);
                $js_annotations[] = $newAnnotation;
            }
            
            if (! empty($annotation_std_dev) && ! empty($std_dev)) {
                $annotation_parameters = explode(',', $annotation_std_dev);
                $js_annotations[] = self::createStdDevAnnotation($annotation_parameters, $average, $std_dev, 'left');
                $js_annotations[] = self::createStdDevAnnotation($annotation_parameters, $average, $std_dev, 'right');
            }
            
            return $js_annotations;
        }

        static function checkCurrentLicenseKey() {
            $license_level = 0;
            //license_key
            //MAXICHARTSAPI::getLogger()->debug("checkCurrentLicenseKey: ".self::$license_key);
            $host = $_SERVER['SERVER_NAME'];
            /*$info = parse_url($url);
            $host = $info['host'];*/
            $hashed = strtolower(sha1($host));
            if ($hashed === strtolower(self::$license_key)) {
                MAXICHARTSAPI::getLogger()->debug("Valid key for site");
                $license_level = 1;
            } else {
                MAXICHARTSAPI::getLogger()->warn("Invalid key for site: ".$host); 
                /*MAXICHARTSAPI::getLogger()->error($hashed);
                MAXICHARTSAPI::getLogger()->error(self::$license_key);*/
                
            }
            
            return $license_level;
        }
        
        static function chartReports($source, $destination, $raw_atts = [], $content = null, $tag = '')
        {
            MAXICHARTSAPI::getLogger()->debug("MAXICHART DO Report from " . $source . " to " . $destination);
            
            $active_license_type = self::checkCurrentLicenseKey();
            
            
            MAXICHARTSAPI::getLogger()->debug("User defined attributes in shortcode tag. raw attributes:");
            // normalize attribute keys, lowercase
            $raw_atts = array_change_key_case((array) $raw_atts, CASE_LOWER);
            
            MAXICHARTSAPI::getLogger()->info($raw_atts);
            if (isset($raw_atts[0])) {
                MAXICHARTSAPI::getLogger()->error("Probable invalid shortocde syntax");
            }
            if ($source == 'csv' && ! class_exists('mcharts_csv_source_plugin')) {
                
                $msg = "Please install/activate csv plugin";
                MAXICHARTSAPI::getLogger()->error($msg);
                return $msg;
            }
            
            do_action('check_source', $source);
            do_action('check_destination', $destination);
            
            // self::maxicharts_enqueue_scripts();
            
            $defaultsParameters = array(
                'type' => 'pie',
                'url' => '',
                'position' => '',
                'float' => false,
                'center' => false,
                'title' => 'chart',
                'display_title' => true,
                'display_legend' => true,
                'group_fields' => '',
                'css_classes_as_series' => '',
                'css_datasets_labels' => '',
                'width' => '48%',
                'height' => 'auto',
                'margin' => '5px',
                'relativewidth' => '1',
                'align' => '',
                'class' => '',
                'labels' => '',
                'data' => '30,50,100',
                'data_conversion' => '',
                'datasets_invert' => '',
                'datasets' => '',
                'datasets_field' => '',
                'data_only' => '',
                'gf_form_ids' => '',
                'multi_include' => '',
                'gf_form_id' => '1',
                'gf_entry_id' => '',
                'maxentries' => '',
                'gf_criteria' => '',
                'include' => '',
                'exclude' => '',
                'ignore_empty_values' => false,
                'colors' => '',
                'color_set' => '',
                'color_rand' => false,
                'chart_js_options' => '',
                'tooltip_style' => 'BOTH',
                'chart_js_options_hover' => "mode: 'point',intersect: false",
                'chart_js_options_tooltip' => "mode: 'point',intersect: false",
                'grouped_tooltips' => false,
                'tooltip_dataserie_prefix' => '',
                'tooltip_title_prefix' => '',
                'number_format' => 'en-US',
                'number_format_options' => '{maximumFractionDigits: 2}',                
                'custom_search_criteria' => '',
                'fillopacity' => '0.7',
                'fill' => false,
                'pointstrokecolor' => '#FFFFFF',
                'animation' => 'true',
                'xaxis_display' => true,
                'yaxis_display' => true,
                'xaxislabel' => '',
                'yaxislabel' => '',
                'scalefontsize' => '12',
                'scalefontcolor' => '#666',
                'scaleoverride' => 'false',
                'scalesteps' => 'null',
                'scalestepwidth' => 'null',
                'scalestartvalue' => 'null',
                'case_insensitive' => false,
                'compute' => '',
                'header_start' => '0',
                'x_stacked' => false,
                'y_stacked' => false,
                'x_step_size' => '',
                'y_step_size' => '',
                'x_scale_type' => '',
                'y_scale_type' => '',
                'decimal_separator' => '',
                'thousands_separator' => '',
                'filter' => false,
                'annotation' => '',
                'annotation_mean' => '',
                'annotation_std_dev' => '',
                'annotation_min' => '',
                'annotation_max' => '',
                'no_entries_custom_message' => '',
		        'datalabels' => ''
            );
            
            $defaultsParameters = apply_filters('mcharts_filter_defaults_parameters', $defaultsParameters);
            MAXICHARTSAPI::getLogger()->debug("Entire list of supported attributes and their defaults:");
            MAXICHARTSAPI::getLogger()->debug($defaultsParameters);
            
            $atts = shortcode_atts($defaultsParameters, $raw_atts, $tag);
            
            MAXICHARTSAPI::getLogger()->debug("After merge with default parameters:");
            MAXICHARTSAPI::getLogger()->debug($atts);
            
            if (isset($atts['type']) && trim($atts['type']) == 'random') {
                $allTypes = array(
                    'pie',
                    'halfPie',
                    'bar',
                    'horizontalBar',
                    'doughnut',
                    'halfDoughnut',
                    'line'
                );
                $atts['type'] = $allTypes[array_rand($allTypes)];
            } else {
                $type = trim($atts['type']); // str_replace(' ', '', $type);
            }
            
            if ($type === 'linearGauge') {
                if (ENABLE_LINEARGAUGE) {                    
                    wp_enqueue_script('chart-js-lineargauge-scale-plugin');
                    wp_enqueue_script('chart-js-lineargauge-rect-plugin');
                    wp_enqueue_script('chart-js-lineargauge-plugin');
                } else {
                    $msg = __("No linear gauge implemented");
                    MAXICHARTSAPI::getLogger()->error($msg);
                    return $msg;
                }
            }
            
            // - - - - - - - - - - - - - - - - - - - - - - -
            
            $annotation = trim($atts['annotation']);
            $annotation_mean = trim($atts['annotation_mean']);
            $annotation_std_dev = trim($atts['annotation_std_dev']);
            $annotation_min = trim($atts['annotation_min']);
            $annotation_max = trim($atts['annotation_max']);
            
            $datalabels = trim($atts['datalabels']);
            $datalabels_options = '';
            if (! empty($annotation) || ! empty($annotation_mean) || ! empty($annotation_std_dev) || ! empty($annotation_min) || ! empty($annotation_max)) {
                // if shortcode has annotation, load dedicated plugin
                wp_enqueue_script('chart-js-annotation-plugin');
                MAXICHARTSAPI::getLogger()->info("Load chart-js-annotation-plugin");
            }
            
            if (!empty($datalabels)){
                wp_enqueue_script('chart-js-datalabels-plugin');
                MAXICHARTSAPI::getLogger()->info("Load chart-js-datalabels-plugin");
                
                $datalabels_options = 'plugins: {
                                            datalabels: {
                                                color: \'white\',
                                                display: function(context) {
                                                    return context.dataset.data[context.dataIndex] > 15;
                                                },
                                                font: {
                                                    weight: \'bold\'
                                                },
                                                formatter: Math.round
                                            }
                                        }';
                
                
                $datalabels_options = 'plugins: {
                    // Change options for ALL labels of THIS CHART
                    datalabels: {
                        color: \'white\',
                        display: function(context) {
							return context.dataset.data[context.dataIndex] >= 1;
						},
                        font: {
							weight: \'bold\',
                            size: 20
						},
						formatter: Math.round
                    }
                }';
            }
            MAXICHARTSAPI::getLogger()->debug($type);
            $url = trim($atts['url']); // str_replace(' ', '', $url);
            $title = trim($atts['title']); // str_replace(' ', '', $title);
            
            MAXICHARTSAPI::getLogger()->debug('CHECK TYPE : ' . $type);
            $data = explode(',', trim($atts['data']));
            $data_conversion = trim($atts['data_conversion']); // str_replace(' ', '', $data_conversion);
            $datasets_invert = trim($atts['datasets_invert']); // str_replace(' ', '', $datasets_invert);
                                                               // $gv_approve_status = explode ( ";", str_replace ( ' ', '', $gv_approve_status) );
            $datasets = explode("next", trim($atts['datasets']));
            $data_only = filter_var(trim($atts['data_only']), FILTER_VALIDATE_BOOLEAN);
            if ($data_only) {
                $allChartsDatas = array();
            }
            $gf_form_ids = explode(',', trim($atts['gf_form_ids'])/*str_replace(' ', '', $gf_form_ids)*/);
            $multi_include = explode(',', trim($atts['multi_include'])/*str_replace(' ', '', $multi_include)*/);
            $gf_form_id = trim($atts['gf_form_id']); // str_replace(' ', '', $gf_form_id);
            $colors = trim($atts['colors']); // str_replace(' ', '', $colors);
            $color_set = trim($atts['color_set']); // str_replace(' ', '', $color_set);
            $color_rand = trim($atts['color_rand']); // str_replace(' ', '', $color_rand);
            $fill_option = trim($atts['fill']); // str_replace(' ', $fill);//filter_var($fill, FILTER_VALIDATE_BOOLEAN);
            $position = trim($atts['position']); // str_replace(' ', '', $position);
            $float = trim($atts['float']); // str_replace(' ', '', $float);
            $center = trim($atts['center']); // str_replace(' ', '', $center);
            $case_insensitive = filter_var(trim($atts['case_insensitive']), FILTER_VALIDATE_BOOLEAN); // boolval(trim($case_insensitive));
            $xaxis_display = filter_var($atts['xaxis_display'], FILTER_VALIDATE_BOOLEAN);
            MAXICHARTSAPI::getLogger()->debug($xaxis_display);
            $yaxis_display = filter_var($atts['yaxis_display'], FILTER_VALIDATE_BOOLEAN);                
            $y_stacked = filter_var($atts['y_stacked'], FILTER_VALIDATE_BOOLEAN); // boolval(str_replace(' ', '', $y_stacked));
            $x_stacked = filter_var($atts['x_stacked'], FILTER_VALIDATE_BOOLEAN); // boolval(str_replace(' ', '', $x_stacked));
            $x_step_size = trim($atts['x_step_size']); // str_replace(' ', '', $x_step_size);
            $y_step_size = trim($atts['y_step_size']); // str_replace(' ', '', $y_step_size);
            $xaxislabel = trim($atts['xaxislabel']);
            $yaxislabel = trim($atts['yaxislabel']);
            $margin = trim($atts['margin']);
            $chart_js_options = trim($atts['chart_js_options']);
            $chart_js_options_hover = trim($atts['chart_js_options_hover']);
            $chart_js_options_tooltip = trim($atts['chart_js_options_tooltip']);
            
            MAXICHARTSAPI::getLogger()->debug($x_stacked);
            $include = trim($atts['include']); // str_replace(' ', '', $include);
            $exclude = trim($atts['exclude']); // str_replace(' ', '', $exclude);
            $tooltip_style = trim($atts['tooltip_style']); // str_replace(' ', '', $tooltip_style);
            $groupedTooltips = filter_var($atts['grouped_tooltips'], FILTER_VALIDATE_BOOLEAN); // trim($atts['groupedTooltips']);
            $tooltip_dataserie_prefix = $atts['tooltip_dataserie_prefix'];
            $tooltip_title_prefix = $atts['tooltip_title_prefix'];
            $number_format = trim($atts['number_format']);
            $number_format_options = trim($atts['number_format_options']);
            /*
            $minimum_fraction_digits = intval(trim($atts['minimum_fraction_digits']));
            $maximum_fraction_digits = intval(trim($atts['maximum_fraction_digits']));*/
            // MAXICHARTSAPI::getLogger ()->debug ( $columns );
            // MAXICHARTSAPI::getLogger ()->debug ( $rows );
            $compute = trim($atts['compute']); // str_replace(' ', '', $compute);
            $maxentries = trim($atts['maxentries']); // str_replace(' ', '', $maxentries);
            $information_source = trim($atts['information_source']);
            
            /*
             * if (empty ( $maxentries )) {
             * $maxentries = DEFAULT_MAX_ENTRIES;
             * }
             */
            $header_start = trim($atts['header_start']); // str_replace(' ', '', $header_start);
                                                         // $header_size = str_replace ( ' ', '', $header_size );
            
            $reportFields = array();
            if ((! empty($include))) {
                $includeArray = explode(",", $include);
            }
            if (! empty($exclude)) {
                $excludeArray = explode(",", $exclude);
            }
            MAXICHARTSAPI::getLogger()->debug($type);
            $width = trim($atts['width']);
            $height = trim($atts['height']);
            MAXICHARTSAPI::getLogger()->debug("W:$width / H:$height");
            
            $class = trim($atts['class']);
            $display_legend = trim($atts['display_legend']);
            $display_title = trim($atts['display_title']);
            // end of attributes catch
            
            if (empty($source) || (empty($destination))) {
                $msg = "Invalid source and/or destination " . $source . ' / ' . $destination;
                MAXICHARTSAPI::getLogger()->error($msg);
                return $msg;
            }
            
            MAXICHARTSAPI::getLogger()->debug('CHECK TYPE : ' . $type);
            $reportFields = apply_filters('maxicharts_get_data_from_source', $reportFields, $source, $atts);
            MAXICHARTSAPI::getLogger()->debug('CHECK TYPE : ' . $type);
            if (is_array($reportFields) && empty($reportFields)) {
                $msg = __('No data from data source', 'maxicharts') . ' ' . $source;
                MAXICHARTSAPI::getLogger()->warn($msg);
                return $msg;
            }
            
            MAXICHARTSAPI::getLogger()->debug('ABOUT to give result if peculiar type : ' . $type);
            $need_to_return_without_graph = apply_filters('mcharts_return_without_graph', $atts);
            
            if ((! is_array($reportFields) && ! empty($reportFields)) || $need_to_return_without_graph) {
                return $reportFields;
            } else {
                MAXICHARTSAPI::getLogger()->debug("Need to chart!");
            }
            
            if (empty($reportFields)) {
                $msg = __('No graph to display because empty data', 'maxicharts');
                MAXICHARTSAPI::getLogger()->warn($msg);
                return $msg;
            }
            // FIXME ADD HOOK to return if needed
            $datasets_field = trim($atts['datasets_field']);
            if (empty($datasets_field) /*&& $group_fields != 1*/ ) {
                $reportFields = self::updateLabels($reportFields);
            }
            // source data retrieved
            // do_action('process_additionnal_source', $source, $atts);
            // need to implement extract ( shortcode_atts ($defaultsParameters, $atts ) );
            
            // setting colors
            MAXICHARTSAPI::getLogger()->debug("### User colors : " . $colors . " ###");
            $colors = apply_filters('mcharts_modify_colors', $color_set, $color_rand, $colors);
            // $this->setColorPalette ( $color_set, $color_rand, $colors );
            MAXICHARTSAPI::getLogger()->debug("### Color Palette set : " . implode(";", $colors) . " ###");
            
            $currentchartOptions = '';
            $allCharts = '';
            $globalJSFunctions = ''; // self::getGlobalCustomJsFunctions();
            
            $variable_defintions_js = '';
            $currentchartData = '';
            // $allChartsNames = array();
            MAXICHARTSAPI::getLogger()->debug("### Charts to display : " . count($reportFields));
            if (empty($reportFields)) {
                $msg = __('No graph to display because no data', 'maxicharts');
                MAXICHARTSAPI::getLogger()->debug($msg);
                return $msg;
            }
            
            $javascript_code_to_inject = '';
            foreach ($reportFields as $chartId => $chartToPlot) {
                
                if (! is_int($chartId)) {
                    MAXICHARTSAPI::getLogger()->error("not an id  " . $chartId . ' skipping chart with data');
                    MAXICHARTSAPI::getLogger()->error("Probably a GF form attribute graph type");
                    MAXICHARTSAPI::getLogger()->error($chartToPlot);
                    continue;
                }
                
                if (isset($chartToPlot['no_answers']) && $chartToPlot['no_answers'] == 1) {
                    // no answers yet to field
                    $currentChartMsg = '<h6>' . __("No answers yet to field number") . ' ' . $chartId . ' : ' . $chartToPlot['label'] . '</h6>';
                    MAXICHARTSAPI::getLogger()->warn($currentChartMsg);
                    $allCharts .= $currentChartMsg;
                    continue;
                }
                
                if (! isset($chartToPlot['labels']) || empty($chartToPlot['labels'])) {
                    $currentChartMsg = __('No Labels trying to prepare chart : ' . $chartId);
                    MAXICHARTSAPI::getLogger()->error($currentChartMsg);
                    $allCharts .= $currentChartMsg;
                    continue;
                }
                /*
                 * if (!isset($chartToPlot['data']) || empty($chartToPlot['data'])){
                 * $currentChartMsg = __('No Data trying to prepare chart : '.$chartId);
                 * MAXICHARTSAPI::getLogger ()->error ( $currentChartMsg );
                 * MAXICHARTSAPI::getLogger ()->error ( $chartToPlot );
                 * $allCharts .= $currentChartMsg;
                 * continue;
                 * }
                 */
                
                $chartType = $chartToPlot['graphType'];
                
                MAXICHARTSAPI::getLogger()->debug('Try to display chart ' . $chartType . ' with id ' . $chartId);
                MAXICHARTSAPI::getLogger()->trace($chartToPlot);
                
                $currentChartId = self::clean(uniqid('id_') . $chartId . '_' . $chartType);
                MAXICHARTSAPI::getLogger()->debug("+++ Creating chart " . $currentChartId . ' with data size : ' . count($chartToPlot));
                MAXICHARTSAPI::getLogger()->trace($chartToPlot);
                
                // $allChartsNames[] = $currentChartId;
                $chartOptionsId = 'Options_' . $currentChartId;
                $dataName = 'Data_' . $currentChartId;
                $chartjsOptionsArray = [];
                if (!empty($datalabels_options)){
                    $chartjsOptionsArray[] = $datalabels_options;
                }
                $tooltipContent = "tooltipLabel + ': '";
                if ($chartToPlot['multisets']) {
                    if ($type == 'pie') {
                        $tooltipContent = "tooltipLabel + ' / '+";
                    } else {
                        $tooltipContent = '';
                    }
                    $tooltipContent .= "datasetLabel + ': ' + tooltipData";
                    if (empty($atts['css_classes_as_series'])) {
                        $tooltipContent .= ' + xaxisLabel';
                    }
                } else if ($data_conversion == "%") {
                    $tooltipContent .= "+ tooltipData + '%'";
                } else {
                    
                    if ($tooltip_style == 'SUM') {
                        // $tooltipContent = "tooltipLabel + ': ' + tooltipData";
                        $tooltipContent .= "+ tooltipData";
                    } else if ($tooltip_style == 'BOTH') {
                        // $tooltipContent = "tooltipLabel + ': ' + tooltipData + ' (' + tooltipPercentage + '%)'";
                        $tooltipContent .= "+ tooltipData + ' (' + tooltipPercentage + '%)'";
                    } else if ($tooltip_style == 'PERCENT') {
                        // $tooltipContent = "tooltipLabel + ': ' + tooltipPercentage + '%'";
                        $tooltipContent .= "+ tooltipPercentage + '%'";
                    }
                }
                
                $tooltipContent = apply_filters('mcharts_modify_tooltip_content', $tooltipContent);
                MAXICHARTSAPI::getLogger()->trace("TOOLTIP: " . $tooltipContent);
                
                $tooltipAxisLabel = ($chartType == 'bar') ? $yaxislabel : $xaxislabel;
                // $chartJsHover = '';
                if ($chart_js_options_hover) {
                    $chartjsOptionsArray[] = "hover: {" . $chart_js_options_hover . "}";
                    // $chartjsOptionsArray[] = $chartJsHover;
                }
                
                if ($groupedTooltips) {
                    /*
                     * tooltips: {
                     * enabled: false,
                     * mode: 'index',
                     * position: 'nearest',
                     * custom: customTooltips
                     * }
                     */
                    
                    // custom: groupedTooltips,
                    // position: 'nearest',
                    /*
                     * beforeTitle: function(tooltipItem, data) {
                     * return '".$tooltip_title_prefix."';
                     * },
                     */
                    // $before_number = '€ ';
                    $chartJsTooltips = "tooltips: {
						mode: 'index',
                        intersect: false,												
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var datasetLabel = data.datasets[tooltipItem.datasetIndex].label;
                                return datasetLabel + ': ' + '".$tooltip_dataserie_prefix."' + Intl.NumberFormat('".$number_format."', $number_format_options).format(tooltipItem.yLabel);
                            },
                           
                            title: function(tooltipItems, data) {
                                //Return value for title
                                return '" . $tooltip_title_prefix . "' + tooltipItems[0].xLabel;
                            },
                        }
					}";
                } else if (empty($chart_js_options_tooltip)) {
                    /*
                     * tooltips: {
                     * mode: 'index',
                     * intersect: false,
                     * },
                     */
                    
                    $chartJsTooltips = "
tooltips: {
            enabled: true,
            mode: '" . $tooltip_mode . "',
            intersect: '" . $tooltip_intersect . "',
            callbacks: {
                label: function(tooltipItem, data) {
                    var allData = data.datasets[tooltipItem.datasetIndex].data;
					var datasetLabel = data.datasets[tooltipItem.datasetIndex].label;
                console.log(allData);
                console.log(datasetLabel);
                var dataAtIdx = allData[tooltipItem.index];
                console.log(dataAtIdx);";
                    
                    $chartJsTooltips .= "
if (typeof dataAtIdx === 'object' && dataAtIdx !== null){
                        var tooltipLabel = dataAtIdx.x;
                        var tooltipData = dataAtIdx.y;
                    } else {
                        var tooltipLabel = data.labels[tooltipItem.index];
                        var tooltipData = allData[tooltipItem.index];
                    }";
                    
                    $chartJsTooltips .= "
var xaxisLabel = ' " . $tooltipAxisLabel . "';
                    var total = 0;
                    for (var i in allData) {
                        if (typeof allData[i] === 'object' && allData[i] !== null){
                                total += allData[i].y;
                            }
                        else {
                            total += allData[i];
                        }
                    }
                    var tooltipPercentage = Math.round((tooltipData / total) * 100);
					
                    return " . $tooltipContent . ";
				}
            }
        }";
                } else {
                    // $chart_js_options_hover
                    $chartJsTooltips = "tooltip: {" . $chart_js_options_tooltip . "}";
                }
                MAXICHARTSAPI::getLogger()->trace($chartJsTooltips);
                $chartjsOptionsArray[] = $chartJsTooltips;
                
                $multiRows = isset($chartToPlot['gsurveyLikertEnableMultipleRows']) ? $chartToPlot['gsurveyLikertEnableMultipleRows'] == 1 : false;
                MAXICHARTSAPI::getLogger()->debug("AXIS TITLES " . $chartType . ": " . $xaxislabel . ' / ' . $yaxislabel);
                
                $xaxisScaleLabel = "scaleLabel: {
        display: ";
                $xaxisScaleLabel .= empty($xaxislabel) ? 'false' : 'true';
                $xaxisScaleLabel .= ",labelString: '" . strval($xaxislabel) . "'";
                $xaxisScaleLabel .= "},";
                
                $yaxisScaleLabel = "scaleLabel: {
        display: ";
                $yaxisScaleLabel .= empty($yaxislabel) ? 'false' : 'true';
                $yaxisScaleLabel .= ",labelString: '" . strval($yaxislabel) . "'";
                $yaxisScaleLabel .= "},";
                
                MAXICHARTSAPI::getLogger()->debug("xaxislabel : " . $xaxisScaleLabel);
                MAXICHARTSAPI::getLogger()->debug("yaxislabel : " . $yaxisScaleLabel);
                $chartJsScalesOptions = '';
                $x_stacked_option = $x_stacked ? "true" : "false";
                $y_stacked_option = $y_stacked ? "true" : "false";
                MAXICHARTSAPI::getLogger()->debug("y_stacked : " . $y_stacked . ' / ' . $y_stacked_option);
                MAXICHARTSAPI::getLogger()->debug("x_stacked : " . $x_stacked . ' / ' . $x_stacked_option);
                $stepSizeX = ! empty($x_step_size) ? 'stepSize:' . $x_step_size . ',' : '';
                $stepSizeY = ! empty($y_step_size) ? 'stepSize:' . $y_step_size . ',' : '';
                
                if ($chartType == 'bar' || $chartType == 'horizontalBar' || $chartType == 'line') {
                    MAXICHARTSAPI::getLogger()->debug("building chartjs options scale: ");
                    /*
                     * scales types
                     * linear
                     * logarithmic
                     * category
                     * time
                     */
                    $xaxes = "xAxes: [{
                        id: 'x-axis-0'," . $xaxisScaleLabel;
                    $xaxes .= ! empty($x_scale_type) ? "type: '" . $x_scale_type . "'," : "";
                    $xaxes .= "display: ".($xaxis_display?'true':'false').",";
                    $xaxes .= "
                        stacked:" . $x_stacked_option . ",
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            beginAtZero: true,
                            autoSkip: false,
                            " . $stepSizeX . "
                        }
                    }]";
                    
                    $yaxes = "yAxes: [{
      id: 'y-axis-0'," . $yaxisScaleLabel;
                    $yaxes .= "display: ".($yaxis_display?'true':'false').",";
                    $yaxes .= ! empty($y_scale_type) ? "type: '" . $y_scale_type . "'," : "";
                    $yaxes .= "     
        stacked:" . $y_stacked_option . ",
      gridLines: {
        display: true,
        lineWidth: 1,
        color: 'rgba(0,0,0,0.30)'
      },
      ticks: {
        beginAtZero:true,
        mirror:false,
        suggestedMin: 0,
		" . $stepSizeY . "
      },
      afterBuildTicks: function(chart) {

      }
    }]";
                    
                    $chartJsScalesOptions = "scales: {" . $xaxes . "," . $yaxes . "}";
                } else {
                    MAXICHARTSAPI::getLogger()->debug("Chart not bar or hbar : " . $chartType);
                }
                
                MAXICHARTSAPI::getLogger()->trace("Chart js scales options : " . $chartJsScalesOptions);
                if (! empty($chartJsScalesOptions)) {
                    $chartjsOptionsArray[] = apply_filters('filter_chartjs_scale_options', $chartJsScalesOptions);
                }
                if (isset($chartToPlot['label'])) {
                    $chartTitleCleaned = apply_filters('mcharts_filter_chart_title', $chartToPlot['label']);
                    if (empty($chartTitleCleaned)) {
                        MAXICHARTSAPI::getLogger()->error("Empty chart title after cleaning");
                    }
                } else {
                    MAXICHARTSAPI::getLogger()->warn("Chart labels not set");
                }
                
                MAXICHARTSAPI::getLogger()->debug("Chart js options : " . $chart_js_options);
                if ($chart_js_options && ((is_array($chart_js_options) && count($chart_js_options) > 0) || ! empty($chart_js_options))) {
                    $chartjsOptionsArray[] = apply_filters('mcharts_filter_chart_js_options', $chart_js_options);
                } else {
                    
                    $legendOption = "legend: {display: " . $display_legend . " }";
                    $chartjsOptionsArray[] = apply_filters('mcharts_filter_legend_option', $legendOption);
                    
                    $titleOption = "title:{display: " . $display_title . ",text: '" . $chartTitleCleaned . "',padding:20}";
                    $chartjsOptionsArray[] = apply_filters('mcharts_filter_title_option', $titleOption);
                }
                
                $containerDimensions = 'width:' . $width . '; height:' . $height . '; ';
                MAXICHARTSAPI::getLogger()->debug($containerDimensions);
                $chartWrappingTag = 'div';
                $canvasStyle = '';
                $containerMargin = 'margin:' . $margin . ';';
                if ((! empty($float) && $float == true) || $position == 'float') {
                    MAXICHARTSAPI::getLogger()->debug("floating graphs... : " . $float);
                    $additionalStyle = 'float:left;';
                } else {
                    $additionalStyle = 'display:flex;';
                }
                
                if ((! empty($center) && $center == true) || $position == 'center') {
                    MAXICHARTSAPI::getLogger()->debug("center graphs... : " . $center);
                    // $containerDimensions = 'height: 320px;width: 40%;';
                    $containerMargin = 'margin: 0px auto;';
                    // display: flex; max-width: 100%; /*! width: 100%; */ /*! height: auto; */ margin: 5px;
                    $canvasStyle = 'display: block;margin: 0 auto;';
                    // display: block; width: 272px; height: 272px;
                }
                
                if ($height != 'auto') {
                    MAXICHARTSAPI::getLogger()->debug("Height set to : " . $height);
                    $canvasStyle .= 'height:' . $height . ';width: content-box;'; // width: ' . $width. '; height: ' . $height. ';';
                    $additionalOptions = 'responsive: true,
                	maintainAspectRatio: true';
                    
                    $chartjsOptionsArray[] = $additionalOptions;
                } else {
                    MAXICHARTSAPI::getLogger()->debug("Width set to : " . $width);
                    $canvasStyle .= 'width: ' . $width . '; height: ' . $height . ';';
                    
                    $additionalOptions = 'responsive: true,
                	maintainAspectRatio: false';
                    $chartjsOptionsArray[] = $additionalOptions;
                }
                
                if ($chartType === 'halfDoughnut' || $chartType === 'halfPie') {
                    
                    $chartjsOptionsArray[] = 'rotation: 1 * Math.PI,circumference: 1 * Math.PI,';
                }
                
                MAXICHARTSAPI::getLogger()->debug("About to limit labels size for display");
                MAXICHARTSAPI::getLogger()->debug($chartToPlot['labels']);
                if (! isset($chartToPlot['labels']) || empty($chartToPlot['labels'])) {
                    $msg = __('No Labels for chart : ' . $chartId);
                }
                if (! empty($datasets_field)) {
                    MAXICHARTSAPI::getLogger()->debug("Datasets field chosen by user : " . $datasets_field);
                    MAXICHARTSAPI::getLogger()->debug($chartToPlot);
                    
                    $chartToPlot['labels'] = array_map(function ($in) {
                        $maxSize = 30;
                        $out = strlen($in) > $maxSize ? substr($in, 0, $maxSize) . "..." : $in;
                        return $out;
                    }, $chartToPlot['labels']);
                    if (! isset($chartToPlot['labels']) || empty($chartToPlot['labels'])) {
                        $msg = __('No Labels for chart : ' . $chartId);
                        MAXICHARTSAPI::getLogger()->error($msg);
                        // return $msg;
                        $allCharts .= $msg;
                        continue;
                    } else {
                        MAXICHARTSAPI::getLogger()->debug("Labels are:");
                        MAXICHARTSAPI::getLogger()->debug($chartToPlot['labels']);
                    }
                }
                
                MAXICHARTSAPI::getLogger()->trace("Chart options : " . $currentchartOptions);
                // MAXICHARTSAPI::getLogger()->debug($chartToPlot);
                /*
                 * if ($width == 0 || $height == 0 || $canvaswidth == 0 || $canvasheight == 0) {
                 * MAXICHARTSAPI::getLogger()->warn('One dimension is 0 !');
                 * MAXICHARTSAPI::getLogger()->warn($width);
                 * MAXICHARTSAPI::getLogger()->warn($height);
                 * MAXICHARTSAPI::getLogger()->warn($canvaswidth);
                 * MAXICHARTSAPI::getLogger()->warn($canvasheight);
                 * }
                 */
                
                MAXICHARTSAPI::getLogger()->debug($additionalStyle);
                
                MAXICHARTSAPI::getLogger()->debug($containerMargin);
                $remove_branding = ($active_license_type > 0);
                $chart_brand_message = __("Create your first MaxiChart");
                $chart_brand_link = "https://maxicharts.com/?utm_source=".$_SERVER['SERVER_NAME']."&utm_medium=client_site&utm_campaign=2020_maxicharts_branding";
                $relativewidth = trim($atts['relativewidth']);
                MAXICHARTSAPI::getLogger()->debug($relativewidth);
                
                MAXICHARTSAPI::getLogger()->debug("switching " . $dataName . " of type " . $chartType);
                switch ($chartType) {
                    case 'linearGauge':
                        MAXICHARTSAPI::getLogger()->debug("Linear Gauge");
                        $currentchart = self::buildContainersAndCanvas($chartWrappingTag, $class, $canvasStyle, $currentChartId, $information_source, $remove_branding, $chart_brand_link, $chart_brand_message);
                        //var barChartData10 = {labels: [], datasets: []};
                        $empty_data = false;
                        
                        $currentchartData = 'var '.$dataName . ' = {';
                        /*
                        $currentchartData .= '{labels: ["test-1"],';
                        if ($empty_data){
                            $currentchartData .= "datasets: []";
                        } else {
                            $currentchartData .= "datasets: [
        		          ]";
                        }
                      
                        */
                        
                        $currentchartData .= "labels: [],
	datasets: [{
            label: 'ENDO Min',
			data: [-1],
			width: 2,
			offset: 30,
			hoverBackgroundColor: 'grey',
			backgroundColor: '#e1e113'
		},{
            label: 'ENDO Max',
			data: [2],
			width: 2,
			offset: 30,
			hoverBackgroundColor: 'grey',
			backgroundColor: '#e1e113'
		}, {
            label: 'OMNI Min',
			data: [-2],
			width: 2,
			offset: 60,
			hoverBackgroundColor: 'grey',
			backgroundColor: '#fd97e3'
		},  {
            label: 'OMNI Max',
			data: [1],
			width: 2,
			offset: 60,
			hoverBackgroundColor: 'grey',
			backgroundColor: '#fd97e3'
		}, 

	]";
                        $currentchartData .= "};";
                        
                        $chartjsOptionsArray = [];
                       
                        $chartjsOptionsArray[] = 'responsive: true';
                        $chartjsOptionsArray[] = 'animationEasing: "easeOutElastic"';
                        $chartjsOptionsArray[] = "legend: {
			                    display: true
			                }";
                        $chartjsOptionsArray[] = "scale: {
			                    horizontal: true,
			                    range: {
					startValue: -2,
					endValue: 2
				},
				responsive: true,
				font: {
					fontName: 'Arial',
					fontSize: 12
				},
				axisWidth: 2,
				axisColor: 'black',
				ticks: {
					majorTicks: {
						interval: 1,
						customValues: [-2,-1,0,1,2],
						width: 6,
						height: 1,
						offset: 0,
						color: '#fff'
					}
				},
				scaleLabel: {
					display: true,
					units: '',
					interval: 1,
					offset: 22,
					color: '#777b80'
				}
			                    
			                }";
                        break;
                    case 'pie':
                    case 'halfPie':
                    case 'doughnut':
                    case 'halfDoughnut':
                    case 'bar':
                    case 'line':
                    case 'horizontalBar':
                    case 'radar':
                    
                        
                        // https://www.chartjs.org/docs/latest/general/responsive.html#important-note
                        $currentchart = self::buildContainersAndCanvas($chartWrappingTag, $class, $canvasStyle, $currentChartId, $information_source, $remove_branding, $chart_brand_link, $chart_brand_message);
                        
                        $currentchartData = 'var ' . $dataName . ' = {';
                        $finalLabels = '';
                        $labelsDatasArray = array();
                        if (isset($chartToPlot['labels']) && is_array($chartToPlot['labels'])) {
                            
                            MAXICHARTSAPI::getLogger()->debug('Labels before clean : ' . implode("/", $chartToPlot['labels']));
                            if (empty($chartToPlot['labels'])) {
                                MAXICHARTSAPI::getLogger()->error("No labels for current chart");
                                MAXICHARTSAPI::getLogger()->error($chartToPlot);
                            }
                            
                            $labelsCleaned = apply_filters('mcharts_filter_chart_labels', $chartToPlot['labels']);
                            
                            if (empty($labelsCleaned)) {
                                MAXICHARTSAPI::getLogger()->error("Cleaning labels destoryed them!");
                                MAXICHARTSAPI::getLogger()->error($labelsCleaned);
                            }
                            
                            $all_numeric = true;
                            foreach ($labelsCleaned as $key) {
                                if (! (is_numeric($key))) {
                                    $all_numeric = false;
                                    break;
                                }
                            }
                            
                            MAXICHARTSAPI::getLogger()->debug("Data set is " . ($all_numeric ? "ALL numeric" : "mixed"));
                            $idx = 0;
                            if (isset($chartToPlot['data'])) {
                                foreach ($labelsCleaned as $labelCleanedKey => $labelCleanedVal) {
                                    $labelsDatasArray[$labelCleanedVal] = $chartToPlot['data'][$idx ++];
                                }
                                
                                if ($all_numeric) {
                                    ksort($labelsDatasArray);
                                }
                                
                                if (empty($labelsDatasArray) && empty($datasets_field)) {
                                    $currentchart = "No labels for graph with data " . $dataName;
                                    MAXICHARTSAPI::getLogger()->error($currentchart);
                                    
                                    $allCharts .= $currentchart;
                                    continue 2;
                                }
                                MAXICHARTSAPI::getLogger()->debug($labelsDatasArray);
                                if ($all_numeric && $chartType == 'line') {
                                    // no labels id all nmueric with x/y notation for lines (see below)
                                } else {
                                    $finalLabels .= implode('","', array_keys($labelsDatasArray));
                                }
                            } else {
                                $errMsg = 'No data to build labels for chart ' . $chartId;
                                MAXICHARTSAPI::getLogger()->error($errMsg);
                                MAXICHARTSAPI::getLogger()->error($chartToPlot);
                                $finalLabels .= implode('","', $labelsCleaned);
                                // return $errMsg;
                            }
                        } else {
                            $currentchart = "No labels for graph with data " . $dataName;
                            
                            MAXICHARTSAPI::getLogger()->error($currentchart);
                            MAXICHARTSAPI::getLogger()->error($chartToPlot['labels']);
                            MAXICHARTSAPI::getLogger()->error($chartToPlot);
                            $allCharts .= $currentchart;
                            $allCharts .= $chartToPlot;
                        }
                        
                        if ($finalLabels) {
                            $currentchartData .= 'labels : ["';
                            $currentchartData .= $finalLabels;
                            $currentchartData .= '"],';
                        }
                        $currentchartDatasets = 'datasets : [';
                        
                        $nbOfDatasets = isset($chartToPlot['datasets']) ? count($chartToPlot['datasets']) : 0;
                        $is_csv = isset($chartToPlot['type']) ? $chartToPlot['type'] === 'csv' : false;
                       // MAXICHARTSAPI::getLogger()->debug('Chart type: ' . $chartToPlot['type'] . ' ' . $nbOfDatasets);
                        if ($is_csv && (isset($chartToPlot['datasets']) && is_array($chartToPlot['datasets']) && count($chartToPlot['datasets']) <= 1)) {
                            MAXICHARTSAPI::getLogger()->info("Charting CSV with $nbOfDatasets datasets");
                            if (! isset($chartToPlot['label'])) {
                                $msg = "No labels for chart " . $chartId;
                                MAXICHARTSAPI::getLogger()->error($msg);
                                // $chartToPlot['label'] = 'no_csv_title';
                                // return $msg;
                            }
                            
                            $currentDataset = '{';
                            $currentDataset .= '
                            		label: "' . self::mcharts_clean_chart_label($chartToPlot['label']) . '",';
                            foreach ($chartToPlot['datasets'] as $datasetName => $datasetDatas) {
                                MAXICHARTSAPI::getLogger()->debug("Processing dataset " . $datasetName);
                                if (isset($datasetDatas['data']) && is_array($datasetDatas['data'])) {
                                    MAXICHARTSAPI::getLogger()->debug("Data found for " . $datasetName);
                                    $currentDataset .= 'data: [' . implode(",", array_values($datasetDatas['data'])) . '],';
                                } else {
                                    MAXICHARTSAPI::getLogger()->error("No data for " . $datasetName);
                                }
                            }
                            
                            // one color per answer if single dataset
                            if (isset($colors) && isset($chartToPlot['labels'])) {
                                MAXICHARTSAPI::getLogger()->debug("Colors " . implode(";", $colors));
                                MAXICHARTSAPI::getLogger()->debug("Labels " . implode(";", $chartToPlot['labels']));
                                $colorArray = array();
                                $idxColor = 0;
                                foreach ($chartToPlot['labels'] as $label) {
                                    if (isset($colors[$idxColor])) {
                                        
                                        $colorArray[] = $colors[$idxColor];
                                        $idxColor ++;
                                    } else {
                                        // MAXICHARTSAPI::getLogger ()->debug($idxColor. ' color not set, looping');
                                        $idxColor = 0;
                                        $colorArray[] = $colors[$idxColor];
                                        $idxColor ++;
                                    }
                                    // MAXICHARTSAPI::getLogger ()->debug('new color to array '.$colors [$idxColor]);
                                }
                                $currentDataset .= 'backgroundColor: ["' . implode('","', $colorArray) . '"],';
                            }
                            
                            $currentDataset .= '}';
                            $currentchartDatasets .= $currentDataset;
                            
                            // end CSV plot
                        } else if ((! isset($atts['list_field_id']) || ! is_numeric($atts['list_field_id'])) && isset($multiRows) && $multiRows || isset($chartToPlot['multisets']) && $chartToPlot['multisets']) {
                            
                            // copy / paste root labels - for radar charts
                            
                            MAXICHARTSAPI::getLogger()->info("Multisets");
                            MAXICHARTSAPI::getLogger()->debug($multiRows);
                            MAXICHARTSAPI::getLogger()->debug($chartToPlot['multisets']);
                            $fill_option = trim($atts['fill']);
                            $idx = 0;
                            // count($chartToPlot ['labels'])
                            if (isset($chartToPlot['datasets'])) {
                                $datasetsArray = [];
                                foreach ($chartToPlot['datasets'] as $datasetName => $datasetDatas) {
                                    MAXICHARTSAPI::getLogger()->debug($datasetName . " : ");
                                    MAXICHARTSAPI::getLogger()->debug($datasetDatas);
                                    // one color per dataset if multiple datasets
                                    if (isset($colors[$idx])) {
                                        $currentColor = $colors[$idx];
                                        $idx ++;
                                    } else {
                                        $idx = 0;
                                        $currentColor = $colors[$idx];
                                        $idx ++;
                                    }
                                    $colorArray = array_fill(0, count($chartToPlot['labels']), $currentColor);
                                    
                                    $currentDataset = '{';
                                    
                                    $currentDataset .= 'label: "' . self::mcharts_clean_chart_label($datasetName) . '",';
                                    if (isset($datasetDatas['data']) && is_array($datasetDatas['data'])) {
                                        MAXICHARTSAPI::getLogger()->debug($datasetName . " : data present");
                                        // data: [20, 10] notation
                                        $currentDataset .= 'data: [' . implode(",", array_values($datasetDatas['data'])) . '],';
                                        /* data: [{
                                        x: 10,
                                        y: 20
                                    }, {
                                        x: 15,
                                        y: 10
                                    }]
                                    */ 
                                        //$currentDataset .= 'data: [' . implode(",", array_values($datasetDatas['data'])) . '],';
                                    } else {
                                        MAXICHARTSAPI::getLogger()->error("No data for " . $datasetName);
                                    }
                                    
                                    if (! empty($datasets_field)) {
                                        MAXICHARTSAPI::getLogger()->debug($datasetName . " : dataset field " . $datasets_field);
                                        // radar background colors
                                        $currentDataset .= 'backgroundColor:color("' . $currentColor . '").alpha(0.1).rgbString(),';
                                        $currentDataset .= 'borderColor:"' . $currentColor . '",';
                                        $currentDataset .= 'pointBorderColor:"' . $currentColor . '",';
                                        $currentDataset .= 'fill: "' . $fill_option . '",';
                                    } else {
                                        MAXICHARTSAPI::getLogger()->debug($datasetName . " : no dataset field chosen by user set background color array");
                                        if ($chartType == 'line') {
                                            MAXICHARTSAPI::getLogger()->debug($datasetName . " : line type");
                                            $currentDataset .= 'backgroundColor: "' . $currentColor . '",';
                                            $currentDataset .= 'fill: "' . $fill_option . '",';
                                        } else {
                                            MAXICHARTSAPI::getLogger()->debug($datasetName . " : NOT line type");
                                            $currentDataset .= 'backgroundColor: ["' . implode('","', $colorArray) . '"],';
                                        }
                                    }
                                    
                                    
                                    // datalabels
                                    if (!empty($datalabels)) {
                                        $currentDataset .= 'datalabels: {
                                            anchor: \'center\',
                                            align: \'center\',
                                        }';                                     
                                    }
                                    
                                    $currentDataset .= '}';
                                    $datasetsArray[] = $currentDataset;
                                }
                                $currentchartDatasets .= implode(',', $datasetsArray);
                            } else {
                                MAXICHARTSAPI::getLogger()->error("No datasets set");
                                MAXICHARTSAPI::getLogger()->error($chartToPlot);
                            }
                        } else {
                            MAXICHARTSAPI::getLogger()->info("Single set");
                            MAXICHARTSAPI::getLogger()->info("All Numeric: " . $all_numeric);
                            $currentDataset = '{';
                            $currentDataset .= '
                            		label: "' . self::mcharts_clean_chart_label($chartToPlot['label']) . '",';
                            if (isset($chartToPlot['data']) && is_array($chartToPlot['data'])) {
                                $dataArray = [];
                                if ($all_numeric && $type === 'line') {
                                    MAXICHARTSAPI::getLogger()->debug("All numeric line type");
                                    
                                    $dataArrayItems = [];
                                    foreach ($labelsDatasArray as $x => $y) {
                                        $dataArrayItems[] = "{x: " . $x . ",y: " . $y . "}";
                                    }
                                    $dataArray = implode(",", $dataArrayItems);
                                } else {
                                    $dataArray = implode(",", array_values($labelsDatasArray));
                                }
                                $currentDataset .= 'data: [' . $dataArray . '],';
                            } else {
                                MAXICHARTSAPI::getLogger()->error("No labels for " . $datasetName);
                            }
                            
                            if ($type === 'line') {
                                // same color for all points in dataset
                                $currentDataset .= 'backgroundColor: "' . $colors[0] . '",';
                                $currentDataset .= 'fill: "' . $fill_option . '",';
                            } else if ($type == 'radar') {
                                // $currentDataset .= 'backgroundColor: "' . $colors[0] . '",';
                                $currentColor = $colors[0];
                                $currentDataset .= 'backgroundColor:color("' . $currentColor . '").alpha(0.1).rgbString(),';
                                $currentDataset .= 'borderColor:"' . $currentColor . '",';
                                $currentDataset .= 'pointBorderColor:"' . $currentColor . '",';
                            } else {
                                // one color per answer if single dataset
                                if (isset($colors) && isset($chartToPlot['labels'])) {
                                    MAXICHARTSAPI::getLogger()->debug("Colors " . implode(";", $colors));
                                    MAXICHARTSAPI::getLogger()->debug("Labels " . implode(";", $chartToPlot['labels']));
                                    $colorArray = array();
                                    $idxColor = 0;
                                    foreach ($chartToPlot['labels'] as $label) {
                                        if (isset($colors[$idxColor])) {
                                            // $colorArray[] = $colors[$idxColor];
                                            // MAXICHARTSAPI::getLogger ()->debug($idxColor. ' color set');
                                            $colorArray[] = $colors[$idxColor];
                                            $idxColor ++;
                                        } else {
                                            // MAXICHARTSAPI::getLogger ()->debug($idxColor. ' color not set, looping');
                                            $idxColor = 0;
                                            $colorArray[] = $colors[$idxColor];
                                            $idxColor ++;
                                        }
                                        
                                        // MAXICHARTSAPI::getLogger ()->debug('new color to array '.$colors [$idxColor]);
                                    }
                                    $currentDataset .= 'backgroundColor: ["' . implode('","', $colorArray) . '"],';
                                }
                            }
                            
                            
                            // datalabels
                            if (!empty($datalabels)) {
                                $currentDataset .= 'datalabels: {
                                            anchor: \'center\',
                                            align: \'center\',
                                        }';
                            }
                            
                            $currentDataset .= '}';
                            
                            $currentchartDatasets .= $currentDataset;
                        }
                        
                        $currentchartDatasets .= ']';
                        MAXICHARTSAPI::getLogger()->trace($currentchartDatasets);
                        $currentchartData .= $currentchartDatasets;
                        if (empty($currentchartDatasets)) {
                            $msg = __("No datasets for graph with data") . ' ' . $dataName;
                            MAXICHARTSAPI::getLogger()->warn($msg);
                            return $msg;
                        } else {
                            MAXICHARTSAPI::getLogger()->trace($currentchartDatasets);
                        }
                        $currentchartData .= '};';
                        break;
                    
                    case 'table':
                        $allCharts .= apply_filters('mcharts_display_table_from_chart_data_filter', $chartToPlot, $atts);
                        continue 2;
                    default:
                        MAXICHARTSAPI::getLogger()->error("Unknown graph type : " . $chartType . ' / ' . $type);
                        continue 2;
                }
                
                if ($type == 'table') {
                    $msg = "No js needed for table type " . $chartTitleCleaned . ' / ' . $type;
                    MAXICHARTSAPI::getLogger()->warn($msg);
                    continue;
                }
                
                if (empty($currentchartData)) {
                    $msg = "No data for chart " . $chartTitleCleaned . ' / ' . $type;
                    $msg .= ! empty($reportFields['error']) ? $reportFields['error'] : '';
                    MAXICHARTSAPI::getLogger()->warning($msg);
                    return $msg;
                } else if ($data_only) {
                    $allChartsDatas[$dataName]['data'] = array_values($labelsDatasArray);
                    $allChartsDatas[$dataName]['labels'] = array_keys($labelsDatasArray);
                }
                
                // compute basic math indicators
                // $chartToPlot['datasets']
                $avg = isset($chartToPlot['average']) ? $chartToPlot['average'] : '';
                $std_dev = isset($chartToPlot['std_dev']) ? $chartToPlot['std_dev'] : '';
                $min = isset($chartToPlot['min']) ? $chartToPlot['min'] : '';
                $max = isset($chartToPlot['max']) ? $chartToPlot['max'] : '';
                
                $annotationsArray = self::prepareAnnotations($annotation, $annotation_mean, $avg, $annotation_std_dev, $std_dev);
                if (!empty($annotation_min) && !empty($min)) {
                    $annotationsArray = array_merge($annotationsArray, self::prepareMinMaxAnnotation($annotation_min, $min));
                }
                if (!empty($annotation_max) && !empty($max)) {
                    $annotationsArray = array_merge($annotationsArray, self::prepareMinMaxAnnotation($annotation_max, $max));
                }
                if (! empty($annotationsArray)) {
                    $currentChartAnnotationOptions = "annotation: {drawTime: 'afterDatasetsDraw',
						events: ['click'],
						annotations: [ ";
                    MAXICHARTSAPI::getLogger()->debug(count($annotationsArray) . " annotations created");
                    $currentChartAnnotationOptions .= implode(',', $annotationsArray);
                    $currentChartAnnotationOptions .= "]}";
                    $chartjsOptionsArray[] = $currentChartAnnotationOptions;
                }
                
                $allCharts .= apply_filters('maxicharts_current_chart_filter', $currentchart, array(
                    "id" => $currentChartId,
                    "options" => $chartOptionsId,
                    'data' => $dataName,
                    'type' => $chartType
                ), $atts);
                
                
                // create custom tooltip
                //$currentChartCustomFunctions = $this->getCustomJsFunctions();
                
                // create option JS var
                $currentchartOptions = 'var ' . $chartOptionsId . ' = {';
                $currentchartOptions .= implode(',', $chartjsOptionsArray);
                $currentchartOptions .= '};';
                MAXICHARTSAPI::getLogger()->debug($currentchartOptions);
                
                // $csvArray = apply_filters('mcharts_modify_chartjs_options', $chartOptionsId, $atts);
                $chartArrayItemJson = 'window.maxicharts_reports["' . $currentChartId . '"] = { options: ' . $chartOptionsId . ', data: ' . $dataName . ', type: \'' . $chartType . '\' };';
                //self::getL
                MAXICHARTSAPI::getLogger()->debug($chartArrayItemJson);
                
                $current_chart_js_code = 'var color = Chart.helpers.color;
                		' . $currentchartOptions . '
                		' . $currentchartData . '
                        ' . $chartArrayItemJson;
                
                // put all js code after html charts
                
                $javascript_code_to_inject .= $current_chart_js_code;
                MAXICHARTSAPI::getLogger()->debug("HTML for Chart " . $currentChartId . " :");
                MAXICHARTSAPI::getLogger()->debug($current_chart_js_code);
                MAXICHARTSAPI::getLogger()->debug('------------------------------------');
                
                
                
            } //  end current chart from report field
            
            $js_functions = '';//self::getGlobalCustomJsFunctions();           
            
            // add script js            
            $allCharts .= '<script type="text/javascript">';
            $allCharts .= $js_functions;
            $allCharts .= 'window.maxicharts_reports = window.maxicharts_reports || {};';
            $allCharts .= $javascript_code_to_inject;
            $allCharts .= '</script>';
            
            MAXICHARTSAPI::getLogger()->debug(count($reportFields) . ' charts created');
            
            if ($data_only) {
                MAXICHARTSAPI::getLogger()->debug('Return DATA only');
                MAXICHARTSAPI::getLogger()->debug($allChartsDatas);
                wp_send_json($allChartsDatas);
                
                // return $allChartsDatas;
            } else {
                MAXICHARTSAPI::getLogger()->debug('Returning complete graphs');
            }
            
            MAXICHARTSAPI::getLogger()->trace($allCharts);
            
            return apply_filters('maxicharts_all_charts_filter', $allCharts, $atts);
        }

        
        static function buildContainersAndCanvas($chartWrappingTag, $class, $canvasStyle, $currentChartId, $information_source, $remove_branding, $chart_brand_link, $chart_brand_message) {
         
            $currentchart = '<' . $chartWrappingTag . ' class="' . $class . ' maxicharts_reports-wrap">';
            $currentchart .= '<canvas style="' . $canvasStyle . '" id="' . $currentChartId . '" class="maxicharts_reports_canvas">';
            $currentchart .= '</canvas>'; // end canvas
            $currentchart .= empty($information_source) ? '' : '<span class="information_source">' . $information_source . '</span>';
            $branding_div = $remove_branding ? '' : '<div class="chart_branding"><a target="_blank" href="' . $chart_brand_link . '">' . $chart_brand_message . '</a></div>';
            $currentchart .= $branding_div;
            $currentchart .= '</' . $chartWrappingTag . '>'; // end maxicharts_reports-wrap
            
            MAXICHARTSAPI::getLogger()->trace($currentchart);
            return $currentchart;
        }
        
        function replace_carriage_return($replace, $string)
        {
            /*
             * return str_replace(array(
             * "\n\r",
             * "\n",
             * "\r"
             * ), $replace, $string);
             */
            $stringCleaned = trim(preg_replace('/\s+/', $replace, $string));
            return $stringCleaned;
        }

        function removeQuotesAndConvertHtml($str)
        {
            $res = preg_replace('/["]/', '', wp_strip_all_tags($str));
            $res = html_entity_decode($res);
            $res = $this->replace_carriage_return(' ', $res);
            
            return $res;
        }

        static function removeQuotes($string)
        {
            return preg_replace('/["]/', '', $string); // Removes special chars.
        }

        static function clean($string)
        {
            $string = str_replace(' ', '', $string); // Replaces all spaces with hyphens.
            
            return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
        }
    }
}

call_user_func(array(
    new maxicharts_reports(__FILE__),
    'init_hooks'
));