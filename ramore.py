#!/usr/bin/python3
import os, json, math, threading, logging, signal, sys, time, bot
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs
from datetime import date, datetime
from logging.handlers import SysLogHandler
from subprocess import *

HOST_NAME = "0.0.0.0"
SERVER_PORT = 9999
RTL_POWER = 'rtl_power' if sys.platform != 'win32' else "C:/rtl_sdr/rtl_power.exe"
RTL_TCP = 'rtl_tcp' if sys.platform != 'win32' else "C:/rtl_sdr/rtl_tcp.exe"

modo='ascolto'
freq = 868E6
threshold = 0
bw = 1E6
lock = threading.Lock()
proc = None
lastRecording = None
tLastRec = None

# https://stackoverflow.com/questions/11875770/how-to-overcome-datetime-datetime-not-json-serializable
def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""

    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    raise TypeError("Type %s not serializable" % type(obj))


def rtl_power():
    global proc
    
    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)
    proc = Popen([RTL_POWER,"-f",f'{int(freq-bw/2)}:{int(freq+bw/2)}:{math.trunc(bw/32)}'],
          encoding='utf8',bufsize=0,stdout=PIPE)
    bot.msg("Attivata modalità monitoraggio")
    #TODO: attesa partenza o errore
    

def rtl_tcp():
    global proc
    
    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)
    proc = Popen([RTL_TCP,"-a","0.0.0.0"],encoding='utf8')
    bot.msg("Attivata modalità ascolto remoto")
    #TODO: attesa partenza o errore
    
def analisi(line):
    global threshold, lastRecording, tLastRec
    
    a=line.split(',')
    if len(a)<10:
        return None
    data=a[0]
    ora=a[1]
    freqLo=int(a[2])
    freqHi=int(a[3])
    binSize=float(a[4])
    unk=a[5]
    a = [float(x) for x in a[6:]]
    tLastRec=datetime.now()
    lastRecording = a
    return any(x>threshold for x in a)
    
class MyServer(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.0"

    def do_GET(self):
        global modo,freq, threshold,bw
        try:
            print(self.path)
            uri = urlparse(self.path)
            params = parse_qs(uri.query)
            if self.path == "/favicon.ico":
                self.send_response(404)
                self.end_headers()
                return
            if uri.path == "/stato":
                self.send_response(200)
                self.send_header("Content-type", "application/json")
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                status = {
                    "modo": modo,
                    "f": freq,
                    "thr": threshold,
                    "bw": bw,
                    "lastRec": {"time": tLastRec, "data": lastRecording},
                }
                self.wfile.write(bytes(json.dumps(status, default=json_serial), "utf-8"))
                return
            if uri.path == "/ascolto":
                response = "OK"
                with lock:
                    modo='ascolto'
                    try:
                        rtl_tcp()
                    except Exception as e:
                        response="KO"
                        return
                self.send_response(200)
                self.send_header("Content-type", "text/plain")
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                self.wfile.write(bytes(response, "utf-8"))
                return
            if uri.path == "/monitor":
                if "f" in params:
                   freq = float(params["f"][0])
                if "thr" in params:
                   threshold = float(params["thr"][0])
                if "bw" in params:
                    bw = float(params["bw"][0])
                with lock:
                    modo='monitor'
                    rtl_power()
                self.send_response(200)
                self.send_header("Content-type", "text/plain")
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                self.wfile.write(bytes("OK", "utf-8"))
                return
        except Exception as x:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            logger.error(exc_tb.tb_lineno, x)


def threadFunc():
    webServer = ThreadingHTTPServer((HOST_NAME, SERVER_PORT), MyServer)
    while True:
        try:
            webServer.serve_forever()
        except KeyboardInterrupt:
            break
        except Exception as x:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            logger.error(exc_tb.tb_lineno, x)
    webServer.server_close()

logger = logging.getLogger(__name__)
formatter = logging.Formatter(fmt="%(asctime)s: %(message)s", datefmt="%H:%M:%S")
if sys.platform!='win32':
    handler = SysLogHandler(address="/dev/log")
    handler.setLevel(logging.WARNING)
    handler.setFormatter(formatter)
    logger.addHandler(handler)
handler = logging.StreamHandler()
handler.setLevel(logging.DEBUG)
handler.setFormatter(formatter)
logger.addHandler(handler)

rtl_power()
modo='monitor'

t = threading.Thread(target=threadFunc, daemon=True)
t.start()

try:
    while True:
        time.sleep(1)
        with lock:
            if modo=='monitor' and proc is not None:
                try:
                    line = proc.stdout.readline().rstrip()
                except Exception:
                    print('eccezione in lettura rtl_power')
                else:
                    print(line)
                    res = analisi(line)
                    if res is not None:
                        if res:
                            bot.msg('Rilevato segnale')
                    
except KeyboardInterrupt as e:
    print('chiusura')
    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)