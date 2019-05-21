
if (allcharts && allcharts.length>0) {
    for(var i=0;i<allcharts.length;i++) {
        //add it to object that is already in page?
        //chart/query/
        var self = this;
        var chartinfo = self.allcharts[i];
		
		chartinfo.responseHandler = function (response) {
		    var self = this;
                	if (response.isError()) {
            			alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
        			}
        			else {
        				var data = response.getDataTable();	 
						
        				self.chart.draw(data, Function('"use strict";return (' + self.options + ')')());
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
    