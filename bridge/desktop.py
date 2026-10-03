"""One-screen Windows setup. No local web portal or database password."""
import json
import os
import queue
import sqlite3
import sys
import threading
import tkinter as tk
from pathlib import Path
from tkinter import ttk, messagebox
from core import Api, Bridge, Store, validate_config

HOME_DIR=Path(os.environ.get('LOCALAPPDATA',str(Path.home())))/'DigitalLabBridge'
HOME_DIR.mkdir(parents=True,exist_ok=True)
SETTINGS=HOME_DIR/'settings.json'
DEFAULTS={'api_url':'https://lightpink-badger-650079.hostingersite.com/api',
          'token':'','listen_ip':'0.0.0.0','analyzer_ip':'192.168.1.10','port':5001,'sample_field':2,'auto_start':True}


class Window:
    def __init__(self):
        self.np21 = DEFAULTS.get('adapter') == 'np21h'
        self.root=tk.Tk();self.root.title('Digital Lab — '+('NP-21H' if self.np21 else 'DxH 500')+' Bridge');self.root.geometry('820x760')
        self.root.minsize(720,620)
        self.events=queue.Queue();self.bridge=None;self.busy=False
        self.store=Store(HOME_DIR/'bridge.sqlite3')
        self.config=dict(DEFAULTS)
        if SETTINGS.exists():
            try:self.config.update(json.loads(SETTINGS.read_text(encoding='utf-8')))
            except (ValueError,OSError):pass
        self.vars={};self.inputs=[]
        main=ttk.Frame(self.root,padding=24);main.pack(fill='both',expand=True)
        ttk.Label(main,text=('Digital Lab — NP-21H CBC Bridge' if self.np21 else 'ربط جهاز DxH 500 بالمختبر'),font=('Segoe UI',20,'bold'),anchor='e').pack(fill='x')
        ttk.Label(main,text='اضبط الاتصال مرة واحدة، بعدها الاستقبال والإرسال تلقائياً',anchor='e').pack(fill='x',pady=(5,18))
        form=ttk.Frame(main);form.pack(fill='x')
        fields=[('api_url','عنوان API السيرفر'),('token','مفتاح الجهاز API Token'),
                ('listen_ip','IP الحاسبة — 0.0.0.0 للاستماع على الشبكة'),('analyzer_ip','IP جهاز التحليل'),
                ('port','منفذ الاستقبال — مطابق لـ Host Port'),('sample_field','حقل باركود العينة O-2 أو O-3')]
        if self.np21:
            fields = [f for f in fields if f[0]!='sample_field']
            ttk.Label(main,text='Sample ID: OBR-3 | CBC + 3-part differential | 21 parameters').pack(fill='x')
        for i,(key,label) in enumerate(fields):
            var=tk.StringVar(value=str(self.config.get(key,'')));self.vars[key]=var
            ttk.Label(form,text=label,anchor='e').grid(row=i,column=1,sticky='e',padx=(12,0),pady=7)
            if key=='sample_field':entry=ttk.Combobox(form,textvariable=var,values=['2','3'],state='readonly',width=8)
            else:entry=ttk.Entry(form,textvariable=var,show='*' if key=='token' else '')
            entry.grid(row=i,column=0,sticky='ew',pady=7);self.inputs.append((entry,key))
        form.columnconfigure(0,weight=1)
        self.auto=tk.BooleanVar(value=bool(self.config.get('auto_start',True)))
        ttk.Checkbutton(main,text='تشغيل الاستقبال تلقائياً عند فتح البرنامج',variable=self.auto).pack(anchor='e',pady=8)
        actions=ttk.Frame(main);actions.pack(fill='x',pady=10)
        self.save_button=ttk.Button(actions,text='فحص السيرفر وحفظ الإعدادات',command=self.save);self.save_button.pack(side='right',padx=4)
        self.start_button=ttk.Button(actions,text='تشغيل',command=self.start);self.start_button.pack(side='right',padx=4)
        self.stop_button=ttk.Button(actions,text='إيقاف',command=self.stop);self.stop_button.pack(side='right',padx=4)
        ttk.Button(actions,text='إعادة محاولة الإرسال',command=self.retry).pack(side='right',padx=4)
        self.status=tk.StringVar(value='أدخل مفتاح الجهاز ثم افحص السيرفر واحفظ الإعدادات')
        ttk.Label(main,textvariable=self.status,wraplength=750,anchor='e').pack(fill='x',pady=8)
        self.counts=tk.StringVar();ttk.Label(main,textvariable=self.counts,font=('Segoe UI',11,'bold'),anchor='e').pack(fill='x',pady=5)
        self.log=tk.Text(main,height=10,wrap='word',state='disabled',font=('Segoe UI',10));self.log.pack(fill='both',expand=True)
        ttk.Label(main,text='النتائج تظهر داخل برنامج المختبر ← الأجهزة ← النتائج الواردة. الحفظ لا يعني اعتماد التقرير.',wraplength=740,anchor='e').pack(fill='x',pady=8)
        ttk.Button(main,text='فتح مجلد الإعدادات والبيانات',command=self.open_folder).pack(anchor='e')
        self.root.protocol('WM_DELETE_WINDOW',self.quit)
        self.root.after(200,self.poll)
        if self.config.get('device_id') and self.auto.get():self.root.after(600,self.start)

    def notify(self,text):self.events.put(('log',text))

    def settings(self):
        return {**self.config,**{k:v.get().strip() for k,v in self.vars.items()},'auto_start':self.auto.get()}

    def controls(self):
        running=self.bridge is not None
        for item,key in self.inputs:item.configure(state='disabled' if running or self.busy else ('readonly' if key=='sample_field' else 'normal'))
        self.save_button.configure(state='disabled' if running or self.busy else 'normal')
        self.start_button.configure(state='disabled' if running or self.busy else 'normal')
        self.stop_button.configure(state='disabled' if not running or self.busy else 'normal')

    def save(self):
        if self.bridge or self.busy:return
        try:
            cfg=validate_config(self.settings(),require_destination=False)
            cfg.pop('device_id',None)
        except Exception as e:messagebox.showerror('الإعدادات',str(e));return
        self.busy=True;self.controls();self.status.set('جارٍ فحص الاتصال بالسيرفر...')
        def run():
            try:
                reply=Api(cfg).check();cfg['device_id']=reply['device_id']
                if not self.store.can_change_destination(cfg):raise ValueError('Unsent records belong to another server/device. Finish delivery first.')
                temp=SETTINGS.with_suffix('.tmp');temp.write_text(json.dumps(cfg,indent=2),encoding='utf-8')
                try:temp.chmod(0o600)
                except OSError:pass
                temp.replace(SETTINGS)
                self.events.put(('saved',cfg))
            except Exception as e:self.events.put(('failed',str(e)))
        threading.Thread(target=run,daemon=True).start()

    def start(self):
        if self.bridge or self.busy:return
        try:
            # Only a verified saved configuration can start the receiver.
            cfg=validate_config(self.config)
            edited=validate_config(self.settings())
            if any(edited[k]!=cfg[k] for k in self.vars):raise ValueError('Save and verify changed settings first')
            self.bridge=Bridge(cfg,self.store,self.notify);self.bridge.start();self.controls()
        except Exception as e:
            self.bridge=None;messagebox.showerror('تعذر التشغيل',str(e));self.controls()

    def stop(self,closing=False):
        if self.busy:return
        if not self.bridge:
            if closing:self.root.destroy()
            return
        self.busy=True;self.controls();self.status.set('جارٍ الإيقاف وحفظ الحالة...')
        def run():
            try:self.bridge.close();self.events.put(('closed',closing))
            except Exception as e:self.events.put(('failed',str(e)))
        threading.Thread(target=run,daemon=True).start()

    def retry(self):self.store.retry();self.notify('تمت جدولة إعادة المحاولة؛ شغّل الاستقبال لإرسالها')
    def quit(self):
        if self.busy:messagebox.showinfo('انتظار','انتظر اكتمال العملية الحالية قبل الإغلاق');return
        self.stop(closing=True)
    def open_folder(self):
        if sys.platform=='win32':os.startfile(str(HOME_DIR))
        else:messagebox.showinfo('Data',str(HOME_DIR))
    def poll(self):
        try:
            while True:
                kind,value=self.events.get_nowait()
                if kind=='saved':self.config=value;self.busy=False;self.controls();value='تم التحقق والحفظ — اضغط تشغيل'
                elif kind=='failed':self.busy=False;self.controls();messagebox.showerror('تنبيه',value)
                elif kind=='closed':
                    self.bridge=None;self.busy=False;self.controls()
                    if value:self.root.destroy();return
                    value='الاستقبال متوقف؛ النتائج المحفوظة لم تُحذف'
                self.status.set(value);self.log.configure(state='normal');self.log.insert('end',value+'\n')
                if int(self.log.index('end-1c').split('.')[0])>150:self.log.delete('1.0','20.0')
                self.log.see('end');self.log.configure(state='disabled')
        except queue.Empty:pass
        try:
            counts=self.store.counts()
            self.counts.set(f"محفوظة بالسيرفر: {counts.get('sent',0)}  |  تنتظر الإرسال: {counts.get('pending',0)}  |  تحتاج تدخّل: {counts.get('blocked',0)+counts.get('review',0)}")
        except (sqlite3.Error,OSError):pass
        self.root.after(500,self.poll)


if __name__=='__main__':
    window=Window()
    if '--smoke-test' in sys.argv:
        window.root.update();window.root.destroy()
    else:window.root.mainloop()
