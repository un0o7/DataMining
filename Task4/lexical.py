#extract lexical features
import re
from pathlib import Path
import sys
from matplotlib import pyplot as plt
from collections import Counter
import numpy as np
import codecs

from collections import defaultdict
import math
import operator
from sklearn.metrics.pairwise import kernel_metrics
import pickle
contents = ['php-benign','php-webshell']

def feature_select(list_words):
    #总词频统计
    print("sdiwuei")
    doc_frequency=defaultdict(int)
    for word_list in list_words:
        for i in word_list:
            doc_frequency[i]+=1
    print("sdiwuei")
    #计算每个词的TF值
    word_tf={}  #存储没个词的tf值
    for i in doc_frequency:
        print(i)
        word_tf[i]=doc_frequency[i]/sum(doc_frequency.values())
    print("sdiwuei")
    #计算每个词的IDF值
    doc_num=len(list_words)
    word_idf={} #存储每个词的idf值
    word_doc=defaultdict(int) #存储包含该词的文档数
    for i in doc_frequency:
        for j in list_words:
            if i in j:
                word_doc[i]+=1
    print("sdiwuei")
    for i in doc_frequency:
        word_idf[i]=math.log(doc_num/(word_doc[i]+1))
    print("sdiwuei")
    #计算每个词的TF*IDF的值
    word_tf_idf={}
    for i in doc_frequency:
        word_tf_idf[i]=word_tf[i]*word_idf[i]
    print("wesdskdsl")
    # 对字典按值由大到小排序
    dict_feature_select=sorted(word_tf_idf.items(),key=operator.itemgetter(1),reverse=True)
    return dict_feature_select

def numberofstrings(str):
    result=[]
    l=list(set(re.findall('\"[1-9a-zA-Z-?<>_*@#= \.\n]+\"',str,flags=0)+re.findall("\'[1-9a-zA-Z-?<>_*@#$ \.]+\'",str,flags=0)))
    max = 0
    for index,i in enumerate(l):
        if len(i)>3:
            result.append(i[1:-1])
            if len(i)>max:
                max=len(i)
    return len(result),max
def use_dangerousfun(str):
    total=0
    sensitives=['eval','system','assert','shell','connect','preg_replace','passthru','popen','proc_open','pcntl_exec','call_user_func',\
        'call_user_func_array','create_function','include','readfile','fopen','fwrite','exec','readfile','include_once','show_source',\
            'copy']
    for i in sensitives:
        total+=str.count(i)
    return total
def length(str):
    return len(str)

def max_linelength(str_list):
    max=0
    for i in str_list:
        if len(i) > max:
            max =len(i)
    return max

#print(max_linelength(str_list))

'''
def use_inputvar(str):
    input=['$_SERVER','$_GET','$_POST','$_COOKIE','$_REQUEST','$_FILES','$_ENV','$_HTTP_COOKIE_VARS',\
        '$_HTTP_ENV_VARS','$_HTTP_GET_VARS','$_HTTP_POST_FILES','$_HTTP_POST_VARS','$_HTTP_SERVER_VARS']
    total = 0 
    for i in input:
        total+=str.count(i)
    return total
'''
def use_network(str):
    networks = ['send','packet','socket','proxy','header','connect','recv','receive','get','post','host']
    total = 0 
    for i in networks:
        total+=str.count(i)
    return total

def linelength_entropy(str_list):
    entropy=0
    l = []
    for i in str_list:
        if len(i)<5:
            continue
        else:
            l.append(len(i.replace(' ','').strip()))
    length = len(l)
    for i in dict(Counter(l)).values():
        p=i/(length)
        entropy -=p*np.log2(p)
    return entropy

def number_mark(str):
    return str.count('<')

def information_entropy(str):
    #preprocess
    entropy=0
    char_list=[i for i in str if ord(i)>32 and ord(i)<127 ]
    length = len(char_list)
    for i in dict(Counter(char_list)).values():
        p=i/(length)
        entropy -=p*np.log2(p)
    return entropy

def index_coincidence(str):
    char_list=[i for i in str if ord(i)>32 and ord(i)<127 ]
    n = len(char_list)
    total=0
    for i in dict(Counter(char_list)).values():
        total+=i*(i-1)
    return total/(n*(n-1))

def is_encoded(str):
    keywords = ['base64','encode','decode']
    total =0
    for i in keywords:
        total+=str.count(i)
    return total 

