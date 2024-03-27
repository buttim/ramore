import requests, cfg

def updates():
    url = f"https://api.telegram.org/bot{cfg.TOKEN}/getUpdates"
    response=requests.get(url).json()
    print(response)
    for x in response['result']:
        if 'message' in x:
            print("L'ID della chat è: ",x['message']['chat']['id'])
            
def msg(txt):
    # https://stackoverflow.com/questions/75116947/how-to-send-messages-to-telegram-using-python

    url = f"https://api.telegram.org/bot{cfg.TOKEN}/sendMessage?chat_id={cfg.CHAT_ID}&text={txt}"
    result = requests.get(url).json()
    return result['ok']
    
if __name__=='__main__':
    updates()