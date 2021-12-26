from sklearn import model_selection
import random
import numpy as np
from matplotlib import colors
from sklearn import svm
from sklearn.svm import SVC
from sklearn.ensemble import RandomForestClassifier
from sklearn import model_selection
import matplotlib.pyplot as plt
import matplotlib as mpl
import urllib
import re
PATH_LIST =["dmzo_nomal.csv" ,"xssed.csv"]

def load_features(path_list):
    X=[]
    Y=[]
    flag=0
    for i in path_list:
        with open(i,'r') as f:
            for line in f.readlines():
                line=line.strip().lower()
                temp=line.count('%')
                line=urllib.parse.unquote(line).lower()
                print(line)
                if not line :
                    continue
                l=re.findall("[a-zA-Z0-9_/]+=[a-zA-Z0-9_/]+",line)
                pro=0
                for k in l:
                    pro+=len(k)
                Y.append(flag)
           
                X.append([temp,line.count("script"),line.count("java"),line.count("iframe"),line.count(">"),
                          line .count("<"),line.count("/"),line.count("("),line.count(")"),
                          len(line),line.count("//"),pro/len(line),line.count('.'),line.count("<")==line.count(">"),
                          line.count('"'),line[0]=='"' or line[0]=="'" ])
        flag=1
    index = [i for i in range(len(Y))]

    random.shuffle(index)
    X = np.array(X)[index]
    Y = np.array(Y)[index]

    x_train, x_test, y_train, y_test = model_selection.train_test_split(X,  # 所要划分的样本特征集
                                                                        Y,  # 所要划分的样本结果
                                                                        random_state=1,  # 随机数种子
                                                                        test_size=0.3)
    return x_train,x_test,y_train,y_test
def show_accuracy(a, b, tip):
    acc = a.ravel() == b.ravel()
    print('%s Accuracy:%f' %(tip, np.mean(acc)))

if __name__=="__main__":
    # total 64833 , xssed 33426 , not xssed 31407
    x_train,x_test,y_train,y_test=load_features(PATH_LIST)
    #f=open("dmzo_nomal.csv")
    clf = RandomForestClassifier(n_estimators=100,oob_score=True)
   # clf = svm.SVC(C=0.5,  # 误差项惩罚系数,默认值是1
    #              kernel='linear',  # 线性核 kenrel="rbf":高斯核
     #             decision_function_shape='ovr')  # 决策函数
    clf.fit(x_train,  # 训练集特征向量
            y_train.ravel())  # 训练集目标值
    print(clf.feature_importances_)
    show_accuracy(clf.predict(x_train), y_train, 'traing data')
    show_accuracy(clf.predict(x_test), y_test, 'testing data')
    #0.979882


