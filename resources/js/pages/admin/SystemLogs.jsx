import { useEffect, useState } from 'react';
import Icon from '../../components/icons';
import { Toolbar } from '../../components/DataTable';
import { Alert, Async, Badge, Button, ConfirmDialog, Empty, Input, PageHeader, Pagination, Select, Tabs } from '../../components/ui';
import { del, fetchPrivate } from '../../lib/api';
import { useAction, useApi, useDebounced } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago, label } from '../../lib/format';

const LEVEL_TONE = { emergency: 'red', alert: 'red', critical: 'red', error: 'red', warning: 'amber', notice: 'blue', info: 'teal', debug: 'slate' };
const size = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

function Entry({ e }) {
    const [open, setOpen] = useState(false);
    const hasDetail = !!e.context;
    return (
        <li className="border-b border-slate-100 last:border-0">
            <button type="button" onClick={() => hasDetail && setOpen((o) => !o)} className={`flex w-full items-start gap-3 px-4 py-3 text-left ${hasDetail ? 'hover:bg-slate-50' : 'cursor-default'}`} aria-expanded={hasDetail ? open : undefined}>
                <Badge tone={LEVEL_TONE[e.level] || 'slate'} className="mt-0.5 w-20 justify-center">{label(e.level)}</Badge>
                <span className="min-w-0 flex-1">
                    <span className="block break-words font-mono text-[13px] text-slate-800">{e.message}</span>
                    <span className="mt-0.5 block text-xs text-slate-400">{new Date(e.datetime.replace(' ', 'T')).toLocaleString('en-IN')} · {e.env}{hasDetail && (open ? ' · hide details' : ' · show details')}</span>
                </span>
                {hasDetail && <Icon name="chevron" className={`mt-1 h-4 w-4 shrink-0 text-slate-400 transition ${open ? 'rotate-90' : ''}`} />}
            </button>
            {open && <pre className="mx-4 mb-3 max-h-96 overflow-auto rounded-xl bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{e.context}</pre>}
        </li>
    );
}

export default function SystemLogs() {
    const toast = useToast();
    const files = useApi('/admin/system-logs');
    const [file, setFile] = useState('');
    const [level, setLevel] = useState('');
    const [q, setQ] = useState('');
    const dq = useDebounced(q, 400);
    const [page, setPage] = useState(1);
    const [confirmClear, setConfirmClear] = useState(false);
    useEffect(() => { if (!file && files.data?.files?.length) setFile(files.data.files[0].name); }, [files.data, file]);
    const entries = useApi(file ? `/admin/system-logs/${encodeURIComponent(file)}` : null, { level, q: dq, page });
    const clear = useAction(() => del(`/admin/system-logs/${encodeURIComponent(file)}`), { onSuccess: (r) => { toast(r.message); setConfirmClear(false); entries.reload(); files.reload(); }, onError: (e) => toast(e.message, 'error') });

    const download = async () => {
        let blob;
        try { blob = await fetchPrivate(`/admin/system-logs/${encodeURIComponent(file)}/download`); } catch { return toast('Download failed', 'error'); }
        const url = URL.createObjectURL(blob);
        Object.assign(document.createElement('a'), { href: url, download: file }).click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    return (
        <div>
            <PageHeader title="System logs" subtitle="Application errors and events from storage/logs. Newest first."
                actions={file && <><Button variant="secondary" onClick={() => entries.reload()}><Icon name="refresh" className="h-4 w-4" />Refresh</Button><Button variant="secondary" onClick={download}>Download</Button><Button variant="ghost" onClick={() => setConfirmClear(true)}>Clear</Button></>} />
            <Async state={files}>
                {(f) => f.files.length === 0 ? <Empty icon="file" title="No log files yet">Laravel creates storage/logs/laravel.log the first time something is logged.</Empty> : (
                    <>
                        <Toolbar>
                            {f.files.length > 1 && <Select aria-label="Log file" value={file} onChange={(e) => { setFile(e.target.value); setPage(1); }} options={f.files.map((x) => ({ value: x.name, label: `${x.name} (${size(x.size)})` }))} />}
                            <Input type="search" placeholder="Search messages and stack traces" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} />
                            {f.files.length === 1 && <span className="text-sm text-slate-500">{f.files[0].name} · {size(f.files[0].size)} · updated {ago(f.files[0].modified_at)}</span>}
                        </Toolbar>
                        {entries.data && (
                            <Tabs className="mb-3" value={level} onChange={(l) => { setLevel(l); setPage(1); }}
                                tabs={[['', 'All'], ...Object.entries(entries.data.counts).filter(([, n]) => n > 0).map(([l, n]) => [l, label(l), n])]} />
                        )}
                        {entries.data?.truncated && <div className="mb-3"><Alert tone="amber">This file is large — only the most recent {size(f.max_bytes)} is shown. Download it for the full history, or clear it.</Alert></div>}
                        <Async state={entries}>
                            {(d) => d.data.length === 0 ? <Empty icon="check" title={q || level ? 'No matching entries' : 'Log is empty'}>{q || level ? 'Try another level or search term.' : 'Nothing has been logged yet.'}</Empty> : (
                                <>
                                    <ul className="card overflow-hidden">{d.data.map((e, i) => <Entry key={`${d.current_page}-${i}-${e.datetime}`} e={e} />)}</ul>
                                    <Pagination meta={d} onPage={setPage} />
                                </>
                            )}
                        </Async>
                        <p className="mt-4 text-xs text-slate-400">Login codes in SMS log lines are masked. Downloads and clearing are recorded in the audit log.</p>
                    </>
                )}
            </Async>
            <ConfirmDialog open={confirmClear} onClose={() => setConfirmClear(false)} busy={clear.busy} tone="danger" title={`Clear ${file}?`} message="All entries in this file will be deleted. Download it first if you may need it." confirmText="Clear log" onConfirm={() => clear.run()} />
        </div>
    );
}
