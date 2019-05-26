
if (allcharts && allcharts.length>0) {
    for(var i=0;i<allcharts.length;i++) {
        //add it to object that is already in page?
        //chart/query/
        var self = this;
        var chartinfo = self.allcharts[i];
        chartinfo.options = Function( '"use strict";return (' + chartinfo.options + ')')();
        
		if (chartinfo.script) {
			chartinfo.script =new  Function('"use strict";return ' + chartinfo.script + ';')();
		}
		else {
			chartinfo.script = function (data, chart, options) {
				
				if (this.charttype == 'Map') {				
					var view  = new google.visualization.DataView(data);
					
					view.setColumns([
									{
										calc:function (data, ridx) {
											var payload = data.getValue(ridx, 1);
											return payload.lat?payload.lat:payload.latitude;
										}, 
										type:'number', 
										label:'Lat'
									},
									{
										calc:function (data, ridx) {
											var payload = data.getValue(ridx, 1);
											return payload.lon?payload.lon:(payload.lng?payload.lng:payload.longitude);
										}, 
										type:'number', 
										label:'Long'
									},
									{
										calc:function (data, ridx) {
											return data.getColumnId(1) + ' @ ' + data.getValue(ridx,0);
										},
										type:'string', 
										label:'Description'
									}
								]);
					
					chart.draw(view, options);
				} 
				else {
					chart.draw(data, options);
				}
			}
		}
		
		chartinfo.responseHandler = function (response) {
		    var self = this;
			if (response.isError()) {
				alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
			}
			else {
				var data = response.getDataTable();	 	
				
				if (self.script) {
					self.script.bind(self)(data, self.chart, self.options);
				}
			}
			
			if (parseInt(self.refresh_secs)>0) {
				setTimeout(function () {
					self.query.send(self.responseHandler.bind(self));
				},parseInt(self.refresh_secs)*1000);
			}
		};
    
		chartinfo.onLoadCallback = function () {
		    var self = this;
            self.chart = new google.visualization[self.charttype](document.getElementById(self.id));    
            self.query = new google.visualization.Query(self.querystring);
            self.query.send(self.responseHandler.bind(self));
        };
		
	
        google.charts.setOnLoadCallback(chartinfo.onLoadCallback.bind(chartinfo));
    }
}
    