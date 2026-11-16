import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import Icon from './icons';
import { STATUS, label, whatsappIcon } from '../lib/format';
import { BASE } from '../lib/api';

export function Button({ as: As = 'button', variant = 'primary', size = 'md', busy, className = '', children, ...props }) {
    const v = {
        primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm',
        secondary: 'bg-white text-slate-800 border border-slate-300 hover:bg-slate-50',
        ghost: 'text-slate-700 hover:bg-slate-100',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        accent: 'bg-accent-500 text-slate-900 hover:bg-accent-600',
        whatsapp: 'bg-[#25D366] text-white hover:bg-[#1ebe5a]',
    }[variant];
    const s = { sm: 'px-3 py-1.5 text-sm rounded-lg', md: 'px-4 py-2.5 text-[15px] rounded-xl', lg: 'px-5 py-3 text-base rounded-xl' }[size];
    const disabled = busy || props.disabled;
    return (
        <As className={`inline-flex items-center justify-center gap-2 font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${v} ${s} ${className}`} {...props} disabled={As === 'button' ? disabled : undefined} aria-busy={busy || undefined}>
            {busy && <Spinner className="h-4 w-4" />}
            {children}
        </As>
    );
}

export function WhatsAppButton({ href, children = 'Chat on WhatsApp', size = 'md', className = '' }) {
    if (!href) return null;
    return (
        <Button as="a" href={href} target="_blank" rel="noopener noreferrer" variant="whatsapp" size={size} className={className}>
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true"><path d={whatsappIcon} /></svg>
            {children}
        </Button>
    );
}

export const Spinner = ({ className = 'h-6 w-6' }) => (
    <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke="currentColor" strokeOpacity=".25" strokeWidth="3" />
        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
);

export function Loading({ label: text = 'Loading…' }) {
    return <div className="flex items-center justify-center gap-3 py-16 text-slate-500" role="status"><Spinner /> <span className="text-sm">{text}</span></div>;
}

export function Skeleton({ className = 'h-4 w-full' }) {
    return <div className={`animate-pulse rounded-lg bg-slate-200/80 ${className}`} />;
}

export function CardGridSkeleton({ count = 8 }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="card overflow-hidden"><Skeleton className="aspect-square rounded-none" /><div className="space-y-2 p-3"><Skeleton className="h-3 w-3/4" /><Skeleton className="h-4 w-1/2" /></div></div>
            ))}
        </div>
    );
}

export function ErrorState({ error, onRetry }) {
    return (
        <div className="card flex flex-col items-center gap-3 px-6 py-12 text-center">
            <div className="rounded-full bg-red-50 p-3 text-red-600"><Icon name="alert" className="h-6 w-6" /></div>
            <p className="font-semibold text-slate-900">{error?.status === 404 ? 'Not found' : error?.status === 403 ? 'No access' : 'Something went wrong'}</p>
            <p className="max-w-sm text-sm text-slate-500">{error?.message}</p>
            {onRetry && error?.status !== 404 && error?.status !== 403 && <Button variant="secondary" size="sm" onClick={onRetry}><Icon name="refresh" className="h-4 w-4" />Try again</Button>}
            {(error?.status === 0 || error?.status >= 500) && <a href={`${BASE}/status`} className="text-xs font-semibold text-brand-700 underline">Check server status</a>}
        </div>
    );
}

