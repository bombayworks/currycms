(function($){

	/*
	var example = {
		title: 'Title',
		url: 'path/to/json',
		model: null,
		idColumn: null,

		paginate: true|false|auto,
		maxPerPage: 10,
		currentPage: 1,
		numItems: 0,

		sortable: true|false,
		quickSearch: true|false|auto,

		actions: {
			edit: {
				label: 'Edit',
				general: true,
				single: true,
				multi: true,
				href: 'url',
				class: '',
			}
		},

		display: [],
		hide: [],
		template: "",

		columns: {
			name: {
				label: 'Name',
				sortable: true,
				escape: true,
				action: 'name',
				hide: false
			}
		}
	}
	*/


	$.modelview = function(el, options){
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		var base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $(el);
		base.el = el;

		// Add a reverse reference to the DOM object
		if (base.$el.data('modelview-instance'))
			return;
		base.$el.data("modelview-instance", base);

		base.init = function(){
			var dataOptions = base.$el.data('modelview');
			base.options = $.extend({},$.modelview.defaultOptions, dataOptions, options);

			var columnIndex = 0;
			for(var c in base.options.columns) {
				if (c === base.options.idColumn) {
					break;
				}
				columnIndex++;
			}
			base.options.idColumnIndex = columnIndex;

			// Search filter
			base.$el.on('input', '.search-query', function() {
				if ($(this).closest('.modelview')[0] == base.el)
					base.reload({q: $(this).val() });
			});
			// Model update
			$(document).on('model-update', function(e, model, id, method) {
				if (base.options.model == model) {
					base.reload();
				}
			});
			// Sort/pager
			base.$el.on('click', '.modelview-param', function() {
				if ($(this).closest('.modelview')[0] == base.el)
					base.reload(URI($(this).attr('href')).search(true));
				return false;
			});
			// Checkbox
			base.$el.delegate('.modelview-col-select :checkbox', 'change', function() {
				if ($(this).closest('.modelview')[0] == base.el) {
					$(this).closest('tr').toggleClass( 'modelview-selected', $(this).is(':checked') );
					$(this).closest('.modelview').toggleClass( 'modelview-has-selection', !!$(this).closest('tbody').find('.modelview-selected').length );
				}
			});
			// Select row on click
			base.$el.delegate('.modelview-row td', 'click', function(e) {
				if($(e.target).parents().andSelf().is(base.options.cancel))
					return;
				if ($(this).closest('.modelview')[0] == base.el) {
					var cb = $(this).parent().find(':checkbox');
					if(cb.is(':checked'))
						cb.removeAttr('checked').trigger('change');
					else
						cb.attr('checked', 'checked').trigger('change');
					return false;
				}
			});

			base.buildMain();

			// Sorting
			if (base.options.sortable) {
				$.require('jquery-ui', function() {
					$(base.$el.find('.modelview-table-wrapper table tbody')).sortable({
						axis: 'y',
						items: '> .modelview-row',
						forcePlaceholderSize: true,
						forceHelperSize: true,
						containment: 'parent',
						tolerance: 'pointer',
						delay: 200,
						//scroll: false,
						helper: function(e, tr) { // manual clone to force cell width
							var $originals = tr.children();
							var $helper = tr.clone();
							$helper.children().each(function(index) {
								$(this).width($originals.eq(index).width());
							});
							return $helper;
						},
						start: function(e, ui) {
							var self = this,
								$siblings = ui.item.siblings('.inline-content');
							$siblings.children().children('div').slideUp(null, function() {
								$siblings.remove();
								$(self).sortable('refreshPositions');
							});
						},
						update: function(e, ui) {
							var params = $(this).sortable('serialize', {key: 'item[]', attribute: 'data-mv-id', expression: /(.+)/});
							$.post(base.options.sortable, params, function() {

							});
						}
					});
				});
			}

			base.reload();
		};

		base.buildMain = function() {
			base.$el
				.empty()
				.append(base.buildHeader())
				.append(base.buildBody())
				.append(base.buildFooter());
		}

		base.buildHeader = function() {
			var $content = $('<div class="modelview-header"></div>')
				.append($('<h1 />', {text: base.options.title}));
			$content.append(base.buildGeneralActions());
			base.$search = $('<div class="modelview-search form-search"><div class="input-append"><input type="text" name="q" class="input-small search-query"><button type="submit" class="btn"><span class="icon-search"></span></button></div></div>');
			$content.append(base.$search);
			return $content;
		}

		base.buildGeneralActions = function() {
			var $content = $('<div class="btn-group"></div>');
			for(var i in base.options.actions) {
				var a = base.options.actions[i];
				if (a.general) {
					$content.append($('<a />', {href: a.href, class: "btn btn-small " + a.class, text: a.label}));
				}
			}
			return $content;
		}

		base.buildBody = function() {
			var $content = $('<tr />');
			var visibleColumns = 0;
			for(var i in base.options.columns) {
				var column = base.options.columns[i];
				if (!column.hide) {
					if (column.sortable) {
						$content.append($('<th />').append($('<a />', {text: column.label, class: 'modelview-param', href: URI(window.location.href).addSearch({sort_column: i, sort_order: 'asc'})})));
					} else {
						$content.append($('<th />', {text: column.label}));
					}
					++visibleColumns;
				}
			}
			base.options.visibleColumns = visibleColumns;
			$content.prepend($('<th class="modelview-col-select"><input type="checkbox" name="item_all" value="1" /></th>'));
			$content.append($('<th class="modelview-single-actions">&nbsp;</th>'));
			$content = $('<thead />').append($content);
			$content = $('<table />').append($content).append('<tbody />');
			$content = $('<div />', {class: 'modelview-table-wrapper'}).append($content);
			return $content;
		}

		base.buildFooter = function() {
			base.$status = $('<span>Initializing...</span>');
			return $('<div />', {class: 'modelview-footer'})
				.append(base.buildMultiActions())
				.append(base.$status)
				.append(base.buildPager());
		}

		base.buildMultiActions = function() {
			 return '';/*'<div class="form-inline">\
			 <div class="modelview-multiactions">\
			 ↳ with selected\
			 <div class="input-append">\
			 <select>\
			 <option value="url" class="class">delete</option>\
			 </select>\
			 <button class="btn" type="submit"><span class="icon-ok"></span></button>\
			 </div>\
			 </div>\
			 </div>';*/
		}

		base.buildPager = function() {
			var div = document.createElement('div'),
				ul = document.createElement('ul'),
				li, a;
			div.className = 'modelview-pager pagination';
			var numPages = Math.ceil(base.options.numItems / base.options.maxPerPage);
			if(!base.options.maxPerPage || !numPages || numPages <= 1) {
				base.$status.text('Showing ' + base.options.numItems + ' items.');
				base.$search.toggle(base.$search.find('.search-query').val() !== '');
				return div;
			} else {
				var start = 1 + (base.options.currentPage - 1) * base.options.maxPerPage;
				var end = Math.min(base.options.numItems, base.options.currentPage * base.options.maxPerPage);
				base.$status.text('Showing ' + start + ' to ' + end + ' of ' + base.options.numItems + ' items.');
				base.$search.show();
			}
			var min = Math.max(1, base.options.currentPage - 3);
			var max = Math.min(numPages, base.options.currentPage + 3);
			for(var i = min; i <= max; ++i) {
				li = document.createElement('li');
				li.className = base.options.currentPage == i ? 'active' : '';
				a = document.createElement('a');
				a.className = 'modelview-param';
				a.textContent = i;
				a.href = '?p=' + i;
				li.appendChild(a);
				ul.appendChild(li);
			}
			div.appendChild(ul);
			return div;
		}

		base.buildCell = function(column, value, id) {
			var td = document.createElement('td');
			if (column.action) {
				var action = base.options.actions[column.action];
				var a = document.createElement('a');
				a.href = base.actionLink(action, id);
				a.className = action.class;
				a[column.escape ? 'textContent' : 'innerHTML'] = value;
				td.appendChild(a);
			} else {
				td[column.escape ? 'textContent' : 'innerHTML'] = value;
			}
			return td;
		}

		base.actionLink = function(action, id) {
			var uri = URI(action.href);
			if (typeof id !== "undefined")
				uri.addSearch({item: id});
			return uri;
		}

		base.buildSingleActions = function(id) {
			var td = document.createElement('td'),
				div = document.createElement('div'),
				icon = document.createElement('i'),
				list = document.createElement('ul'),
				li;
			td.className = 'modelview-single-actions';
			div.className = 'dropdown';
			icon.className = 'icon-cog';
			list.className = 'dropdown-menu pull-right';
			td.appendChild(div);
			div.appendChild(icon);
			div.appendChild(list);
			for(var i in base.options.actions) {
				var action = base.options.actions[i];
				if (action.single) {
					var a = document.createElement('a');
					a.href = base.actionLink(action, id);
					a.className = action.class;
					a.textContent = action.label;
					li = document.createElement('li');
					li.appendChild(a);
					list.appendChild(li);
				}
			}
			return td;
		}

		base.populate = function(rows) {
			var tbody = base.$el.children('.modelview-table-wrapper').children('table').children('tbody')[0],
				tr, td,
				ci, id, idx;
			$(tbody).empty();
			for(idx in rows) {
				id = rows[idx][base.options.idColumnIndex];
				ci = 0;
				tr = document.createElement('tr');
				tr.setAttribute('data-mv-id', encodeURIComponent(id));
				tr.className = 'modelview-row';
				td = document.createElement('td');
				td.className = 'modelview-col-select';
				td.innerHTML = '<input type="checkbox" name="item[]" value="'+id+'" />';
				tr.appendChild(td);
				for(var c in base.options.columns) {
					var column = base.options.columns[c];
					if (!column.hide) {
						td = base.buildCell(column, rows[idx][ci], id);
						tr.appendChild(td);
					}
					++ci;
				}
				td = base.buildSingleActions(id);
				tr.appendChild(td);
				tbody.appendChild(tr);
			}
			if (!tr) {
				tr = document.createElement('tr');
				tr.className = 'modelview-no-hits';
				td = document.createElement('td');
				td.setAttribute('colspan', base.options.visibleColumns + 2);
				td.textContent = 'No entries found';
				tr.appendChild(td);
				tbody.appendChild(tr);
			}
		}

		base.reload = function(params) {
			var uri = URI(base.options.url);
			if (typeof params !== "undefined")
				uri.addSearch(params);
			base.$status.text('Loading...');
			$.post(uri, function(content) {
				base.options.currentPage = content.page;
				base.options.numItems = content.total;
				var $wrapper = base.$el.children('.modelview-table-wrapper'),
					height = $wrapper.clearQueue().css('height','').height();
				base.populate(content.rows);
				var postHeight = $wrapper.height();
				$wrapper.height(height).animate({height: postHeight}, function() { $(this).css('height', '').css('overflow', ''); });
				base.$el.find('.modelview-pager').replaceWith(base.buildPager());
			});
		};

		// Run initializer
		base.init();
	};

	$.modelview.defaultOptions = {
		numItems: 0,
		maxPerPage: 0,
		cancel: "input,textarea,button,select,option,a",
		sortable: false
	};

	$.fn.modelview = function(options){
		return this.each(function(){
			(new $.modelview(this, options));
		});
	};
})(jQuery);

