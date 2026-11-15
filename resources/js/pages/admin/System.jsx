import { useEffect, useState } from 'react';
import BarChart from '../../components/BarChart';
import DataTable, { Toolbar } from '../../components/DataTable';
import { Alert, Async, Badge, Button, Checkbox, ConfirmDialog, Field, Input, Modal, PageHeader, Section, Select, Stat, StatusBadge, Tabs, Textarea } from '../../components/ui';
import { DateRange, defaultRange } from '../merchant/Other';
import { del, post, put } from '../../lib/api';
import { useAction, useApi, useDebounced, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { dateTime, label, minutes, money } from '../../lib/format';

const TAXONOMY = {
    categories: { title: 'Product categories', fields: [['name', 'Name'], ['icon', 'Icon key'], ['sort_order', 'Sort order', 'number']] },
    brands: { title: 'Brands', fields: [['name', 'Name']] },
    'phone-models': { title: 'Phone models', fields: [['name', 'Model name'], ['brand_id', 'Brand', 'brand'], ['release_year', 'Release year', 'number']] },
    areas: { title: 'Areas', fields: [['name', 'Area name'], ['city', 'City'], ['pincode', 'Pincode']] },
    'repair-categories': { title: 'Repair categories', fields: [['name', 'Name'], ['icon', 'Icon key'], ['sort_order', 'Sort order', 'number']] },
};

export function Catalogue() {
    const meta = useMeta();
    const toast = useToast();
    const [type, setType] = useState('categories');
    const [q, setQ] = useState('');
    const [brand, setBrand] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const [edit, setEdit] = useState(null);
    const [remove, setRemove] = useState(null);
    const state = useApi(`/admin/taxonomy/${type}`, { q: dq, page, brand_id: type === 'phone-models' ? brand : '' });
    const cfg = TAXONOMY[type];
    const save = useAction(() => (edit.id ? put(`/admin/taxonomy/${type}/${edit.id}`, edit) : post(`/admin/taxonomy/${type}`, edit)), { onSuccess: () => { toast('Saved'); setEdit(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    const destroy = useAction(() => del(`/admin/taxonomy/${type}/${remove.id}`), { onSuccess: () => { toast('Deleted'); setRemove(null); state.reload(); }, onError: (e) => { toast(e.message, 'error'); setRemove(null); } });
    const toggle = useAction((row) => put(`/admin/taxonomy/${type}/${row.id}`, { ...row, is_active: !row.is_active }), { onSuccess: () => state.reload(), onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Catalogue management" actions={<Button onClick={() => setEdit({ is_active: true, city: 'Bhatkal' })}>Add {cfg.title.toLowerCase().replace(/s$/, '')}</Button>} />
            <Tabs className="mb-3" value={type} onChange={(t) => { setType(t); setPage(1); setQ(''); }} tabs={Object.entries(TAXONOMY).map(([k, v]) => [k, v.title])} />
            <Toolbar><Input type="search" placeholder="Search" value={q} onChange={(e) => setQ(e.target.value)} />{type === 'phone-models' && <Select value={brand} onChange={(e) => setBrand(e.target.value)} placeholder="All brands" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} />}</Toolbar>
            <DataTable state={state} onPage={setPage} empty="No entries" columns={[
                { key: 'name', label: 'Name', render: (r) => <b>{r.name}</b> },
                type === 'phone-models' && { key: 'brand', label: 'Brand', render: (r) => r.brand?.name },
                type === 'phone-models' && { key: 'release_year', label: 'Year' },
                type === 'areas' && { key: 'city', label: 'City' },
                type === 'categories' && { key: 'products_count', label: 'Products' },
                type === 'brands' && { key: 'phone_models_count', label: 'Models' },
                { key: 'slug', label: 'Slug', render: (r) => <span className="font-mono text-xs text-slate-500">{r.slug}</span> },
                { key: 'is_active', label: 'Status', render: (r) => <button onClick={() => toggle.run(r)}><StatusBadge status={r.is_active ? 'active' : 'inactive'} /></button> },
                { key: 'a', label: '', render: (r) => <div className="flex gap-1"><Button size="sm" variant="secondary" onClick={() => setEdit(r)}>Edit</Button><Button size="sm" variant="ghost" onClick={() => setRemove(r)}>Delete</Button></div> },
            ].filter(Boolean)} />
            <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? `Edit ${edit.name}` : `New entry · ${cfg.title}`} footer={<Button busy={save.busy} onClick={() => save.run()}>Save</Button>}>
                {edit && <div className="space-y-3">
                    {cfg.fields.map(([k, l, t]) => (
                        <Field key={k} label={l} error={save.errors[k]}>
                            {t === 'brand' ? <Select value={edit[k] || ''} onChange={(e) => setEdit({ ...edit, [k]: e.target.value })} placeholder="Select brand" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} />
                                : <Input type={t || 'text'} value={edit[k] ?? ''} onChange={(e) => setEdit({ ...edit, [k]: e.target.value })} />}
                        </Field>
                    ))}
                    <Checkbox label="Active (visible to users)" checked={!!edit.is_active} onChange={(e) => setEdit({ ...edit, is_active: e.target.checked })} />
                </div>}
            </Modal>
            <ConfirmDialog open={!!remove} onClose={() => setRemove(null)} busy={destroy.busy} tone="danger" title={`Delete ${remove?.name}?`} message="Entries in use cannot be deleted — deactivate them instead." confirmText="Delete" onConfirm={() => destroy.run()} />
        </div>
    );
}

export function Templates() {
    const toast = useToast();
    const state = useApi('/admin/notification-templates');
    const [edit, setEdit] = useState(null);
    const [preview, setPreview] = useState('');
    const [remove, setRemove] = useState(null);
    const save = useAction(() => (edit.id ? put(`/admin/notification-templates/${edit.id}`, edit) : post('/admin/notification-templates', edit)), { onSuccess: () => { toast('Template saved'); setEdit(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    const destroy = useAction(() => del(`/admin/notification-templates/${remove.id}`), { onSuccess: () => { setRemove(null); state.reload(); } });
    useEffect(() => {
        if (!edit?.body) { setPreview(''); return; }
        const t = setTimeout(() => post('/admin/notification-templates/preview', { body: edit.body }).then((r) => setPreview(r.preview)).catch(() => {}), 300);
        return () => clearTimeout(t);
    }, [edit?.body]);
    return (
        <Async state={state}>
            {({ templates, defaults }) => (
                <div className="space-y-4">
                    <PageHeader title="Notification templates" actions={<Button onClick={() => setEdit({ key: '', channel: 'whatsapp', name: '', body: '', is_active: true })}>New template</Button>} />
                    <Alert tone="blue" icon="message" title="WhatsApp click-to-chat vs automated messages">Templates on the WhatsApp channel prefill the click-to-chat links customers and shops open themselves. They are not sent automatically — automated delivery needs an approved WhatsApp Business provider (Phase 3).</Alert>
                    <DataTable state={{ data: templates }} empty="No custom templates" columns={[
                        { key: 'name', label: 'Template', render: (t) => <><b>{t.name}</b><span className="block font-mono text-xs text-slate-400">{t.key}</span></> },
                        { key: 'channel', label: 'Channel', render: (t) => <Badge tone="teal">{label(t.channel)}</Badge> }, { key: 'body', label: 'Message', render: (t) => <span className="block max-w-md text-slate-700">{t.body}</span> },
                        { key: 'is_active', label: 'Status', render: (t) => <StatusBadge status={t.is_active ? 'active' : 'inactive'} /> },
                        { key: 'a', label: '', render: (t) => <div className="flex gap-1"><Button size="sm" variant="secondary" onClick={() => setEdit(t)}>Edit</Button><Button size="sm" variant="ghost" onClick={() => setRemove(t)}>Delete</Button></div> },
                    ]} />
                    <Section title="Built-in defaults (used when no active template exists)">
                        {Object.entries(defaults).map(([ch, items]) => (
                            <div key={ch} className="mb-3"><p className="mb-1 text-xs font-bold uppercase text-slate-500">{ch}</p><ul className="space-y-1 text-sm">{Object.entries(items).map(([k, b]) => <li key={k}><span className="font-mono text-xs text-brand-700">{k}</span> — {b}</li>)}</ul></div>
                        ))}
                    </Section>
                    <Modal open={!!edit} onClose={() => setEdit(null)} size="max-w-xl" title={edit?.id ? 'Edit template' : 'New template'} footer={<Button busy={save.busy} onClick={() => save.run()}>Save</Button>}>
                        {edit && <div className="space-y-3">
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Key" error={save.errors.key} hint="e.g. product_enquiry"><Input value={edit.key} onChange={(e) => setEdit({ ...edit, key: e.target.value })} /></Field>
                                <Field label="Channel"><Select value={edit.channel} onChange={(e) => setEdit({ ...edit, channel: e.target.value })} options={['whatsapp', 'sms', 'email', 'in_app']} /></Field>
                            </div>
                            <Field label="Name" error={save.errors.name}><Input value={edit.name} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
                            <Field label="Message" error={save.errors.body} hint="Placeholders: {product_name} {compatible_model} {order_reference} {shop_name} {amount} {device} {problem} {repair_reference} {status_label}"><Textarea rows={4} value={edit.body} onChange={(e) => setEdit({ ...edit, body: e.target.value })} /></Field>
                            {preview && <div className="rounded-xl bg-[#e7fbe9] p-3 text-sm text-slate-800"><p className="mb-1 text-xs font-semibold text-slate-500">Preview</p>{preview}</div>}
                            <Checkbox label="Active" checked={!!edit.is_active} onChange={(e) => setEdit({ ...edit, is_active: e.target.checked })} />
                        </div>}
                    </Modal>
                    <ConfirmDialog open={!!remove} onClose={() => setRemove(null)} busy={destroy.busy} tone="danger" title="Delete template?" message="The built-in default will be used instead." confirmText="Delete" onConfirm={() => destroy.run()} />
                </div>
            )}
        </Async>
    );
}

export function Reports() {
    const [range, setRange] = useState(defaultRange);
    const [period, setPeriod] = useState('day');
    const state = useApi('/admin/reports', { ...range, period });
    return (
        <div className="space-y-4">
            <PageHeader title="Reports & analytics" actions={<div className="flex flex-wrap items-end gap-2"><DateRange value={range} onChange={setRange} /><Field label="Group by"><Select value={period} onChange={(e) => setPeriod(e.target.value)} options={['day', 'week', 'month']} /></Field></div>} />
            <Async state={state}>
                {(r) => (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat label="Orders" value={r.orders.total} hint={`${r.orders.completed} completed`} icon="bag" />
                            <Stat label="Cancellation rate" value={`${r.orders.cancellation_rate}%`} hint={`${r.orders.cancelled} cancelled/declined`} icon="x" tone="red" />
                            <Stat label="Gross revenue" value={money(r.revenue.gross)} icon="rupee" tone="blue" />
                            <Stat label="Commission" value={money(r.revenue.commission)} hint={`Refunds ${money(r.revenue.refunds)}`} icon="rupee" tone="green" />
                            <Stat label="Active merchants" value={r.active_merchants} icon="store" />
                            <Stat label="Merchant response" value={minutes(r.merchant_response_minutes)} hint={`Quote in ${minutes(r.merchant_quote_minutes)}`} icon="clock" tone="blue" />
                            <Stat label="Technician response" value={minutes(r.technician_response_minutes)} icon="clock" tone="blue" />
                            <Stat label="Repair turnaround" value={r.repairs.avg_turnaround_hours ? `${r.repairs.avg_turnaround_hours} h` : '—'} hint={`${r.repairs.completed}/${r.repairs.total} completed`} icon="wrench" />
                            <Stat label="Warranty claims" value={r.repairs.warranty_claims} icon="shield" tone="amber" />
                            <Stat label="Complaints" value={r.complaints.total} hint={`${r.complaints.refunds_requested} refunds raised`} icon="ticket" tone="red" />
                            <Stat label="Repeat customers" value={`${r.customer_retention.repeat_rate}%`} hint={`${r.customer_retention.repeat_customers} of ${r.customer_retention.customers_with_completed_orders}`} icon="user" tone="green" />
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <BarChart title={`Orders per ${period}`} data={r.orders.series} xKey="bucket" yKey="orders" />
                            <BarChart title={`Completed revenue per ${period}`} data={r.orders.series} xKey="bucket" yKey="revenue" format={money} />
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Section title="Product demand"><DataTable state={{ data: r.product_demand }} empty="No demand recorded" columns={[{ key: 'name', label: 'Product', render: (p) => <><b>{p.name}</b><span className="block text-xs text-slate-400">{p.shop?.name}</span></> }, { key: 'enquiries', label: 'Enquiries' }, { key: 'ordered', label: 'Units ordered' }]} /></Section>
                            <Section title="Searches with no results" action={<Badge>Catalogue gaps</Badge>}><DataTable state={{ data: r.no_result_searches }} rowKey="query" empty="Every search found something" columns={[{ key: 'query', label: 'Search term', render: (s) => <b>{s.query}</b> }, { key: 'searches', label: 'Times' }, { key: 'last', label: 'Last', render: (s) => dateTime(s.last_searched) }]} /></Section>
                        </div>
                        <Section title="Complaints by category"><DataTable state={{ data: r.complaints.by_category }} rowKey="category" empty="No complaints in this period" columns={[{ key: 'category', label: 'Category', render: (c) => label(c.category) }, { key: 'total', label: 'Tickets' }]} /></Section>
                    </>
                )}
            </Async>
        </div>
    );
}

export function SearchInsights() {
    const [noResults, setNoResults] = useState(true);
    const [page, setPage] = useState(1);
    const state = useApi('/admin/search-logs', { no_results: noResults ? 1 : '', page });
    return (
        <div>
            <PageHeader title="Search insights" subtitle="What Bhatkal is searching for. Share no-result terms with merchants to fill catalogue gaps." />
            <Toolbar><Checkbox label="Only searches with no results" checked={noResults} onChange={(e) => { setNoResults(e.target.checked); setPage(1); }} /></Toolbar>
            <DataTable state={state} onPage={setPage} rowKey="query" empty="No searches logged" emptyIcon="search" columns={[{ key: 'query', label: 'Search term', render: (s) => <b>{s.query}</b> }, { key: 'searches', label: 'Searches' }, { key: 'min_results', label: 'Min results' }, { key: 'last', label: 'Last searched', render: (s) => dateTime(s.last_searched) }]} />
        </div>
    );
}

export function ActivityLogs() {
    const [action, setAction] = useState('');
    const da = useDebounced(action);
    const [page, setPage] = useState(1);
    const [open, setOpen] = useState(null);
    const state = useApi('/admin/activity-logs', { action: da, page });
    return (
        <div>
            <PageHeader title="Admin activity log" subtitle="Audit trail of important administrative actions. Entries cannot be edited." />
            <Toolbar><Input type="search" placeholder="Filter by action, e.g. shop. or refund." value={action} onChange={(e) => setAction(e.target.value)} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No activity" emptyIcon="shield" onRowClick={setOpen} columns={[
                { key: 'created_at', label: 'When', render: (l) => dateTime(l.created_at) }, { key: 'user', label: 'By', render: (l) => l.user?.name || 'System' },
                { key: 'action', label: 'Action', render: (l) => <span className="font-mono text-xs">{l.action}</span> }, { key: 'description', label: 'Description' }, { key: 'ip', label: 'IP', render: (l) => <span className="text-xs text-slate-400">{l.ip}</span> },
            ]} />
            <Modal open={!!open} onClose={() => setOpen(null)} title={open?.action}>{open && <pre className="overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs">{JSON.stringify({ subject: `${open.subject_type || ''} #${open.subject_id || ''}`, properties: open.properties, user_agent: open.user_agent }, null, 2)}</pre>}</Modal>
        </div>
    );
}

export function Settings() {
    const toast = useToast();
    const state = useApi('/admin/settings');
    const [s, setS] = useState(null);
    useEffect(() => { if (state.data) setS(state.data.settings); }, [state.data]);
    const save = useAction(() => put('/admin/settings', s), { onSuccess: () => toast('Settings saved'), onError: (e) => toast(e.message, 'error') });
    const f = (k, type = 'text') => ({ type, value: s?.[k] ?? '', onChange: (e) => setS({ ...s, [k]: type === 'number' ? Number(e.target.value) : e.target.value }) });
    return (
        <Async state={state}>
            {() => s && (
                <div className="mx-auto max-w-2xl">
                    <PageHeader title="System settings" />
                    <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.run(); }}>
                        <Section title="Platform">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Platform name" error={save.errors.platform_name}><Input {...f('platform_name')} /></Field>
                                <Field label="Default city"><Input {...f('default_city')} /></Field>
                                <Field label="Support WhatsApp/phone (with 91)" error={save.errors.support_phone}><Input {...f('support_phone')} /></Field>
                                <Field label="Support email" error={save.errors.support_email}><Input {...f('support_email', 'email')} /></Field>
                            </div>
                        </Section>
                        <Section title="Marketplace rules">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Stock is ‘stale’ after (days)" hint="Merchants get reminders; customers see the freshness"><Input {...f('stock_stale_days', 'number')} /></Field>
                                <Field label="Max pending order requests per customer"><Input {...f('max_open_order_requests_per_customer', 'number')} /></Field>
                                <Checkbox className="sm:col-span-2" label="Publish reviews without moderation" checked={!!s.reviews_auto_approve} onChange={(e) => setS({ ...s, reviews_auto_approve: e.target.checked })} />
                            </div>
                        </Section>
                        <Button size="lg" busy={save.busy}>Save settings</Button>
                    </form>
                </div>
            )}
        </Async>
    );
}
