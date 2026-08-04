import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Stage, Layer, Rect, Text, Image as KImage, Transformer } from 'react-konva';
import '@fontsource-variable/montserrat/wght.css';
import '@fontsource-variable/montserrat/wght-italic.css';
import './visual-editor.css';
import './visual-interactions.css';
import './visual-layers.css';

type ElementType = 'text' | 'rect' | 'ellipse' | 'image';
type CanvasElement = {
    id: string; type: ElementType; x: number; y: number; width: number; height: number;
    rotation: number; text?: string; src?: string; fill?: string; fontSize?: number;
    fontFamily?: string; fontStyle?: string; align?: 'left' | 'center' | 'right' | 'justify'; strokeWidth?: number;
};
type Activity = { id: number; type: string; question: string; options: string[]; delete_url: string };
type Slide = {
    id: number; position: number; title: string; elements: CanvasElement[]; save_url: string;
    activity_url: string; delete_url: string; load_url: string; thumbnail_url: string; loaded: boolean; activities: Activity[];
};

const uid = () => crypto.randomUUID();
const scale = .75;

function Picture({ item, nodeRef, onSelect, onChange }: any) {
    const [image, setImage] = useState<HTMLImageElement>();
    useEffect(() => {
        const img = new Image();
        img.onload = () => setImage(img);
        img.src = item.src;
    }, [item.src]);
    return <KImage ref={nodeRef} image={image} {...item} draggable={!item.id.startsWith('theme-')} onClick={onSelect} onTap={onSelect}
        onDragEnd={e => onChange({ ...item, x: e.target.x(), y: e.target.y() })}
        onTransformEnd={e => {
            const n = e.target, sx = n.scaleX(), sy = n.scaleY();
            n.scaleX(1); n.scaleY(1);
            onChange({ ...item, x: n.x(), y: n.y(), rotation: n.rotation(), width: Math.max(20, n.width() * sx), height: Math.max(20, n.height() * sy) });
        }} />;
}

