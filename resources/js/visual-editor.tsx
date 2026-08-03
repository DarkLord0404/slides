import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Stage, Layer, Rect, Text, Image as KImage, Transformer } from 'react-konva';
import './visual-editor.css';
import './visual-theme.css';
import './visual-interactions.css';

type ElementType = 'text' | 'rect' | 'image';
type CanvasElement = {
    id: string; type: ElementType; x: number; y: number; width: number; height: number;
    rotation: number; text?: string; src?: string; fill?: string; fontSize?: number;
    fontFamily?: string; fontStyle?: string;
};
type Activity = { id: number; type: string; question: string; options: string[]; delete_url: string };
type Slide = {
    id: number; position: number; title: string; elements: CanvasElement[]; save_url: string;
    activity_url: string; activities: Activity[];
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
    return <KImage ref={nodeRef} image={image} {...item} draggable onClick={onSelect} onTap={onSelect}
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
    const [history, setHistory] = useState<CanvasElement[][]>([]);
    const [interaction, setInteraction] = useState('');
    const transformer = useRef<any>(null);
    const nodes = useRef<Record<string, any>>({});
    const slide = slides[active];
    const chosen = slide.elements.find(e => e.id === selected);

    const update = (elements: CanvasElement[], remember = true) => {
        if (remember) setHistory(h => [...h.slice(-30), slide.elements]);
        setSlides(all => all.map((value, index) => index === active ? { ...value, elements } : value));
        setStatus('Guardando…');
    };
    const change = (next: CanvasElement) => update(slide.elements.map(item => item.id === next.id ? next : item));

    useEffect(() => {
        const timer = setTimeout(async () => {
            if (status !== 'Guardando…') return;
            const response = await fetch(slide.save_url, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf },
                body: JSON.stringify({ elements: slide.elements }),
            });
            setStatus(response.ok ? 'Guardado' : 'Error al guardar');
        }, 450);
        return () => clearTimeout(timer);
    }, [slide.elements]);

    useEffect(() => {
        transformer.current?.nodes(selected && nodes.current[selected] ? [nodes.current[selected]] : []);
        transformer.current?.getLayer()?.batchDraw();
    }, [selected, slide.elements]);

    useEffect(() => {
        const key = (event: KeyboardEvent) => {
            if ((event.key === 'Delete' || event.key === 'Backspace') && selected && !editing) {
                event.preventDefault(); update(slide.elements.filter(item => item.id !== selected)); setSelected(null);
            }
            if ((event.ctrlKey || event.metaKey) && event.key === 'z') {
                const previous = history.at(-1);
                if (previous) { event.preventDefault(); setHistory(h => h.slice(0, -1)); update(previous, false); }
            }
        };
        addEventListener('keydown', key);
        return () => removeEventListener('keydown', key);
    }, [selected, editing, slide, history]);

    const addText = () => update([...slide.elements, { id: uid(), type: 'text', x: 140, y: 110, width: 500, height: 120, rotation: 0, text: 'Escribe aquí', fill: '#102a2e', fontSize: 48, fontFamily: 'Arial' }]);
    const addRect = () => update([...slide.elements, { id: uid(), type: 'rect', x: 220, y: 220, width: 260, height: 150, rotation: 0, fill: '#ff6846' }]);
    const addImage = (file: File) => { const reader = new FileReader(); reader.onload = () => update([...slide.elements, { id: uid(), type: 'image', x: 180, y: 120, width: 420, height: 280, rotation: 0, src: String(reader.result) }]); reader.readAsDataURL(file); };
    const beginEdit = (item: CanvasElement) => { setSelected(item.id); setEditing(item.id); setDraft(item.text || ''); };
    const finishEdit = () => { if (editing) { const item = slide.elements.find(e => e.id === editing); if (item) change({ ...item, text: draft }); } setEditing(null); };
    const undo = () => { const previous = history.at(-1); if (previous) { setHistory(h => h.slice(0, -1)); update(previous, false); } };

    return <div className="ve">
        <header>
            <a href={data.edit_url} onClick={event => { if (!confirm('Volverás a la edición asistida. Los objetos avanzados se conservarán, pero allí solo podrás modificar título, contenido, fondo e interacciones.')) event.preventDefault(); }}>← Edición asistida</a>
            <b>{data.title}</b><span>{status}</span><button onClick={undo}>↶ Deshacer</button>
        </header>
        <aside>
            <section><h3>Agregar</h3><div className="tool-grid"><button onClick={addText}>A Texto</button><button onClick={addRect}>□ Forma</button><label>▧ Imagen<input type="file" accept="image/*" onChange={e => e.target.files?.[0] && addImage(e.target.files[0])} /></label></div></section>
            <section><h3>Propiedades</h3>{chosen?.type === 'text' ? <div className="properties">
                <label>Fuente<select value={chosen.fontFamily || 'Arial'} onChange={e => change({ ...chosen, fontFamily: e.target.value })}><option>Arial</option><option>Georgia</option><option>Verdana</option><option>Trebuchet MS</option><option>Times New Roman</option><option>Courier New</option></select></label>
                <label>Tamaño de letra<input type="number" min="10" max="180" value={chosen.fontSize || 40} onChange={e => change({ ...chosen, fontSize: Number(e.target.value) })} /></label>
                <label>Color<input type="color" value={chosen.fill || '#102a2e'} onChange={e => change({ ...chosen, fill: e.target.value })} /></label>
                <button onClick={() => change({ ...chosen, fontStyle: chosen.fontStyle === 'bold' ? 'normal' : 'bold' })}>Negrita</button>
                <p className="hint">El marco cambia el espacio del texto; el tamaño de letra solo cambia aquí.</p>
            </div> : chosen ? <div className="properties"><label>Color<input type="color" value={chosen.fill || '#ff6846'} onChange={e => change({ ...chosen, fill: e.target.value })} /></label></div> : <p className="hint">Selecciona un objeto para modificarlo.</p>}</section>
            <section><h3>Interacciones de esta diapositiva</h3><div className="activity-list">{slide.activities.map(activity => <div key={activity.id}><b>{activity.question}</b><small>{activity.type.replace('_', ' ')}</small><form method="post" action={activity.delete_url} onSubmit={event => { if (!confirm('¿Eliminar esta interacción?')) event.preventDefault(); }}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="_method" value="DELETE" /><button>Eliminar</button></form></div>)}</div>
                {!interaction ? <div className="interaction-list"><button onClick={() => setInteraction('multiple_choice')}>Encuesta</button><button onClick={() => setInteraction('word_cloud')}>Nube de palabras</button><button onClick={() => setInteraction('open_text')}>Pregunta abierta</button><button onClick={() => setInteraction('true_false')}>Verdadero / falso</button></div> : <form className="new-activity" method="post" action={slide.activity_url}><input type="hidden" name="_token" value={data.csrf} /><input type="hidden" name="type" value={interaction} /><label>Pregunta<textarea name="question" maxLength={220} required autoFocus /></label>{interaction === 'multiple_choice' && <label>Opciones, una por línea<textarea name="options_text" maxLength={500} required /></label>}<div><button type="button" onClick={() => setInteraction('')}>Cancelar</button><button>Agregar</button></div></form>}
                <p className="hint">Los “Me gusta” ya están disponibles automáticamente para la audiencia.</p>
            </section>
        </aside>
        <main><div className="ruler">Diapositiva {active + 1} · 1280 × 720</div><div className="canvas-shell">
            <Stage width={960} height={540} scaleX={scale} scaleY={scale} onMouseDown={e => { if (e.target === e.target.getStage()) setSelected(null); }}><Layer><Rect width={1280} height={720} fill="#fffdf8" />
                {slide.elements.map(item => item.type === 'text' ? <React.Fragment key={item.id}>{selected === item.id && <Rect x={item.x} y={item.y} width={item.width} height={item.height} rotation={item.rotation} stroke="#007f7b" strokeWidth={2} dash={[8, 6]} listening={false} />}<Text ref={node => { nodes.current[item.id] = node; }} {...item} wrap="word" verticalAlign="top" opacity={editing === item.id ? 0 : 1} draggable onClick={() => setSelected(item.id)} onTap={() => setSelected(item.id)} onDblClick={() => beginEdit(item)} onDblTap={() => beginEdit(item)} onDragEnd={e => change({ ...item, x: e.target.x(), y: e.target.y() })} onTransformEnd={e => { const node = e.target, sx = node.scaleX(), sy = node.scaleY(); node.scaleX(1); node.scaleY(1); change({ ...item, x: node.x(), y: node.y(), rotation: node.rotation(), width: Math.max(80, item.width * sx), height: Math.max(item.fontSize || 20, item.height * sy), fontSize: item.fontSize }); }} /></React.Fragment> : item.type === 'rect' ? <Rect key={item.id} ref={node => { nodes.current[item.id] = node; }} {...item} draggable onClick={() => setSelected(item.id)} onDragEnd={e => change({ ...item, x: e.target.x(), y: e.target.y() })} onTransformEnd={e => { const node = e.target, sx = node.scaleX(), sy = node.scaleY(); node.scaleX(1); node.scaleY(1); change({ ...item, x: node.x(), y: node.y(), rotation: node.rotation(), width: node.width() * sx, height: node.height() * sy }); }} /> : <Picture key={item.id} nodeRef={(node: any) => nodes.current[item.id] = node} item={item} onSelect={() => setSelected(item.id)} onChange={change} />)}
                <Transformer ref={transformer} rotateEnabled enabledAnchors={chosen?.type === 'text' ? ['top-left', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-right'] : undefined} boundBoxFunc={(_, box) => box.width < 30 || box.height < 20 ? _ : box} />
            </Layer></Stage>
            {editing && chosen?.type === 'text' && <textarea autoFocus className="inline-editor" style={{ left: chosen.x * scale, top: chosen.y * scale, width: chosen.width * scale, height: chosen.height * scale, fontSize: (chosen.fontSize || 40) * scale, fontFamily: chosen.fontFamily, color: chosen.fill, fontWeight: chosen.fontStyle === 'bold' ? 700 : 400, transform: `rotate(${chosen.rotation}deg)` }} value={draft} onChange={e => setDraft(e.target.value)} onBlur={finishEdit} onKeyDown={e => { if (e.key === 'Escape') setEditing(null); if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') finishEdit(); }} />}
        </div><small>Doble clic para escribir · el marco verde delimita el texto · Supr elimina</small></main>
        <nav><h3>Diapositivas</h3><div className="filmstrip">{slides.map((item, index) => <button key={item.id} className={index === active ? 'active' : ''} onClick={() => { setActive(index); setSelected(null); setEditing(null); setInteraction(''); }}><span>{index + 1}</span><div>{item.title || 'Sin título'}</div></button>)}</div></nav>
    </div>;
}

createRoot(document.querySelector('#visual-editor')!).render(<App />);
