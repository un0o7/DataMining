
import subprocess
import sys
import os
import re
import pathlib

def opcode(str):
    t=re.findall('[A-Z_]+',err,flags=0)
    l=[]
    for i in t:
        if len(i)>5:
            l.append(i)
    return l
#p=subprocess.Popen('php -dvld.active=1 php-webshell/449e640fffac9c6fe6b6b945117b15729b04ca8e.php', 

contents = ['php-webshell']
for i in contents:
    for j in pathlib.Path(i).iterdir():
        filename=str(j).replace('\\','/')
        print(filename)
        if filename == 'php-webshell/449e640fffac9c6fe6b6b945117b15729b04ca8e.php':
            continue
        p=subprocess.Popen('php -dvld.active=1 ' +filename, 
                         stdout = subprocess.PIPE,
                         stderr = subprocess.PIPE)
        try:
            out,err = p.communicate(timeout=30)#bytes
        except Exception:
            out=''
            err=''
        out=str(out)
        err=str(err)
        s=''
        if out.find('RETURN') !=-1:
            s = out
        if err.find('RETURN') !=-1:
            s = err
        l=opcode(s)
        print(l)
        with open(i.replace('php-','')+'-opcode/'+filename.split('/')[-1].replace('php','')+'txt','w') as f:
            for k in l:
                f.write(k)
                f.write('\n')
        