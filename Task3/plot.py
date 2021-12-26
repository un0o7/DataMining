import matplotlib.pyplot as plt
import pickle
import numpy as np
with open('features.pkl','rb') as f:
        features=pickle.load(f)
    
with open("labels.pkl",'rb') as f:
        labels=pickle.load(f)
indexs=[]
benign_indexs=[]
for index,i in enumerate(labels):
    if i ==1 :
        indexs.append(index)
    else:
        benign_indexs.append(index)
n_grams=np.array(features)[:][indexs]
benign_n_grams=np.array(features)[:][benign_indexs]
print(n_grams)
plt.figure()
plt.title("标准化n-gram分布")
plt.scatter(range(len(n_grams)),np.around(n_grams*1000),marker='o',c='r')
plt.scatter(range(len(benign_n_grams)),np.around(benign_n_grams*1000),marker='^',c='y')
plt.ylabel("3_gram")
plt.legend()
plt.show()
