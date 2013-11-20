(function($) {

$(document).ready(function() {

	var isInFrame = (window.location != window.parent.location) ? true : false,
		isMoving = false;
	$('body')
		.addClass(isInFrame ? 'curry-admin-frame' : 'curry-admin-standalone')
		.append(window.inlineAdminContent);

	// Check for pointer-events support
	var pointerEvents = (function(){
		var element = document.createElement('x');
		element.style.cssText = 'pointer-events:auto';
		return element.style.pointerEvents === 'auto';
	})();
	
	// opens a dialog
	function openDialog(url) {
		// Disable outer scrolling
		$('html,body').css('overflow','hidden');

		// Create overlay
		var $overlay = $('<div id="curry-admin-overlay"></div>').appendTo('body'),
			close = function() { window.location.reload(); };
		$overlay.click(close);

		// Create button to close overlay
		$('<a href="#" id="curry-admin-iframe-close">&times;</a>').appendTo($overlay).click(function() {
			close();
			return false;
		});

		// Create iframe
		var iframe = $('<iframe id="inline-admin-iframe" frameborder="0" allowtransparency="true" />').attr('src', url).prependTo($overlay);
		iframe.css('visibility','hidden');
		iframe.on('curry-ready',function(){
			var doc = this.contentDocument || this.contentWindow.document;

			// Check if we should close the iframe
			if ($('body', doc).hasClass('live-edit-close')) {
				close();
				return;
			}

			// Add inline-admin class to inner document and show iframe
			$('body', doc).addClass('inline-admin');
			iframe.css('visibility','');

			// Close iframe when clicking on html or body elements
			$('html, body', doc).click(function(e) {
				if (e.target == e.currentTarget) {
					close();
					return false;
				}
			});

			// Hide iframe when it is loading
			$(this.contentWindow).on('beforeunload', function() {
				iframe.css('visibility','hidden');
			});
		});
	}

	function getContent(open) {
		var close = document.getElementById(open.id.replace('-open', '-close'));
		// fetch all text and element-nodes in between the open and close element
		var nodes = [];
		var node = open.nextSibling;
		while(node && node != close) {
			if((node.nodeType == Node.TEXT_NODE && !node.nodeValue.match(/^\s+$/)) || node.nodeType == Node.ELEMENT_NODE) {
				nodes.push(node);
			}
			node = node.nextSibling;
		}
		return $(nodes);
	}

	function getBounds($content) {
		var top = Number.MAX_VALUE;
		var left = Number.MAX_VALUE;
		var right = 0;
		var bottom = 0;
		$content.each(function() {
			var t = this;

			// wrap text nodes with spans (to be able to get dimensions)
			if(this.nodeType == Node.TEXT_NODE) {
				$(this).wrap('<span></span>');
				t = $(this).parent();
			}

			if($(t).css('display') != 'none') {
				// for non-floated items with overflow set to visible we need to change overflow to hidden to get the proper height
				var of = ($(t).css('display') == 'block' && $(t).css('overflow') == 'visible' && $(t).css('float') == 'none') ? t.style.overflow : -1;
				if(of !== -1)
					t.style.overflow = 'hidden';

				var o = $(t).offset();
				top = Math.min(top, o.top);
				left = Math.min(left, o.left);
				bottom = Math.max(bottom, o.top + $(t).outerHeight());
				right = Math.max(right, o.left + $(t).outerWidth());

				if(of !== -1)
					t.style.overflow = of;
			}

			if(this.nodeType == Node.TEXT_NODE) {
				$(this).unwrap();
			}
		});
		if (left > right || top > bottom)
			return false;
		$content.parents().each(function() {
			if($(this).css('overflow') == 'auto' || $(this).css('overflow') == 'hidden') {
				var o = $(this).offset();
				top = Math.max(top, o.top);
				left = Math.max(left, o.left);
				bottom = Math.min(bottom, o.top + $(this).outerHeight());
				right = Math.min(right, o.left + $(this).outerWidth());
			}
		});
		return {top: top, left: left, bottom: bottom, right: right};
	}
	
	$('.module-control, .block-control, #curry-admin-panel')
		.delegate('a.iframe', 'click', function() {
			openDialog(this.href);
			return false;
		})
		.delegate('a.toggle-show-all-modules', 'click', function() {
			$('body').toggleClass('show-all-modules');
			return false;
		})
		.delegate('a.toggle-show-all-blocks', 'click', function() {
			$('body').toggleClass('show-all-blocks');
			return false;
		});

	var $c = $('<div id="curry-admin"></div>').appendTo('body');

	$('.module-open').each(function() {
		var $content = getContent(this);
		if(!$content.length)
			return;
		
		var $d = $('<div class="module-holder" style="position:absolute;"></div>').appendTo($c);
		$d.data('module', $(this).data('module'));
		$(this).contents().appendTo($d);
		
		$d.click(function() {
			if(!$d.hasClass('edit')){
				if($d.find('.commands a').length == 1){
					//we only got one command, let's execute it directly
					$d.find('.commands a').click();
					return;
				}
			}
			$d.toggleClass('edit');
			if($d.hasClass('edit')) {
				$d.siblings('.module-holder').removeClass('edit');
				$d.appendTo($d.parent());
			}
		});
		
		function updatePosition() {
			if (isMoving)
				return;
			var bounds = getBounds($content);
			if (bounds === false)
				$d.hide();
			else
				$d.show().css({"top": bounds.top, "left": bounds.left, "width": (bounds.right - bounds.left)+"px", "height": (bounds.bottom - bounds.top)+"px"});
		}
		
		// Update positioning of module boxes
		$(window).resize(updatePosition)
			.scroll(updatePosition)
			.load(updatePosition);
		setInterval(updatePosition, 500);
		
		updatePosition();
		
	}).add('.module-close').remove();

	$('.block-open').each(function() {
		var $content = getContent(this);
		if(!$content.length) {
			//return;
			$content = $(this.parentNode);
		}

		var $d = $('<div class="block-holder" style="position:absolute;"></div>').prependTo($c);
		$(this).contents().appendTo($d);

		function updatePosition() {
			if (isMoving)
				return;
			var bounds = getBounds($content);
			if (bounds === false)
				$d.hide();
			else
				$d.show().css({"top": bounds.top, "left": bounds.left, "width": (bounds.right - bounds.left)+"px", "height": (bounds.bottom - bounds.top)+"px"});
		}

		// Update positioning of module boxes
		$(window).resize(updatePosition)
			.scroll(updatePosition)
			.load(updatePosition);
		setInterval(updatePosition, 500);

		updatePosition();
	}).add('.block-close').remove();
	
	var toggle = true;
	$(document).keydown(function(event) {
		if (event.which === 27){
			//close open frames / menus
			if($("#inline-admin-iframe").length === 1){
				window.location.reload();
			}

			if($(".module-holder.edit").length === 1){
				$(".module-holder.edit").removeClass("edit");
			}
		}
		if (event.shiftKey) {
			toggle = !toggle;
			toggle ? $c.show() : $c.hide();
		}
	});

	// Without pointer-events support, we need to manually adjust zIndex
	if (!pointerEvents) {
		$c.find('.block-control').hover(function() {
			this.parentNode.style.zIndex = 1;
		}, function() {
			this.parentNode.style.zIndex = -1;
		});
	}
	
	// Hide active module-holder on clicks outside of module-holders
	$(document).click(function(e) {
		if(!$(e.target).closest('.module-holder').length)
			$(".module-holder.edit").removeClass("edit");
	});

	$c.find('.module-holder').on('mousedown', function(e) {
		var self = this,
			last = e,
			offset = $(this).offset(),
			dragging = false,
			target = null,
			$control = $(this).find('.module-control');
		function cancel() { return false; }
		function mousemove(e) {
			if (!dragging) {
				if (Math.abs(e.pageX - last.pageX) > 10 || Math.abs(e.pageY - last.pageY) > 10) {
					$control.on('click', cancel);
					isMoving = true;
					dragging = true;
					$(self).addClass('module-move');
				}
				else
					return;
			}
			offset.left += e.pageX - last.pageX;
			offset.top += e.pageY - last.pageY;
			$(self).offset(offset);
			last = e;

			var newTarget = null;
			$c.find('.module-holder').each(function() {
				if (this == self)
					return;
				var o = $(this).offset();
				o.width = $(this).outerWidth();
				o.height = $(this).outerHeight();
				if (last.pageX > o.left && last.pageX < (o.left + o.width) &&
					last.pageY > o.top && last.pageY < (o.top + o.height))
					newTarget = this;
			});
			if (newTarget !== target) {
				if (target)
					$(target).removeClass('module-move-target');
				target = newTarget;
				$(target).addClass('module-move-target');
			}
		}

		function endmove() {
			$(document).off('mousemove', mousemove);
			$(document).off('mouseup', endmove);
			$(self).removeClass('module-move');
			setTimeout(function() {
				$control.off('click', cancel);
			}, 100);
			if (dragging && target) {
				$(target).removeClass('module-move-target');
				var pageId = $(self).data('module').page_id,
					a = $(self).data('module').id,
					b = $(target).data('module').id;
				$.post('/admin.php?module=Curry_Backend_Page&view=SwapModules&page_id=' + pageId, {a: a, b: b}, function() {
					window.location.reload();
				});
			}
			isMoving = false;
			return false;
		}

		$(document).on('mousemove', mousemove);
		$(document).on('mouseup', endmove);

		return false;
	});

});

}($.noConflict(true)));