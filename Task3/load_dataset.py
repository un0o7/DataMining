import numpy as np
import random
import math
import pickle
from nltk.tokenize import TweetTokenizer
from collections import Counter, OrderedDict,defaultdict
import os 
import io
import time
datasetpath=['dga-domain.txt','umbrella-top-1m.csv']
vowel=['a','e','i','o','u']
with open("data/high.pkl","rb") as f :
        high=pickle.load(f)
with open('data/low.pkl',"rb") as f:
        low = pickle.load(f)
#origin dataset
def load_origin_dataset(path=datasetpath):
    x=[]
    y=[]
   
    with open(path[0],'r') as f:
        while f.readline() != '\n':
            continue
        #print(f.readlines()[0].strip().split('\t')[1].strip())
        for i in f.readlines()[0:5000]:
            x.append(i.strip().split('\t')[1].strip().lower())
            y.append(1)
  
    with open(path[1],'r',) as f:
        for i in f.readlines()[0:5000]:
            x.append(i.strip().split(',')[1].strip().lower())
            y.append(0)
    index = [i for i in range(len(x))] # test_data为测试数据
    np.random.shuffle(index) 
    x=np.array(x)[index]
    y=np.array(y)[index]
    return x,y
                   
#features
#length

#shang
def cal_entropy(domain):
    dic=[i for i in domain]
    dic=list(set(dic))
    pro=[]
    entropy=0
    for i in dic: 
        pro=domain.count(i)*1.0/len(domain)
        entropy-=np.log2(pro)*pro
    return entropy
    

   
#连续辅音字母的组合个数和域名长度的比值
def consonant_pro(domain):
    count=0
    flag=0
    for i in domain:
        if i not in vowel and flag != 1 :
            count+=1
            flag=1
        if i in vowel:
            flag=0
    return count*1.0/len(domain)
#元音出现的概率 dga中元音出现的概率小且重复率低
def vowel_pro(domain):
    count=0
    for i in domain:
        if i in vowel :
            count+=1
    return count*1.0/len(domain)




#域名中数字出现的概率
def integer_pro(domain):
    count = 0
    for i in domain:
        if ord(i) > 48 and ord(i) < 58:
            count+=1
    return count*1.0/len(domain)
    
def create_text(path,N):
    high=list()
    low=list()
    with open(path,'r') as f:
        for i in f.readlines():
            i=i.strip().replace('<unk>','@@').replace(' ','').strip()
            split_words=[i[j:j+2] for j  in range(0,len(i),2)]
            l=['$'+i[0]]+split_words+['#'+i[-1]]
            high.extend([l[j:j+N] for j in range(len(l)-N+1)])#分子
            low.extend([l[j:j+N-1] for j in range(len(l)-N+2)])#分母
    with open(path.split("/")[0]+'/high.pkl','wb') as f:
        pickle.dump(high,f)
    with open(path.split("/")[0]+'/low.pkl','wb') as f:
        pickle.dump(low,f)
    print(high[0])
        
#n-gram特征
#select n which can separate benign and malware best through pyplot
def n_gram(path, domain, n):# if n=2:cal P(w1)P(w2|w1)P(w3|w2)P(w4|w3)…P(wn|wn-1)
  
    #high,low=create_text(path,n)
    split_domain=[i.strip() for i in domain.strip().split('.')]
    pro=1
    for i in split_domain:
        #i=i.strip().replace('<unk>','@@').replace(' ','').strip()
        split_words=[i[j:j+2] for j  in range(0,len(i),2)]
        i=['$'+i[0]]+split_words+[i[-1]+'#']
        
        for index,j in enumerate(i[2:]):#i[index+2]|i[index]i[index+1]
            pro=pro*(high.count([i[index],i[index+1],i[index+2]])+1)*1.0/(low.count([i[index],i[index+1]])+1)
    return pro

def load_features(x,y):
    features=[]
    for index,i in enumerate(x):
        print(i)
        num=i.count('.')
        i=i.split('.')
        if len(i) > 1:
            i=i[-2]
        features.append([len(i),consonant_pro(i),vowel_pro(i),cal_entropy(i),n_gram("data", i, 3),integer_pro(i),num])
    average=[]
    stds=[]

    for j in range(6):
        b=[item[j] for item in features]
        average.append(np.mean(b))
        stds.append(np.std(b))
    features=np.array(features)
    for i in range(6):
        features[:,i]=(features[:,i]-average[i])*1.0/stds[i]

    with open('features.pkl','wb') as f:
        pickle.dump(features,f)
    print('load features into features.pkl success')
    with open("labels.pkl",'wb') as f:
        pickle.dump(y,f)

if __name__=="__main__":
    x,y=load_origin_dataset()
    print("load origin data success")
    #print(y)
    #print(cal_entropy("sdwexsa"))
    #print("sw124eWEWE".lower())
    #domains,y=load_origin_dataset(datasetpath)
    #create_text('data/ptb.train.txt',3)
    x=list(x)
    y=list(y)
   
    #pro=n_gram('data',"sdswe.research.re",3)
    #print(pro)
    load_features(x,y)
    #n_gram("data", x, 3)
    #cal_entropy('ymx7su5tnxcbns4adz.com')
    #l=[len(x),consonant_pro(x),vowel_pro(x),cal_entropy(x),n_gram("data", x, 3),integer_pro(x)]
    #n_gram('sd',5)

   

    
