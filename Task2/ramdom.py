import torch
from torch.utils import data # 获取迭代数据
import torch.utils.data as Data
from torch.autograd import Variable # 获取变量
import torchvision
import matplotlib.pyplot as plt
import numpy as np
import urllib
from sklearn.model_selection import train_test_split
PATH_LIST =["dmzo_nomal.csv" ,"xssed.csv"]

def load_features(path_list):
    X=[]
    Y=[]
    flag=0
    for i in path_list:
        with open(i,'r') as f:
            for line in f.readlines():
                line=line.lower()
                line=urllib.parse.unquote(line).lower()
                if flag == 0:
                    Y.append(0)
                else:
                    Y.append(1)# stand for xssed
                X.append([line.count('.'),line.count("script"),
                          line .count(">"),line .count("<"),line.count("/"),
                          line.count("("),line.count(")"),len(line),line.count("//"),line.count("=")])
        flag=1
    return X,Y

x,y=load_features(PATH_LIST)
x_train,x_test,y_train,y_test=train_test_split(x,              #所要划分的样本特征集
                                                               y,              #所要划分的样本结果
                                                               random_state=1, #随机数种子
                                                               shuffle=True,
                                                               test_size=0.3)  #测试样本

from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics  import accuracy_score
clf = RandomForestClassifier(n_estimators=500,oob_score=True)
clf.fit(x_train,y_train)
print("hello")
print(clf.predict(x_test))

print(clf.feature_importances_)
print(accuracy_score(clf.predict(x_test),y_test))