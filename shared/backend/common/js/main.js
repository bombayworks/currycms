// create closure
(function($) {
	$.registerComponent('.previewimage', 'previewImage');
	$.registerComponent('.filebrowser', 'filebrowser');
	$.registerComponent('.ctrlsave', 'ctrlsave');
	$.registerComponent('.ajaxsubmit', 'ajaxsubmit');
	$.registerComponent('.trigger-change', 'change');
	$.registerComponent('.chosen', 'chosen', ['chosen']);
	$.registerComponent('.modelview', 'modelview', ['modelview']);

	$.registerComponent('.codemirror', function($obj) {
		$obj.codemirror({initCallback: function(editor) {
			// bind ctrl+s to submit
			$(editor.win.document).ctrlsave(function() {
				$(editor.dom).val( editor.getCode() );
				$(editor.dom).closest('form').submit();
			});
		}});
	}, ['codemirror']);
	
	$.registerComponent('.tinymce', function($obj) {
		$obj.each(function() {
			var o = $.extend({}, window.tinymceSettings, $(this).data('tinymce'));
			$(this).tinymce(o);
		});
	}, ['tinymce']);
	
	$.registerComponent('fieldset.advanced', function($obj) {
		$obj.addClass('advanced-hidden')
			.children("legend")
			.bind('click', function() {
				$(this).parent().toggleClass('advanced-hidden');
				return false;
			});
	});
	
	$.registerComponent('.toggle', function($obj) {
		$obj.bind('click', function() {
			$($obj.attr('rel')).toggle();
			return false;
		});
	});

	// Show ajax loader
	var numLoading = 0;
	var $loader = $('<div id="loader"/>');
	function startLoad() { $loader.toggle(!!++numLoading); }
	function endLoad() { $loader.toggle(!!--numLoading); }
	$(document)
		.ready(function() { $loader.appendTo('body'); })
		.delegate('script,link', 'curry-load', startLoad)
		.delegate('script,link', 'curry-success curry-error', endLoad)
		.ajaxStart(startLoad)
		.ajaxStop(endLoad);

	// Trigger events on document from ajax-calls
	$(document).ajaxComplete(function(e, xhr, opt) {
		var header, headers = xhr.getAllResponseHeaders().split('\r\n');
		for(header in headers) {
			var match = headers[header].match(/^X-Trigger-Events: (.+)/);
			if(match) {
				var events = JSON.parse('['+match[1]+']');
				for(var e in events) {
					var event = events[e];
					$(document).trigger(event.type, event.params || []);
				}
			}
		}
	});

	// Show ajax errors
	$(document).ajaxError(function(event, request, options, exception){
		if($.util && $.util.openDialog) {
			var content = $('<div class="dialog-error"><p>' + options.url + '</p><iframe></iframe></iframe></div>');
			var iframe = content.find('iframe')[0];
			$.util.openDialog(null, exception, content);
			var doc = iframe.contentWindow.document;
			doc.open();
			doc.writeln(request.responseText);
			doc.close();
		} else
			alert('An error occured.');
	});
	
	$(document).ready(function() {
		// Initialize components
		$(document).trigger('curry-init');
		// If we are being loaded inside a frame, let the frame element know our document is ready
		if (window.frameElement) {
			var el = window.frameElement,
				doc = el.ownerDocument,
				evt = doc.createEvent('HTMLEvents');
			evt.initEvent('curry-ready', true, true);
			el.dispatchEvent(evt);
		}
	});
})(jQuery);