def encoded_stringlength(str):
    str=str.replace(' ','').replace('\n','').replace("'.'",'').lower()
    l=re.findall("\'[0-9a-zA-Z-?<>_*@%#=/ +:;]+\'",str,flags=0)+re.findall("\"[0-9a-zA-Z-?<>%_*@#=/ +;:]+\"",str,flags=0)
    if len(l)==0:
        return 0
    max =0
    str = ''
    for i in l:
        i.replace('\'','')
        i.replace('\"','')
        if len(i) > max:
            max=len(i)
            str=i
    return max
def find_pro(word,word_dict):
    for i in word_dict:
        if i[0] == word :
            return i[1]
    return 0
def extract_idf():
    result=[] #contain all results here 
    benign_opcode=[]
    webshell_opcode=[]
    for i in ['benign-opcode','webshell-opcode']:
        for j in Path(i).iterdir():
            with open(j,'r') as f :
                l=[]
                for opcode in f.readlines():
                    if len(opcode) < 3 or '_' not in opcode or opcode.count('A')> 3 or opcode.count('_') > 4:
                        continue
                    l.append(opcode.strip())
                if i=='php-benign':
                    benign_opcode.append(l)
                else:
                    webshell_opcode.append(l)
    print(len(benign_opcode))
    print(len(webshell_opcode))
    word_dict=feature_select(benign_opcode+webshell_opcode)
    with open('opcode_dict.pkl','wb') as f:
        pickle.dump(word_dict,f)
    print("work dict generate finished!")
    print(word_dict)
    for i in benign_opcode:
        temp=1
        for j in list(set(i)):
            temp*=find_pro(j,word_dict)
        result.append(temp)
    for i in webshell_opcode:
        temp=0
        for j in list(set(i)):
            temp+=find_pro(j,word_dict)
        result.append(temp)
    with open("word_dict.pkl",'wb') as f:
        pickle.dump(result,f)
        print("write success")
    return result
print("sjdksjd")
#bianli extract features from original data
str =''
str_list = list()
x=list()
x1=list()
x2=list()
y=list()
print("sjdksjd")
result = extract_idf()
print(len(result))
flag=0
for mark,content in enumerate(contents):#mark=0 standfor benign while 1 standfor webshell
    for path in Path(content).iterdir():
        print(path)
        with codecs.open(path,'r','utf-8',errors='ignore') as f:#there exist some chinese
            str=f.read().lower()
            f.seek(0)
            str_list=f.readlines()
            #add features here
            #t=numberofstrings(str)
            #8 features accuracy: 0.9478
            x.append([use_dangerousfun(str),length(str),use_network(str),max_linelength(str_list),linelength_entropy(str_list),\
                number_mark(str),information_entropy(str),index_coincidence(str),is_encoded(str),encoded_stringlength(str),result[flag]])
            if mark:
                x2.append(result[flag])
            else:
                x1.append(result[flag])
            flag += 1
            y.append(mark)
ax1=plt.subplot(1,2,1)
ax1.scatter(range(len(x1)),x1,color='r',marker='o',alpha= 0.5)
ax1.set_title('benign')

ax2=plt.subplot(1,2,2)
ax2.scatter(range(len(x2)),x2,color='y',marker='^',alpha= 0.5)
ax2.set_title('webshell')

plt.suptitle('encoded sting max length')
plt.show()

from sklearn.ensemble import RandomForestClassifier 
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
from sklearn.metrics import recall_score
clf = RandomForestClassifier(n_estimators=100,oob_score=True)
index = [i for i in range(len(x))] # test_data为测试数据
np.random.shuffle(index) # 打乱索引
x=np.array(x)
y=np.array(y)
x = x[index]
y = y[index]
for i in range(len(x[0])):
    x[:,i]=(x[:,i]-np.mean(x[:,i]))/(np.std(x[:,i]))
x_train, x_test, y_train, y_test = train_test_split(x,  # 所要划分的样本特征集
                                                    y,  # 所要划分的样本结果
                                                    random_state=1,  # 随机数种子
                                                    test_size=0.3)
clf.fit(x_train,y_train)
print(clf.feature_importances_)
print("accuracy score:",accuracy_score(clf.predict(x_test),y_test))
print("recall   score:",recall_score(clf.predict(x_test),y_test))
