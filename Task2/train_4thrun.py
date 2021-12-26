import pickle
import numpy as np
import torch
from torch.utils import data # 获取迭代数据
import torch.utils.data as Data
from torch.autograd import Variable # 获取变量
import torchvision
import matplotlib.pyplot as plt
import numpy as np
import urllib


def load_dataset(datas_dir,labels_dir):
    with open(datas_dir,"rb") as f :
        datas=pickle.load(f)
    f.close()
    with open(labels_dir,"rb") as f :
        labels=pickle.load(f)
    f.close()
    return datas,labels

x,y=load_dataset("datas.pickle","labels.pickle")
x=np.array(x)[:,5:]
#n*750
print("x_shape=",x.shape)
xx=[]
for i in x:
    xx.append(np.array(i).reshape((1,25,30)))
xx=np.array(xx)
xx=torch.tensor(xx,dtype=torch.float32)

y=torch.tensor(y,dtype=torch.float32)

torch_dataset = Data.TensorDataset(xx,y)
train_dataset, test_dataset = torch.utils.data.random_split(torch_dataset, [round(len(x)*0.7), len(x)-round(len(x)*0.7)])
train_loader = Data.DataLoader(
    dataset=train_dataset,      # torch TensorDataset format
    batch_size=252,             # mini batch size
    shuffle=True               # 要不要打乱数据 (打乱比较好
)
test_loader = Data.DataLoader(
    dataset=test_dataset,      # torch TensorDataset format
    batch_size=252,             # mini batch size
    shuffle=True               # 要不要打乱数据 (打乱比较好             # 多线程来读数据
)
#input 100*1*3*4
#conv 100*2*2*3
#last output 100*2



class cnn_net(torch.nn.Module):
    def __init__(self,):
        super(cnn_net, self).__init__()
        self.conv1=torch.nn.Sequential(
            torch.nn.Conv2d(
                in_channels=1,
                out_channels=15,
                kernel_size=5,
                stride=1,
                padding=0
            ),
            torch.nn.BatchNorm2d(15),
            torch.nn.ReLU()
        )
        self.conv2 = torch.nn.Sequential(
            torch.nn.Conv2d(
                in_channels=15,
                out_channels=30,
                kernel_size=5,
                stride=1,
                padding=0
            ),
            torch.nn.BatchNorm2d(30),
            torch.nn.ReLU()
        )
        self.conv3 = torch.nn.Sequential(
            torch.nn.Conv2d(
                in_channels=30,
                out_channels=20,
                kernel_size=5,
                stride=1,
                padding=0
            ),
            torch.nn.BatchNorm2d(20),
            torch.nn.ReLU()
        )
        self.conv4 = torch.nn.Sequential(
            torch.nn.Conv2d(
                in_channels=20,
                out_channels=10,
                kernel_size=5,
                stride=1,
                padding=0
            ),
            torch.nn.BatchNorm2d(10),
            torch.nn.ReLU()
        )
        self.mlp1 = torch.nn.Linear(1260,200 )
        self.mlp2 = torch.nn.Linear(200, 100)
        self.mlp3 = torch.nn.Linear(100,10)
    def forward(self,x):
        x=self.conv1(x)
        x=self.conv2(x)
        x=self.conv3(x)
        x=self.conv4(x)
        x=self.mlp1(x.view(x.size()[0], -1))
        x=self.mlp2(x)
        x=self.mlp3(x)
        return x
model=cnn_net()
print(model)
loss_func = torch.nn.CrossEntropyLoss()
opt = torch.optim.Adam(model.parameters(),lr=0.001)#learing rate = 0.001

loss_count = []
for epoch in range(25):
    for i,(x,y) in enumerate(train_loader):
        batch_x = Variable(x) # torch.Size([128, 1, 28, 28])
        batch_y = Variable(y) # torch.Size([128])
        # 获取最后输出
        out = model(batch_x) # torch.Size([128,10])
        # 获取损失
        loss = loss_func(out,batch_y.long())
        # 使用优化器优化损失
        opt.zero_grad()  # 清空上一步残余更新参数值
        loss.backward() # 误差反向传播，计算参数更新值
        opt.step() # 将参数更新值施加到net的parmeters上
        if i%20 == 0:
            loss_count.append(loss.item())
            print('{}:{}:\t'.format(epoch,i), loss.item())
            torch.save(model,r'C:\Users\15247\PycharmProjects\Task2\model_cnn')
        if i % 100 == 0:
            for a,b in test_loader:
                test_x = Variable(a)
                test_y = Variable(b)
                out = model(test_x)
                #print("batch_y\t",batch_y)
                #print("out:\t",out)
                #print('test_out:\t',torch.max(out,1)[1])
                #print('test_y:\t',test_y)
                accuracy = torch.max(out,1)[1].numpy() == test_y.numpy()
                #torch.max(inputtensor,dim)
                #函数会返回两个tensor，第一个tensor是每行的最大值；第二个tensor是每行最大值的索引。
                print('accuracy:\t%f'%(np.mean(accuracy)))
                break
plt.figure('PyTorch_CNN_Loss')
plt.plot(loss_count,label='Loss')
plt.legend()
plt.show()

model = torch.load(r'C:\Users\15247\PycharmProjects\Task2\model_cnn')
def predict():
   while True:
       line = input("input the string:")
       if line == 'quit':
           break
       line = line.lower()
       features = [line.count("script"), line.count("java"), line.count("iframe"), line.count(">"), line.count("<"),
                   line.count("/"), line.count(r"\\"), line.count("%"), line.count("("), line.count(")"), len(line),
                   line.count("//")]
       features = np.array(features).reshape(1, 3, 4)
       out = model(torch.tensor([features], dtype=torch.float32))
       print("out:\t", out)
       result = torch.max(out, 1)[1].numpy()
       print(result)

accuracy_sum = []
for i,(test_x,test_y) in enumerate(test_loader):
    test_x = Variable(test_x)
    test_y = Variable(test_y)
    out = model(test_x)
    # print('test_out:\t',torch.max(out,1)[1])
    # print('test_y:\t',test_y)
    accuracy = torch.max(out,1)[1].numpy() == test_y.numpy()
    accuracy_sum.append(accuracy.mean())
    print('accuracy:\t',accuracy.mean())

print('总准确率：\t',sum(accuracy_sum)/len(accuracy_sum))
# 精确率图
print('总准确率：\t',sum(accuracy_sum)/len(accuracy_sum))
plt.figure('Accuracy')
plt.plot(accuracy_sum,'o',label='accuracy')
plt.title('Pytorch_CNN_Accuracy')
plt.legend()
plt.show()

predict()