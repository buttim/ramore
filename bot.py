import requests, cfg

def updates():
    url = f"https://api.telegram.org/bot{cfg.TOKEN}/getUpdates"
    print(requests.get(url).json())

def msg(txt):
    # https://stackoverflow.com/questions/75116947/how-to-send-messages-to-telegram-using-python

    url = f"https://api.telegram.org/bot{cfg.TOKEN}/sendMessage?chat_id={cfg.CHAT_ID}&text={txt}"
    result = requests.get(url).json()
    return result['ok']
    
updates()