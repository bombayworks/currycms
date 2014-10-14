(function($){
    $.finder = function(el, options){
        // To avoid scope issues, use 'base' instead of 'this'
        // to reference this class from internal events and functions.
        var base = this;
        
        // Access to jQuery and DOM versions of element
        base.$el = $(el);
        base.el = el;
        
        // Add a reverse reference to the DOM object
        if(base.$el.data("finder-instance"))
        	return;
        base.$el.data("finder-instance", base); 
        
        base.init = function(){
            base.options = $.extend({}, $.finder.defaultOptions, $(this).data('finder'), options);
            if(!base.options.path)
            	base.options.path = base.options.defaultPath;
            if(base.options.multiple && base.options.path.indexOf(base.options.pathSeperator) != -1)
            	base.options.path = base.options.path.split(base.options.pathSeperator);
            // set filter
            var f = base.options.filter;
           	base.options.filter = base.options.filters[0][1];
			if(f) {
				$.each(base.options.filters, function(i, value) {
        			if(f == value[0] || f == value[1])
        				base.options.filter = value[1];
	        	});
			}
            base.bindEvents();
            base.refresh();
        };
        
        base.askOverwrite = function(overwrite) {
			var o = {};
			for(var i=0; i < overwrite.length; ++i) {
				var name = overwrite[i];
				o[name] = confirm('Do you want to overwrite ' + name + '?');
			}
			var action = base.options.action + '?' + $.param({
				action: 'UploadOverwrite',
				overwrite: o
			});
			$.post(action, function(data) {
				if(!data.status)
					alert(data.error);
				else
					base.refresh();
			});
        }
        
        base.bindEvents = function(rebind) {
        	base.$el.find('.folder').each(function() {
        		if(this.addEventListener) {
					this.addEventListener('dragenter', function(e) { e.stopPropagation(); e.preventDefault(); }, false);
					this.addEventListener('dragover', function(e) { e.stopPropagation(); e.preventDefault(); }, false);
					this.addEventListener('drop', function(e) {
						e.stopPropagation();
						e.preventDefault();
						var dt = e.dataTransfer;
						var files = dt.files;
						if(files.length)
							base.uploadFiles($(this).data('finder').action, files);
					}, false);
        		}
        	});
        	base.$el.find('.upload').each(function() {
        		var action = $(this).attr('href');
                var $wrapper = $('<div class="upload-input"></div>').insertAfter(this);
                $wrapper
                    .css({position: 'absolute', overflow: 'hidden'})
                    .offset($(this).offset())
                    .width($(this).outerWidth())
                    .height($(this).outerHeight());
				var $fileInput = $('<input type="file" name="file[]" multiple/>')
                    .appendTo($wrapper)
					.change(function(e) {
						if(this.files) {
							if(this.files.length)
								base.uploadFiles(action, this.files);
						} else {
							base.uploadFilesFallback(action, this);
						}
					});
        	});
        	var $select = base.$el.find('select');
        	$.each(base.options.filters, function(i, value) {
        		$select.append('<option value="'+value[1]+'"'+(value[1]==base.options.filter?' selected="selected"':'')+'>'+value[2]+'</option>');
        	});
        	$select.bind('change', function() {
        		base.options.filter = $(this).val();
        		base.refresh();
        	});
        	if(!rebind) {
				$(document).on('finder-reload', function() {
					base.refresh();
				});
        		base.$el.delegate('.navigate', 'mousedown', function(startEvent) {
        			var isDragging = null;
        			var $file = $(this);
        			var $li = $(this).closest('li');
        			var origin = $li.offset();
        			var $helper = $li.clone();
        			var target = null;
        			var mouseMove = function(e) {
        				$helper.offset({
        					left: origin.left + e.pageX - startEvent.pageX,
        					top: origin.top + e.pageY - startEvent.pageY
        				});
        				
						var element = null;
						base.$el.find('.folder:not(.root), .is-folder .navigate').not(this).not($li.parent()).not($helper).not($helper.children()).each(function() {
							var offset = $(this).offset();
							offset.right = offset.left + $(this).outerWidth();
							offset.bottom = offset.top + $(this).outerHeight();
							if(e.pageX >= offset.left && e.pageX <= offset.right && e.pageY >= offset.top && e.pageY <= offset.bottom)
								element = this;
						});
						
						if(target && target !== element) {
							$(target).removeClass('drop-target');
							target = null;
						}
						if(element) {
							target = element;
							$(target).addClass('drop-target');
						}
        			}
        			setTimeout(function() {
        				if(isDragging === false)
        					return;
        				isDragging = true;
        				$helper
        					.insertAfter($li)
        					.css({position:'absolute'})
        					.offset(origin);
        				$(document).bind('mousemove', mouseMove);
        			}, 250);
        			$(document).one('mouseup', function(e) {
        				if(isDragging === null) {
        					isDragging = false;
        					return;
        				}
        				isDragging = false;
        				$(document).unbind('mousemove', mouseMove);
        				$helper.remove();
						if(target) {
							$(target).removeClass('drop-target');
							var source = $file.data('finder').path;
							var destination = $(target).data('finder').path;
							var postMove = function(overwrite) {
								var action = base.options.action + '?' + $.param({
									action: 'Move',
									path: source,
									destination: destination,
									overwrite: overwrite
								});
								$.post(action, function(data) {
		        					if(data.overwrite) {
		        						if(confirm(data.error + '\nOverwrite?'))
		        							postMove(true);
		        					} else if(!data.status) {
		        						alert(data.error);
			        				} else
			        					base.refresh();
			        			});
							}
							postMove('');
						}
        				return false;
        			});
	        		return false;
        		});
	        	base.$el.delegate('.navigate', 'click', function(e) {
	        		e.stopPropagation();
					e.preventDefault();
	        		if(!$(this).closest('li').hasClass('selected')) {
						if((e.ctrlKey || e.shiftKey) && base.options.multiple) {
							if(!$.isArray(base.options.path))
								base.options.path = [base.options.path];
							var p = $(this).data('finder').path;
							var pos = $.inArray(p, base.options.path);
							if(pos == -1)
								base.options.path.push(p);
							else if(base.options.path.length > 1)
								base.options.path.splice(pos, 1);
						} else {
							base.options.path = $(this).data('finder').path;
						}
						base.options.public = false;
						base.refresh();
	        		}
	        	});
	        	base.$el.delegate('.navigate', 'dblclick', function(e) {
					e.stopPropagation();
					e.preventDefault();
					if(!(e.ctrlKey || e.shiftKey) && base.options.path == $(this).data('finder').path) {
						base.$el.find('.select').click();
					}
	        	});
	        	/*
	        	base.$el.delegate('.upload', 'click', function(e) {
	        		var action = $(this).attr('href');
					var $fileInput = $('<input type="file" name="fileinput" style="opacity:0" multiple/>')
						.change(function(e) {
							base.uploadFiles(action, this.files);
						})
						.appendTo('body');
					var ret = $fileInput[0].click();
					console.log(ret);
					return false;
	        	});
	        	*/
	        	base.$el.delegate('.delete', 'click', function(e) {
	        		if(confirm('Do you really want to delete the selected file(s)/folder(s)?')) {
						$.post($(this).attr('href'), function(data) {
	        				if(!data.status)
	        					alert(data.error);
	        				else
	        					base.refresh();
	        			});
	        		}
	        		return false;
	        	});
	        	base.$el.delegate('.rename', 'click', function(e) {
	        		var currentName = $(this).data('finder').name;
	        		var newName = prompt('Rename file/folder', currentName);
	        		if(newName) {
	        			$.post($(this).attr('href'), {name: newName}, function(data) {
	        				if(!data.status)
	        					alert(data.error);
	        				else
	        					base.refresh();
	        			});
	        		}
	        		return false;
	        	});
	        	base.$el.delegate('.create-directory', 'click', function(e) {
	        		var name = prompt('Create new directory', 'New Folder');
	        		if(name) {
	        			$.post($(this).attr('href'), {name: name}, function(data) {
	        				if(!data.status)
	        					alert(data.error);
	        				else
	        					base.refresh();
	        			});
	        		}
	        		return false;
	        	});
	        	base.$el.delegate('.cancel', 'click', function(e) {
	        		base.$el.trigger('finder-close');
	        		return false;
	        	});
	        	base.$el.delegate('.select', 'click', function(e) {
	        		var $selection = base.$el.find('input[name=selection]');
	        		if($selection.length) {
	        			base.$el.trigger('finder-select', $selection.val());
	        			base.$el.trigger('finder-close');
	        		}
	        		return false;
	        	});
	        	base.$el.delegate('.fullscreen-preview', 'click', function(e) {
	        		$(this).toggleClass('active');
	        		return false;
	        	});
        	}
        }
        
        base.uploadFiles = function(action, files) {
			var $overlay = base.$el
				.addClass('loading')
				.find('.overlay')
				.html('<p>Uploading files...</p>');
				
			var totalSize = 0;
			for(var i=0; i<files.length; ++i)
				totalSize += files[i].size;
			
			if(base.options.maxUploadSize && totalSize > base.options.maxUploadSize) {
				if(!confirm('The '+(files.length==1?'file':'files')+' you are trying to upload exceeds the servers max upload size, would you like to try anyway?'))
					return;
			}
			
			var xhr = new XMLHttpRequest;
			
			// Progress
			(xhr.upload||xhr).addEventListener('progress', function(e) {
				if(e.lengthComputable)
					$overlay.html('<p>Uploading files... ' + Math.round(e.loaded/e.total*100) + '%</p>');
			}, false);
			
			// Complete
			xhr.addEventListener('load', function (e) {
				try {
					var data = JSON.parse(this.responseText);
					if(data.status) {
						base.options.path = data.uploaded_virtual[0]; // select uploaded file
					} else if(data.overwrite) {
						base.askOverwrite(data.overwrite);
					} else {
						alert(data.error);
					}
				}
				catch(e) {
					alert('An unknown server error occured.\n'+this.responseText);
				}
				base.refresh();
			}, false);
			
			xhr.open('post', action, true);
			xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			
			var formData;
			if(typeof(FormData) !== 'undefined') {
				formData = new FormData();
				for(var i=0; i<files.length; ++i)
					formData.append('file[]', files[i]);
				xhr.send(formData);
			} else if($.isFunction(files[0].getAsBinary)) {
				var boundary = '----CurryFormBoundary' + (new Date()).getTime();
				var rn = '\r\n';
                formData = '--' + boundary;
                for(var i=0; i<files.length; ++i) {
                	formData += rn +
						'Content-Disposition: form-data; name="' + 'file[]' + '"; filename="' + files[i].name + '"' + rn +
						'Content-Type: ' + files[i].type + rn + rn +
						files[i].getAsBinary() + rn +
						'--' + boundary;
                }
                formData += '--';
                xhr.setRequestHeader('Content-Type', 'multipart/form-data; boundary=' + boundary);
                xhr.sendAsBinary(formData);
			}
        }
        
        /* uses an iframe for uploading instead of ajax */
        base.uploadFilesFallback = function (action, fileInput) {
			var iframeId = 'curry-upload-frame';
			var $iframe = $('<iframe name="' + iframeId + '" />').attr({id: iframeId}).css({display:'none'}).appendTo('body');
			var $form = $(fileInput).wrap('<form method="POST" enctype="multipart/form-data" />').parent()
				.attr({action: action + "&iframe=1", target: iframeId});
			$form.submit();
			
			var $overlay = base.$el
				.addClass('loading')
				.find('.overlay')
				.html('<p>Uploading files...</p>');
				
			$iframe.load(function() {
				var doc = $iframe[0].contentDocument ? $iframe[0].contentDocument : $iframe[0].contentWindow.document,
					data = {};
				try {
					data = eval("(" + doc.body.innerHTML + ")");
					if(data.status) {
						base.options.path = data.uploaded_virtual[0]; // select uploaded file
					} else if(data.overwrite) {
						base.askOverwrite(data.overwrite);
					} else {
						alert(data.error);
					}
				} catch(err) { }
				
				$(fileInput).unwrap();
				$iframe.remove();
				base.refresh();
			});
        }
        
        base.refresh = function() {
        	var $wrapper = base.$el.find('.wrapper');
        	var preLeft = $wrapper.scrollLeft();
        	var preWidth = $wrapper.length ? $wrapper.get(0).scrollWidth : 0;
        	
        	var path = base.options.action + '?' + $.param({
        		path: base.options.path,
        		filter: base.options.filter,
        		public: base.options.public,
        		type: base.options.type
        	});
        	
			base.$el.addClass('loading')
			$.ajax(path, {
				dataType: 'json',
				cache: false,
				success: function(data) {
					if(!base.options.maxUploadSize && data.maxUploadSize)
						base.options.maxUploadSize = data.maxUploadSize;
					base.options.path = data.path;
					base.options.public = false;
					base.$el.removeClass('loading');
					var scrollPos = {};
					base.$el.find('.folder').each(function() {
						scrollPos[$(this).data().finder.path] = $(this).scrollTop();
					});
					base.$el.html($(data.content).children());
					base.bindEvents(true);
					// animate scroll
					$wrapper = base.$el.find('.wrapper');
					var width = $wrapper.get(0).scrollWidth;
					$wrapper.scrollLeft(preLeft);
					if(width > preWidth) {
						var sl = $wrapper.get(0).scrollWidth - $wrapper.innerWidth();
						$wrapper.animate({scrollLeft: sl});
					} else if(width < preWidth) {
						var $div = $('<div />')
							.css({'display': 'inline-block', width: preWidth - width})
							.animate({width: 0}, function() { $(this).remove(); });
						$wrapper.append($div)
							.scrollLeft(preLeft);
					}
					$wrapper.find('.folder').each(function() {
						var path = $(this).data().finder.path;
						if (path in scrollPos)
							$(this).scrollTop(scrollPos[path]);
					});
					base.$el.trigger('finder-update');
				},
				error: function(xhr, textStatus, errorThrown) {
					alert(textStatus + ': ' + errorThrown);
					base.$el.trigger('finder-close');
				}
			});
		}
        
        // Run initializer
        base.init();
    };
    
    $.finder.defaultOptions = {
        action: '/admin/curry_backend_filebrowser/',
        filter: null,
        filters: [
        	['all', '*','All files'],
        	['images', '*.jpg;*.png;*.gif','Image files'],
        	['scripts', '*.js','JavaScript files'],
        	['stylesheets', '*.css','Stylesheet files'],
        	['markup', '*.htm;*.html;*.xml','Markup files']
        ],
        multiple: false,
        type: 'file',
        path: '',
        defaultPath: '',
        public: true,
        maxUploadSize: 0,
        pathSeperator: ':'
    };
    
    $.fn.finder = function(options){
        return this.each(function(){
            (new $.finder(this, options));
        });
    };
    
    // This function breaks the chain, but returns
    // the finder if it has been attached to the object.
    $.fn.getFinder = function(){
        return this.data("finder-instance");
    };
    
})(jQuery);
