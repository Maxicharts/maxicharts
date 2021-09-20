
(function(Chart) {
	
	var helpers = Chart.helpers;

	var globalDefaults = Chart.defaults.global;
	
	Chart.defaults._set('global', {
		elements: {
			gaugerect: {
				backgroundColor: '#0fa',
				borderWidth: 3,
				borderColor: globalDefaults.defaultColor,
				borderCapStyle: 'butt',
				fill: true, // do we fill in the area between the line and its base axis
				width: 6,
				height: 6,
				shape: 'rect',
				pointer: 'bar',
				text: 'Pointer Text',
		        fontSize: 14,
		        fontFamily: 'Arial',
		        offset: 0,
		        rotate: 0,
		        color: '#000'
			}
		}
	});

	Chart.elements.Gaugerect = Chart.elements.Rectangle.extend({

		rangeColorImage: null,

        generateImage: function(colors, widths) {
            var width = 0;
            for (var i = 0; i < widths.length; i++)
                width += widths[i];
            var canvas = document.createElement('canvas'),
                c = canvas.getContext('2d');
            //document.body.appendChild(canvas);
            canvas.width = width;
            canvas.height = 1;
            var gw2 = widths[0];
            var grd = c.createLinearGradient(0, 0, width, 0);
            grd.addColorStop(0, colors[0]);
            for (var k = 0; k < colors.length; k++) {
                if ((k + 1) < colors.length) {
                    gw2 += widths[k + 1] / 2;
                    var ks = gw2 / width;
                    if(ks > 1) ks = 1;
                    grd.addColorStop(ks, colors[k + 1]);
                } else grd.addColorStop(1, colors[k]);
                c.closePath();
                if ((k + 1) < colors.length)
                    gw2 += widths[k + 1] / 2;
            }
            c.fillStyle = grd;
            c.fillRect(0, 0, width, 20);
            var imgd = c.getImageData(0, 0, canvas.width, 1);
            return imgd;
        },
        getColor: function(val, scale) {
            var out = 0;
            var rc = 0;
            var gc = 0;
            var bc = 0;
            var ac = 1;
            var opts = this.getMeOptions();
            //	If image data did not filled yet
            if (this.rangeColorImage === null) {
                var colors = [];
                var widths = [];
                //colors.push(startColor);
                helpers.each(opts.colorRanges, function(cl, i) {
                    if (i === 0)
                        widths.push((cl.breakpoint - this._Scale.options.range.startValue) * scale);//this.scaleValue);
                    else
                        widths.push((cl.breakpoint - opts.colorRanges[i - 1].breakpoint) * scale);//this.scaleValue);
                    colors.push(cl.color);

                }, this);
                this.rangeColorImage = this.generateImage(colors, widths);
            }


            var start = this._Scale.options.range.startValue;

            var k = Math.ceil((val - start) * scale);//this.scaleValue);
            rc = this.rangeColorImage.data[k * 4 + 0];
            gc = this.rangeColorImage.data[k * 4 + 1];
            bc = this.rangeColorImage.data[k * 4 + 2];
            ac = this.rangeColorImage.data[k * 4 + 3];

            return 'rgba(' + rc + ', ' + gc + ', ' + bc + ', ' + ac/256 + ')';
        },

        getMeOptions: function() {
        	var me = this;
        	var i = me._datasetIndex;
        	var opts = me._chart.config.data.datasets[i];
        	return opts;
        },

		draw: function() {
			var me = this;
			var vm = me._view;
			var ctx = me._chart.ctx;
			var opts = me.getMeOptions();
			var horizontal = me._model.horizontal;
			var defaults = me._chart.options.elements.gaugerect;
			
			ctx.save();
			if (typeof(opts.colorRanges) == 'object' && opts.colorRanges.length > 0) {
				var clr = me.getColor(me._model.value, me._model.scaleValue);
                ctx.fillStyle = clr;
            } else
				ctx.fillStyle = me._model.backgroundColor;
			
            
            opts.pointer = opts.pointer ? opts.pointer : defaults.pointer;

            if (typeof opts.img !== 'undefined' && opts.img !== null) {
            	var imgsrc = opts.img;
            	if(typeof opts.imageRanges !== 'undefined' && typeof opts.imageRanges.length !== 'undefined' && opts.imageRanges.length > 0){
					for(var i in opts.imageRanges){
						var r = opts.imageRanges[i];
						if(me._model.value >= r.startpoint && me._model.value < r.breakpoint && typeof r.img !== 'undefined' && r.img !== ''){
							imgsrc = r.img;
							break;
						}
					}
				}
				if(typeof this.imgs === 'undefined') this.imgs = [];
				var imbuffer = null;
				for(var i in this.imgs){
					if(this.imgs[i].src === imgsrc) imbuffer = this.imgs[i]; break;
				}
                if(imbuffer === null) imbuffer = new Image(); imbuffer.src = imgsrc; this.imgs.push(imbuffer);
                
                var width = me._view.width = opts.width ? opts.width : defaults.width;
                var height = me._view.height = opts.height ? opts.height : defaults.height;
                if (horizontal) {
                    me._view.x = vm.head;
                    var x = vm.head - width / 2;
                    var y = vm.y + height / 2;
                } else {
                    var x = vm.x - width / 2;
                    var y = vm.y - height / 2;
                }
                if(imbuffer.complete){
                    ctx.beginPath();
                    ctx.drawImage(imbuffer, 0, 0, imbuffer.width, imbuffer.height, x, y, width, height);
                    ctx.restore();
                } else {
                    imbuffer.onload = function(){
                        ctx.beginPath();
                        ctx.drawImage(imbuffer, 0, 0, imbuffer.width, imbuffer.height, x, y, width, height);
                        ctx.restore();
                    }
                }
                
                return;
            }

            if(typeof opts.pointer === 'undefined' || opts.pointer === 'bar'){
            	
                // Stroke Line
                ctx.beginPath();
                
                ctx.rect(
                    vm.x,
                    vm.y,
                    vm.width,
                    vm.height
                );
                
                ctx.fill();
                ctx.restore();
            }
            
            if (opts.pointer === 'point') {
            	
            	var shape = opts.shape ? opts.shape : defaults.shape;
            	var width = me._view.width = opts.width ? opts.width : defaults.width;
            	var height = me._view.height = opts.height ? opts.height : defaults.height;
                
                if (shape == 'circle') {
                	
                	if (horizontal) {
                        var x = me._view.x = vm.head;
                    	var y = vm.y;
                    } else {
                        var x = vm.x;
                    	var y = vm.y;
                    }
                	/*
                    this.leftX = x - this.height / 2;
                    this.rightX = x + this.height / 2;
                    this.leftY = y - this.height / 2;
                    this.rightY = y + this.height / 2;
                    ctx.arc(x, y, r, sAngle, eAngle, counterclockwise);
                    */
                    
                    var r = width / 2;
                    var sAngle = 0;
                    var eAngle = Math.PI * 2;
                    var counterclockwise = false;
                    ctx.beginPath();
                    ctx.arc(x, y, r, sAngle, eAngle, counterclockwise);
                    ctx.fill();
            		ctx.restore();
                }
                if (shape == 'rect') {
                	if (horizontal) {
                        me._view.x = vm.head;
                        var x = vm.head - width / 2;
                    	var y = vm.y - height / 2;
                    } else {
                        var x = vm.x - width / 2;
                    	var y = vm.y - height / 2;
                    }
                	
                    ctx.beginPath();
                    ctx.rect(x, y, width, height);
                    ctx.fill();
            		ctx.restore();
                }
                if (shape == 'triangle') {
                	
                    if (horizontal) {
                        var x1 = me._view.x = vm.head,
                            y1 = vm.y + height/2,
                            x2 = x1 - width/2,
                            y2 = y1 - height,
                            x3 = x2 + width,
                            y3 = y2;
                    } else {
                        var x1 = vm.x - width/2 + width,
                            y1 = vm.y,
                            x2 = x1 + width,
                            y2 = y1 - height / 2,
                            x3 = x2,
                            y3 = y2 + height;
                    }
					
					ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.lineTo(x3, y3);
                    ctx.fill();
            		ctx.restore();
                }
                if (shape == 'inverted-triangle') {
                    
                    if (horizontal) {
                        var x1 = me._view.x = vm.head,
                            y1 = vm.y - height/2,
                            x2 = x1 - width/2,
                            y2 = y1 + height,
                            x3 = x2 + width,
                            y3 = y2;
                    } else {
                        var x1 = vm.x + width/2 + width,
                            y1 = vm.y,
                            x2 = x1 - width,
                            y2 = y1 - height / 2,
                            x3 = x2,
                            y3 = y2 + height;
                    }
					
					ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.lineTo(x3, y3);
                    ctx.fill();
            		ctx.restore();
                }
                if (shape == 'bowtie') {
                    if (horizontal) {
                        
                        var x1 = me._view.x = vm.head,
                            y1 = vm.y + width,
                            x2 = x1 - height/2,
                            y2 = y1 - width/2,
                            x3 = x2 + height,
                            y3 = y2;

                        var x11 = vm.head,
                            y11 = vm.y + width,
                            x21 = x11 - height/2,
                            y21 = y11 + width/2,
                            x31 = x21 + height,
                            y31 = y21;
                    } else {
                        
                        var x1 = vm.x,
                            y1 = vm.y,
                            x2 = x1 + width/2,
                            y2 = y1 - height / 2,
                            x3 = x2,
                            y3 = y2 + height;

                       	var x11 = vm.x,
                            y11 = vm.y,
                            x21 = x11 - width/2,
                            y21 = y11 - height / 2,
                            x31 = x21,
                            y31 = y21 + height;
                    }
					
					ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.lineTo(x3, y3);
                    ctx.closePath();
                    ctx.fill();
                    ctx.beginPath();
                    ctx.lineTo(x11, y11);
                    ctx.lineTo(x21, y21);
                    ctx.lineTo(x31, y31);
                    ctx.fill();
            		ctx.restore();
                }
                if (shape == 'diamond') {
                    if (horizontal) {
                        
                        var x1 = me._view.x = vm.head,
                            y1 = vm.y - width/2 + width,
                            x2 = x1 - height/2,
                            y2 = y1 + width/2 + 0.5,
                            x3 = x2 + height,
                            y3 = y2;

                        var x11 = vm.head,
                            y11 = vm.y + width/2 + width,
                            x21 = x11 - height/2,
                            y21 = y11 - width/2,
                            x31 = x21 + height,
                            y31 = y21;
                    } else {
                        
                        var x1 = vm.x - width/2,
                            y1 = vm.y,
                            x2 = x1 + width/2 + 0.5,
                            y2 = y1 - height / 2,
                            x3 = x2,
                            y3 = y2 + height;

                       	var x11 = vm.x + width/2,
                            y11 = vm.y,
                            x21 = x11 - width/2,
                            y21 = y11 - height / 2,
                            x31 = x21,
                            y31 = y21 + height;
                    }
					
					ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.lineTo(x3, y3);
                    ctx.closePath();
                    ctx.fill();
                    ctx.beginPath();
                    ctx.lineTo(x11, y11);
                    ctx.lineTo(x21, y21);
                    ctx.lineTo(x31, y31);
                    ctx.fill();
            		ctx.restore();
                }
            	
            	
            }
            
            if(opts.pointer === 'text'){
                var rotate = opts.rotate ? opts.rotate : defaults.rotate;
            	var text = opts.text ? opts.text : defaults.text;
            	var fontSize = opts.fontSize ? opts.fontSize : defaults.fontSize;
            	var fontFamily = opts.fontFamily ? opts.fontFamily : defaults.fontFamily;
            	var offset = opts.offset ? opts.offset : defaults.offset;
            	var color = opts.color ? opts.color : defaults.color;
            	
            	if(typeof opts.textRanges !== 'undefined' && typeof opts.textRanges.length !== 'undefined' && opts.textRanges.length > 0){
					for(var i in opts.textRanges){
						var r = opts.textRanges[i];
						if(me._model.value >= r.startpoint && me._model.value < r.breakpoint && typeof r.text !== 'undefined' && r.text !== ''){
							text = r.text;
							break;
						}
					}
				}
				
				// Stroke Line
	            ctx.beginPath();
	            ctx.font = fontSize + "px " + fontFamily;
	            var arrayOfThings = [];
	            arrayOfThings.push(text);
				var tlen = helpers.longestText(ctx, ctx.font, arrayOfThings);
				if(horizontal) vm.x = me._view.x = vm.head;
				if(typeof opts.textPosition !== 'undefined' && opts.textPosition === 'center'){
					ctx.translate(vm.x - tlen/2, vm.y + fontSize/3);
				}
				else if(typeof opts.textPosition !== 'undefined' && opts.textPosition === 'right'){
					ctx.translate(vm.x - tlen, vm.y + fontSize/3);
				} else {
	                ctx.translate(vm.x, vm.y + fontSize/3);
				}
				
            	ctx.rotate(Math.PI*2*(rotate/360));
				//ctx.fillStyle = color;
				//ctx.textAlign = "center";
				ctx.fillText(text, 0, 0);
                ctx.restore();
                
            }
			
		},
        
        tooltipPosition: function() {
        	
            var opts = this.getMeOptions();
            var defaults = this._chart.options.elements.gaugerect;
            var shape = opts.shape ? opts.shape : defaults.shape;

            if (typeof opts.img !== 'undefined' && opts.img !== null){
                var vm = this._view;
                if(this._model.horizontal){
                    var x = vm.x;
                    var y = vm.y + vm.height/2;
                } else {
                    var x = vm.x;
                    var y = vm.y - vm.height/2;
                }
                
                return {
                    x: x,
                    y: y
                };
            }
            
            if (this._view && (typeof opts.pointer === 'undefined' || opts.pointer === 'bar')) {
                var vm = this._view;
                return {
                    x: vm.x + (this._model.horizontal ? vm.width : vm.width/2),
                    y: vm.y + (this._model.horizontal ? vm.height/2 : 0),
                };
            }
            
            if (this._view && typeof opts.pointer !== 'undefined' && opts.pointer === 'point') {
                var vm = this._view;
                var x = vm.x;
                var y = vm.y;
                if((shape === 'triangle' || shape === 'inverted-triangle') && !this._model.horizontal)
                    var x = vm.x + vm.width; var y = vm.y;
                 if((shape === 'triangle' || shape === 'inverted-triangle') && this._model.horizontal)
                    var x = vm.x; var y = vm.y;
                return {
                    x: x,
                    y: y
                };
            }
            

		},

        inRange: function(mouseX, mouseY) {
            var inRange = false;
            var opts = this.getMeOptions();
            var defaults = this._chart.options.elements.gaugerect;
            var shape = opts.shape ? opts.shape : defaults.shape;

            if (typeof opts.img !== 'undefined' && opts.img !== null){
                var vm = this._view;
                if(this._model.horizontal)
                    inRange = mouseX >= vm.x - vm.width/2 && mouseX <= vm.x + vm.width/2 && mouseY >= vm.y + vm.height/2 && mouseY <= vm.y + vm.height + vm.height/2;
                else
                    inRange = mouseX >= vm.x - vm.width/2 && mouseX <= vm.x + vm.width/2 && mouseY >= vm.y - vm.height/2 && mouseY <= vm.y + vm.height/2;
                return inRange;
            }

            if (this._view && (typeof opts.pointer === 'undefined' || opts.pointer === 'bar')) {
                var vm = this._view;
                if(vm.width >= 0 && vm.height >= 0)
                    inRange = mouseX >= vm.x && mouseX <= vm.x + vm.width && mouseY >= vm.y && mouseY <= vm.y + vm.height;
                if(vm.width < 0 && vm.height >= 0)
                    inRange = mouseX >= (vm.x + vm.width) && mouseX <= vm.x && mouseY >= vm.y && mouseY <= vm.y + vm.height;
                if(vm.width >= 0 && vm.height < 0)
                    inRange = mouseX >= vm.x && mouseX <= vm.x + vm.width && mouseY >= vm.y + vm.height && mouseY <= vm.y;
                if(vm.width < 0 && vm.height < 0)
                    inRange = mouseX >= (vm.x + vm.width) && mouseX <= vm.x && mouseY >= vm.y + vm.height && mouseY <= vm.y;
            }
            if (this._view && typeof opts.pointer !== 'undefined' && opts.pointer === 'point') {
                var vm = this._view;
                inRange = mouseX >= vm.x - vm.width/2 && mouseX <= vm.x + vm.width/2 && mouseY >= vm.y - vm.height/2 && mouseY <= vm.y + vm.height/2;
                if((shape === 'triangle' || shape === 'inverted-triangle') && !this._model.horizontal)
                    inRange = mouseX >= vm.x + vm.width/2 && mouseX <= vm.x + vm.width + vm.width/2 && mouseY >= vm.y - vm.height/2 && mouseY <= vm.y + vm.height/2;
                 if((shape === 'triangle' || shape === 'inverted-triangle') && this._model.horizontal)
                    inRange = mouseX >= vm.x - vm.width/2 && mouseX <= vm.x + vm.width/2 && mouseY >= vm.y - vm.height/2 && mouseY <= vm.y + vm.height/2;

            }

            return inRange;
        },
        
	});
	
}).call(this, Chart);



