import { useState } from 'react';

/**
 * Single-series bar chart (one y-axis). Thin bars, 4px rounded tops anchored to the baseline,
 * recessive grid, per-bar hover tooltip with a hit target taller than the mark, and a table toggle.
 * Bar colour #0a9a80 was validated (lightness, chroma, contrast) against the light surface.
 */
export default function BarChart({ title, data, xKey, yKey, format = (v) => v, height = 180 }) {
    const [hover, setHover] = useState(null);
    const [table, setTable] = useState(false);
    const values = data.map((d) => Number(d[yKey]) || 0);
    const max = Math.max(1, ...values);
    const ticks = [0, max / 2, max];
    const W = 640, H = height, padL = 44, padB = 22, padT = 8;
    const plotW = W - padL - 4, plotH = H - padB - padT;
    const step = plotW / Math.max(1, data.length);
    const barW = Math.max(3, Math.min(28, step - 2));
    const peak = values.indexOf(max);

    return (
        <figure className="card p-4">
            <div className="mb-2 flex items-center justify-between">
                <figcaption className="text-sm font-bold text-slate-900">{title}</figcaption>
                <button className="text-xs font-semibold text-brand-700" onClick={() => setTable((t) => !t)}>{table ? 'Show chart' : 'Show table'}</button>
            </div>
            {data.length === 0 ? <p className="py-10 text-center text-sm text-slate-500">No data in this period.</p> : table ? (
                <div className="table-wrap max-h-64 overflow-y-auto"><table><thead><tr><th>Period</th><th>{title}</th></tr></thead><tbody>{data.map((d) => <tr key={d[xKey]}><td>{d[xKey]}</td><td>{format(d[yKey])}</td></tr>)}</tbody></table></div>
            ) : (
                <div className="relative">
                    <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label={`${title} bar chart`}>
                        {ticks.map((t) => {
                            const y = padT + plotH - (t / max) * plotH;
                            return <g key={t}><line x1={padL} x2={W} y1={y} y2={y} stroke="#e2e8f0" strokeWidth="1" /><text x={padL - 6} y={y + 3} textAnchor="end" fontSize="10" fill="#64748b">{format(Math.round(t))}</text></g>;
                        })}
                        {data.map((d, i) => {
                            const v = values[i];
                            const h = Math.max(v > 0 ? 2 : 0, (v / max) * plotH);
                            const x = padL + i * step + (step - barW) / 2;
                            const y = padT + plotH - h;
                            const r = Math.min(4, barW / 2, h);
                            return (
                                <g key={d[xKey]} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)} onFocus={() => setHover(i)} onBlur={() => setHover(null)} tabIndex={0}>
                                    <rect x={padL + i * step} y={padT} width={step} height={plotH} fill="transparent" />
                                    {h > 0 && <path d={`M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + barW - r} Q${x + barW},${y} ${x + barW},${y + r} V${y + h} Z`} fill="#0a9a80" opacity={hover === null || hover === i ? 1 : 0.45} />}
                                    {i === peak && v > 0 && <text x={x + barW / 2} y={y - 4} textAnchor="middle" fontSize="10" fontWeight="600" fill="#0f172a">{format(v)}</text>}
                                </g>
                            );
                        })}
                        <line x1={padL} x2={W} y1={padT + plotH} y2={padT + plotH} stroke="#cbd5e1" />
                        {data.map((d, i) => (data.length <= 12 || i % Math.ceil(data.length / 8) === 0) && <text key={d[xKey]} x={padL + i * step + step / 2} y={H - 6} textAnchor="middle" fontSize="10" fill="#64748b">{String(d[xKey]).slice(-5)}</text>)}
                    </svg>
                    {hover !== null && (
                        <div className="pointer-events-none absolute top-0 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow" style={{ left: `${((padL + hover * step + step / 2) / W) * 100}%`, transform: 'translateX(-50%)' }}>
                            <div className="text-slate-300">{data[hover][xKey]}</div><div className="font-semibold">{format(values[hover])}</div>
                        </div>
                    )}
                </div>
            )}
        </figure>
    );
}
