import re
from sklearn import linear_model
from sklearn.ensemble import RandomForestClassifier 
import numpy as np                
from matplotlib import colors     
from matplotlib import font_manager
from sklearn import svm            
from sklearn.svm import SVC
from sklearn import model_selection
import matplotlib.pyplot as plt
import matplotlib as mpl
import pickle
from sklearn.ensemble import AdaBoostClassifier  
from sklearn.ensemble import GradientBoostingClassifier
from sklearn import linear_model
from sklearn.neural_network import BernoulliRBM  
from sklearn.pipeline import Pipeline 

from sklearn.metrics import accuracy_score
from sklearn.metrics import roc_auc_score
from sklearn.metrics import confusion_matrix
from sklearn.metrics import recall_score
with open("labels.pkl",'rb') as f :
    y=pickle.load(f)
with open("features.pkl",'rb') as f :
    x=pickle.load(f)
x_train,x_test,y_train,y_test=model_selection.train_test_split(x,              #所要划分的样本特征集
                                                               y,              #所要划分的样本结果
                                                               random_state=1, #随机数种子
                                                               test_size=0.3)  #测试样本
clf = svm.SVC(C=0.1,                         #误差项惩罚系数,默认值是1
                  kernel='linear',               #线性核 kenrel="rbf":高斯核
                  decision_function_shape='ovr') #决策函数
clf.fit(x_train,y_train)
clf.predict(x_test)
print(accuracy_score(clf.predict(x_test),y_test))

#如果用正则化，可以添加参数penalty，可以是l1正则化（可以更有效的抵抗共线性），也可以是l2正则化，如果是类别不均衡的数据集，可以添加class_weight参数，这个可以自己设置，也可以让模型自己计算
logistic = linear_model.LogisticRegression( penalty='l2', class_weight='balanced')
logistic.fit(x_train,y_train)
y_pred0 = logistic.predict( x_test)
#如果只想预测概率大小，可以用下面这个函数
#y_pred = logistic.predict_proba(x_test)


#采用袋外样本来评估模型的好坏，提高泛化能力
random_clf = RandomForestClassifier(n_estimators=100,oob_score=True) 
random_clf.fit(x_train,y_train)
y_pred1 = random_clf.predict(x_test)

 #迭代100次 ,学习率为0.1
ab_clf = AdaBoostClassifier(n_estimators=100,learning_rate=0.1)
ab_clf.fit(x_train,y_train)
y_pred2 = ab_clf.predict( x_test)


 #迭代100次 ,学习率为0.1
gdbt_clf = GradientBoostingClassifier(n_estimators=100, learning_rate=0.1)
gdbt_clf.fit(x_train,y_train)
y_pred3 = gdbt_clf.predict( x_test)


logistic = linear_model.LogisticRegression()  
rbm = BernoulliRBM(random_state=0, verbose=True)  
classifier = Pipeline(steps=[('rbm', rbm), ('logistic', logistic)])  
rbm.learning_rate = 0.1
rbm.n_iter = 20   
rbm.n_components = 100  
#正则化强度参数
logistic.C = 1000   
classifier.fit(x_train, y_train)  
y_pred4 = classifier.predict(x_test)

from xgboost import XGBClassifier
from matplotlib import pyplot
model = XGBClassifier()
model.fit(x_train,y_train)
y_pred5 = model.predict(x_test)
#查看预测准确率
accuracy_score(y_test, y_pred5)

#绘制特征的重要性
#from xgboost import plot_importance
#plot_importance(model)
#pyplot.show()


from sklearn import linear_model

clf = linear_model.Ridge(alpha=0.1)  # 设置正则化强度
clf.fit(x_train, y_train)  # 参数拟合
y_pred6=clf.predict(x_test)

y_pred6=np.round(y_pred6)
#计算准确率
#accuracy = accuracy_score(y_test, y_pred)
#计算auc，一般针对两类分类问题
#auc = roc_auc_score(y_test, y_pred)
#计算混淆矩阵，一般针对两类分类问题
#conMat = confusion_matrix(y_test, y_pred)
models=['logistic','randomforest','adaBoost','gdbt','nn','xgboost','ridge']
accuracy=[]
recall=[]
y_pred=[y_pred0,y_pred1,y_pred2,y_pred3,y_pred4,y_pred5,y_pred6]
for index, i in enumerate(y_pred):
    print(index)
    accuracy.append(accuracy_score(y_test,i))
    recall.append(recall_score(y_test,i, average='micro'))

bar_width = 0.3
print(accuracy)
print(recall)

plt.rcParams['font.sans-serif'] =[u'SimHei']
plt.rcParams['axes.unicode_minus'] = False

plt.bar(np.arange(len(models)), accuracy, label = '准确率', color = 'steelblue', alpha = 0.8, width = bar_width)
plt.bar(np.arange(len(models))+bar_width, recall, label = '召回率', color = 'indianred', alpha = 0.8, width = bar_width)

plt.xlabel("模型名称")
plt.ylabel("准确率和召回率分布")


plt.xticks([i+bar_width for i in np.arange(len(models))],models)


for index,accuracy_rate in enumerate(accuracy):
    plt.text(index, accuracy_rate, '%s' %accuracy_rate, ha='center')


for index,recall_rate in enumerate(recall):
    plt.text(index+bar_width, recall_rate, '%s' %recall_rate, ha='center')
# 显示图例
plt.legend()
# 显示图形
plt.show()

