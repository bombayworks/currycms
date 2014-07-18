// create closure
(function($) {

	// Preview Image
	/////////////////////////////////////
	$.fn.previewImage = function() {
	return this.each(function() {
			var image = new Image();
			var input = this;

			var $img = $('<img />');
			var $remove = $('<a href="#" class="close">&times;</a>');
			var $info = $('<span class="previewimage-info"></span>');
			var $wrapper = $('<div class="previewimage-wrapper"></div>').append($img).append($remove).append($info);

			$remove.click(function() {
				$(input).val('').change();
				return false;
			});
			$wrapper.click(function() {
				$.util.openFinder(input);
			});

			function setImage(value) {
				$wrapper.toggleClass('previewimage-empty', value === '');
				$wrapper.toggleClass('previewimage-loading', value === true);
				$wrapper.toggleClass('previewimage-error', value === false);
				if(value && value !== true) {
					$img.attr('src', value).fadeIn();
					$remove.show();
					$info.text($img.prop('naturalWidth')+'x'+$img.prop('naturalHeight')).show();
				} else {
					$img.hide();
					$remove.hide();
					$info.hide();
				}
			}

			image.onload = function() {
				setImage(image.src)
			}
			image.onerror = function() {
				setImage(false);
			}

			$(this)
				.change(function() {
					if ($(this).val()) {
						image.src = $(this).val();
						setImage(image.complete ? image.src : true);
					} else {
						setImage('');
					}
				})
				.hide()
				.after($wrapper)
				.change();
		});
	};
	
	// Filebrowser
	////////////////////////////////////////////
	$.fn.filebrowser = function(options) {
		// build main options before element iteration
		var opts = $.extend({}, $.fn.filebrowser.defaults, options);
		return this.each(function() {
			var base = {};
			base.el = this;
			base.$el = $(this);
			if(base.$el.data('filebrowser-instance'))
				return;
			base.$el.data('filebrowser-instance', base);
			base.options = $.extend({}, opts, $(this).data('filebrowser'));
			base.$el.wrap('<div class="filebrowser-wrapper input-append" />');
			
			if(base.options.remote) {
				base.$remote = $('<a href="#" class="btn" title="Browse online"><i class="icon-folder-open" /></a>');
				base.$remote.click(function(event) {
					$.util.openFinder(base.el, base.$el.data('finder'), base.$el.data('dialog'));
					return false;
				});
				base.$el.after(base.$remote);
			}
			
			if(base.options.local) {
				base.$local = $('<a href="#" class="btn" title="Browse computer"><i class="icon-cloud-upload" /></a>');
				var $fileInput = $('<input type="file" name="fileinput" />')
					.change(function(e) {
						if(!this.files.length)
							return;
						var finderOptions = base.$el.data('finder') || {};
						var uploadPath = finderOptions.defaultPath || base.$el.val();
						var action = $.finder.defaultOptions.action + '&' + $.param({action: 'Upload', path: uploadPath, public: true});
						var $overlay = $('<div style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); color:white; text-align:center;"><p>Uploading files...</p></div>')
							.appendTo('body');
						
						var formData = new FormData();
						for(var i=0; i<this.files.length; ++i)
							formData.append('file[]', this.files[i]);
						
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
									base.$el.val(data.uploaded_public.join(':'));
								} else {
									alert(data.error);
								}
							}
							catch(e) {
								alert('Error:'+this.responseText);
							}
							$overlay.remove();
						}, false);
						
						xhr.open('post', action, true);
						xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			            xhr.send(formData);
					});
				$fileInput.appendTo(base.$local);
				base.$el.after(base.$local);
				
				var form = $fileInput[0].form;
				if(form)
					$(form).submit(function() { $fileInput.remove(); });
			}
		});
	}
	$.fn.filebrowser.defaults = {
		local: true,
		remote: true
	};
	
	// Ctrl+S save
	////////////////////////////////////////////
	$.fn.ctrlsave = function(func) {
		return this.bind('keydown', function(e) {
			if(e.keyCode == 'S'.charCodeAt(0) && e.ctrlKey) {
				e.preventDefault();
        		e.stopPropagation();
        		
        		if(func == undefined) {
        			$(this).closest('form').submit();
        		} else {
        			func();
        		}
        		
				return false;
        	}
        });
	}
	
	// Submit forms using ajax
	////////////////////////////////////////////
	$.fn.ajaxsubmit = function() {
		return this.bind('submit', function(e) {
			var $form = $(this).removeClass('ajaxsubmit-error').addClass('ajaxsubmit-loading');
			var $buttons = $(":submit:not(:disabled)", this).attr('disabled', true);

			if($form.find(':file').length)
				alert('Unable to use ajaxsubmit with file uploads.');

			$form.find('.codemirror').each(function() {
				var cm = $(this).data('codemirror-instance');
				cm.save();
			});

			// we need this to execute after other bound submit-handlers
			setTimeout(function() {
				$.post($form.attr('action'), '_ajaxsubmit=1&' + $form.serialize(), function(data) {
					if(data.success) {
						$form.removeClass('ajaxsubmit-loading').addClass('ajaxsubmit-success');
						setTimeout(function() { $form.removeClass('ajaxsubmit-success'); }, 250);
					} else {
						$form.removeClass('ajaxsubmit-loading').addClass('ajaxsubmit-error');
						alert(data.error);
					}
					$buttons.attr('disabled', false);
					if ($.isPlainObject(data.values)) {
						$.each(data.values, function(name, value) {
							$form.find('[name='+name+']').val(value);
						});
					}
				});
			}, 100);
			e.preventDefault();
		});
	}
	
	// CodeMirror
	////////////////////////////////////////////
	$.fn.codemirror = function(options) {
		// build main options before element iteration
		var opts = $.extend({}, $.fn.codemirror.defaults, options);
		
		return this.each(function() {
			if($(this).data("codemirror-instance"))
				return;
			// build element specific options
			var o = $.extend({}, opts, $(this).data('codemirror'));
			var editor = CodeMirror.fromTextArea(this, o);
			$(this).data("codemirror-instance", editor);
			editor.dom = this;
		});
	}
	$.fn.codemirror.defaults = {
		lineNumbers: true
	};
	
	// plugin definition
	$.util = {};
	
	// opens a dialog
	$.util.openDialog = function(id, dialogTitle, url, dialogOptions) {
        var options = $.extend({}, dialogOptions);
		options.title = options.title || dialogTitle;
        var $overlay = $('<div class="overlay" />').appendTo('body').hide(),
            $dialog = $('<div class="dialog-container"><h1 class="dialog-title"><a href="#" class="dialog-close">&times;</a></h1><div class="dialog-content"></div></div>').appendTo($overlay);
        $dialog.find('.dialog-title').prepend(options.title);

        if(options.width)
            $dialog.width(options.width);
        if(options.height && options.height !== 'auto') {
            $dialog.height(options.height);
            $dialog.find('.dialog-content').css({position: 'absolute'});
        }

        var keyhandler = function(e) {
            if (e.keyCode == 27) {
                $dialog.trigger('dialogclose');
                return false;
            }
        }
        $(document).on('keyup', keyhandler);
        $overlay.on('click', function(e) {
            if(e.target == this) {
                $dialog.trigger('dialogclose');
                return false;
            }
        });
        $dialog.on('click', '.dialog-close', function() {
            $dialog.trigger('dialogclose');
            return false;
        });
        $dialog.on('curry-init', function() {
            $dialog.hide();
            $dialog.css({
                marginLeft: -($dialog.outerWidth() / 2),
                top: $(window).scrollTop() + Math.max(0, $(window).height() - $dialog.outerHeight()) / 2
            });
            $dialog.show();
        })
        $dialog.bind('dialogclose', function() {
            if (options.close)
                options.close();
            $(document).off('keyup', keyhandler);
            $overlay.fadeOut(function() { $overlay.remove(); });
            $dialog.find('.tinymce').remove(); // need to remove this before destroying dialog due to firefox tinymce/iframe issue.
            $dialog.remove();
        });

        if(url instanceof jQuery) {
            $overlay.fadeIn();
            $dialog.find('.dialog-content').append(url).trigger('curry-init');
        } else if(url) {
            $dialog.find('.dialog-content').load(url, "curry_context=main", function() {
                $overlay.fadeIn();
                $dialog.trigger('curry-init');
            });
        }

        if(options.buttons) {
            var $btns = $('<div class="dialog-buttons"></div>');
            for(var i=0; i<options.buttons.length; ++i) {
                var buttonOptions = options.buttons[i];
                $('<button></button>')
                    .addClass('btn')
                    .text(buttonOptions.text)
                    .click((function(buttonOptions) {
                        return function() {
                            $dialog.trigger('dialogclose');
                            buttonOptions.click();
                        };
                    })(buttonOptions))
                    .appendTo($btns);
            }
            $btns.appendTo($dialog);
        }

        return $dialog;
	};

	$(document).on('click', 'button[type=submit], input[type=submit]', function() {
		if (this.form) {
			$(this.form).data('curry-submit-element', this);
			$(this.form).data('curry-submit-value', $(this).val());
		}
	});
	
	// opens a finder dialog
	$.util.openFinder = function(element, finderOptions, dialogOptions) {
		finderOptions = $.extend({path: $(element).val()}, finderOptions);
		dialogOptions = $.extend({width: Math.min(900, $(window).width() - 20)}, dialogOptions);
		var $finder = $('<div class="finder" />')
            .finder(finderOptions)
            .one('finder-update', function() { $dialog.trigger('curry-init'); })
            .bind('finder-select', function(e, data) {
                $(element).val(data).change();
            })
            .bind('finder-close', function(e) {
                $dialog.trigger('dialogclose');
            });
        var $dialog = $.util.openDialog(null, 'Browse for file...', $finder, dialogOptions);
        return $finder;
	};
	
	// Create dialogs from links
	$(document).delegate('a.dialog', 'click', function() {
		var title = $(this).attr('title') || $(this).text();
		$.util.openDialog(null, title, $(this).attr('href'), $(this).data('dialog'));
		return false;
	});

	$.util.ajaxform = function(form, opt, extra) {
		var options = {
			type: 'POST',
			url: $(form).attr('action')
		};
		if (typeof window.FormData !== "undefined") {
			options.data = new FormData(form);
			options.processData = false;
			options.contentType = false;
			if(typeof extra !== "undefined") {
				for(var name in extra)
					options.data.append(name, extra[name]);
			}
			if ($(form).data('curry-submit-element')) {
				var el = $(form).data('curry-submit-element'),
					name = $(el).attr('name') || $(el).attr('id'),
					val = $(form).data('curry-submit-value');
				options.data.append(name, val);
			}
		} else {
			if($(form).find(':file').length)
				alert('Your browser does not support file uploads using javascript.');
			options.data = $(form).serialize();
			if(typeof extra !== "undefined") {
				options.data += "&" + $.param(extra);
			}
			if ($(form).data('curry-submit-element')) {
				var el = $(form).data('curry-submit-element'),
					name = $(el).attr('name') || $(el).attr('id'),
					val = $(form).data('curry-submit-value');
				options.data += "&" + $.param([{name: name, value: val}]);
			}
		}
		$.extend(options, opt);
		return $.ajax(options);
	};
	
	$(document).delegate('.dialog-container form:not(.no-ajax)', 'submit', function() {
		var $form = $(this);
		$form.find(':submit').attr('disabled', true);
		var action = $form.attr('action');
		action += (action.indexOf('?') == -1 ? '?' : '&') + 'curry_context=main';
		$.util.ajaxform(this, {url: action, success: function(content) {
			$form.find(':submit').attr('disabled', false);
			if (content.length) {
				$form.closest('.dialog-content').html(content).trigger('curry-init');
			} else {
				$form.closest('.dialog-container').trigger('dialogclose');
			}
		}});
		return false;
	});
	
	$(document).delegate('a.postback', 'click', function(e) {
		if(!e.isDefaultPrevented()) {
			var $a = $(this);
			$.require('jquery-bw-url', function() {
				var url = new $.bw.url($a.attr('href'));
				url.doPost($a.data('postback'));
			});
			return false;
		}
	});
	
})(jQuery);
