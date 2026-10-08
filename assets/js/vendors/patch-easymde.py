# -*- coding: utf-8 -*-
"""
EasyMDE vendor 补丁 - 幂等，可重复运行。

覆盖 easymde.min.js 后跑一次即可：  python patch-easymde.py

补丁点详见同目录 easymde.PATCH.md：
  P1 图标映射表 te   fa fa-*  ->  mdi mdi-*
  P2 图标 token 正则 /^fa(...)/  ->  /^(fa|mdi)(...)/   （缺了它图标会全部消失）

任一项找不到目标串会报 MISS 并中止，不会写出半改状态。
"""
import io
import sys

P = 'easymde.min.js'

MDI_TE = (
    'var te={bold:"mdi mdi-format-bold",italic:"mdi mdi-format-italic",'
    'strikethrough:"mdi mdi-format-strikethrough",heading:"mdi mdi-format-header-pound",'
    '"heading-smaller":"mdi mdi-format-header-decrease","heading-bigger":"mdi mdi-format-header-increase",'
    '"heading-1":"mdi mdi-format-header-1","heading-2":"mdi mdi-format-header-2",'
    '"heading-3":"mdi mdi-format-header-3",code:"mdi mdi-code-tags",quote:"mdi mdi-format-quote-open",'
    '"ordered-list":"mdi mdi-format-list-numbers","unordered-list":"mdi mdi-format-list-bulleted",'
    '"check-list":"mdi mdi-checkbox-marked-outline","clean-block":"mdi mdi-eraser",link:"mdi mdi-link",'
    'image:"mdi mdi-image","upload-image":"mdi mdi-upload",table:"mdi mdi-table",'
    '"horizontal-rule":"mdi mdi-minus",preview:"mdi mdi-eye","side-by-side":"mdi mdi-page-layout-sidebar-right",'
    'fullscreen:"mdi mdi-fullscreen",guide:"mdi mdi-help-circle",undo:"mdi mdi-undo",redo:"mdi mdi-redo"}'
)

RE_OLD = r'/^fa([srlb]|(-[\w-]*)|$)/'
RE_NEW = r'/^(fa|mdi)([srlb]|(-[\w-]*)|$)/'

s = io.open(P, encoding='utf-8').read()
orig = s
miss = []

# ---------- P1: 图标映射表 ----------
if 'var te={bold:"mdi ' in s:
    print('P1  图标映射表      : SKIP（已是 MDI）')
else:
    start = s.find('var te={')
    end = -1
    if start >= 0:
        depth = 0
        j = s.find('{', start)
        while j < len(s):
            if s[j] == '{':
                depth += 1
            elif s[j] == '}':
                depth -= 1
                if depth == 0:
                    end = j
                    break
            j += 1
    if start < 0 or end < 0:
        miss.append('P1 找不到 var te={...} 映射表')
    else:
        s = s[:start] + MDI_TE + s[end + 1:]
        print('P1  图标映射表      : OK（fa -> mdi）')

# ---------- P2: 图标 token 正则 ----------
if RE_NEW in s:
    print('P2  图标 token 正则 : SKIP（已支持 mdi）')
elif RE_OLD in s:
    s = s.replace(RE_OLD, RE_NEW)
    print('P2  图标 token 正则 : OK（fa -> fa|mdi）')
else:
    miss.append('P2 找不到图标 token 正则 /^fa([srlb]|(-[\\w-]*)|$)/')

if miss:
    print('')
    for m in miss:
        print('  MISS - ' + m)
    print('')
    print('未做任何写入。请人工比对新版 easymde 后更新本脚本，详见 easymde.PATCH.md。')
    sys.exit(1)

if s != orig:
    io.open(P, 'w', encoding='utf-8', newline='').write(s)
    print('')
    print('已写入 %s（%d -> %d 字节）' % (P, len(orig), len(s)))
else:
    print('')
    print('无变化，已是打过补丁的状态。')

# ---------- 自检 ----------
print('')
print('自检:')
print('  "fa fa-" 残留        :', s.count('"fa fa-'))
print('  "mdi mdi-" 映射条目  :', s.count('mdi mdi-'))
print('  mdi 正则             :', 'OK' if RE_NEW in s else 'MISS')
