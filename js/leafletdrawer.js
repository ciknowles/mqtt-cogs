
if (allmaps && allmaps.length>0) {
    for(var i=0;i<allmaps.length;i++) {
		var self = this;
        var mapinfo = self.allmaps[i];
		mapinfo.options = Function('"use strict";return (' + mapinfo.options + ')')();
        mapinfo.globaloptions = Function( '"use strict";return (' + mapinfo.globaloptions + ')')();

        mapinfo.options = jQuery.extend(true, {}, mapinfo.globaloptions, mapinfo.options);
		mapinfo.options.initalized = false;
        
		mapinfo.map =  L.map(mapinfo.id, mapinfo.options); 
		if (mapinfo.script) {
			mapinfo.script = new Function('"use strict"; return ' + mapinfo.script + ';')();
		} 
		else {
			mapinfo.script = function (data, map, mapoptions) {
				var markerArray = [];
				var data_datetime;
				var data_payload;
				var data_topic;

				//loop through rows
				for (var ridx=0;ridx<data.getNumberOfRows();ridx++) {
					for (var cidx=1;cidx<data.getNumberOfColumns();cidx++) {
						//ignore if null
						if (data.getValue(ridx, cidx) == null) {
							continue;
						}
						
						//extract the bits we understand
						data_datetime =  data.getValue(ridx,0);
						data_payload = data.getValue(ridx,cidx) ;
						data_topic = data.getColumnId(cidx);

						//Firstly, we assume that the payload is an object and contains 
						//a lng, lat or lon lat property
						var lnglat = {
							lat: data_payload.lat?data_payload.lat:data_payload.latitude,
							lng: data_payload.lon?data_payload.lon:(data_payload.lng?data_payload.lng:data_payload.longitude)
						}
						
						//If it was an object. Not sure how to output the target value here.
						//WIP
						if (lnglat.lat && lnglat.lng) {
							markerArray.push(mapinfo.makeMarker(lnglat.lng, lnglat.lat, data_topic, data_datetime, data_payload)
							.openPopup());
							continue;
						}
						
						//Is a simple object OR doesn't contain lng lat in payload
						//try and find lng lat from column property
						var colproplnglat= data.getColumnProperty(cidx, 'lnglat');
						if (colproplnglat) {		
							//is a simple comma delimited value
							if (!colproplnglat.type) {
								colproplnglat = payload.split(',') ;
								if (colproplnglat.length==2) {
									lnglat.lng = parseInt(payload[0]);
									lnglat.lat = parseInt(payload[1]);
								}
								//lon lat from lnglat field so return here
								if (lat && lon) {
									markerArray.push(mapinfo.makeMarker(lnglat.lng, lnglat.lat, data_topic, data_datetime, data_payload)
									.openPopup());
									continue;
								}					
							}
							//shape from geoJSON
							else {
								markerArray.push(mapinfo.makeGeoJSONMarker(lnglat.lng, lnglat.lat, data_topic, data_datetime, data_payload));
							}
						}
					}	
				}

				var group = L.featureGroup(markerArray).addTo(map);
				
				if (!mapoptions.initialized)  {
					map.fitBounds(group.getBounds());
					mapoptions.initialized = true;
				}
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

		mapinfo.makeMarker = function (lng, lat, data_topic, data_datetime, data_payload) {
			return L.marker(new Array(lat, lng)).bindPopup(mapinfo.makeFlag(data_topic, data_datetime, data_payload));
		}

		mapinfo.makeGeoJSONMarker = function (lng, lat, data_topic, data_datetime, data_payload) {
			return L.geoJSON(colproplnglat).bindPopup(mapinfo.makeFlag(data_topic, data_datetime, data_payload));
		}

		mapinfo.makeFlag = function(data_topic, data_datetime, data_payload) {
			return data_payload + ' @ ' + data_datetime;
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
		
		
	
		
