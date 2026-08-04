document.addEventListener('DOMContentLoaded',()=>{
 const app=document.querySelector('.slides-app'),editor=app?.querySelector('.editor');if(!editor)return;
 const stack=editor.querySelector('.slide-stack'),articles=[...stack.querySelectorAll('.slide-editor')],sorter=editor.querySelector('#slide-sorter'),csrf=document.querySelector('meta[name="csrf-token"]').content;
 const right=document.createElement('aside');right.className='interaction-panel';right.innerHTML='<header><div><span>INTERACCIONES</span><h3>Participación</h3></div><small>Diapositiva actual</small></header><div class="interaction-slot"></div>';editor.append(right);
 const slot=right.querySelector('.interaction-slot'),status=document.createElement('span');status.className='autosave-status';status.textContent='✓ Todo guardado';app.querySelector('.pagehead .actions')?.prepend(status);
 const origins=new Map,thumbs=new Map;
 articles.forEach((article,index)=>{
   const id=article.id.replace('slide-',''),form=article.querySelector('.slide-content-form'),activities=article.querySelector('.activities'),canvas=article.querySelector('.editor-canvas');origins.set(id,{article,activities});
   const item=sorter.querySelector(`[data-slide-id="${id}"]`),preview=canvas.cloneNode(true);preview.className+=' sorter-preview';preview.querySelectorAll('.classic-elements').forEach(layer=>layer.remove());item.querySelector('a').prepend(preview);thumbs.set(id,item);
   const format=document.createElement('div');format.className='format-bar';const content=document.createElement('div');content.className='content-fields';
   [...form.children].forEach(child=>{if(child.matches('.layout-picker,.background-picker,.design-controls'))format.append(child);else if(!child.matches('input[name="_token"],input[name="_method"]'))content.append(child)});form.prepend(format);form.append(content);form.querySelector('button.ghost')?.remove();
   let timer;const save=async()=>{clearTimeout(timer);status.textContent='Guardando…';status.classList.add('saving');const response=await fetch(form.action,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'text/html'},body:new FormData(form)});status.textContent=response.ok?'✓ Todo guardado':'No se pudo guardar';status.classList.toggle('saving',!response.ok)};
   form.addEventListener('submit',event=>{event.preventDefault();save()});form.querySelectorAll('input,textarea,select').forEach(control=>control.addEventListener(control.type==='file'?'change':'input',()=>{clearTimeout(timer);timer=setTimeout(save,control.type==='file'?50:650)}));
 });
 let current=null;const activate=id=>{if(current&&origins.has(current)){const old=origins.get(current);old.article.append(old.activities)}current=String(id);articles.forEach(article=>article.classList.toggle('is-current',article.id===`slide-${current}`));thumbs.forEach((item,key)=>item.classList.toggle('is-current',key===current));const selected=origins.get(current);if(selected)slot.append(selected.activities);slot.scrollTop=0;document.querySelector('.slides-current-number').textContent=selected?.article.querySelector('.slide-number')?.textContent||''};
 sorter.addEventListener('click',event=>{const item=event.target.closest('.slide-sort-item');if(!item||event.target.closest('.move-slide'))return;event.preventDefault();activate(item.dataset.slideId)});
 const currentBadge=document.createElement('span');currentBadge.className='slides-current-number';app.querySelector('.pagehead>div')?.append(currentBadge);activate(articles[0]?.id.replace('slide-',''));
});
