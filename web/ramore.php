<!DOCTYPE HTML>
<html>
	<head>
		<title>Radio monitoraggio remoto</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes" />
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/zebra_dialog@latest/dist/css/flat/zebra_dialog.min.css">
		<link rel="stylesheet" href="waitMe.min.css" />
		<style>
html, body { height: 100%; }
body { font-family: arial; background-color:#D4FFE9; user-select:none }
input { top-padding:10px }
#f { width: 5em; }
#banda { width: 4em; }
#soglia { width: 3em; }
#ascolto { margin-bottom: 50px }
#modalita { color: blue }
#input { float:left }
#impostazioni { margin-top:10px }
#chart-container { float:right; border :1px solid lightgray; width:600px; height:400px }
#tLastRec { margin-top: 10px }
#suggerimenti { margin-top:60px; width: 300px }
.rosso { color:indianred }
.row { margin-top:10px }
.impostazione { font-weight: bold }
		</style>
		<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.0.1/chart.umd.js"></script>
		<script src='https://timeago.yarp.com/jquery.timeago.js'></script>
		<script src="https://cdn.jsdelivr.net/npm/zebra_dialog@latest/dist/zebra_dialog.min.js"></script>
		<script type="module" src='waitMe.min.js'></script>
		<script>
var chart, url='http://192.168.1.196:9999/';//TODO: localhost!
const suggerimentoAscolto=`Adesso è possibile collegarsi remotamente con SDR# specificando sorgente "RTL TCP" e indirizzo ${location.host}:1234`,
	suggerimentoMonitor='Il grafico mostra l&quot;analisi del segnale ricevuto ogni 10 secondi';
	
function getStato() {
	$.getJSON(url+'stato')
		.done(function(stato) {
			$('#modalita').html(stato.modo);
			if (stato.modo=='ascolto') {
				$('#btnAscolto').prop('disabled',true);
				$('#btnMonitor').prop('disabled',false);
				$('#suggerimenti').html(suggerimentoAscolto);
				$('#chart-container').hide();
			}
			else if (stato.modo=='monitor') {
				$('#btnMonitor').prop('disabled',true);
				$('#suggerimenti').html(suggerimentoMonitor);
				$('#chart-container').show();
			}
			
			$('#_f').html((parseFloat(stato.f)/1E6).toFixed(3));
			$('#_banda').html((parseFloat(stato.bw)/1000).toFixed(1));
			$('#_soglia').html(stato.thr);
			if (stato.lastRec.data==null) return;
			$('#tLastRec').html($.timeago(Date.parse(stato.lastRec.time)));
			chart.data.labels=Array(stato.lastRec.data.length).fill('');
			chart.data.datasets[0].data=stato.lastRec.data.map(x=>[-30,x]);
			chart.update();
		})
		.fail(function(jqxhr, textStatus, error ) {
			new $.Zebra_Dialog(error);
		})
		.always(function() {
			setTimeout(function(){getStato();},10000);
		 });
}

$(function () {
	$.timeago.settings.strings = {
	    prefixAgo: null,
	    prefixFromNow: "fra",
	    suffixAgo: "fa",
	    suffixFromNow: null,
	    seconds: "meno di un minuto",
	    minute: "1'",
	    minutes: "%d'",
	    hour: "un'ora",
	    hours: "%d ore",
	    day: "un giorno",
	    days: "%d giorni",
	    month: "un mese",
	    months: "%d mesi",
	    year: "circa un anno",
	    years: "%d anni"
	};
	$('#btnMonitor').on('click',function() {
	//TODO: inviare parametri
		$('body').waitMe({text:'cambio modalità in corso...'});
		$.get(url+'monitor')
			.done(function(x) {
				console.log(x);
				$('#btnAscolto').prop('disabled',false);
			})
			.fail(function(jqxhr, textStatus, error ) {
				new $.Zebra_Dialog(error);
			})
			.always(function() {
				$('body').waitMe('hide');
			});
			getStato();
	});
	$('#btnAscolto').on('click',function() {
		$('body').waitMe({text:'cambio modalità in corso...'});
		$.get(url+'ascolto')
			.done(function(x) {
				console.log(x);
				$('#btnAscolto').prop('disabled',true);
			})
			.fail(function(jqxhr, textStatus, error ) {
				new $.Zebra_Dialog(error);
			})
			.always(function() {
				$('body').waitMe('hide');
			});
			getStato();
	});
	chart=new Chart($('#chart'), {
		type: 'bar',
		data: {
			labels: ['', '', '', '', '', ''],
			datasets: [{
				label: 'Livello',
				barPercentage: 1,
				categoryPercentage: 1,
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			animation : false,
			tooltip : { enabled : false },
			plugins : {
				legend : { display : false }
			},
			scales: {
				x : { grid : { display : false } },
				y : { beginAtZero : false, min : -30, max: 30 }
			}
		}
	});
	getStato();
});
		</script>
	</head>
	<body>
		<h1><span class='rosso'>
			Ra</span>dio <span class='rosso'>mo</span>nitoraggio <span class='rosso'>re</span>moto
		</h1>
		<div id='input'>
			<div class='row' id='ascolto'>
				<h2>Modalità: <span id='modalita'>inattivo</span></h2>
				<input type='button' value='Ascolto' id='btnAscolto'></input>
			</div>
			<div class='row'>
				<label for='f' accesskey='q'>Frequenza</label>
				<input type='number' id='f' min='100' max='500'></input> MHz
			</div>
			<div class='row'>
				<label for='banda' accesskey='b'>Banda</label>
				<input type='number' id='banda' min='1' max='100'></input> kHz
			</div>
			<div class='row'>
				<label for='soglia'  accesskey='s'>Soglia</label>
				<input type='number' id='soglia' min='-30' max='30'></input>
			</div>
			<div class='row'>
				<input type='button' value='Avvia monitoraggio' id='btnMonitor'></input>
			</div>
			<div id='suggerimenti'>
				bla
			</div>
		</div>
		<div id='chart-container'>
			<canvas id="chart"></canvas>
			<div id='impostazioni'>
				Frequenza: <span id='_f' class='impostazione'></span>MHz
				Banda: <span id='_banda' class='impostazione'></span>kHz
				Soglia: <span id='_soglia' class='impostazione'></span>
			</div>
			<div>Ultimo aggiornamento <span id='tLastRec'>-</span></div>
		</div>
	</body>
</html>