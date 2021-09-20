<?php
if (! defined ( 'ABSPATH' )) {
	exit ();
}

//define ( 'PLUGIN_PATH', trailingslashit ( plugin_dir_path ( __FILE__ ) ) );

require_once __DIR__ . "/mcharts_utils.php";

if (! class_exists ( 'mcharts_table_plugin' )) {
	class mcharts_table_plugin {
		// protected static $logger = null;
		function __construct() {
			// MAXICHARTSAPI::getLogger()->debug("construct gfcr_custom_search_criteria");
			MAXICHARTSAPI::getLogger ()->debug ( "Adding Module : " . __CLASS__ );
			add_filter ( "mcharts_display_table_from_chart_data_filter", array (
					$this,
					"display_table" 
			), 10, 2 );
			
			add_action ( "wp_enqueue_scripts", array (
					$this,
					"maxicharts_table_load_scripts" 
			) );
		}
		function maxicharts_table_load_scripts() {
			$mcr_css = plugins_url ( 'css/maxicharts-tables.css', __FILE__ );
			wp_enqueue_style ( 'maxicharts-table-css', $mcr_css, __FILE__ );
		}
		function add_default_params($defaultsParameters) {
			// CSV source
			$newDefaults = array (
					'title' => '',
					'title_style' => '',
					'table_style' => '' 
			);
			
			return array_merge ( $defaultsParameters, $newDefaults );
		}
		
		function formatNumbers(){
		    //$newValueDisplayed = number_format($newValue, $roundComputedDataTo, $decimalSeparator, $thousandsSeparator);
		}
		
		function display_table($chartToPlot, $atts) {
			$title = $atts ['title'];
			$titleStyle = $atts ['title_style'];
			$tableStyle = $atts ['table_style'];
			$decimal_sep = $atts['decimal_separator'];
			$thousands_sep = $atts['thousands_separator'];
			$roundPrecision = 0;
			
			MAXICHARTSAPI::getLogger ()->debug ( "### display table ###" );
			$result = empty ( $title ) ? '' : '<h3 style="' . $titleStyle . '">' . $title . '</h3>';
			$resultTable = '<table class="rwd-table" style="' . $tableStyle . '">';
			
			MAXICHARTSAPI::getLogger ()->debug ( $chartToPlot );
			
			if (is_array ( $chartToPlot ['datasets'] ) && $chartToPlot ['type'] == 'list') {
				
				$allLabels = $chartToPlot ['labels'];
				$allUniqueLabels = array_unique ( $allLabels );
				sort ( $allUniqueLabels );
				
				// $tableTitle = $atts['title'];
				$idx = 0;
				foreach ( $chartToPlot ['datasets'] as $serieName => $serieData ) {
					$data = $serieData ['data'];
					if ($idx ++ == 0) {
						$resultTable .= '<thead class="rwd-table">';
						$resultTable .= '<tr class="rwd-table">';
						$resultTable .= '<th class="rwd-table"></th><th class="rwd-table">' . implode ( '</th><th class="rwd-table">', array_values ( $allUniqueLabels ) ) . '</th>';
						// $resultTable .= '<th>' . $chartToPlot ['label'] . '</th>';
						$resultTable .= '</tr>';
						$resultTable .= '</thead>';
					}
					$resultTable .= '<tbody class="rwd-table">';
					$resultTable .= '<tr class="rwd-table">';
					// .rwd-table tbody ??
					$resultTable .= '<td  class="rwd-table" data-column="' . '' . '">' . $serieName . '</td>';
					// $cellData = json_decode($chartToPlot ['label']);
					
					// need list of all possible column names
					// foreach ($data as $dataCol => $dataVal) {
					
					foreach ( $allUniqueLabels as $colName ) {
						// $colName
					    $resultTable .= '<td class="rwd-table" data-column="' . $colName . '">' . number_format($data [$colName],$roundPrecision,$decimal_sep,$thousands_sep) . '</td>';
						// $colName
					}
					
					$resultTable .= '</tr>';
				}
			} else {
				$resultTable .= '<tr class="rwd-table">';
				$resultTable .= '<th class="rwd-table">' . $chartToPlot ['label'] . '</th>';
				$resultTable .= '</tr>';
				
				foreach ( $chartToPlot ['labels'] as $label ) {
					$resultTable .= '<tr class="rwd-table">';
					
					$cellData = $chartToPlot ['label'];
					
					$resultTable .= '<td  class="rwd-table" data-column="' . $cellData . '">' . $label . '</td>';
					$resultTable .= '</tr>';
				}
			}
			
			$resultTable .= '</tbody>';
			$resultTable .= '</table>';
			$result .= $resultTable;
			// MAXICHARTSAPI::getLogger()->debug("display_table AFTER");
			MAXICHARTSAPI::getLogger ()->debug ( $resultTable );
			MAXICHARTSAPI::getLogger ()->debug ( "### END display table ###" );
			return $result;
		}
	}
}

new mcharts_table_plugin ();