
if (alltables && alltables.length>0) {
    for(var i=0;i<alltables.length;i++) {
		var self = this;
        var tableinfo = self.alltables[i];
		tableinfo.options = Function('"use strict";return (' + tableinfo.options + ')')();
    
	
		
		
		if (tableinfo.script) {
					tableinfo.script = new Function('"use strict"; return ' + tableinfo.script + ';')();
		} 
		else {
			tableinfo.script = function (data, datatable, datatableoptions) {
				if (!this.datatable) {
					//we have to inject html for column headers
					//datetime, topic1, topic2, topic3...
					var headerelement = jQuery('#' + this.id + '>thead>tr').first(); 
					
					for (var cidx=0;cidx<data.getNumberOfColumns();cidx++) {
							headerelement.append('<th>' + data.getColumnId(cidx) + '</th>');		
					}		
					datatable = jQuery('#' + this.id).DataTable(this.options);		
					this.datatable = datatable;
					
					//datatables can't handle nulls....
					for(var cidx=0;cidx<datatable.columns().length;cidx++) {
							datatable.column(cidx).defaultContent = "";
					}
				}
				
				datatable.clear();
					
				for (var ridx=0;ridx<data.getNumberOfRows();ridx++) {
					var row = [];
					var val = null;
					for (var cidx=0;cidx<data.getNumberOfColumns();cidx++) {
						val = data.getValue(ridx, cidx);
						if (val instanceof String) {
							row.push(val);
						}
						else if (val instanceof Date) {
							row.push(val);
						}
						else if (val instanceof Object) {
							row.push(JSON.stringify(val));
						}
						else {
							row.push(val);
						}
					}
					datatable.row.add(row);		
				}
				
				datatable.draw();
			}
		}

					
	

		tableinfo.responseHandler = function (response) {
		    var self = this;
			if (response.isError()) {
				alert("Error in query: " + response.getMessage() + " " + response.getDetailedMessage());	
			}
			else {
				var data = response.getDataTable();	 
				
				if (self.script) {
					self.script(data, self.datatable, self.options);
				}
			}
			
			if (parseInt(self.refresh_secs)>0) {
				setTimeout(function () {
					self.query.send(self.responseHandler.bind(self));
				},parseInt(self.refresh_secs)*1000);
			}
		};
    
		tableinfo.onLoadCallback = function () {
		    var self = this;
            self.query = new google.visualization.Query(self.querystring);
            self.query.send(self.responseHandler.bind(self));
        };
		
	
        google.charts.setOnLoadCallback(tableinfo.onLoadCallback.bind(tableinfo));
	}
}
		
		
	
		
