
if (allmaps && allmaps.length>0) {
    for(var i=0;i<allmaps.length;i++) {
		var self = this;
        var mapinfo = self.allmaps[i];
		mapinfo.options = Function('"use strict";return (' + mapinfo.options + ')')();
        mapinfo.globaloptions = Function( '"use strict";return (' + mapinfo.globaloptions + ')')();

        mapinfo.options = jQuery.extend(true, {}, mapinfo.globaloptions, mapinfo.options);
        
	
	
		mapinfo.map =  L.map(mapinfo.id, mapinfo.options); 
		if (mapinfo.script) {
					mapinfo.script = new Function('"use strict"; return ' + mapinfo.script + ';')();
		} 
		else {
			mapinfo.script = function (data, map, mapoptions) {
				var markerArray = [];
                var payload;
				//loop through rows
				for (var ridx=0;ridx<data.getNumberOfRows();ridx++) {
					for (var cidx=1;cidx<data.getNumberOfColumns();cidx++) {
						//ignore if null
						if (data.getValue(ridx, cidx) == null) {
							continue;
						}
						
						//find a lat or lon
						payload = data.getValue(ridx, cidx);
						var lat = payload.lat?payload.lat:payload.latitude;
						var lon = payload.lon?payload.lon:(payload.lng?payload.lng:payload.longitude);
						
						//lon lat from payload so add marker and return
						if (lat && lon) {
							markerArray.push(L.marker(new Array(lat, lon))
							.bindPopup(data.getColumnId(cidx) + ' @ ' + data.getValue(ridx,0)));
							continue;
						}
						
						//lon lat from lnglat column property
						payload= data.getColumnProperty(cidx, 'lnglat');
						if (payload) {		
							//is a simple comma delimited value
							if (!payload.type) {
								payload = payload.split(',') ;
								if (payload.length==2) {
									lon = parseInt(payload[0]);
									lat = parseInt(payload[1]);
								}
								//lon lat from lnglat field so return here
								if (lat && lon) {
									markerArray.push(L.marker(new Array(lat, lon)).bindPopup(data.getColumnId(cidx) + ' @ ' + data.getValue(ridx,0)));
									continue;
								}					
							}
							//shape from geoJSON
							else {
								markerArray.push(L.geoJSON(payload).bindPopup(data.getColumnId(cidx) + ' @ ' + data.getValue(ridx,0)));
							}
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
				
				if (self.script) {
					self.script(data, self.map, self.options);
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
		
		
	
		
