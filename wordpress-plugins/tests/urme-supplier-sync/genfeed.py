import random, sys, json
# usage: genfeed.py N OUT '{"drop":[...], "set":{"REF000000":{"STOCK":"0"}}, "cats":{"WATCH":"WATCH"}, "truncate":0}'
n=int(sys.argv[1]); out=sys.argv[2]; o=json.loads(sys.argv[3]) if len(sys.argv)>3 else {}
random.seed(7)
brands=['Seiko','Tissot','Guess','Michael Kors','BOSS','Casio']
cats=['WATCH']*3+['JEWELRY','ACCESSORIES','GLASSES']
drop=set(o.get('drop',[])); sets=o.get('set',{}); keep_watches=o.get('keep_watches')
buf=['<?xml version="1.0" encoding="UTF-8"?>\n<items>\n']
w=0
for i in range(n):
    ref=f"REF{i:06d}"
    c=o.get('catmap',{}).get(cats[i%6],cats[i%6])
    f={'CATEGORY':c,'MANUFACTURER':brands[i%6],'PRODUCT_NAME':f"{brands[i%6]} watch & co {i}",'PRODUCTNO':ref,'ITEM_ID':str(4900000000000+i),
       'PURCHASE_PRICE':f"{random.randint(20,900)}.{random.randint(0,99):02d}",'STOCK':random.choice(['1','2','5','12']),
       'IMG_URL':f"https://example.com/img/{i}.jpg",'SUBCATEGORY':'Men'}
    f.update(sets.get(ref,{}))
    if ref in drop: continue
    if c=='WATCH':
        w+=1
        if keep_watches is not None and w>keep_watches: continue
    buf.append('  <item>'+''.join(f'<{k}><![CDATA[{v}]]></{k}>' for k,v in f.items())+'</item>\n')
buf.append('</items>\n')
s=''.join(buf)
if o.get('truncate'): s=s[:int(len(s)*0.6)]
open(out,'w').write(s)
