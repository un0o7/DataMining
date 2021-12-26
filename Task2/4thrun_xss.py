#!/usr/bin/env python
# coding: utf-8

# # XSS\-Detection
#
# Steps:
# 1. word2vec
# 2. some data processing
# 3. MLP
# 4. More can be done (LSTM or something)

# In[1]:




# ## 1\. word2vec
#
# Segmentation has to be done before creating word vectors\.
#
# Principles are given below:
# - `"xss"` or `'xss'`
# - URLs including `HTTP` or `HTTPS`
# - labels (like `<script>`)
# - beginning (like `<h1`)
# - params (like `topic=`)
# - functions (like `alert(`)
# - strings consisting of digits and characters (need to be normalized)

# In[2]:


import csv, pickle, time, os
from collections import Counter
import matplotlib.pyplot as plt
from sklearn.manifold import TSNE
from gensim.models.word2vec import Word2Vec
import nltk
import re
from urllib.parse import unquote


# In[3]:


# utils
def GeneSeg(payload):
    # normalization
    # digits -> '0'
    payload = payload.lower()
    payload = unquote(unquote(payload))
    payload, num = re.subn(r'\d+', "0", payload)
    # URLs -> 'http://u'
    payload, num = re.subn(r'(http|https)://[a-zA-Z0-9\.@&/#!#\?]+', "http://u", payload)
    # segmentation
    r = '''
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
    return nltk.regexp_tokenize(payload, r)


# In[4]:


learning_rate = 0.1
vocabulary_size = 3000
batch_size = 128
embedding_size = 128
num_skips = 4
skip_window = 5
num_sampled = 64
num_iter = 5
plot_only = 100

log_dir = "word2vec.log"
plt_dir = "word2vec.png"
vec_dir = "word2vec.pickle"
data_dir = ""

# In[6]:


start = time.time()
words = list()
datas = list()

with open( "xssed.csv", "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, fieldnames=["payload"])
    for row in reader:
        payload = row["payload"]
        word = GeneSeg(payload)
        datas.append(word)
        words += word
f.close()


# build dataset
def build_dataset(datas, words):
    count = [["UNK", -1]]
    counter = Counter(words)
    count.extend(counter.most_common(vocabulary_size - 1))
    vocabulary = [c[0] for c in count]
    data_set = []
    for data in datas:
        d_set = []
        for word in data:
            if word in vocabulary:
                d_set.append(word)
            else:
                d_set.append("UNK")
                count[0][1] += 1
        data_set.append(d_set)
    return data_set


data_set = build_dataset(datas, words)

# do pay attention to params
model = Word2Vec(data_set, vector_size=embedding_size, window=skip_window, negative=num_sampled, epochs=num_iter)
embeddings = model.wv
print(len(embeddings['script']))



def plot_with_labels(low_dim_embs, labels, filename=plt_dir):
    plt.figure(figsize=(10, 10))
    for i, label in enumerate(labels):
        x, y = low_dim_embs[i, :]
        plt.scatter(x, y)
        plt.annotate(label, xy=(x, y), xytext=(5, 2),
                     textcoords="offset points",
                     ha="right",
                     va="bottom")
        f_text = "vocabulary_size=%d;batch_size=%d;embedding_size=%d;skip_window=%d;num_iter=%d" % (
            vocabulary_size, batch_size, embedding_size, skip_window, num_iter
        )
        plt.figtext(0.03, 0.03, f_text, color="green", fontsize=10)
    plt.show()
    plt.savefig(filename)


# a warning unavoidable
tsne = TSNE(perplexity=30, n_components=2, init="pca", n_iter=5000, learning_rate=200.0)
# `index2word` has been replaced by `index_to_key`
plot_words = embeddings.index_to_key[:plot_only]
plot_embeddings = []

for word in plot_words:
    plot_embeddings.append(embeddings[word])
low_dim_embs = tsne.fit_transform(plot_embeddings)
plot_with_labels(low_dim_embs, plot_words)


def save(embeddings):
    dictionary = dict([(embeddings.index_to_key[i], i) for i in range(len(embeddings.index_to_key))])
    reverse_dictionary = dict(zip(dictionary.values(), dictionary.keys()))
    word2vec = {"dictionary": dictionary, "embeddings": embeddings, "reverse_dictionary": reverse_dictionary}
    with open(vec_dir, "wb") as f:
        pickle.dump(word2vec, f)
    f.close()


save(embeddings)
end = time.time()
print("Job completed in: {}\nSaving word vectors to: {}".format(end - start, vec_dir))

# ## 2\. Data Processing (preparations for MLP)

# In[8]:


import csv, pickle, random, json
import numpy as np
from keras.preprocessing.sequence import pad_sequences
import tensorflow as tf
from tensorflow.keras.utils import to_categorical
from sklearn.model_selection import train_test_split

# In[9]:


vec_dir = "word2vec.pickle"
pre_datas_train = "pre_data_train.csv"
pre_datas_test = "pre_data_test.csv"
process_datas_dir = "process_data.pickle"


# In[13]:


def build_dataset(batch_size):
    with open(vec_dir, "rb") as f:
        word2vec = pickle.load(f)
    f.close()
    embeddings = word2vec["embeddings"]
    reverse_dictionary = word2vec["reverse_dictionary"]
    train_size = word2vec["train_size"]
    test_size = word2vec["test_size"]
    dims_num = word2vec["dims_num"]
    input_num = word2vec["input_num"]
    train_generator = batch_generator(pre_datas_train, train_size, batch_size, embeddings, reverse_dictionary)
    test_generator = batch_generator(pre_datas_test, test_size, batch_size, embeddings, reverse_dictionary, train=False)
    return train_generator, test_generator, train_size, test_size, input_num, dims_num


# In[14]:


def pre_process():
    with open(vec_dir, "rb") as f:
        word2vec = pickle.load(f)
        dictionary = word2vec["dictionary"]
        reverse_dictionary = word2vec["reverse_dictionary"]
        embeddings = word2vec["embeddings"]
    f.close()

    xssed_data = []
    normal_data = []

    with open(os.path.join(data_dir, "xssed.csv"), "r", encoding="utf-8") as f:
        reader = csv.DictReader(f, fieldnames=["payload"])
        for row in reader:
            payload = row["payload"]
            word = GeneSeg(payload)
            xssed_data.append(word)
    f.close()
    with open(os.path.join(data_dir, "dmzo_nomal.csv"), "r", encoding="utf-8") as f:
        reader = csv.reader(f)
        reader = csv.DictReader(f, fieldnames=["payload"])
        for row in reader:
            payload = row["payload"]
            word = GeneSeg(payload)
            normal_data.append(word)
    f.close()

    # label (1 or 0)
    xssed_num = len(xssed_data)
    normal_num = len(normal_data)
    xssed_labels = [1] * xssed_num
    normal_labels = [0] * normal_num
    datas = xssed_data + normal_data
    labels = xssed_labels + normal_labels
    #labels = to_categorical(labels)
    #print("labels:",labels)

    def to_index(data):
        d_index = list()
        for word in data:
            if word in dictionary.keys():
                d_index.append(dictionary[word])
            else:
                d_index.append(dictionary["UNK"])
        return d_index

    datas_index = [to_index(data) for data in datas]
    datas_index = pad_sequences(datas_index, value=-1)
    rand = random.sample(range(len(datas_index)), len(datas_index))
    datas = [datas_index[index] for index in rand]
    labels = [labels[index] for index in rand]
    return datas,labels


def data_generator(data_dir):
    reader = tf.TextLineReader()
    queue = tf.train.string_input_producer([data_dir])
    _, value = reader.read(queue)
    coord = tf.train.Coordinator()
    sess = tf.Session()
    threads = tf.train.start_queue_runners(sess=sess, coord=coord)
    while True:
        v = sess.run(value)
        [data, label] = v.split(b"|")
        data = np.array(json.loads(data.decode("utf-8")))
        label = np.array(json.loads(label.decode("utf-8")))
        yield (data, label)
    coord.request_stop()
    coord.join(threads)
    sess.close()


def batch_generator(datas_dir, datas_size, batch_size, embeddings, reverse_dictionary, train=True):
    batch_data = []
    batch_label = []
    generator = data_generator(datas_dir)
    n = 0
    while True:
        for i in range(batch_size):
            data, label = next(generator)
            data_embed = []
            for d in data:
                if d != -1:
                    data_embed.append(embeddings[reverse_dictionary[d]])
                else:
                    data_embed.append([0.0] * len(embeddings["UNK"]))
            batch_data.append(data_embed)
            batch_label.append(label)
            n += 1
            if not train and n == datas_size:
                break
        if not train and n == datas_size:
            yield (np.array(batch_data), np.array(batch_label))
            break
        else:
            yield (np.array(batch_data), np.array(batch_label))
            batch_data = []
            batch_label = []


datas,labels=pre_process()
print("datas:",datas,len(datas))
print("labels",labels,len(labels))

with open("datas.pickle","wb") as  f:
    pickle.dump(datas,f)
f.close()
with open("labels.pickle","wb") as f:
    pickle.dump(labels,f)
f.close()







