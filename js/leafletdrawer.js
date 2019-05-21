var $jsonp = (function(){
  var that = {};

  that.send = function(src, options) {
    var callback_name = options.callbackName || 'callback',
      on_success = options.onSuccess || function(){},
      on_timeout = options.onTimeout || function(){},
      timeout = options.timeout || 10; // sec

    var timeout_trigger = window.setTimeout(function(){
      window[callback_name] = function(){};
      on_timeout();
    }, timeout * 1000);

    window[callback_name] = function(data){
      window.clearTimeout(timeout_trigger);
      on_success(data);
    }

    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.async = true;
    script.src = src;

    document.getElementsByTagName('head')[0].appendChild(script);
  }

  return that;
})();


if (allmaps && allmaps.length>0) {
    for(var i=0;i<allmaps.length;i++) {
		var self = this;
        var mapinfo = self.allmaps[i];
		mapinfo.map =  L.map(mapinfo.id, eval('('+mapinfo.options+')')); 
		
		if (mapinfo.tilelayers) {
			var tilelayers = Function('"use strict";return (' + mapinfo.tilelayers + ')')();
			tilelayers = [].concat(tilelayers);
			for (var tl = 0; tl<tilelayers.length; tl++) {
				L.tileLayer(tilelayers[tl].urlTemplate, tilelayers[tl].options)
				.addTo(mapinfo.map);
			}
		}

		//callback when new data arrives
		mapinfo.responseHandler = function (response) {
		    var self = this;
			
		}
		
		var uid =  Math.random().toString(36).substr(2, 9);		
		//call to get new data
		mapinfo.send = function() {
			//tqx=responseHandler:TEST
			var self = this;
			$jsonp.send(mapinfo.querystring + '&' + 'tqx=responseHandler:' + uid, {
				callbackName: uid,
				onSuccess: self.responseHandler,
				onTimeout: function(){
					console.log('timeout!');
				},
				timeout: 5
			});
			
		}
		
		mapinfo.send.bind(mapinfo)();
	}
}
		
		
	
		
