import { Link } from 'react-router-dom';
import Icon from './icons';

const TONES = { amber: 'bg-amber-50 text-amber-600', green: 'bg-emerald-50 text-emerald-600', blue: 'bg-sky-50 text-sky-600', red: 'bg-red-50 text-red-600', brand: 'bg-brand-50 text-brand-600', slate: 'bg-slate-100 text-slate-500' };

/** "What needs doing" list for portal home screens. Items with a zero count are hidden. */
export function TodoList({ items, done, title = 'To do' }) {
    const open = items.filter((i) => i.count > 0);
    return (
        <section className="card overflow-hidden" aria-label={title}>
            <h2 className="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">{title}{open.length > 0 && <span className="ml-2 rounded-full bg-red-500 px-2 py-0.5 text-[11px] text-white">{open.reduce((n, i) => n + i.count, 0)}</span>}</h2>
            {open.length === 0 ? (
                <p className="flex items-center gap-3 px-4 py-4 text-sm text-slate-600"><span className="rounded-full bg-emerald-50 p-1.5 text-emerald-600"><Icon name="check" className="h-4 w-4" /></span>{done}</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {open.map((i) => (
                        <li key={i.label}>
                            <Link to={i.to} className="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 active:bg-slate-100">
                                <span className={`rounded-xl p-2 ${TONES[i.tone] || TONES.brand}`}><Icon name={i.icon} className="h-5 w-5" /></span>
                                <span className="min-w-0 flex-1"><span className="block text-[15px] font-semibold text-slate-900">{i.label}</span>{i.hint && <span className="block truncate text-xs text-slate-500">{i.hint}</span>}</span>
                                <span className="text-lg font-extrabold text-slate-900">{i.count}</span>
                                <Icon name="chevron" className="h-4 w-4 text-slate-300" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** Big tap targets for the most common tasks. */
export function QuickActions({ actions }) {
    return (
        <div className="grid grid-cols-4 gap-2" role="navigation" aria-label="Quick actions">
            {actions.map((a) => (
                <Link key={a.label} to={a.to} className={`flex flex-col items-center gap-1.5 rounded-2xl p-3 text-center text-xs font-semibold transition ${a.primary ? 'bg-brand-600 text-white hover:bg-brand-700' : 'card text-slate-700 hover:border-brand-500'}`}>
                    <Icon name={a.icon} className="h-5 w-5" />{a.label}
                </Link>
            ))}
        </div>
    );
}

/** Compact key-number strip: [[label, value], …] */
export function KpiStrip({ items }) {
    return (
        <dl className="card grid grid-cols-2 divide-slate-100 sm:grid-cols-4 sm:divide-x [&>div:nth-child(-n+2)]:border-b [&>div:nth-child(-n+2)]:border-slate-100 sm:[&>div:nth-child(-n+2)]:border-b-0">
            {items.map(([l, v]) => (
                <div key={l} className="p-4">
                    <dt className="text-xs font-medium text-slate-500">{l}</dt>
                    <dd className="mt-0.5 truncate text-xl font-extrabold text-slate-900">{v}</dd>
                </div>
            ))}
        </dl>
    );
}

/** Phone list card with a title line, meta line, status and an optional footer. */
export function ListCard({ to, title, meta, status, children, footer }) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1"><p className="line-clamp-2 font-semibold leading-snug text-slate-900">{title}</p>{meta && <p className="mt-0.5 truncate text-xs text-slate-500">{meta}</p>}</div>
                {status}
            </div>
            {children && <div className="mt-2 text-sm text-slate-700">{children}</div>}
        </>
    );
    return (
        <div className="card overflow-hidden">
            {to ? <Link to={to} className="block p-4 active:bg-slate-50">{body}</Link> : <div className="p-4">{body}</div>}
            {footer && <div className="border-t border-slate-100 px-4 py-3">{footer}</div>}
        </div>
    );
}
