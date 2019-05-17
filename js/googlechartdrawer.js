
if (allcharts && allcharts.length>0) {
    for(var i=0;i<allcharts.length;i++) {
        //add it to object that is already in page?
        //chart/query/
        var self = this;
        var chartinfo = self.allcharts[i];
        google.charts.setOnLoadCallback(function () {
            
            chartinfo.chart = new google.visualization[chartinfo.charttype](document.getElementById(chartinfo.id));    
            chartinfo.query = new google.visualization.Query(chartinfo.querystring);
        
            chartinfo.responsehandler = function (response) {
                    	if (response.isError()) {
                			alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
            			}
            			else {
            				var data = response.getDataTable();	 
            				chartinfo.chart.draw(data, eval('('+chartinfo.options+')'));
            			}
            	        
            	        if (parseInt(chartinfo.refresh_secs)>0) {
                	        setTimeout(function () {
                	            chartinfo.query.send(chartinfo.responsehandler);
                	        },parseInt(chartinfo.refresh_secs)*1000);
            	        }
                };
        
            chartinfo.query.send(chartinfo.responsehandler);
        });
    }
}
    