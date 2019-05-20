
if (allmaps && allmaps.length>0) {
    for(var i=0;i<allmaps.length;i++) {
		var self = this;
        var mapinfo = self.allmaps[i];
		mapinfo.map =  L.map(mapinfo.id, eval('('+mapinfo.options+')')); 
		
		if (mapinfo.tilelayers) {
			var tilelayers = eval('('+mapinfo.tilelayers+')');
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
		
		//call to get new data
		mapinfo.requestSender = function() {
			
		}
	}
}
		
		
	
		
