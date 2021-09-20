jQuery(document).ready(function($) {	

	tinymce.create("tinymce.plugins.maxicharts_button", {

		// url argument holds the absolute url of our plugin directory
		init : function(ed, url) {
			
			//console.log("Current Editor: " +ed.id);
	
			ed.addButton("maxicharts_insert", {
				title : "Insert GF Chart",
				both : false,
				tooltip: 'Insert GF Chart',
				image : "https://maxicharts.com/wp-content/uploads/2017/07/icon-128x128-1.png",
				// uncomment to add menu buttons
				/*type:'menubutton',
				text:'GF Charts Reports',
				menu : [{
					text : 'Insert GF Chart',	*/
					onclick : function() {
						ed.windowManager.open( {
							body : [
								{
								label : 'GF Form ID',
								name : 'gf_form_id',
								type : 'listbox',
								'values' : ed.settings.gfFormsList,// getFormsValues(ed),//
																	// ed.settings.gfFormsList,//
								},
							
								{
									label: 'Include fields ids (comma separated list)',
									name: 'include',
									// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
									type: 'textbox',
									placeholder: 'all by default',
									value : ''
								},
								{
									label: 'Exclude',
									name: 'exclude',
									// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
									type: 'textbox',
									placeholder: 'none by default',
									value : ''
								},
								
							{
								label : 'Type',
								name : 'type',
								type : 'listbox',
								values : [

								{
									text : 'Pie',
									value : 'pie'
								}, {
									text : 'Bar',
									value : 'bar'
								}, {
									text : 'Horizontal Bar',
									value : 'horizontalBar'
								}, {
									text : 'Line',
									value : 'line'
								}, {
									text : 'Doughnut',
									value : 'doughnut'
								},],
							},
							
							{
								label: 'Color Set',
								name: 'color_set',
								// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
								type: 'listbox',
								values : [
			                        { text: 'Blue', value: 'blue' },
			                        { text: 'Green', value: 'green' },
			                        { text: 'Red', value: 'red' },
			                        { text: 'Orange', value: 'orange' },
			                        { text: 'Purple', value: 'purple' },
			                    ]
							},
							{
								label: 'Color Randomize',
								name: 'color_rand',
								// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
								type: 'listbox',
								values : [			                       
			                        { text: 'False', value: '0' },
			                        { text: 'True', value: '1' },
			                    ]
							},
							{
								label: 'Height',
								name: 'height',
								// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
								type: 'textbox',
								value : '400'
							},
							{
								label: 'Tooltip style',
								name: 'tooltip_style',
								// http://archive.tinymce.com/wiki.php/api4:class.tinymce.ui.ListBox
								type: 'listbox',
								values : [
									{ text: 'Show sum and percent', value: 'BOTH' },
			                        { text: 'Only show sum', value: 'SUM' },
			                        { text: 'Only show percent', value: 'PERCENT' },
			                       
			                    ]
							},
							
							{
								selected: 'false',
								label: 'Position',
								name: 'position',
								type: 'listbox',
								values : [
									{ text: 'Center', value: 'center' },
									{ text: 'Float', value: 'float' },			                        
			                    ]
							},
							
							
							{
								label: 'Maximum entries to fetch',
								name: 'maxentries',
								placeholder: '200 by default',
								type: 'textbox',
								values : ''
							},
							
							{
								label: 'Chartjs options (JSON)',
								name: 'chart_js_options',
								type: 'textbox',
								value : ''
							},
							{
								label: 'Search Criteria (JSON)',
								name: 'custom_search_criteria',
								type: 'textbox',
								value : ''
							},
							{
								label: 'Case insensitive',
								name: 'case_insensitive',
								type: 'listbox',
								values : [			                       
			                        { text: 'False', value: '0' },
			                        { text: 'True', value: '1' },
			                    ]
							},
							{
								label: 'Add filter (requires Query Builder add-on)',
								name: 'filter',
								type: 'listbox',
								values : [			                       
			                        { text: 'False', value: '0' },
			                        { text: 'True', value: '1' },
			                    ]
							},
							
							],
			
							onsubmit: function( e ) {
								
								var titreFenetre = 'Add Gravity Form Chart Report';//!_.isUndefined(e.atts.nom) ? e.atts.nom : 'Ajouter un shortcode';
								var balise = 'gfchartsreports';//!_.isUndefined(e.atts.balise) ? e.atts.balise : false;
								var out = '[' + balise;
								for ( var attr in e.data) {
								    // !$(this).val()
								    console.log(attr + ' = '+e.data[attr]);
								    if (e.data[attr]){
									out += ' ' + attr + '="' + e.data[attr] + '"';
								    }
								}
								out += '/]';
								ed.insertContent(out);
							}
						});
					}
					
				// uncomment to add menu buttons
				//}, ]

			}); // button added

			
			

		}, // end of init function

		createControl : function(n, cm) {
			return null;
		},

		getInfo : function() {
			return {
				longname : "MaxiCharts Reports Buttons",
				author : "Maxicharts",
				version : "1"
			};
		}

	});
	

	
	
	tinymce.PluginManager.add("maxicharts_button", tinymce.plugins.maxicharts_button);

});