function App() {
    const host = document.querySelector('#visual-editor') as HTMLElement;
    const data = JSON.parse(host.dataset.presentation || '{}');
    const [slides, setSlides] = useState<Slide[]>(data.slides);
    const [active, setActive] = useState(0);
    const [selected, setSelected] = useState<string | null>(null);
    const [editing, setEditing] = useState<string | null>(null);
    const [draft, setDraft] = useState('');
    const [status, setStatus] = useState('Guardado');
    const [history, setHistory] = useState<Record<number, CanvasElement[][]>>({});
    const [interaction, setInteraction] = useState('');
    const [contextMenu, setContextMenu] = useState<{ x: number; y: number } | null>(null);
    const [backgroundColor, setBackgroundColor] = useState('#fffdf8');
    const [accentColor, setAccentColor] = useState('#ff6846');
    const [backgroundStyle, setBackgroundStyle] = useState<'solid' | 'rings' | 'spotlight'>('solid');
    const [draggedSlide, setDraggedSlide] = useState<number | null>(null);
    const [thumbnailRevision, setThumbnailRevision] = useState<Record<number, number>>({});
    const [thumbnailImages, setThumbnailImages] = useState<Record<number, string>>({});
    const transformer = useRef<any>(null);
    const stage = useRef<any>(null);
    const selectionBox = useRef<any>(null);
    const nodes = useRef<Record<string, any>>({});
    const slide = slides[active];
    const chosen = slide.elements.find(e => e.id === selected);
    const background = slide.elements.find(e => e.id === 'theme-background');
    const canvasBackground = background?.fill || data.theme_settings?.background_color || '#fffdf8';

    const update = (elements: CanvasElement[], remember = true) => {
        if (remember) setHistory(all => ({ ...all, [slide.id]: [...(all[slide.id] || []).slice(-30), slide.elements] }));
        setSlides(all => all.map((value, index) => index === active ? { ...value, elements } : value));
        setStatus('Guardando…');
    };
    const change = (next: CanvasElement) => {
        update(slide.elements.map(item => item.id === next.id ? next : item));
        if (next.id === `legacy-title-${slide.id}`) setSlides(all => all.map((value, index) => index === active ? { ...value, title: next.text || '' } : value));
    };
    const toggleFontStyle = (style: 'bold' | 'italic') => {
        if (!chosen || chosen.type !== 'text') return;
        const styles = new Set((chosen.fontStyle || 'normal').split(' ').filter(value => value !== 'normal'));
        styles.has(style) ? styles.delete(style) : styles.add(style);
        change({ ...chosen, fontStyle: styles.size ? [...styles].join(' ') : 'normal' });
    };
    const changeFont = async (fontFamily: string) => {
        if (!chosen || chosen.type !== 'text') return;
        change({ ...chosen, fontFamily });
        await document.fonts.load(`16px "${fontFamily}"`);
        setSlides(all => [...all]);
    };
    const pickScreenColor = async (apply: (color: string) => void) => {
        if (!('EyeDropper' in window)) {
            setStatus('Tu navegador no admite el cuentagotas');
            return;
        }
        try {
            const result = await new (window as any).EyeDropper().open();
            apply(result.sRGBHex);
        } catch { /* The user cancelled the picker. */ }
    };
    const changeBackground = (color: string) => {
        if (background) {
            change({ ...background, fill: color });
            return;
        }
        update([{ id: 'theme-background', type: 'rect', x: 0, y: 0, width: 1280, height: 720, rotation: 0, fill: color }, ...slide.elements]);
    };

    const persistSlide = (target: Slide) => fetch(target.save_url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf },
        body: JSON.stringify({ elements: target.elements }),
    });

    useEffect(() => {
        const timer = setTimeout(async () => {
            if (status !== 'Guardando…') return;
            const response = await persistSlide(slide);
            setStatus(response.ok ? 'Guardado' : 'Error al guardar');
            if (response.ok) setThumbnailRevision(all => ({ ...all, [slide.id]: (all[slide.id] || 0) + 1 }));
        }, 450);
        return () => clearTimeout(timer);
    }, [slide.elements]);

    useEffect(() => {
        transformer.current?.nodes(selected && nodes.current[selected] ? [nodes.current[selected]] : []);
        transformer.current?.getLayer()?.batchDraw();
    }, [selected, slide.elements]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (!stage.current) return;
            transformer.current?.hide(); selectionBox.current?.hide();
            stage.current.batchDraw();
            const image = stage.current.toDataURL({ pixelRatio: .34 });
            transformer.current?.show(); selectionBox.current?.show(); stage.current.batchDraw();
            setThumbnailImages(all => ({ ...all, [slide.id]: image }));
        }, 650);
        return () => clearTimeout(timer);
    }, [slide.id, slide.elements]);

    useEffect(() => {
        const key = (event: KeyboardEvent) => {
            if ((event.key === 'Delete' || event.key === 'Backspace') && selected && !editing) {
                event.preventDefault(); update(slide.elements.filter(item => item.id !== selected)); setSelected(null);
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z' && !editing) {
                const previous = history[slide.id]?.at(-1);
                if (previous) { event.preventDefault(); setHistory(all => ({ ...all, [slide.id]: all[slide.id].slice(0, -1) })); update(previous, false); }
            }
        };
        addEventListener('keydown', key);
        return () => removeEventListener('keydown', key);
    }, [selected, editing, slide, history]);

    const addText = () => update([...slide.elements, { id: uid(), type: 'text', x: 140, y: 110, width: 500, height: 120, rotation: 0, text: 'Escribe aquí', fill: '#102a2e', fontSize: 48, fontFamily: 'Arial' }]);
    const addRect = () => update([...slide.elements, { id: uid(), type: 'rect', x: 220, y: 220, width: 260, height: 150, rotation: 0, fill: '#ff6846' }]);
    const addImageSource = (src: string) => update([...slide.elements, { id: uid(), type: 'image', x: 180, y: 120, width: 420, height: 280, rotation: 0, src }]);
    const addImage = (file: File) => { const reader = new FileReader(); reader.onload = () => addImageSource(String(reader.result)); reader.readAsDataURL(file); };
    const beginEdit = (item: CanvasElement) => { setSelected(item.id); setEditing(item.id); setDraft(item.text || ''); };
    const finishEdit = () => { if (editing) { const item = slide.elements.find(e => e.id === editing); if (item) change({ ...item, text: draft }); } setEditing(null); };
    const undo = () => { const previous = history[slide.id]?.at(-1); if (previous) { setHistory(all => ({ ...all, [slide.id]: all[slide.id].slice(0, -1) })); update(previous, false); } };
    const moveLayer = (mode: 'back' | 'down' | 'up' | 'front') => {
        if (!selected) return;
        const elements = [...slide.elements], index = elements.findIndex(item => item.id === selected);
        if (index < 0) return;
        const [item] = elements.splice(index, 1);
        let floor = 0;
        while (floor < elements.length && elements[floor].id.startsWith('theme-')) floor++;
        const target = mode === 'back' ? floor : mode === 'front' ? elements.length : mode === 'down' ? Math.max(floor, index - 1) : Math.min(elements.length, index + 1);
        elements.splice(target, 0, item); update(elements);
    };
    const reorderSlide = async (index: number, target: number) => {
        if (index === target || target < 0 || target >= slides.length) return;
        const activeId = slide.id;
        const reordered = [...slides];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(target, 0, moved);
        setSlides(reordered); setActive(reordered.findIndex(item => item.id === activeId)); setStatus('Guardando orden…');
        const response = await fetch(data.reorder_url, { method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf }, body: JSON.stringify({ slide_ids: reordered.map(item => item.id) }) });
        setStatus(response.ok ? 'Guardado' : 'Error al ordenar');
    };

    const backgroundElements = (style: 'solid' | 'rings' | 'spotlight', color: string, accent: string): CanvasElement[] => {
        const elements: CanvasElement[] = [{ id: 'theme-background', type: 'rect', x: 0, y: 0, width: 1280, height: 720, rotation: 0, fill: color }];
        if (style === 'rings') elements.push(
            { id: 'theme-ring-1', type: 'ellipse', x: 1030, y: -170, width: 430, height: 430, rotation: 0, fill: accent, strokeWidth: 42 },
            { id: 'theme-ring-2', type: 'ellipse', x: -170, y: 535, width: 340, height: 340, rotation: 0, fill: accent, strokeWidth: 34 },
        );
        if (style === 'spotlight') elements.push(
            { id: 'theme-spot-1', type: 'ellipse', x: 850, y: 70, width: 520, height: 520, rotation: 0, fill: accent, strokeWidth: 110 },
            { id: 'theme-spot-2', type: 'ellipse', x: -190, y: -210, width: 420, height: 420, rotation: 0, fill: accent, strokeWidth: 80 },
        );
        return elements;
    };
    const applyBackground = async (allSlides = false) => {
        const backgrounds = backgroundElements(backgroundStyle, backgroundColor, accentColor);
        const merge = (elements: CanvasElement[]) => [...backgrounds, ...elements.filter(item => !item.id.startsWith('theme-'))];
        if (!allSlides) {
            update(merge(slide.elements)); setContextMenu(null); return;
        }
        setStatus('Aplicando fondo…');
        const response = await fetch(data.background_url, { method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf }, body: JSON.stringify({ background_elements: backgrounds }) });
        if (response.ok) setSlides(items => items.map(item => item.loaded ? { ...item, elements: merge(item.elements) } : item));
        setStatus(response.ok ? 'Guardado' : 'Error al aplicar fondo'); setContextMenu(null);
    };

    useEffect(() => {
        const paste = (event: ClipboardEvent) => {
            const target = event.target as HTMLElement;
            if (editing || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;
            const imageItem = [...event.clipboardData!.items].find(item => item.type.startsWith('image/'));
            if (imageItem) { event.preventDefault(); const file = imageItem.getAsFile(); if (file) addImage(file); return; }
            const html = event.clipboardData?.getData('text/html');
            if (html) {
                const documentCopy = new DOMParser().parseFromString(html, 'text/html');
                const image = documentCopy.querySelector('img')?.src;
                if (image) { event.preventDefault(); addImageSource(image); return; }
            }
            const text = event.clipboardData?.getData('text/plain')?.trim();
            if (text) { event.preventDefault(); update([...slide.elements, { id: uid(), type: 'text', x: 140, y: 120, width: 700, height: 220, rotation: 0, text, fill: '#102a2e', fontSize: 40, fontFamily: 'Montserrat Variable' }]); }
        };
        addEventListener('paste', paste);
        return () => removeEventListener('paste', paste);
    }, [slide, editing]);
    const selectSlide = async (index: number) => {
        setSelected(null); setEditing(null); setInteraction('');
        if (status === 'Guardando…') {
            const saved = await persistSlide(slide);
            if (!saved.ok) { setStatus('Error al guardar'); return; }
        }
        if (slides[index].loaded) { setActive(index); return; }
        setStatus('Cargando diapositiva…');
        const response = await fetch(slides[index].load_url, { headers: { Accept: 'application/json' } });
        if (!response.ok) { setStatus('No se pudo cargar'); return; }
        const payload = await response.json();
        setSlides(all => all.map((item, position) => position === index ? { ...item, elements: payload.elements, loaded: true } : item));
        setActive(index); setStatus('Guardado');
    };

    return <div className="ve">
        <header>
            <a href={data.edit_url}>← Presentaciones</a><img src="/brand/koqoi-mark.svg" alt="" /><b>{data.title}</b><span>{status}</span><button onClick={undo}>↶ Deshacer</button>
        </header>
        <aside>
            <section><h3>Agregar</h3><div className="tool-grid"><button onClick={addText}>A Texto</button><button onClick={addRect}>□ Forma</button><label>▧ Imagen<input type="file" accept="image/*" onChange={e => e.target.files?.[0] && addImage(e.target.files[0])} /></label></div></section>
            <section><h3>Propiedades</h3>{chosen?.type === 'text' ? <div className="properties">
                <label>Fuente<select value={chosen.fontFamily || 'Arial'} onChange={e => changeFont(e.target.value)}><option value="Montserrat Variable">Montserrat</option><option>Arial</option><option>Georgia</option><option>Verdana</option><option>Trebuchet MS</option><option>Times New Roman</option><option>Courier New</option></select></label>
                <label>Tamaño de letra<input type="number" min="10" max="180" value={chosen.fontSize || 40} onChange={e => change({ ...chosen, fontSize: Number(e.target.value) })} /></label>
                <label>Color de letra<div className="color-control"><input type="color" value={chosen.fill || '#102a2e'} onChange={e => change({ ...chosen, fill: e.target.value })} /><input aria-label="Código de color" value={chosen.fill || '#102a2e'} onChange={e => /^#[0-9a-f]{6}$/i.test(e.target.value) && change({ ...chosen, fill: e.target.value })} /><button title="Tomar color de la pantalla" onClick={() => pickScreenColor(color => change({ ...chosen, fill: color }))}>⌾</button></div></label>
                <button className={(chosen.fontStyle || '').includes('bold') ? 'active' : ''} onClick={() => toggleFontStyle('bold')}><b>B</b> Negrita</button>
                <button className={(chosen.fontStyle || '').includes('italic') ? 'active' : ''} onClick={() => toggleFontStyle('italic')}><i>I</i> Cursiva</button>
                <div className="alignment-controls"><button className={(chosen.align || 'left') === 'left' ? 'active' : ''} title="Alinear a la izquierda" onClick={() => change({ ...chosen, align: 'left' })}>≡</button><button className={chosen.align === 'center' ? 'active' : ''} title="Centrar" onClick={() => change({ ...chosen, align: 'center' })}>≡</button><button className={chosen.align === 'right' ? 'active' : ''} title="Alinear a la derecha" onClick={() => change({ ...chosen, align: 'right' })}>≡</button><button className={chosen.align === 'justify' ? 'active' : ''} title="Justificar" onClick={() => change({ ...chosen, align: 'justify' })}>☰</button></div>
                <p className="hint">El marco cambia el espacio del texto; el tamaño de letra solo cambia aquí.</p>
            </div> : chosen ? <div className="properties"><label>Color<input type="color" value={chosen.fill || '#ff6846'} onChange={e => change({ ...chosen, fill: e.target.value })} /></label></div> : <p className="hint">Selecciona un objeto para modificarlo.</p>}</section>
            {chosen && !chosen.id.startsWith('theme-') && <section><h3>Orden de capas</h3><div className="layer-controls"><button onClick={() => moveLayer('back')}>⇤ Al fondo</button><button onClick={() => moveLayer('down')}>↓ Bajar</button><button onClick={() => moveLayer('up')}>↑ Subir</button><button onClick={() => moveLayer('front')}>⇥ Al frente</button></div><p className="hint">Los objetos que están más al frente cubren a los que están detrás.</p></section>}
            <section><h3>Interacciones de esta diapositiva</h3><div className="activity-list">{slide.activities.map(activity => <div key={activity.id}><b>{activity.question}</b><small>{activity.type.replace('_', ' ')}</small><form method="post" action={activity.delete_url} onSubmit={event => { if (!confirm('¿Eliminar esta interacción?')) event.preventDefault(); }}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="_method" value="DELETE" /><button>Eliminar</button></form></div>)}</div>
                {!interaction ? <div className="interaction-list"><button onClick={() => setInteraction('multiple_choice')}>Encuesta</button><button onClick={() => setInteraction('word_cloud')}>Nube de palabras</button><button onClick={() => setInteraction('open_text')}>Pregunta abierta</button><button onClick={() => setInteraction('true_false')}>Verdadero / falso</button></div> : <form className="new-activity" method="post" action={slide.activity_url}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="type" value={interaction} /><label>Pregunta<textarea name="question" maxLength={220} required autoFocus /></label>{interaction === 'multiple_choice' && <label>Opciones, una por línea<textarea name="options_text" maxLength={500} required /></label>}<div><button type="button" onClick={() => setInteraction('')}>Cancelar</button><button>Agregar</button></div></form>}
                <p className="hint">Los “Me gusta” ya están disponibles automáticamente para la audiencia.</p>
            </section>
        </aside>
        <main onClick={() => setContextMenu(null)}><div className="ruler">Diapositiva {active + 1} · 1280 × 720</div><div className="canvas-shell" onContextMenu={event => { event.preventDefault(); setBackgroundColor(canvasBackground); setAccentColor(slide.elements.find(item => item.id.startsWith('theme-') && item.id !== 'theme-background')?.fill || '#ff6846'); setBackgroundStyle(slide.elements.some(item => item.id.startsWith('theme-spot')) ? 'spotlight' : slide.elements.some(item => item.id.startsWith('theme-ring')) ? 'rings' : 'solid'); setContextMenu({ x: event.clientX, y: event.clientY }); }}>
            <Stage ref={stage} width={960} height={540} scaleX={scale} scaleY={scale} onMouseDown={e => { if (e.target === e.target.getStage()) setSelected(null); }}><Layer><Rect width={1280} height={720} fill={canvasBackground} />
                {slide.elements.map(item => item.type === 'text' ? <React.Fragment key={item.id}>{selected === item.id && <Rect ref={selectionBox} x={item.x} y={item.y} width={item.width} height={item.height} rotation={item.rotation} stroke="#007f7b" strokeWidth={2} dash={[8, 6]} listening={false} />}<Text ref={node => { nodes.current[item.id] = node; }} {...item} wrap="word" verticalAlign="top" opacity={editing === item.id ? 0 : 1} draggable onClick={() => setSelected(item.id)} onTap={() => setSelected(item.id)} onDblClick={() => beginEdit(item)} onDblTap={() => beginEdit(item)} onDragEnd={e => change({ ...item, x: e.target.x(), y: e.target.y() })} onTransformEnd={e => { const node = e.target, sx = node.scaleX(), sy = node.scaleY(); node.scaleX(1); node.scaleY(1); change({ ...item, x: node.x(), y: node.y(), rotation: node.rotation(), width: Math.max(80, item.width * sx), height: Math.max(item.fontSize || 20, item.height * sy), fontSize: item.fontSize }); }} /></React.Fragment> : item.type === 'rect' || item.type === 'ellipse' ? <Rect key={item.id} ref={node => { nodes.current[item.id] = node; }} {...item} fill={item.type === 'ellipse' ? 'transparent' : item.fill} stroke={item.type === 'ellipse' ? item.fill : undefined} strokeWidth={item.type === 'ellipse' ? item.strokeWidth || 30 : undefined} cornerRadius={item.type === 'ellipse' ? 999 : 0} draggable onClick={() => setSelected(item.id)} onTap={() => setSelected(item.id)} onDragEnd={e => change({ ...item, x: e.target.x(), y: e.target.y() })} onTransformEnd={e => { const node = e.target, sx = node.scaleX(), sy = node.scaleY(); node.scaleX(1); node.scaleY(1); change({ ...item, x: node.x(), y: node.y(), rotation: node.rotation(), width: node.width() * sx, height: node.height() * sy }); }} /> : <Picture key={item.id} nodeRef={(node: any) => nodes.current[item.id] = node} item={item} onSelect={() => setSelected(item.id)} onChange={change} />)}
                <Transformer ref={transformer} rotateEnabled enabledAnchors={chosen?.type === 'text' ? ['top-left', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-right'] : undefined} boundBoxFunc={(_, box) => box.width < 30 || box.height < 20 ? _ : box} />
            </Layer></Stage>
            {editing && chosen?.type === 'text' && <textarea autoFocus className="inline-editor" style={{ left: chosen.x * scale, top: chosen.y * scale, width: chosen.width * scale, height: chosen.height * scale, fontSize: (chosen.fontSize || 40) * scale, fontFamily: chosen.fontFamily, color: chosen.fill, fontWeight: (chosen.fontStyle || '').includes('bold') ? 700 : 400, fontStyle: (chosen.fontStyle || '').includes('italic') ? 'italic' : 'normal', textAlign: chosen.align || 'left', transform: `rotate(${chosen.rotation}deg)` }} value={draft} onChange={e => setDraft(e.target.value)} onBlur={finishEdit} onKeyDown={e => { if (e.key === 'Escape') setEditing(null); if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') finishEdit(); }} />}
        </div><small>Doble clic para escribir · el marco verde delimita el texto · Supr elimina</small></main>
        <nav><div className="pages-head"><h3>Páginas</h3><form method="post" action={data.slide_url}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="title" value="Nueva diapositiva" /><button title="Agregar diapositiva">＋</button></form></div><div className="filmstrip">{slides.map((item, index) => <div key={item.id} draggable className={`page-item ${index === active ? 'active' : ''} ${draggedSlide === index ? 'dragging' : ''}`} onDragStart={() => setDraggedSlide(index)} onDragOver={event => event.preventDefault()} onDrop={() => { if (draggedSlide !== null) reorderSlide(draggedSlide, index); setDraggedSlide(null); }} onDragEnd={() => setDraggedSlide(null)}><button className="page-preview" onClick={() => selectSlide(index)}><span>{index + 1}</span><img loading="lazy" src={thumbnailImages[item.id] || `${item.thumbnail_url}?v=${thumbnailRevision[item.id] || 0}`} alt={`Diapositiva ${index + 1}`} /></button><div className="page-actions">{slides.length > 1 && <form method="post" action={item.delete_url} onSubmit={event => { if (!confirm('¿Eliminar esta diapositiva?')) event.preventDefault(); }}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="_method" value="DELETE" /><button title="Eliminar">×</button></form>}</div></div>)}</div></nav>
        {contextMenu && <div className="background-menu" style={{ left: Math.min(contextMenu.x, innerWidth - 310), top: Math.min(contextMenu.y, innerHeight - 390) }} onClick={event => event.stopPropagation()}><header><b>Fondo</b><button onClick={() => setContextMenu(null)}>×</button></header><label>Color de fondo<div className="color-control"><input type="color" value={backgroundColor} onChange={e => setBackgroundColor(e.target.value)} /><input value={backgroundColor} onChange={e => /^#[0-9a-f]{6}$/i.test(e.target.value) && setBackgroundColor(e.target.value)} /><button title="Tomar color" onClick={() => pickScreenColor(setBackgroundColor)}>⌾</button></div></label><label>Color decorativo<div className="color-control"><input type="color" value={accentColor} onChange={e => setAccentColor(e.target.value)} /><input value={accentColor} onChange={e => /^#[0-9a-f]{6}$/i.test(e.target.value) && setAccentColor(e.target.value)} /><button title="Tomar color" onClick={() => pickScreenColor(setAccentColor)}>⌾</button></div></label><div className="background-styles"><button className={backgroundStyle === 'solid' ? 'active' : ''} onClick={() => setBackgroundStyle('solid')}>Liso</button><button className={backgroundStyle === 'rings' ? 'active' : ''} onClick={() => setBackgroundStyle('rings')}>Aros</button><button className={backgroundStyle === 'spotlight' ? 'active' : ''} onClick={() => setBackgroundStyle('spotlight')}>Focos</button></div><div className="background-actions"><button onClick={() => applyBackground(false)}>Aplicar</button><button onClick={() => applyBackground(true)}>Aplicar a todas</button></div></div>}
    </div>;
}

createRoot(document.querySelector('#visual-editor')!).render(<App />);
