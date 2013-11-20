(function($){
	if (!$.bw) { $.bw = {}; };
	
	$.bw.url = function(url) {
		var base = this;
		base.scheme = '';
		base.user = '';
		base.pass = '';
		base.host = '';
		base.port = '';
		base.path = '';
		base.fragment = '';
		base.env = {};
		
		base.fromUrl = function(url) {
			var re = /^((\w+):\/\/)?(([^@:]+)(:([^@]*))?@)?(([^\/:]+)(:(\d+))?)?((\/[^\?#]*)?(\?([^#]+))?(#(.+))?)?/;
			// 1 => http://
			// 2 => http
			// 3 => user:password@
			// 4 => user
			// 5 => :password
			// 6 => password
			// 7 => www.example.com:80
			// 8 => www.example.com
			// 9 => :80
			// 10 => 80
			// 11 => /test.php?foo=bar#frag
			// 12 => /test.php
			// 13 => ?foo=bar
			// 14 => foo=bar
			// 15 => #frag
			// 16 => frag
			var matches = url.match(re);
			if(matches != null) {
				base.scheme = matches[2];
				base.user = matches[4];
				base.pass = matches[6];
				base.host = matches[8];
				base.port = matches[10];
				base.path = matches[12];
				base.env = base._parseQueryString(matches[14]);
				base.fragment = matches[16];
			}
		}
		
		base.doPost = function(data) {
			var $form = $('<form action="' + base.getUrl() + '" method="post" style="display: none">'
				+ base._createHiddenInput(data)
				+ '</form>');
			$form.appendTo('body');
			$form.submit();
		}
		
		base.isAbsolute = function() {
			return base.scheme || base.user || base.pass || base.host || base.port;
		}
		
		base.getBase = function(force) {
			if(base.isAbsolute() || force) {
				var currentUrl = new $.bw.url();
				
				// scheme
				var ret = (base.scheme ? base.scheme : currentUrl.scheme) + "://";
	
				// credentials
				if(base.user || base.pass)
					ret += base.user + ":" + base.pass + '@';
				
				// host
				ret += base.host ? base.host : currentUrl.host;
				
				// port
				if(base.port)
					ret += ":" + base.port;
				
				return ret;
			}
			return "";
		}
		
		base.getUrl = function(separator) {
			var ret = base.getBase();
			if(base.path === "") {
				// use current script
				var currentUrl = new $.bw.url();
				ret += currentUrl.path;
			} else if(base.path === null) {
				;
			} else {
				ret += base.path;
			}
			
			if(base.env) {
				var query = $.param(base.env);
				if(query.length)
					ret += "?" + query;
			}
			
			if(base.fragment)
				ret += "#" + base.fragment;
			return ret;
		}
		
		base._createHiddenInput = function(obj, prefix) {
			if(!prefix)
				prefix = '';
			var query = '';
			for(var prop in obj) {
				var n = prefix.length ? prefix + '[' + prop + ']' : prop;
				var next = '';
				if(typeof obj[prop] == "object")
					next = base._createHiddenInput(obj[prop], n);
				else
					next = '<input type="hidden" name="' + n + '" value="' + obj[prop] + '" />';
				if(next) {
					if(query)
						query += '&';
					query += next;
				}
			}
			return query;
		}
		
		base.toString = function() {
			return base.getUrl();
		}
		
		base._parseQueryString = function(query) {
			var vars = {};
			if(!query)
				return vars;
			var pairs = query.split("&");
			for (var i = 0; i < pairs.length; i++) {
				var data = pairs[i].split("=");
				if (data.length == 2) {
					var name = decodeURIComponent(data[0]);
					var value = decodeURIComponent(data[1]);
                                        // jQuery param substitutes space with + but decodeURIComponent does not reverse
                                        name = name.replace('+', ' ');
                                        value = value.replace('+', ' ');
					if(name.substring(name.length - 2) == '[]') {
						name = name.substring(0, name.length - 2);
						if(typeof vars[name] == 'undefined')
							vars[name] = [value];
						else
							vars[name].push(value);
					} else {
						vars[name] = value;
					}
				}
			}
			return vars;
		}
		
		base.fromUrl(typeof url == "undefined" ? window.location.href : url);
		
		return base;
	}
	
})(jQuery);
