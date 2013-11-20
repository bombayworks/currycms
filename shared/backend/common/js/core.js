(function($) {
	var loaded_js;
	var loaded_css;
	var libraries = {};
	var components = [];
	
	/**
	 * var p = $.sync();
	 * async(p.wait());
	 * p.done(all());
	 */
	$.sync = function() {
		var base = {};
		base.count = 1;
		base.wait = function() {
			++base.count;
			return base.check;
		}
		base.check = function() {
			if(!--base.count) {
				if($.isFunction(base.success))
					base.success();
			}
		}
		base.done = function(success) {
			base.success = success;
			base.check();
		}
		return base;
	}
	
	// Load javascript files asyncronously
	$.require_js = function(src, success, error) {
		if(!$.isPlainObject(loaded_js)) {
			loaded_js = {};
			$("script[type='text/javascript']").each(function() {
				var s = $(this).attr('src');
				if(s) {
					loaded_js[s] = this;
					this.status = 'success';
				}
			});
		}
		
		var script = loaded_js[src];
		if(!script) {
			var head = document.getElementsByTagName('head')[0]
			script = loaded_js[src] = document.createElement('script');
			script.setAttribute('src', src);
			script.setAttribute('type', 'text/javascript');
			script.onload = function() {
				script.status = 'success';
				$(script).trigger('curry-success');
			}
			if(!script.addEventListener) { /* IE8 and below */
				script.onreadystatechange = function() {
					if(this.readyState == 'complete' || this.readyState == 'loaded')
						this.onload();
				}
			}
			script.onerror = function() {
				script.status = 'error';
				$(script).trigger('curry-error');
			}
			// add to <head> to start loading
			head.appendChild(script);
			$(script).trigger('curry-load');
		}
		
		if(script.status == 'success') {
			if($.isFunction(success))
				success.call(script);
		} if(script.status == 'error') {
			if($.isFunction(error))
				error.call(script);
		} else {
			if($.isFunction(success))
				$(script).one('curry-success', success);
			if($.isFunction(error))
				$(script).one('curry-error', error);
		}
		
		return script;
	}
	
	// Load css stylesheets
	// source: http://thudjs.tumblr.com/post/637855087/stylesheet-onload-or-lack-thereof
	$.require_css = function(src, success, error) {
		if(!$.isPlainObject(loaded_css)) {
			loaded_css = {};
			$("link[type='text/css']").each(function() {
				loaded_css[$(this).attr('href')] = this;
				this.status = 'success';
			});
		}
		
		var link = loaded_css[src];
		if(!link) {
			var head = document.getElementsByTagName('head')[0]
			link = loaded_css[src] = document.createElement('link');
			link.setAttribute('href', src);
			link.setAttribute('rel', 'stylesheet');
			link.setAttribute('type', 'text/css');
			
			var sheet = 'styleSheet', cssRules = 'rules';
			if ('sheet' in link) {
				sheet = 'sheet';
				cssRules = 'cssRules';
			}

			var interval_id = setInterval(function() {
				try {
					if (link[sheet] && link[sheet][cssRules].length) {
						clearInterval(interval_id);
						clearTimeout(timeout_id);
						link.status = 'success';
						$(link).trigger('curry-success');
					}
				}
				catch( e ) {}
			}, 10),
			timeout_id = setTimeout(function() {
				clearInterval(interval_id);
				clearTimeout(timeout_id);
				head.removeChild(link);
				link.status = 'error';
				$(link).trigger('curry-error');
			}, 15000);
			// add to <head> to start loading
			head.appendChild(link);
			$(link).trigger('curry-load');
		}
		
		if(link.status == 'success') {
			if($.isFunction(success))
				success.call(link);
		} if(link.status == 'error') {
			if($.isFunction(error))
				error.call(link);
		} else {
			if($.isFunction(success))
				$(link).one('curry-success', success);
			if($.isFunction(error))
				$(link).one('curry-error', error);
		}
		
		return link;
	}
	
	// Register a library (set of javascript and css files)
	$.registerLibrary = function(name, requirements) {
		if(typeof name == 'string') {
			libraries[name] = requirements;
			if(libraries[name].preload)
				$.require(name);
		} else if($.isPlainObject(name)) {
			for(var n in name) {
				libraries[n] = name[n];
				if(libraries[n].preload)
					$.require(n);
			}
		}
	}
	
	// Load a registered library
	$.require = function(library, success, error) {
		var sync = $.sync();
		if($.isArray(library)) {
			for(var i = 0; i < library.length; ++i)
				$.require(library[i], sync.wait(), error);
			sync.done(success);
		} else if(typeof libraries[library] == 'object') {
			var lib = libraries[library];
			// If library is loaded, we can call success and return
			if(lib.isInitialized) {
				if($.isFunction(success))
					success();
				return;
			}
			// Load dependencies...
			var depsync = $.sync();
			if(typeof lib.dep == 'string')
				$.require(lib.dep, depsync.wait(), error);
			else if($.isArray(lib.dep))
				for(var i = 0; i < lib.dep.length; ++i)
					$.require(lib.dep[i], depsync.wait(), error);
			// When dependencies are loaded...
			depsync.done(function() {
				// Load css
				if(typeof lib.css == 'string')
					$.require_css(lib.css, sync.wait(), error);
				else if($.isArray(lib.css))
					for(var i = 0; i < lib.css.length; ++i)
						$.require_css(lib.css[i], sync.wait(), error);
				// Load js
				if(typeof lib.js == 'string')
					$.require_js(lib.js, sync.wait(), error);
				else if($.isArray(lib.js)) {
					if(lib.sequential) {
						var cb = sync.wait();
						var loadNext = function(index) {
							$.require_js(lib.js[index], function() {
								++index;
								if(index < lib.js.length)
									loadNext(index);
								else
									cb();
							}, error);
						}
						loadNext(0);
					} else {
						for(var i = 0; i < lib.js.length; ++i)
							$.require_js(lib.js[i], sync.wait(), error);
					}
				}
				// When js/css are loaded...		
				sync.done(function() {
					if($.isFunction(lib.init) && !lib.isInitialized)
						lib.init();
					lib.isInitialized = true;
					if($.isFunction(success))
						success();
				});
			});
		} else {
			alert('Library \''+library+'\' not recognized.');
			if($.isFunction(error))
				error();
			return;
		}
	}
	
	// Register a new component
	$.registerComponent = function(selector, component, requirements) {
		components.push({selector: selector, component: component, requirements: requirements});
	}
	
	var initComponent = function(cc, $scope) {
		if(typeof cc.component == 'string')
			$scope[cc.component]();
		else if($.isFunction(cc.component))
			cc.component($scope);
	}
	
	// Initialize all components on new content
	$(document).bind('curry-init', function(e) {
		var sync = $.sync();
		for(var c in components) {
			var cc = components[c];
			var $scope = (typeof cc.selector == 'string' ? $(cc.selector, e.target) : $(e.target));
			if($scope.length) {
				if(cc.requirements)
					$.require(cc.requirements, (function(c, s, w) { return function() { initComponent(c, s); w(); }; })(cc, $scope, sync.wait()));
				else
					initComponent(cc, $scope);
			}
		}
		sync.done(function() {
			$(e.target).trigger('curry-init-complete');
		});
	});
	
})(jQuery);
