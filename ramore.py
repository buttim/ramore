#!/usr/bin/python3
import os, json, math, threading, logging, shutil, signal, sys, time, tempfile, bot
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs
from datetime import date, datetime
from systemd.journal import JournalHandler
from subprocess import *

HOST_NAME = "0.0.0.0"
SERVER_PORT = 9999
RTL_POWER = 'rtl_power' if sys.platform != 'win32' else "C:/rtl_sdr/rtl_power.exe"
RTL_TCP = 'rtl_tcp' if sys.platform != 'win32' else "C:/rtl_sdr/rtl_tcp.exe"

modo='ascolto'
freq = 868.343E6
threshold = 0
ppm = 0
bw = 25e3
lock = threading.Lock()
proc = None
lastRecording = None
tLastRec = None
outFile = None
poll=None

# https://stackoverflow.com/questions/11875770/how-to-overcome-datetime-datetime-not-json-serializable
def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""

    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    raise TypeError("Type %s not serializable" % type(obj))


def rtl_power(newFile=True):
    global proc, outFile
    
    if newFile:
        try:
            os.mkdir("log")
        except FileExistsError:
            pass
        try:
            outFile = open("log/" + datetime.now().strftime("%Y-%m-%d_%H-%M-%S_") + str(int(freq)) + ".log", "a")
        except:
            logger.error("Impossibile creare file di log")

    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)
        proc.wait()
    proc = Popen([RTL_POWER,"-p",str(ppm),"-g","0","-f",f'{int(freq-bw/2)}:{int(freq+bw/2)}:25'],
                    encoding='utf8',bufsize=0,stdout=PIPE, stderr=PIPE)
    os.set_blocking(proc.stderr.fileno(), False)
    os.set_blocking(proc.stdout.fileno(), False)
    
    if newFile:
        bot.msg("Attivata modalità monitoraggio")
    #TODO: attesa partenza o errore
    

def rtl_tcp():
    global proc, freq, outFile
    
    if outFile:
        outFile.close()
        outFile = None
    
    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)
        proc.wait()
    proc = Popen([RTL_TCP,"-a","0.0.0.0"],encoding='utf8')
    bot.msg("Attivata modalità ascolto remoto")
    #TODO: attesa partenza o errore
    
def analisi(line):
    global threshold, lastRecording, tLastRec
    
    try:
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
    except Exception as x:
        exc_type, exc_obj, exc_tb = sys.exc_info()
        logger.error("Exception at line %d: %s",exc_tb.tb_lineno, str(x))
        return None
        
class MyServer(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.0"

    def do_GET(self):
        global modo, freq, threshold, bw, ppm, lastRecording, tLastRec
        try:
            print(self.path)
            uri = urlparse(self.path)
            params = parse_qs(uri.query)
            if self.path == "/favicon.ico":
                self.send_response(404)
                self.end_headers()
                return
            if uri.path == "/img":
                if outFile is None or outFile.tell()==0:
                    self.send_response(404)
                    self.end_headers()
                    return
                basename = os.path.basename(outFile.name).removesuffix('.log')+'.png'
                tmpFilename=tempfile.gettempdir()+'/'+basename
                heatmapProc=Popen(["./heatmap.py","--ytick","5m",outFile.name,tmpFilename]) 
                result=heatmapProc.wait()
                if result==0:
                    self.send_response(200)
                    self.send_header("Content-type", "image/png")
                    self.send_header("Access-Control-Allow-Origin", "*")
                    self.send_header('Content-Disposition', f'filename="{basename}"')
                    self.end_headers()
                    with open(tmpFilename,'rb') as f:
                        shutil.copyfileobj(f,self.wfile)
                    os.remove(tmpFilename)
                else:
                    self.send_response(500)
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
                        raise
                self.send_response(200)
                self.send_header("Content-type", "text/plain")
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                self.wfile.write(bytes(response, "utf-8"))
                return
            if uri.path == "/monitor":
                lastRecording = None
                tLastRec = None
                if "f" in params:
                   freq = float(params["f"][0])
                if "thr" in params:
                   threshold = float(params["thr"][0])
                if "ppm" in params:
                    ppm = float(params["ppm"][0])
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
            logger.error("Exception at line %d: %s",exc_tb.tb_lineno, str(x))


def threadFunc():
    webServer = ThreadingHTTPServer((HOST_NAME, SERVER_PORT), MyServer)
    while True:
        try:
            webServer.serve_forever()
        except KeyboardInterrupt:
            break
        except Exception as x:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            logger.error("Exception at line %d: %s",exc_tb.tb_lineno, str(x))
    webServer.server_close()

logger = logging.getLogger(__name__)
formatter = logging.Formatter(fmt="%(asctime)s: %(message)s", datefmt="%H:%M:%S")
if sys.platform!='win32':
    logger.addHandler(JournalHandler(SYSLOG_IDENTIFIER='ramore'))
handler = logging.StreamHandler(sys.stdout)
handler.setFormatter(formatter)
logger.setLevel(logging.DEBUG)
logger.addHandler(handler)

p=Popen(['killall','rtl_tcp'])
p.wait()
p=Popen(['killall','rtl_power'])
p.wait()

rtl_tcp()
modo = 'ascolto'

t = threading.Thread(target=threadFunc, daemon=True)
t.start()

logger.info('start')

try:
    while True:
        time.sleep(1)
        doRead=False
        with lock:
            if modo=='monitor' and proc is not None:
                doRead=True
        if doRead:
            while True:
                try:
                    l=proc.stderr.readline()
                except TypeError:
                    break
                if l is not None:
                    l=l.rstrip()
                    #TODO: gestire errore "usb_claim_interface error -6"
                    if l=='Error: dropped samples.' or l=='No supported devices found.':
                        time.sleep(1)
                        rtl_power(False)
                        break
                    else:
                        logger.debug('STDERR: [%s]',l)
            while True:
                try:
                    line = proc.stdout.readline().rstrip()
                except TypeError:
                    break
                except Exception:
                    print('eccezione in lettura rtl_power')
                if outFile is not None:
                    try:
                        outFile.write(line)
                        outFile.write("\n")
                        outFile.flush()
                    except:
                        pass
                res = analisi(line)
                if res is not None:
                    if res:
                        bot.msg('Rilevato segnale')
except KeyboardInterrupt as e:
    print('chiusura')
    if proc is not None:
        os.kill(proc.pid,signal.SIGTERM)
        proc.wait(timeout=3)
except Exception as x:
    exc_type, exc_obj, exc_tb = sys.exc_info()
    logger.error("Exception at line %d: %s",exc_tb.tb_lineno, str(x))
