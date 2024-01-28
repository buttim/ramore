<!DOCTYPE HTML>
<html>
	<head>
		<title>Radio monitoraggio remoto</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes" />
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/zebra_dialog@latest/dist/css/flat/zebra_dialog.min.css">
		<link rel="stylesheet" href="waitMe.min.css" />
		<style>
		body { font-family: arial; background-color:#D4FFE9; user-select:none }
		input { top-padding:10px }
		#f { width: 5em; }
		#banda { width: 4em; }
		#soglia { width: 3em; }
		#correzione { width: 3em; }
		#ascolto { margin-bottom: 50px }
		#modalita { color: blue }
		#input { float:left }
		#impostazioni { margin-top:10px }
		#chart { background-color:rgba(0,0,0,.1) }
		#chart-container { float:right; border :1px solid lightgray; width:calc(100% - 500px); height:400px }
		#tLastRec { margin-top: 10px;  font-weight: bold }
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
"use strict";
var chart, url='http://192.168.1.196:9999/';//TODO: localhost!
const suggerimentoAscolto=`Adesso &egrave; possibile collegarsi remotamente con SDR# specificando sorgente "RTL TCP" e indirizzo ${location.host}:1234`,
	suggerimentoMonitor='Il grafico mostra l&apos;analisi del segnale ricevuto ogni 10 secondi',
	minFftValue=-60, maxFftValue=60;
var parametriModificati=false;
	
function getStato() {
	$.getJSON(url+'stato')
		.done(function(stato) {
			$('#modalita').html(stato.modo);
			if (stato.modo=='ascolto') {
				$('#btnAscolto').prop('disabled',true);
				$('#suggerimenti').html(suggerimentoAscolto);
				$('#chart-container').hide();
			}
			else if (stato.modo=='monitor') {
				$('#suggerimenti').html(suggerimentoMonitor);
				$('#chart-container').show();
			}
			
			$('#_f').html((parseFloat(stato.f)/1E6).toFixed(3));
			$('#_banda').html((parseFloat(stato.bw)/1000).toFixed(1));
			$('#_soglia').html(stato.thr);
			if (stato.lastRec.data==null) return;
			$('#imgLink').show();
			$('#tLastRec').html($.timeago(Date.parse(stato.lastRec.time)));
			chart.data.labels=Array(stato.lastRec.data.length).fill('');
			chart.data.datasets[0].data=stato.lastRec.data.map(x=>[minFftValue,x]);
			chart.update();
		})
		.fail(function(jqxhr, textStatus, error ) {
			new $.Zebra_Dialog("Impossibile connettersi al server",{auto_close:3000});
		})
		.always(function() {
			setTimeout(function(){getStato();},10000);
		 });
}

function getCookies() {
	var res={};
	var a=document.cookie.split(';');
	a.forEach(function(x) {
		var pair=x.split('=');
		res[pair[0].trim()]=pair[1].trim();
	});
	return res;
}

$(function () {
	var cookies=getCookies();

	if ('f' in cookies)
		$('#f').val(cookies['f']/1E6);
	if ('bw' in cookies)
		$('#banda').val(cookies['bw']/1000);
	if ('thr' in cookies)
		$('#soglia').val(cookies['thr']);
	if ('ppm' in cookies)
		$('#correzione').val(cookies['ppm']);

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
	$('#imgLink').hide();
	$('#imgLink').prop('href',url+'img');	//TODO: pulire
	$('#btnMonitor').on('click',function() {
		$('body').waitMe({
			effect: 'bouncePulse',
			bg: 'rgba(255,255,255,0.9)',
			text:'cambio modalità in corso...'
		});
		var f=Math.trunc(parseFloat($('#f').val())*1e6),
			bw=Math.trunc(parseFloat($('#banda').val()*1000)),
			thr=parseInt($('#soglia').val()),
			ppm=parseInt($('#correzione').val());
		document.cookie=`f=${f}`;
		document.cookie=`bw=${bw}`;
		document.cookie=`thr=${thr}`;
		document.cookie=`ppm=${ppm}`;
		$.get(url+'monitor',{ f : f, bw : bw, thr : thr, ppm : ppm })
			.done(function(x) {
				console.log(x);
			})
			.fail(function(jqxhr, textStatus, error ) {
				new $.Zebra_Dialog("Impossibile connettersi al server",{auto_close:3000});
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
				new $.Zebra_Dialog("Impossibile connettersi al server",{auto_close:3000});
			})
			.always(function() {
				$('body').waitMe('hide');
			});
			getStato();
	});
	chart=new Chart($('#chart'), {
		type: 'bar',
		data: {
			//labels: ['', '', '', '', '', ''],
			datasets: [{
				label: 'Livello',
				barPercentage: 1,
				categoryPercentage: 1,
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins : {
				tooltip : { enabled : false },
				legend : { display : false }
			},
			scales: {
				x : { grid : { display : false } },
				y : { beginAtZero : false, min : minFftValue, max: maxFftValue }
			}
		}
	});
	getStato();
});
		</script>
	</head>
	<body>
		<div id='input'>
			<h1><span class='rosso'>
				Ra</span>dio <span class='rosso'>mo</span>nitoraggio <span class='rosso'>re</span>moto
			</h1>
			<div class='row' id='ascolto'>
				<h2>Modalità: <span id='modalita'>inattivo</span></h2>
				<input type='button' value='Ascolto' id='btnAscolto'></input>
			</div>
			<div class='row'>
				<label for='f' accesskey='q'>Frequenza</label>
				<input type='number' id='f' min='150' max='500' value='170.000' class='parametri'></input> MHz
			</div>
			<div class='row'>
				<label for='banda' accesskey='b'>Banda</label>
				<input type='number' id='banda' min='2' max='25' value='5' class='parametri'></input> kHz
			</div>
			<div class='row'>
				<label for='soglia'  accesskey='s'>Soglia</label>
				<input type='number' id='soglia' min='-60' max='60' value='5' class='parametri'></input>
			</div>
			<div class='row'>
				<label for='correzione'  accesskey='s'>Correzione</label>
				<input type='number' id='correzione' min='-100' max='100' value='0' class='parametri'></input> ppm
			</div>
			<div class='row'>
				<input type='button' value='Avvia monitoraggio' id='btnMonitor'></input>
			</div>
			<div id='suggerimenti'>
			</div>
		</div>
		<div id='chart-container'>
			<canvas id="chart"></canvas>
			<div id='impostazioni'>
				Frequenza: <span id='_f' class='impostazione'></span>MHz
				Banda: <span id='_banda' class='impostazione'></span>kHz
				Soglia: <span id='_soglia' class='impostazione'></span>
			</div>
			<div style='float:left'>Ultimo aggiornamento <span id='tLastRec'>-</span></div>
			<a id='imgLink' target='_new' href='#' style='display:hidden; float:right'>immagine acquisizione</a>
		</div>
	</body>
</html>