export function Empty({ icon = 'box', title, children, action }) {
    return (
        <div className="card flex flex-col items-center gap-2 px-6 py-12 text-center">
            <div className="mb-1 rounded-2xl bg-brand-50 p-3.5 text-brand-600"><Icon name={icon} className="h-7 w-7" /></div>
            <p className="font-semibold text-slate-900">{title}</p>
            {children && <div className="max-w-md text-sm text-slate-500">{children}</div>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}

/** Wraps a useApi() result: shows loading / error / content. */
export function Async({ state, children, skeleton }) {
    if (state.loading && !state.data) return skeleton || <Loading />;
    if (state.error) return <ErrorState error={state.error} onRetry={state.reload} />;
    if (!state.data) return null;
    return children(state.data);
}

export function Badge({ tone = 'slate', children, className = '' }) {
    const t = {
        slate: 'bg-slate-100 text-slate-700', green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15', amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
        red: 'bg-red-50 text-red-700 ring-red-600/15', blue: 'bg-sky-50 text-sky-700 ring-sky-600/15', teal: 'bg-brand-50 text-brand-700 ring-brand-600/15',
    }[tone];
    return <span className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ring-transparent ${t} ${className}`}>{children}</span>;
}

export function StatusBadge({ status }) {
    const [text, tone] = STATUS[status] || [label(status), 'slate'];
    return <Badge tone={tone}>{text}</Badge>;
}

export function Stars({ value = 0, count, size = 'h-4 w-4' }) {
    const v = Number(value) || 0;
    return (
        <span className="inline-flex items-center gap-1" aria-label={`Rated ${v.toFixed(1)} out of 5`}>
            <span className="flex text-amber-400">{[1, 2, 3, 4, 5].map((i) => <Icon key={i} name="star" className={size} fill={i <= Math.round(v) ? 'currentColor' : 'none'} strokeWidth={1.5} />)}</span>
            {count !== undefined && <span className="text-xs text-slate-500">{count ? `${v.toFixed(1)} (${count})` : 'No ratings yet'}</span>}
        </span>
    );
}

export function StarInput({ value, onChange, name = 'Rating' }) {
    return (
        <div className="flex gap-1" role="radiogroup" aria-label={name}>
            {[1, 2, 3, 4, 5].map((i) => (
                <button key={i} type="button" role="radio" aria-checked={value === i} aria-label={`${i} star`} onClick={() => onChange(i)} className="text-amber-400 transition hover:scale-110">
                    <Icon name="star" className="h-8 w-8" fill={i <= value ? 'currentColor' : 'none'} strokeWidth={1.5} />
                </button>
            ))}
        </div>
    );
}

export function Field({ label: text, error, hint, children, required, className = '' }) {
    return (
        <label className={`block ${className}`}>
            {text && <span className="label">{text}{required && <span className="text-red-500"> *</span>}</span>}
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-red-600">{Array.isArray(error) ? error[0] : error}</span>}
        </label>
    );
}

export const Input = ({ className = '', ...p }) => <input className={`input ${className}`} {...p} />;
export const Textarea = ({ className = '', rows = 3, ...p }) => <textarea rows={rows} className={`input ${className}`} {...p} />;
export function Select({ options = [], placeholder, className = '', ...p }) {
    return (
        <select className={`input pr-9 ${className}`} {...p}>
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {options.map((o) => (typeof o === 'object' ? <option key={o.value} value={o.value}>{o.label}</option> : <option key={o} value={o}>{label(o)}</option>))}
        </select>
    );
}
export function Checkbox({ label: text, className = '', ...p }) {
    return (
        <label className={`flex cursor-pointer items-start gap-2.5 text-sm text-slate-700 ${className}`}>
            <input type="checkbox" className="mt-0.5 h-4.5 w-4.5 rounded border-slate-300 text-brand-600 accent-brand-600" {...p} />
            <span>{text}</span>
        </label>
    );
}

export function Modal({ open, onClose, title, children, footer, size = 'max-w-lg' }) {
    const ref = useRef();
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        ref.current?.querySelector('input,textarea,select,button')?.focus();
        return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
    }, [open, onClose]);
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-[2px] sm:items-center sm:p-4" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
            <div ref={ref} role="dialog" aria-modal="true" aria-label={title} className={`flex max-h-[92vh] w-full ${size} flex-col rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl`}>
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 className="text-lg font-bold text-slate-900">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Close"><Icon name="x" /></button>
                </div>
                <div className="overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-3.5">{footer}</div>}
            </div>
        </div>
    );
}

/** Confirmation dialog, optionally collecting a reason. onConfirm(reason) may return a promise. */
export function ConfirmDialog({ open, onClose, onConfirm, title, message, confirmText = 'Confirm', tone = 'primary', reason, reasonLabel = 'Reason', busy }) {
    const [text, setText] = useState('');
    useEffect(() => { if (open) setText(''); }, [open]);
    return (
        <Modal open={open} onClose={onClose} title={title}
            footer={<><Button variant="secondary" onClick={onClose}>Back</Button><Button variant={tone} busy={busy} disabled={reason && !text.trim()} onClick={() => onConfirm(text)}>{confirmText}</Button></>}>
            {message && <p className="text-[15px] text-slate-600">{message}</p>}
            {reason && <Field label={reasonLabel} className="mt-4" required><Textarea value={text} onChange={(e) => setText(e.target.value)} rows={3} /></Field>}
        </Modal>
    );
}

export function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <nav className="mt-6 flex items-center justify-between gap-3 text-sm" aria-label="Pagination">
            <span className="text-slate-500">Page {meta.current_page} of {meta.last_page} · {meta.total} results</span>
            <div className="flex gap-2">
                <Button variant="secondary" size="sm" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Previous</Button>
                <Button variant="secondary" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next</Button>
            </div>
        </nav>
    );
}

export function Tabs({ tabs, value, onChange, className = '' }) {
    return (
        <div className={`no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 ${className}`} role="tablist">
            {tabs.map((t) => {
                const [v, text, count] = Array.isArray(t) ? t : [t, label(t)];
                return (
                    <button key={v} role="tab" aria-selected={value === v} onClick={() => onChange(v)} className={`chip ${value === v ? 'chip-active' : ''}`}>
                        {text}{count !== undefined && <span className={`rounded-full px-1.5 text-xs ${value === v ? 'bg-white/20' : 'bg-slate-100'}`}>{count}</span>}
                    </button>
                );
            })}
        </div>
    );
}

export function Stat({ label: text, value, icon, tone = 'brand', to, hint }) {
    const tones = { brand: 'bg-brand-50 text-brand-600', amber: 'bg-amber-50 text-amber-600', red: 'bg-red-50 text-red-600', blue: 'bg-sky-50 text-sky-600', slate: 'bg-slate-100 text-slate-600', green: 'bg-emerald-50 text-emerald-600' };
    const body = (
        <div className="card flex h-full items-start gap-3 p-4 transition hover:border-slate-300">
            {icon && <div className={`rounded-xl p-2.5 ${tones[tone]}`}><Icon name={icon} className="h-5 w-5" /></div>}
            <div className="min-w-0">
                <p className="text-xs font-medium text-slate-500">{text}</p>
                <p className="mt-0.5 truncate text-xl font-bold text-slate-900">{value}</p>
                {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
            </div>
        </div>
    );
    return to ? <Link to={to} className="block">{body}</Link> : body;
}

export function PageHeader({ title, subtitle, actions, back }) {
    return (
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div className="min-w-0">
                {back && <Link to={back} className="mb-1 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-brand-700"><Icon name="back" className="h-4 w-4" />Back</Link>}
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-900">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

export function Section({ title, action, children, className = '' }) {
    return (
        <section className={`card p-5 ${className}`}>
            {(title || action) && <div className="mb-4 flex items-center justify-between gap-3"><h2 className="font-bold text-slate-900">{title}</h2>{action}</div>}
            {children}
        </section>
    );
}

export function DL({ items }) {
    return (
        <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            {items.filter(Boolean).map(([k, v]) => (
                <div key={k}><dt className="text-slate-500">{k}</dt><dd className="font-medium text-slate-900">{v ?? '—'}</dd></div>
            ))}
        </dl>
    );
}

/** Vertical status timeline built from *_status_histories rows. */
export function Timeline({ items }) {
    return (
        <ol className="relative space-y-4 border-l-2 border-slate-100 pl-5">
            {items.map((h) => (
                <li key={h.id} className="relative">
                    <span className="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white bg-brand-500 ring-2 ring-brand-100" />
                    <div className="flex flex-wrap items-center gap-2">{h.from_status !== h.to_status ? <StatusBadge status={h.to_status} /> : <Badge>Update</Badge>}<span className="text-xs text-slate-500">{new Date(h.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })}</span></div>
                    {h.note && <p className="mt-1 text-sm text-slate-700">{h.note}</p>}
                    {h.user && <p className="text-xs text-slate-400">by {h.user.name} ({label(h.user.role)})</p>}
                </li>
            ))}
        </ol>
    );
}

export function FileDrop({ multiple, accept = 'image/*', files, onChange, label: text = 'Upload photos', hint }) {
    const list = files ? (Array.isArray(files) ? files : [files]) : [];
    return (
        <div>
            <label className="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600 transition hover:border-brand-500 hover:bg-brand-50">
                <Icon name="upload" className="h-6 w-6 text-slate-400" />
                <span className="font-semibold text-brand-700">{text}</span>
                {hint && <span className="text-xs text-slate-500">{hint}</span>}
                <input type="file" className="sr-only" multiple={multiple} accept={accept} onChange={(e) => onChange(multiple ? [...list, ...e.target.files] : e.target.files[0])} />
            </label>
            {list.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-2">
                    {list.map((f, i) => (
                        <li key={i} className="flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                            {f.name}<button type="button" aria-label={`Remove ${f.name}`} onClick={() => onChange(multiple ? list.filter((_, j) => j !== i) : null)}><Icon name="x" className="h-3.5 w-3.5" /></button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function Alert({ tone = 'amber', title, children, icon = 'alert' }) {
    const t = { amber: 'border-amber-200 bg-amber-50 text-amber-900', red: 'border-red-200 bg-red-50 text-red-900', blue: 'border-sky-200 bg-sky-50 text-sky-900', green: 'border-emerald-200 bg-emerald-50 text-emerald-900' }[tone];
    return (
        <div className={`flex gap-3 rounded-2xl border p-4 text-sm ${t}`} role="note">
            <Icon name={icon} className="mt-0.5 h-5 w-5 shrink-0" />
            <div>{title && <p className="font-semibold">{title}</p>}{children && <div className="mt-0.5 opacity-90">{children}</div>}</div>
        </div>
    );
}
