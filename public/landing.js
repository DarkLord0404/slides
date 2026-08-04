document.addEventListener('DOMContentLoaded',()=>{
 const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target)}}),{threshold:.12});
 document.querySelectorAll('.reveal').forEach(el=>observer.observe(el));
 document.querySelectorAll('[data-count]').forEach(el=>{const target=Number(el.dataset.count);if(reduced){el.textContent=String(target);return}let n=0;const tick=()=>{n=Math.min(target,n+2);el.textContent=String(n);if(n<target)requestAnimationFrame(tick)};setTimeout(tick,500)});
 const scene=document.querySelector('[data-parallax]');if(scene&&!reduced)addEventListener('pointermove',event=>{const x=(event.clientX/innerWidth-.5)*10,y=(event.clientY/innerHeight-.5)*8;scene.style.setProperty('--rx',`${-y}deg`);scene.style.setProperty('--ry',`${x}deg`)});
 document.querySelectorAll('.mode-tabs button').forEach(button=>button.addEventListener('click',()=>{const mode=button.dataset.mode;document.querySelectorAll('.mode-tabs button,.mode-canvas,.mode-copy>div').forEach(el=>el.classList.remove('active'));button.classList.add('active');document.querySelector(`.mode-canvas.${mode}`)?.classList.add('active');document.querySelector(`[data-copy="${mode}"]`)?.classList.add('active')}));
});
