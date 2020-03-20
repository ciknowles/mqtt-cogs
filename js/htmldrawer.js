
if (allhtmls && allhtmls.length>0) {
    for(var i=0;i<allhtmls.length;i++) {
		var self = this;
        var htmlinfo = self.allhtmls[i];
		
		if (htmlinfo.script) {
					htmlinfo.script = new Function('"use strict"; return ' + htmlinfo.script + ';')();
		} 
		else {
			htmlinfo.script = function (data) {
				var target = jQuery('#' + this.id).first(); 
				
				var content = this.content.replace(/\{.+?\}/g, function(match, num) {	
					//{something}
					var val;
					//strip braces
					match = match.substring(1, match.length-1);
					
					//does it contain a comma
					var matches = match.split(',');
					if (matches.length>1) {
						val= data.getValue(parseInt(matches[0]), parseInt(matches[1]));
					}
					else {
						val = data.getValue(0, parseInt(matches[0]));
					}
					if ((val instanceof Date) && (this.dateformat)) {
						val = moment(val).format(this.dateformat);
					}
					return val;
				});
				target.html(content);
			}
		}

				
		htmlinfo.responseHandler = function (response) {
		    var self = this;
			if (response.isError()) {
				alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
			}
			else {
				var data = response.getDataTable();	 
				
				if (self.script) {
					self.script(data);
				}
			}
			
			if (parseInt(self.refresh_secs)>0) {
				setTimeout(function () {
					self.query.send(self.responseHandler.bind(self));
				},parseInt(self.refresh_secs)*1000);
			}
		};
    
		htmlinfo.onLoadCallback = function () {
		    var self = this;
            self.query = new google.visualization.Query(self.querystring);
            self.query.send(self.responseHandler.bind(self));
        };
		
        google.charts.setOnLoadCallback(htmlinfo.onLoadCallback.bind(htmlinfo));
	}
}
		
		
	
		
