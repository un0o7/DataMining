import csv
import html
import time
import urllib
import nltk
import  re
from gensim.models.word2vec import  Word2Vec
from sklearn.manifold import TSNE
import pickle
from collections import Counter



def segment(line):
    line=line.lower()
    line=urllib.parse.unquote(line)
    line,num=re.subn(r'\d+',"0",line)
    line,num=re.subn(r'(http|https)://[a-zA-Z0-9\,@/#!#\?]+',"http://u",line)
    r='''
        (?x)[\w\.]+?\(
        |\)
        |"\w+?"
        |'\w+?'
        |http://\w
        |</\w+>
        |<\w+>
        |<\w+
        |\w+=
        |>
        |[\w\.]+
    '''
    return nltk.regexp_tokenize(line,r)
with open("xssed.csv","r") as f :

        a=segment("method=show&lojaPrincipal=&areaName=busca&nomeLoja=<br/>&tipoLoja=&tipoBusca=comFoto&fetch=30&loja=&palavra=%22%3E%27%3E%3CSCRIPT%3Ealert%28String.fromCharC<br/>ode%2884%2C69%2C83%2C84%2C69%29%29%3C%2FSCRIPT%3E&x=80&y=20")
        print(a)


learning_rate = 0.1
vocabulary_size = 3000
batch_size = 128
num_skips = 5
skip_window = 5
num_sampled = 64
num_iter = 5
plot_only = 100

log_dir='./word2vec/word2vec.log'
plt_dir='./word2vec/word2vec.png'
vec_dir='./word2vec/word2vec.pickle'
data_dir='./data'

start = time.time()
words = list()
datas = list()

with open('xssed.csv','r',encoding='utf-8' ) as f:
    reader=csv.DictReader(f,fieldnames=['payload'])
    print(reader.fieldnames)
    for row in reader :
        payload = row['payload']
        word = segment(payload)
        datas.append(word)
        words+=word
    f.close()



