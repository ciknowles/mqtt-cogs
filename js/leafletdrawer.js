
if (allmaps && allmaps.length>0) {
    for(var i=0;i<allmaps.length;i++) {
		var self = this;
        var mapinfo = self.allmaps[i];
		mapinfo.map =  L.map(mapinfo.id, eval('('+mapinfo.options+')')); 
		if (mapinfo.script) {
					mapinfo.script = Function('"use strict"; return ' + mapinfo.script)();
		} 
		else {
			mapinfo.script = function (data, map, mapoptions) {
				var markerArray = [];

				//loop through rows
				for (var ridx=0;ridx<data.getNumberOfRows();ridx++) {
					for (var cidx=1;cidx<data.getNumberOfColumns();cidx++) {
						//ignore if null
						if (data.getValue(ridx, cidx) == null) {
							continue;
						}
						
						//find a lat or lon
						var payload = data.getValue(ridx, cidx);
						var lat = payload.lat?payload.lat:payload.latitude;
						var lon = payload.lon?payload.lon:(payload.lng?payload.lng:payload.longitude);
						
						if (lat && lon) {
							markerArray.push(L.marker(new Array(lat, lon))
							.bindPopup(data.getColumnLabel(cidx) + ' @ ' + data.getValue(ridx,0)));
						}
					}	
				}

				var group = L.featureGroup(markerArray).addTo(map);
				map.fitBounds(group.getBounds());
			}
		}

					
		if (mapinfo.tilelayers) {
			var tilelayers = Function('"use strict";return (' + mapinfo.tilelayers + ')')();
			tilelayers = [].concat(tilelayers);
			for (var tl = 0; tl<tilelayers.length; tl++) {
				L.tileLayer(tilelayers[tl].urlTemplate, tilelayers[tl].options)
				.addTo(mapinfo.map);
			}
		}

		mapinfo.responseHandler = function (response) {
		    var self = this;
			if (response.isError()) {
				alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
			}
			else {
				var data = response.getDataTable();	 
				
				if (mapinfo.script) {
					mapinfo.script(data, mapinfo.map, mapinfo.options);
				}
			}
			
			if (parseInt(self.refresh_secs)>0) {
				setTimeout(function () {
					self.query.send(self.responseHandler.bind(self));
				},parseInt(self.refresh_secs)*1000);
			}
		};
    
		mapinfo.onLoadCallback = function () {
		    var self = this;
            self.query = new google.visualization.Query(self.querystring);
            self.query.send(self.responseHandler.bind(self));
        };
		
	
        google.charts.setOnLoadCallback(mapinfo.onLoadCallback.bind(mapinfo));
	}
}
		
		
	
		