// .inline
$(document).delegate('.modelview .inline', 'click', function() {
	var $a = $(this);
	var $tr = $(this).closest('tr');
	var $content = $tr.next('.inline-content');
	var $siblings = null;
	if ($content.length) {
		var c = $content;
		$content.children().children('div').slideUp(null, function() {
			c.remove();
		});
		if($content.data('mv-href') == $a.attr('href'))
			return false;
	} else {
		$siblings = $tr.siblings('.inline-content');
		$siblings.children().children('div').slideUp(null, function() {
			$siblings.remove();
		});
	}
	var cols = 0;
	$tr.children().each(function() {
		cols += parseInt($(this).attr('colspan') || 1);
	});
	$content = $('<tr class="inline-content"><td colspan="'+cols+'"><div class="inline-wrapper"></div></td></tr>');
	$content.data('mv-href', $a.attr('href'));
	var $div = $content.find('div').addClass('inline-wrapper-hide');
	$tr.after($content);
	$div.load($a.attr('href')+'&curry_context=main', function() {
		if($siblings)
			$siblings.remove();
		$('<a href="#" class="inline-close">✖</a>').prependTo(this).click(function(){
			$div.slideUp(null, function() { $content.remove(); });
			return false;
		});
		$div.one('curry-init-complete', function() {
			$div.removeClass('inline-wrapper-hide').hide().slideDown();
		});
		$div.trigger('curry-init');
	});
	return false;
});
$(document).delegate('.modelview .post', 'click', function() {
	$.post($(this).attr('href'));
	return false;
});
$(document).delegate('.inline-content form:not(.no-ajax)', 'submit', function() {
	var $form = $(this);
	var $contentDiv = $(this).closest('div');
	var $tr = $(this).closest('tr');
	$form.find(':submit').attr('disabled', true);
	$.util.ajaxform(this, {
		dataType: 'text',
		success: function(content) {
			$form.find(':submit').attr('disabled', false);
			if (content.length) {
				var p = $form.parent();
				if (p.is('.inline-wrapper'))
					$form.siblings(':not(.inline-close)').remove();
				$form.replaceWith(content);
				p.trigger('curry-init');
			} else {
				$contentDiv.slideUp(null, function() { $tr.remove(); });
			}
		}
	}, {curry_context: 'main'});
	return false;
});