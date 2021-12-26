from os import path
from  pathlib import Path

from time import sleep
import re
print(len(list(Path('php-benign').iterdir())))
print(len(list(Path('benign-opcode').iterdir())))
a=list(Path('php-webshell').iterdir())
b=list(Path('webshell-opcode').iterdir())
for i in range(len(a)):
    a[i]=str(a[i]).split('\\')[-1].replace('.php','').replace('.PhP','').strip()
for i in range(len(b)):
    b[i]=str(b[i]).split('\\')[-1].replace('.txt','').strip()

print(len(a))
print(len(b))


