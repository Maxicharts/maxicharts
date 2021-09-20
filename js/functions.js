jQuery(document).ready(
		function($) {

			//console.log(window.maxicharts_reports);

			window.maxicharts_reports = window.maxicharts_reports || {};
			window.maxicharts_reports_init = window.maxicharts_reports_init || {};
			var size = Object.keys(window.maxicharts_reports).length;
			var initSize = Object.keys(window.maxicharts_reports_init).length;
			
			// console.log("number of charts : "+size);
			$.each(window.maxicharts_reports, function(index, value) {

				console.log(index + ' => ' + value);
				// console.log(document.getElementById(index));
				var ctx = document.getElementById(index).getContext("2d");
//				console.log(value.type);

				var chartjsType = '';// value.type.toLowerCase();
				var chartData = value.data;
				// console.log(chartData);
				var chartOptions = value.options;
				// console.log(chartOptions);
				switch (value.type) {
					case 'doughnut' :
					case 'halfDoughnut':
						chartjsType = 'doughnut'
						break;
					case 'PolarArea' :
						chartjsType = 'polarArea';
						break;
					case 'horizontalBar' :
						chartjsType = 'horizontalBar';
						break;
					case 'bar' :
						chartjsType = 'bar';
						break;
					case 'line' :
						chartjsType = 'line';
						break;
					case 'radar' :
						chartjsType = 'radar';
						break;
					case 'pie' :
					case 'halfPie':
                        chartjsType = 'pie';
                        break;
					case 'linearGauge':
						chartjsType = 'linearGauge';
						break;
					default :
						console.error('Unknown chart type: '+value.type);
						chartjsType = 'pie';

				}

				if (ctx && ctx != null && chartjsType && chartData && chartOptions && chartjsType != null && chartData != null && chartOptions != null && typeof chartjsType !== 'undefined' && typeof chartData !== 'undefined'
						&& typeof chartOptions !== 'undefined') {
					// console.log(index);
					// console.log(window.maxicharts_reports_init);
					try {		
						/*
						console.log(chartjsType);
						console.log(chartData);
						console.log(chartOptions);
						*/
						chartjsArgs = {
								type : chartjsType,
								data : chartData,
								options : chartOptions,
							};
						console.log(chartjsArgs);
						var newChart = new Chart(ctx, chartjsArgs);
					
						console.log(index + ") Chart created");			
						
						window.maxicharts_reports_init[index] = newChart;
					} catch (err) {
						console.error("MaxiCharts::Error creating chart " + index);
						console.error(err);
						console.error(ctx);
						console.error(chartjsType);
						console.error(chartData);
						console.error(chartOptions);
					}

				}
			});
		});