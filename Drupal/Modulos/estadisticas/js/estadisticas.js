(function (parameter) {
	'use strict';
	let chart_path = location.protocol + '//' + location.host + '/sites/default/files/Graficas/charts';

	// se identifica que boton fue presionado
	document.getElementById('edit-button-2').onclick = check_click;
	document.getElementById('edit-date-0-submit-2').onclick = check_click;
	document.getElementById('edit-date-1-submit-2').onclick = check_click;
	document.getElementById('edit-date-2-submit-2').onclick = check_click;
	document.getElementById('edit-date-3-submit-2').onclick = check_click;
	document.getElementById('edit-date-4-submit-2').onclick = check_click;
	document.getElementById('edit-sites-button-2').onclick = check_click;
	document.getElementById('edit-button-2--2').onclick = check_click; //setTimeout(check_click, 2000);
	document.getElementById('edit-date-5-button-2').onclick = check_click;
	
	// permite retrazar por un lapso de tiempo la llamada a una función
	function sleep(ms) {
		return new Promise(resolve => setTimeout(resolve, ms));
	}

	// identifica que grafica se va a mostrar y abre una ventana con la gráfica
	async function check_click(clicked) {
		let chart;
		switch (this.id) {
			case "edit-button-2":    
				chart = "/hc_chart.html";
				break;
			case "edit-date-0-submit-2":
				chart = "/rd_chart.html";
				break;
			case "edit-date-1-submit-2":
				chart = "/hd_chart.html";
				break;
			case "edit-date-2-submit-2":
				chart = "/hi_chart.html";
				break;
			case "edit-date-3-submit-2":
				chart = "/h_d_chart.html";
				break;
			case "edit-date-4-submit-2":
				chart = "/p_chart.html";
				break;
			case "edit-sites-button-2":
				chart = "/s_chart.html";
				break;
			case "edit-button-2--2":
				chart = "/hip_chart.html";
				break;
			case "edit-date-5-button-2":
				chart = "/d_chart.html";
				break;
		}
		await new Promise(r => setTimeout(r, 300));
		var n = "width=600,height=500,status=0,titlebar=0,scrollbars=0,menubar=0,toolbar=0,location=0,resizable=1";
		window.open(chart_path + chart, chart, n);
	} 
})();
